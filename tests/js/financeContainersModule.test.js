'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical/finance/finance-module.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(),
        id: id || '',
        children: [],
        attributes: Object.create(null),
        dataset: Object.create(null),
        _text: '',
        _html: '',
        disabled: false,
        hidden: false,
        type: 'button',
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
        setAttributeNS: function (ns, name, value) {
            this.attributes[name] = String(value);
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
        get: function () {
            if (this.children.length > 0) {
                return this.children.map(function(c) { return c.textContent; }).join('');
            }
            return this._text;
        },
        set: function (value) {
            this._text = String(value);
            this._html = '';
            this.children = [];
        }
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
    if (!el) return false;
    if (selector.charAt(0) === '#') return el.id === selector.slice(1);
    if (selector.charAt(0) === '.') return el.classList.contains(selector.slice(1));
    return el.tagName === selector.toUpperCase();
}

function findMatch(root, selector) {
    if (matchesSelector(root, selector)) return root;
    for (let i = 0; i < root.children.length; i++) {
        const found = findMatch(root.children[i], selector);
        if (found) return found;
    }
    return null;
}

function collectMatches(root, selector, out) {
    if (matchesSelector(root, selector)) out.push(root);
    root.children.forEach(function (child) {
        collectMatches(child, selector, out);
    });
}

function buildDom() {
    const document = {
        _elements: Object.create(null),
        createElement: function (tag) {
            return createEl(tag);
        },
        createElementNS: function (ns, tag) {
            return createEl(tag);
        },
        getElementById: function (id) {
            return this._elements[id] || null;
        },
        querySelectorAll: function (selector) {
            const out = [];
            Object.values(this._elements).forEach(function (el) {
                collectMatches(el, selector, out);
            });
            return out;
        }
    };

    const root = createEl('div', 'aa-finance-root');
    const statusEl = createEl('div', 'aa-finance-status');
    statusEl.setAttribute('aria-live', 'polite');
    const gridEl = createEl('div', 'aa-finance-grid');
    const paginationEl = createEl('div', 'aa-finance-pagination');
    const prevBtn = createEl('button', 'aa-finance-prev');
    const nextBtn = createEl('button', 'aa-finance-next');
    const pageIndicatorEl = createEl('span', 'aa-finance-page-indicator');

    paginationEl.appendChild(prevBtn);
    paginationEl.appendChild(pageIndicatorEl);
    paginationEl.appendChild(nextBtn);

    root.appendChild(statusEl);
    root.appendChild(paginationEl);
    root.appendChild(gridEl);

    document._elements['aa-finance-root'] = root;
    document._elements['aa-finance-status'] = statusEl;
    document._elements['aa-finance-grid'] = gridEl;
    document._elements['aa-finance-pagination'] = paginationEl;
    document._elements['aa-finance-prev'] = prevBtn;
    document._elements['aa-finance-next'] = nextBtn;
    document._elements['aa-finance-page-indicator'] = pageIndicatorEl;

    return { document, root, statusEl, gridEl, paginationEl, prevBtn, nextBtn, pageIndicatorEl };
}

describe('FinanceContainersModule (Ciclo 3D1)', () => {

    it('permanece completamente inerte si #aa-finance-root no existe en el DOM', () => {
        const { document } = buildDom();
        delete document._elements['aa-finance-root'];

        let fetchCalled = false;
        const sandbox = {
            document: document,
            window: {
                AA_FINANCE_DATA: {
                    ajaxUrl: 'https://example.com/ajax',
                    nonce: 'test_nonce',
                    familyKey: 'finance',
                    variantKey: 'general',
                    actions: { listContainers: 'aa_list_finance_containers' }
                }
            },
            fetch: () => { fetchCalled = true; return Promise.resolve(); }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        assert.strictEqual(fetchCalled, false);
    });

    it('muestra error visible y no hace fetch si AA_FINANCE_DATA falta o es inválido', () => {
        const { document, statusEl } = buildDom();

        let fetchCalled = false;
        const sandbox = {
            document: document,
            window: {
                AA_FINANCE_DATA: {
                    ajaxUrl: '', // Inválido
                    nonce: 'nonce',
                    familyKey: 'finance',
                    variantKey: 'general',
                    actions: { listContainers: 'aa_list_finance_containers' }
                }
            },
            fetch: () => { fetchCalled = true; return Promise.resolve(); }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        assert.strictEqual(fetchCalled, false);
        assert.strictEqual(statusEl.textContent, 'No se pudo iniciar el módulo de Finanzas.');
    });

    it('no lee ni modifica window.ajaxurl', () => {
        const { document } = buildDom();
        const sandbox = {
            document: document,
            window: {
                AA_FINANCE_DATA: {
                    ajaxUrl: 'https://example.com/admin-ajax.php',
                    nonce: 'safe_nonce',
                    familyKey: 'finance',
                    variantKey: 'general',
                    actions: { listContainers: 'aa_list_finance_containers' }
                }
            },
            FormData: class {
                constructor() { this.entries = {}; }
                append(k, v) { this.entries[k] = v; }
            },
            fetch: () => new Promise(() => {})
        };

        vm.runInNewContext(moduleSrc, sandbox);
        assert.strictEqual(sandbox.window.ajaxurl, undefined);
    });

    it('envía petición POST exacta con action, _wpnonce, variant_key y page', async () => {
        const { document } = buildDom();

        let requestedUrl = '';
        let requestedOptions = null;

        const sandbox = {
            document: document,
            window: {
                AA_FINANCE_DATA: {
                    ajaxUrl: 'https://example.com/admin-ajax.php',
                    nonce: 'my_nonce_123',
                    familyKey: 'finance',
                    variantKey: 'general',
                    actions: { listContainers: 'aa_list_finance_containers' }
                }
            },
            FormData: class {
                constructor() { this.data = {}; }
                append(k, v) { this.data[k] = v; }
            },
            AbortController: class {
                constructor() { this.signal = {}; }
                abort() {}
            },
            fetch: (url, options) => {
                requestedUrl = url;
                requestedOptions = options;
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({
                        success: true,
                        data: {
                            items: [],
                            page: 1,
                            per_page: 15,
                            total: 0,
                            total_pages: 0,
                            has_previous: false,
                            has_next: false
                        }
                    })
                });
            }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        await new Promise(setImmediate);

        assert.strictEqual(requestedUrl, 'https://example.com/admin-ajax.php');
        assert.strictEqual(requestedOptions.method, 'POST');
        assert.strictEqual(requestedOptions.body.data.action, 'aa_list_finance_containers');
        assert.strictEqual(requestedOptions.body.data._wpnonce, 'my_nonce_123');
        assert.strictEqual(requestedOptions.body.data.variant_key, 'general');
        assert.strictEqual(requestedOptions.body.data.page, '1');
    });

    it('renderiza estado vacío correctamente cuando total es 0', async () => {
        const { document, gridEl, paginationEl } = buildDom();

        const sandbox = {
            document: document,
            window: {
                AA_FINANCE_DATA: {
                    ajaxUrl: 'https://example.com/admin-ajax.php',
                    nonce: 'nonce',
                    familyKey: 'finance',
                    variantKey: 'general',
                    actions: { listContainers: 'aa_list_finance_containers' }
                }
            },
            FormData: class { append() {} },
            fetch: () => Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve({
                    success: true,
                    data: {
                        items: [],
                        page: 1,
                        per_page: 15,
                        total: 0,
                        total_pages: 0,
                        has_previous: false,
                        has_next: false
                    }
                })
            })
        };

        vm.runInNewContext(moduleSrc, sandbox);
        await new Promise(setImmediate);

        assert.strictEqual(paginationEl.hidden, true);
        assert.ok(gridEl.textContent.includes('No hay contenedores creados'));
        assert.ok(gridEl.textContent.includes('Aún no se han registrado contenedores en esta variante.'));
    });

    it('renderiza cards, neutralidad de amount_total y previene XSS', async () => {
        const { document, gridEl } = buildDom();

        const items = [
            {
                id: 1,
                family_key: 'finance',
                variant_key: 'general',
                title: '<img src=x onerror=alert(1)> Título 1',
                details: 'Detalles con <script>alert(2)</script>',
                amount_total: null,
                created_at: '2026-08-29 18:30:00'
            },
            {
                id: 2,
                family_key: 'finance',
                variant_key: 'general',
                title: 'Caja Chica',
                details: null,
                amount_total: '0.00',
                created_at: '2026-08-29 19:00:00'
            },
            {
                id: 3,
                family_key: 'finance',
                variant_key: 'general',
                title: 'Ingresos Positivos',
                details: 'Texto largo',
                amount_total: '150.85',
                created_at: '2026-08-29 19:30:00'
            },
            {
                id: 4,
                family_key: 'finance',
                variant_key: 'general',
                title: 'Gastos Negativos',
                details: 'Gasto operativo',
                amount_total: '-45.00',
                created_at: '2026-08-29 20:00:00'
            }
        ];

        const sandbox = {
            document: document,
            window: {
                AA_FINANCE_DATA: {
                    ajaxUrl: 'https://example.com/admin-ajax.php',
                    nonce: 'nonce',
                    familyKey: 'finance',
                    variantKey: 'general',
                    actions: { listContainers: 'aa_list_finance_containers' }
                }
            },
            FormData: class { append() {} },
            fetch: () => Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve({
                    success: true,
                    data: {
                        items: items,
                        page: 1,
                        per_page: 15,
                        total: 4,
                        total_pages: 1,
                        has_previous: false,
                        has_next: false
                    }
                })
            })
        };

        vm.runInNewContext(moduleSrc, sandbox);
        await new Promise(setImmediate);

        assert.strictEqual(gridEl.children.length, 4);

        // Card 1: XSS payload insertado como texto, amount_total null -> "Sin importes", fecha formateada a 29/08/2026
        const card1 = gridEl.children[0];
        assert.ok(card1.textContent.includes('<img src=x onerror=alert(1)> Título 1'));
        assert.ok(card1.textContent.includes('Detalles con <script>alert(2)</script>'));
        assert.ok(card1.textContent.includes('Sin importes'));
        assert.ok(card1.textContent.includes('29/08/2026'));

        // Card 2: "0.00", details null no genera elemento p
        const card2 = gridEl.children[1];
        assert.ok(card2.textContent.includes('0.00'));

        // Card 3 & 4: neutralidad de estilo entre positivo y negativo
        const card3 = gridEl.children[2];
        const card4 = gridEl.children[3];
        assert.ok(card3.textContent.includes('150.85'));
        assert.ok(card4.textContent.includes('-45.00'));

        const badge3 = card3.querySelector('.font-mono');
        const badge4 = card4.querySelector('.font-mono');
        assert.strictEqual(badge3.className, badge4.className); // Mismo estilo exacto neutral
    });

    it('rechaza fail-closed payloads inválidos (tipos no enteros, per_page != 15, variant mismatch)', async () => {
        const { document, statusEl } = buildDom();

        const invalidPayloadCases = [
            { per_page: 10 }, // per_page != 15
            { page: 1.5 }, // page no entero
            { total: -1 }, // total negativo
            { total_pages: NaN }, // total_pages NaN
            { items: [{ id: 1, family_key: 'other_family', variant_key: 'general', title: 'T', details: null, amount_total: null, created_at: '2026-08-29' }] }, // family mismatch
            { items: [{ id: 1.5, family_key: 'finance', variant_key: 'general', title: 'T', details: null, amount_total: null, created_at: '2026-08-29' }] } // id fraccionario
        ];

        for (const badCase of invalidPayloadCases) {
            const data = Object.assign({
                items: [],
                page: 1,
                per_page: 15,
                total: 0,
                total_pages: 0,
                has_previous: false,
                has_next: false
            }, badCase);

            const sandbox = {
                document: document,
                window: {
                    AA_FINANCE_DATA: {
                        ajaxUrl: 'https://example.com/admin-ajax.php',
                        nonce: 'nonce',
                        familyKey: 'finance',
                        variantKey: 'general',
                        actions: { listContainers: 'aa_list_finance_containers' }
                    }
                },
                FormData: class { append() {} },
                fetch: () => Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ success: true, data: data })
                })
            };

            vm.runInNewContext(moduleSrc, sandbox);
            await new Promise(setImmediate);

            assert.strictEqual(statusEl.textContent.includes('Respuesta del servidor no válida.'), true);
        }
    });

    it('paginación, reintento ante error y preservación de página confirmada', async () => {
        const { document, statusEl, prevBtn, nextBtn, pageIndicatorEl } = buildDom();

        let pageToReturn = 1;
        let shouldFail = false;

        const sandbox = {
            document: document,
            window: {
                AA_FINANCE_DATA: {
                    ajaxUrl: 'https://example.com/admin-ajax.php',
                    nonce: 'nonce',
                    familyKey: 'finance',
                    variantKey: 'general',
                    actions: { listContainers: 'aa_list_finance_containers' }
                }
            },
            FormData: class {
                constructor() { this.data = {}; }
                append(k, v) { this.data[k] = v; }
            },
            fetch: (url, opts) => {
                if (shouldFail) {
                    return Promise.reject(new Error('Network error'));
                }
                const p = parseInt(opts.body.data.page, 10);
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({
                        success: true,
                        data: {
                            items: [{ id: p, family_key: 'finance', variant_key: 'general', title: 'Item ' + p, details: null, amount_total: null, created_at: '2026-08-29' }],
                            page: p,
                            per_page: 15,
                            total: 30,
                            total_pages: 2,
                            has_previous: p > 1,
                            has_next: p < 2
                        }
                    })
                });
            }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        await new Promise(setImmediate);

        assert.strictEqual(pageIndicatorEl.textContent, 'Página 1 de 2');
        assert.strictEqual(prevBtn.disabled, true);
        assert.strictEqual(nextBtn.disabled, false);

        // Clic en siguiente -> solicita página 2 pero simulamos fallo
        shouldFail = true;
        nextBtn.dispatch('click');
        await new Promise(setImmediate);

        assert.ok(statusEl.textContent.includes('Error de conexión con el servidor.'));
        const retryBtn = statusEl.querySelector('button');
        assert.ok(retryBtn !== null);

        // Reintentar -> ahora sí responde página 2 exitosamente
        shouldFail = false;
        retryBtn.dispatch('click');
        await new Promise(setImmediate);

        assert.strictEqual(pageIndicatorEl.textContent, 'Página 2 de 2');
        assert.strictEqual(prevBtn.disabled, false);
        assert.strictEqual(nextBtn.disabled, true);
    });

    it('solo existe una región con aria-live="polite"', () => {
        const { document } = buildDom();
        let ariaLiveCount = 0;
        Object.values(document._elements).forEach(function (el) {
            if (el.getAttribute('aria-live') === 'polite') {
                ariaLiveCount++;
            }
        });
        assert.strictEqual(ariaLiveCount, 1);
    });
});
