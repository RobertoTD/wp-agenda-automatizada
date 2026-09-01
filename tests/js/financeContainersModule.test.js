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

const hostSetTimeout = setTimeout.bind(globalThis);
const hostClearTimeout = clearTimeout.bind(globalThis);
const hostSetImmediate = setImmediate;
const hostAbortController = globalThis.AbortController;
const hostAbortSignal = globalThis.AbortSignal;

const EMPTY_LIST_DATA = {
    items: [],
    page: 1,
    per_page: 15,
    total: 0,
    total_pages: 0,
    has_previous: false,
    has_next: false
};

const AUTHORITATIVE_LIST_ITEM = {
    id: 7,
    family_key: 'finance',
    variant_key: 'general',
    title: 'Desde Listado',
    details: null,
    amount_total: null,
    created_at: '2026-08-31 10:00:00'
};

const AUTHORITATIVE_LIST_DATA = {
    items: [AUTHORITATIVE_LIST_ITEM],
    page: 1,
    per_page: 15,
    total: 1,
    total_pages: 1,
    has_previous: false,
    has_next: false
};

const CREATE_RESPONSE_CONTAINER = {
    id: 99,
    family_key: 'finance',
    variant_key: 'general',
    title: 'Desde Create',
    details: 'Payload de creación',
    created_at: '2026-08-31 12:00:00'
};

const DEFAULT_FINANCE_DATA = {
    ajaxUrl: 'https://example.com/ajax',
    nonce: 'test_nonce',
    familyKey: 'finance',
    variantKey: 'general',
    actions: {
        listContainers: 'aa_list_finance_containers',
        createContainer: 'aa_create_finance_container'
    }
};

function createDeferred() {
    const deferred = {
        settled: false,
        promise: null,
        resolve: null,
        reject: null
    };
    deferred.promise = new Promise(function (resolve, reject) {
        deferred.resolve = function (value) {
            if (deferred.settled) {
                return;
            }
            deferred.settled = true;
            resolve(value);
        };
        deferred.reject = function (reason) {
            if (deferred.settled) {
                return;
            }
            deferred.settled = true;
            reject(reason);
        };
    });
    return deferred;
}

function settleDeferred(deferred, value) {
    if (deferred && !deferred.settled) {
        deferred.resolve(value);
    }
}

function rejectDeferred(deferred, reason) {
    if (deferred && !deferred.settled) {
        deferred.reject(reason);
    }
}

function createTimerController() {
    const capturedCreateTimeouts = new Map();
    let nextCapturedId = 1;

    return {
        setTimeout: function (fn, delay) {
            if (delay === 15000) {
                const id = nextCapturedId++;
                capturedCreateTimeouts.set(id, fn);
                return id;
            }
            return hostSetTimeout(fn, delay);
        },
        clearTimeout: function (id) {
            if (capturedCreateTimeouts.has(id)) {
                capturedCreateTimeouts.delete(id);
                return;
            }
            hostClearTimeout(id);
        },
        flushCreateTimeout: function (id) {
            const fn = capturedCreateTimeouts.get(id);
            if (!fn) {
                return false;
            }
            capturedCreateTimeouts.delete(id);
            fn();
            return true;
        },
        flushAllCreateTimeouts: function () {
            const ids = Array.from(capturedCreateTimeouts.keys());
            ids.forEach(function (id) {
                this.flushCreateTimeout(id);
            }, this);
        },
        getCapturedCreateTimeoutIds: function () {
            return Array.from(capturedCreateTimeouts.keys());
        }
    };
}

function listFetchResponse(data) {
    return {
        ok: true,
        status: 200,
        json: function () {
            return Promise.resolve({
                success: true,
                data: data || EMPTY_LIST_DATA
            });
        }
    };
}

function createFetchResponse(payload) {
    return {
        ok: payload.ok,
        status: payload.status,
        json: function () {
            return Promise.resolve(payload.body);
        }
    };
}

function isCreateFetch(opts) {
    return opts && opts.body && opts.body.data
        && opts.body.data.action === 'aa_create_finance_container';
}

function isListFetch(opts) {
    return opts && opts.body && opts.body.data
        && opts.body.data.action === 'aa_list_finance_containers';
}

function createFinanceFetchHandler(options) {
    const state = {
        listDeferred: options.listDeferred || null,
        createDeferred: options.createDeferred || null,
        listShouldFail: options.listShouldFail || function () { return false; },
        listCallCount: 0,
        createFetchCalls: 0,
        lastCreatePayload: null,
        immediateList: options.immediateList !== false,
        immediateCreate: options.immediateCreate || null
    };

    function fetch(url, opts) {
        if (isCreateFetch(opts)) {
            state.createFetchCalls++;
            state.lastCreatePayload = opts.body.data;
            if (state.createDeferred) {
                return state.createDeferred.promise;
            }
            if (state.immediateCreate) {
                return Promise.resolve(state.immediateCreate(opts));
            }
            return Promise.resolve(createFetchResponse({
                ok: true,
                status: 200,
                body: { success: true, data: { container: options.createContainer || {} } }
            }));
        }

        if (isListFetch(opts)) {
            state.listCallCount++;
            if (state.listShouldFail()) {
                return Promise.reject(new Error('Network drop'));
            }
            if (state.listDeferred) {
                return state.listDeferred.promise;
            }
            if (state.immediateList) {
                return Promise.resolve(listFetchResponse(options.listData));
            }
        }

        return Promise.resolve(listFetchResponse(options.listData));
    }

    return {
        fetch: fetch,
        state: state,
        settleList: function (data) {
            if (state.listDeferred) {
                settleDeferred(state.listDeferred, listFetchResponse(data));
            }
        },
        rejectList: function (reason) {
            if (state.listDeferred) {
                rejectDeferred(state.listDeferred, reason || new Error('Network drop'));
            }
        },
        settleCreate: function (payload) {
            if (state.createDeferred) {
                settleDeferred(state.createDeferred, createFetchResponse(payload));
            }
        },
        releaseCreate: function () {
            if (!state.createDeferred || state.createDeferred.settled) {
                return;
            }
            state.createDeferred.promise.catch(function () {});
            const err = new Error('Aborted');
            err.name = 'AbortError';
            rejectDeferred(state.createDeferred, err);
        }
    };
}

function buildTestFormData() {
    return class {
        constructor() {
            this.data = {};
        }
        append(k, v) {
            this.data[k] = v;
        }
    };
}

function buildSandbox(document, options) {
    const timerCtrl = createTimerController();
    const listDeferred = options.listDeferred
        || (options.immediateList === false ? createDeferred() : null);
    const createDeferredRef = options.createDeferred === false
        ? null
        : (options.createDeferred || null);
    const fetchHandler = createFinanceFetchHandler({
        listDeferred: listDeferred,
        createDeferred: createDeferredRef,
        listShouldFail: options.listShouldFail,
        immediateList: options.immediateList,
        immediateCreate: options.immediateCreate,
        listData: options.listData,
        createContainer: options.createContainer
    });

    const sandbox = {
        document: document,
        window: {
            AA_FINANCE_DATA: options.financeData || DEFAULT_FINANCE_DATA
        },
        FormData: options.FormData || buildTestFormData(),
        setTimeout: timerCtrl.setTimeout.bind(timerCtrl),
        clearTimeout: timerCtrl.clearTimeout.bind(timerCtrl),
        fetch: fetchHandler.fetch,
        AbortController: hostAbortController,
        AbortSignal: hostAbortSignal
    };

    function cleanup() {
        timerCtrl.flushAllCreateTimeouts();
        if (listDeferred) {
            fetchHandler.settleList(options.listData);
        }
        fetchHandler.releaseCreate();
    }

    return {
        sandbox: sandbox,
        timerCtrl: timerCtrl,
        fetchHandler: fetchHandler,
        listDeferred: listDeferred,
        createDeferred: createDeferredRef,
        cleanup: cleanup
    };
}

async function flushMicrotasks() {
    await new Promise(hostSetImmediate);
}

async function waitForHostDelay(ms) {
    await new Promise(function (resolve) {
        hostSetTimeout(resolve, ms);
    });
}

async function bootModule(sandbox) {
    vm.runInNewContext(moduleSrc, sandbox);
    await flushMicrotasks();
}

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(),
        id: id || '',
        children: [],
        attributes: Object.create(null),
        dataset: Object.create(null),
        _text: '',
        _html: '',
        _value: '',
        disabled: false,
        hidden: false,
        readOnly: false,
        type: tag === 'button' ? 'button' : (tag === 'textarea' ? 'textarea' : 'text'),
        parentElement: null,
        parentNode: null,
        tabIndex: 0,
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
        focus: function () {
            if (this.ownerDocument) {
                this.ownerDocument.activeElement = this;
            }
        },
        setAttribute: function (name, value) {
            this.attributes[name] = String(value);
            if (name === 'id') {
                this.id = String(value);
            }
            if (name === 'hidden') {
                this.hidden = true;
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
            if (name === 'hidden') {
                this.hidden = false;
            }
        },
        appendChild: function (child) {
            child.parentElement = this;
            child.parentNode = this;
            child.ownerDocument = this.ownerDocument;
            this.children.push(child);
            return child;
        },
        removeChild: function (child) {
            this.children = this.children.filter(function (c) { return c !== child; });
            child.parentElement = null;
            child.parentNode = null;
            return child;
        },
        addEventListener: function (type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        dispatch: function (type, event) {
            const list = this._listeners[type] || [];
            const ev = Object.assign({
                type: type,
                key: (event && event.key) || '',
                shiftKey: (event && event.shiftKey) || false,
                _defaultPrevented: false,
                preventDefault: function () {
                    this._defaultPrevented = true;
                }
            }, event || {});
            list.forEach(function (handler) {
                handler(ev);
            });
            return ev;
        },
        querySelector: function (selector) {
            return findMatch(this, selector);
        },
        querySelectorAll: function (selector) {
            const out = [];
            collectMatches(this, selector, out);
            return out;
        },
        contains: function (other) {
            if (!other) return false;
            if (other === this) return true;
            let p = other.parentElement;
            while (p) {
                if (p === this) return true;
                p = p.parentElement;
            }
            return false;
        }
    };

    Object.defineProperty(el, 'value', {
        get: function () { return this._value; },
        set: function (v) { this._value = String(v); }
    });

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
                return this.children.map(function (c) { return c.textContent; }).join('');
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

function matchesSimpleSelector(el, selector) {
    if (!el) return false;
    if (selector.charAt(0) === '#') return el.id === selector.slice(1);
    if (selector.charAt(0) === '.') return el.classList.contains(selector.slice(1));
    return el.tagName === selector.toUpperCase();
}

function elementMatchesSelector(el, selector) {
    if (!el || !selector) return false;

    const parts = selector.split(',').map(function (part) {
        return part.trim();
    });

    for (let i = 0; i < parts.length; i++) {
        const part = parts[i];
        const notDisabledMatch = part.match(/^(button|input|textarea):not\(\[disabled\]\)$/);
        if (notDisabledMatch) {
            if (el.tagName === notDisabledMatch[1].toUpperCase() && !el.disabled) {
                return true;
            }
            continue;
        }
        if (part === '[tabindex]:not([tabindex="-1"])') {
            const tabIndex = el.getAttribute('tabindex');
            if (tabIndex !== null && tabIndex !== '-1') {
                return true;
            }
            continue;
        }
        if (matchesSimpleSelector(el, part)) {
            return true;
        }
    }

    return false;
}

function matchesSelector(el, selector) {
    if (!el || !selector) return false;
    if (selector.indexOf(',') !== -1 || selector.indexOf(':not(') !== -1) {
        return elementMatchesSelector(el, selector);
    }
    return matchesSimpleSelector(el, selector);
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
    if (matchesSelector(root, selector) && out.indexOf(root) === -1) {
        out.push(root);
    }
    root.children.forEach(function (child) {
        collectMatches(child, selector, out);
    });
}

function isElementVisible(el) {
    if (!el || el.hidden || el.getAttribute('aria-hidden') === 'true' || el.tabIndex === -1) {
        return false;
    }
    let current = el;
    while (current && current.parentElement) {
        if (current.hidden || (current.classList && current.classList.contains('hidden'))
            || current.getAttribute('aria-hidden') === 'true') {
            return false;
        }
        current = current.parentElement;
    }
    return true;
}

function getVisibleFocusableElements(container) {
    if (!container) return [];
    const raw = container.querySelectorAll(
        'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    return raw.filter(function (el) {
        return isElementVisible(el);
    });
}

function advanceTabFocus(document, createModal, shiftKey) {
    const focusables = getVisibleFocusableElements(createModal);
    if (focusables.length === 0) {
        return;
    }
    const current = document.activeElement;
    let index = focusables.indexOf(current);
    if (index === -1) {
        focusables[0].focus();
        return;
    }
    if (shiftKey) {
        index = index === 0 ? focusables.length - 1 : index - 1;
    } else {
        index = index === focusables.length - 1 ? 0 : index + 1;
    }
    focusables[index].focus();
}

function buildDom() {
    const document = {
        _elements: Object.create(null),
        activeElement: null,
        body: null,
        createElement: function (tag) {
            const el = createEl(tag);
            el.ownerDocument = this;
            return el;
        },
        createElementNS: function (ns, tag) {
            const el = createEl(tag);
            el.ownerDocument = this;
            return el;
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
        },
        _listeners: Object.create(null),
        addEventListener: function (type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        dispatch: function (type, event) {
            const list = this._listeners[type] || [];
            const ev = Object.assign({
                type: type,
                key: (event && event.key) || '',
                shiftKey: (event && event.shiftKey) || false,
                _defaultPrevented: false,
                preventDefault: function () {
                    this._defaultPrevented = true;
                }
            }, event || {});
            list.forEach(function (handler) {
                handler(ev);
            });
            if (type === 'keydown' && ev.key === 'Tab' && !ev._defaultPrevented) {
                const createModal = this.getElementById('aa-finance-create-modal');
                if (createModal && !createModal.classList.contains('hidden')
                    && createModal.contains(this.activeElement)) {
                    advanceTabFocus(this, createModal, ev.shiftKey);
                }
            }
            return ev;
        }
    };

    const body = createEl('body');
    body.ownerDocument = document;
    document.body = body;

    const root = createEl('div', 'aa-finance-root');
    root.ownerDocument = document;
    body.appendChild(root);

    const statusEl = createEl('div', 'aa-finance-status');
    statusEl.setAttribute('aria-live', 'polite');
    statusEl.setAttribute('tabindex', '-1');
    statusEl.tabIndex = -1;
    const gridEl = createEl('div', 'aa-finance-grid');
    const paginationEl = createEl('div', 'aa-finance-pagination');
    const prevBtn = createEl('button', 'aa-finance-prev');
    const nextBtn = createEl('button', 'aa-finance-next');
    const pageIndicatorEl = createEl('span', 'aa-finance-page-indicator');

    const openCreateBtn = createEl('button', 'aa-finance-open-create-btn');
    const createModal = createEl('div', 'aa-finance-create-modal');
    createModal.classList.add('hidden');
    const modalBackdrop = createEl('div', 'aa-finance-modal-backdrop');
    const modalCloseBtn = createEl('button', 'aa-finance-modal-close-btn');
    const createForm = createEl('form', 'aa-finance-create-form');
    const modalErrorEl = createEl('div', 'aa-finance-modal-error');
    modalErrorEl.classList.add('hidden');

    const titleInput = createEl('input', 'aa-finance-create-title');
    const titleErrorEl = createEl('p', 'aa-finance-title-error');
    titleErrorEl.classList.add('hidden');

    const detailsInput = createEl('textarea', 'aa-finance-create-details');
    const detailsErrorEl = createEl('p', 'aa-finance-details-error');
    detailsErrorEl.classList.add('hidden');

    const standardActionsEl = createEl('div', 'aa-finance-modal-actions-standard');
    const cancelBtn = createEl('button', 'aa-finance-modal-cancel-btn');
    const submitBtn = createEl('button', 'aa-finance-modal-submit-btn');
    submitBtn.type = 'submit';

    const hiddenActionsEl = createEl('div', 'aa-finance-modal-actions-hidden-trap');
    hiddenActionsEl.classList.add('hidden');
    const hiddenTrapBtn = createEl('button', 'aa-finance-modal-hidden-trap-btn');
    hiddenActionsEl.appendChild(hiddenTrapBtn);

    standardActionsEl.appendChild(cancelBtn);
    standardActionsEl.appendChild(submitBtn);

    const uncertainActionsEl = createEl('div', 'aa-finance-modal-actions-uncertain');
    uncertainActionsEl.classList.add('hidden');
    const uncertainCloseBtn = createEl('button', 'aa-finance-modal-uncertain-close-btn');
    uncertainActionsEl.appendChild(uncertainCloseBtn);

    const blockedActionsEl = createEl('div', 'aa-finance-modal-actions-blocked');
    blockedActionsEl.classList.add('hidden');
    const blockedCloseBtn = createEl('button', 'aa-finance-modal-blocked-close-btn');
    blockedActionsEl.appendChild(blockedCloseBtn);

    createForm.appendChild(modalErrorEl);
    createForm.appendChild(titleInput);
    createForm.appendChild(titleErrorEl);
    createForm.appendChild(detailsInput);
    createForm.appendChild(detailsErrorEl);
    createForm.appendChild(standardActionsEl);
    createForm.appendChild(hiddenActionsEl);
    createForm.appendChild(uncertainActionsEl);
    createForm.appendChild(blockedActionsEl);

    createModal.appendChild(modalBackdrop);
    createModal.appendChild(modalCloseBtn);
    createModal.appendChild(createForm);

    paginationEl.appendChild(prevBtn);
    paginationEl.appendChild(pageIndicatorEl);
    paginationEl.appendChild(nextBtn);

    root.appendChild(openCreateBtn);
    root.appendChild(statusEl);
    root.appendChild(paginationEl);
    root.appendChild(gridEl);
    root.appendChild(createModal);

    const allNamed = [
        root, statusEl, gridEl, paginationEl, prevBtn, nextBtn, pageIndicatorEl,
        openCreateBtn, createModal, modalBackdrop, modalCloseBtn, createForm, modalErrorEl,
        titleInput, titleErrorEl, detailsInput, detailsErrorEl,
        standardActionsEl, cancelBtn, submitBtn, hiddenActionsEl, hiddenTrapBtn,
        uncertainActionsEl, uncertainCloseBtn,
        blockedActionsEl, blockedCloseBtn
    ];

    allNamed.forEach(function (el) {
        el.ownerDocument = document;
        if (el.id) {
            document._elements[el.id] = el;
        }
    });

    return {
        document, root, statusEl, gridEl, paginationEl, prevBtn, nextBtn, pageIndicatorEl,
        openCreateBtn, createModal, modalBackdrop, modalCloseBtn, createForm, modalErrorEl,
        titleInput, titleErrorEl, detailsInput, detailsErrorEl,
        standardActionsEl, cancelBtn, submitBtn, hiddenActionsEl, hiddenTrapBtn,
        uncertainActionsEl, uncertainCloseBtn,
        blockedActionsEl, blockedCloseBtn
    };
}

describe('FinanceContainersModule (Ciclos 3D1 y 3D2)', () => {

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
            fetch: function () {
                fetchCalled = true;
                return Promise.resolve();
            }
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
                    ajaxUrl: '',
                    nonce: 'nonce',
                    familyKey: 'finance',
                    variantKey: 'general',
                    actions: { listContainers: 'aa_list_finance_containers' }
                }
            },
            fetch: function () {
                fetchCalled = true;
                return Promise.resolve();
            }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        assert.strictEqual(fetchCalled, false);
        assert.strictEqual(statusEl.textContent, 'No se pudo iniciar el módulo de Finanzas.');
    });

    it('no lee ni modifica window.ajaxurl', async () => {
        const { document } = buildDom();
        const pendingList = createDeferred();
        const harness = buildSandbox(document, {
            immediateList: false,
            listDeferred: pendingList,
            financeData: {
                ajaxUrl: 'https://example.com/admin-ajax.php',
                nonce: 'safe_nonce',
                familyKey: 'finance',
                variantKey: 'general',
                actions: { listContainers: 'aa_list_finance_containers' }
            }
        });

        try {
            vm.runInNewContext(moduleSrc, harness.sandbox);
            assert.strictEqual(harness.sandbox.window.ajaxurl, undefined);
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
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
            FormData: buildTestFormData(),
            AbortController: hostAbortController,
            fetch: function (url, options) {
                requestedUrl = url;
                requestedOptions = options;
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: function () {
                        return Promise.resolve({
                            success: true,
                            data: EMPTY_LIST_DATA
                        });
                    }
                });
            }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        await flushMicrotasks();

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
            fetch: function () {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: function () {
                        return Promise.resolve({
                            success: true,
                            data: EMPTY_LIST_DATA
                        });
                    }
                });
            }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        await flushMicrotasks();

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
            fetch: function () {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: function () {
                        return Promise.resolve({
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
                        });
                    }
                });
            }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        await flushMicrotasks();

        assert.strictEqual(gridEl.children.length, 4);

        const card1 = gridEl.children[0];
        assert.ok(card1.textContent.includes('<img src=x onerror=alert(1)> Título 1'));
        assert.ok(card1.textContent.includes('Detalles con <script>alert(2)</script>'));
        assert.ok(card1.textContent.includes('Sin importes'));
        assert.ok(card1.textContent.includes('29/08/2026'));

        const card2 = gridEl.children[1];
        assert.ok(card2.textContent.includes('0.00'));

        const card3 = gridEl.children[2];
        const card4 = gridEl.children[3];
        assert.ok(card3.textContent.includes('150.85'));
        assert.ok(card4.textContent.includes('-45.00'));

        const badge3 = card3.querySelector('.font-mono');
        const badge4 = card4.querySelector('.font-mono');
        assert.strictEqual(badge3.className, badge4.className);
    });

    it('rechaza fail-closed payloads inválidos (tipos no enteros, per_page != 15, variant mismatch)', async () => {
        const { document, statusEl } = buildDom();

        const invalidPayloadCases = [
            { per_page: 10 },
            { page: 1.5 },
            { total: -1 },
            { total_pages: NaN },
            { items: [{ id: 1, family_key: 'other_family', variant_key: 'general', title: 'T', details: null, amount_total: null, created_at: '2026-08-29' }] },
            { items: [{ id: 1.5, family_key: 'finance', variant_key: 'general', title: 'T', details: null, amount_total: null, created_at: '2026-08-29' }] }
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
                fetch: function () {
                    return Promise.resolve({
                        ok: true,
                        status: 200,
                        json: function () {
                            return Promise.resolve({ success: true, data: data });
                        }
                    });
                }
            };

            vm.runInNewContext(moduleSrc, sandbox);
            await flushMicrotasks();

            assert.strictEqual(statusEl.textContent.includes('Respuesta del servidor no válida.'), true);
        }
    });

    it('paginación, reintento ante error y preservación de página confirmada', async () => {
        const { document, statusEl, prevBtn, nextBtn, pageIndicatorEl } = buildDom();

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
            FormData: buildTestFormData(),
            fetch: function (url, opts) {
                if (shouldFail) {
                    return Promise.reject(new Error('Network error'));
                }
                const p = parseInt(opts.body.data.page, 10);
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: function () {
                        return Promise.resolve({
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
                        });
                    }
                });
            }
        };

        vm.runInNewContext(moduleSrc, sandbox);
        await flushMicrotasks();

        assert.strictEqual(pageIndicatorEl.textContent, 'Página 1 de 2');
        assert.strictEqual(prevBtn.disabled, true);
        assert.strictEqual(nextBtn.disabled, false);

        shouldFail = true;
        nextBtn.dispatch('click');
        await flushMicrotasks();

        assert.ok(statusEl.textContent.includes('Error de conexión con el servidor.'));
        const retryBtn = statusEl.querySelector('button');
        assert.ok(retryBtn !== null);

        shouldFail = false;
        retryBtn.dispatch('click');
        await flushMicrotasks();

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

    it('degrada ocultando trigger si createContainer no está publicado', async () => {
        const { document, openCreateBtn } = buildDom();
        const harness = buildSandbox(document, {
            financeData: {
                ajaxUrl: DEFAULT_FINANCE_DATA.ajaxUrl,
                nonce: DEFAULT_FINANCE_DATA.nonce,
                familyKey: DEFAULT_FINANCE_DATA.familyKey,
                variantKey: DEFAULT_FINANCE_DATA.variantKey,
                actions: { listContainers: DEFAULT_FINANCE_DATA.actions.listContainers }
            },
            immediateList: true
        });

        try {
            await bootModule(harness.sandbox);
            assert.strictEqual(openCreateBtn.hidden, true);
            assert.strictEqual(harness.fetchHandler.state.listCallCount, 1);
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
    });

    it('abre modal, enfoca título, valida pre-submit y limpia errores en input', async () => {
        const {
            document, openCreateBtn, createModal, titleInput, titleErrorEl, createForm
        } = buildDom();
        const harness = buildSandbox(document, { immediateList: true });

        try {
            await bootModule(harness.sandbox);

            openCreateBtn.dispatch('click');
            await waitForHostDelay(60);
            assert.strictEqual(createModal.classList.contains('hidden'), false);
            assert.strictEqual(document.activeElement, titleInput);

            titleInput.value = '   ';
            createForm.dispatch('submit');
            assert.strictEqual(titleErrorEl.classList.contains('hidden'), false);
            assert.strictEqual(titleErrorEl.textContent, 'El título no puede estar vacío.');
            assert.strictEqual(titleInput.getAttribute('aria-invalid'), 'true');
            assert.strictEqual(titleInput.getAttribute('aria-describedby'), 'aa-finance-title-error');

            titleInput.value = 'Mi Lista';
            titleInput.dispatch('input');
            assert.strictEqual(titleErrorEl.classList.contains('hidden'), true);
            assert.strictEqual(titleInput.getAttribute('aria-invalid'), null);
            assert.strictEqual(titleInput.getAttribute('aria-describedby'), null);
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
    });

    it('focus trap excluye controles dentro de ancestros .hidden y disabled en EDITING', async () => {
        const {
            document, openCreateBtn, submitBtn, modalCloseBtn, cancelBtn
        } = buildDom();
        cancelBtn.disabled = true;
        const harness = buildSandbox(document, { immediateList: true });

        try {
            await bootModule(harness.sandbox);

            openCreateBtn.dispatch('click');
            await waitForHostDelay(60);

            submitBtn.focus();
            document.dispatch('keydown', { key: 'Tab', shiftKey: false });
            assert.strictEqual(document.activeElement, modalCloseBtn);

            modalCloseBtn.focus();
            document.dispatch('keydown', { key: 'Tab', shiftKey: true });
            assert.strictEqual(document.activeElement, submitBtn);
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
    });

    it('captura snapshot inmutable, bloquea campos con readOnly y separa AbortControllers', async () => {
        const {
            document, openCreateBtn, titleInput, detailsInput, createForm, submitBtn, modalCloseBtn
        } = buildDom();
        const pendingCreate = createDeferred();
        const harness = buildSandbox(document, {
            immediateList: true,
            financeData: Object.assign({}, DEFAULT_FINANCE_DATA, { nonce: 'safe_nonce_123' }),
            createDeferred: pendingCreate
        });

        try {
            await bootModule(harness.sandbox);
            openCreateBtn.dispatch('click');
            await waitForHostDelay(60);
            titleInput.value = 'Lista Original';
            detailsInput.value = 'Detalles Originales';
            createForm.dispatch('submit');
            await flushMicrotasks();

            titleInput.value = 'Modificación Maliciosa';
            detailsInput.value = 'Detalles Modificados';

            assert.strictEqual(titleInput.readOnly, true);
            assert.strictEqual(detailsInput.readOnly, true);
            assert.strictEqual(submitBtn.textContent, 'Creando…');
            assert.strictEqual(submitBtn.disabled, true);
            assert.strictEqual(modalCloseBtn.disabled, true);
            assert.strictEqual(harness.fetchHandler.state.createFetchCalls, 1);

            titleInput.focus();
            document.dispatch('keydown', { key: 'Tab', shiftKey: false });
            assert.strictEqual(document.activeElement, detailsInput);

            createForm.dispatch('submit');
            assert.strictEqual(harness.fetchHandler.state.createFetchCalls, 1);

            const capturedTimeoutIds = harness.timerCtrl.getCapturedCreateTimeoutIds();
            assert.strictEqual(capturedTimeoutIds.length, 1);

            const submittedPayload = harness.fetchHandler.state.lastCreatePayload;
            assert.strictEqual(submittedPayload.title, 'Lista Original');
            assert.strictEqual(submittedPayload.details, 'Detalles Originales');
            assert.strictEqual(submittedPayload._wpnonce, 'safe_nonce_123');
            assert.strictEqual(submittedPayload.variant_key, 'general');
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
    });

    it('rechazo corregible reactiva submit, retira readOnly y enfoca el campo', async () => {
        const {
            document, openCreateBtn, titleInput, createForm, titleErrorEl, submitBtn
        } = buildDom();
        const harness = buildSandbox(document, {
            immediateList: true,
            immediateCreate: function () {
                return createFetchResponse({
                    ok: false,
                    status: 400,
                    body: {
                        success: false,
                        data: {
                            code: 'title_too_long',
                            message: 'El título no puede exceder los 200 caracteres.'
                        }
                    }
                });
            }
        });

        try {
            await bootModule(harness.sandbox);

            openCreateBtn.dispatch('click');
            await waitForHostDelay(60);
            titleInput.value = 'Título Demasiado Largo';
            createForm.dispatch('submit');
            await flushMicrotasks();

            assert.strictEqual(titleErrorEl.textContent, 'El título no puede exceder los 200 caracteres.');
            assert.strictEqual(titleInput.readOnly, false);
            assert.strictEqual(submitBtn.disabled, false);
            assert.strictEqual(submitBtn.textContent, 'Crear lista');
            assert.strictEqual(document.activeElement, titleInput);
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
    });

    it('rechazo bloqueante (bad_nonce / unknown_variant) no reactiva submit y muestra copy de recarga', async () => {
        const blockingCases = [
            { code: 'bad_nonce', status: 403, message: 'Nonce inválido.' },
            { code: 'unknown_variant', status: 404, message: 'Variante canónica "other" no encontrada.' }
        ];

        for (const blockingCase of blockingCases) {
            const {
                document, openCreateBtn, titleInput, createForm, modalErrorEl,
                standardActionsEl, blockedActionsEl, submitBtn, gridEl
            } = buildDom();
            const harness = buildSandbox(document, {
                immediateList: true,
                immediateCreate: function () {
                    return createFetchResponse({
                        ok: false,
                        status: blockingCase.status,
                        body: {
                            success: false,
                            data: {
                                code: blockingCase.code,
                                message: blockingCase.message
                            }
                        }
                    });
                }
            });

            try {
                await bootModule(harness.sandbox);

                openCreateBtn.dispatch('click');
                await waitForHostDelay(60);
                titleInput.value = 'Lista Bloqueada';
                createForm.dispatch('submit');
                await flushMicrotasks();

                assert.ok(
                    modalErrorEl.textContent.includes('La creación no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.'),
                    'copy de recarga ausente para ' + blockingCase.code
                );
                assert.strictEqual(standardActionsEl.classList.contains('hidden'), true);
                assert.strictEqual(blockedActionsEl.classList.contains('hidden'), false);
                assert.strictEqual(submitBtn.disabled, true);
                assert.strictEqual(harness.fetchHandler.state.createFetchCalls, 1);
                assert.strictEqual(gridEl.textContent.includes('Lista Bloqueada'), false);

                createForm.dispatch('submit');
                await flushMicrotasks();
                assert.strictEqual(harness.fetchHandler.state.createFetchCalls, 1);
            } finally {
                harness.cleanup();
                await flushMicrotasks();
            }
        }
    });

    it('estado incierto: timeout o 500 muestra Cerrar y revisar, bloquea trigger hasta List exitoso y conserva draft', async () => {
        const dom500 = buildDom();
        let listShouldFail = false;
        const harness500 = buildSandbox(dom500.document, {
            immediateList: false,
            listShouldFail: function () { return listShouldFail; },
            immediateCreate: function () {
                return createFetchResponse({
                    ok: false,
                    status: 500,
                    body: {
                        success: false,
                        data: { code: 'persistence_failed', message: 'Fallo BD' }
                    }
                });
            }
        });

        try {
            harness500.fetchHandler.settleList();
            await bootModule(harness500.sandbox);

            dom500.openCreateBtn.dispatch('click');
            await waitForHostDelay(60);
            dom500.titleInput.value = 'Mi Lista Incierta';
            dom500.detailsInput.value = 'Detalles Inciertos';
            dom500.createForm.dispatch('submit');
            await flushMicrotasks();

            assert.ok(dom500.modalErrorEl.textContent.includes('No pudimos confirmar si la lista se creó. Revisa el listado antes de intentarlo nuevamente.'));
            assert.strictEqual(dom500.uncertainActionsEl.classList.contains('hidden'), false);

            listShouldFail = true;
            dom500.uncertainCloseBtn.dispatch('click');
            await flushMicrotasks();

            assert.strictEqual(dom500.createModal.classList.contains('hidden'), true);
            assert.strictEqual(dom500.openCreateBtn.disabled, true);
            assert.strictEqual(dom500.document.activeElement, dom500.statusEl);
            assert.notStrictEqual(dom500.document.activeElement, dom500.uncertainCloseBtn);
            assert.notStrictEqual(dom500.document.activeElement, dom500.titleInput);
            assert.notStrictEqual(dom500.document.activeElement, dom500.openCreateBtn);

            listShouldFail = false;
            harness500.fetchHandler.settleList();
            const retryBtn = dom500.document.getElementById('aa-finance-status').querySelector('button');
            retryBtn.dispatch('click');
            await flushMicrotasks();

            assert.strictEqual(dom500.openCreateBtn.disabled, false);

            dom500.openCreateBtn.dispatch('click');
            await waitForHostDelay(60);
            assert.strictEqual(dom500.titleInput.value, 'Mi Lista Incierta');
            assert.strictEqual(dom500.detailsInput.value, 'Detalles Inciertos');
            assert.ok(dom500.modalErrorEl.textContent.includes('Antes de volver a crearla, verifica que la lista no aparezca ya en el listado.'));
        } finally {
            harness500.cleanup();
            await flushMicrotasks();
        }

        const domTimeout = buildDom();
        const pendingCreateTimeout = createDeferred();
        const harnessTimeout = buildSandbox(domTimeout.document, {
            immediateList: true,
            createDeferred: pendingCreateTimeout
        });

        try {
            await bootModule(harnessTimeout.sandbox);

            domTimeout.openCreateBtn.dispatch('click');
            await waitForHostDelay(60);
            domTimeout.titleInput.value = 'Lista por timeout';
            domTimeout.detailsInput.value = 'Detalles timeout';
            domTimeout.createForm.dispatch('submit');
            await flushMicrotasks();

            const capturedIds = harnessTimeout.timerCtrl.getCapturedCreateTimeoutIds();
            assert.strictEqual(capturedIds.length, 1);
            assert.strictEqual(harnessTimeout.timerCtrl.flushCreateTimeout(capturedIds[0]), true);
            await flushMicrotasks();

            assert.ok(domTimeout.modalErrorEl.textContent.includes('No pudimos confirmar si la lista se creó. Revisa el listado antes de intentarlo nuevamente.'));
            assert.strictEqual(domTimeout.uncertainActionsEl.classList.contains('hidden'), false);
        } finally {
            harnessTimeout.cleanup();
            await flushMicrotasks();
        }
    });

    it('éxito confirmado recarga página 1 sin insertar la card de creación', async () => {
        const {
            document, openCreateBtn, titleInput, detailsInput, createForm,
            createModal, submitBtn, gridEl
        } = buildDom();
        const pendingCreate = createDeferred();
        const harness = buildSandbox(document, {
            immediateList: true,
            listData: AUTHORITATIVE_LIST_DATA,
            createDeferred: pendingCreate
        });

        try {
            await bootModule(harness.sandbox);
            assert.strictEqual(harness.fetchHandler.state.listCallCount, 1);
            assert.ok(gridEl.textContent.includes('Desde Listado'));
            assert.strictEqual(gridEl.textContent.includes('Desde Create'), false);

            openCreateBtn.dispatch('click');
            await waitForHostDelay(60);
            titleInput.value = 'Desde Create';
            detailsInput.value = 'Payload de creación';
            createForm.dispatch('submit');
            await flushMicrotasks();

            assert.strictEqual(harness.fetchHandler.state.createFetchCalls, 1);
            assert.strictEqual(submitBtn.textContent, 'Creando…');
            assert.strictEqual(harness.timerCtrl.getCapturedCreateTimeoutIds().length, 1);

            const submittedPayload = harness.fetchHandler.state.lastCreatePayload;
            assert.strictEqual(submittedPayload.title, 'Desde Create');
            assert.strictEqual(submittedPayload.details, 'Payload de creación');
            assert.strictEqual(submittedPayload.variant_key, 'general');
            assert.strictEqual(submittedPayload.action, 'aa_create_finance_container');

            titleInput.value = 'Mutación posterior';
            detailsInput.value = 'No debe enviarse';

            harness.fetchHandler.settleCreate({
                ok: true,
                status: 200,
                body: {
                    success: true,
                    data: {
                        container: CREATE_RESPONSE_CONTAINER
                    }
                }
            });
            await flushMicrotasks();

            assert.strictEqual(createModal.classList.contains('hidden'), true);
            assert.strictEqual(titleInput.value, '');
            assert.strictEqual(detailsInput.value, '');
            assert.strictEqual(submitBtn.textContent, 'Creando…');
            assert.strictEqual(harness.fetchHandler.state.createFetchCalls, 1);
            assert.strictEqual(harness.fetchHandler.state.listCallCount, 2);
            assert.strictEqual(harness.timerCtrl.getCapturedCreateTimeoutIds().length, 0);
            assert.ok(gridEl.textContent.includes('Desde Listado'));
            assert.strictEqual(gridEl.textContent.includes('Desde Create'), false);
            assert.strictEqual(Number.isInteger(CREATE_RESPONSE_CONTAINER.id), true);
            assert.ok(CREATE_RESPONSE_CONTAINER.id >= 1);
            assert.strictEqual(CREATE_RESPONSE_CONTAINER.family_key, 'finance');
            assert.strictEqual(CREATE_RESPONSE_CONTAINER.variant_key, 'general');

            harness.fetchHandler.settleCreate({
                ok: true,
                status: 200,
                body: {
                    success: true,
                    data: {
                        container: Object.assign({}, CREATE_RESPONSE_CONTAINER, { id: 100, title: 'Segunda confirmación' })
                    }
                }
            });
            createForm.dispatch('submit');
            await flushMicrotasks();

            assert.strictEqual(harness.fetchHandler.state.createFetchCalls, 1);
            assert.strictEqual(harness.fetchHandler.state.listCallCount, 2);
            assert.strictEqual(createModal.classList.contains('hidden'), true);
            assert.strictEqual(gridEl.textContent.includes('Segunda confirmación'), false);
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
    });

    it('cancela el timer de foco al cerrar el modal antes de que dispare', async () => {
        const {
            document, openCreateBtn, cancelBtn, createModal, titleInput
        } = buildDom();
        const harness = buildSandbox(document, { immediateList: true });

        try {
            await bootModule(harness.sandbox);

            openCreateBtn.dispatch('click');
            assert.strictEqual(createModal.classList.contains('hidden'), false);

            cancelBtn.dispatch('click');
            assert.strictEqual(createModal.classList.contains('hidden'), true);
            assert.strictEqual(document.activeElement, openCreateBtn);

            await waitForHostDelay(60);

            assert.strictEqual(createModal.classList.contains('hidden'), true);
            assert.strictEqual(document.activeElement, openCreateBtn);
            assert.notStrictEqual(document.activeElement, titleInput);
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
    });

    it('cierre bloqueante por X y por Cerrar dejan el mismo estado IDLE', async () => {
        const closers = ['blockedCloseBtn', 'modalCloseBtn'];

        for (const closerName of closers) {
            const dom = buildDom();
            const harness = buildSandbox(dom.document, {
                immediateList: true,
                immediateCreate: function () {
                    return createFetchResponse({
                        ok: false,
                        status: 403,
                        body: {
                            success: false,
                            data: {
                                code: 'bad_nonce',
                                message: 'Nonce inválido.'
                            }
                        }
                    });
                }
            });

            try {
                await bootModule(harness.sandbox);

                dom.openCreateBtn.dispatch('click');
                await waitForHostDelay(60);
                dom.titleInput.value = 'Lista Bloqueada';
                dom.detailsInput.value = 'Detalles bloqueados';
                dom.createForm.dispatch('submit');
                await flushMicrotasks();

                assert.strictEqual(dom.submitBtn.disabled, true);
                assert.strictEqual(dom.blockedActionsEl.classList.contains('hidden'), false);
                assert.ok(dom.modalErrorEl.textContent.includes('La creación no está disponible con la sesión actual. Recarga la página antes de intentarlo nuevamente.'));

                const closer = closerName === 'blockedCloseBtn' ? dom.blockedCloseBtn : dom.modalCloseBtn;
                closer.dispatch('click');

                assert.strictEqual(dom.createModal.classList.contains('hidden'), true, 'modal abierto tras ' + closerName);
                assert.strictEqual(dom.titleInput.value, '', 'título residual tras ' + closerName);
                assert.strictEqual(dom.detailsInput.value, '', 'detalles residuales tras ' + closerName);
                assert.strictEqual(dom.document.activeElement, dom.openCreateBtn, 'foco no restaurado tras ' + closerName);
                assert.strictEqual(dom.submitBtn.disabled, true, 'submit reactivado antes de reabrir tras ' + closerName);

                dom.openCreateBtn.dispatch('click');
                await waitForHostDelay(60);

                assert.strictEqual(dom.createModal.classList.contains('hidden'), false);
                assert.strictEqual(dom.titleInput.value, '');
                assert.strictEqual(dom.detailsInput.value, '');
                assert.strictEqual(dom.standardActionsEl.classList.contains('hidden'), false);
                assert.strictEqual(dom.blockedActionsEl.classList.contains('hidden'), true);
                assert.strictEqual(dom.submitBtn.disabled, false);
                assert.strictEqual(dom.submitBtn.textContent, 'Crear lista');
                assert.strictEqual(dom.modalErrorEl.classList.contains('hidden'), true);
            } finally {
                harness.cleanup();
                await flushMicrotasks();
            }
        }
    });

    it('ignora un envelope de éxito tardío después del timeout de creación', async () => {
        const {
            document, openCreateBtn, titleInput, detailsInput, createForm,
            createModal, gridEl, uncertainActionsEl, modalErrorEl, submitBtn
        } = buildDom();
        const pendingCreate = createDeferred();
        const harness = buildSandbox(document, {
            immediateList: true,
            listData: AUTHORITATIVE_LIST_DATA,
            createDeferred: pendingCreate
        });

        try {
            await bootModule(harness.sandbox);
            const listCountAfterBoot = harness.fetchHandler.state.listCallCount;

            openCreateBtn.dispatch('click');
            await waitForHostDelay(60);
            titleInput.value = 'Lista Tardía';
            detailsInput.value = 'Detalles tardíos';
            createForm.dispatch('submit');
            await flushMicrotasks();

            const capturedIds = harness.timerCtrl.getCapturedCreateTimeoutIds();
            assert.strictEqual(capturedIds.length, 1);
            assert.strictEqual(harness.timerCtrl.flushCreateTimeout(capturedIds[0]), true);
            await flushMicrotasks();

            assert.ok(modalErrorEl.textContent.includes('No pudimos confirmar si la lista se creó. Revisa el listado antes de intentarlo nuevamente.'));
            assert.strictEqual(uncertainActionsEl.classList.contains('hidden'), false);
            assert.strictEqual(createModal.classList.contains('hidden'), false);
            assert.strictEqual(titleInput.value, 'Lista Tardía');
            assert.strictEqual(detailsInput.value, 'Detalles tardíos');
            assert.strictEqual(submitBtn.disabled, true);
            assert.strictEqual(harness.fetchHandler.state.listCallCount, listCountAfterBoot);
            assert.strictEqual(gridEl.textContent.includes('Lista Tardía'), false);

            harness.fetchHandler.settleCreate({
                ok: true,
                status: 200,
                body: {
                    success: true,
                    data: {
                        container: Object.assign({}, CREATE_RESPONSE_CONTAINER, {
                            title: 'Lista Tardía',
                            details: 'Detalles tardíos'
                        })
                    }
                }
            });
            await flushMicrotasks();

            assert.strictEqual(createModal.classList.contains('hidden'), false);
            assert.strictEqual(uncertainActionsEl.classList.contains('hidden'), false);
            assert.ok(modalErrorEl.textContent.includes('No pudimos confirmar si la lista se creó. Revisa el listado antes de intentarlo nuevamente.'));
            assert.strictEqual(titleInput.value, 'Lista Tardía');
            assert.strictEqual(detailsInput.value, 'Detalles tardíos');
            assert.strictEqual(harness.fetchHandler.state.createFetchCalls, 1);
            assert.strictEqual(harness.fetchHandler.state.listCallCount, listCountAfterBoot);
            assert.strictEqual(gridEl.textContent.includes('Lista Tardía'), false);
            assert.strictEqual(gridEl.textContent.includes('Desde Create'), false);
            assert.ok(gridEl.textContent.includes('Desde Listado'));
        } finally {
            harness.cleanup();
            await flushMicrotasks();
        }
    });
});
