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
        innerHTML: '',
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
        dispatch(type, event) {
            ((el._listeners && el._listeners[type]) || []).forEach((fn) => fn(event || {
                preventDefault() {},
                stopPropagation() {}
            }));
        },
        focus() { el._focused = true; },
        closest(selector) {
            let node = el;
            while (node) {
                if (matches(node, selector)) {
                    return node;
                }
                node = node.parentNode;
            }
            return null;
        },
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
const DTO_D = { id: 7, width: 700, height: 700, byte_size: 20000, created_at: '2026-07-31 09:30:00' };

function baseRecord(overrides) {
    const record = Object.assign({
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
    if (record.adjuntos.length && !record.adjunto) {
        record.adjunto = record.adjuntos[0];
    }
    return record;
}

function ids(list) {
    return Array.from(list || [], (d) => d.id);
}

function makeSandbox(options) {
    options = options || {};
    const fetchCalls = [];
    let fetchImpl = () => Promise.resolve({
        status: 200,
        json: async () => ({ success: false })
    });
    let confirmImpl = options.confirm !== undefined ? options.confirm : () => true;

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
                    deleteAdjunto: 'aa_delete_expediente_adjunto',
                    listRegistros: 'aa_list_expediente_registros'
                }
            },
            AA_CLIENTS_NONCES: { expediente_registros: 'nonce-1' },
            setTimeout: (fn) => fn(),
            confirm: function (msg) { return confirmImpl(msg); }
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
        setFetch(fn) { fetchImpl = fn; },
        setConfirm(fn) { confirmImpl = fn; }
    };
}

function mount(ctx, records) {
    const root = createEl('div');
    ctx.api.setState({ clientId: 7, recordsRoot: root, records: records, loading: false });
    return root;
}

function fieldsOf(call) {
    return Object.fromEntries(call.opts.body.entries);
}

function settle() {
    return new Promise((resolve) => setImmediate(resolve));
}

describe('expediente MC5c1 — eliminación individual de imagen', () => {
    it('papelera usa el adjunto seleccionado y es hermana del main', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })]);
        ctx.api.renderRecordsList();

        const gallery = findFirst(root, '.aa-expediente-galeria');
        const wrap = findFirst(gallery, '.aa-expediente-galeria-main-wrap');
        const main = findFirst(wrap, '.aa-expediente-galeria-main');
        const del = findFirst(wrap, '.aa-expediente-galeria-delete');
        assert.ok(wrap && main && del);
        assert.equal(main.parentNode, wrap);
        assert.equal(del.parentNode, wrap);
        assert.equal(del.getAttribute('aria-label'), 'Eliminar imagen');
        assert.equal(del.getAttribute('title'), 'Eliminar imagen');
        assert.equal(del.getAttribute('data-adjunto-id'), '12');
        assert.equal(main.getAttribute('data-adjunto-id'), '12');

        const minis = [];
        collect(gallery, '.aa-expediente-galeria-mini', minis);
        minis[1].dispatch('click');
        assert.equal(del.getAttribute('data-adjunto-id'), '9', 'papelera sigue la selección');
        assert.equal(main.getAttribute('data-adjunto-id'), '9');
    });

    it('cancelar confirmación no llama Ajax ni muta estado', () => {
        const ctx = makeSandbox({ confirm: () => false });
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B] })]);
        ctx.api.renderRecordsList();
        findFirst(root, '.aa-expediente-galeria-delete').dispatch('click');
        assert.equal(ctx.fetchCalls.length, 0);
        assert.deepEqual(ids(ctx.api.getState().records[0].adjuntos), [12, 9]);
    });

    it('doble envío bloqueado mientras la petición está en vuelo', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B] })]);
        ctx.api.renderRecordsList();
        let resolveLate;
        ctx.setFetch(() => new Promise((resolve) => { resolveLate = resolve; }));

        const del = findFirst(root, '.aa-expediente-galeria-delete');
        del.dispatch('click');
        del.dispatch('click');
        assert.equal(ctx.fetchCalls.length, 1, 'una sola petición');
        assert.equal(del.disabled, true);

        resolveLate({
            status: 200,
            json: async () => ({
                success: true,
                data: {
                    record_id: 1,
                    deleted_attachment_id: 12,
                    adjuntos: [DTO_B],
                    adjunto: DTO_B
                }
            })
        });
        await settle();
        assert.deepEqual(ids(ctx.api.getState().records[0].adjuntos), [9]);
    });

    it('selección: intermedia → siguiente; última → anterior; única → ninguna', () => {
        const ctx = makeSandbox();
        assert.equal(
            ctx.api.pickSelectionAfterDelete([DTO_C, DTO_D, DTO_B, DTO_A], 9, [DTO_C, DTO_D, DTO_A]),
            5,
            '[12,7,9,5]−9 → selecciona 5 (misma posición)'
        );
        // Ejemplo canónico [7,6,5,4] seleccionada 6 → [7,5,4] selecciona 5
        const before = [{ id: 7 }, { id: 6 }, { id: 5 }, { id: 4 }];
        const afterMid = [{ id: 7 }, { id: 5 }, { id: 4 }];
        assert.equal(ctx.api.pickSelectionAfterDelete(before, 6, afterMid), 5);

        const afterLast = [{ id: 7 }, { id: 6 }];
        assert.equal(ctx.api.pickSelectionAfterDelete([{ id: 7 }, { id: 6 }, { id: 5 }], 5, afterLast), 6);

        assert.equal(ctx.api.pickSelectionAfterDelete([{ id: 7 }], 7, []), 0);
    });

    it('éxito sustituye adjuntos, recalcula contador/alias/summary y selección', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })]);
        ctx.api.renderRecordsList();
        const minis = [];
        collect(root, '.aa-expediente-galeria-mini', minis);
        minis[1].dispatch('click'); // selecciona 9

        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({
                success: true,
                data: {
                    record_id: 1,
                    deleted_attachment_id: 9,
                    adjuntos: [DTO_C, DTO_A],
                    adjunto: DTO_C
                }
            })
        }));
        findFirst(root, '.aa-expediente-galeria-delete').dispatch('click');
        await settle();

        const record = ctx.api.getState().records[0];
        assert.deepEqual(ids(record.adjuntos), [12, 5]);
        assert.equal(record.adjunto.id, 12, 'alias = adjuntos[0]');
        assert.equal(ctx.api.getThumbs().selectedByRecord[1], 5, 'posición i → 5');

        const main = findFirst(root, '.aa-expediente-galeria-main');
        assert.equal(main.getAttribute('data-adjunto-id'), '5');
        assert.equal(findFirst(root, '.aa-expediente-galeria-counter').textContent, '2 de 2');
        assert.equal(
            findFirst(root, '.aa-expediente-adjunto-thumb').getAttribute('data-adjunto-id'),
            '12',
            'summary sigue siendo adjuntos[0]'
        );

        const deleteCall = ctx.fetchCalls.find((c) => fieldsOf(c).action === 'aa_delete_expediente_adjunto');
        assert.ok(deleteCall, 'petición de delete presente');
        const fields = fieldsOf(deleteCall);
        assert.equal(fields.attachment_id, '9');
        assert.equal(fields.record_id, '1');
        assert.equal(fields.client_id, '7');
        assert.ok(!Object.prototype.hasOwnProperty.call(fields, 'storage_path'));
    });

    it('eliminar la única retira galería y summary', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B] })]);
        ctx.api.renderRecordsList();
        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({
                success: true,
                data: { record_id: 1, deleted_attachment_id: 9, adjuntos: [], adjunto: null }
            })
        }));
        findFirst(root, '.aa-expediente-galeria-delete').dispatch('click');
        await settle();

        assert.equal(findFirst(root, '.aa-expediente-galeria'), null);
        assert.equal(findFirst(root, '.aa-expediente-adjunto-thumb'), null);
        assert.equal(ctx.api.getThumbs().selectedByRecord[1], undefined);
        assert.equal(ctx.api.getState().records[0].adjunto, null);
    });

    it('error conserva estado y permite reintentar', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B] })]);
        ctx.api.renderRecordsList();
        ctx.setFetch(() => Promise.resolve({
            status: 502,
            json: async () => ({ success: false, data: { code: 'storage_delete_failed', message: 'x' } })
        }));
        const del = findFirst(root, '.aa-expediente-galeria-delete');
        del.dispatch('click');
        await settle();

        assert.deepEqual(ids(ctx.api.getState().records[0].adjuntos), [12, 9]);
        assert.equal(del.disabled, false, 'control re-habilitado');
        assert.ok(findFirst(root, '.aa-expediente-galeria-error'));

        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({
                success: true,
                data: { record_id: 1, deleted_attachment_id: 12, adjuntos: [DTO_B], adjunto: DTO_B }
            })
        }));
        del.dispatch('click');
        await settle();
        assert.deepEqual(ids(ctx.api.getState().records[0].adjuntos), [9]);
    });

    it('respuesta tardía tras destroy no afecta otra vista', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B] })]);
        ctx.api.renderRecordsList();
        let resolveLate;
        ctx.setFetch(() => new Promise((resolve) => { resolveLate = resolve; }));
        findFirst(root, '.aa-expediente-galeria-delete').dispatch('click');

        ctx.api.destroy();
        const rootB = createEl('div');
        ctx.api.setState({
            clientId: 8,
            recordsRoot: rootB,
            records: [baseRecord({ id: 50, client_id: 8, adjuntos: [DTO_A] })],
            loading: false
        });

        resolveLate({
            status: 200,
            json: async () => ({
                success: true,
                data: { record_id: 1, deleted_attachment_id: 12, adjuntos: [DTO_B], adjunto: DTO_B }
            })
        });
        await settle();

        assert.equal(ctx.api.getState().clientId, 8);
        assert.equal(ctx.api.getState().records[0].id, 50);
        assert.deepEqual(ids(ctx.api.getState().records[0].adjuntos), [5]);
        assert.deepEqual(rootB.children, []);
    });
});
