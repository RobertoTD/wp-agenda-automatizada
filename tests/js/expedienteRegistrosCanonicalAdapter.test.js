'use strict';

/**
 * C1b — ExpedienteRegistrosCanonicalAdapter (factory sin montaje).
 * Ejecutar: node --test tests/js/expedienteRegistrosCanonicalAdapter.test.js
 */

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const adapterPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/expedientes/expediente-registros-canonical-adapter.js'
);
const adapterSrc = fs.readFileSync(adapterPath, 'utf8');
const detailSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/expedientes/detail.php'),
    'utf8'
);

const VALID_CAPS = {
    createRegistro: true,
    updateRegistro: false,
    deleteRegistro: false,
    attach: true,
    signRead: true,
    deleteAdjunto: true
};

const VALID_ACTIONS = {
    listRegistros: 'aa_list_expediente_registros_for_expediente',
    createRegistro: 'aa_create_expediente_registro_for_expediente',
    updateRegistro: 'aa_update_expediente_registro_for_expediente',
    attachRegistro: 'aa_attach_expediente_adjunto_for_expediente',
    signAdjuntoRead: 'aa_sign_expediente_adjunto_read_for_expediente',
    deleteAdjunto: 'aa_delete_expediente_adjunto_for_expediente'
};

function validConfig(overrides) {
    return Object.assign({
        ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
        nonce: 'nonce-by-expediente',
        expedienteId: '5',
        recordsPage: 2,
        scopeKey: 'expediente:5',
        actions: Object.assign({}, VALID_ACTIONS),
        capabilities: Object.assign({}, VALID_CAPS)
    }, overrides || {});
}

function formFields(body) {
    if (!body || !body.entries) {
        return {};
    }
    return Object.fromEntries(body.entries);
}

function loadAdapter(fetchImpl) {
    const fetchCalls = [];
    class FakeFormData {
        constructor() { this.entries = []; }
        append(k, v, filename) {
            if (arguments.length >= 3) {
                this.entries.push([k, v, filename]);
            } else {
                this.entries.push([k, v]);
            }
        }
    }

    const windowObj = { AAAdmin: {} };
    windowObj.window = windowObj;

    const sandbox = {
        window: windowObj,
        document: {
            createElement() { throw new Error('DOM forbidden'); },
            querySelector() { throw new Error('DOM forbidden'); },
            getElementById() { throw new Error('DOM forbidden'); }
        },
        console: { error() {}, log() {} },
        fetch: function (url, opts) {
            fetchCalls.push({ url, opts });
            if (typeof fetchImpl === 'function') {
                return fetchImpl(url, opts, fetchCalls.length);
            }
            return Promise.resolve({
                status: 200,
                json: async () => ({ success: true, data: { records: [] } })
            });
        },
        FormData: FakeFormData,
        AbortController,
        Promise,
        Object,
        Array,
        String,
        Number,
        Math,
        parseInt,
        isFinite,
        JSON,
        Blob: function () {}
    };

    vm.runInNewContext(adapterSrc, sandbox);
    return {
        build: sandbox.window.AAAdmin.ExpedienteRegistrosCanonicalAdapter.build,
        fetchCalls,
        setFetch(fn) { fetchImpl = fn; }
    };
}

describe('ExpedienteRegistrosCanonicalAdapter (C1b)', () => {
    it('fuente: sin autoejecución / sin DOM / sin globals legacy / sin actions hardcodeadas de fallback', () => {
        assert.match(adapterSrc, /ExpedienteRegistrosCanonicalAdapter/);
        assert.match(adapterSrc, /build:\s*build/);
        assert.doesNotMatch(adapterSrc, /AA_CLIENTS_DATA/);
        assert.doesNotMatch(adapterSrc, /AA_CLIENTS_NONCES/);
        assert.doesNotMatch(adapterSrc, /ExpedienteRegistros\.init/);
        assert.doesNotMatch(adapterSrc, /querySelector|getElementById|addEventListener/);
        assert.doesNotMatch(adapterSrc, /client_id/);
        // No defaults de action como fallback de strings de endpoint
        assert.doesNotMatch(adapterSrc, /actions\.\w+\s*\|\|/);
        assert.doesNotMatch(adapterSrc, /'aa_list_expediente_registros_for_expediente'/);
    });

    it('build válido: seis ports incl. update, sin deleteRegistro, scopeKey y capabilities', () => {
        const ctx = loadAdapter();
        const built = ctx.build(validConfig());
        assert.ok(built);
        assert.equal(built.scopeKey, 'expediente:5');
        // Comparar vía JSON para evitar desigualdad entre realms de vm
        assert.equal(JSON.stringify(built.capabilities), JSON.stringify(VALID_CAPS));
        assert.equal(typeof built.ports.list, 'function');
        assert.equal(typeof built.ports.create, 'function');
        assert.equal(typeof built.ports.update, 'function');
        assert.equal(typeof built.ports.attach, 'function');
        assert.equal(typeof built.ports.signRead, 'function');
        assert.equal(typeof built.ports.deleteAdjunto, 'function');
        assert.equal(built.ports.deleteRegistro, undefined);
        assert.equal(Object.prototype.hasOwnProperty.call(built, 'clientId'), false);
        assert.equal(ctx.fetchCalls.length, 0);
    });

    it('build no muta capabilities de entrada', () => {
        const caps = Object.assign({}, VALID_CAPS);
        const ctx = loadAdapter();
        const built = ctx.build(validConfig({ capabilities: caps }));
        caps.createRegistro = false;
        assert.equal(built.capabilities.createRegistro, true);
    });

    it('config inválida → null (sin requests)', () => {
        const ctx = loadAdapter();
        const cases = [
            null,
            {},
            validConfig({ ajaxUrl: '' }),
            validConfig({ ajaxUrl: '   ' }),
            validConfig({ ajaxUrl: 1 }),
            validConfig({ nonce: '' }),
            validConfig({ nonce: 1 }),
            validConfig({ expedienteId: '0' }),
            validConfig({ expedienteId: '-1' }),
            validConfig({ expedienteId: '01' }),
            validConfig({ expedienteId: '1.0' }),
            validConfig({ expedienteId: '1e2' }),
            validConfig({ expedienteId: ['5'] }),
            validConfig({ expedienteId: { id: 5 } }),
            validConfig({ recordsPage: 0 }),
            validConfig({ recordsPage: -1 }),
            validConfig({ recordsPage: '01' }),
            validConfig({ recordsPage: 1.5 }),
            validConfig({ recordsPage: '1e2' }),
            validConfig({ scopeKey: '' }),
            validConfig({ scopeKey: '   ' }),
            validConfig({ scopeKey: 1 }),
            validConfig({
                actions: Object.assign({}, VALID_ACTIONS, { listRegistros: undefined })
            }),
            validConfig({
                actions: Object.assign({}, VALID_ACTIONS, { extra: 'x' })
            }),
            validConfig({
                actions: {
                    listRegistros: VALID_ACTIONS.listRegistros,
                    createRegistro: VALID_ACTIONS.createRegistro,
                    attachRegistro: VALID_ACTIONS.attachRegistro,
                    signAdjuntoRead: VALID_ACTIONS.signAdjuntoRead
                    // deleteAdjunto faltante
                }
            }),
            validConfig({
                capabilities: Object.assign({}, VALID_CAPS, { attach: 'true' })
            }),
            validConfig({
                capabilities: Object.assign({}, VALID_CAPS, { extra: false })
            }),
            validConfig({
                capabilities: {
                    createRegistro: true,
                    updateRegistro: false,
                    deleteRegistro: false,
                    attach: true,
                    signRead: true
                    // deleteAdjunto faltante
                }
            }),
            validConfig({
                capabilities: Object.assign({}, VALID_CAPS, {
                    createRegistro: false,
                    updateRegistro: false,
                    attach: true
                })
            }),
            validConfig({
                capabilities: Object.assign({}, VALID_CAPS, {
                    signRead: false,
                    deleteAdjunto: true
                })
            })
        ];

        cases.forEach((cfg, idx) => {
            assert.equal(ctx.build(cfg), null, 'case ' + idx);
        });
        assert.equal(ctx.fetchCalls.length, 0);
    });

    it('list() cierra página, usa action/nonce/expediente_id, sin client_id', async () => {
        const ctx = loadAdapter();
        const built = ctx.build(validConfig({ recordsPage: 3, expedienteId: '12' }));
        const payload = await built.ports.list();
        assert.equal(payload.httpStatus, 200);
        assert.equal(payload.result.success, true);
        assert.equal(ctx.fetchCalls.length, 1);
        const call = ctx.fetchCalls[0];
        assert.equal(call.url, 'https://example.test/wp-admin/admin-ajax.php');
        assert.equal(call.opts.credentials, 'same-origin');
        const fields = formFields(call.opts.body);
        assert.equal(fields.action, VALID_ACTIONS.listRegistros);
        assert.equal(fields._wpnonce, 'nonce-by-expediente');
        assert.equal(fields.expediente_id, '12');
        assert.equal(fields.page, '3');
        assert.equal(Object.prototype.hasOwnProperty.call(fields, 'client_id'), false);
        assert.equal(built.ports.list.length, 0);
        assert.ok(!JSON.stringify(fields).includes('expediente:5'));
        assert.ok(!JSON.stringify(fields).includes('scopeKey'));
    });

    it('create envía solo action/nonce/expediente/title/body y no navega', async () => {
        const ctx = loadAdapter();
        const built = ctx.build(validConfig());
        await built.ports.create({ title: 'T', body: 'B' });
        const fields = formFields(ctx.fetchCalls[0].opts.body);
        assert.deepEqual(Object.keys(fields).sort(), [
            '_wpnonce',
            'action',
            'body',
            'expediente_id',
            'title'
        ]);
        assert.equal(fields.action, VALID_ACTIONS.createRegistro);
        assert.equal(fields.expediente_id, '5');
        assert.equal(fields.title, 'T');
        assert.equal(fields.body, 'B');
        assert.equal(Object.prototype.hasOwnProperty.call(fields, 'client_id'), false);
    });

    it('update envía action/nonce/expediente/record_id/title/body sin client_id', async () => {
        const ctx = loadAdapter();
        const built = ctx.build(validConfig());
        await built.ports.update(14, { title: 'Nuevo', body: 'Cuerpo' });
        const fields = formFields(ctx.fetchCalls[0].opts.body);
        assert.deepEqual(Object.keys(fields).sort(), [
            '_wpnonce',
            'action',
            'body',
            'expediente_id',
            'record_id',
            'title'
        ]);
        assert.equal(fields.action, VALID_ACTIONS.updateRegistro);
        assert.equal(fields.expediente_id, '5');
        assert.equal(fields.record_id, '14');
        assert.equal(fields.title, 'Nuevo');
        assert.equal(fields.body, 'Cuerpo');
        assert.equal(Object.prototype.hasOwnProperty.call(fields, 'client_id'), false);
        assert.equal(Object.prototype.hasOwnProperty.call(fields, 'scopeKey'), false);
    });

    it('attach usa FormData con Blob, record_id, upload_operation_id, sin client_id/paths', async () => {
        const ctx = loadAdapter();
        const built = ctx.build(validConfig());
        const blob = { __blob: true, size: 12 };
        const op = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        await built.ports.attach(14, blob, op);
        const entries = ctx.fetchCalls[0].opts.body.entries;
        const map = Object.fromEntries(entries.map(([k, v]) => [k, v]));
        assert.equal(map.action, VALID_ACTIONS.attachRegistro);
        assert.equal(map._wpnonce, 'nonce-by-expediente');
        assert.equal(map.expediente_id, '5');
        assert.equal(map.record_id, '14');
        assert.equal(map.upload_operation_id, op);
        assert.equal(map.file, blob);
        const fileEntry = entries.find((e) => e[0] === 'file');
        assert.equal(fileEntry[1], blob);
        assert.equal(fileEntry[2], 'adjunto.jpg');
        assert.equal(Object.prototype.hasOwnProperty.call(map, 'client_id'), false);
        assert.equal(Object.prototype.hasOwnProperty.call(map, 'storage_path'), false);
    });

    it('signRead propaga AbortSignal y no envía client_id', async () => {
        const ctx = loadAdapter();
        const built = ctx.build(validConfig());
        const ac = new AbortController();
        await built.ports.signRead(14, 20, 'summary', ac.signal);
        const call = ctx.fetchCalls[0];
        assert.equal(call.opts.signal, ac.signal);
        const fields = formFields(call.opts.body);
        assert.equal(fields.action, VALID_ACTIONS.signAdjuntoRead);
        assert.equal(fields.attachment_id, '20');
        assert.equal(fields.variant, 'summary');
        assert.equal(fields.record_id, '14');
        assert.equal(fields.expediente_id, '5');
        assert.equal(Object.prototype.hasOwnProperty.call(fields, 'client_id'), false);
    });

    it('signRead AbortError se propaga (no se convierte en toast)', async () => {
        const err = new Error('aborted');
        err.name = 'AbortError';
        const ctx = loadAdapter(() => Promise.reject(err));
        const built = ctx.build(validConfig());
        await assert.rejects(() => built.ports.signRead(1, 2, 'gallery'), (e) => e.name === 'AbortError');
    });

    it('deleteAdjunto envía record_id/attachment_id, action configurada, sin client_id', async () => {
        const ctx = loadAdapter();
        const built = ctx.build(validConfig());
        await built.ports.deleteAdjunto(14, 20);
        const fields = formFields(ctx.fetchCalls[0].opts.body);
        assert.equal(fields.action, VALID_ACTIONS.deleteAdjunto);
        assert.equal(fields.record_id, '14');
        assert.equal(fields.attachment_id, '20');
        assert.equal(fields.expediente_id, '5');
        assert.equal(Object.prototype.hasOwnProperty.call(fields, 'client_id'), false);
        assert.equal(Object.prototype.hasOwnProperty.call(fields, 'storage_path'), false);
    });

    it('transporte: HTTP no 200 con JSON; JSON inválido; error de red', async () => {
        let mode = 'http400';
        const ctx = loadAdapter((url, opts, n) => {
            if (mode === 'http400') {
                return Promise.resolve({
                    status: 400,
                    json: async () => ({ success: false, data: { code: 'invalid_id' } })
                });
            }
            if (mode === 'badjson') {
                return Promise.resolve({
                    status: 200,
                    json: async () => { throw new SyntaxError('bad json'); }
                });
            }
            return Promise.reject(new TypeError('network'));
        });
        const built = ctx.build(validConfig());

        const bad = await built.ports.list();
        assert.equal(bad.httpStatus, 400);
        assert.equal(bad.result.data.code, 'invalid_id');

        mode = 'badjson';
        await assert.rejects(() => built.ports.create({ title: 'a', body: 'b' }), SyntaxError);

        mode = 'network';
        await assert.rejects(() => built.ports.deleteAdjunto(1, 2), TypeError);
    });

    it('usa actions del config (no hardcode); scopeKey nunca en requests', async () => {
        const custom = {
            listRegistros: 'aa_custom_list',
            createRegistro: 'aa_custom_create',
            updateRegistro: 'aa_custom_update',
            attachRegistro: 'aa_custom_attach',
            signAdjuntoRead: 'aa_custom_sign',
            deleteAdjunto: 'aa_custom_delete'
        };
        const ctx = loadAdapter();
        const built = ctx.build(validConfig({
            actions: custom,
            scopeKey: 'expediente:99'
        }));
        await built.ports.list();
        await built.ports.create({ title: 't', body: 'b' });
        await built.ports.update(3, { title: 'u', body: 'v' });
        await built.ports.attach(1, { size: 1 }, 'op');
        await built.ports.signRead(1, 2, 'display');
        await built.ports.deleteAdjunto(1, 2);
        const got = ctx.fetchCalls.map((c) => {
            const e = c.opts.body.entries || [];
            const hit = e.find((x) => x[0] === 'action');
            return hit ? hit[1] : formFields(c.opts.body).action;
        });
        assert.deepEqual(got, [
            'aa_custom_list',
            'aa_custom_create',
            'aa_custom_update',
            'aa_custom_attach',
            'aa_custom_sign',
            'aa_custom_delete'
        ]);
        const blob = JSON.stringify(ctx.fetchCalls);
        assert.equal(blob.includes('expediente:99'), false);
        assert.equal(blob.includes('scopeKey'), false);
        assert.equal(blob.includes('client_id'), false);
    });

    it('detail.php: config aditiva; C1c1 carga adapter/renderer/mount; create provisional; sin create rico', () => {
        assert.match(detailSrc, /scopeKey/);
        assert.match(detailSrc, /recordsPage/);
        assert.match(detailSrc, /listRegistros/);
        assert.match(detailSrc, /attachRegistro/);
        assert.match(detailSrc, /signAdjuntoRead/);
        assert.match(detailSrc, /deleteAdjunto/);
        assert.match(detailSrc, /createRegistro:\s*true/);
        assert.match(detailSrc, /updateRegistro:\s*true/);
        assert.match(detailSrc, /deleteRegistro:\s*false/);
        assert.match(detailSrc, /aa_update_expediente_registro_for_expediente/);
        assert.match(detailSrc, /\$aa_detail_update_action/);
        assert.match(detailSrc, /expediente:' \. \(string\) \$aa_detail_id/);
        assert.match(detailSrc, /\$aa_records_page/);
        assert.match(detailSrc, /successUrl/);
        assert.match(detailSrc, /expediente-registro-create-modal\.js/);
        assert.match(detailSrc, /expediente-registros-canonical-adapter\.js/);
        assert.match(detailSrc, /expediente-registros\.js/);
        assert.match(detailSrc, /expediente-registros-canonical-mount\.js/);
        assert.doesNotMatch(detailSrc, /executable-options-menu-placement/);
        assert.doesNotMatch(detailSrc, /ExpedienteRegistros\.openCreate/);
        assert.doesNotMatch(detailSrc, /onCreateComplete/);
        assert.doesNotMatch(detailSrc, /clientId/);
        assert.doesNotMatch(detailSrc, /client_id/);
    });
});
