'use strict';

/**
 * C1c2 — FAB adoption + navigation en CanonicalMount.
 * Ejecutar: node --test tests/js/expedienteRegistrosCanonicalMountC1c2.test.js
 */

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('path');
const vm = require('node:vm');

const rootDir = path.join(__dirname, '../..');
const rendererSrc = fs.readFileSync(
    path.join(rootDir, 'includes/admin/ui/modules/clients/expediente-registros.js'),
    'utf8'
);
const adapterSrc = fs.readFileSync(
    path.join(rootDir, 'includes/admin/ui/modules/expedientes/expediente-registros-canonical-adapter.js'),
    'utf8'
);
const mountSrc = fs.readFileSync(
    path.join(rootDir, 'includes/admin/ui/modules/expedientes/expediente-registros-canonical-mount.js'),
    'utf8'
);
const detailSrc = fs.readFileSync(
    path.join(rootDir, 'includes/admin/ui/modules/expedientes/detail.php'),
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
    attachRegistro: 'aa_attach_expediente_adjunto_for_expediente',
    signAdjuntoRead: 'aa_sign_expediente_adjunto_read_for_expediente',
    deleteAdjunto: 'aa_delete_expediente_adjunto_for_expediente'
};

function wait() {
    return new Promise((r) => setImmediate(r));
}

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(),
        className: '',
        id: id || '',
        hidden: false,
        textContent: '',
        children: [],
        attributes: Object.create(null),
        parentNode: null,
        _listeners: Object.create(null),
        classList: {
            _set: new Set(),
            add(c) { this._set.add(c); },
            remove(c) { this._set.delete(c); },
            contains(c) { return this._set.has(c); }
        },
        setAttribute(name, value) {
            el.attributes[name] = String(value);
            if (name === 'hidden') el.hidden = true;
        },
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(el.attributes, name)
                ? el.attributes[name]
                : null;
        },
        hasAttribute(name) {
            return Object.prototype.hasOwnProperty.call(el.attributes, name);
        },
        removeAttribute(name) {
            delete el.attributes[name];
            if (name === 'hidden') el.hidden = false;
        },
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
        replaceChild(neu, old) {
            const i = el.children.indexOf(old);
            if (i >= 0) {
                el.children[i] = neu;
                neu.parentNode = el;
                old.parentNode = null;
            }
            return old;
        },
        contains(node) {
            if (node === el) return true;
            let found = false;
            function walk(n) {
                (n.children || []).forEach((c) => {
                    if (c === node) found = true;
                    walk(c);
                });
            }
            walk(el);
            return found;
        },
        querySelector(sel) {
            return el.querySelectorAll(sel)[0] || null;
        },
        querySelectorAll(sel) {
            const out = [];
            function walk(node) {
                (node.children || []).forEach((child) => {
                    const cn = child.className || '';
                    if (sel === '.aa-expediente-detail-pagination'
                        && cn.indexOf('aa-expediente-detail-pagination') !== -1) {
                        out.push(child);
                    }
                    if (sel === '.aa-expediente-detail-registros-title'
                        && cn.indexOf('aa-expediente-detail-registros-title') !== -1) {
                        out.push(child);
                    }
                    if (sel === '.aa-expediente-btn-editar'
                        && cn.indexOf('aa-expediente-btn-editar') !== -1) {
                        out.push(child);
                    }
                    if (sel === '.aa-expediente-btn-eliminar'
                        && cn.indexOf('aa-expediente-btn-eliminar') !== -1) {
                        out.push(child);
                    }
                    walk(child);
                });
            }
            walk(el);
            return out;
        },
        cloneNode() {
            const c = createEl(el.tagName, el.id);
            c.className = el.className;
            c.textContent = el.textContent;
            c.attributes = Object.assign({}, el.attributes);
            c.classList._set = new Set(el.classList._set);
            return c;
        },
        addEventListener(type, fn) {
            el._listeners[type] = el._listeners[type] || [];
            el._listeners[type].push(fn);
        },
        removeEventListener(type, fn) {
            el._listeners[type] = (el._listeners[type] || []).filter((f) => f !== fn);
        },
        click() {
            (el._listeners.click || []).forEach((fn) => fn({ preventDefault() {} }));
        },
        focus() {}
    };
    Object.defineProperty(el, 'firstChild', {
        get() { return el.children[0] || null; }
    });
    return el;
}

function validConfig(overrides) {
    return Object.assign({
        ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
        nonce: 'nonce-x',
        action: VALID_ACTIONS.createRegistro,
        expedienteId: '5',
        successUrl: 'https://example.test/detail?expediente_id=5',
        scopeKey: 'expediente:5',
        recordsPage: 1,
        actions: Object.assign({}, VALID_ACTIONS),
        capabilities: Object.assign({}, VALID_CAPS)
    }, overrides || {});
}

function loadHarness(opts) {
    opts = opts || {};
    const byId = Object.create(null);
    const assigns = [];
    const openCreateCalls = [];

    const section = createEl('div', 'aa-expediente-detail-registros');
    const title = createEl('h4');
    title.className = 'aa-expediente-detail-registros-title';
    const ssr = createEl('div', 'aa-expediente-detail-registros-ssr');
    ssr.textContent = 'SSR';
    const live = createEl('div', 'aa-expediente-detail-registros-live');
    live.classList.add('hidden');
    live.hidden = true;
    live.setAttribute('hidden', '');
    live.setAttribute('aria-hidden', 'true');
    const nav = createEl('nav');
    nav.className = 'aa-expediente-detail-pagination';
    const fabStack = createEl('div', 'aa-expediente-detail-fab-stack');
    const fab = createEl('button', 'aa-expediente-detail-new-registro');
    fab.className = 'aa-expediente-detail-fab';
    fab.setAttribute('aria-label', 'Nuevo registro');
    // provisional listener
    fab.addEventListener('click', function provisional() {
        fab._provisionalHits = (fab._provisionalHits || 0) + 1;
    });
    fabStack.appendChild(fab);
    section.appendChild(title);
    section.appendChild(ssr);
    section.appendChild(live);
    section.appendChild(nav);

    byId['aa-expediente-detail-registros'] = section;
    byId['aa-expediente-detail-registros-ssr'] = ssr;
    byId['aa-expediente-detail-registros-live'] = live;
    byId['aa-expediente-detail-new-registro'] = fab;

    function refreshFabId() {
        const current = fabStack.children[0];
        byId['aa-expediente-detail-new-registro'] = current || null;
        return current;
    }

    // patch replaceChild on fabStack to update byId
    const origReplace = fabStack.replaceChild.bind(fabStack);
    fabStack.replaceChild = function (neu, old) {
        const r = origReplace(neu, old);
        refreshFabId();
        return r;
    };

    const listPayload = opts.listPayload || {
        status: 200,
        body: { success: true, data: { records: [] } }
    };

    const windowObj = {
        AAAdmin: {
            openModal() {},
            closeModal() {},
            modal: { isOpen() { return false; } },
            toast: { show() {} }
        },
        AA_EXPEDIENTE_DETAIL_DATA: opts.noConfig ? undefined : validConfig(opts.config),
        addEventListener() {},
        removeEventListener() {},
        setTimeout: (fn) => fn(),
        console: { error() {}, log() {} },
        location: {
            assign(url) { assigns.push(String(url)); }
        }
    };
    windowObj.window = windowObj;

    const documentEl = {
        readyState: 'loading',
        createElement: createEl,
        getElementById(id) { return byId[id] || null; },
        querySelector() { return null; },
        addEventListener() {},
        removeEventListener() {},
        contains: () => true
    };

    const sandbox = {
        window: windowObj,
        document: documentEl,
        console: windowObj.console,
        fetch: function () {
            if (listPayload.reject) {
                return Promise.reject(listPayload.reject);
            }
            if (listPayload.invalidJson) {
                return Promise.resolve({
                    status: 200,
                    json: async () => { throw new SyntaxError('bad'); }
                });
            }
            return Promise.resolve({
                status: listPayload.status || 200,
                json: async () => listPayload.body
            });
        },
        FormData: class {
            constructor() { this.entries = []; }
            append(k, v) { this.entries.push([k, v]); }
        },
        AbortController,
        URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
        setTimeout: (fn) => fn(),
        Object, JSON, Math, parseInt, String, Array, Number, isFinite, Promise,
        crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
    };

    vm.runInNewContext(rendererSrc, sandbox);
    const realOpenCreate = sandbox.window.AAAdmin.ExpedienteRegistros.openCreate;
    sandbox.window.AAAdmin.ExpedienteRegistros.openCreate = function (el) {
        openCreateCalls.push(el || null);
        return realOpenCreate.call(this, el);
    };
    vm.runInNewContext(adapterSrc, sandbox);
    if (opts.nullBuild) {
        sandbox.window.AAAdmin.ExpedienteRegistrosCanonicalAdapter.build = () => null;
    }
    vm.runInNewContext(mountSrc, sandbox);

    return {
        mountApi: sandbox.window.AAAdmin.ExpedienteRegistrosCanonicalMount,
        renderer: sandbox.window.AAAdmin.ExpedienteRegistros,
        ssr,
        live,
        fab,
        fabStack,
        section,
        assigns,
        openCreateCalls,
        refreshFabId,
        getFab: () => byId['aa-expediente-detail-new-registro']
    };
}

describe('CanonicalMount C1c2 FAB + navigation', () => {
    it('detail conserva provisional y no openCreate en PHP', () => {
        assert.match(detailSrc, /expediente-registro-create-modal\.js/);
        assert.match(detailSrc, /expediente-registros-canonical-mount\.js/);
        assert.doesNotMatch(detailSrc, /ExpedienteRegistros\.openCreate/);
    });

    it('adopta FAB solo tras readiness; click llama openCreate una vez', async () => {
        const h = loadHarness();
        h.mountApi.mount();
        await wait();
        await wait();
        const adopted = h.getFab();
        assert.notEqual(adopted, h.fab);
        assert.equal(adopted.id, 'aa-expediente-detail-new-registro');
        assert.equal(adopted.getAttribute('aria-label'), 'Nuevo registro');
        adopted.click();
        assert.equal(h.openCreateCalls.length, 1);
        // provisional listener no está en el clone
        assert.equal(adopted._provisionalHits, undefined);
        assert.equal(h.fab._provisionalHits || 0, 0);
    });

    it('fallo inicial conserva SSR y FAB provisional', async () => {
        const h = loadHarness({
            listPayload: {
                status: 500,
                body: { success: false, data: { code: 'x' } }
            }
        });
        h.mountApi.mount();
        await wait();
        await wait();
        assert.equal(h.ssr.hidden, false);
        assert.equal(h.getFab(), h.fab);
        h.fab.click();
        assert.equal(h.fab._provisionalHits, 1);
        assert.equal(h.openCreateCalls.length, 0);
    });

    it('successUrl inválida conserva FAB provisional tras swap', async () => {
        const h = loadHarness({
            config: { successUrl: '' }
        });
        h.mountApi.mount();
        await wait();
        await wait();
        assert.equal(h.live.hidden, false);
        assert.equal(h.getFab(), h.fab);
        assert.equal(h.openCreateCalls.length, 0);
    });

    it('solo un FAB con ese ID; destroy restaura provisional', async () => {
        const h = loadHarness();
        h.mountApi.mount();
        await wait();
        await wait();
        assert.equal(h.fabStack.children.length, 1);
        assert.equal(h.getFab().id, 'aa-expediente-detail-new-registro');
        h.mountApi.destroy();
        assert.equal(h.getFab(), h.fab);
        assert.equal(h.fabStack.children[0], h.fab);
        h.fab.click();
        assert.equal(h.fab._provisionalHits, 1);
    });

    it('onCreateComplete terminal programa una sola navegación a successUrl', async () => {
        const h = loadHarness();
        h.mountApi.mount();
        await wait();
        await wait();
        const state = h.renderer.__test__.getState();
        assert.equal(typeof state.onCreateComplete, 'function');
        state.onCreateComplete({ recordId: 14, imageOutcome: 'none' });
        state.onCreateComplete({ recordId: 14, imageOutcome: 'none' });
        assert.equal(h.assigns.length, 1);
        assert.equal(h.assigns[0], 'https://example.test/detail?expediente_id=5');
        assert.ok(!h.assigns[0].includes('records_page'));
    });

    it('destroy impide navegación posterior', async () => {
        const h = loadHarness();
        h.mountApi.mount();
        await wait();
        await wait();
        const cb = h.renderer.__test__.getState().onCreateComplete;
        h.mountApi.destroy();
        if (cb) cb({ recordId: 1, imageOutcome: 'saved' });
        assert.equal(h.assigns.length, 0);
    });

    it('mount → destroy → mount no acumula listeners en FAB', async () => {
        const h = loadHarness();
        h.mountApi.mount();
        await wait();
        await wait();
        h.mountApi.destroy();
        h.mountApi.mount();
        await wait();
        await wait();
        const fab = h.getFab();
        fab.click();
        assert.equal(h.openCreateCalls.length, 1);
    });

    it('sin Editar/Eliminar registro en listado canónico montado', async () => {
        const h = loadHarness({
            listPayload: {
                status: 200,
                body: {
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
            }
        });
        h.mountApi.mount();
        await wait();
        await wait();
        assert.equal(h.live.querySelector('.aa-expediente-btn-editar'), null);
        assert.equal(h.live.querySelector('.aa-expediente-btn-eliminar'), null);
    });

    it('fuente mount: sin client_id; usa onCreateComplete y successUrl', () => {
        assert.doesNotMatch(mountSrc, /client_id/);
        assert.doesNotMatch(mountSrc, /clientId/);
        assert.match(mountSrc, /onCreateComplete/);
        assert.match(mountSrc, /successUrl/);
        assert.match(mountSrc, /cloneNode/);
        assert.match(mountSrc, /location\.assign/);
    });
});
