'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modalPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/expedientes/expediente-registro-create-modal.js'
);
const modalSrc = fs.readFileSync(modalPath, 'utf8');
const detailPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/expedientes/detail.php'
);
const detailSrc = fs.readFileSync(detailPath, 'utf8');
const expedientesIndexPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/expedientes/index.php'
);
const expedientesIndex = fs.readFileSync(expedientesIndexPath, 'utf8');
const clientsIndexPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/index.php'
);
const clientsIndex = fs.readFileSync(clientsIndexPath, 'utf8');
const legacyRegistrosPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const legacyRegistrosSrc = fs.readFileSync(legacyRegistrosPath, 'utf8');

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(),
        id: id || '',
        children: [],
        attributes: Object.create(null),
        _text: '',
        value: '',
        disabled: false,
        parentNode: null,
        _listeners: Object.create(null),
        classList: {
            _set: new Set(),
            add: function (c) { this._set.add(c); },
            remove: function (c) { this._set.delete(c); },
            contains: function (c) { return this._set.has(c); }
        },
        setAttribute: function (name, value) {
            this.attributes[name] = String(value);
            if (name === 'id') {
                this.id = String(value);
            }
        },
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
        },
        appendChild: function (child) {
            child.parentNode = this;
            this.children.push(child);
            return child;
        },
        replaceChild: function (newChild, oldChild) {
            const idx = this.children.indexOf(oldChild);
            if (idx !== -1) {
                this.children[idx] = newChild;
                newChild.parentNode = this;
                oldChild.parentNode = null;
            }
            return oldChild;
        },
        cloneNode: function () {
            const clone = createEl(this.tagName.toLowerCase(), this.id);
            clone.className = this.className;
            clone.textContent = this.textContent;
            clone.value = this.value;
            clone.disabled = this.disabled;
            Object.keys(this.attributes).forEach((name) => {
                clone.setAttribute(name, this.attributes[name]);
            });
            return clone;
        },
        addEventListener: function (type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        dispatch: function (type, event) {
            const list = this._listeners[type] || [];
            list.forEach(function (handler) {
                handler(event || { type: type, preventDefault: function () {} });
            });
        },
        focus: function () {
            this._focused = true;
        }
    };

    if (tag === 'input' || tag === 'textarea') {
        Object.defineProperty(el, 'maxLength', {
            get: function () { return this._maxLength || -1; },
            set: function (value) { this._maxLength = Number(value); }
        });
        Object.defineProperty(el, 'required', {
            get: function () { return !!this._required; },
            set: function (value) { this._required = !!value; }
        });
    }

    Object.defineProperty(el, 'className', {
        get: function () { return Array.from(el.classList._set).join(' '); },
        set: function (value) {
            el.classList._set = new Set(String(value).split(/\s+/).filter(Boolean));
        }
    });
    Object.defineProperty(el, 'textContent', {
        get: function () {
            if (this._text !== '') {
                return this._text;
            }
            return this.children.map(function (child) {
                return child.textContent || '';
            }).join('');
        },
        set: function (value) { this._text = String(value); }
    });
    Object.defineProperty(el, 'parentNode', {
        get: function () { return this._parentNode || null; },
        set: function (value) { this._parentNode = value; }
    });

    if (id) {
        el.id = id;
        el.attributes.id = id;
    }

    return el;
}

function findById(root, id) {
    if (!root) {
        return null;
    }
    if (root.id === id) {
        return root;
    }
    for (let i = 0; i < (root.children || []).length; i++) {
        const found = findById(root.children[i], id);
        if (found) {
            return found;
        }
    }
    return null;
}

class FormData {
    constructor() {
        this._data = Object.create(null);
    }
    append(key, value) {
        this._data[key] = String(value);
    }
    get(key) {
        return Object.prototype.hasOwnProperty.call(this._data, key) ? this._data[key] : null;
    }
    entries() {
        return Object.keys(this._data).map((key) => [key, this._data[key]]);
    }
}

function loadModal(options) {
    options = options || {};
    const posts = [];
    const modalCalls = { open: [], close: 0 };
    const assigns = [];
    const byId = Object.create(null);
    const modalRoot = createEl('div', 'aa-modal-root');
    const fab = createEl('button', 'aa-expediente-detail-new-registro');
    byId['aa-expediente-detail-new-registro'] = fab;

    const fetchImpl = options.fetch || function () {
        return Promise.resolve({
            ok: true,
            status: 200,
            json: function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        record: {
                            id: 55,
                            title: 'Nota',
                            body: 'Texto',
                            recorded_at: '2026-08-20 12:00:00',
                            created_at: '2026-08-20 12:00:00',
                            updated_at: null
                        }
                    }
                });
            }
        });
    };

    const wrappedFetch = function (url, init) {
        const body = init && init.body;
        const fields = Object.create(null);
        [
            'action', '_wpnonce', 'expediente_id', 'title', 'body',
            'client_id', 'blog_id', 'recorded_at', 'created_at', 'id'
        ].forEach(function (key) {
            fields[key] = body && typeof body.get === 'function' ? body.get(key) : null;
        });
        posts.push({ url: url, fields: fields, init: init });
        return fetchImpl(url, init, posts.length);
    };

    const documentEl = {
        readyState: 'complete',
        createElement: function (tag) {
            return createEl(tag);
        },
        getElementById: function (id) {
            if (id === 'aa-modal-root') {
                return modalRoot;
            }
            if (id === 'aa-expediente-detail-new-registro') {
                return fab;
            }
            // Prefer live tree over byId cache (cloneNode.replaceChild invalida el cache).
            const found = findById(modalRoot, id);
            if (found) {
                byId[id] = found;
                return found;
            }
            if (byId[id]) {
                return byId[id];
            }
            return null;
        },
        addEventListener: function () {}
    };

    const locationObj = {
        assign: function (url) {
            assigns.push(String(url));
        }
    };

    const windowObj = {
        AA_EXPEDIENTE_DETAIL_DATA: {
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            nonce: 'nonce-detail',
            action: 'aa_create_expediente_registro_for_expediente',
            expedienteId: '7',
            successUrl: 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes&view=detail&expediente_id=7'
        },
        AAAdmin: {
            openModal: function (payload) {
                modalCalls.open.push(payload);
                modalRoot.children = [];
                if (payload && payload.body) {
                    modalRoot.appendChild(payload.body);
                }
                if (payload && payload.footer) {
                    modalRoot.appendChild(payload.footer);
                }
            },
            closeModal: function () {
                modalCalls.close += 1;
                modalRoot.children = [];
            }
        },
        location: locationObj
    };

    const context = {
        window: windowObj,
        document: documentEl,
        fetch: wrappedFetch,
        FormData: FormData,
        setTimeout: function (fn) { fn(); },
        console: console
    };
    context.window.window = windowObj;
    context.window.document = documentEl;
    context.window.AAAdmin = windowObj.AAAdmin;
    context.window.fetch = wrappedFetch;
    context.window.location = locationObj;

    vm.runInNewContext(modalSrc, context, { filename: 'expediente-registro-create-modal.js' });

    return {
        modal: windowObj.AAAdmin.ExpedienteRegistroCreateModal,
        modalCalls: modalCalls,
        posts: posts,
        assigns: assigns,
        fab: fab,
        modalRoot: modalRoot,
        getById: function (id) {
            return findById(modalRoot, id) || byId[id] || null;
        }
    };
}

function clickSave(harness) {
    harness.getById('aa-expediente-detail-registro-create-save').dispatch('click');
}

describe('expediente-registro-create-modal guardrails', () => {
    it('detalle tiene FAB propio y listado/legacy no lo comparten', () => {
        assert.match(detailSrc, /id="aa-expediente-detail-new-registro"/);
        assert.match(detailSrc, /AA_EXPEDIENTE_DETAIL_DATA/);
        assert.match(detailSrc, /expediente-registro-create-modal\.js/);
        assert.doesNotMatch(detailSrc, /aa-expedientes-new-expediente/);
        assert.doesNotMatch(expedientesIndex, /aa-expediente-detail-new-registro/);
        assert.doesNotMatch(expedientesIndex, /AA_EXPEDIENTE_DETAIL_DATA/);
        assert.doesNotMatch(clientsIndex, /AA_EXPEDIENTE_DETAIL_DATA/);
        assert.doesNotMatch(clientsIndex, /expediente-registro-create-modal\.js/);
        assert.doesNotMatch(modalSrc, /expediente-registros\.js/);
        assert.doesNotMatch(modalSrc, /aa_create_expediente_registro[^_]/);
        assert.ok(!legacyRegistrosSrc.includes('AA_EXPEDIENTE_DETAIL_DATA'));
    });

    it('openCreate usa AAAdmin.openModal con Título/Detalles y límites', () => {
        const harness = loadModal();
        harness.modal.openCreate();

        assert.equal(harness.modalCalls.open.length, 1);
        assert.equal(harness.modalCalls.open[0].title, 'Nuevo registro');

        const title = harness.getById('aa-expediente-detail-registro-create-title');
        const body = harness.getById('aa-expediente-detail-registro-create-body');
        assert.equal(title.maxLength, 200);
        assert.equal(body.maxLength, 10000);
        assert.equal(title.required, true);
        assert.equal(body.required, true);
        assert.match(harness.modalRoot.textContent, /Título/);
        assert.match(harness.modalRoot.textContent, /Detalles/);
        assert.equal(harness.getById('aa-expediente-detail-registro-create-save').textContent, 'Guardar');
        assert.equal(title._focused, true);
        assert.equal(harness.modal.TITLE_MAX, 200);
        assert.equal(harness.modal.BODY_MAX, 10000);
    });

    it('validación cliente rechaza vacíos sin fetch', async () => {
        const harness = loadModal();
        harness.modal.openCreate();
        harness.getById('aa-expediente-detail-registro-create-title').value = '  ';
        harness.getById('aa-expediente-detail-registro-create-body').value = 'x';
        clickSave(harness);
        await Promise.resolve();
        assert.equal(harness.posts.length, 0);
        assert.match(harness.getById('aa-expediente-detail-registro-create-error').textContent, /título/i);
    });

    it('envía solo action, nonce, expediente_id, title y body', async () => {
        const harness = loadModal();
        harness.modal.openCreate();
        harness.getById('aa-expediente-detail-registro-create-title').value = '  Nota  ';
        harness.getById('aa-expediente-detail-registro-create-body').value = '  Cuerpo  ';
        clickSave(harness);
        await Promise.resolve();
        await new Promise(function (resolve) { setImmediate(resolve); });

        assert.equal(harness.posts.length, 1);
        const fields = harness.posts[0].fields;
        assert.equal(fields.action, 'aa_create_expediente_registro_for_expediente');
        assert.equal(fields._wpnonce, 'nonce-detail');
        assert.equal(fields.expediente_id, '7');
        assert.equal(fields.title, 'Nota');
        assert.equal(fields.body, 'Cuerpo');
        assert.equal(fields.client_id, null);
        assert.equal(fields.blog_id, null);
        assert.equal(fields.recorded_at, null);
        assert.equal(fields.created_at, null);
        assert.equal(fields.id, null);
    });

    it('bloquea doble submit y muestra Guardando…', async () => {
        let resolveFetch;
        const pending = new Promise(function (resolve) {
            resolveFetch = resolve;
        });
        const harness = loadModal({
            fetch: function () {
                return pending.then(function () {
                    return {
                        ok: true,
                        status: 200,
                        json: function () {
                            return Promise.resolve({
                                success: true,
                                data: { record: { id: 1, title: 'X', body: 'Y' } }
                            });
                        }
                    };
                });
            }
        });

        harness.modal.openCreate();
        harness.getById('aa-expediente-detail-registro-create-title').value = 'Uno';
        harness.getById('aa-expediente-detail-registro-create-body').value = 'Dos';
        clickSave(harness);
        assert.equal(
            harness.getById('aa-expediente-detail-registro-create-save').textContent,
            'Guardando…'
        );
        clickSave(harness);
        assert.equal(harness.posts.length, 1);

        resolveFetch();
        await new Promise(function (resolve) { setImmediate(resolve); });
    });

    it('error 400 conserva draft y permite reintentar', async () => {
        const harness = loadModal({
            fetch: function () {
                return Promise.resolve({
                    ok: false,
                    status: 400,
                    json: function () {
                        return Promise.resolve({
                            success: false,
                            data: { code: 'missing_title', message: 'El título es obligatorio.' }
                        });
                    }
                });
            }
        });

        harness.modal.openCreate();
        harness.getById('aa-expediente-detail-registro-create-title').value = 'Conservar';
        harness.getById('aa-expediente-detail-registro-create-body').value = 'Texto';
        clickSave(harness);
        await new Promise(function (resolve) { setImmediate(resolve); });

        assert.equal(harness.modalCalls.close, 0);
        assert.equal(harness.assigns.length, 0);
        assert.equal(harness.getById('aa-expediente-detail-registro-create-title').value, 'Conservar');
        assert.equal(harness.getById('aa-expediente-detail-registro-create-body').value, 'Texto');
        assert.match(harness.getById('aa-expediente-detail-registro-create-error').textContent, /título/i);
        assert.equal(harness.getById('aa-expediente-detail-registro-create-save').disabled, false);
        assert.equal(harness.getById('aa-expediente-detail-registro-create-save').textContent, 'Guardar');
    });

    it('403 muestra mensaje de sesión/acceso', async () => {
        const harness = loadModal({
            fetch: function () {
                return Promise.resolve({
                    ok: false,
                    status: 403,
                    json: function () {
                        return Promise.resolve({
                            success: false,
                            data: { code: 'bad_nonce', message: 'Nonce inválido.' }
                        });
                    }
                });
            }
        });
        harness.modal.openCreate();
        harness.getById('aa-expediente-detail-registro-create-title').value = 'A';
        harness.getById('aa-expediente-detail-registro-create-body').value = 'B';
        clickSave(harness);
        await new Promise(function (resolve) { setImmediate(resolve); });
        assert.match(
            harness.getById('aa-expediente-detail-registro-create-error').textContent,
            /Sesión expirada|acceso no permitido/i
        );
    });

    it('404 muestra expediente no disponible', async () => {
        const harness = loadModal({
            fetch: function () {
                return Promise.resolve({
                    ok: false,
                    status: 404,
                    json: function () {
                        return Promise.resolve({
                            success: false,
                            data: { code: 'not_found', message: 'Expediente no encontrado.' }
                        });
                    }
                });
            }
        });
        harness.modal.openCreate();
        harness.getById('aa-expediente-detail-registro-create-title').value = 'A';
        harness.getById('aa-expediente-detail-registro-create-body').value = 'B';
        clickSave(harness);
        await new Promise(function (resolve) { setImmediate(resolve); });
        assert.match(
            harness.getById('aa-expediente-detail-registro-create-error').textContent,
            /ya no está disponible/i
        );
    });

    it('500 y red/JSON inválido muestran error genérico', async () => {
        const harness500 = loadModal({
            fetch: function () {
                return Promise.resolve({
                    ok: false,
                    status: 500,
                    json: function () {
                        return Promise.resolve({
                            success: false,
                            data: { code: 'persistence_failed', message: 'db boom' }
                        });
                    }
                });
            }
        });
        harness500.modal.openCreate();
        harness500.getById('aa-expediente-detail-registro-create-title').value = 'A';
        harness500.getById('aa-expediente-detail-registro-create-body').value = 'B';
        clickSave(harness500);
        await new Promise(function (resolve) { setImmediate(resolve); });
        assert.equal(
            harness500.getById('aa-expediente-detail-registro-create-error').textContent,
            'No se pudo guardar el registro.'
        );

        const harnessNet = loadModal({
            fetch: function () {
                return Promise.reject(new Error('network'));
            }
        });
        harnessNet.modal.openCreate();
        harnessNet.getById('aa-expediente-detail-registro-create-title').value = 'A';
        harnessNet.getById('aa-expediente-detail-registro-create-body').value = 'B';
        clickSave(harnessNet);
        await new Promise(function (resolve) { setImmediate(resolve); });
        assert.equal(
            harnessNet.getById('aa-expediente-detail-registro-create-error').textContent,
            'No se pudo guardar el registro.'
        );

        const harnessJson = loadModal({
            fetch: function () {
                return Promise.resolve({
                    ok: false,
                    status: 500,
                    json: function () {
                        return Promise.reject(new Error('bad json'));
                    }
                });
            }
        });
        harnessJson.modal.openCreate();
        harnessJson.getById('aa-expediente-detail-registro-create-title').value = 'A';
        harnessJson.getById('aa-expediente-detail-registro-create-body').value = 'B';
        clickSave(harnessJson);
        await new Promise(function (resolve) { setImmediate(resolve); });
        assert.equal(
            harnessJson.getById('aa-expediente-detail-registro-create-error').textContent,
            'No se pudo guardar el registro.'
        );
    });

    it('éxito cierra modal y hace location.assign(successUrl)', async () => {
        const harness = loadModal();
        harness.modal.openCreate();
        harness.getById('aa-expediente-detail-registro-create-title').value = 'Nuevo';
        harness.getById('aa-expediente-detail-registro-create-body').value = 'Texto';
        clickSave(harness);
        await new Promise(function (resolve) { setImmediate(resolve); });

        assert.equal(harness.modalCalls.close, 1);
        assert.equal(harness.assigns.length, 1);
        assert.equal(
            harness.assigns[0],
            'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes&view=detail&expediente_id=7'
        );
        assert.doesNotMatch(harness.assigns[0], /records_page/);
    });

    it('no inserta cards ni depende del legacy', () => {
        assert.doesNotMatch(modalSrc, /appendChild\(.*card/i);
        assert.doesNotMatch(modalSrc, /aa-expediente-registros-list/);
        assert.doesNotMatch(modalSrc, /persistRecordInList/);
        assert.doesNotMatch(modalSrc, /ExpedienteRegistros\.openCreate/);
        assert.doesNotMatch(modalSrc, /createElement\(['\"]details/);
    });
});
