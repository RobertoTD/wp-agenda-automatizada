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
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

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
        addEventListener(type, handler) {
            el._listeners = el._listeners || {};
            el._listeners[type] = el._listeners[type] || [];
            el._listeners[type].push(handler);
        },
        focus() { el._focused = true; },
        querySelector(selector) { return findFirst(el, selector); },
        querySelectorAll(selector) {
            const out = [];
            collect(el, selector, out);
            return out;
        }
    };
    Object.defineProperty(el, 'firstChild', {
        get() { return el.children[0] || null; }
    });
    return el;
}

function matches(el, selector) {
    if (!el || !el.tagName) {
        return false;
    }
    if (selector.startsWith('.')) {
        const cls = selector.slice(1);
        return String(el.className).split(/\s+/).includes(cls) || el.classList.contains(cls);
    }
    return el.tagName === selector.toUpperCase();
}

function collect(root, selector, out) {
    (root.children || []).forEach((child) => {
        if (matches(child, selector)) {
            out.push(child);
        }
        collect(child, selector, out);
    });
}

function findFirst(root, selector) {
    const out = [];
    collect(root, selector, out);
    return out[0] || null;
}

const DTO_A = { id: 5, width: 800, height: 600, byte_size: 40000, created_at: '2026-07-31 08:00:00' };
const DTO_B = { id: 9, width: 1024, height: 768, byte_size: 65000, created_at: '2026-07-31 09:00:00' };
const DTO_C = { id: 12, width: 640, height: 480, byte_size: 30000, created_at: '2026-07-31 10:00:00' };

function baseRecord(overrides) {
    return Object.assign({
        id: 1,
        client_id: 7,
        title: 'Registro',
        body: 'Texto',
        recorded_at: '2026-07-31 08:00:00',
        created_at: '2026-07-31 08:00:00',
        updated_at: null,
        adjuntos: [],
        adjunto: null
    }, overrides || {});
}

function makeSandbox() {
    const fetchCalls = [];
    let fetchImpl = () => Promise.resolve({
        status: 200,
        json: async () => ({ success: false })
    });

    class FakeFormData {
        constructor() { this.entries = []; }
        append(k, v) { this.entries.push([k, v]); }
    }

    const sandbox = {
        window: {
            AAAdmin: {},
            AA_CLIENTS_DATA: {
                ajaxUrl: '/admin-ajax.php',
                actions: {
                    signAdjuntoRead: 'aa_sign_expediente_adjunto_read',
                    listRegistros: 'aa_list_expediente_registros'
                }
            },
            AA_CLIENTS_NONCES: { expediente_registros: 'nonce-1' },
            setTimeout: (fn) => fn()
        },
        document: {
            createElement: createEl,
            getElementById: () => null,
            querySelector: () => null,
            contains: () => true
        },
        console: { error: () => {}, log: () => {} },
        fetch: function (url, opts) {
            fetchCalls.push({ url, opts });
            return fetchImpl(url, opts, fetchCalls.length);
        },
        FormData: FakeFormData,
        AbortController: AbortController,
        URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
        Image: function () {},
        setTimeout: (fn) => fn(),
        Date: Date,
        Object: Object,
        JSON: JSON,
        Math: Math,
        parseInt: parseInt,
        crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
    };

    vm.runInNewContext(moduleSrc, sandbox);
    const api = sandbox.window.AAAdmin.ExpedienteRegistros.__test__;

    return {
        sandbox,
        api,
        fetchCalls,
        setFetch(fn) { fetchImpl = fn; }
    };
}

function mount(ctx, records) {
    const root = createEl('div');
    ctx.api.setState({ clientId: 7, recordsRoot: root, records: records, loading: false });
    return root;
}

// Los arrays creados dentro del sandbox vm tienen otro prototipo; Array.from
// en el realm del test evita falsos negativos de deepStrictEqual.
function ids(list) {
    return Array.from(list || [], (d) => d.id);
}

describe('expediente adjuntos MC5a — adjuntos[] y lectura dirigida', () => {
    it('normalizeAdjuntosList filtra inválidos, deduplica y ordena id DESC', () => {
        const ctx = makeSandbox();
        const dup = { id: 5, width: 1, height: 1, byte_size: 1, created_at: 'x' };
        const invalid = { id: 0, width: 0, height: 0, byte_size: 0, created_at: null };
        const out = ctx.api.normalizeAdjuntosList([DTO_A, DTO_C, dup, invalid, DTO_B, null]);
        assert.deepEqual(ids(out), [12, 9, 5]);
        assert.equal(out[2], DTO_A, 'primera aparición gana en el dedupe');
    });

    it('normalizeIncomingRecord: adjuntos autoritativo, puente adjunto, sin claves → []', () => {
        const ctx = makeSandbox();

        const conLista = ctx.api.normalizeIncomingRecord(
            { id: 1, adjuntos: [DTO_A, DTO_B], adjunto: null }
        );
        assert.deepEqual(ids(conLista.adjuntos), [9, 5]);
        assert.equal(conLista.adjunto.id, 9, 'alias derivado de adjuntos[0]');

        const vacia = ctx.api.normalizeIncomingRecord({ id: 2, adjuntos: [] });
        assert.equal(vacia.adjuntos.length, 0);
        assert.equal(vacia.adjunto, null);

        const puente = ctx.api.normalizeIncomingRecord({ id: 3, adjunto: DTO_A });
        assert.deepEqual(ids(puente.adjuntos), [5]);
        assert.equal(puente.adjunto.id, 5);

        const sinClaves = ctx.api.normalizeIncomingRecord({ id: 4 });
        assert.equal(sinClaves.adjuntos.length, 0);
        assert.equal(sinClaves.adjunto, null);
    });

    it('editar solo texto conserva la colección completa y el alias', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjuntos: [DTO_B, DTO_A], adjunto: DTO_B })]);

        ctx.api.replaceRecord({ id: 1, title: 'Editado', body: 'Nuevo' });

        const record = ctx.api.getState().records[0];
        assert.deepEqual(ids(record.adjuntos), [9, 5]);
        assert.equal(record.adjunto.id, 9);
        assert.equal(record.title, 'Editado');
    });

    it('clave adjuntos presente (incluso []) es autoritativa', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B, DTO_A], adjunto: DTO_B })]);

        ctx.api.replaceRecord({ id: 1, title: 'T', body: 'B', adjuntos: [] });
        const record = ctx.api.getState().records[0];
        assert.equal(record.adjuntos.length, 0);
        assert.equal(record.adjunto, null);
        assert.equal(findFirst(root, '.aa-expediente-adjunto-thumb'), null, 'miniatura retirada');

        ctx.api.replaceRecord({ id: 1, title: 'T', body: 'B', adjuntos: [DTO_A, DTO_C] });
        assert.deepEqual(ids(ctx.api.getState().records[0].adjuntos), [12, 5]);
    });

    it('attach agrega la nueva imagen al inicio sin duplicar y deriva el alias', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjuntos: [DTO_A], adjunto: DTO_A })]);

        ctx.api.applyAdjuntoToRecord(1, DTO_B);
        let record = ctx.api.getState().records[0];
        assert.deepEqual(ids(record.adjuntos), [9, 5], 'nueva imagen al frente');
        assert.equal(record.adjunto.id, 9);

        // Reaplicar el mismo DTO (reintento) sustituye por id, no duplica.
        ctx.api.applyAdjuntoToRecord(1, { id: 9, width: 2048, height: 1536, byte_size: 99000, created_at: 'z' });
        record = ctx.api.getState().records[0];
        assert.deepEqual(ids(record.adjuntos), [9, 5]);
        assert.equal(record.adjuntos[0].width, 2048, 'DTO sustituido por id');
        assert.equal(ctx.api.getState().records.length, 1);
    });

    it('la miniatura de summary usa adjuntos[0]', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A], adjunto: DTO_C })]);
        ctx.api.renderRecordsList();

        const boxes = [];
        collect(root, '.aa-expediente-adjunto-thumb', boxes);
        assert.equal(boxes.length, 1, 'una sola miniatura en MC5a');
        assert.equal(boxes[0].getAttribute('data-adjunto-id'), '12');
    });

    it('sign-read incluye attachment_id de la identidad solicitada', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B, DTO_A], adjunto: DTO_B })]);
        ctx.api.renderRecordsList();
        const box = findFirst(root, '.aa-expediente-adjunto-thumb');

        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({
                success: true,
                data: {
                    url: 'https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/x.jpg?token=t',
                    expires_in: 600,
                    variant: 'summary'
                }
            })
        }));
        ctx.api.requestThumbFor(box, 1);
        await new Promise((resolve) => setImmediate(resolve));

        assert.equal(ctx.fetchCalls.length, 1);
        const entries = ctx.fetchCalls[0].opts.body.entries;
        const asMap = Object.fromEntries(entries);
        assert.equal(asMap.attachment_id, '9');
        assert.equal(asMap.record_id, '1');
        assert.equal(asMap.client_id, '7');
        assert.equal(asMap.variant, 'summary');
    });

    it('respuesta tardía de loadRecords tras destroy/cambio de cliente no sobrescribe', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, []);

        let resolveLate;
        ctx.setFetch(() => new Promise((resolve) => { resolveLate = resolve; }));
        ctx.api.loadRecords();
        assert.equal(ctx.fetchCalls.length, 1);

        // Cambio de vista: destroy + montaje de otro cliente con estado propio.
        ctx.api.destroy();
        const rootB = createEl('div');
        const recordB = baseRecord({ id: 50, client_id: 8, title: 'Cliente 8' });
        ctx.api.setState({ clientId: 8, recordsRoot: rootB, records: [recordB], loading: false });

        resolveLate({
            status: 200,
            json: async () => ({
                success: true,
                data: { records: [baseRecord({ id: 99, title: 'Tardío', adjuntos: [DTO_A] })] }
            })
        });
        await new Promise((resolve) => setImmediate(resolve));

        const state = ctx.api.getState();
        assert.equal(state.clientId, 8);
        assert.equal(state.records.length, 1);
        assert.equal(state.records[0].id, 50, 'estado vigente intacto');
        assert.deepEqual(rootB.children, [], 'raíz del cliente activo no tocada por la respuesta tardía');
    });

    it('caché y poda consideran todas las identidades de adjuntos[]', () => {
        const ctx = makeSandbox();
        mount(ctx, [baseRecord({ adjuntos: [DTO_B, DTO_A], adjunto: DTO_B })]);
        const thumbs = ctx.api.getThumbs();
        const keyPrimero = ctx.api.thumbKey(1, 9, 'summary');
        const keySegundo = ctx.api.thumbKey(1, 5, 'gallery');
        const keyAjena = ctx.api.thumbKey(1, 777, 'summary');
        thumbs.thumbnailCache[keyPrimero] = { url: 'https://x/9', deadlineMs: Date.now() + 60000 };
        thumbs.thumbnailCache[keySegundo] = { url: 'https://x/5', deadlineMs: Date.now() + 60000 };
        thumbs.thumbnailCache[keyAjena] = { url: 'https://x/777', deadlineMs: Date.now() + 60000 };

        ctx.api.renderRecordsList();

        assert.ok(thumbs.thumbnailCache[keyPrimero], 'identidad visible conservada');
        assert.ok(thumbs.thumbnailCache[keySegundo], 'identidad no visible pero vigente conservada');
        assert.equal(thumbs.thumbnailCache[keyAjena], undefined, 'identidad inexistente podada');
    });

    it('listado normaliza y ordena aunque el servidor envíe desorden', async () => {
        const ctx = makeSandbox();
        mount(ctx, []);

        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({
                success: true,
                data: {
                    records: [baseRecord({
                        id: 1,
                        adjuntos: [DTO_A, DTO_C, DTO_A, DTO_B],
                        adjunto: DTO_A
                    })]
                }
            })
        }));
        ctx.api.loadRecords();
        await new Promise((resolve) => setImmediate(resolve));

        const record = ctx.api.getState().records[0];
        assert.deepEqual(ids(record.adjuntos), [12, 9, 5]);
        assert.equal(record.adjunto.id, 12, 'alias rederivado, no el enviado');
    });
});
