'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/expedientes/expedientes-module.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(),
        id: id || '',
        children: [],
        attributes: Object.create(null),
        _text: '',
        _html: '',
        value: '',
        disabled: false,
        parentNode: null,
        style: {},
        _listeners: Object.create(null),
        classList: {
            _set: new Set(),
            add: function (c) {
                this._set.add(c);
            },
            remove: function (c) {
                this._set.delete(c);
            },
            contains: function (c) {
                return this._set.has(c);
            }
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
        hasAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name);
        },
        removeAttribute: function (name) {
            delete this.attributes[name];
        },
        appendChild: function (child) {
            child.parentNode = this;
            this.children.push(child);
            return child;
        },
        removeChild: function (child) {
            this.children = this.children.filter(function (c) { return c !== child; });
            child.parentNode = null;
            return child;
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
        querySelector: function (selector) {
            return findMatch(this, selector);
        },
        querySelectorAll: function (selector) {
            const out = [];
            collectMatches(this, selector, out);
            return out;
        }
    };

    Object.defineProperty(el, 'className', {
        get: function () {
            return Array.from(el.classList._set).join(' ');
        },
        set: function (value) {
            el.classList._set = new Set(String(value).split(/\s+/).filter(Boolean));
        }
    });
    Object.defineProperty(el, 'textContent', {
        get: function () { return this._text; },
        set: function (value) { this._text = String(value); this._html = ''; }
    });
    Object.defineProperty(el, 'innerHTML', {
        get: function () { return this._html; },
        set: function (value) { this._html = String(value); }
    });
    Object.defineProperty(el, 'firstChild', {
        get: function () { return this.children[0] || null; }
    });

    if (id) {
        el.id = id;
    }

    return el;
}

function matchesSelector(el, selector) {
    if (!el) {
        return false;
    }
    if (selector.charAt(0) === '#') {
        return el.id === selector.slice(1);
    }
    if (selector.charAt(0) === '.') {
        return el.classList.contains(selector.slice(1));
    }
    if (selector.charAt(0) === '[') {
        const body = selector.slice(1, -1);
        const eq = body.indexOf('=');
        if (eq === -1) {
            return el.hasAttribute(body);
        }
        const name = body.slice(0, eq);
        const value = body.slice(eq + 1).replace(/^["']|["']$/g, '');
        return el.getAttribute(name) === value;
    }
    return el.tagName === selector.toUpperCase();
}

function findMatch(root, selector) {
    if (matchesSelector(root, selector)) {
        return root;
    }
    for (let i = 0; i < root.children.length; i++) {
        const found = findMatch(root.children[i], selector);
        if (found) {
            return found;
        }
    }
    return null;
}

function collectMatches(root, selector, out) {
    if (matchesSelector(root, selector)) {
        out.push(root);
    }
    root.children.forEach(function (child) {
        collectMatches(child, selector, out);
    });
}

function buildDom() {
    const byId = Object.create(null);
    const ids = [
        'aa-expedientes-root',
        'aa-expedientes-list-root',
        'aa-expedientes-action-bar',
        'aa-expedientes-search',
        'aa-expedientes-pagination',
        'aa-expedientes-prev',
        'aa-expedientes-next',
        'aa-expedientes-status',
        'aa-expedientes-grid'
    ];

    ids.forEach(function (id) {
        const tag = id.indexOf('prev') !== -1 || id.indexOf('next') !== -1 ? 'button'
            : (id.indexOf('search') !== -1 ? 'input' : 'div');
        byId[id] = createEl(tag, id);
    });

    byId['aa-expedientes-pagination'].classList.add('hidden');
    byId['aa-expedientes-pagination'].setAttribute('hidden', '');
    byId['aa-expedientes-prev'].disabled = true;
    byId['aa-expedientes-next'].disabled = true;

    const documentEl = {
        readyState: 'loading',
        body: createEl('body'),
        _listeners: Object.create(null),
        getElementById: function (id) {
            return byId[id] || null;
        },
        createElement: function (tag) {
            return createEl(tag);
        },
        addEventListener: function (type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        }
    };

    return { document: documentEl, byId: byId };
}

function listPayload(overrides) {
    return Object.assign({
        expedientes: [],
        page: 1,
        per_page: 15,
        total: 0,
        total_pages: 0,
        has_previous: false,
        has_next: false
    }, overrides || {});
}

function expedienteItem(overrides) {
    return Object.assign({
        id: 7,
        title: 'Contrato laboral',
        description: 'Detalle',
        category: { slug: 'general', name: 'General' },
        created_at: '2026-08-17 13:00:00'
    }, overrides || {});
}

function loadModule(options) {
    const opts = options || {};
    const dom = buildDom();
    const posts = [];
    let fetchImpl = opts.fetch;

    if (!fetchImpl) {
        fetchImpl = function (url, init) {
            const body = init && init.body;
            const fields = {};
            ['action', '_wpnonce', 'query', 'page', 'per_page', 'limit', 'offset', 'blog_id'].forEach(function (key) {
                fields[key] = body && typeof body.get === 'function' ? body.get(key) : null;
            });
            posts.push({ url: url, fields: fields, signal: init && init.signal });
            return Promise.resolve({
                ok: true,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: listPayload(opts.data)
                    });
                }
            });
        };
    } else {
        const inner = fetchImpl;
        fetchImpl = function (url, init) {
            const body = init && init.body;
            const fields = {};
            ['action', '_wpnonce', 'query', 'page', 'per_page', 'limit', 'offset', 'blog_id'].forEach(function (key) {
                fields[key] = body && typeof body.get === 'function' ? body.get(key) : null;
            });
            posts.push({ url: url, fields: fields, signal: init && init.signal });
            return inner(url, init);
        };
    }

    const windowObj = {
        AA_EXPEDIENTES_DATA: {
            ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
            nonce: 'nonce-test',
            actions: { list: 'aa_list_expedientes', create: 'aa_create_expediente' }
        },
        ajaxurl: 'https://example.test/wp-admin/admin-ajax.php',
        AAAdmin: {}
    };

    const context = {
        window: windowObj,
        document: dom.document,
        fetch: fetchImpl,
        FormData: FormData,
        AbortController: AbortController,
        DOMException: DOMException,
        setTimeout: setTimeout,
        clearTimeout: clearTimeout,
        console: console
    };
    context.window.window = windowObj;
    context.window.document = dom.document;
    context.window.AAAdmin = windowObj.AAAdmin;
    context.window.fetch = fetchImpl;

    vm.runInNewContext(moduleSrc, context, { filename: 'expedientes-module.js' });

    return {
        module: windowObj.AAAdmin.ExpedientesModule,
        byId: dom.byId,
        posts: posts,
        window: windowObj
    };
}

describe('expedientes-module', () => {
    it('formatCreatedAt formatea fecha MySQL a UI', () => {
        const harness = loadModule();
        assert.equal(harness.module.formatCreatedAt('2026-08-17 13:00:00'), '17/08/2026');
        assert.equal(harness.module.formatCreatedAt(''), '—');
    });

    it('createCard usa textContent para título/descripción y no anida data-aa-card en el slot', () => {
        const harness = loadModule();
        const card = harness.module.createCard(expedienteItem({
            title: '<img src=x onerror=alert(1)>',
            description: '<b>html</b>'
        }));

        assert.equal(card.getAttribute('data-aa-card'), '');
        const toggle = card.querySelector('[data-aa-card-toggle]');
        assert.ok(toggle);
        const title = card.querySelector('.aa-expediente-card-title');
        assert.equal(title.textContent, '<img src=x onerror=alert(1)>');
        assert.equal(title.innerHTML, '');
        const description = card.querySelector('.aa-expediente-card-description');
        assert.equal(description.textContent, '<b>html</b>');
        const slot = card.querySelector('[data-expediente-id]');
        assert.equal(slot.getAttribute('data-expediente-id'), '7');
        assert.equal(slot.hasAttribute('data-aa-card'), false);
        assert.equal(slot.querySelector('[data-aa-card]'), null);
    });

    it('createCard muestra Sin descripción y categoría por nombre', () => {
        const harness = loadModule();
        const card = harness.module.createCard(expedienteItem({
            description: null,
            category: { slug: 'general', name: 'General' }
        }));
        assert.equal(card.querySelector('.aa-expediente-card-description').textContent, 'Sin descripción');
        const metas = [];
        collectMatches(card, '.aa-expediente-card-meta-value', metas);
        assert.equal(metas[0].textContent, 'General');
        assert.equal(metas[1].textContent, '17/08/2026');
    });

    it('init pide página 1 con query vacía y sin per_page/limit/offset/blog_id', async () => {
        const harness = loadModule({
            data: listPayload({
                expedientes: [expedienteItem()],
                total: 1,
                total_pages: 1
            })
        });

        await harness.module.init();
        assert.equal(harness.posts.length, 1);
        assert.equal(harness.posts[0].fields.action, 'aa_list_expedientes');
        assert.equal(harness.posts[0].fields._wpnonce, 'nonce-test');
        assert.equal(harness.posts[0].fields.query, '');
        assert.equal(harness.posts[0].fields.page, '1');
        assert.equal(harness.posts[0].fields.per_page, null);
        assert.equal(harness.posts[0].fields.limit, null);
        assert.equal(harness.posts[0].fields.offset, null);
        assert.equal(harness.posts[0].fields.blog_id, null);
        assert.equal(harness.byId['aa-expedientes-grid'].children.length, 1);
        assert.equal(harness.byId['aa-expedientes-status'].textContent, '');
        assert.equal(harness.byId['aa-expedientes-pagination'].classList.contains('hidden'), true);
    });

    it('inventario vacío muestra empty state y deja el buscador', async () => {
        const harness = loadModule({ data: listPayload() });
        await harness.module.init();
        assert.equal(harness.byId['aa-expedientes-status'].textContent, 'Aún no hay expedientes.');
        assert.equal(harness.byId['aa-expedientes-grid'].children.length, 0);
        assert.ok(harness.byId['aa-expedientes-search']);
    });

    it('búsqueda sin resultados usa copy distinto', async () => {
        const harness = loadModule({
            data: listPayload({ total: 0, total_pages: 0 })
        });
        harness.byId['aa-expedientes-search'].value = 'zzzz';
        await harness.module.load('zzzz', 1);
        assert.equal(harness.byId['aa-expedientes-status'].textContent, 'No se encontraron expedientes.');
        assert.equal(harness.posts[0].fields.query, 'zzzz');
        assert.equal(harness.posts[0].fields.page, '1');
    });

    it('paginación usa page efectiva y conserva query', async () => {
        const harness = loadModule({
            fetch: function (url, init) {
                const page = init.body.get('page');
                if (page === '2') {
                    return Promise.resolve({
                        ok: true,
                        json: function () {
                            return Promise.resolve({
                                success: true,
                                data: listPayload({
                                    expedientes: [expedienteItem({ id: 2, title: 'B' })],
                                    page: 2,
                                    total: 16,
                                    total_pages: 2,
                                    has_previous: true,
                                    has_next: false
                                })
                            });
                        }
                    });
                }

                return Promise.resolve({
                    ok: true,
                    json: function () {
                        return Promise.resolve({
                            success: true,
                            data: listPayload({
                                expedientes: [expedienteItem({ id: 1, title: 'A' })],
                                page: 1,
                                total: 16,
                                total_pages: 2,
                                has_previous: false,
                                has_next: true
                            })
                        });
                    }
                });
            }
        });

        await harness.module.init();
        await harness.module.load('Contrato', 2);
        assert.equal(harness.byId['aa-expedientes-pagination'].classList.contains('hidden'), false);
        assert.equal(harness.byId['aa-expedientes-prev'].disabled, false);
        assert.equal(harness.byId['aa-expedientes-next'].disabled, true);

        harness.byId['aa-expedientes-prev'].dispatch('click');
        await new Promise(function (resolve) { setImmediate(resolve); });

        const last = harness.posts[harness.posts.length - 1];
        assert.equal(last.fields.page, '1');
        assert.equal(last.fields.query, 'Contrato');
        assert.equal(last.fields.per_page, null);
    });

    it('respuesta inválida o HTTP error no deja loading', async () => {
        const harness = loadModule({
            fetch: function () {
                return Promise.resolve({
                    ok: false,
                    json: function () {
                        return Promise.resolve({ success: false, data: { message: 'SQLSTATE' } });
                    }
                });
            }
        });
        await harness.module.load('', 1);
        assert.equal(harness.byId['aa-expedientes-status'].textContent, 'No se pudieron cargar los expedientes.');
        assert.equal(harness.byId['aa-expedientes-status'].classList.contains('is-error'), true);
        assert.equal(harness.byId['aa-expedientes-status'].textContent.indexOf('SQLSTATE'), -1);
        assert.equal(harness.byId['aa-expedientes-grid'].getAttribute('aria-busy'), null);
    });

    it('una respuesta antigua no sustituye una búsqueda más reciente', async () => {
        let resolveSlow;
        const slow = new Promise(function (resolve) {
            resolveSlow = resolve;
        });
        let calls = 0;

        const harness = loadModule({
            fetch: function () {
                calls += 1;
                if (calls === 1) {
                    return slow.then(function () {
                        return {
                            ok: true,
                            json: function () {
                                return Promise.resolve({
                                    success: true,
                                    data: listPayload({
                                        expedientes: [expedienteItem({ title: 'Viejo' })],
                                        total: 1,
                                        total_pages: 1
                                    })
                                });
                            }
                        };
                    });
                }
                return Promise.resolve({
                    ok: true,
                    json: function () {
                        return Promise.resolve({
                            success: true,
                            data: listPayload({
                                expedientes: [expedienteItem({ title: 'Nuevo' })],
                                total: 1,
                                total_pages: 1
                            })
                        });
                    }
                });
            }
        });

        const first = harness.module.load('viejo', 1);
        const second = harness.module.load('nuevo', 1);
        resolveSlow();
        await Promise.all([first, second]);

        const title = harness.byId['aa-expedientes-grid'].querySelector('.aa-expediente-card-title');
        assert.equal(title.textContent, 'Nuevo');
        assert.equal(harness.posts[0].signal.aborted, true);
    });

    it('Enter cancela debounce y busca página 1', async () => {
        const harness = loadModule({
            data: listPayload({
                expedientes: [expedienteItem()],
                total: 1,
                total_pages: 1
            })
        });
        await harness.module.init();
        harness.byId['aa-expedientes-search'].value = '  Contrato  ';
        harness.byId['aa-expedientes-search'].dispatch('keydown', {
            key: 'Enter',
            keyCode: 13,
            preventDefault: function () {}
        });
        await new Promise(function (resolve) { setImmediate(resolve); });
        const last = harness.posts[harness.posts.length - 1];
        assert.equal(last.fields.query, 'Contrato');
        assert.equal(last.fields.page, '1');
    });
});
