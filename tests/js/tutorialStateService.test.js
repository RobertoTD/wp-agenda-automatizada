'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const servicePath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialStateService.js'
);
const serviceSrc = fs.readFileSync(servicePath, 'utf8');

function createFormDataMock() {
    function FormDataMock() {
        this._fields = new Map();
    }

    FormDataMock.prototype.append = function (key, value) {
        this._fields.set(String(key), String(value));
    };

    FormDataMock.prototype.get = function (key) {
        return this._fields.has(String(key)) ? this._fields.get(String(key)) : null;
    };

    FormDataMock.prototype.keys = function () {
        return this._fields.keys();
    };

    return FormDataMock;
}

function loadService(fetchImpl, tutorialData) {
    var requests = [];

    var context = {
        window: {},
        fetch: fetchImpl || function (url, options) {
            requests.push({ url: url, options: options || {} });

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: { version: 1, tutorials: {} }
                    });
                }
            });
        }
    };

    context.window = context;
    if (tutorialData === undefined) {
        context.window.AA_TUTORIAL_DATA = {
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            getAction: 'aa_get_tutorial_state',
            updateAction: 'aa_update_tutorial_state',
            reconcileAction: 'aa_reconcile_tutorial_state',
            nonce: 'test-nonce'
        };
    } else {
        context.window.AA_TUTORIAL_DATA = tutorialData;
    }
    context.FormData = createFormDataMock();

    vm.runInNewContext(serviceSrc, context, { filename: servicePath });

    return {
        TutorialStateService: context.window.TutorialStateService,
        requests: requests
    };
}

function readFormField(body, field) {
    if (!body || typeof body.get !== 'function') {
        return null;
    }

    return body.get(field);
}

function formKeys(body) {
    if (!body || typeof body.keys !== 'function') {
        return [];
    }

    return Array.from(body.keys());
}

describe('TutorialStateService MC3C', () => {
    let originalWindow;
    let originalFetch;

    beforeEach(() => {
        originalWindow = global.window;
        originalFetch = global.fetch;
        global.window = global;
    });

    afterEach(() => {
        if (originalWindow === undefined) {
            delete global.window;
        } else {
            global.window = originalWindow;
        }

        if (originalFetch === undefined) {
            delete global.fetch;
        } else {
            global.fetch = originalFetch;
        }

        delete global.AA_TUTORIAL_DATA;
        delete global.TutorialStateService;
    });

    it('fetchState usa GET con getAction y nonce', async () => {
        var requests = [];
        var loaded = loadService(function (url, options) {
            requests.push({ url: url, options: options || {} });

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: { version: 1, tutorials: {} }
                    });
                }
            });
        });

        var result = await loaded.TutorialStateService.fetchState();

        assert.equal(requests.length, 1);
        assert.match(requests[0].url, /action=aa_get_tutorial_state/);
        assert.match(requests[0].url, /_wpnonce=test-nonce/);
        assert.equal(requests[0].options.method, 'GET');
        assert.equal(requests[0].options.credentials, 'same-origin');
        assert.deepEqual(result, { version: 1, tutorials: {} });
    });

    it('transition postea updateAction con tutorial_id, status y current_step_id', async () => {
        var requests = [];
        var loaded = loadService(function (url, options) {
            requests.push({ url: url, options: options || {} });

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: {
                            version: 1,
                            tutorials: {
                                create_test_appointment_v1: {
                                    status: 'in_progress',
                                    current_step_id: 'calendar_overview'
                                }
                            }
                        }
                    });
                }
            });
        });

        var result = await loaded.TutorialStateService.transition({
            tutorialId: 'create_test_appointment_v1',
            status: 'in_progress',
            currentStepId: 'calendar_overview'
        });

        assert.equal(requests.length, 1);
        assert.equal(requests[0].options.method, 'POST');
        assert.equal(requests[0].options.credentials, 'same-origin');

        var body = requests[0].options.body;
        assert.equal(readFormField(body, 'action'), 'aa_update_tutorial_state');
        assert.equal(readFormField(body, '_wpnonce'), 'test-nonce');
        assert.equal(readFormField(body, 'tutorial_id'), 'create_test_appointment_v1');
        assert.equal(readFormField(body, 'status'), 'in_progress');
        assert.equal(readFormField(body, 'current_step_id'), 'calendar_overview');
        assert.equal(result.tutorials.create_test_appointment_v1.current_step_id, 'calendar_overview');
    });

    it('transition omite current_step_id cuando no se envía', async () => {
        var requests = [];
        var loaded = loadService(function (url, options) {
            requests.push({ url: url, options: options || {} });

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({ success: true, data: { version: 1, tutorials: {} } });
                }
            });
        });

        await loaded.TutorialStateService.transition({
            tutorialId: 'create_test_appointment_v1',
            status: 'paused'
        });

        var keys = formKeys(requests[0].options.body);
        assert.ok(keys.indexOf('current_step_id') === -1);
    });

    it('transition envía current_step_id vacío cuando currentStepId es null', async () => {
        var requests = [];
        var loaded = loadService(function (url, options) {
            requests.push({ url: url, options: options || {} });

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({ success: true, data: { version: 1, tutorials: {} } });
                }
            });
        });

        await loaded.TutorialStateService.transition({
            tutorialId: 'create_test_appointment_v1',
            status: 'completed',
            currentStepId: null
        });

        assert.equal(readFormField(requests[0].options.body, 'current_step_id'), '');
    });

    it('no envía timestamps aunque vengan en el input', async () => {
        var requests = [];
        var loaded = loadService(function (url, options) {
            requests.push({ url: url, options: options || {} });

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({ success: true, data: { version: 1, tutorials: {} } });
                }
            });
        });

        await loaded.TutorialStateService.transition({
            tutorialId: 'create_test_appointment_v1',
            status: 'in_progress',
            currentStepId: 'calendar_overview',
            accepted_at: '2026-07-04 10:00:00',
            updated_at: '2026-07-04 10:05:00'
        });

        var keys = formKeys(requests[0].options.body);
        assert.deepEqual(keys.sort(), ['_wpnonce', 'action', 'current_step_id', 'status', 'tutorial_id'].sort());
    });

    it('rechaza fetchState si falta AA_TUTORIAL_DATA', async () => {
        var loaded = loadService(undefined, null);

        await assert.rejects(
            loaded.TutorialStateService.fetchState(),
            function (err) {
                return err.code === 'missing_config';
            }
        );
    });

    it('rechaza transition con input inválido sin fetch', async () => {
        var requests = [];
        var loaded = loadService(function (url, options) {
            requests.push({ url: url, options: options || {} });
            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({ success: true, data: {} });
                }
            });
        });

        await assert.rejects(
            loaded.TutorialStateService.transition({ status: 'in_progress' }),
            function (err) {
                return err.code === 'invalid_input';
            }
        );

        assert.equal(requests.length, 0);
    });

    it('propaga error de red', async () => {
        var loaded = loadService(function () {
            return Promise.reject(new Error('network down'));
        });

        await assert.rejects(
            loaded.TutorialStateService.fetchState(),
            /network down/
        );
    });

    it('propaga error AJAX con code y httpStatus', async () => {
        var loaded = loadService(function () {
            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: false,
                        data: {
                            message: 'Respuesta inválida',
                            code: 'ajax_error'
                        }
                    });
                }
            });
        });

        await assert.rejects(
            loaded.TutorialStateService.fetchState(),
            function (err) {
                return err.code === 'ajax_error'
                    && err.message === 'Respuesta inválida'
                    && err.httpStatus === 200;
            }
        );
    });

    it('propaga FSM 400 conservando code y message del body', async () => {
        var loaded = loadService(function () {
            return Promise.resolve({
                ok: false,
                status: 400,
                json: function () {
                    return Promise.resolve({
                        success: false,
                        data: {
                            message: 'Solo se permite avanzar al siguiente paso lineal.',
                            code: 'invalid_step_transition'
                        }
                    });
                }
            });
        });

        await assert.rejects(
            loaded.TutorialStateService.transition({
                tutorialId: 'create_test_appointment_v1',
                status: 'in_progress',
                currentStepId: 'calendar_overview'
            }),
            function (err) {
                return err.code === 'invalid_step_transition'
                    && err.message === 'Solo se permite avanzar al siguiente paso lineal.'
                    && err.httpStatus === 400;
            }
        );
    });

    it('reconcileState usa POST con reconcileAction y nonce', async () => {
        var requests = [];
        var loaded = loadService(function (url, options) {
            requests.push({ url: url, options: options || {} });

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: {
                            version: 1,
                            tutorials: {},
                            reconciled: false
                        }
                    });
                }
            });
        });

        var result = await loaded.TutorialStateService.reconcileState();

        assert.equal(requests.length, 1);
        assert.equal(requests[0].options.method, 'POST');
        assert.equal(requests[0].options.credentials, 'same-origin');
        assert.equal(readFormField(requests[0].options.body, 'action'), 'aa_reconcile_tutorial_state');
        assert.equal(readFormField(requests[0].options.body, '_wpnonce'), 'test-nonce');
        assert.deepEqual(formKeys(requests[0].options.body).sort(), ['_wpnonce', 'action'].sort());
        assert.equal(result.reconciled, false);
    });

    it('reconcileState propaga error 503 del probe', async () => {
        var loaded = loadService(function () {
            return Promise.resolve({
                ok: false,
                status: 503,
                json: function () {
                    return Promise.resolve({
                        success: false,
                        data: {
                            message: 'No se pudo comprobar si existen citas.',
                            code: 'reservation_existence_check_failed'
                        }
                    });
                }
            });
        });

        await assert.rejects(
            loaded.TutorialStateService.reconcileState(),
            function (err) {
                return err.code === 'reservation_existence_check_failed'
                    && err.httpStatus === 503;
            }
        );
    });

    it('reconcileState rechaza si falta reconcileAction', async () => {
        var loaded = loadService(undefined, {
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            getAction: 'aa_get_tutorial_state',
            updateAction: 'aa_update_tutorial_state',
            nonce: 'test-nonce'
        });

        await assert.rejects(
            loaded.TutorialStateService.reconcileState(),
            function (err) {
                return err.code === 'missing_config';
            }
        );
    });
});
