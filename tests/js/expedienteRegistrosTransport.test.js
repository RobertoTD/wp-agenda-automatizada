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
        addEventListener() {},
        removeEventListener() {},
        querySelector() { return null; },
        querySelectorAll() { return []; }
    };
    Object.defineProperty(el, 'firstChild', {
        get() { return el.children[0] || null; }
    });
    return el;
}

function formFields(call) {
    return Object.fromEntries(call.opts.body.entries);
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

function fullTransport(overrides) {
    return Object.assign({
        ajaxUrl: 'https://injected.example/admin-ajax.php',
        nonce: 'nonce-injected',
        actions: {
            listRegistros: 'aa_list_injected',
            createRegistro: 'aa_create_injected',
            updateRegistro: 'aa_update_injected',
            deleteRegistro: 'aa_delete_injected',
            attachRegistro: 'aa_attach_injected',
            signAdjuntoRead: 'aa_sign_injected',
            deleteAdjunto: 'aa_delete_adjunto_injected'
        }
    }, overrides || {});
}

describe('ExpedienteRegistros transport injection (ciclo A)', () => {
    it('transporte inyectado: lista y segunda operación usan solo el adapter', async () => {
        const ctx = makeSandbox({
            clientsData: {},
            clientsNonces: {},
            ajaxurl: undefined
        });
        ctx.sandbox.window.confirm = () => true;
        const root = createEl('div');

        ctx.api.init({
            clientId: 42,
            recordsRoot: root,
            transport: fullTransport()
        });

        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.fetchCalls.length, 1, 'list debe enviar una petición');
        assert.equal(ctx.fetchCalls[0].url, 'https://injected.example/admin-ajax.php');
        const listFields = formFields(ctx.fetchCalls[0]);
        assert.equal(listFields.action, 'aa_list_injected');
        assert.equal(listFields._wpnonce, 'nonce-injected');
        assert.equal(listFields.client_id, '42');

        const transport = ctx.testApi.getState().transport;
        assert.ok(transport);
        assert.equal(transport.ajaxUrl, 'https://injected.example/admin-ajax.php');
        assert.equal(transport.nonce, 'nonce-injected');

        // Segunda operación: deleteRegistro resuelve action/nonce/url desde el mismo adapter.
        ctx.testApi.setState({
            records: [{
                id: 9,
                client_id: 42,
                title: 'Uno',
                body: 'Texto',
                recorded_at: '2026-08-01 10:00:00',
                created_at: '2026-08-01 10:00:00',
                updated_at: null,
                adjunto: null,
                adjuntos: []
            }]
        });
        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({
                success: true,
                data: { deleted: true, record_id: 9 }
            })
        }));
        ctx.testApi.confirmAndDeleteRegistro(9, null);
        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.fetchCalls.length, 2);
        assert.equal(ctx.fetchCalls[1].url, 'https://injected.example/admin-ajax.php');
        const deleteFields = formFields(ctx.fetchCalls[1]);
        assert.equal(deleteFields.action, 'aa_delete_injected');
        assert.equal(deleteFields._wpnonce, 'nonce-injected');
        assert.equal(deleteFields.client_id, '42');
        assert.equal(deleteFields.record_id, '9');
    });

    it('transporte incompleto no mezcla campos con globals válidos', async () => {
        const ctx = makeSandbox({
            clientsData: {
                ajaxUrl: 'https://global.example/admin-ajax.php',
                actions: {
                    listRegistros: 'aa_list_from_global',
                    createRegistro: 'aa_create_from_global',
                    updateRegistro: 'aa_update_from_global',
                    deleteRegistro: 'aa_delete_from_global',
                    attachRegistro: 'aa_attach_from_global',
                    signAdjuntoRead: 'aa_sign_from_global',
                    deleteAdjunto: 'aa_delete_adjunto_from_global'
                }
            },
            clientsNonces: { expediente_registros: 'nonce-global' },
            ajaxurl: 'https://window-ajaxurl.example/admin-ajax.php'
        });
        const root = createEl('div');

        // Adapter con ajaxUrl/nonce válidos pero sin listRegistros: no debe tomar action del global.
        ctx.api.init({
            clientId: 7,
            recordsRoot: root,
            transport: {
                ajaxUrl: 'https://injected.example/admin-ajax.php',
                nonce: 'nonce-injected',
                actions: {
                    createRegistro: 'aa_create_injected'
                }
            }
        });

        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.fetchCalls.length, 0, 'no debe enviar petición híbrida');
        assert.equal(ctx.testApi.getState().loading, false);
        assert.match(root.children[0].textContent, /Transporte incompleto|No se pudieron cargar/);
    });

    it('transporte inválido en init no monta ni mezcla con globals', async () => {
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
            clientId: 7,
            recordsRoot: root,
            transport: {
                ajaxUrl: 'https://injected.example/admin-ajax.php',
                // nonce ausente a propósito
                actions: { listRegistros: 'aa_list_injected' }
            }
        });

        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.fetchCalls.length, 0);
        assert.equal(ctx.testApi.getState().transport, null);
        assert.equal(ctx.testApi.getState().clientId, 0);
        assert.equal(ctx.testApi.getState().recordsRoot, null);
    });

    it('fallback legacy sin transport sigue leyendo globals', async () => {
        const ctx = makeSandbox({
            clientsData: {
                ajaxUrl: 'https://global.example/admin-ajax.php',
                actions: {
                    listRegistros: 'aa_list_from_global',
                    createRegistro: 'aa_create_from_global',
                    updateRegistro: 'aa_update_from_global',
                    deleteRegistro: 'aa_delete_from_global',
                    attachRegistro: 'aa_attach_from_global',
                    signAdjuntoRead: 'aa_sign_from_global',
                    deleteAdjunto: 'aa_delete_adjunto_from_global'
                }
            },
            clientsNonces: { expediente_registros: 'nonce-global' }
        });
        const root = createEl('div');

        ctx.api.init({
            clientId: 11,
            recordsRoot: root
        });

        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.fetchCalls.length, 1);
        assert.equal(ctx.fetchCalls[0].url, 'https://global.example/admin-ajax.php');
        const fields = formFields(ctx.fetchCalls[0]);
        assert.equal(fields.action, 'aa_list_from_global');
        assert.equal(fields._wpnonce, 'nonce-global');
        assert.equal(fields.client_id, '11');
        assert.equal(ctx.testApi.getState().transport, null);
    });

    it('lifecycle: destroy limpia transport; segundo init usa solo el nuevo adapter; respuesta tardía no pinta', async () => {
        const ctx = makeSandbox({
            clientsData: {},
            clientsNonces: {}
        });
        const rootA = createEl('div');
        const rootB = createEl('div');

        let resolveLate;
        ctx.setFetch(() => new Promise((resolve) => { resolveLate = resolve; }));

        ctx.api.init({
            clientId: 1,
            recordsRoot: rootA,
            transport: fullTransport({
                ajaxUrl: 'https://first.example/admin-ajax.php',
                nonce: 'nonce-first',
                actions: Object.assign({}, fullTransport().actions, {
                    listRegistros: 'aa_list_first'
                })
            })
        });

        assert.equal(ctx.fetchCalls.length, 1);
        assert.equal(ctx.fetchCalls[0].url, 'https://first.example/admin-ajax.php');
        assert.equal(formFields(ctx.fetchCalls[0]).action, 'aa_list_first');
        assert.equal(ctx.testApi.getState().transport.nonce, 'nonce-first');

        const epochAfterFirst = ctx.testApi.getThumbs().viewEpoch;

        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({
                success: true,
                data: { records: [] }
            })
        }));

        ctx.api.init({
            clientId: 2,
            recordsRoot: rootB,
            transport: fullTransport({
                ajaxUrl: 'https://second.example/admin-ajax.php',
                nonce: 'nonce-second',
                actions: Object.assign({}, fullTransport().actions, {
                    listRegistros: 'aa_list_second'
                })
            })
        });

        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.testApi.getState().transport.ajaxUrl, 'https://second.example/admin-ajax.php');
        assert.equal(ctx.testApi.getState().transport.nonce, 'nonce-second');
        assert.equal(ctx.fetchCalls.length, 2);
        assert.equal(ctx.fetchCalls[1].url, 'https://second.example/admin-ajax.php');
        assert.equal(formFields(ctx.fetchCalls[1]).action, 'aa_list_second');
        assert.equal(formFields(ctx.fetchCalls[1]).client_id, '2');
        assert.ok(ctx.testApi.getThumbs().viewEpoch > epochAfterFirst);
        assert.equal(ctx.testApi.getState().records.length, 0);

        // Respuesta tardía del montaje anterior: no debe alterar el montaje actual.
        resolveLate({
            status: 200,
            json: async () => ({
                success: true,
                data: {
                    records: [{
                        id: 1,
                        client_id: 1,
                        title: 'Tardío',
                        body: 'X',
                        recorded_at: '2026-07-01 10:00:00',
                        created_at: '2026-07-01 10:00:00',
                        updated_at: null,
                        adjunto: null
                    }]
                }
            })
        });
        await new Promise((resolve) => setImmediate(resolve));

        const state = ctx.testApi.getState();
        assert.equal(state.clientId, 2);
        assert.equal(state.recordsRoot, rootB);
        assert.equal(state.records.length, 0);
        assert.equal(state.transport.nonce, 'nonce-second');

        ctx.api.destroy();
        assert.equal(ctx.testApi.getState().transport, null);
        assert.equal(ctx.testApi.getState().clientId, 0);
    });

    it('wiring: clients-module pasa transport explícito (ajaxUrl, nonce, actions)', () => {
        assert.match(clientsModuleSrc, /transport:\s*\{/);
        assert.match(clientsModuleSrc, /ajaxUrl:\s*data\.ajaxUrl\s*\|\|\s*window\.ajaxurl/);
        assert.match(clientsModuleSrc, /nonce:\s*nonces\.expediente_registros/);
        assert.match(clientsModuleSrc, /actions:\s*\{/);
        assert.match(clientsModuleSrc, /listRegistros:/);
        assert.match(clientsModuleSrc, /createRegistro:/);
        assert.match(clientsModuleSrc, /updateRegistro:/);
        assert.match(clientsModuleSrc, /deleteRegistro:/);
        assert.match(clientsModuleSrc, /attachRegistro:/);
        assert.match(clientsModuleSrc, /signAdjuntoRead:/);
        assert.match(clientsModuleSrc, /deleteAdjunto:/);
        assert.match(clientsModuleSrc, /clientId:\s*clientId/);
        assert.match(clientsModuleSrc, /recordsRoot:\s*recordsRoot/);
    });
});
