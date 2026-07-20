'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it, afterEach } = require('node:test');

const servicePath = path.join(__dirname, '../../assets/js/services/trainingService.js');
const serviceSrc = fs.readFileSync(servicePath, 'utf8');

function loadService(fetchImpl, config) {
    globalThis.AA_TRAINING_DATA = config || {
        ajaxUrl: 'https://agenda.test/wp-admin/admin-ajax.php',
        nonce: 'training-nonce',
        actions: {
            getStatus: 'aa_get_training_status',
            enroll: 'aa_enroll_training',
            unsubscribe: 'aa_unsubscribe_training',
            getConsentStatus: 'aa_get_training_consent_status',
            acceptConsent: 'aa_accept_training_consent',
            revokeConsent: 'aa_revoke_training_consent',
            getCourse: 'aa_get_training_course',
            getLesson: 'aa_get_training_lesson'
        }
    };
    globalThis.fetch = fetchImpl;
    delete require.cache[servicePath];
    return require(servicePath);
}

function captureFetch() {
    var calls = [];
    var fetchImpl = function (url, options) {
        var fields = {};
        if (options && options.body && typeof options.body.entries === 'function') {
            Array.from(options.body.entries()).forEach(function (entry) {
                fields[entry[0]] = entry[1];
            });
        }
        calls.push({ url: url, options: options, fields: fields });
        return Promise.resolve({
            ok: true,
            json: function () {
                return Promise.resolve({
                    success: true,
                    data: { ok_marker: true }
                });
            }
        });
    };
    return { calls: calls, fetchImpl: fetchImpl };
}

describe('TrainingService', () => {
    afterEach(() => {
        delete globalThis.TrainingService;
        delete globalThis.AA_TRAINING_DATA;
        delete globalThis.fetch;
        delete require.cache[servicePath];
    });

    it('archivo no contiene HMAC, secretos ni URL directa del backend', () => {
        assert.doesNotMatch(serviceSrc, /HMAC|hmac/i);
        assert.doesNotMatch(serviceSrc, /client_secret|aa_client_secret/i);
        assert.doesNotMatch(serviceSrc, /AA_API_BASE_URL|api\.deoia\.com|localhost:3000/);
        assert.doesNotMatch(serviceSrc, /X-Signature|X-Client-Id/);
    });

    it('ocho métodos usan la acción correcta y el nonce configurado', async () => {
        var capture = captureFetch();
        var service = loadService(capture.fetchImpl);
        var methods = [
            ['getStatus', 'aa_get_training_status'],
            ['enroll', 'aa_enroll_training'],
            ['unsubscribe', 'aa_unsubscribe_training'],
            ['getConsentStatus', 'aa_get_training_consent_status'],
            ['acceptConsent', 'aa_accept_training_consent'],
            ['revokeConsent', 'aa_revoke_training_consent'],
            ['getCourse', 'aa_get_training_course']
        ];

        for (var i = 0; i < methods.length; i += 1) {
            await service[methods[i][0]]();
        }
        await service.getLesson('bienvenida');

        assert.equal(capture.calls.length, 8);
        methods.forEach(function (pair, index) {
            assert.equal(capture.calls[index].fields.action, pair[1]);
            assert.equal(capture.calls[index].fields._wpnonce, 'training-nonce');
            assert.equal(capture.calls[index].url, 'https://agenda.test/wp-admin/admin-ajax.php');
        });
        assert.equal(capture.calls[7].fields.action, 'aa_get_training_lesson');
        assert.equal(capture.calls[7].fields._wpnonce, 'training-nonce');
    });

    it('getLesson envía solo lessonKey (sin courseKey ni IDs)', async () => {
        var capture = captureFetch();
        var service = loadService(capture.fetchImpl);
        await service.getLesson('bienvenida');
        assert.equal(capture.calls[0].fields.lessonKey, 'bienvenida');
        assert.equal(Object.prototype.hasOwnProperty.call(capture.calls[0].fields, 'courseKey'), false);
        assert.equal(Object.prototype.hasOwnProperty.call(capture.calls[0].fields, 'enrollmentId'), false);
        assert.equal(Object.prototype.hasOwnProperty.call(capture.calls[0].fields, 'installationId'), false);
    });

    it('success se normaliza a { success: true, data }', async () => {
        var service = loadService(function () {
            return Promise.resolve({
                ok: true,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: { access_state: 'active' }
                    });
                }
            });
        });

        var result = await service.getStatus();
        assert.deepEqual(result, {
            success: true,
            data: { access_state: 'active' }
        });
    });

    it('error training_* se conserva en err.code', async () => {
        var service = loadService(function () {
            return Promise.resolve({
                ok: true,
                json: function () {
                    return Promise.resolve({
                        success: false,
                        data: {
                            code: 'training_not_eligible',
                            message: ''
                        }
                    });
                }
            });
        });

        await assert.rejects(service.enroll(), (err) => {
            assert.equal(err.code, 'training_not_eligible');
            assert.equal(err.kind, 'training');
            return true;
        });
    });

    it('error de red se normaliza', async () => {
        var service = loadService(function () {
            return Promise.resolve({
                ok: false,
                status: 503,
                json: function () {
                    return Promise.resolve({});
                }
            });
        });

        await assert.rejects(service.getCourse(), (err) => {
            assert.equal(err.kind, 'network');
            assert.equal(err.code, 'training_network_error');
            return true;
        });
    });

    it('respuesta inválida se normaliza', async () => {
        var service = loadService(function () {
            return Promise.resolve({
                ok: true,
                json: function () {
                    return Promise.reject(new Error('bad json'));
                }
            });
        });

        await assert.rejects(service.getCourse(), (err) => {
            assert.equal(err.kind, 'invalid_response');
            assert.equal(err.code, 'training_invalid_response');
            return true;
        });
    });

    it('Abort se distingue', async () => {
        var service = loadService(function (_url, options) {
            return new Promise(function (_resolve, reject) {
                if (options && options.signal) {
                    options.signal.addEventListener('abort', function () {
                        var err = new Error('Aborted');
                        err.name = 'AbortError';
                        reject(err);
                    });
                }
            });
        });

        var controller = new AbortController();
        var pending = service.getStatus({ signal: controller.signal });
        controller.abort();

        await assert.rejects(pending, (err) => {
            assert.equal(err.kind, 'aborted');
            assert.equal(err.code, 'training_aborted');
            return true;
        });
    });

    it('usa ajaxUrl inyectado vía options.config', async () => {
        var capture = captureFetch();
        var service = loadService(capture.fetchImpl, {
            ajaxUrl: 'https://ignored.test/admin-ajax.php',
            nonce: 'ignored',
            actions: {}
        });

        await service.getStatus({
            config: {
                ajaxUrl: 'https://injected.test/wp-admin/admin-ajax.php',
                nonce: 'injected-nonce',
                actions: { getStatus: 'aa_get_training_status' }
            }
        });

        assert.equal(capture.calls[0].url, 'https://injected.test/wp-admin/admin-ajax.php');
        assert.equal(capture.calls[0].fields._wpnonce, 'injected-nonce');
    });
});
