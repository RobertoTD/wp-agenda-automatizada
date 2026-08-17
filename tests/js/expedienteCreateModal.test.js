'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modalPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/expedientes/expediente-create-modal.js'
);
const modalSrc = fs.readFileSync(modalPath, 'utf8');
const clientsIndexPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/index.php'
);
const clientsIndex = fs.readFileSync(clientsIndexPath, 'utf8');
const expedientesIndexPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/expedientes/index.php'
);
const expedientesIndex = fs.readFileSync(expedientesIndexPath, 'utf8');

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
            Object.keys(this.attributes).forEach(function (name) {
                clone.setAttribute(name, clone.attributes[name] || el.attributes[name]);
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
        focus: function () {}
    };

    if (tag === 'input' || tag === 'textarea') {
        Object.defineProperty(el, 'maxLength', {
            get: function () { return this._maxLength || -1; },
            set: function (value) { this._maxLength = Number(value); }
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
    }

    return el;
}

function findById(root, id) {
    if (root.id === id) {
        return root;
    }
    for (let i = 0; i < root.children.length; i++) {
        const found = findById(root.children[i], id);
        if (found) {
            return found;
        }
    }
    return null;
}

function loadModal(options) {
    const opts = options || {};
    const byId = Object.create(null);
    const modalRoot = createEl('div', 'aa-modal-root');
    const modalCalls = { open: [], close: 0 };
    const posts = [];
    const docEvents = Object.create(null);
    const dispatchedEvents = [];
    let fetchImpl = opts.fetch;

    if (!fetchImpl) {
        fetchImpl = function () {
            return Promise.resolve({
                ok: true,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: {
                            id: 42,
                            title: 'Nuevo',
                            description: null,
                            category: { slug: 'general', name: 'General' },
                            created_at: '2026-08-17 13:00:00'
                        }
                    });
                }
            });
        };
    }

    const wrappedFetch = function (url, init) {
        const body = init && init.body;
        const fields = {};
        [
            'action', '_wpnonce', 'title', 'description',
            'category_id', 'category_slug', 'client_id', 'blog_id'
        ].forEach(function (key) {
            fields[key] = body && typeof body.get === 'function' ? body.get(key) : null;
        });
        posts.push({ url: url, fields: fields });
        return fetchImpl(url, init, posts.length);
    };

    const documentEl = {
        createElement: function (tag) {
            return createEl(tag);
        },
        getElementById: function (id) {
            if (byId[id]) {
                return byId[id];
            }
            if (id === 'aa-modal-root') {
                return modalRoot;
            }
            const found = findById(modalRoot, id);
            if (found) {
                byId[id] = found;
                return found;
            }
            return null;
        },
        addEventListener: function (type, handler) {
            docEvents[type] = docEvents[type] || [];
            docEvents[type].push(handler);
        },
        dispatchEvent: function (event) {
            dispatchedEvents.push(event);
            const list = docEvents[event.type] || [];
            list.forEach(function (handler) {
                handler(event);
            });
            return true;
        }
    };

    const windowObj = {
        AA_EXPEDIENTES_DATA: {
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            nonce: 'nonce-test',
            actions: { create: 'aa_create_expediente' }
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
        }
    };

    const context = {
        window: windowObj,
        document: documentEl,
        fetch: wrappedFetch,
        FormData: FormData,
        CustomEvent: CustomEvent,
        setTimeout: function (fn) { fn(); },
        console: console
    };
    context.window.window = windowObj;
    context.window.document = documentEl;
    context.window.AAAdmin = windowObj.AAAdmin;
    context.window.fetch = wrappedFetch;

    vm.runInNewContext(modalSrc, context, { filename: 'expediente-create-modal.js' });

    return {
        modal: windowObj.AAAdmin.ExpedienteCreateModal,
        modalCalls: modalCalls,
        posts: posts,
        document: documentEl,
        docEvents: docEvents,
        modalRoot: modalRoot,
        dispatchedEvents: dispatchedEvents,
        getById: function (id) {
            return findById(modalRoot, id);
        }
    };
}

function clickSave(harness) {
    harness.getById('aa-expediente-create-save').dispatch('click');
}

describe('expediente-create-modal guardrails', () => {
    it('FAB de expedientes solo vive en module=expedientes, no en clientes', () => {
        assert.match(expedientesIndex, /id="aa-expedientes-new-expediente"/);
        assert.match(expedientesIndex, /data-expedientes-tool="create-expediente"/);
        assert.match(expedientesIndex, /Nuevo expediente/);
        assert.doesNotMatch(clientsIndex, /aa-expedientes-new-expediente/);
        assert.doesNotMatch(clientsIndex, /data-expedientes-tool="create-expediente"/);
        assert.doesNotMatch(modalSrc, /aa-clients-new-client/);
        assert.doesNotMatch(modalSrc, /aa_crear_cliente/);
    });

    it('openCreate usa AAAdmin.openModal con campos y categoría General read-only', () => {
        const harness = loadModal();
        harness.modal.openCreate();

        assert.equal(harness.modalCalls.open.length, 1);
        const payload = harness.modalCalls.open[0];
        assert.equal(payload.title, 'Nuevo expediente');
        assert.ok(payload.body);
        assert.ok(payload.footer);

        const title = harness.getById('aa-expediente-create-title');
        const description = harness.getById('aa-expediente-create-description');
        assert.equal(title.maxLength, 200);
        assert.equal(description.maxLength, 10000);
        assert.match(harness.modalRoot.textContent, /General/);
        assert.equal(harness.getById('aa-expediente-create-save').textContent, 'Crear expediente');
    });

    it('rechaza nombre vacío o solo espacios sin fetch', async () => {
        const harness = loadModal();
        harness.modal.openCreate();

        harness.getById('aa-expediente-create-title').value = '   ';
        clickSave(harness);
        await Promise.resolve();

        assert.equal(harness.posts.length, 0);
        assert.match(harness.getById('aa-expediente-create-error').textContent, /obligatorio/i);
        assert.equal(harness.modalCalls.close, 0);
    });

    it('envía solo action, nonce, title y description opcional', async () => {
        const harness = loadModal();
        harness.modal.openCreate();

        harness.getById('aa-expediente-create-title').value = '  Contrato  ';
        harness.getById('aa-expediente-create-description').value = 'Detalle';
        clickSave(harness);
        await Promise.resolve();

        assert.equal(harness.posts.length, 1);
        const fields = harness.posts[0].fields;
        assert.equal(fields.action, 'aa_create_expediente');
        assert.equal(fields._wpnonce, 'nonce-test');
        assert.equal(fields.title, 'Contrato');
        assert.equal(fields.description, 'Detalle');
        assert.equal(fields.category_id, null);
        assert.equal(fields.category_slug, null);
        assert.equal(fields.client_id, null);
        assert.equal(fields.blog_id, null);
    });

    it('omite description vacía para que el backend normalice a null', async () => {
        const harness = loadModal();
        harness.modal.openCreate();

        harness.getById('aa-expediente-create-title').value = 'Solo título';
        harness.getById('aa-expediente-create-description').value = '   ';
        clickSave(harness);
        await Promise.resolve();

        assert.equal(harness.posts[0].fields.description, null);
    });

    it('previene doble envío mientras la solicitud está activa', async () => {
        let resolveFetch;
        const pending = new Promise(function (resolve) {
            resolveFetch = resolve;
        });

        const harness = loadModal({
            fetch: function () {
                return pending.then(function () {
                    return {
                        ok: true,
                        json: function () {
                            return Promise.resolve({ success: true, data: { id: 1, title: 'X' } });
                        }
                    };
                });
            }
        });

        harness.modal.openCreate();
        harness.getById('aa-expediente-create-title').value = 'Uno';
        clickSave(harness);
        clickSave(harness);
        assert.equal(harness.posts.length, 1);

        resolveFetch();
        await new Promise(function (resolve) { setImmediate(resolve); });
    });

    it('error del servidor mantiene modal abierto y conserva inputs', async () => {
        const harness = loadModal({
            fetch: function () {
                return Promise.resolve({
                    ok: true,
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
        harness.getById('aa-expediente-create-title').value = 'Conservar';
        harness.getById('aa-expediente-create-description').value = 'Texto';
        clickSave(harness);
        await new Promise(function (resolve) { setImmediate(resolve); });

        assert.equal(harness.modalCalls.close, 0);
        assert.equal(harness.getById('aa-expediente-create-title').value, 'Conservar');
        assert.equal(harness.getById('aa-expediente-create-description').value, 'Texto');
        assert.match(harness.getById('aa-expediente-create-error').textContent, /obligatorio/i);
    });

    it('fallo inesperado muestra mensaje genérico sin cerrar modal', async () => {
        const harness = loadModal({
            fetch: function () {
                return Promise.reject(new Error('network'));
            }
        });

        harness.modal.openCreate();
        harness.getById('aa-expediente-create-title').value = 'X';
        clickSave(harness);
        await new Promise(function (resolve) { setImmediate(resolve); });

        assert.equal(harness.modalCalls.close, 0);
        assert.equal(
            harness.getById('aa-expediente-create-error').textContent,
            'No se pudo crear el expediente.'
        );
    });

    it('éxito cierra modal y emite aa:expediente:saved', async () => {
        const harness = loadModal();
        let savedDetail = null;

        harness.document.addEventListener('aa:expediente:saved', function (event) {
            savedDetail = event.detail;
        });

        harness.modal.openCreate();
        harness.getById('aa-expediente-create-title').value = 'Nuevo';
        clickSave(harness);
        await new Promise(function (resolve) { setImmediate(resolve); });

        assert.equal(harness.modalCalls.close, 1);
        assert.equal(harness.dispatchedEvents.length, 1);
        assert.equal(harness.dispatchedEvents[0].type, 'aa:expediente:saved');
        assert.ok(savedDetail || harness.dispatchedEvents[0].detail);
        const detail = savedDetail || harness.dispatchedEvents[0].detail;
        assert.equal(detail.expediente.id, 42);
    });

    it('expone límites 200/10000', () => {
        const harness = loadModal();
        assert.equal(harness.modal.TITLE_MAX, 200);
        assert.equal(harness.modal.DESCRIPTION_MAX, 10000);
    });
});
