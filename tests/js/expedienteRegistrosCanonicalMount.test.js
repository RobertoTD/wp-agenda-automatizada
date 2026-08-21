'use strict';

/**
 * C1c1 — ExpedienteRegistrosCanonicalMount.
 * Ejecutar: node --test tests/js/expedienteRegistrosCanonicalMount.test.js
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
        classList: {
            _set: new Set(),
            add(c) { this._set.add(c); },
            remove(c) { this._set.delete(c); },
            contains(c) { return this._set.has(c); }
        },
        setAttribute(name, value) {
            el.attributes[name] = String(value);
            if (name === 'hidden') {
                el.hidden = true;
            }
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
            if (name === 'hidden') {
                el.hidden = false;
            }
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
        contains(node) {
            if (node === el) {
                return true;
            }
            let found = false;
            function walk(n) {
                (n.children || []).forEach((c) => {
                    if (c === node) {
                        found = true;
                    }
                    walk(c);
                });
            }
            walk(el);
            return found;
        },
        querySelector(sel) {
            const all = el.querySelectorAll(sel);
            return all[0] || null;
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
                    walk(child);
                });
            }
            walk(el);
            return out;
        },
        addEventListener() {},
        removeEventListener() {}
    };
    Object.defineProperty(el, 'firstChild', {
        get() { return el.children[0] || null; }
    });
    return el;
}

function validConfig() {
    return {
        ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
        nonce: 'nonce-x',
        action: VALID_ACTIONS.createRegistro,
        expedienteId: '5',
        successUrl: 'https://example.test/detail',
        scopeKey: 'expediente:5',
        recordsPage: 1,
        actions: Object.assign({}, VALID_ACTIONS),
        capabilities: Object.assign({}, VALID_CAPS)
    };
}

function waitMicro() {
    return new Promise((r) => setImmediate(r));
}

/**
 * @param {{listPayload?: object|Function, omit?: string[], readyState?: string}} opts
 */
function loadHarness(opts) {
    opts = opts || {};
    const byId = Object.create(null);
    const initCalls = [];
    let listImpl = opts.listPayload;

    const section = createEl('div', 'aa-expediente-detail-registros');
    const title = createEl('h4');
    title.className = 'aa-expediente-detail-registros-title';
    title.textContent = 'Registros';
    const ssr = createEl('div', 'aa-expediente-detail-registros-ssr');
    ssr.textContent = 'SSR CONTENT';
    const live = createEl('div', 'aa-expediente-detail-registros-live');
    live.classList.add('hidden');
    live.hidden = true;
    live.setAttribute('hidden', '');
    live.setAttribute('aria-hidden', 'true');
    const nav = createEl('nav');
    nav.className = 'aa-expediente-detail-pagination';
    const fab = createEl('button', 'aa-expediente-detail-new-registro');

    section.appendChild(title);
    section.appendChild(ssr);
    section.appendChild(live);
    section.appendChild(nav);

    byId['aa-expediente-detail-registros'] = section;
    byId['aa-expediente-detail-registros-ssr'] = ssr;
    byId['aa-expediente-detail-registros-live'] = live;
    byId['aa-expediente-detail-new-registro'] = fab;

    (opts.omit || []).forEach((id) => { delete byId[id]; });

    class FakeFormData {
        constructor() { this.entries = []; }
        append(k, v) { this.entries.push([k, v]); }
    }

    const windowObj = {
        AAAdmin: {
            openModal() {},
            closeModal() {},
            modal: { isOpen() { return false; } },
            toast: { show() {} }
        },
        AA_EXPEDIENTE_DETAIL_DATA: opts.noConfig ? undefined : validConfig(),
        addEventListener() {},
        removeEventListener() {},
        setTimeout: (fn) => fn(),
        console: { error() {}, log() {} }
    };
    windowObj.window = windowObj;

    const documentEl = {
        readyState: opts.readyState || 'loading',
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
            let payload;
            if (typeof listImpl === 'function') {
                return listImpl();
            }
            if (listImpl && listImpl.reject) {
                return Promise.reject(listImpl.reject);
            }
            payload = listImpl || {
                status: 200,
                body: { success: true, data: { records: [] } }
            };
            if (payload.invalidJson) {
                return Promise.resolve({
                    status: payload.status || 200,
                    json: async () => { throw new SyntaxError('bad'); }
                });
            }
            return Promise.resolve({
                status: payload.status || 200,
                json: async () => payload.body
            });
        },
        FormData: FakeFormData,
        AbortController,
        URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
        setTimeout: (fn) => fn(),
        Object,
        JSON,
        Math,
        parseInt,
        String,
        Array,
        Number,
        isFinite,
        Promise,
        crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
    };

    if (!opts.skipRenderer) {
        vm.runInNewContext(rendererSrc, sandbox);
        const realInit = sandbox.window.AAAdmin.ExpedienteRegistros.init;
        sandbox.window.AAAdmin.ExpedienteRegistros.init = function (options) {
            initCalls.push(options);
            return realInit.call(this, options);
        };
    }
    if (!opts.skipAdapter) {
        vm.runInNewContext(adapterSrc, sandbox);
    }
    if (opts.nullBuild && sandbox.window.AAAdmin.ExpedienteRegistrosCanonicalAdapter) {
        sandbox.window.AAAdmin.ExpedienteRegistrosCanonicalAdapter.build = function () {
            return null;
        };
    }
    if (!opts.skipMount) {
        vm.runInNewContext(mountSrc, sandbox);
    }

    return {
        mountApi: sandbox.window.AAAdmin.ExpedienteRegistrosCanonicalMount,
        renderer: sandbox.window.AAAdmin.ExpedienteRegistros,
        ssr,
        live,
        nav,
        fab,
        section,
        initCalls,
        setList(next) { listImpl = next; },
        byId
    };
}

describe('ExpedienteRegistrosCanonicalMount (C1c1)', () => {
    it('detail.php: markup SSR/live + scripts en orden sin placement/clients', () => {
        assert.match(detailSrc, /id="aa-expediente-detail-registros-ssr"/);
        assert.match(detailSrc, /id="aa-expediente-detail-registros-live"/);
        assert.match(detailSrc, /aria-live="polite"/);
        assert.doesNotMatch(
            detailSrc,
            /id="aa-expediente-detail-registros"[^>]*aria-live/
        );
        assert.match(detailSrc, /expediente-registros\.js/);
        assert.match(detailSrc, /expediente-registros-canonical-adapter\.js/);
        assert.match(detailSrc, /expediente-registros-canonical-mount\.js/);
        assert.match(detailSrc, /expediente-registro-create-modal\.js/);
        assert.doesNotMatch(detailSrc, /clients-module\.js/);
        assert.doesNotMatch(detailSrc, /executable-options-menu-placement/);
        const reg = detailSrc.indexOf('expediente-registros.js');
        const ada = detailSrc.indexOf('expediente-registros-canonical-adapter.js');
        const mnt = detailSrc.indexOf('expediente-registros-canonical-mount.js');
        const cre = detailSrc.indexOf('expediente-registro-create-modal.js');
        assert.ok(reg > 0 && ada > reg && mnt > ada && cre > mnt);
        assert.match(detailSrc, /ExpedienteRegistrosCanonicalMount|canonical-mount/);
        assert.doesNotMatch(detailSrc, /ExpedienteRegistros\.openCreate/);
        assert.doesNotMatch(detailSrc, /onCreateComplete/);
    });

    it('precondiciones: sin config/adapter/build/renderer/roots/FAB → no toca SSR', async () => {
        const cases = [
            { noConfig: true },
            { skipAdapter: true },
            { nullBuild: true },
            { skipRenderer: true },
            { omit: ['aa-expediente-detail-registros-ssr'] },
            { omit: ['aa-expediente-detail-new-registro'] }
        ];
        for (const c of cases) {
            const h = loadHarness(Object.assign({
                listPayload: { status: 200, body: { success: true, data: { records: [] } } }
            }, c));
            // autoMount ya corrió y falló o no aplicó
            if (h.mountApi) {
                h.mountApi.destroy();
                const ok = h.mountApi.mount();
                assert.equal(ok, false);
            }
            assert.equal(h.ssr.hidden, false);
            assert.equal(h.ssr.textContent, 'SSR CONTENT');
            assert.equal(h.fab.id, 'aa-expediente-detail-new-registro');
        }
    });

    it('live oculto; list fallo → SSR visible, FAB intacto, renderer destruido', async () => {
        const statuses = [403, 404, 500];
        for (const status of statuses) {
            const h = loadHarness({
                listPayload: {
                    status,
                    body: { success: false, data: { code: 'x' } }
                }
            });
            h.mountApi.destroy();
            assert.equal(h.live.hidden, true);
            h.mountApi.mount();
            await waitMicro();
            await waitMicro();
            assert.equal(h.ssr.hidden, false);
            assert.equal(h.ssr.getAttribute('aria-hidden'), null);
            assert.equal(h.live.hidden, true);
            assert.ok(h.nav.parentNode === h.section);
            assert.equal(h.fab.id, 'aa-expediente-detail-new-registro');
            assert.equal(h.renderer.__test__.getState().recordsRoot, null);
        }
    });

    it('JSON inválido y red → fallback SSR', async () => {
        const h1 = loadHarness({
            listPayload: { invalidJson: true, status: 200 }
        });
        h1.mountApi.destroy();
        h1.mountApi.mount();
        await waitMicro();
        assert.equal(h1.ssr.hidden, false);

        const h2 = loadHarness({
            listPayload: { reject: new TypeError('network') }
        });
        h2.mountApi.destroy();
        h2.mountApi.mount();
        await waitMicro();
        assert.equal(h2.ssr.hidden, false);
        assert.equal(h2.live.hidden, true);
    });

    it('éxito: init con liveRoot/scope/ports/caps y swap una vez', async () => {
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
        h.mountApi.destroy();
        const first = h.mountApi.mount();
        assert.equal(first, true);
        await waitMicro();
        await waitMicro();

        assert.equal(h.initCalls.length, 1);
        const opts = h.initCalls[0];
        assert.equal(opts.recordsRoot, h.live);
        assert.equal(opts.scopeKey, 'expediente:5');
        assert.equal(typeof opts.ports.list, 'function');
        assert.equal(typeof opts.ports.create, 'function');
        assert.equal(typeof opts.ports.attach, 'function');
        assert.equal(typeof opts.ports.signRead, 'function');
        assert.equal(typeof opts.ports.deleteAdjunto, 'function');
        assert.equal(opts.ports.update, undefined);
        assert.equal(JSON.stringify(opts.capabilities), JSON.stringify(VALID_CAPS));
        assert.equal(typeof opts.onInitialLoad, 'function');

        assert.equal(h.ssr.hidden, true);
        assert.ok(h.ssr.classList.contains('hidden'));
        assert.equal(h.ssr.getAttribute('aria-hidden'), 'true');
        assert.equal(h.live.hidden, false);
        assert.equal(h.live.hasAttribute('hidden'), false);
        assert.equal(h.live.getAttribute('aria-hidden'), null);
        assert.ok(!h.live.classList.contains('hidden'));
        assert.ok(h.nav.parentNode === h.section);
        assert.equal(h.fab.id, 'aa-expediente-detail-new-registro');

        const second = h.mountApi.mount();
        assert.equal(second, false);
        assert.equal(h.initCalls.length, 1);
    });

    it('destroy delega al renderer y permite remount; no restaura SSR tras swap', async () => {
        const h = loadHarness({
            listPayload: {
                status: 200,
                body: { success: true, data: { records: [] } }
            }
        });
        h.mountApi.destroy();
        h.mountApi.mount();
        await waitMicro();
        assert.equal(h.ssr.hidden, true);

        h.mountApi.destroy();
        assert.equal(h.renderer.__test__.getState().recordsRoot, null);
        assert.equal(h.live.hidden, true);
        // No reabre SSR automáticamente
        assert.equal(h.ssr.hidden, true);

        const remounted = h.mountApi.mount();
        assert.equal(remounted, true);
        await waitMicro();
        assert.equal(h.ssr.hidden, true);
        assert.equal(h.live.hidden, false);
    });

    it('fuente mount: sin HTTP, sin client_id, sin openCreate del renderer', () => {
        assert.doesNotMatch(mountSrc, /AA_CLIENTS_DATA/);
        assert.doesNotMatch(mountSrc, /AA_CLIENTS_NONCES/);
        assert.doesNotMatch(mountSrc, /client_id/);
        assert.doesNotMatch(mountSrc, /clientId/);
        assert.doesNotMatch(mountSrc, /ExpedienteRegistros\.openCreate/);
        assert.doesNotMatch(mountSrc, /onCreateComplete/);
        assert.match(mountSrc, /onInitialLoad/);
        assert.match(mountSrc, /\.build\(/);
    });
});
