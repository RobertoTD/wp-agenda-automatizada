'use strict';

/**
 * D1 — navegación legacy create → detalle canónico.
 * Ejecutar: node --test tests/js/clientsExpedienteCreateNavD1.test.js
 */

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const rootDir = path.join(__dirname, '../..');
const clientsModuleSrc = fs.readFileSync(
    path.join(rootDir, 'includes/admin/ui/modules/clients/clients-module.js'),
    'utf8'
);
const clientsIndexSrc = fs.readFileSync(
    path.join(rootDir, 'includes/admin/ui/modules/clients/index.php'),
    'utf8'
);
const mountSrc = fs.readFileSync(
    path.join(rootDir, 'includes/admin/ui/modules/expedientes/expediente-registros-canonical-mount.js'),
    'utf8'
);

const BASE =
    'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes&view=detail';

function createEl(tag) {
    const el = {
        tagName: String(tag).toUpperCase(),
        className: '',
        id: '',
        children: [],
        attributes: Object.create(null),
        parentNode: null,
        classList: {
            _set: new Set(),
            add(c) { this._set.add(c); },
            remove(c) { this._set.delete(c); },
            contains(c) { return this._set.has(c); }
        },
        setAttribute(n, v) { el.attributes[n] = String(v); },
        getAttribute(n) {
            return Object.prototype.hasOwnProperty.call(el.attributes, n)
                ? el.attributes[n]
                : null;
        },
        appendChild(c) {
            c.parentNode = el;
            el.children.push(c);
            return c;
        },
        addEventListener() {},
        removeEventListener() {},
        focus() {}
    };
    return el;
}

function loadClientsModule(opts) {
    opts = opts || {};
    const replaces = [];
    const assigns = [];
    let createResponse = opts.createResponse || {
        success: true,
        data: {
            record: {
                id: 77,
                title: 'T',
                body: 'B',
                recorded_at: '2026-08-20 12:00:00'
            },
            expediente_id: 5
        }
    };

    const initCaptures = [];

    const windowObj = {
        AAAdmin: {
            ExpedienteRegistros: {
                init(options) {
                    initCaptures.push(options);
                },
                destroy() {}
            }
        },
        AA_CLIENTS_DATA: {
            view: 'expediente',
            clientId: 42,
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            detailCanonicalBaseUrl: opts.baseUrl !== undefined ? opts.baseUrl : BASE,
            actions: {
                listRegistros: 'aa_list_expediente_registros',
                createRegistro: 'aa_create_expediente_registro',
                updateRegistro: 'aa_update_expediente_registro',
                deleteRegistro: 'aa_delete_expediente_registro',
                attachRegistro: 'aa_attach_expediente_registro',
                signAdjuntoRead: 'aa_sign_expediente_adjunto_read',
                deleteAdjunto: 'aa_delete_expediente_adjunto'
            }
        },
        AA_CLIENTS_NONCES: { expediente_registros: 'nonce-x' },
        location: {
            href: 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=clients&view=expediente&client_id=42',
            replace(url) { replaces.push(String(url)); },
            assign(url) { assigns.push(String(url)); }
        },
        addEventListener() {},
        removeEventListener() {},
        setTimeout: (fn) => fn(),
        console: { error() {}, log() {}, warn() {} }
    };
    windowObj.window = windowObj;

    const sandbox = {
        window: windowObj,
        document: {
            readyState: 'complete',
            createElement: createEl,
            getElementById: () => null,
            querySelectorAll: () => [],
            addEventListener() {},
            removeEventListener() {}
        },
        console: windowObj.console,
        fetch: function (url, options) {
            const action = options && options.body && options.body.entries
                ? (options.body.entries.find((e) => e[0] === 'action') || [])[1]
                : null;
            if (action === 'aa_create_expediente_registro') {
                return Promise.resolve({
                    status: 200,
                    json: async () => createResponse
                });
            }
            return Promise.resolve({
                status: 200,
                json: async () => ({ success: true, data: { records: [] } })
            });
        },
        FormData: class {
            constructor() { this.entries = []; }
            append(k, v) { this.entries.push([k, v]); }
        },
        URL,
        Number,
        Math,
        Object,
        JSON,
        String,
        Array,
        Promise,
        parseInt,
        setTimeout: (fn) => fn()
    };

    vm.runInNewContext(clientsModuleSrc, sandbox);
    const api = sandbox.window.AAAdmin.ClientsModule.__test__;

    return {
        api,
        replaces,
        assigns,
        initCaptures,
        windowObj,
        setCreateResponse(r) { createResponse = r; },
        mount() {
            const root = createEl('div');
            api.mountExpedienteRegistros(42, root, null);
            assert.ok(initCaptures.length >= 1);
            return initCaptures[initCaptures.length - 1];
        }
    };
}

describe('Clients expediente create nav (D1)', () => {
    it('PHP emite detailCanonicalBaseUrl sin expediente_id/client_id/records_page', () => {
        assert.match(clientsIndexSrc, /detailCanonicalBaseUrl/);
        assert.match(clientsIndexSrc, /\$aa_clients_detail_canonical_base_url/);
        assert.match(
            clientsIndexSrc,
            /'module'\s*=>\s*'expedientes'[\s\S]*?'view'\s*=>\s*'detail'/
        );
        // La base no incluye expediente_id= en el add_query_arg de la base canónica.
        assert.match(
            clientsIndexSrc,
            /\$aa_clients_detail_canonical_base_url\s*=\s*add_query_arg\(\s*\[[\s\S]*?'action'\s*=>\s*'aa_iframe_content'[\s\S]*?'module'\s*=>\s*'expedientes'[\s\S]*?'view'\s*=>\s*'detail'[\s\S]*?\]/
        );
        assert.doesNotMatch(
            clientsIndexSrc,
            /\$aa_clients_detail_canonical_base_url\s*=\s*add_query_arg\(\s*\[[^\]]*expediente_id/
        );
    });

    it('fuente: replace una vez; sin assign/parent/top', () => {
        assert.match(clientsModuleSrc, /location\.replace/);
        assert.doesNotMatch(clientsModuleSrc, /location\.assign/);
        assert.doesNotMatch(clientsModuleSrc, /parent\.location/);
        assert.doesNotMatch(clientsModuleSrc, /top\.location/);
        assert.match(mountSrc, /location\.assign/);
        assert.match(mountSrc, /onCreateComplete/);
    });

    it('port create conserva envelope y captura record→expediente', async () => {
        const h = loadClientsModule();
        const initOpts = h.mount();
        const envelope = {
            httpStatus: 200,
            result: {
                success: true,
                data: {
                    record: { id: 77, title: 'T', body: 'B' },
                    expediente_id: 5
                }
            }
        };
        h.setCreateResponse(envelope.result);
        const out = await initOpts.ports.create({ title: 'T', body: 'B' });
        assert.deepEqual(out.result, envelope.result);
        assert.equal(out.httpStatus, 200);
        const session = h.api.getCreateNavSession();
        assert.equal(session.recordToExpediente[77], 5);
    });

    it('create fallido no registra relación', async () => {
        const h = loadClientsModule();
        const initOpts = h.mount();
        h.setCreateResponse({
            success: false,
            data: { message: 'fail', code: 'persistence_failed' }
        });
        await initOpts.ports.create({ title: 'T', body: 'B' });
        const session = h.api.getCreateNavSession();
        assert.equal(Object.keys(session.recordToExpediente).length, 0);
    });

    it('expediente_id ausente no navega', async () => {
        const h = loadClientsModule();
        const initOpts = h.mount();
        h.setCreateResponse({
            success: true,
            data: { record: { id: 77, title: 'T', body: 'B' } }
        });
        await initOpts.ports.create({ title: 'T', body: 'B' });
        initOpts.onCreateComplete({ recordId: 77, imageOutcome: 'none' });
        assert.equal(h.replaces.length, 0);
    });

    it('IDs inválidos no capturan ni navegan', async () => {
        const h = loadClientsModule();
        const initOpts = h.mount();
        const bad = [0, -1, 1.5, NaN, Infinity, '5', null, undefined, true];
        for (const id of bad) {
            h.setCreateResponse({
                success: true,
                data: {
                    record: { id: typeof id === 'number' && id > 0 && Number.isInteger(id) ? id : 99, title: 'T', body: 'B' },
                    expediente_id: id
                }
            });
            // force both invalid paths
            h.setCreateResponse({
                success: true,
                data: {
                    record: { id: 99, title: 'T', body: 'B' },
                    expediente_id: id
                }
            });
            await initOpts.ports.create({ title: 'T', body: 'B' });
        }
        assert.equal(Object.keys(h.api.getCreateNavSession().recordToExpediente).length, 0);

        h.setCreateResponse({
            success: true,
            data: { record: { id: 10, title: 'T', body: 'B' }, expediente_id: 5 }
        });
        await initOpts.ports.create({ title: 'T', body: 'B' });
        for (const rid of bad) {
            initOpts.onCreateComplete({ recordId: rid, imageOutcome: 'none' });
        }
        assert.equal(h.replaces.length, 0);
        // valid still present
        assert.equal(h.api.getCreateNavSession().recordToExpediente[10], 5);
    });

    it('none/saved/failed/abandoned navegan una vez con URL correcta', async () => {
        for (const outcome of ['none', 'saved', 'failed', 'abandoned']) {
            const h = loadClientsModule();
            const initOpts = h.mount();
            await initOpts.ports.create({ title: 'T', body: 'B' });
            initOpts.onCreateComplete({ recordId: 77, imageOutcome: outcome });
            assert.equal(h.replaces.length, 1, outcome);
            assert.equal(h.assigns.length, 0);
            const url = new URL(h.replaces[0]);
            assert.equal(url.searchParams.get('action'), 'aa_iframe_content');
            assert.equal(url.searchParams.get('module'), 'expedientes');
            assert.equal(url.searchParams.get('view'), 'detail');
            assert.equal(url.searchParams.get('expediente_id'), '5');
            assert.equal(url.searchParams.get('client_id'), null);
            assert.equal(url.searchParams.get('records_page'), null);
        }
    });

    it('callback repetido no duplica; entrada eliminada antes de replace', async () => {
        const h = loadClientsModule();
        const initOpts = h.mount();
        await initOpts.ports.create({ title: 'T', body: 'B' });
        assert.equal(h.api.getCreateNavSession().recordToExpediente[77], 5);
        initOpts.onCreateComplete({ recordId: 77, imageOutcome: 'none' });
        assert.equal(h.api.getCreateNavSession().recordToExpediente[77], undefined);
        initOpts.onCreateComplete({ recordId: 77, imageOutcome: 'none' });
        assert.equal(h.replaces.length, 1);
    });

    it('recordId distinto no consume expediente ajeno', async () => {
        const h = loadClientsModule();
        const initOpts = h.mount();
        await initOpts.ports.create({ title: 'T', body: 'B' });
        initOpts.onCreateComplete({ recordId: 999, imageOutcome: 'none' });
        assert.equal(h.replaces.length, 0);
        assert.equal(h.api.getCreateNavSession().recordToExpediente[77], 5);
    });

    it('base URL inválida conserva legacy (no navega)', async () => {
        const h = loadClientsModule({ baseUrl: '' });
        const initOpts = h.mount();
        await initOpts.ports.create({ title: 'T', body: 'B' });
        initOpts.onCreateComplete({ recordId: 77, imageOutcome: 'none' });
        assert.equal(h.replaces.length, 0);

        const h2 = loadClientsModule({
            baseUrl: 'https://evil.test/phish?action=aa_iframe_content&module=hack&view=detail'
        });
        const opts2 = h2.mount();
        await opts2.ports.create({ title: 'T', body: 'B' });
        opts2.onCreateComplete({ recordId: 77, imageOutcome: 'none' });
        assert.equal(h2.replaces.length, 0);
    });

    it('destroy/invalidate impide navegación tardía y limpia relaciones', async () => {
        const h = loadClientsModule();
        const initOpts = h.mount();
        await initOpts.ports.create({ title: 'T', body: 'B' });
        h.api.invalidateExpedienteCreateNavSession();
        initOpts.onCreateComplete({ recordId: 77, imageOutcome: 'saved' });
        assert.equal(h.replaces.length, 0);
        assert.equal(h.api.getCreateNavSession(), null);
    });

    it('reinicialización no reutiliza estado anterior', async () => {
        const h = loadClientsModule();
        const first = h.mount();
        await first.ports.create({ title: 'T', body: 'B' });
        assert.equal(h.api.getCreateNavSession().recordToExpediente[77], 5);
        const second = h.mount();
        assert.equal(h.api.getCreateNavSession().recordToExpediente[77], undefined);
        first.onCreateComplete({ recordId: 77, imageOutcome: 'none' });
        assert.equal(h.replaces.length, 0);
        h.setCreateResponse({
            success: true,
            data: {
                record: { id: 88, title: 'T', body: 'B' },
                expediente_id: 9
            }
        });
        await second.ports.create({ title: 'T', body: 'B' });
        second.onCreateComplete({ recordId: 88, imageOutcome: 'none' });
        assert.equal(h.replaces.length, 1);
        assert.match(h.replaces[0], /expediente_id=9/);
    });

    it('isStrictPositiveInt rechaza no enteros', () => {
        const h = loadClientsModule();
        assert.equal(h.api.isStrictPositiveInt(1), true);
        assert.equal(h.api.isStrictPositiveInt(0), false);
        assert.equal(h.api.isStrictPositiveInt(-3), false);
        assert.equal(h.api.isStrictPositiveInt(2.2), false);
        assert.equal(h.api.isStrictPositiveInt(NaN), false);
        assert.equal(h.api.isStrictPositiveInt('1'), false);
    });
});
