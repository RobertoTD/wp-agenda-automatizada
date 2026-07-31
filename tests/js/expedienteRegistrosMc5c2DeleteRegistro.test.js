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
        insertBefore(child, reference) {
            child.parentNode = el;
            if (!reference) {
                el.children.push(child);
                return child;
            }
            const idx = el.children.indexOf(reference);
            if (idx === -1) {
                el.children.push(child);
            } else {
                el.children.splice(idx, 0, child);
            }
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

function makeSandbox(options) {
    options = options || {};
    const fetchCalls = [];
    let fetchImpl = () => Promise.resolve({
        status: 200,
        json: async () => ({ success: false })
    });
    let confirmImpl = options.confirm !== undefined ? options.confirm : () => true;
    let modalOpen = options.modalOpen === true;
    const confirmMessages = [];

    class FakeFormData {
        constructor() { this.entries = []; }
        append(k, v) { this.entries.push([k, v]); }
    }

    const sandbox = {
        window: {
            AAAdmin: {
                modal: {
                    isOpen() { return modalOpen; }
                }
            },
            AA_CLIENTS_DATA: {
                ajaxUrl: '/admin-ajax.php',
                actions: {
                    signAdjuntoRead: 'aa_sign_expediente_adjunto_read',
                    deleteAdjunto: 'aa_delete_expediente_adjunto',
                    deleteRegistro: 'aa_delete_expediente_registro',
                    listRegistros: 'aa_list_expediente_registros'
                }
            },
            AA_CLIENTS_NONCES: { expediente_registros: 'nonce-1' },
            setTimeout: (fn) => fn(),
            confirm: function (msg) {
                confirmMessages.push(msg);
                return confirmImpl(msg);
            }
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
        confirmMessages,
        setFetch(fn) { fetchImpl = fn; },
        setConfirm(fn) { confirmImpl = fn; },
        setModalOpen(v) { modalOpen = !!v; }
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

function findDeleteRecordBtn(root) {
    const buttons = [];
    collect(root, 'BUTTON', buttons);
    return buttons.find((b) => b.className.includes('aa-expediente-btn-eliminar')
        && b.textContent === 'Eliminar') || null;
}

describe('expediente MC5c2 — eliminación de registro completo', () => {
    it('copy confirm: 0, 1 y N imágenes', () => {
        const ctx = makeSandbox();
        assert.equal(
            ctx.api.deleteRecordConfirmMessage(baseRecord({ adjuntos: [] })),
            '¿Eliminar este registro? Esta acción no se puede deshacer.'
        );
        assert.equal(
            ctx.api.deleteRecordConfirmMessage(baseRecord({ adjuntos: [DTO_A] })),
            '¿Eliminar este registro? También se eliminarán sus 1 imágenes. Esta acción no se puede deshacer.'
        );
        assert.equal(
            ctx.api.deleteRecordConfirmMessage(baseRecord({ adjuntos: [DTO_C, DTO_B, DTO_A] })),
            '¿Eliminar este registro? También se eliminarán sus 3 imágenes. Esta acción no se puede deshacer.'
        );
        assert.equal(
            ctx.api.DELETE_RECORD_CONFIRM_EMPTY,
            '¿Eliminar este registro? Esta acción no se puede deshacer.'
        );
    });

    it('botón Eliminar junto a Editar en acciones', () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_A] })]);
        ctx.api.renderRecordsList();
        const actions = findFirst(root, '.aa-expediente-registro-actions');
        assert.ok(actions);
        const buttons = actions.children.filter((c) => c.tagName === 'BUTTON');
        assert.equal(buttons.length, 2);
        assert.equal(buttons[0].textContent, 'Editar');
        assert.equal(buttons[1].textContent, 'Eliminar');
        assert.ok(buttons[1].className.includes('aa-expediente-btn-eliminar'));
    });

    it('modal abierto bloquea confirmación y Ajax', () => {
        const ctx = makeSandbox({ modalOpen: true });
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_B] })]);
        ctx.api.renderRecordsList();
        findDeleteRecordBtn(root).dispatch('click');
        assert.equal(ctx.confirmMessages.length, 0);
        assert.equal(ctx.fetchCalls.length, 0);
        assert.equal(ctx.api.getState().records.length, 1);
    });

    it('cancelar no cambia nada', () => {
        const ctx = makeSandbox({ confirm: () => false });
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C, DTO_B] })]);
        ctx.api.renderRecordsList();
        findDeleteRecordBtn(root).dispatch('click');
        assert.equal(ctx.confirmMessages.length, 1);
        assert.match(ctx.confirmMessages[0], /2 imágenes/);
        assert.equal(ctx.fetchCalls.length, 0);
        assert.equal(ctx.api.getState().records.length, 1);
        assert.deepEqual(
            ctx.api.getState().records[0].adjuntos.map((a) => a.id),
            [12, 9]
        );
    });

    it('doble envío bloqueado mientras la petición está en vuelo', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [] })]);
        ctx.api.renderRecordsList();
        let resolveLate;
        ctx.setFetch(() => new Promise((resolve) => { resolveLate = resolve; }));

        const del = findDeleteRecordBtn(root);
        del.dispatch('click');
        del.dispatch('click');
        assert.equal(ctx.fetchCalls.length, 1);
        assert.equal(del.disabled, true);

        const fields = fieldsOf(ctx.fetchCalls[0]);
        assert.equal(fields.action, 'aa_delete_expediente_registro');
        assert.equal(fields.client_id, '7');
        assert.equal(fields.record_id, '1');
        assert.equal(fields._wpnonce, 'nonce-1');
        assert.ok(!Object.prototype.hasOwnProperty.call(fields, 'storage_path'));

        resolveLate({
            status: 200,
            json: async () => ({ success: true, data: { deleted: true, record_id: 1 } })
        });
        await settle();
        assert.equal(ctx.api.getState().records.length, 0);
    });

    it('éxito retira la tarjeta y muestra el vacío', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [
            baseRecord({ id: 1, adjuntos: [DTO_C, DTO_B] }),
            baseRecord({ id: 2, title: 'Otro', adjuntos: [] })
        ]);
        ctx.api.renderRecordsList();
        ctx.api.getThumbs().selectedByRecord[1] = 9;

        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({ success: true, data: { deleted: true, record_id: 1 } })
        }));
        findDeleteRecordBtn(root).dispatch('click');
        await settle();

        assert.equal(ctx.api.getState().records.length, 1);
        assert.equal(ctx.api.getState().records[0].id, 2);
        assert.equal(ctx.api.getThumbs().selectedByRecord[1], undefined);

        // Último registro también → vacío
        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({ success: true, data: { deleted: true, record_id: 2 } })
        }));
        findDeleteRecordBtn(root).dispatch('click');
        await settle();
        assert.equal(ctx.api.getState().records.length, 0);
        assert.ok(findFirst(root, '.aa-expediente-registros-empty'));
        assert.equal(
            findFirst(root, '.aa-expediente-registros-empty').textContent,
            'Aún no hay registros en este expediente'
        );
    });

    it('error conserva la tarjeta y permite reintentar', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_A] })]);
        ctx.api.renderRecordsList();
        ctx.setFetch(() => Promise.resolve({
            status: 502,
            json: async () => ({ success: false, data: { code: 'storage_delete_partial', message: 'x' } })
        }));
        const del = findDeleteRecordBtn(root);
        del.dispatch('click');
        await settle();

        assert.equal(ctx.api.getState().records.length, 1);
        assert.equal(del.disabled, false);
        assert.ok(findFirst(root, '.aa-expediente-registro-delete-error'));

        ctx.setFetch(() => Promise.resolve({
            status: 200,
            json: async () => ({ success: true, data: { deleted: true, record_id: 1 } })
        }));
        del.dispatch('click');
        await settle();
        assert.equal(ctx.api.getState().records.length, 0);
    });

    it('respuesta tardía tras cambiar de cliente o destroy es inocua', async () => {
        const ctx = makeSandbox();
        const root = mount(ctx, [baseRecord({ adjuntos: [DTO_C] })]);
        ctx.api.renderRecordsList();
        let resolveLate;
        ctx.setFetch(() => new Promise((resolve) => { resolveLate = resolve; }));
        findDeleteRecordBtn(root).dispatch('click');

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
            json: async () => ({ success: true, data: { deleted: true, record_id: 1 } })
        });
        await settle();

        assert.equal(ctx.api.getState().clientId, 8);
        assert.equal(ctx.api.getState().records[0].id, 50);
        assert.deepEqual(ctx.api.getState().records[0].adjuntos.map((a) => a.id), [5]);
    });
});
