'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const clientsModulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/clients-module.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const clientsModuleSrc = fs.readFileSync(clientsModulePath, 'utf8');

function createEl(tag) {
    const el = {
        tagName: String(tag).toUpperCase(),
        className: '',
        id: '',
        type: '',
        textContent: '',
        open: false,
        disabled: false,
        isConnected: true,
        children: [],
        attributes: Object.create(null),
        parentNode: null,
        classList: {
            _set: new Set(),
            add(c) { this._set.add(c); },
            remove(c) { this._set.delete(c); },
            contains(c) { return this._set.has(c); }
        },
        setAttribute(name, value) { el.attributes[name] = String(value); },
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(el.attributes, name)
                ? el.attributes[name]
                : null;
        },
        removeAttribute(name) { delete el.attributes[name]; },
        appendChild(child) {
            child.parentNode = el;
            el.children.push(child);
            return child;
        },
        removeChild(child) {
            el.children = el.children.filter((c) => c !== child);
            child.parentNode = null;
            return child;
        },
        closest() { return null; },
        querySelector() { return null; },
        querySelectorAll() { return []; },
        addEventListener() {},
        removeEventListener() {},
        focus() {}
    };
    Object.defineProperty(el, 'firstChild', {
        get() { return el.children[0] || null; }
    });
    return el;
}

function formFields(body) {
    return Object.fromEntries(body.entries);
}

function makeSandbox(options) {
    options = options || {};
    const fetchCalls = [];
    let fetchImpl = options.fetch || (() => Promise.resolve({
        status: 200,
        json: async () => ({ success: true, data: { records: [] } })
    }));

    class FakeFormData {
        constructor() { this.entries = []; }
        append(k, v) { this.entries.push([k, v]); }
    }

    const windowObj = {
        AAAdmin: {},
        AA_CLIENTS_DATA: options.clientsData !== undefined ? options.clientsData : {},
        AA_CLIENTS_NONCES: options.clientsNonces !== undefined ? options.clientsNonces : {},
        addEventListener() {},
        removeEventListener() {},
        confirm: () => true,
        setTimeout: (fn) => fn(),
        console: { error() {}, log() {} }
    };
    if (options.ajaxurl !== undefined) {
        windowObj.ajaxurl = options.ajaxurl;
    }
    windowObj.window = windowObj;

    const document = {
        createElement: createEl,
        getElementById: () => null,
        querySelector: () => null,
        contains: () => true,
        addEventListener() {},
        removeEventListener() {}
    };

    const sandbox = {
        window: windowObj,
        document: document,
        console: windowObj.console,
        fetch: function (url, opts) {
            fetchCalls.push({ url, opts });
            return fetchImpl(url, opts, fetchCalls.length);
        },
        FormData: FakeFormData,
        AbortController: AbortController,
        URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
        setTimeout: (fn) => fn(),
        Date: Date,
        Object: Object,
        JSON: JSON,
        Math: Math,
        parseInt: parseInt,
        String: String,
        Array: Array,
        Promise: Promise,
        crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
    };

    vm.runInNewContext(moduleSrc, sandbox);

    return {
        sandbox,
        api: sandbox.window.AAAdmin.ExpedienteRegistros,
        testApi: sandbox.window.AAAdmin.ExpedienteRegistros.__test__,
        fetchCalls,
        setFetch(fn) { fetchImpl = fn; }
    };
}

function okPayload(data) {
    return Promise.resolve({
        httpStatus: 200,
        result: { success: true, data: data }
    });
}

function recordingPorts(overrides) {
    const calls = [];
    const ports = {
        list: function () {
            calls.push({ name: 'list', args: [] });
            return okPayload({ records: [] });
        },
        create: function (draft) {
            calls.push({ name: 'create', args: [draft] });
            return okPayload({
                record: {
                    id: 101,
                    title: draft.title,
                    body: draft.body,
                    recorded_at: '2026-08-01 10:00:00',
                    created_at: '2026-08-01 10:00:00',
                    updated_at: null
                }
            });
        },
        update: function (recordId, draft) {
            calls.push({ name: 'update', args: [recordId, draft] });
            return okPayload({
                record: {
                    id: recordId,
                    title: draft.title,
                    body: draft.body,
                    recorded_at: '2026-08-01 10:00:00',
                    created_at: '2026-08-01 10:00:00',
                    updated_at: '2026-08-01 11:00:00'
                }
            });
        },
        deleteRegistro: function (recordId) {
            calls.push({ name: 'deleteRegistro', args: [recordId] });
            return okPayload({ deleted: true, record_id: recordId });
        },
        attach: function (recordId, blob, operationId) {
            calls.push({ name: 'attach', args: [recordId, blob, operationId] });
            return okPayload({
                record_id: recordId,
                adjunto: {
                    id: 5,
                    width: 800,
                    height: 600,
                    byte_size: 40000,
                    created_at: '2026-08-01 10:00:00'
                }
            });
        },
        signRead: function (recordId, attachmentId, variant, signal) {
            calls.push({ name: 'signRead', args: [recordId, attachmentId, variant, signal] });
            return okPayload({
                url: 'https://signed.example/x.jpg',
                expires_in: 600,
                variant: variant
            });
        },
        deleteAdjunto: function (recordId, attachmentId) {
            calls.push({ name: 'deleteAdjunto', args: [recordId, attachmentId] });
            return okPayload({
                record_id: recordId,
                deleted_attachment_id: attachmentId,
                adjuntos: [],
                adjunto: null
            });
        }
    };
    Object.assign(ports, overrides || {});
    return { ports, calls };
}

/** Mirrors clients-module buildExpedienteRegistrosLegacyPorts for equivalence tests. */
function buildLegacyPortsLikeClientsModule(clientId, transport, fetchFn, FormDataCtor) {
    const ajaxUrl = transport.ajaxUrl;
    const nonce = transport.nonce;
    const actions = transport.actions || {};
    const clientIdStr = String(clientId);

    function postJsonForm(action, fields) {
        const formData = new FormDataCtor();
        formData.append('action', action);
        formData.append('_wpnonce', nonce);
        Object.keys(fields).forEach(function (key) {
            formData.append(key, fields[key]);
        });
        return fetchFn(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (result) {
                return { httpStatus: response.status, result: result };
            });
        });
    }

    return {
        list: function () {
            return postJsonForm(actions.listRegistros, { client_id: clientIdStr });
        },
        create: function (draft) {
            draft = draft || {};
            return postJsonForm(actions.createRegistro, {
                client_id: clientIdStr,
                title: draft.title,
                body: draft.body
            });
        },
        update: function (recordId, draft) {
            draft = draft || {};
            return postJsonForm(actions.updateRegistro, {
                client_id: clientIdStr,
                record_id: String(recordId),
                title: draft.title,
                body: draft.body
            });
        },
        deleteRegistro: function (recordId) {
            return postJsonForm(actions.deleteRegistro, {
                client_id: clientIdStr,
                record_id: String(recordId)
            });
        },
        attach: function (recordId, fileBlob, uploadOperationId) {
            const formData = new FormDataCtor();
            formData.append('action', actions.attachRegistro);
            formData.append('_wpnonce', nonce);
            formData.append('client_id', clientIdStr);
            formData.append('record_id', String(recordId || ''));
            formData.append('upload_operation_id', uploadOperationId);
            formData.append('file', fileBlob, 'adjunto.jpg');
            return fetchFn(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (result) {
                    return { httpStatus: response.status, result: result };
                });
            });
        },
        signRead: function (recordId, attachmentId, variant, signal) {
            const formData = new FormDataCtor();
            formData.append('action', actions.signAdjuntoRead);
            formData.append('_wpnonce', nonce);
            formData.append('client_id', clientIdStr);
            formData.append('record_id', String(recordId));
            formData.append('attachment_id', String(attachmentId));
            formData.append('variant', variant);
            const options = {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            };
            if (signal) {
                options.signal = signal;
            }
            return fetchFn(ajaxUrl, options).then(function (response) {
                return response.json().then(function (result) {
                    return { httpStatus: response.status, result: result };
                });
            });
        },
        deleteAdjunto: function (recordId, attachmentId) {
            return postJsonForm(actions.deleteAdjunto, {
                client_id: clientIdStr,
                record_id: String(recordId),
                attachment_id: String(attachmentId)
            });
        }
    };
}

const FULL_TRANSPORT = {
    ajaxUrl: 'https://legacy.example/admin-ajax.php',
    nonce: 'nonce-legacy',
    actions: {
        listRegistros: 'aa_list_expediente_registros',
        createRegistro: 'aa_create_expediente_registro',
        updateRegistro: 'aa_update_expediente_registro',
        deleteRegistro: 'aa_delete_expediente_registro',
        attachRegistro: 'aa_attach_expediente_registro',
        signAdjuntoRead: 'aa_sign_expediente_adjunto_read',
        deleteAdjunto: 'aa_delete_expediente_adjunto'
    }
};

describe('ExpedienteRegistros ports injection (ciclo B1)', () => {
    it('fuente: create/update/attach despachan callPort sin client_id en el renderer', () => {
        assert.match(moduleSrc, /callPort\('list'\)/);
        assert.match(moduleSrc, /callPort\('create'/);
        assert.match(moduleSrc, /callPort\('update'/);
        assert.match(moduleSrc, /callPort\('attach'/);
        assert.match(moduleSrc, /callPort\('signRead'/);
        assert.match(moduleSrc, /callPort\('deleteRegistro'/);
        assert.match(moduleSrc, /callPort\('deleteAdjunto'/);
        assert.match(moduleSrc, /isPortsMode\(\)/);
        // En modalidad ports el create no arma FormData con client_id en el monolito.
        assert.match(
            moduleSrc,
            /saveRequest = isPortsMode\(\)\s*\n\s*\? callPort\('create', draft\)/
        );
    });

    it('ports inyectados: list/delete/sign/deleteAdjunto sin fetch legacy', async () => {
        const rec = recordingPorts();
        const ctx = makeSandbox({
            clientsData: {
                ajaxUrl: 'https://global.example/admin-ajax.php',
                actions: { listRegistros: 'aa_list_from_global' }
            },
            clientsNonces: { expediente_registros: 'nonce-global' },
            ajaxurl: 'https://window-ajaxurl.example/admin-ajax.php'
        });
        const root = createEl('div');

        ctx.api.init({
            clientId: 42,
            recordsRoot: root,
            transport: FULL_TRANSPORT,
            ports: rec.ports
        });
        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.fetchCalls.length, 0, 'no debe usar fetch/transport/globals');
        assert.equal(rec.calls.filter((c) => c.name === 'list').length, 1);
        assert.ok(ctx.testApi.getState().ports);
        assert.equal(ctx.testApi.getState().transport, null, 'ports gana: transport no queda activo');

        ctx.testApi.setState({
            records: [{
                id: 9,
                title: 'Uno',
                body: 'Texto',
                recorded_at: '2026-08-01 10:00:00',
                created_at: '2026-08-01 10:00:00',
                updated_at: null,
                adjuntos: [{
                    id: 5,
                    width: 800,
                    height: 600,
                    byte_size: 40000,
                    created_at: '2026-08-01 10:00:00'
                }],
                adjunto: {
                    id: 5,
                    width: 800,
                    height: 600,
                    byte_size: 40000,
                    created_at: '2026-08-01 10:00:00'
                }
            }]
        });

        ctx.testApi.confirmAndDeleteRegistro(9, null);
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(rec.calls.some((c) => c.name === 'deleteRegistro' && c.args[0] === 9), true);
        assert.equal(ctx.fetchCalls.length, 0);

        const box = createEl('div');
        box.setAttribute('data-adjunto-id', '5');
        box.setAttribute('data-variant', 'summary');
        ctx.testApi.setState({
            records: [{
                id: 9,
                title: 'Uno',
                body: 'Texto',
                recorded_at: '2026-08-01 10:00:00',
                created_at: '2026-08-01 10:00:00',
                updated_at: null,
                adjuntos: [{
                    id: 5,
                    width: 800,
                    height: 600,
                    byte_size: 40000,
                    created_at: '2026-08-01 10:00:00'
                }],
                adjunto: {
                    id: 5,
                    width: 800,
                    height: 600,
                    byte_size: 40000,
                    created_at: '2026-08-01 10:00:00'
                }
            }]
        });
        ctx.testApi.requestThumbFor(box, 9);
        await new Promise((resolve) => setImmediate(resolve));
        const signCall = rec.calls.find((c) => c.name === 'signRead');
        assert.ok(signCall);
        assert.equal(signCall.args[0], 9);
        assert.equal(signCall.args[1], 5);
        assert.equal(signCall.args[2], 'summary');
        assert.ok(signCall.args[3] == null || typeof signCall.args[3].aborted === 'boolean');
        assert.equal(ctx.fetchCalls.length, 0);

        const deleteBtn = createEl('button');
        deleteBtn.setAttribute('data-adjunto-id', '5');
        ctx.testApi.confirmAndDeleteAdjunto(9, deleteBtn);
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(
            rec.calls.some((c) => c.name === 'deleteAdjunto' && c.args[0] === 9 && c.args[1] === 5),
            true
        );
        assert.equal(ctx.fetchCalls.length, 0);
    });

    it('port ausente no hibrida con transport/globals', async () => {
        const listCalls = [];
        const ctx = makeSandbox({
            clientsData: {
                ajaxUrl: 'https://global.example/admin-ajax.php',
                actions: {
                    listRegistros: 'aa_list_from_global',
                    deleteRegistro: 'aa_delete_from_global'
                }
            },
            clientsNonces: { expediente_registros: 'nonce-global' }
        });
        const root = createEl('div');

        ctx.api.init({
            clientId: 7,
            recordsRoot: root,
            transport: FULL_TRANSPORT,
            ports: {
                list: function () {
                    listCalls.push(1);
                    return okPayload({ records: [] });
                }
                // deleteRegistro ausente a propósito
            }
        });
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(listCalls.length, 1);
        assert.equal(ctx.fetchCalls.length, 0);

        ctx.testApi.setState({
            records: [{
                id: 3,
                title: 'T',
                body: 'B',
                recorded_at: '2026-08-01 10:00:00',
                created_at: '2026-08-01 10:00:00',
                updated_at: null,
                adjuntos: [],
                adjunto: null
            }]
        });
        ctx.testApi.confirmAndDeleteRegistro(3, null);
        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.fetchCalls.length, 0, 'no petición híbrida al faltar port');
        assert.equal(ctx.testApi.getState().records.length, 1, 'estado intacto ante fallo controlado');
    });

    it('sin ports: transport del ciclo A sigue funcionando', async () => {
        const ctx = makeSandbox({
            clientsData: {},
            clientsNonces: {}
        });
        const root = createEl('div');
        ctx.api.init({
            clientId: 11,
            recordsRoot: root,
            transport: {
                ajaxUrl: 'https://injected.example/admin-ajax.php',
                nonce: 'nonce-injected',
                actions: Object.assign({}, FULL_TRANSPORT.actions, {
                    listRegistros: 'aa_list_injected'
                })
            }
        });
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(ctx.fetchCalls.length, 1);
        assert.equal(ctx.fetchCalls[0].url, 'https://injected.example/admin-ajax.php');
        assert.equal(formFields(ctx.fetchCalls[0].opts.body).action, 'aa_list_injected');
        assert.equal(ctx.testApi.getState().ports, null);
    });

    it('sin ports ni transport: fallback global', async () => {
        const ctx = makeSandbox({
            clientsData: {
                ajaxUrl: 'https://global.example/admin-ajax.php',
                actions: { listRegistros: 'aa_list_from_global' }
            },
            clientsNonces: { expediente_registros: 'nonce-global' }
        });
        const root = createEl('div');
        ctx.api.init({ clientId: 11, recordsRoot: root });
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(ctx.fetchCalls.length, 1);
        assert.equal(formFields(ctx.fetchCalls[0].opts.body).action, 'aa_list_from_global');
        assert.equal(formFields(ctx.fetchCalls[0].opts.body)._wpnonce, 'nonce-global');
    });

    it('lifecycle: destroy limpia ports; segundo init y respuesta tardía', async () => {
        const callsA = [];
        const callsB = [];
        let resolveLate;
        const ctx = makeSandbox();
        const rootA = createEl('div');
        const rootB = createEl('div');

        ctx.api.init({
            clientId: 1,
            recordsRoot: rootA,
            ports: {
                list: function () {
                    callsA.push('list');
                    return new Promise((resolve) => { resolveLate = resolve; });
                }
            }
        });
        assert.equal(callsA.length, 1);
        assert.ok(ctx.testApi.getState().ports);

        ctx.api.init({
            clientId: 2,
            recordsRoot: rootB,
            ports: {
                list: function () {
                    callsB.push('list');
                    return okPayload({ records: [] });
                }
            }
        });
        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(callsB.length, 1);
        assert.equal(ctx.testApi.getState().clientId, 2);
        assert.equal(typeof ctx.testApi.getState().ports.list, 'function');

        resolveLate({
            httpStatus: 200,
            result: {
                success: true,
                data: {
                    records: [{
                        id: 1,
                        title: 'Tardío',
                        body: 'X',
                        recorded_at: '2026-07-01 10:00:00',
                        created_at: '2026-07-01 10:00:00',
                        updated_at: null
                    }]
                }
            }
        });
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(ctx.testApi.getState().records.length, 0);
        assert.equal(ctx.testApi.getState().clientId, 2);

        ctx.api.destroy();
        assert.equal(ctx.testApi.getState().ports, null);
    });

    it('AbortSignal llega al port signRead', async () => {
        let receivedSignal = null;
        const ctx = makeSandbox();
        const root = createEl('div');
        ctx.api.init({
            clientId: 7,
            recordsRoot: root,
            ports: {
                list: function () { return okPayload({ records: [] }); },
                signRead: function (recordId, attachmentId, variant, signal) {
                    receivedSignal = signal;
                    return okPayload({
                        url: 'https://signed.example/x.jpg',
                        expires_in: 600,
                        variant: variant
                    });
                }
            }
        });
        await new Promise((resolve) => setImmediate(resolve));

        ctx.testApi.setState({
            records: [{
                id: 1,
                title: 'T',
                body: 'B',
                recorded_at: '2026-08-01 10:00:00',
                created_at: '2026-08-01 10:00:00',
                updated_at: null,
                adjuntos: [{
                    id: 5,
                    width: 800,
                    height: 600,
                    byte_size: 1000,
                    created_at: '2026-08-01 10:00:00'
                }],
                adjunto: {
                    id: 5,
                    width: 800,
                    height: 600,
                    byte_size: 1000,
                    created_at: '2026-08-01 10:00:00'
                }
            }]
        });
        const box = createEl('div');
        box.setAttribute('data-adjunto-id', '5');
        box.setAttribute('data-variant', 'summary');
        ctx.testApi.requestThumbFor(box, 1);
        await new Promise((resolve) => setImmediate(resolve));
        assert.ok(receivedSignal, 'debe recibir AbortSignal del controller');
        assert.equal(typeof receivedSignal.aborted, 'boolean');
    });

    it('wiring: clients-module crea los siete ports y cierra sobre clientId/transport', () => {
        assert.match(clientsModuleSrc, /function buildExpedienteRegistrosLegacyPorts/);
        assert.match(clientsModuleSrc, /function buildExpedienteRegistrosTransport/);
        assert.match(clientsModuleSrc, /ports:\s*buildExpedienteRegistrosLegacyPorts\(clientId,\s*transport,\s*session\)/);
        assert.match(clientsModuleSrc, /onCreateComplete/);
        assert.match(clientsModuleSrc, /detailCanonicalBaseUrl/);
        assert.match(clientsModuleSrc, /location\.replace/);
        assert.doesNotMatch(clientsModuleSrc, /location\.assign/);
        assert.doesNotMatch(clientsModuleSrc, /parent\.location/);
        assert.doesNotMatch(clientsModuleSrc, /top\.location/);
        assert.match(clientsModuleSrc, /transport:\s*transport/);
        assert.match(clientsModuleSrc, /list:\s*function/);
        assert.match(clientsModuleSrc, /create:\s*function/);
        assert.match(clientsModuleSrc, /update:\s*function/);
        assert.match(clientsModuleSrc, /deleteRegistro:\s*function/);
        assert.match(clientsModuleSrc, /attach:\s*function/);
        assert.match(clientsModuleSrc, /signRead:\s*function/);
        assert.match(clientsModuleSrc, /deleteAdjunto:\s*function/);
        assert.match(clientsModuleSrc, /client_id:\s*clientIdStr/);
        assert.match(clientsModuleSrc, /upload_operation_id/);
        assert.match(clientsModuleSrc, /'adjunto\.jpg'/);
        assert.match(clientsModuleSrc, /options\.signal\s*=\s*signal/);
        assert.match(clientsModuleSrc, /recordsRoot:\s*recordsRoot/);
    });

    it('equivalencia: adapter legacy produce los mismos campos por operación', async () => {
        const fetchCalls = [];
        class FakeFormData {
            constructor() { this.entries = []; }
            append(k, v, filename) {
                this.entries.push(filename !== undefined ? [k, v, filename] : [k, v]);
            }
        }
        const fetchFn = (url, opts) => {
            fetchCalls.push({ url, opts });
            return Promise.resolve({
                status: 200,
                json: async () => ({ success: true, data: {} })
            });
        };
        const ports = buildLegacyPortsLikeClientsModule(42, FULL_TRANSPORT, fetchFn, FakeFormData);

        await ports.list();
        await ports.create({ title: 'T', body: 'B' });
        await ports.update(9, { title: 'T2', body: 'B2' });
        await ports.deleteRegistro(9);
        await ports.attach(9, { size: 1 }, 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee');
        const ac = new AbortController();
        await ports.signRead(9, 5, 'summary', ac.signal);
        await ports.deleteAdjunto(9, 5);

        assert.equal(fetchCalls.length, 7);
        fetchCalls.forEach((c) => {
            assert.equal(c.url, FULL_TRANSPORT.ajaxUrl);
        });

        const list = formFields(fetchCalls[0].opts.body);
        assert.equal(list.action, 'aa_list_expediente_registros');
        assert.equal(list._wpnonce, 'nonce-legacy');
        assert.equal(list.client_id, '42');

        const create = formFields(fetchCalls[1].opts.body);
        assert.equal(create.action, 'aa_create_expediente_registro');
        assert.equal(create.title, 'T');
        assert.equal(create.body, 'B');
        assert.equal(create.client_id, '42');

        const update = formFields(fetchCalls[2].opts.body);
        assert.equal(update.action, 'aa_update_expediente_registro');
        assert.equal(update.record_id, '9');
        assert.equal(update.client_id, '42');

        const delReg = formFields(fetchCalls[3].opts.body);
        assert.equal(delReg.action, 'aa_delete_expediente_registro');
        assert.equal(delReg.record_id, '9');

        const attachEntries = fetchCalls[4].opts.body.entries;
        const attachMap = Object.fromEntries(attachEntries.map((e) => [e[0], e[1]]));
        assert.equal(attachMap.action, 'aa_attach_expediente_registro');
        assert.equal(attachMap.client_id, '42');
        assert.equal(attachMap.record_id, '9');
        assert.equal(attachMap.upload_operation_id, 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee');
        assert.ok(attachEntries.some((e) => e[0] === 'file'));

        const sign = formFields(fetchCalls[5].opts.body);
        assert.equal(sign.action, 'aa_sign_expediente_adjunto_read');
        assert.equal(sign.attachment_id, '5');
        assert.equal(sign.variant, 'summary');
        assert.equal(fetchCalls[5].opts.signal, ac.signal);

        const delAdj = formFields(fetchCalls[6].opts.body);
        assert.equal(delAdj.action, 'aa_delete_expediente_adjunto');
        assert.equal(delAdj.attachment_id, '5');
    });
});
