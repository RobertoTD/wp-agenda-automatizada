'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const recordsModulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical/finance/finance-records-module.js'
);
const orchestratorModulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical/finance/finance-module.js'
);
const recordsModuleSrc = fs.readFileSync(recordsModulePath, 'utf8');
const orchestratorModuleSrc = fs.readFileSync(orchestratorModulePath, 'utf8');

const hostSetImmediate = setImmediate;
const hostSetTimeout = setTimeout.bind(globalThis);
const hostAbortController = globalThis.AbortController;
const hostAbortSignal = globalThis.AbortSignal;

const DEFAULT_FINANCE_DATA = {
    ajaxUrl: 'https://example.com/admin-ajax.php',
    nonce: 'test_nonce',
    familyKey: 'finance',
    variantKey: 'general',
    actions: {
        listContainers: 'aa_list_finance_containers',
        createContainer: 'aa_create_finance_container',
        listRecords: 'aa_list_finance_records'
    }
};

const SAMPLE_CONTAINER = {
    id: 7,
    family_key: 'finance',
    variant_key: 'general',
    title: 'Caja General',
    details: 'Detalle línea 1\nDetalle línea 2',
    created_at: '2026-08-31 10:00:00'
};

const EMPTY_LIST_DATA = {
    items: [],
    page: 1,
    per_page: 15,
    total: 0,
    total_pages: 0,
    has_previous: false,
    has_next: false
};

const AUTHORITATIVE_LIST_DATA = {
    items: [{
        id: 7,
        family_key: 'finance',
        variant_key: 'general',
        title: 'Caja General',
        details: null,
        amount_total: null,
        created_at: '2026-08-31 10:00:00'
    }],
    page: 1,
    per_page: 15,
    total: 1,
    total_pages: 1,
    has_previous: false,
    has_next: false
};

function createDeferred() {
    const deferred = { settled: false, promise: null, resolve: null, reject: null };
    deferred.promise = new Promise(function (resolve, reject) {
        deferred.resolve = function (value) {
            if (deferred.settled) return;
            deferred.settled = true;
            resolve(value);
        };
        deferred.reject = function (reason) {
            if (deferred.settled) return;
            deferred.settled = true;
            reject(reason);
        };
    });
    return deferred;
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

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(),
        id: id || '',
        children: [],
        attributes: Object.create(null),
        dataset: Object.create(null),
        _text: '',
        disabled: false,
        hidden: false,
        type: tag === 'button' ? 'button' : 'text',
        parentElement: null,
        parentNode: null,
        tabIndex: 0,
        style: {},
        _listeners: Object.create(null),
        classList: {
            _set: new Set(),
            add(c) { this._set.add(c); },
            remove(c) { this._set.delete(c); },
            contains(c) { return this._set.has(c); }
        },
        focus() {
            if (this.ownerDocument) this.ownerDocument.activeElement = this;
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
            if (name === 'id') this.id = String(value);
            if (name === 'hidden') this.hidden = true;
        },
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
        },
        removeAttribute(name) {
            delete this.attributes[name];
            if (name === 'hidden') this.hidden = false;
        },
        appendChild(child) {
            child.parentElement = this;
            child.parentNode = this;
            child.ownerDocument = this.ownerDocument;
            this.children.push(child);
            return child;
        },
        removeChild(child) {
            this.children = this.children.filter(function (c) { return c !== child; });
            child.parentElement = null;
            child.parentNode = null;
            return child;
        },
        addEventListener(type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        removeEventListener(type, handler) {
            const list = this._listeners[type] || [];
            this._listeners[type] = list.filter(function (fn) { return fn !== handler; });
        },
        dispatch(type, event) {
            (this._listeners[type] || []).forEach(function (handler) {
                handler(Object.assign({ type: type, preventDefault: function () {} }, event || {}));
            });
        },
        querySelector(selector) {
            return findMatch(this, selector);
        },
        querySelectorAll(selector) {
            const out = [];
            collectMatches(this, selector, out);
            return out;
        }
    };

    Object.defineProperty(el, 'className', {
        get() { return Array.from(el.classList._set).join(' '); },
        set(value) { el.classList._set = new Set(String(value).split(/\s+/).filter(Boolean)); }
    });
    Object.defineProperty(el, 'textContent', {
        get() {
            if (this.children.length > 0) {
                return this.children.map(function (c) { return c.textContent; }).join('');
            }
            return this._text;
        },
        set(value) {
            this._text = String(value);
            this.children = [];
        }
    });
    Object.defineProperty(el, 'firstChild', {
        get() { return this.children[0] || null; }
    });
    if (id) el.id = id;
    return el;
}

function matchesSelector(el, selector) {
    if (!el || !selector) return false;
    if (selector.charAt(0) === '#') return el.id === selector.slice(1);
    if (selector.charAt(0) === '.') return el.classList.contains(selector.slice(1));
    if (selector.indexOf(',') !== -1) {
        return selector.split(',').some(function (part) {
            return matchesSelector(el, part.trim());
        });
    }
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
    if (matchesSelector(root, selector) && out.indexOf(root) === -1) out.push(root);
    root.children.forEach(function (child) { collectMatches(child, selector, out); });
}

function buildRecordsDom() {
    const document = {
        _elements: Object.create(null),
        activeElement: null,
        body: null,
        createElement(tag) {
            const el = createEl(tag);
            el.ownerDocument = this;
            return el;
        },
        getElementById(id) {
            return this._elements[id] || null;
        },
        querySelectorAll(selector) {
            const out = [];
            Object.values(this._elements).forEach(function (el) {
                collectMatches(el, selector, out);
            });
            return out;
        },
        contains(node) {
            if (!node) return false;
            return !!this._elements[node.id];
        },
        _listeners: Object.create(null),
        addEventListener(type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        }
    };

    const body = createEl('body');
    body.ownerDocument = document;
    document.body = body;

    const root = createEl('div', 'aa-finance-root');
    body.appendChild(root);

    const listContainer = createEl('div', 'aa-finance-list-container');
    const statusEl = createEl('div', 'aa-finance-status');
    statusEl.setAttribute('aria-live', 'polite');
    statusEl.setAttribute('tabindex', '-1');
    const gridEl = createEl('div', 'aa-finance-grid');
    const paginationEl = createEl('div', 'aa-finance-pagination');
    paginationEl.hidden = true;
    const prevBtn = createEl('button', 'aa-finance-prev');
    const nextBtn = createEl('button', 'aa-finance-next');
    const pageIndicatorEl = createEl('span', 'aa-finance-page-indicator');
    paginationEl.appendChild(prevBtn);
    paginationEl.appendChild(pageIndicatorEl);
    paginationEl.appendChild(nextBtn);
    listContainer.appendChild(statusEl);
    listContainer.appendChild(paginationEl);
    listContainer.appendChild(gridEl);

    const recordsContainer = createEl('div', 'aa-finance-records-container');
    recordsContainer.classList.add('hidden');
    recordsContainer.hidden = true;
    const recordsBackBtn = createEl('button', 'aa-finance-records-back');
    const recordsHeadingEl = createEl('h3', 'aa-finance-records-heading');
    recordsHeadingEl.setAttribute('tabindex', '-1');
    const recordsSummaryEl = createEl('div', 'aa-finance-records-summary');
    const recordsStatusEl = createEl('div', 'aa-finance-records-status');
    recordsStatusEl.setAttribute('aria-live', 'polite');
    recordsStatusEl.setAttribute('tabindex', '-1');
    const recordsGridEl = createEl('div', 'aa-finance-records-grid');
    recordsGridEl.setAttribute('aria-busy', 'false');
    const recordsPaginationEl = createEl('div', 'aa-finance-records-pagination');
    recordsPaginationEl.hidden = true;
    const recordsPrevBtn = createEl('button', 'aa-finance-records-prev');
    const recordsNextBtn = createEl('button', 'aa-finance-records-next');
    const recordsPageIndicatorEl = createEl('span', 'aa-finance-records-page-indicator');
    recordsPaginationEl.appendChild(recordsPrevBtn);
    recordsPaginationEl.appendChild(recordsPageIndicatorEl);
    recordsPaginationEl.appendChild(recordsNextBtn);
    recordsContainer.appendChild(recordsBackBtn);
    recordsContainer.appendChild(recordsHeadingEl);
    recordsContainer.appendChild(recordsSummaryEl);
    recordsContainer.appendChild(recordsStatusEl);
    recordsContainer.appendChild(recordsPaginationEl);
    recordsContainer.appendChild(recordsGridEl);

    const openCreateBtn = createEl('button', 'aa-finance-open-create-btn');
    const createModal = createEl('div', 'aa-finance-create-modal');
    createModal.classList.add('hidden');
    const modalBackdrop = createEl('div', 'aa-finance-modal-backdrop');
    const modalCloseBtn = createEl('button', 'aa-finance-modal-close-btn');
    const createForm = createEl('form', 'aa-finance-create-form');
    const modalErrorEl = createEl('div', 'aa-finance-modal-error');
    const titleInput = createEl('input', 'aa-finance-create-title');
    const titleErrorEl = createEl('p', 'aa-finance-title-error');
    const detailsInput = createEl('textarea', 'aa-finance-create-details');
    const detailsErrorEl = createEl('p', 'aa-finance-details-error');
    const standardActionsEl = createEl('div', 'aa-finance-modal-actions-standard');
    const cancelBtn = createEl('button', 'aa-finance-modal-cancel-btn');
    const submitBtn = createEl('button', 'aa-finance-modal-submit-btn');
    const uncertainActionsEl = createEl('div', 'aa-finance-modal-actions-uncertain');
    const uncertainCloseBtn = createEl('button', 'aa-finance-modal-uncertain-close-btn');
    const blockedActionsEl = createEl('div', 'aa-finance-modal-actions-blocked');
    const blockedCloseBtn = createEl('button', 'aa-finance-modal-blocked-close-btn');

    standardActionsEl.appendChild(cancelBtn);
    standardActionsEl.appendChild(submitBtn);
    createForm.appendChild(modalErrorEl);
    createForm.appendChild(titleInput);
    createForm.appendChild(detailsInput);
    createForm.appendChild(standardActionsEl);
    createForm.appendChild(uncertainActionsEl);
    createForm.appendChild(blockedActionsEl);
    createModal.appendChild(modalBackdrop);
    createModal.appendChild(modalCloseBtn);
    createModal.appendChild(createForm);

    root.appendChild(openCreateBtn);
    root.appendChild(listContainer);
    root.appendChild(recordsContainer);
    root.appendChild(createModal);

    [
        root, listContainer, statusEl, gridEl, paginationEl, prevBtn, nextBtn, pageIndicatorEl,
        recordsContainer, recordsBackBtn, recordsHeadingEl, recordsSummaryEl, recordsStatusEl,
        recordsGridEl, recordsPaginationEl, recordsPrevBtn, recordsNextBtn, recordsPageIndicatorEl,
        openCreateBtn, createModal, modalBackdrop, modalCloseBtn, createForm, modalErrorEl,
        titleInput, titleErrorEl, detailsInput, detailsErrorEl,
        standardActionsEl, cancelBtn, submitBtn, uncertainActionsEl, uncertainCloseBtn,
        blockedActionsEl, blockedCloseBtn
    ].forEach(function (el) {
        el.ownerDocument = document;
        if (el.id) document._elements[el.id] = el;
    });

    return {
        document, root, listContainer, statusEl, gridEl, openCreateBtn,
        recordsContainer, recordsBackBtn, recordsHeadingEl, recordsSummaryEl,
        recordsStatusEl, recordsGridEl, recordsPaginationEl, recordsPrevBtn, recordsNextBtn
    };
}

async function flushMicrotasks() {
    await new Promise(hostSetImmediate);
}

function recordsEnvelope(overrides) {
    return Object.assign({
        container: Object.assign({}, SAMPLE_CONTAINER),
        items: [{
            id: 101,
            family_key: 'finance',
            variant_key: 'general',
            container_id: 7,
            title: 'Registro A',
            details: 'Detalle <script>alert(1)</script>',
            amount: '150.85',
            created_at: '2026-08-31 11:00:00'
        }],
        page: 1,
        per_page: 15,
        total: 1,
        total_pages: 1,
        has_previous: false,
        has_next: false,
        amount_total: '150.85'
    }, overrides || {});
}

function jsonResponse(payload) {
    return {
        ok: payload.ok !== false,
        status: payload.status || 200,
        json() {
            return Promise.resolve(payload.body);
        }
    };
}

function isListContainersFetch(opts) {
    return opts && opts.body && opts.body.data
        && opts.body.data.action === 'aa_list_finance_containers';
}

function isListRecordsFetch(opts) {
    return opts && opts.body && opts.body.data
        && opts.body.data.action === 'aa_list_finance_records';
}

function bootBothModules(document, options) {
    options = options || {};
    const fetchCalls = [];
    const recordsDeferred = options.recordsDeferred || null;
    const listDeferred = options.listDeferred || null;

    const sandbox = {
        document: document,
        window: {
            AA_FINANCE_DATA: options.financeData || DEFAULT_FINANCE_DATA
        },
        FormData: buildTestFormData(),
        setTimeout: hostSetTimeout,
        clearTimeout: clearTimeout,
        fetch: function (url, opts) {
            fetchCalls.push({ url: url, opts: opts });
            if (isListRecordsFetch(opts)) {
                if (recordsDeferred) return recordsDeferred.promise;
                if (options.recordsResponse) {
                    return Promise.resolve(options.recordsResponse(opts));
                }
            }
            if (isListContainersFetch(opts)) {
                if (listDeferred) return listDeferred.promise;
                return Promise.resolve(jsonResponse({
                    ok: true,
                    status: 200,
                    body: { success: true, data: options.listData || AUTHORITATIVE_LIST_DATA }
                }));
            }
            return Promise.resolve(jsonResponse({
                ok: true,
                status: 200,
                body: { success: true, data: options.listData || AUTHORITATIVE_LIST_DATA }
            }));
        },
        AbortController: hostAbortController,
        AbortSignal: hostAbortSignal
    };

    if (options.includeRecordsFactory !== false) {
        vm.runInNewContext(recordsModuleSrc, sandbox);
    }
    vm.runInNewContext(orchestratorModuleSrc, sandbox);

    return { sandbox, fetchCalls, recordsDeferred, listDeferred };
}

describe('FinanceRecordsModule (Ciclo 3D3A)', () => {

    it('expone factory AA_FinanceRecords.createController', () => {
        const { document } = buildRecordsDom();
        const sandbox = { document: document, window: {} };
        vm.runInNewContext(recordsModuleSrc, sandbox);
        assert.ok(sandbox.window.AA_FinanceRecords);
        assert.strictEqual(typeof sandbox.window.AA_FinanceRecords.createController, 'function');
    });

    it('degrada navegación a registros si falta factory AA_FinanceRecords', async () => {
        const dom = buildRecordsDom();
        const boot = bootBothModules(dom.document, { includeRecordsFactory: false });
        await flushMicrotasks();
        assert.strictEqual(dom.gridEl.querySelector('.aa-finance-open-records-btn'), null);
        assert.strictEqual(dom.openCreateBtn.hidden, false);
        boot.sandbox = null;
    });

    it('degrada navegación si falta action listRecords', async () => {
        const dom = buildRecordsDom();
        const data = JSON.parse(JSON.stringify(DEFAULT_FINANCE_DATA));
        delete data.actions.listRecords;
        const boot = bootBothModules(dom.document, { financeData: data });
        await flushMicrotasks();
        assert.strictEqual(dom.gridEl.querySelector('.aa-finance-open-records-btn'), null);
        boot.sandbox = null;
    });

    it('envía POST exacto con action, nonce, container_id, variant_key y page', async () => {
        const dom = buildRecordsDom();
        const boot = bootBothModules(dom.document, {
            recordsResponse: function () {
                return jsonResponse({
                    ok: true,
                    status: 200,
                    body: { success: true, data: recordsEnvelope() }
                });
            }
        });
        await flushMicrotasks();

        const openBtn = dom.gridEl.querySelector('.aa-finance-open-records-btn');
        assert.ok(openBtn);
        openBtn.dispatch('click');
        await flushMicrotasks();

        const recordsFetch = boot.fetchCalls.find(function (call) {
            return isListRecordsFetch(call.opts);
        });
        assert.ok(recordsFetch);
        assert.strictEqual(recordsFetch.opts.method, 'POST');
        assert.strictEqual(recordsFetch.opts.body.data.action, 'aa_list_finance_records');
        assert.strictEqual(recordsFetch.opts.body.data._wpnonce, 'test_nonce');
        assert.strictEqual(recordsFetch.opts.body.data.container_id, '7');
        assert.strictEqual(recordsFetch.opts.body.data.variant_key, 'general');
        assert.strictEqual(recordsFetch.opts.body.data.page, '1');

        const getContainerFetch = boot.fetchCalls.find(function (call) {
            return call.opts.body.data.action === 'aa_get_finance_container';
        });
        assert.strictEqual(getContainerFetch, undefined);
    });

    it('rechaza ID inválido fail-closed sin fetch de registros', async () => {
        const dom = buildRecordsDom();
        const sandbox = { document: dom.document, window: { AA_FINANCE_DATA: DEFAULT_FINANCE_DATA } };
        vm.runInNewContext(recordsModuleSrc, sandbox);
        const controller = sandbox.window.AA_FinanceRecords.createController({
            cfg: DEFAULT_FINANCE_DATA,
            elements: {
                heading: dom.recordsHeadingEl,
                summary: dom.recordsSummaryEl,
                status: dom.recordsStatusEl,
                grid: dom.recordsGridEl,
                pagination: dom.recordsPaginationEl,
                prev: dom.recordsPrevBtn,
                next: dom.recordsNextBtn,
                pageIndicator: dom.recordsPageIndicatorEl
            }
        });
        const fetchCalls = [];
        sandbox.fetch = function () {
            fetchCalls.push(true);
            return Promise.resolve();
        };
        controller.open(0, 1);
        controller.open(1.5, 1);
        await flushMicrotasks();
        assert.strictEqual(fetchCalls.length, 0);
        controller.destroy();
    });

    it('renderiza contenedor, registros, amounts y previene XSS', async () => {
        const dom = buildRecordsDom();
        bootBothModules(dom.document, {
            recordsResponse: function () {
                return jsonResponse({
                    ok: true,
                    status: 200,
                    body: {
                        success: true,
                        data: recordsEnvelope({
                            items: [
                                {
                                    id: 1,
                                    family_key: 'finance',
                                    variant_key: 'general',
                                    container_id: 7,
                                    title: '<img onerror=alert(1)>',
                                    details: null,
                                    amount: null,
                                    created_at: '2026-08-31 12:00:00'
                                },
                                {
                                    id: 2,
                                    family_key: 'finance',
                                    variant_key: 'general',
                                    container_id: 7,
                                    title: 'Cero',
                                    details: null,
                                    amount: '0.00',
                                    created_at: '2026-08-31 12:05:00'
                                },
                                {
                                    id: 3,
                                    family_key: 'finance',
                                    variant_key: 'general',
                                    container_id: 7,
                                    title: 'Positivo',
                                    details: null,
                                    amount: '99.99',
                                    created_at: '2026-08-31 12:10:00'
                                },
                                {
                                    id: 4,
                                    family_key: 'finance',
                                    variant_key: 'general',
                                    container_id: 7,
                                    title: 'Negativo',
                                    details: null,
                                    amount: '-25.50',
                                    created_at: '2026-08-31 12:15:00'
                                }
                            ],
                            amount_total: '-25.50',
                            total: 4,
                            total_pages: 1
                        })
                    }
                });
            }
        });
        await flushMicrotasks();
        dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
        await flushMicrotasks();

        assert.strictEqual(dom.recordsHeadingEl.textContent, 'Caja General');
        assert.ok(dom.recordsSummaryEl.textContent.includes('Sin importes') || dom.recordsSummaryEl.textContent.includes('-25.50'));
        assert.ok(dom.recordsGridEl.textContent.includes('Sin importe'));
        assert.ok(dom.recordsGridEl.textContent.includes('0.00'));
        assert.ok(dom.recordsGridEl.textContent.includes('-25.50'));
        assert.ok(dom.recordsGridEl.textContent.includes('<img onerror=alert(1)>'));
    });

    it('muestra estado vacío con amount_total null', async () => {
        const dom = buildRecordsDom();
        bootBothModules(dom.document, {
            recordsResponse: function () {
                return jsonResponse({
                    ok: true,
                    status: 200,
                    body: {
                        success: true,
                        data: recordsEnvelope({
                            items: [],
                            total: 0,
                            total_pages: 0,
                            amount_total: null
                        })
                    }
                });
            }
        });
        await flushMicrotasks();
        dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
        await flushMicrotasks();
        assert.ok(dom.recordsGridEl.textContent.includes('no tiene registros'));
        assert.strictEqual(dom.recordsPaginationEl.hidden, true);
    });

    it('maneja container_not_found invalidando snapshot y recargando al volver', async () => {
        const dom = buildRecordsDom();
        let listCalls = 0;
        const sandbox = {
            document: dom.document,
            window: { AA_FINANCE_DATA: DEFAULT_FINANCE_DATA },
            FormData: buildTestFormData(),
            setTimeout: hostSetTimeout,
            clearTimeout: clearTimeout,
            fetch: function (url, opts) {
                if (isListRecordsFetch(opts)) {
                    return Promise.resolve(jsonResponse({
                        ok: false,
                        status: 404,
                        body: {
                            success: false,
                            data: { code: 'container_not_found', message: 'Contenedor financiero no encontrado.' }
                        }
                    }));
                }
                if (isListContainersFetch(opts)) {
                    listCalls++;
                    return Promise.resolve(jsonResponse({
                        ok: true,
                        status: 200,
                        body: {
                            success: true,
                            data: listCalls === 1 ? AUTHORITATIVE_LIST_DATA : EMPTY_LIST_DATA
                        }
                    }));
                }
                return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: EMPTY_LIST_DATA } }));
            },
            AbortController: hostAbortController,
            AbortSignal: hostAbortSignal
        };
        vm.runInNewContext(recordsModuleSrc, sandbox);
        vm.runInNewContext(orchestratorModuleSrc, sandbox);
        await flushMicrotasks();

        const initialListCalls = listCalls;
        dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
        await flushMicrotasks();
        assert.ok(dom.recordsStatusEl.textContent.includes('no está disponible'));

        dom.recordsBackBtn.dispatch('click');
        await flushMicrotasks();
        assert.ok(listCalls > initialListCalls);
        assert.strictEqual(dom.recordsContainer.hidden, true);
        assert.strictEqual(dom.listContainer.hidden, false);
    });

    it('vuelve sin refetch cuando snapshot sigue válido', async () => {
        const dom = buildRecordsDom();
        let listCalls = 0;
        const boot = bootBothModules(dom.document, {
            recordsResponse: function () {
                return jsonResponse({
                    ok: true,
                    status: 200,
                    body: { success: true, data: recordsEnvelope() }
                });
            }
        });
        boot.sandbox.fetch = function (url, opts) {
            if (isListContainersFetch(opts)) {
                listCalls++;
                return Promise.resolve(jsonResponse({
                    ok: true,
                    status: 200,
                    body: { success: true, data: AUTHORITATIVE_LIST_DATA }
                }));
            }
            if (isListRecordsFetch(opts)) {
                return Promise.resolve(jsonResponse({
                    ok: true,
                    status: 200,
                    body: { success: true, data: recordsEnvelope() }
                }));
            }
            return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: AUTHORITATIVE_LIST_DATA } }));
        };
        vm.runInNewContext(recordsModuleSrc, boot.sandbox);
        vm.runInNewContext(orchestratorModuleSrc, boot.sandbox);
        await flushMicrotasks();

        const callsAfterBoot = listCalls;
        const openBtn = dom.gridEl.querySelector('.aa-finance-open-records-btn');
        openBtn.dispatch('click');
        await flushMicrotasks();
        dom.recordsBackBtn.dispatch('click');
        await flushMicrotasks();
        assert.strictEqual(listCalls, callsAfterBoot);
        assert.strictEqual(dom.document.activeElement, openBtn);
    });

    it('oculta trigger de creación en detalle', async () => {
        const dom = buildRecordsDom();
        bootBothModules(dom.document, {
            recordsResponse: function () {
                return jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } });
            }
        });
        await flushMicrotasks();
        dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
        await flushMicrotasks();
        assert.strictEqual(dom.openCreateBtn.hidden, true);
    });

    it('ignora respuesta tardía al abrir contenedor B antes de responder A', async () => {
        const dom = buildRecordsDom();
        const deferredA = createDeferred();
        const deferredB = createDeferred();
        let callIndex = 0;
        const sandbox = {
            document: dom.document,
            window: { AA_FINANCE_DATA: DEFAULT_FINANCE_DATA },
            FormData: buildTestFormData(),
            setTimeout: hostSetTimeout,
            clearTimeout: clearTimeout,
            fetch: function (url, opts) {
                if (isListContainersFetch(opts)) {
                    return Promise.resolve(jsonResponse({
                        ok: true,
                        status: 200,
                        body: {
                            success: true,
                            data: {
                                items: [
                                    Object.assign({}, AUTHORITATIVE_LIST_DATA.items[0], { id: 7, title: 'A' }),
                                    Object.assign({}, AUTHORITATIVE_LIST_DATA.items[0], { id: 8, title: 'B' })
                                ],
                                page: 1,
                                per_page: 15,
                                total: 2,
                                total_pages: 1,
                                has_previous: false,
                                has_next: false
                            }
                        }
                    }));
                }
                if (isListRecordsFetch(opts)) {
                    callIndex++;
                    if (callIndex === 1) return deferredA.promise;
                    return deferredB.promise;
                }
                return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: AUTHORITATIVE_LIST_DATA } }));
            },
            AbortController: hostAbortController,
            AbortSignal: hostAbortSignal
        };
        vm.runInNewContext(recordsModuleSrc, sandbox);
        vm.runInNewContext(orchestratorModuleSrc, sandbox);
        await flushMicrotasks();

        const buttons = dom.gridEl.querySelectorAll('.aa-finance-open-records-btn');
        assert.strictEqual(buttons.length, 2);

        buttons[0].dispatch('click');
        buttons[1].dispatch('click');

        deferredB.resolve(jsonResponse({
            ok: true,
            status: 200,
            body: {
                success: true,
                data: recordsEnvelope({
                    container: Object.assign({}, SAMPLE_CONTAINER, { id: 8, title: 'Contenedor B' }),
                    items: [{
                        id: 201,
                        family_key: 'finance',
                        variant_key: 'general',
                        container_id: 8,
                        title: 'Registro B',
                        details: null,
                        amount: '10.00',
                        created_at: '2026-08-31 11:00:00'
                    }],
                    amount_total: '10.00'
                })
            }
        }));
        await flushMicrotasks();
        assert.strictEqual(dom.recordsHeadingEl.textContent, 'Contenedor B');

        deferredA.resolve(jsonResponse({
            ok: true,
            status: 200,
            body: {
                success: true,
                data: recordsEnvelope({
                    container: Object.assign({}, SAMPLE_CONTAINER, { id: 7, title: 'Contenedor A tardío' }),
                    items: [{
                        id: 101,
                        family_key: 'finance',
                        variant_key: 'general',
                        container_id: 7,
                        title: 'Registro A tardío',
                        details: null,
                        amount: '5.00',
                        created_at: '2026-08-31 11:00:00'
                    }],
                    amount_total: '5.00'
                })
            }
        }));
        await flushMicrotasks();
        assert.strictEqual(dom.recordsHeadingEl.textContent, 'Contenedor B');
    });

    it('trata AbortError silenciosamente al cerrar durante fetch', async () => {
        const dom = buildRecordsDom();
        const deferred = createDeferred();
        const sandbox = bootBothModules(dom.document, { listDeferred: null }).sandbox;
        sandbox.fetch = function (url, opts) {
            if (isListContainersFetch(opts)) {
                return Promise.resolve(jsonResponse({
                    ok: true,
                    status: 200,
                    body: { success: true, data: AUTHORITATIVE_LIST_DATA }
                }));
            }
            if (isListRecordsFetch(opts)) {
                return deferred.promise;
            }
            return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: AUTHORITATIVE_LIST_DATA } }));
        };
        vm.runInNewContext(recordsModuleSrc, sandbox);
        vm.runInNewContext(orchestratorModuleSrc, sandbox);
        await flushMicrotasks();
        dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
        dom.recordsBackBtn.dispatch('click');
        const err = new Error('Aborted');
        err.name = 'AbortError';
        deferred.reject(err);
        await flushMicrotasks();
        assert.strictEqual(dom.recordsContainer.hidden, true);
        assert.ok(!dom.recordsStatusEl.textContent.includes('conexión'));
    });

    it('ofrece retry solo en errores recuperables', async () => {
        const dom = buildRecordsDom();
        bootBothModules(dom.document, {
            recordsResponse: function () {
                return jsonResponse({
                    ok: false,
                    status: 500,
                    body: {
                        success: false,
                        data: { code: 'persistence_failed', message: 'Fallo temporal.' }
                    }
                });
            }
        });
        await flushMicrotasks();
        dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
        await flushMicrotasks();
        assert.ok(dom.recordsStatusEl.textContent.includes('Reintentar'));
    });

    it('rechaza envelope corrupto sin render parcial', async () => {
        const dom = buildRecordsDom();
        bootBothModules(dom.document, {
            recordsResponse: function () {
                return jsonResponse({
                    ok: true,
                    status: 200,
                    body: {
                        success: true,
                        data: recordsEnvelope({ amount_total: 150.85 })
                    }
                });
            }
        });
        await flushMicrotasks();
        dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
        await flushMicrotasks();
        assert.ok(dom.recordsStatusEl.textContent.includes('no válida'));
        assert.strictEqual(dom.recordsGridEl.children.length, 0);
    });

    it('enfoca heading al abrir detalle', async () => {
        const dom = buildRecordsDom();
        bootBothModules(dom.document, {
            recordsResponse: function () {
                return jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } });
            }
        });
        await flushMicrotasks();
        dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
        await hostSetTimeout(function () {}, 60);
        await flushMicrotasks();
        assert.strictEqual(dom.document.activeElement, dom.recordsHeadingEl);
    });
});
