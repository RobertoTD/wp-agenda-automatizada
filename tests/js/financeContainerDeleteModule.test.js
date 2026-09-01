'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const recordsModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-records-module.js');
const createModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-record-create-module.js');
const recordDeleteModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-record-delete-module.js');
const deleteModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-container-delete-module.js');
const orchestratorModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-module.js');
const recordsModuleSrc = fs.readFileSync(recordsModulePath, 'utf8');
const createModuleSrc = fs.readFileSync(createModulePath, 'utf8');
const recordDeleteModuleSrc = fs.readFileSync(recordDeleteModulePath, 'utf8');
const deleteModuleSrc = fs.readFileSync(deleteModulePath, 'utf8');
const orchestratorModuleSrc = fs.readFileSync(orchestratorModulePath, 'utf8');

const hostSetImmediate = setImmediate;
const hostSetTimeout = setTimeout.bind(globalThis);
const hostClearTimeout = clearTimeout.bind(globalThis);
const hostAbortController = globalThis.AbortController;
const hostAbortSignal = globalThis.AbortSignal;

const FINANCE_DATA = {
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
        getRecord: 'aa_get_finance_record',
        deleteContainer: 'aa_delete_finance_container',
        getContainer: 'aa_get_finance_container'
    }
};

const CONTAINER_SNAPSHOT = {
    containerId: 7,
    title: 'Caja',
    details: null,
    amountTotal: '150.85',
    sourcePage: 2
};

const AUTHORITATIVE_LIST_DATA = {
    items: [{ id: 7, family_key: 'finance', variant_key: 'general', title: 'Caja', details: null, amount_total: '150.85', created_at: '2026-08-31 10:00:00' }],
    page: 1, per_page: 15, total: 1, total_pages: 1, has_previous: false, has_next: false
};

const SAMPLE_CONTAINER = { id: 7, family_key: 'finance', variant_key: 'general', title: 'Caja', details: null, created_at: '2026-08-31 10:00:00' };

function createDeferred() {
    const deferred = { settled: false, promise: null, resolve: null, reject: null };
    deferred.promise = new Promise(function (resolve, reject) {
        deferred.resolve = function (value) { if (!deferred.settled) { deferred.settled = true; resolve(value); } };
        deferred.reject = function (reason) { if (!deferred.settled) { deferred.settled = true; reject(reason); } };
    });
    deferred.promise.catch(function () {});
    return deferred;
}

function createTimerController() {
    const captured = new Map();
    let nextId = 1;
    return {
        setTimeout(fn, delay) {
            if (delay === 15000) { const id = nextId++; captured.set(id, fn); return id; }
            return hostSetTimeout(fn, delay);
        },
        clearTimeout(id) { if (captured.has(id)) { captured.delete(id); return; } hostClearTimeout(id); },
        flush(id) { const fn = captured.get(id); if (!fn) return false; captured.delete(id); fn(); return true; },
        flushAll() { Array.from(captured.keys()).forEach((id) => this.flush(id)); },
        ids() { return Array.from(captured.keys()); }
    };
}

function buildTestFormData() {
    return class { constructor() { this.data = {}; } append(k, v) { this.data[k] = v; } };
}

function jsonResponse(payload) {
    return { ok: payload.ok !== false, status: payload.status || 200, json: () => Promise.resolve(payload.body) };
}

function isDeleteContainerFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_delete_finance_container';
}

function isGetContainerFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_get_finance_container';
}

function isListContainersFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_list_finance_containers';
}

function isCreateContainerFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_create_finance_container';
}

function isCreateRecordFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_create_finance_record';
}

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(), id: id || '', children: [], attributes: Object.create(null),
        _text: '', disabled: false, hidden: false, type: tag === 'button' ? 'button' : 'text',
        parentElement: null, parentNode: null, tabIndex: 0,
        classList: { _set: new Set(), add(c) { this._set.add(c); }, remove(c) { this._set.delete(c); }, contains(c) { return this._set.has(c); } },
        focus() { if (this.ownerDocument) this.ownerDocument.activeElement = this; },
        setAttribute(n, v) { this.attributes[n] = String(v); if (n === 'id') this.id = String(v); },
        getAttribute(n) { return Object.prototype.hasOwnProperty.call(this.attributes, n) ? this.attributes[n] : null; },
        removeAttribute(n) { delete this.attributes[n]; },
        appendChild(c) { c.parentElement = this; c.parentNode = this; c.ownerDocument = this.ownerDocument; this.children.push(c); return c; },
        addEventListener(t, h) { this._listeners = this._listeners || Object.create(null); (this._listeners[t] = this._listeners[t] || []).push(h); },
        dispatch(t, e) { (this._listeners?.[t] || []).forEach((h) => h(Object.assign({ type: t, preventDefault() {} }, e || {}))); },
        querySelector(s) { return findMatch(this, s); },
        querySelectorAll(s) { const out = []; collectMatches(this, s, out); return out; },
        contains(n) { let c = n; while (c) { if (c === this) return true; c = c.parentElement; } return false; }
    };
    Object.defineProperty(el, 'className', { get() { return Array.from(el.classList._set).join(' '); }, set(v) { el.classList._set = new Set(String(v).split(/\s+/).filter(Boolean)); } });
    Object.defineProperty(el, 'textContent', { get() { return el.children.length ? el.children.map((c) => c.textContent).join('') : el._text; }, set(v) { el._text = String(v); el.children = []; } });
    if (id) el.id = id;
    return el;
}

function matchesSelector(el, selector) {
    if (!el || !selector) return false;
    if (selector.charAt(0) === '#') return el.id === selector.slice(1);
    if (selector.charAt(0) === '.') return el.classList.contains(selector.slice(1));
    return el.tagName === selector.toUpperCase();
}
function findMatch(root, selector) { if (matchesSelector(root, selector)) return root; for (let i = 0; i < root.children.length; i++) { const f = findMatch(root.children[i], selector); if (f) return f; } return null; }
function collectMatches(root, selector, out) { if (matchesSelector(root, selector) && !out.includes(root)) out.push(root); root.children.forEach((c) => collectMatches(c, selector, out)); }

function buildDeleteModalDom() {
    const document = { _elements: Object.create(null), activeElement: null, body: null, _listeners: Object.create(null), createElement(tag) { const el = createEl(tag); el.ownerDocument = this; return el; }, getElementById(id) { return this._elements[id] || null; }, addEventListener(t, h) { (this._listeners[t] = this._listeners[t] || []).push(h); }, removeEventListener() {}, dispatch(t, e) { (this._listeners[t] || []).forEach((fn) => fn(Object.assign({ type: t, key: e?.key || '', shiftKey: !!e?.shiftKey, preventDefault() {} }, e || {}))); } };
    const body = createEl('body'); body.ownerDocument = document; document.body = body;
    const modal = createEl('div', 'aa-finance-container-delete-modal'); modal.classList.add('hidden');
    const backdrop = createEl('div', 'aa-finance-container-delete-modal-backdrop');
    const closeBtn = createEl('button', 'aa-finance-container-delete-close');
    const title = createEl('h3', 'aa-finance-container-delete-modal-title');
    const bodyEl = createEl('p', 'aa-finance-container-delete-body');
    const amount = createEl('p', 'aa-finance-container-delete-amount'); amount.classList.add('hidden');
    const modalError = createEl('div', 'aa-finance-container-delete-error'); modalError.classList.add('hidden');
    const standard = createEl('div', 'aa-finance-container-delete-actions-standard');
    const cancelBtn = createEl('button', 'aa-finance-container-delete-cancel');
    const confirmBtn = createEl('button', 'aa-finance-container-delete-confirm');
    const uncertain = createEl('div', 'aa-finance-container-delete-actions-uncertain'); uncertain.classList.add('hidden');
    const uncertainClose = createEl('button', 'aa-finance-container-delete-uncertain-close');
    const blocked = createEl('div', 'aa-finance-container-delete-actions-blocked'); blocked.classList.add('hidden');
    const blockedClose = createEl('button', 'aa-finance-container-delete-blocked-close');
    standard.appendChild(cancelBtn); standard.appendChild(confirmBtn); uncertain.appendChild(uncertainClose); blocked.appendChild(blockedClose);
    modal.appendChild(backdrop); modal.appendChild(closeBtn); modal.appendChild(title); modal.appendChild(modalError); modal.appendChild(bodyEl); modal.appendChild(amount); modal.appendChild(standard); modal.appendChild(uncertain); modal.appendChild(blocked);
    body.appendChild(modal);
    [modal, backdrop, closeBtn, title, bodyEl, amount, modalError, standard, cancelBtn, confirmBtn, uncertain, uncertainClose, blocked, blockedClose].forEach((el) => { el.ownerDocument = document; if (el.id) document._elements[el.id] = el; });
    return { document, modal, bodyEl, amount, cancelBtn, confirmBtn, uncertain, uncertainClose, blocked, blockedClose, backdrop, closeBtn, modalError };
}

function buildContainerDeleteModalInDom(document) {
    const dom = buildDeleteModalDom();
    Object.assign(document._elements, dom.document._elements);
    document.body.appendChild(dom.modal);
    return dom;
}

function buildFullDom() {
    const document = { _elements: Object.create(null), activeElement: null, body: null, createElement(tag) { const el = createEl(tag); el.ownerDocument = this; return el; }, getElementById(id) { return this._elements[id] || null; }, addEventListener(type, h) { this._listeners = this._listeners || Object.create(null); (this._listeners[type] = this._listeners[type] || []).push(h); }, dispatch(type, e) { (this._listeners?.[type] || []).forEach((fn) => fn(Object.assign({ type, key: e?.key || '', shiftKey: !!e?.shiftKey, preventDefault() {} }, e || {}))); } };
    const body = createEl('body'); body.ownerDocument = document; document.body = body;
    const root = createEl('div', 'aa-finance-root'); root.dataset = Object.create(null);
    const listContainer = createEl('div', 'aa-finance-list-container');
    const statusEl = createEl('div', 'aa-finance-status'); statusEl.setAttribute('tabindex', '-1');
    const gridEl = createEl('div', 'aa-finance-grid');
    const paginationEl = createEl('div', 'aa-finance-pagination');
    const prevBtn = createEl('button', 'aa-finance-prev'); const nextBtn = createEl('button', 'aa-finance-next'); const pageIndicatorEl = createEl('span', 'aa-finance-page-indicator');
    paginationEl.appendChild(prevBtn); paginationEl.appendChild(pageIndicatorEl); paginationEl.appendChild(nextBtn);
    listContainer.appendChild(statusEl); listContainer.appendChild(paginationEl); listContainer.appendChild(gridEl);
    const openCreateBtn = createEl('button', 'aa-finance-open-create-btn');
    const createModal = createEl('div', 'aa-finance-create-modal'); createModal.classList.add('hidden');
    const modalBackdrop = createEl('div', 'aa-finance-modal-backdrop');
    const modalCloseBtn = createEl('button', 'aa-finance-modal-close-btn');
    const createForm = createEl('form', 'aa-finance-create-form');
    const modalErrorEl = createEl('div', 'aa-finance-modal-error'); modalErrorEl.classList.add('hidden');
    const titleInput = createEl('input', 'aa-finance-create-title');
    const titleErrorEl = createEl('p', 'aa-finance-title-error'); titleErrorEl.classList.add('hidden');
    const detailsInput = createEl('textarea', 'aa-finance-create-details');
    const detailsErrorEl = createEl('p', 'aa-finance-details-error'); detailsErrorEl.classList.add('hidden');
    const standardActionsEl = createEl('div', 'aa-finance-modal-actions-standard');
    const cancelBtn = createEl('button', 'aa-finance-modal-cancel-btn');
    const submitBtn = createEl('button', 'aa-finance-modal-submit-btn'); submitBtn.type = 'submit';
    const uncertainActionsEl = createEl('div', 'aa-finance-modal-actions-uncertain'); uncertainActionsEl.classList.add('hidden');
    const uncertainCloseBtn = createEl('button', 'aa-finance-modal-uncertain-close-btn');
    const blockedActionsEl = createEl('div', 'aa-finance-modal-actions-blocked'); blockedActionsEl.classList.add('hidden');
    const blockedCloseBtn = createEl('button', 'aa-finance-modal-blocked-close-btn');
    standardActionsEl.appendChild(cancelBtn); standardActionsEl.appendChild(submitBtn);
    createForm.appendChild(modalErrorEl); createForm.appendChild(titleInput); createForm.appendChild(titleErrorEl);
    createForm.appendChild(detailsInput); createForm.appendChild(detailsErrorEl); createForm.appendChild(standardActionsEl);
    createForm.appendChild(uncertainActionsEl); uncertainActionsEl.appendChild(uncertainCloseBtn);
    createForm.appendChild(blockedActionsEl); blockedActionsEl.appendChild(blockedCloseBtn);
    createModal.appendChild(modalBackdrop); createModal.appendChild(modalCloseBtn); createModal.appendChild(createForm);
    const recordsContainer = createEl('div', 'aa-finance-records-container'); recordsContainer.classList.add('hidden'); recordsContainer.hidden = true;
    const recordsBackBtn = createEl('button', 'aa-finance-records-back');
    const recordsHeadingEl = createEl('h3', 'aa-finance-records-heading'); recordsHeadingEl.setAttribute('tabindex', '-1');
    const recordsSummaryEl = createEl('div', 'aa-finance-records-summary');
    const recordsStatusEl = createEl('div', 'aa-finance-records-status'); recordsStatusEl.setAttribute('tabindex', '-1');
    const recordsGridEl = createEl('div', 'aa-finance-records-grid');
    const recordsPaginationEl = createEl('div', 'aa-finance-records-pagination'); recordsPaginationEl.hidden = true;
    const recordsPrevBtn = createEl('button', 'aa-finance-records-prev'); const recordsNextBtn = createEl('button', 'aa-finance-records-next'); const recordsPageIndicatorEl = createEl('span', 'aa-finance-records-page-indicator');
    recordsPaginationEl.appendChild(recordsPrevBtn); recordsPaginationEl.appendChild(recordsPageIndicatorEl); recordsPaginationEl.appendChild(recordsNextBtn);
    recordsContainer.appendChild(recordsBackBtn); recordsContainer.appendChild(recordsHeadingEl); recordsContainer.appendChild(recordsSummaryEl);
    recordsContainer.appendChild(recordsStatusEl); recordsContainer.appendChild(recordsPaginationEl); recordsContainer.appendChild(recordsGridEl);
    const openRecordBtn = createEl('button', 'aa-finance-open-record-btn'); openRecordBtn.classList.add('hidden'); openRecordBtn.hidden = true;
    root.appendChild(openCreateBtn); root.appendChild(listContainer); root.appendChild(recordsContainer); root.appendChild(openRecordBtn); root.appendChild(createModal);
    const containerDeleteModal = createEl('div', 'aa-finance-container-delete-modal'); containerDeleteModal.classList.add('hidden');
    const containerDeleteBackdrop = createEl('div', 'aa-finance-container-delete-modal-backdrop');
    const containerDeleteCloseBtn = createEl('button', 'aa-finance-container-delete-close');
    const containerDeleteTitleEl = createEl('h3', 'aa-finance-container-delete-modal-title');
    const containerDeleteBodyEl = createEl('p', 'aa-finance-container-delete-body');
    const containerDeleteAmountEl = createEl('p', 'aa-finance-container-delete-amount'); containerDeleteAmountEl.classList.add('hidden');
    const containerDeleteErrorEl = createEl('div', 'aa-finance-container-delete-error'); containerDeleteErrorEl.classList.add('hidden');
    const containerDeleteStandardActionsEl = createEl('div', 'aa-finance-container-delete-actions-standard');
    const containerDeleteCancelBtn = createEl('button', 'aa-finance-container-delete-cancel');
    const containerDeleteConfirmBtn = createEl('button', 'aa-finance-container-delete-confirm');
    const containerDeleteUncertainActionsEl = createEl('div', 'aa-finance-container-delete-actions-uncertain'); containerDeleteUncertainActionsEl.classList.add('hidden');
    const containerDeleteUncertainCloseBtn = createEl('button', 'aa-finance-container-delete-uncertain-close');
    const containerDeleteBlockedActionsEl = createEl('div', 'aa-finance-container-delete-actions-blocked'); containerDeleteBlockedActionsEl.classList.add('hidden');
    const containerDeleteBlockedCloseBtn = createEl('button', 'aa-finance-container-delete-blocked-close');
    containerDeleteStandardActionsEl.appendChild(containerDeleteCancelBtn); containerDeleteStandardActionsEl.appendChild(containerDeleteConfirmBtn);
    containerDeleteUncertainActionsEl.appendChild(containerDeleteUncertainCloseBtn); containerDeleteBlockedActionsEl.appendChild(containerDeleteBlockedCloseBtn);
    containerDeleteModal.appendChild(containerDeleteBackdrop); containerDeleteModal.appendChild(containerDeleteCloseBtn); containerDeleteModal.appendChild(containerDeleteTitleEl);
    containerDeleteModal.appendChild(containerDeleteErrorEl); containerDeleteModal.appendChild(containerDeleteBodyEl); containerDeleteModal.appendChild(containerDeleteAmountEl);
    containerDeleteModal.appendChild(containerDeleteStandardActionsEl); containerDeleteModal.appendChild(containerDeleteUncertainActionsEl); containerDeleteModal.appendChild(containerDeleteBlockedActionsEl);
    body.appendChild(root); body.appendChild(containerDeleteModal);
    [root, listContainer, statusEl, gridEl, paginationEl, prevBtn, nextBtn, pageIndicatorEl, openCreateBtn, createModal, modalBackdrop, modalCloseBtn, createForm, modalErrorEl, titleInput, titleErrorEl, detailsInput, detailsErrorEl, standardActionsEl, cancelBtn, submitBtn, uncertainActionsEl, uncertainCloseBtn, blockedActionsEl, blockedCloseBtn, recordsContainer, recordsBackBtn, recordsHeadingEl, recordsSummaryEl, recordsStatusEl, recordsGridEl, recordsPaginationEl, recordsPrevBtn, recordsNextBtn, recordsPageIndicatorEl, openRecordBtn, containerDeleteModal, containerDeleteBackdrop, containerDeleteCloseBtn, containerDeleteTitleEl, containerDeleteBodyEl, containerDeleteAmountEl, containerDeleteErrorEl, containerDeleteStandardActionsEl, containerDeleteCancelBtn, containerDeleteConfirmBtn, containerDeleteUncertainActionsEl, containerDeleteUncertainCloseBtn, containerDeleteBlockedActionsEl, containerDeleteBlockedCloseBtn].forEach((el) => { el.ownerDocument = document; if (el.id) document._elements[el.id] = el; });
    return { document, modal: containerDeleteModal, bodyEl: containerDeleteBodyEl, amount: containerDeleteAmountEl, cancelBtn: containerDeleteCancelBtn, confirmBtn: containerDeleteConfirmBtn, uncertain: containerDeleteUncertainActionsEl, uncertainClose: containerDeleteUncertainCloseBtn, blocked: containerDeleteBlockedActionsEl, blockedClose: containerDeleteBlockedCloseBtn, backdrop: containerDeleteBackdrop, closeBtn: containerDeleteCloseBtn, modalError: containerDeleteErrorEl, root, statusEl, gridEl, paginationEl, prevBtn, nextBtn, pageIndicatorEl, openCreateBtn, createModal, createForm, titleInput, detailsInput, submitBtn, cancelBtn, recordsContainer, recordsBackBtn, recordsGridEl, recordsStatusEl, recordsHeadingEl };
}

async function flushMicrotasks() { await new Promise(hostSetImmediate); }
async function waitDelay(ms) { await new Promise((r) => hostSetTimeout(r, ms)); }

function bootDeleteController(options) {
    options = options || {};
    const dom = options.dom || buildDeleteModalDom();
    const timerCtrl = options.timerCtrl || createTimerController();
    const fetchCalls = [];
    const deleteDeferred = options.deleteDeferred || null;
    const getDeferred = options.getDeferred || null;
    const callbacks = { onFlowStateChange: [], onRefreshRequested: [], onReviewPending: [], onReviewUncertain: [], onFocusStatus: 0 };
    const sandbox = {
        document: dom.document, window: {}, FormData: buildTestFormData(),
        setTimeout: timerCtrl.setTimeout.bind(timerCtrl), clearTimeout: timerCtrl.clearTimeout.bind(timerCtrl),
        fetch(url, opts) {
            fetchCalls.push({ url, opts });
            if (isDeleteContainerFetch(opts)) {
                if (deleteDeferred) return deleteDeferred.promise;
                if (options.deleteResponse) return Promise.resolve(options.deleteResponse(opts));
            }
            if (isGetContainerFetch(opts)) {
                if (getDeferred) return getDeferred.promise;
                if (options.getResponse) return Promise.resolve(options.getResponse(opts));
            }
            return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: {} } }));
        },
        AbortController: hostAbortController, AbortSignal: hostAbortSignal
    };
    vm.runInNewContext(deleteModuleSrc, sandbox);
    const cfg = JSON.parse(JSON.stringify(options.financeData || FINANCE_DATA));
    if (options.stripDeleteAction) delete cfg.actions.deleteContainer;
    const ctrl = sandbox.window.AA_FinanceContainerDelete.createController({
        cfg,
        elements: { modal: dom.modal, backdrop: dom.backdrop, closeBtn: dom.closeBtn, title: dom.document.getElementById('aa-finance-container-delete-modal-title'), body: dom.bodyEl, amount: dom.amount, modalError: dom.modalError, standardActions: dom.document.getElementById('aa-finance-container-delete-actions-standard'), cancelBtn: dom.cancelBtn, confirmBtn: dom.confirmBtn, uncertainActions: dom.uncertain, uncertainCloseBtn: dom.uncertainClose, blockedActions: dom.blocked, blockedCloseBtn: dom.blockedClose },
        isListActive: options.isListActive || (() => true),
        isDeleteAllowed: options.isDeleteAllowed || (() => true),
        onFlowStateChange: (p) => callbacks.onFlowStateChange.push(p),
        onRefreshRequested: (p) => callbacks.onRefreshRequested.push(p),
        onReviewPending: (p) => { callbacks.onReviewPending.push(p); return options.reviewToken || 42; },
        onReviewUncertain: (p) => callbacks.onReviewUncertain.push(p),
        onFocusStatus: () => { callbacks.onFocusStatus++; }
    });
    return { ctrl, dom, sandbox, timerCtrl, fetchCalls, callbacks, deleteDeferred, getDeferred, cleanup() { timerCtrl.flushAll(); if (deleteDeferred && !deleteDeferred.settled) { const err = new Error('Aborted'); err.name = 'AbortError'; deleteDeferred.reject(err); } if (getDeferred && !getDeferred.settled) { const err = new Error('Aborted'); err.name = 'AbortError'; getDeferred.reject(err); } ctrl.destroy(); } };
}

function isListRecordsFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_list_finance_records';
}

function recordsEnvelope(overrides) {
    return Object.assign({
        container: Object.assign({}, SAMPLE_CONTAINER),
        items: [],
        page: 1,
        per_page: 15,
        total: 0,
        total_pages: 0,
        has_previous: false,
        has_next: false,
        amount_total: '0.00'
    }, overrides || {});
}

function bootFull(options) {
    options = options || {};
    const dom = options.dom || buildFullDom();
    const timerCtrl = createTimerController();
    const fetchCalls = [];
    const deleteDeferred = options.deleteDeferred || null;
    const getDeferred = options.getDeferred || null;
    const listDeferred = options.listDeferred || null;
    let listCallCount = 0;
    const sandbox = {
        document: dom.document,
        window: { AA_FINANCE_DATA: options.financeData || FINANCE_DATA },
        FormData: buildTestFormData(),
        setTimeout: timerCtrl.setTimeout.bind(timerCtrl),
        clearTimeout: timerCtrl.clearTimeout.bind(timerCtrl),
        fetch(url, opts) {
            fetchCalls.push({ url, opts });
            if (isDeleteContainerFetch(opts)) {
                if (deleteDeferred) return deleteDeferred.promise;
                if (options.deleteResponse) return Promise.resolve(options.deleteResponse(opts));
            }
            if (isGetContainerFetch(opts)) {
                if (getDeferred) return getDeferred.promise;
                if (options.getResponse) return Promise.resolve(options.getResponse(opts));
            }
            if (isCreateContainerFetch(opts)) {
                if (options.createDeferred) return options.createDeferred.promise;
                if (options.createResponse) return Promise.resolve(options.createResponse(opts));
            }
            if (isCreateRecordFetch(opts)) {
                if (options.createRecordDeferred) return options.createRecordDeferred.promise;
            }
            if (isListContainersFetch(opts)) {
                listCallCount++;
                if (listDeferred) return listDeferred.promise;
                if (options.listResponse) return Promise.resolve(options.listResponse(opts, listCallCount));
                return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: options.listData || AUTHORITATIVE_LIST_DATA } }));
            }
            if (isListRecordsFetch(opts)) {
                if (options.recordsResponse) return Promise.resolve(options.recordsResponse(opts));
                return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } }));
            }
            return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: AUTHORITATIVE_LIST_DATA } }));
        },
        AbortController: hostAbortController,
        AbortSignal: hostAbortSignal
    };
    if (options.includeRecordsFactory !== false) vm.runInNewContext(recordsModuleSrc, sandbox);
    if (options.includeCreateFactory !== false) vm.runInNewContext(createModuleSrc, sandbox);
    if (options.includeRecordDeleteFactory !== false) vm.runInNewContext(recordDeleteModuleSrc, sandbox);
    vm.runInNewContext(deleteModuleSrc, sandbox);
    vm.runInNewContext(orchestratorModuleSrc, sandbox);
    return { dom, sandbox, timerCtrl, fetchCalls, deleteDeferred, getDeferred, listDeferred, listCallCountRef: () => listCallCount, cleanup() { timerCtrl.flushAll(); if (deleteDeferred && !deleteDeferred.settled) deleteDeferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } })); if (getDeferred && !getDeferred.settled) getDeferred.resolve(jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'not_found', message: 'No.' } } })); if (listDeferred && !listDeferred.settled) listDeferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: AUTHORITATIVE_LIST_DATA } })); if (options.createDeferred && !options.createDeferred.settled) { const err = new Error('Aborted'); err.name = 'AbortError'; options.createDeferred.reject(err); } } };
}

async function waitForDeleteButtons(dom) {
    await flushMicrotasks();
    for (let i = 0; i < 30; i++) {
        const btn = dom.gridEl.querySelector('.aa-finance-delete-container-btn');
        if (btn) return btn;
        await waitDelay(10);
    }
    return null;
}

describe('FinanceContainerDeleteModule (Ciclo 3D4B)', () => {

    it('degrada sin factory o acciones delete/get', () => {
        const dom = buildDeleteModalDom();
        const sandbox = { document: dom.document, window: {}, FormData: buildTestFormData(), setTimeout: hostSetTimeout, clearTimeout: hostClearTimeout, fetch: () => Promise.resolve(jsonResponse({ ok: true, body: { success: true, data: {} } })), AbortController: hostAbortController };
        vm.runInNewContext(deleteModuleSrc, sandbox);
        const cfg = JSON.parse(JSON.stringify(FINANCE_DATA)); delete cfg.actions.deleteContainer;
        const ctrl = sandbox.window.AA_FinanceContainerDelete.createController({ cfg, elements: { modal: dom.modal }, isListActive: () => true });
        ctrl.openModal(CONTAINER_SNAPSHOT); assert.strictEqual(dom.modal.classList.contains('hidden'), true);
        ctrl.destroy();
    });

    it('abre modal con copy de cascada y amount null', async () => {
        const boot = bootDeleteController();
        try {
            boot.ctrl.openModal({ containerId: 7, title: 'Caja', details: null, amountTotal: null, sourcePage: 1 });
            await waitDelay(60);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), false);
            assert.ok(boot.dom.bodyEl.textContent.includes('Caja'));
            assert.ok(boot.dom.bodyEl.textContent.includes('todas sus entradas'));
            assert.strictEqual(boot.dom.amount.textContent, 'Sin importes');
            assert.strictEqual(boot.callbacks.onFlowStateChange.some((p) => p.phase === 'delete_active'), true);
        } finally { boot.cleanup(); }
    });

    it('cancelar no envía DELETE y restaura foco', async () => {
        const boot = bootDeleteController();
        try {
            const origin = createEl('button'); boot.dom.document.body.appendChild(origin);
            boot.ctrl.openModal(CONTAINER_SNAPSHOT, origin);
            await waitDelay(60);
            boot.dom.cancelBtn.dispatch('click');
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteContainerFetch(c.opts)).length, 0);
            assert.strictEqual(boot.callbacks.onFlowStateChange.some((p) => p.phase === 'idle'), true);
        } finally { boot.cleanup(); }
    });

    it('foco inicial en Cancelar y focus trap', async () => {
        const boot = bootDeleteController();
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            assert.strictEqual(boot.dom.document.activeElement, boot.dom.cancelBtn);
            boot.dom.document.dispatch('keydown', { key: 'Tab' });
        } finally { boot.cleanup(); }
    });

    it('Escape y backdrop solo en CONFIRMING', async () => {
        const boot = bootDeleteController({ deleteDeferred: createDeferred() });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.backdrop.dispatch('click');
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            boot.dom.document.dispatch('keydown', { key: 'Escape' });
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), false);
        } finally { boot.cleanup(); }
    });

    it('snapshot autoritativo fail-closed', async () => {
        const boot = bootDeleteController();
        try {
            boot.ctrl.openModal({ containerId: 0, title: 'X', details: null, amountTotal: null, sourcePage: 1 });
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
            boot.ctrl.openModal({ containerId: 7, title: 7, details: null, amountTotal: null, sourcePage: 1 });
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
        } finally { boot.cleanup(); }
    });

    it('payload DELETE con id, no container_id', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            const call = boot.fetchCalls.find((c) => isDeleteContainerFetch(c.opts));
            assert.ok(call);
            const d = call.opts.body.data;
            assert.strictEqual(d.action, 'aa_delete_finance_container');
            assert.strictEqual(d.id, '7');
            assert.strictEqual(d.container_id, undefined);
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }));
            await flushMicrotasks();
        } finally { boot.cleanup(); }
    });

    it('doble confirmación envía una petición', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteContainerFetch(c.opts)).length, 1);
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }));
            await flushMicrotasks();
        } finally { boot.cleanup(); }
    });

    it('éxito validado refresca con sourcePage', async () => {
        const boot = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }) });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onRefreshRequested.length, 1);
            assert.strictEqual(boot.callbacks.onRefreshRequested[0].reason, 'confirmed');
            assert.strictEqual(boot.callbacks.onRefreshRequested[0].sourcePage, 2);
        } finally { boot.cleanup(); }
    });

    it('ID diferente en envelope pasa a incierto', async () => {
        const boot = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 99 } } }) });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.dom.uncertain.classList.contains('hidden'), false);
            assert.strictEqual(boot.callbacks.onFlowStateChange.some((p) => p.phase === 'invalidate_snapshot'), true);
        } finally { boot.cleanup(); }
    });

    it('no elimina optimistamente en listado integrado', async () => {
        const boot = bootFull({ deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }) });
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            const deleteBtn = await waitForDeleteButtons(dom);
            assert.ok(deleteBtn);
            const cardsBefore = dom.gridEl.querySelectorAll('.aa-finance-delete-container-btn').length;
            deleteBtn.dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            assert.strictEqual(dom.gridEl.querySelectorAll('.aa-finance-delete-container-btn').length, cardsBefore);
            await flushMicrotasks();
            await waitDelay(30);
            await flushMicrotasks();
            assert.ok(boot.fetchCalls.filter((c) => isListContainersFetch(c.opts)).length >= 2);
        } finally { boot.cleanup(); }
    });

    it('un solo DELETE total sin DELETE por registro', async () => {
        const boot = bootFull({ deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }) });
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            const deleteBtn = await waitForDeleteButtons(dom);
            deleteBtn.dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteContainerFetch(c.opts)).length, 1);
            assert.strictEqual(boot.fetchCalls.filter((c) => c.opts.body?.data?.action === 'aa_delete_finance_record').length, 0);
        } finally { boot.cleanup(); }
    });

    it('not_found en DELETE refresca neutralmente', async () => {
        const boot = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'not_found', message: 'No.' } } }) });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onRefreshRequested[0].reason, 'not_found');
            assert.ok(boot.callbacks.onRefreshRequested[0].message.includes('ya no estaba disponible'));
        } finally { boot.cleanup(); }
    });

    it('HTTP 500 y red pasan a incierto', async () => {
        const boot500 = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'Fallo.' } } }) });
        try {
            boot500.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot500.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot500.dom.uncertain.classList.contains('hidden'), false);
        } finally { boot500.cleanup(); }
        const deferred = createDeferred();
        const bootNet = bootDeleteController({ deleteDeferred: deferred });
        try {
            bootNet.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            bootNet.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            deferred.reject(new Error('Network'));
            await flushMicrotasks();
            assert.strictEqual(bootNet.dom.uncertain.classList.contains('hidden'), false);
        } finally { bootNet.cleanup(); }
    });

    it('timeout DELETE controlado sin esperar 15s reales', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            assert.strictEqual(boot.dom.uncertain.classList.contains('hidden'), false);
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }));
            await flushMicrotasks();
            assert.strictEqual(boot.dom.uncertain.classList.contains('hidden'), false);
        } finally { boot.cleanup(); }
    });

    it('respuesta DELETE tardía ignorada tras timeout', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }));
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onRefreshRequested.length, 0);
        } finally { boot.cleanup(); }
    });

    it('JSON corrupto pasa a incierto', async () => {
        const boot = bootDeleteController({
            deleteResponse: () => ({ ok: true, status: 200, json: () => Promise.reject(new Error('bad json')) })
        });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.dom.uncertain.classList.contains('hidden'), false);
        } finally { boot.cleanup(); }
    });

    it('GET confirma ausencia y refresca', async () => {
        const boot = bootDeleteController({
            deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }),
            getResponse: () => jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'not_found', message: 'No.' } } })
        });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewPending.length, 1);
            assert.strictEqual(boot.callbacks.onRefreshRequested.some((p) => p.reason === 'review_gone'), true);
            assert.strictEqual(boot.fetchCalls.filter((c) => isGetContainerFetch(c.opts)).length, 1);
        } finally { boot.cleanup(); }
    });

    it('GET confirma presencia y refresca', async () => {
        const boot = bootDeleteController({
            deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }),
            getResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { container: SAMPLE_CONTAINER } } })
        });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onRefreshRequested.some((p) => p.reason === 'review_exists'), true);
        } finally { boot.cleanup(); }
    });

    it('GET incierto ofrece retry solo GET', async () => {
        const getDeferred = createDeferred();
        const boot = bootDeleteController({
            deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }),
            getDeferred
        });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            getDeferred.reject(new Error('Network'));
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewUncertain.length, 1);
            const deleteBefore = boot.fetchCalls.filter((c) => isDeleteContainerFetch(c.opts)).length;
            boot.ctrl.retryReview();
            await flushMicrotasks();
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteContainerFetch(c.opts)).length, deleteBefore);
            assert.strictEqual(boot.fetchCalls.filter((c) => isGetContainerFetch(c.opts)).length, 2);
        } finally { boot.cleanup(); }
    });

    it('interlock con creación de contenedor SUBMITTING', async () => {
        const pendingCreateDeferred = createDeferred();
        const boot = bootFull({ createDeferred: pendingCreateDeferred, includeRecordsFactory: false, includeCreateFactory: false, includeRecordDeleteFactory: false });
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            dom.openCreateBtn.dispatch('click');
            await waitDelay(60);
            dom.titleInput.value = 'Nueva';
            dom.createForm.dispatch('submit');
            await flushMicrotasks();
            const deleteBtn = await waitForDeleteButtons(dom);
            assert.ok(deleteBtn.disabled);
        } finally {
            if (!pendingCreateDeferred.settled) {
                const err = new Error('Aborted');
                err.name = 'AbortError';
                pendingCreateDeferred.reject(err);
            }
            boot.cleanup();
        }
    });

    it('interlock con recordMutationLock simulado', async () => {
        const boot = bootDeleteController({ isDeleteAllowed: () => false });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
        } finally { boot.cleanup(); }
    });

    it('segundo delete bloqueado mientras delete_active', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            boot.ctrl.openModal({ containerId: 8, title: 'Otra', details: null, amountTotal: null, sourcePage: 1 });
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteContainerFetch(c.opts)).length, 1);
        } finally { boot.cleanup(); }
    });

    it('contenedor objetivo no abre durante review integrado', async () => {
        const getDeferred = createDeferred();
        const boot = bootFull({
            deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }),
            getDeferred
        });
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            const deleteBtn = await waitForDeleteButtons(dom);
            deleteBtn.dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            const openBtn = dom.gridEl.querySelector('.aa-finance-open-records-btn');
            openBtn.dispatch('click');
            await flushMicrotasks();
            assert.ok(dom.statusEl.textContent.includes('eliminación pendiente de revisión'));
        } finally { boot.cleanup(); }
    });

    it('otro contenedor puede leerse durante review', async () => {
        const listDataPage1 = {
            items: [
                { id: 7, family_key: 'finance', variant_key: 'general', title: 'Objetivo', details: null, amount_total: null, created_at: '2026-08-31 10:00:00' },
                { id: 8, family_key: 'finance', variant_key: 'general', title: 'Otro', details: null, amount_total: null, created_at: '2026-08-31 11:00:00' }
            ],
            page: 1, per_page: 15, total: 2, total_pages: 1, has_previous: false, has_next: false
        };
        const getDeferred = createDeferred();
        const boot = bootFull({
            listData: listDataPage1,
            deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }),
            getDeferred
        });
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            const deleteBtns = dom.gridEl.querySelectorAll('.aa-finance-delete-container-btn');
            deleteBtns[0].dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            const openBtns = dom.gridEl.querySelectorAll('.aa-finance-open-records-btn');
            openBtns[1].dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.recordsContainer.classList.contains('hidden'), false);
        } finally { boot.cleanup(); }
    });

    it('paginación no pierde token de revisión', async () => {
        const getDeferred = createDeferred();
        const boot = bootFull({
            deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }),
            getDeferred,
            listResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: Object.assign({}, AUTHORITATIVE_LIST_DATA, { page: 2, has_previous: true }) } })
        });
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            const deleteBtn = await waitForDeleteButtons(dom);
            deleteBtn.dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            dom.nextBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.fetchCalls.filter((c) => isGetContainerFetch(c.opts)).length, 1);
            getDeferred.resolve(jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'not_found', message: 'No.' } } }));
            await flushMicrotasks();
            assert.ok(boot.fetchCalls.filter((c) => isListContainersFetch(c.opts)).length >= 2);
        } finally { boot.cleanup(); }
    });

    it('navegación a detalle aborta GET y regreso reanuda token', async () => {
        const getDeferred = createDeferred();
        const listDataTwo = {
            items: [
                { id: 7, family_key: 'finance', variant_key: 'general', title: 'Objetivo', details: null, amount_total: null, created_at: '2026-08-31 10:00:00' },
                { id: 8, family_key: 'finance', variant_key: 'general', title: 'Otro', details: null, amount_total: null, created_at: '2026-08-31 11:00:00' }
            ],
            page: 1, per_page: 15, total: 2, total_pages: 1, has_previous: false, has_next: false
        };
        const boot = bootFull({
            listData: listDataTwo,
            deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }),
            getDeferred
        });
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            dom.gridEl.querySelectorAll('.aa-finance-delete-container-btn')[0].dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            const getCallsBeforeNav = boot.fetchCalls.filter((c) => isGetContainerFetch(c.opts)).length;
            dom.gridEl.querySelectorAll('.aa-finance-open-records-btn')[1].dispatch('click');
            await flushMicrotasks();
            dom.recordsBackBtn.dispatch('click');
            await flushMicrotasks();
            assert.ok(boot.fetchCalls.filter((c) => isGetContainerFetch(c.opts)).length > getCallsBeforeNav);
            assert.strictEqual(dom.gridEl.querySelectorAll('.aa-finance-delete-container-btn')[0].disabled, true);
        } finally { boot.cleanup(); }
    });

    it('volver desde registros rehabilita Eliminar si no hay locks', async () => {
        const boot = bootFull();
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            const deleteBtn = await waitForDeleteButtons(dom);
            assert.strictEqual(deleteBtn.disabled, false);
            const openBtn = dom.gridEl.querySelector('.aa-finance-open-records-btn');
            openBtn.dispatch('click');
            await flushMicrotasks();
            dom.recordsBackBtn.dispatch('click');
            await flushMicrotasks();
            const deleteBtnAfter = dom.gridEl.querySelector('.aa-finance-delete-container-btn');
            assert.strictEqual(deleteBtnAfter.disabled, false);
        } finally { boot.cleanup(); }
    });

    it('página vigente al refrescar tras cambio de paginación', async () => {
        const listFetchPages = [];
        const boot = bootFull({
            deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }),
            listResponse: (opts, count) => {
                listFetchPages.push(opts.body.data.page);
                return jsonResponse({ ok: true, status: 200, body: { success: true, data: Object.assign({}, AUTHORITATIVE_LIST_DATA, { page: Number(opts.body.data.page), has_previous: Number(opts.body.data.page) > 1 }) } });
            }
        });
        const dom = boot.dom;
        try {
            await flushMicrotasks();
            dom.nextBtn.dispatch('click');
            await flushMicrotasks();
            await waitDelay(30);
            const deleteBtn = await waitForDeleteButtons(dom);
            deleteBtn.dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            await waitDelay(30);
            await flushMicrotasks();
            assert.strictEqual(listFetchPages[listFetchPages.length - 1], '2');
        } finally {
            await waitDelay(50);
            await flushMicrotasks();
            boot.cleanup();
        }
    });

    it('destroy aborta peticiones y limpia callbacks tardíos', async () => {
        const deleteDeferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred });
        try {
            boot.ctrl.openModal(CONTAINER_SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.ctrl.destroy();
            deleteDeferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 7 } } }));
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onRefreshRequested.length, 0);
        } finally { boot.cleanup(); }
    });
});
