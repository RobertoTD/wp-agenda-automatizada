'use strict';

/**
 * C1c1 — onInitialLoad opt-in en ExpedienteRegistros.
 * Ejecutar: node --test tests/js/expedienteRegistrosOnInitialLoad.test.js
 */

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

function createEl(tag) {
    const el = {
        tagName: String(tag).toUpperCase(),
        className: '',
        id: '',
        textContent: '',
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

function loadRenderer(listImpl) {
    const windowObj = {
        AAAdmin: {
            openModal() {},
            closeModal() {},
            modal: { isOpen() { return false; } },
            toast: { show() {} }
        },
        AA_CLIENTS_DATA: {},
        AA_CLIENTS_NONCES: {},
        confirm: () => true,
        addEventListener() {},
        removeEventListener() {},
        setTimeout: (fn) => fn(),
        console: { error() {}, log() {} }
    };
    windowObj.window = windowObj;

    const sandbox = {
        window: windowObj,
        document: {
            createElement: createEl,
            getElementById: () => null,
            querySelector: () => null,
            contains: () => true,
            addEventListener() {},
            removeEventListener() {}
        },
        console: windowObj.console,
        fetch: () => Promise.resolve({
            status: 200,
            json: async () => ({ success: true, data: { records: [] } })
        }),
        FormData: class {
            constructor() { this.entries = []; }
            append(k, v) { this.entries.push([k, v]); }
        },
        AbortController,
        URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
        setTimeout: (fn) => fn(),
        Object,
        JSON,
        Math,
        parseInt,
        String,
        Array,
        Promise,
        crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
    };

    vm.runInNewContext(moduleSrc, sandbox);
    return {
        api: sandbox.window.AAAdmin.ExpedienteRegistros,
        testApi: sandbox.window.AAAdmin.ExpedienteRegistros.__test__
    };
}

function portsWithList(listFn) {
    return {
        list: listFn,
        create: () => Promise.resolve({ httpStatus: 200, result: { success: true, data: {} } }),
        attach: () => Promise.resolve({ httpStatus: 200, result: { success: true, data: {} } }),
        signRead: () => Promise.resolve({ httpStatus: 200, result: { success: true, data: {} } }),
        deleteAdjunto: () => Promise.resolve({ httpStatus: 200, result: { success: true, data: {} } })
    };
}

const CAPS = {
    createRegistro: true,
    updateRegistro: false,
    deleteRegistro: false,
    attach: true,
    signRead: true,
    deleteAdjunto: true
};

function waitMicro() {
    return new Promise((r) => setImmediate(r));
}

describe('ExpedienteRegistros onInitialLoad (C1c1)', () => {
    it('callback ausente → legacy intacto (sin throw, pinta lista)', async () => {
        const ctx = loadRenderer();
        const root = createEl('div');
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: root,
            capabilities: CAPS,
            ports: portsWithList(() => Promise.resolve({
                httpStatus: 200,
                result: {
                    success: true,
                    data: {
                        records: [{
                            id: 1,
                            title: 'A',
                            body: 'B',
                            recorded_at: '2026-08-20 10:00:00',
                            adjuntos: [],
                            adjunto: null
                        }]
                    }
                }
            }))
        });
        await waitMicro();
        assert.ok(root.children.length > 0);
        assert.equal(ctx.testApi.getState().initialLoadSettled, false);
    });

    it('éxito con records → callback una vez tras render', async () => {
        const ctx = loadRenderer();
        const root = createEl('div');
        const outcomes = [];
        let childCountAtCallback = -1;
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: root,
            capabilities: CAPS,
            ports: portsWithList(() => Promise.resolve({
                httpStatus: 200,
                result: {
                    success: true,
                    data: {
                        records: [{
                            id: 14,
                            title: 'R',
                            body: 'B',
                            recorded_at: '2026-08-20 10:00:00',
                            adjuntos: [],
                            adjunto: null
                        }]
                    }
                }
            })),
            onInitialLoad(outcome) {
                outcomes.push(outcome);
                childCountAtCallback = root.children.length;
            }
        });
        await waitMicro();
        assert.equal(outcomes.length, 1);
        assert.equal(outcomes[0].ok, true);
        assert.ok(childCountAtCallback > 0);
    });

    it('empty list → éxito', async () => {
        const ctx = loadRenderer();
        const outcomes = [];
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => Promise.resolve({
                httpStatus: 200,
                result: { success: true, data: { records: [] } }
            })),
            onInitialLoad(o) { outcomes.push(o); }
        });
        await waitMicro();
        assert.equal(outcomes.length, 1);
        assert.equal(outcomes[0].ok, true);
    });

    it('HTTP error → fallo', async () => {
        const ctx = loadRenderer();
        const outcomes = [];
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => Promise.resolve({
                httpStatus: 403,
                result: { success: false, data: { code: 'forbidden' } }
            })),
            onInitialLoad(o) { outcomes.push(o); }
        });
        await waitMicro();
        assert.equal(outcomes.length, 1);
        assert.equal(outcomes[0].ok, false);
        assert.equal(outcomes[0].reason, 'http_error');
    });

    it('success false → fallo', async () => {
        const ctx = loadRenderer();
        const outcomes = [];
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => Promise.resolve({
                httpStatus: 200,
                result: { success: false, data: { message: 'x' } }
            })),
            onInitialLoad(o) { outcomes.push(o); }
        });
        await waitMicro();
        assert.equal(outcomes[0].ok, false);
        assert.equal(outcomes[0].reason, 'list_failed');
    });

    it('records no array → fallo', async () => {
        const ctx = loadRenderer();
        const outcomes = [];
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => Promise.resolve({
                httpStatus: 200,
                result: { success: true, data: { records: null } }
            })),
            onInitialLoad(o) { outcomes.push(o); }
        });
        await waitMicro();
        assert.equal(outcomes[0].ok, false);
        assert.equal(outcomes[0].reason, 'records_invalid');
    });

    it('JSON inválido / red fallida → fallo', async () => {
        const ctx = loadRenderer();
        const outcomes = [];
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => {
                const err = new SyntaxError('bad json');
                return Promise.reject(err);
            }),
            onInitialLoad(o) { outcomes.push(o); }
        });
        await waitMicro();
        assert.equal(outcomes[0].ok, false);
        assert.equal(outcomes[0].reason, 'invalid_json');

        const ctx2 = loadRenderer();
        const outcomes2 = [];
        ctx2.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => Promise.reject(new TypeError('network'))),
            onInitialLoad(o) { outcomes2.push(o); }
        });
        await waitMicro();
        assert.equal(outcomes2[0].reason, 'network');
    });

    it('callback no repetido en reload interno', async () => {
        const ctx = loadRenderer();
        const outcomes = [];
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => Promise.resolve({
                httpStatus: 200,
                result: { success: true, data: { records: [] } }
            })),
            onInitialLoad(o) { outcomes.push(o); }
        });
        await waitMicro();
        assert.equal(outcomes.length, 1);
        await ctx.testApi.loadRecords();
        await waitMicro();
        assert.equal(outcomes.length, 1);
    });

    it('stale epoch no notifica', async () => {
        const ctx = loadRenderer();
        const outcomes = [];
        let resolveList;
        const listPromise = new Promise((resolve) => { resolveList = resolve; });
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => listPromise),
            onInitialLoad(o) { outcomes.push(o); }
        });
        ctx.api.destroy();
        resolveList({
            httpStatus: 200,
            result: { success: true, data: { records: [] } }
        });
        await waitMicro();
        await waitMicro();
        assert.equal(outcomes.length, 0);
    });

    it('destroy antes de respuesta no notifica', async () => {
        const ctx = loadRenderer();
        const outcomes = [];
        let resolveList;
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: createEl('div'),
            capabilities: CAPS,
            ports: portsWithList(() => new Promise((r) => { resolveList = r; })),
            onInitialLoad(o) { outcomes.push(o); }
        });
        ctx.api.destroy();
        resolveList({
            httpStatus: 200,
            result: { success: true, data: { records: [] } }
        });
        await waitMicro();
        assert.equal(outcomes.length, 0);
    });

    it('excepción del callback no rompe renderer ni re-notifica', async () => {
        const ctx = loadRenderer();
        const root = createEl('div');
        let calls = 0;
        ctx.api.init({
            scopeKey: 'expediente:1',
            recordsRoot: root,
            capabilities: CAPS,
            ports: portsWithList(() => Promise.resolve({
                httpStatus: 200,
                result: { success: true, data: { records: [] } }
            })),
            onInitialLoad() {
                calls += 1;
                throw new Error('callback boom');
            }
        });
        await waitMicro();
        assert.equal(calls, 1);
        assert.ok(root.children.length > 0);
        assert.equal(ctx.testApi.getState().initialLoadSettled, true);
        await ctx.testApi.loadRecords();
        await waitMicro();
        assert.equal(calls, 1);
    });
});
