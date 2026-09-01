'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const recordsModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-records-module.js');
const createModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-record-create-module.js');
const deleteModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-record-delete-module.js');
const orchestratorModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-module.js');
const recordsModuleSrc = fs.readFileSync(recordsModulePath, 'utf8');
const createModuleSrc = fs.readFileSync(createModulePath, 'utf8');
const deleteModuleSrc = fs.readFileSync(deleteModulePath, 'utf8');
const orchestratorModuleSrc = fs.readFileSync(orchestratorModulePath, 'utf8');

const hostSetImmediate = setImmediate;
const hostSetTimeout = setTimeout.bind(globalThis);
const hostClearTimeout = clearTimeout.bind(globalThis);
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
        listRecords: 'aa_list_finance_records',
        createRecord: 'aa_create_finance_record',
        deleteRecord: 'aa_delete_finance_record',
        getRecord: 'aa_get_finance_record'
    }
};

const SAMPLE_CONTAINER = {
    id: 7,
    family_key: 'finance',
    variant_key: 'general',
    title: 'Caja General',
    details: null,
    created_at: '2026-08-31 10:00:00'
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
    }, {
        id: 8,
        family_key: 'finance',
        variant_key: 'general',
        title: 'Contenedor B',
        details: null,
        amount_total: null,
        created_at: '2026-08-31 11:00:00'
    }],
    page: 1,
    per_page: 15,
    total: 2,
    total_pages: 1,
    has_previous: false,
    has_next: false
};

const VALID_RECORD = {
    id: 501,
    family_key: 'finance',
    variant_key: 'general',
    container_id: 7,
    title: 'Compra',
    details: 'Detalle\nLínea 2',
    amount: '150.85',
    created_at: '2026-08-31 12:00:00'
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

function createTimerController() {
    const captured = new Map();
    let nextId = 1;
    return {
        setTimeout(fn, delay) {
            if (delay === 15000) {
                const id = nextId++;
                captured.set(id, fn);
                return id;
            }
            return hostSetTimeout(fn, delay);
        },
        clearTimeout(id) {
            if (captured.has(id)) {
                captured.delete(id);
                return;
            }
            hostClearTimeout(id);
        },
        flush(id) {
            const fn = captured.get(id);
            if (!fn) return false;
            captured.delete(id);
            fn();
            return true;
        },
        flushAll() {
            Array.from(captured.keys()).forEach((id) => this.flush(id));
        },
        ids() {
            return Array.from(captured.keys());
        }
    };
}

function buildTestFormData() {
    return class {
        constructor() { this.data = {}; }
        append(k, v) { this.data[k] = v; }
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
            add(c) { this._set.add(c); },
            remove(c) { this._set.delete(c); },
            contains(c) { return this._set.has(c); }
        },
        focus() { if (this.ownerDocument) this.ownerDocument.activeElement = this; },
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
            if (name === 'aria-invalid') delete this.attributes['aria-invalid'];
            if (name === 'aria-describedby') delete this.attributes['aria-describedby'];
        },
        appendChild(child) {
            child.parentElement = this;
            child.parentNode = this;
            child.ownerDocument = this.ownerDocument;
            this.children.push(child);
            return child;
        },
        removeChild(child) {
            this.children = this.children.filter((c) => c !== child);
            child.parentElement = null;
            child.parentNode = null;
            return child;
        },
        addEventListener(type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        removeEventListener(type, handler) {
            this._listeners[type] = (this._listeners[type] || []).filter((fn) => fn !== handler);
        },
        dispatch(type, event) {
            (this._listeners[type] || []).forEach((handler) => {
                handler(Object.assign({ type, preventDefault() {} }, event || {}));
            });
        },
        querySelector(selector) {
            return findMatch(this, selector);
        },
        querySelectorAll(selector) {
            const out = [];
            collectMatches(this, selector, out);
            return out;
        },
        contains(node) {
            if (!node) return false;
            let current = node;
            while (current) {
                if (current === this) return true;
                current = current.parentElement;
            }
            return false;
        }
    };
    Object.defineProperty(el, 'className', {
        get() { return Array.from(el.classList._set).join(' '); },
        set(value) { el.classList._set = new Set(String(value).split(/\s+/).filter(Boolean)); }
    });
    Object.defineProperty(el, 'textContent', {
        get() {
            if (this.children.length > 0) {
                return this.children.map((c) => c.textContent).join('');
            }
            return this._text;
        },
        set(value) {
            this._text = String(value);
            this.children = [];
        }
    });
    Object.defineProperty(el, 'value', {
        get() { return this._value; },
        set(value) { this._value = String(value); }
    });
    Object.defineProperty(el, 'firstChild', { get() { return this.children[0] || null; } });
    if (id) el.id = id;
    return el;
}

function matchesSelector(el, selector) {
    if (!el || !selector) return false;
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
    if (matchesSelector(root, selector) && !out.includes(root)) out.push(root);
    root.children.forEach((child) => collectMatches(child, selector, out));
}

function buildFullDom() {
    const document = {
        _elements: Object.create(null),
        activeElement: null,
        body: null,
        createElement(tag) {
            const el = createEl(tag);
            el.ownerDocument = this;
            return el;
        },
        getElementById(id) { return this._elements[id] || null; },
        querySelectorAll(selector) {
            const out = [];
            Object.values(this._elements).forEach((el) => collectMatches(el, selector, out));
            return out;
        },
        contains(node) { return !!node && !!this._elements[node.id]; },
        _listeners: Object.create(null),
        addEventListener(type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        dispatch(type, event) {
            (this._listeners[type] || []).forEach((handler) => handler(Object.assign({ type }, event || {})));
        }
    };
    const body = createEl('body');
    body.ownerDocument = document;
    document.body = body;
    const root = createEl('div', 'aa-finance-root');
    body.appendChild(root);

    const listContainer = createEl('div', 'aa-finance-list-container');
    const statusEl = createEl('div', 'aa-finance-status');
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
    recordsStatusEl.setAttribute('tabindex', '-1');
    const recordsGridEl = createEl('div', 'aa-finance-records-grid');
    const recordsPaginationEl = createEl('div', 'aa-finance-records-pagination');
    recordsPaginationEl.hidden = true;
    const recordsPrevBtn = createEl('button', 'aa-finance-records-prev');
    const recordsNextBtn = createEl('button', 'aa-finance-records-next');
    const recordsPageIndicatorEl = createEl('span', 'aa-finance-records-page-indicator');
    recordsPaginationEl.appendChild(recordsPrevBtn);
    recordsPaginationEl.appendChild(recordsPageIndicatorEl);
    recordsPaginationEl.appendChild(recordsNextBtn);
    const openRecordBtn = createEl('button', 'aa-finance-open-record-btn');
    openRecordBtn.classList.add('hidden');
    openRecordBtn.hidden = true;

    recordsContainer.appendChild(recordsBackBtn);
    recordsContainer.appendChild(recordsHeadingEl);
    recordsContainer.appendChild(recordsSummaryEl);
    recordsContainer.appendChild(recordsStatusEl);
    recordsContainer.appendChild(openRecordBtn);
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

    const recordCreateModal = createEl('div', 'aa-finance-record-create-modal');
    recordCreateModal.classList.add('hidden');
    const recordModalBackdrop = createEl('div', 'aa-finance-record-create-modal-backdrop');
    const recordModalCloseBtn = createEl('button', 'aa-finance-record-create-close');
    const recordCreateForm = createEl('form', 'aa-finance-record-create-form');
    const recordModalErrorEl = createEl('div', 'aa-finance-record-create-error');
    const recordTitleInput = createEl('input', 'aa-finance-record-create-title');
    const recordTitleErrorEl = createEl('p', 'aa-finance-record-title-error');
    const recordDetailsInput = createEl('textarea', 'aa-finance-record-create-details');
    const recordDetailsErrorEl = createEl('p', 'aa-finance-record-details-error');
    const recordAmountInput = createEl('input', 'aa-finance-record-create-amount');
    const recordAmountErrorEl = createEl('p', 'aa-finance-record-amount-error');
    const recordStandardActionsEl = createEl('div', 'aa-finance-record-create-actions-standard');
    const recordCancelBtn = createEl('button', 'aa-finance-record-create-cancel');
    const recordSubmitBtn = createEl('button', 'aa-finance-record-create-submit');
    const recordUncertainActionsEl = createEl('div', 'aa-finance-record-create-actions-uncertain');
    recordUncertainActionsEl.classList.add('hidden');
    const recordUncertainCloseBtn = createEl('button', 'aa-finance-record-create-uncertain-close');
    const recordBlockedActionsEl = createEl('div', 'aa-finance-record-create-actions-blocked');
    recordBlockedActionsEl.classList.add('hidden');
    const recordBlockedCloseBtn = createEl('button', 'aa-finance-record-create-blocked-close');
    recordStandardActionsEl.appendChild(recordCancelBtn);
    recordStandardActionsEl.appendChild(recordSubmitBtn);
    recordCreateForm.appendChild(recordModalErrorEl);
    recordCreateForm.appendChild(recordTitleInput);
    recordCreateForm.appendChild(recordDetailsInput);
    recordCreateForm.appendChild(recordAmountInput);
    recordCreateForm.appendChild(recordStandardActionsEl);
    recordCreateForm.appendChild(recordUncertainActionsEl);
    recordCreateForm.appendChild(recordBlockedActionsEl);
    recordCreateModal.appendChild(recordModalBackdrop);
    recordCreateModal.appendChild(recordModalCloseBtn);
    recordCreateModal.appendChild(recordCreateForm);

    const recordDeleteModal = createEl('div', 'aa-finance-record-delete-modal');
    recordDeleteModal.classList.add('hidden');
    const recordDeleteBackdrop = createEl('div', 'aa-finance-record-delete-modal-backdrop');
    const recordDeleteCloseBtn = createEl('button', 'aa-finance-record-delete-close');
    const recordDeleteTitleEl = createEl('h3', 'aa-finance-record-delete-modal-title');
    const recordDeleteBodyEl = createEl('p', 'aa-finance-record-delete-body');
    const recordDeleteAmountEl = createEl('p', 'aa-finance-record-delete-amount');
    recordDeleteAmountEl.classList.add('hidden');
    const recordDeleteErrorEl = createEl('div', 'aa-finance-record-delete-error');
    recordDeleteErrorEl.classList.add('hidden');
    const recordDeleteStandardActionsEl = createEl('div', 'aa-finance-record-delete-actions-standard');
    const recordDeleteCancelBtn = createEl('button', 'aa-finance-record-delete-cancel');
    const recordDeleteConfirmBtn = createEl('button', 'aa-finance-record-delete-confirm');
    const recordDeleteUncertainActionsEl = createEl('div', 'aa-finance-record-delete-actions-uncertain');
    recordDeleteUncertainActionsEl.classList.add('hidden');
    const recordDeleteUncertainCloseBtn = createEl('button', 'aa-finance-record-delete-uncertain-close');
    const recordDeleteBlockedActionsEl = createEl('div', 'aa-finance-record-delete-actions-blocked');
    recordDeleteBlockedActionsEl.classList.add('hidden');
    const recordDeleteBlockedCloseBtn = createEl('button', 'aa-finance-record-delete-blocked-close');
    recordDeleteStandardActionsEl.appendChild(recordDeleteCancelBtn);
    recordDeleteStandardActionsEl.appendChild(recordDeleteConfirmBtn);
    recordDeleteUncertainActionsEl.appendChild(recordDeleteUncertainCloseBtn);
    recordDeleteBlockedActionsEl.appendChild(recordDeleteBlockedCloseBtn);
    recordDeleteModal.appendChild(recordDeleteBackdrop);
    recordDeleteModal.appendChild(recordDeleteCloseBtn);
    recordDeleteModal.appendChild(recordDeleteTitleEl);
    recordDeleteModal.appendChild(recordDeleteErrorEl);
    recordDeleteModal.appendChild(recordDeleteBodyEl);
    recordDeleteModal.appendChild(recordDeleteAmountEl);
    recordDeleteModal.appendChild(recordDeleteStandardActionsEl);
    recordDeleteModal.appendChild(recordDeleteUncertainActionsEl);
    recordDeleteModal.appendChild(recordDeleteBlockedActionsEl);

    root.appendChild(openCreateBtn);
    root.appendChild(listContainer);
    root.appendChild(recordsContainer);
    root.appendChild(createModal);
    root.appendChild(recordCreateModal);
    root.appendChild(recordDeleteModal);

    [
        root, listContainer, statusEl, gridEl, paginationEl, prevBtn, nextBtn, pageIndicatorEl,
        recordsContainer, recordsBackBtn, recordsHeadingEl, recordsSummaryEl, recordsStatusEl,
        recordsGridEl, recordsPaginationEl, recordsPrevBtn, recordsNextBtn, recordsPageIndicatorEl,
        openCreateBtn, createModal, openRecordBtn, recordCreateModal, recordModalBackdrop,
        recordModalCloseBtn, recordCreateForm, recordModalErrorEl, recordTitleInput, recordTitleErrorEl,
        recordDetailsInput, recordDetailsErrorEl, recordAmountInput, recordAmountErrorEl,
        recordStandardActionsEl, recordCancelBtn, recordSubmitBtn, recordUncertainActionsEl,
        recordUncertainCloseBtn, recordBlockedActionsEl, recordBlockedCloseBtn,
        recordDeleteModal, recordDeleteBackdrop, recordDeleteCloseBtn, recordDeleteTitleEl,
        recordDeleteBodyEl, recordDeleteAmountEl, recordDeleteErrorEl, recordDeleteStandardActionsEl,
        recordDeleteCancelBtn, recordDeleteConfirmBtn, recordDeleteUncertainActionsEl,
        recordDeleteUncertainCloseBtn, recordDeleteBlockedActionsEl, recordDeleteBlockedCloseBtn
    ].forEach((el) => {
        el.ownerDocument = document;
        if (el.id) document._elements[el.id] = el;
    });

    return {
        document, root, statusEl, gridEl, openCreateBtn, openRecordBtn,
        recordsContainer, recordsBackBtn, recordsHeadingEl, recordsStatusEl, recordsGridEl,
        recordCreateModal, recordCreateForm, recordTitleInput, recordDetailsInput, recordAmountInput,
        recordSubmitBtn, recordCancelBtn, recordModalCloseBtn, recordUncertainCloseBtn,
        recordBlockedCloseBtn, recordTitleErrorEl, recordDetailsErrorEl, recordAmountErrorEl,
        recordModalErrorEl, recordUncertainActionsEl, recordBlockedActionsEl
    };
}

async function flushMicrotasks() {
    await new Promise(hostSetImmediate);
}

async function waitDelay(ms) {
    await new Promise((resolve) => hostSetTimeout(resolve, ms));
}

function jsonResponse(payload) {
    return {
        ok: payload.ok !== false,
        status: payload.status || 200,
        json() { return Promise.resolve(payload.body); }
    };
}

function recordsEnvelope(overrides) {
    return Object.assign({
        container: Object.assign({}, SAMPLE_CONTAINER),
        items: [Object.assign({}, VALID_RECORD)],
        page: 1,
        per_page: 15,
        total: 1,
        total_pages: 1,
        has_previous: false,
        has_next: false,
        amount_total: '150.85'
    }, overrides || {});
}

function isListContainersFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_list_finance_containers';
}

function isListRecordsFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_list_finance_records';
}

function isCreateRecordFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_create_finance_record';
}

function isDeleteRecordFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_delete_finance_record';
}

function bootAllModules(document, options) {
    options = options || {};
    const timerCtrl = createTimerController();
    const fetchCalls = [];
    const recordsDeferred = options.recordsDeferred || null;
    const createRecordDeferred = options.createRecordDeferred || null;

    const sandbox = {
        document,
        window: { AA_FINANCE_DATA: options.financeData || DEFAULT_FINANCE_DATA },
        FormData: buildTestFormData(),
        setTimeout: timerCtrl.setTimeout.bind(timerCtrl),
        clearTimeout: timerCtrl.clearTimeout.bind(timerCtrl),
        fetch(url, opts) {
            fetchCalls.push({ url, opts });
            if (isCreateRecordFetch(opts)) {
                if (createRecordDeferred) return createRecordDeferred.promise;
                if (options.createRecordResponse) return Promise.resolve(options.createRecordResponse(opts));
            }
            if (isListRecordsFetch(opts)) {
                if (recordsDeferred) return recordsDeferred.promise;
                if (options.recordsResponse) return Promise.resolve(options.recordsResponse(opts));
                return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } }));
            }
            if (isDeleteRecordFetch(opts)) {
                if (options.deleteResponse) return Promise.resolve(options.deleteResponse(opts));
            }
            if (isListContainersFetch(opts)) {
                return Promise.resolve(jsonResponse({
                    ok: true,
                    status: 200,
                    body: { success: true, data: options.listData || AUTHORITATIVE_LIST_DATA }
                }));
            }
            return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: AUTHORITATIVE_LIST_DATA } }));
        },
        AbortController: hostAbortController,
        AbortSignal: hostAbortSignal
    };

    if (options.includeRecordsFactory !== false) vm.runInNewContext(recordsModuleSrc, sandbox);
    if (options.includeCreateFactory !== false) vm.runInNewContext(createModuleSrc, sandbox);
    if (options.includeDeleteFactory !== false) vm.runInNewContext(deleteModuleSrc, sandbox);
    vm.runInNewContext(orchestratorModuleSrc, sandbox);

    return {
        sandbox,
        fetchCalls,
        timerCtrl,
        recordsDeferred,
        createRecordDeferred,
        cleanup() {
            timerCtrl.flushAll();
            if (recordsDeferred && !recordsDeferred.settled) {
                recordsDeferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } }));
            }
            if (createRecordDeferred && !createRecordDeferred.settled) {
                const err = new Error('Aborted');
                err.name = 'AbortError';
                createRecordDeferred.reject(err);
            }
        }
    };
}

async function openDetail(dom, boot) {
    await flushMicrotasks();
    dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
    await flushMicrotasks();
    return boot;
}

function createRecordSuccessResponse(record) {
    return jsonResponse({
        ok: true,
        status: 200,
        body: { success: true, data: { record: record || VALID_RECORD } }
    });
}

describe('FinanceRecordCreateModule (Ciclo 3D3B)', () => {

    it('permanece inerte sin factory o config createRecord', async () => {
        const dom = buildFullDom();
        const data = JSON.parse(JSON.stringify(DEFAULT_FINANCE_DATA));
        delete data.actions.createRecord;
        bootAllModules(dom.document, { financeData: data });
        await flushMicrotasks();
        assert.strictEqual(dom.openRecordBtn.hidden, true);
    });

    it('solo abre modal con detalle activo y enfoca título', async () => {
        const dom = buildFullDom();
        bootAllModules(dom.document);
        await flushMicrotasks();
        dom.openRecordBtn.dispatch('click');
        assert.strictEqual(dom.recordCreateModal.classList.contains('hidden'), true);
        await openDetail(dom);
        dom.openRecordBtn.dispatch('click');
        await waitDelay(60);
        assert.strictEqual(dom.recordCreateModal.classList.contains('hidden'), false);
        assert.strictEqual(dom.document.activeElement, dom.recordTitleInput);
    });

    it('valida título vacío y conserva container ID real en payload', async () => {
        const dom = buildFullDom();
        const boot = bootAllModules(dom.document);
        await openDetail(dom, boot);
        dom.openRecordBtn.dispatch('click');
        await waitDelay(60);
        dom.recordTitleInput.value = '   ';
        dom.recordCreateForm.dispatch('submit');
        assert.strictEqual(dom.recordTitleErrorEl.classList.contains('hidden'), false);
        dom.recordTitleInput.value = 'Entrada A';
        dom.recordDetailsInput.value = 'L1\nL2';
        dom.recordAmountInput.value = ' 10.50 ';
        dom.recordCreateForm.dispatch('submit');
        await flushMicrotasks();
        const createFetch = boot.fetchCalls.find((c) => isCreateRecordFetch(c.opts));
        assert.ok(createFetch);
        assert.strictEqual(createFetch.opts.body.data.container_id, '7');
        assert.strictEqual(createFetch.opts.body.data.title, 'Entrada A');
        assert.strictEqual(createFetch.opts.body.data.details, 'L1\nL2');
        assert.strictEqual(createFetch.opts.body.data.amount, ' 10.50 ');
        assert.strictEqual(createFetch.opts.body.data.action, 'aa_create_finance_record');
        boot.cleanup();
    });

    it('omite amount vacío y bloquea doble submit con snapshot inmutable', async () => {
        const dom = buildFullDom();
        const deferred = createDeferred();
        const boot = bootAllModules(dom.document, { createRecordDeferred: deferred });
        try {
            await openDetail(dom, boot);
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            dom.recordTitleInput.value = 'Original';
            dom.recordDetailsInput.value = 'Detalle';
            dom.recordAmountInput.value = '';
            dom.recordCreateForm.dispatch('submit');
            await flushMicrotasks();
            dom.recordTitleInput.value = 'Modificado';
            dom.recordCreateForm.dispatch('submit');
            assert.strictEqual(boot.fetchCalls.filter((c) => isCreateRecordFetch(c.opts)).length, 1);
            assert.strictEqual(deferred.settled, false);
            const payload = boot.fetchCalls.find((c) => isCreateRecordFetch(c.opts)).opts.body.data;
            assert.strictEqual(payload.amount, undefined);
            assert.strictEqual(payload.title, 'Original');
        } finally {
            boot.cleanup();
        }
    });

    it('mapea errores corregibles y bloqueantes', async () => {
        const dom = buildFullDom();
        const boot = bootAllModules(dom.document, {
            createRecordResponse() {
                return jsonResponse({
                    ok: false,
                    status: 400,
                    body: { success: false, data: { code: 'invalid_amount', message: 'Importe inválido.' } }
                });
            }
        });
        await openDetail(dom, boot);
        dom.openRecordBtn.dispatch('click');
        await waitDelay(60);
        dom.recordTitleInput.value = 'T';
        dom.recordAmountInput.value = 'x';
        dom.recordCreateForm.dispatch('submit');
        await flushMicrotasks();
        assert.strictEqual(dom.recordAmountErrorEl.textContent, 'Importe inválido.');
        assert.strictEqual(dom.recordSubmitBtn.disabled, false);

        const domBlocked = buildFullDom();
        const bootBlocked = bootAllModules(domBlocked.document, {
            createRecordResponse() {
                return jsonResponse({
                    ok: false,
                    status: 403,
                    body: { success: false, data: { code: 'forbidden', message: 'No permitido.' } }
                });
            }
        });
        await openDetail(domBlocked, bootBlocked);
        domBlocked.openRecordBtn.dispatch('click');
        await waitDelay(60);
        domBlocked.recordTitleInput.value = 'T';
        domBlocked.recordCreateForm.dispatch('submit');
        await flushMicrotasks();
        assert.strictEqual(domBlocked.recordBlockedActionsEl.classList.contains('hidden'), false);
        assert.ok(domBlocked.recordModalErrorEl.textContent.includes('sesión actual'));
        bootBlocked.cleanup();
    });

    it('confirma éxito, refresca página 1 y no restaura draft tras refresh fallido', async () => {
        const dom = buildFullDom();
        let recordsFail = false;
        const boot = bootAllModules(dom.document, {
            createRecordResponse() {
                return createRecordSuccessResponse();
            },
            recordsResponse() {
                if (recordsFail) {
                    return jsonResponse({
                        ok: false,
                        status: 500,
                        body: { success: false, data: { code: 'persistence_failed', message: 'Fallo refresh.' } }
                    });
                }
                return jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } });
            }
        });
        await openDetail(dom, boot);
        dom.openRecordBtn.dispatch('click');
        await waitDelay(60);
        dom.recordTitleInput.value = 'Confirmada';
        dom.recordCreateForm.dispatch('submit');
        await flushMicrotasks();
        assert.strictEqual(dom.recordCreateModal.classList.contains('hidden'), true);
        const listFetches = boot.fetchCalls.filter((c) => isListRecordsFetch(c.opts));
        assert.ok(listFetches.length >= 2);
        assert.strictEqual(listFetches[listFetches.length - 1].opts.body.data.page, '1');

        recordsFail = true;
        dom.openRecordBtn.dispatch('click');
        assert.strictEqual(dom.openRecordBtn.disabled, false);
    });

    it('timeout, red, JSON corrupto y envelope corrupto pasan a incierto', async () => {
        const dom = buildFullDom();
        const deferred = createDeferred();
        const boot = bootAllModules(dom.document, { createRecordDeferred: deferred });
        try {
            await openDetail(dom, boot);
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            dom.recordTitleInput.value = 'Incierta';
            dom.recordCreateForm.dispatch('submit');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            assert.strictEqual(dom.recordUncertainActionsEl.classList.contains('hidden'), false);

            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { record: { id: 1 } } } }));
            await flushMicrotasks();
            assert.strictEqual(dom.recordCreateModal.classList.contains('hidden'), false);
        } finally {
            boot.cleanup();
        }
    });

    it('review exitoso habilita trigger tras settlement READY', async () => {
        const dom = buildFullDom();
        const createDef = createDeferred();
        const reviewRecordsDef = createDeferred();
        let recordsCall = 0;
        const boot = bootAllModules(dom.document, {
            createRecordDeferred: createDef,
            recordsResponse() {
                recordsCall++;
                if (recordsCall >= 2) {
                    return reviewRecordsDef.promise;
                }
                return jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } });
            }
        });
        try {
            await openDetail(dom, boot);
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            dom.recordTitleInput.value = 'Draft';
            dom.recordCreateForm.dispatch('submit');
            await flushMicrotasks();
            createDef.reject(new Error('Network drop'));
            await flushMicrotasks();
            dom.recordUncertainCloseBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, true);
            reviewRecordsDef.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } }));
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, false);
        } finally {
            boot.cleanup();
        }
    });

    it('retry exitoso completa review una sola vez', async () => {
        const dom = buildFullDom();
        let recordsFail = true;
        const createDef = createDeferred();
        const boot = bootAllModules(dom.document, {
            createRecordDeferred: createDef,
            recordsResponse() {
                if (recordsFail) {
                    return jsonResponse({
                        ok: false,
                        status: 500,
                        body: { success: false, data: { code: 'persistence_failed', message: 'Fallo.' } }
                    });
                }
                return jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } });
            }
        });
        try {
            await openDetail(dom, boot);
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            dom.recordTitleInput.value = 'Draft retry';
            dom.recordCreateForm.dispatch('submit');
            await flushMicrotasks();
            createDef.reject(new Error('Network drop'));
            await flushMicrotasks();
            dom.recordUncertainCloseBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, true);
            recordsFail = false;
            const retryBtn = dom.recordsStatusEl.querySelector('button');
            assert.ok(retryBtn);
            retryBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, false);
            retryBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, false);
        } finally {
            boot.cleanup();
        }
    });

    it('back durante review conserva pending y reabrir mismo contenedor reanuda', async () => {
        const dom = buildFullDom();
        const createDef = createDeferred();
        const recordsDef = createDeferred();
        const boot = bootAllModules(dom.document, { createRecordDeferred: createDef, recordsDeferred: recordsDef });
        try {
            await openDetail(dom, boot);
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            dom.recordTitleInput.value = 'Persistente';
            dom.recordCreateForm.dispatch('submit');
            await flushMicrotasks();
            createDef.reject(new Error('Network drop'));
            await flushMicrotasks();
            dom.recordUncertainCloseBtn.dispatch('click');
            dom.recordsBackBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.hidden, true);
            dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
            recordsDef.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } }));
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, false);
        } finally {
            boot.cleanup();
        }
    });

    it('abrir otro contenedor mantiene draft A y bloquea creación con aviso', async () => {
        const dom = buildFullDom();
        const createDef = createDeferred();
        const reviewRecordsDef = createDeferred();
        let recordsFetchCount = 0;
        const boot = bootAllModules(dom.document, {
            createRecordDeferred: createDef,
            recordsResponse(opts) {
                recordsFetchCount++;
                if (recordsFetchCount === 2) {
                    return reviewRecordsDef.promise;
                }
                const containerId = opts.body.data.container_id;
                return jsonResponse({
                    ok: true,
                    status: 200,
                    body: {
                        success: true,
                        data: recordsEnvelope({
                            container: Object.assign({}, SAMPLE_CONTAINER, {
                                id: Number(containerId),
                                title: containerId === '7' ? 'Caja General' : 'Contenedor B'
                            }),
                            items: [],
                            total: 0,
                            total_pages: 0,
                            amount_total: null
                        })
                    }
                });
            }
        });
        try {
            await openDetail(dom, boot);
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            dom.recordTitleInput.value = 'Draft A';
            dom.recordCreateForm.dispatch('submit');
            await flushMicrotasks();
            createDef.reject(new Error('Network drop'));
            await flushMicrotasks();
            dom.recordUncertainCloseBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, true);
            dom.recordsBackBtn.dispatch('click');
            await flushMicrotasks();
            reviewRecordsDef.resolve(jsonResponse({
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
            }));
            await flushMicrotasks();
            const buttons = dom.gridEl.querySelectorAll('.aa-finance-open-records-btn');
            buttons[1].dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, true);
            assert.ok(dom.recordsStatusEl.textContent.includes('entrada pendiente de revisión'));
        } finally {
            boot.cleanup();
        }
    });

    it('NOT_FOUND durante review cancela draft y vuelve al listado', async () => {
        const dom = buildFullDom();
        const createDef = createDeferred();
        const boot = bootAllModules(dom.document, {
            createRecordDeferred: createDef,
            recordsResponse() {
                return jsonResponse({
                    ok: false,
                    status: 404,
                    body: { success: false, data: { code: 'container_not_found', message: 'No existe.' } }
                });
            }
        });
        try {
            await openDetail(dom, boot);
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            dom.recordTitleInput.value = 'Perdida';
            dom.recordCreateForm.dispatch('submit');
            await flushMicrotasks();
            createDef.reject(new Error('Network drop'));
            await flushMicrotasks();
            dom.recordUncertainCloseBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.recordsContainer.hidden, true);
            assert.ok(dom.statusEl.textContent.includes('no pudo conservarse'));
        } finally {
            boot.cleanup();
        }
    });

    it('degrada si falta factory AA_FinanceRecordCreate', async () => {
        const dom = buildFullDom();
        bootAllModules(dom.document, { includeCreateFactory: false });
        await flushMicrotasks();
        await openDetail(dom);
        assert.strictEqual(dom.openRecordBtn.hidden, true);
    });

    it('delete_review bloquea trigger de creación', async () => {
        const dom = buildFullDom();
        const boot = bootAllModules(dom.document, {
            deleteResponse: function () {
                return jsonResponse({
                    ok: false,
                    status: 500,
                    body: { success: false, data: { code: 'persistence_failed', message: 'Incierto.' } }
                });
            }
        });
        try {
            await openDetail(dom, boot);
            const deleteBtn = dom.recordsGridEl.querySelector('.aa-finance-delete-record-btn');
            assert.ok(deleteBtn);
            deleteBtn.dispatch('click');
            await waitDelay(60);
            dom.document.getElementById('aa-finance-record-delete-confirm').dispatch('click');
            await flushMicrotasks();
            dom.document.getElementById('aa-finance-record-delete-uncertain-close').dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, true);
        } finally {
            boot.cleanup();
        }
    });
});
