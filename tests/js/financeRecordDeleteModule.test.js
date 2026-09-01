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
        getRecord: 'aa_get_finance_record'
    }
};

const SAMPLE_CONTAINER = { id: 7, family_key: 'finance', variant_key: 'general', title: 'Caja', details: null, created_at: '2026-08-31 10:00:00' };
const VALID_RECORD = { id: 501, family_key: 'finance', variant_key: 'general', container_id: 7, title: 'Compra', details: null, amount: '150.85', created_at: '2026-08-31 12:00:00' };
const AUTHORITATIVE_LIST_DATA = {
    items: [{ id: 7, family_key: 'finance', variant_key: 'general', title: 'Caja', details: null, amount_total: '150.85', created_at: '2026-08-31 10:00:00' }],
    page: 1, per_page: 15, total: 1, total_pages: 1, has_previous: false, has_next: false
};

function createDeferred() {
    const deferred = { settled: false, promise: null, resolve: null, reject: null };
    deferred.promise = new Promise(function (resolve, reject) {
        deferred.resolve = function (value) { if (!deferred.settled) { deferred.settled = true; resolve(value); } };
        deferred.reject = function (reason) { if (!deferred.settled) { deferred.settled = true; reject(reason); } };
    });
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
    const document = { _elements: Object.create(null), activeElement: null, body: null, createElement(tag) { const el = createEl(tag); el.ownerDocument = this; return el; }, getElementById(id) { return this._elements[id] || null; }, addEventListener() {}, removeEventListener() {}, dispatch() {} };
    const body = createEl('body'); body.ownerDocument = document; document.body = body;
    const modal = createEl('div', 'aa-finance-record-delete-modal'); modal.classList.add('hidden');
    const backdrop = createEl('div', 'aa-finance-record-delete-modal-backdrop');
    const closeBtn = createEl('button', 'aa-finance-record-delete-close');
    const title = createEl('h3', 'aa-finance-record-delete-modal-title');
    const bodyEl = createEl('p', 'aa-finance-record-delete-body');
    const amount = createEl('p', 'aa-finance-record-delete-amount'); amount.classList.add('hidden');
    const modalError = createEl('div', 'aa-finance-record-delete-error'); modalError.classList.add('hidden');
    const standard = createEl('div', 'aa-finance-record-delete-actions-standard');
    const cancelBtn = createEl('button', 'aa-finance-record-delete-cancel');
    const confirmBtn = createEl('button', 'aa-finance-record-delete-confirm');
    const uncertain = createEl('div', 'aa-finance-record-delete-actions-uncertain'); uncertain.classList.add('hidden');
    const uncertainClose = createEl('button', 'aa-finance-record-delete-uncertain-close');
    const blocked = createEl('div', 'aa-finance-record-delete-actions-blocked'); blocked.classList.add('hidden');
    const blockedClose = createEl('button', 'aa-finance-record-delete-blocked-close');
    standard.appendChild(cancelBtn); standard.appendChild(confirmBtn); uncertain.appendChild(uncertainClose); blocked.appendChild(blockedClose);
    modal.appendChild(backdrop); modal.appendChild(closeBtn); modal.appendChild(title); modal.appendChild(modalError); modal.appendChild(bodyEl); modal.appendChild(amount); modal.appendChild(standard); modal.appendChild(uncertain); modal.appendChild(blocked);
    body.appendChild(modal);
    [modal, backdrop, closeBtn, title, bodyEl, amount, modalError, standard, cancelBtn, confirmBtn, uncertain, uncertainClose, blocked, blockedClose].forEach((el) => { el.ownerDocument = document; if (el.id) document._elements[el.id] = el; });
    return { document, modal, bodyEl, amount, cancelBtn, confirmBtn, uncertain, uncertainClose, blocked, blockedClose, backdrop, closeBtn, modalError };
}

function buildFullDom() {
    const dom = buildDeleteModalDom();
    const document = dom.document;
    const root = createEl('div', 'aa-finance-root');
    root.dataset = Object.create(null);
    document.body.appendChild(root);
    const listContainer = createEl('div', 'aa-finance-list-container');
    const statusEl = createEl('div', 'aa-finance-status'); statusEl.setAttribute('tabindex', '-1');
    const gridEl = createEl('div', 'aa-finance-grid');
    const paginationEl = createEl('div', 'aa-finance-pagination'); paginationEl.hidden = true;
    const prevBtn = createEl('button', 'aa-finance-prev'); const nextBtn = createEl('button', 'aa-finance-next'); const pageIndicatorEl = createEl('span', 'aa-finance-page-indicator');
    paginationEl.appendChild(prevBtn); paginationEl.appendChild(pageIndicatorEl); paginationEl.appendChild(nextBtn);
    listContainer.appendChild(statusEl); listContainer.appendChild(paginationEl); listContainer.appendChild(gridEl);
    const recordsContainer = createEl('div', 'aa-finance-records-container'); recordsContainer.classList.add('hidden'); recordsContainer.hidden = true;
    const recordsBackBtn = createEl('button', 'aa-finance-records-back');
    const recordsHeadingEl = createEl('h3', 'aa-finance-records-heading'); recordsHeadingEl.setAttribute('tabindex', '-1');
    const recordsSummaryEl = createEl('div', 'aa-finance-records-summary');
    const recordsStatusEl = createEl('div', 'aa-finance-records-status'); recordsStatusEl.setAttribute('tabindex', '-1');
    const recordsGridEl = createEl('div', 'aa-finance-records-grid');
    const recordsPaginationEl = createEl('div', 'aa-finance-records-pagination'); recordsPaginationEl.hidden = true;
    const recordsPrevBtn = createEl('button', 'aa-finance-records-prev'); const recordsNextBtn = createEl('button', 'aa-finance-records-next'); const recordsPageIndicatorEl = createEl('span', 'aa-finance-records-page-indicator');
    recordsPaginationEl.appendChild(recordsPrevBtn); recordsPaginationEl.appendChild(recordsPageIndicatorEl); recordsPaginationEl.appendChild(recordsNextBtn);
    const openRecordBtn = createEl('button', 'aa-finance-open-record-btn'); openRecordBtn.classList.add('hidden'); openRecordBtn.hidden = true;
    recordsContainer.appendChild(recordsBackBtn); recordsContainer.appendChild(recordsHeadingEl); recordsContainer.appendChild(recordsSummaryEl); recordsContainer.appendChild(recordsStatusEl); recordsContainer.appendChild(openRecordBtn); recordsContainer.appendChild(recordsPaginationEl); recordsContainer.appendChild(recordsGridEl);
    const openCreateBtn = createEl('button', 'aa-finance-open-create-btn');
    const createModal = createEl('div', 'aa-finance-create-modal'); createModal.classList.add('hidden');
    const modalBackdrop = createEl('div', 'aa-finance-modal-backdrop'); const modalCloseBtn = createEl('button', 'aa-finance-modal-close-btn');
    const createForm = createEl('form', 'aa-finance-create-form'); const modalErrorEl = createEl('div', 'aa-finance-modal-error');
    const titleInput = createEl('input', 'aa-finance-create-title'); const titleErrorEl = createEl('p', 'aa-finance-title-error');
    const detailsInput = createEl('textarea', 'aa-finance-create-details'); const detailsErrorEl = createEl('p', 'aa-finance-details-error');
    const standardActionsEl = createEl('div', 'aa-finance-modal-actions-standard'); const cancelBtn = createEl('button', 'aa-finance-modal-cancel-btn'); const submitBtn = createEl('button', 'aa-finance-modal-submit-btn');
    const uncertainActionsEl = createEl('div', 'aa-finance-modal-actions-uncertain'); const uncertainCloseBtn = createEl('button', 'aa-finance-modal-uncertain-close-btn');
    const blockedActionsEl = createEl('div', 'aa-finance-modal-actions-blocked'); const blockedCloseBtn = createEl('button', 'aa-finance-modal-blocked-close-btn');
    standardActionsEl.appendChild(cancelBtn); standardActionsEl.appendChild(submitBtn);
    createForm.appendChild(modalErrorEl); createForm.appendChild(titleInput); createForm.appendChild(detailsInput); createForm.appendChild(standardActionsEl); createForm.appendChild(uncertainActionsEl); createForm.appendChild(blockedActionsEl);
    createModal.appendChild(modalBackdrop); createModal.appendChild(modalCloseBtn); createModal.appendChild(createForm);
    const recordCreateModal = createEl('div', 'aa-finance-record-create-modal'); recordCreateModal.classList.add('hidden');
    const recordModalBackdrop = createEl('div', 'aa-finance-record-create-modal-backdrop'); const recordModalCloseBtn = createEl('button', 'aa-finance-record-create-close');
    const recordCreateForm = createEl('form', 'aa-finance-record-create-form'); const recordModalErrorEl = createEl('div', 'aa-finance-record-create-error');
    const recordTitleInput = createEl('input', 'aa-finance-record-create-title'); const recordTitleErrorEl = createEl('p', 'aa-finance-record-title-error');
    const recordDetailsInput = createEl('textarea', 'aa-finance-record-create-details'); const recordDetailsErrorEl = createEl('p', 'aa-finance-record-details-error');
    const recordAmountInput = createEl('input', 'aa-finance-record-create-amount'); const recordAmountErrorEl = createEl('p', 'aa-finance-record-amount-error');
    const recordStandardActionsEl = createEl('div', 'aa-finance-record-create-actions-standard'); const recordCancelBtn = createEl('button', 'aa-finance-record-create-cancel'); const recordSubmitBtn = createEl('button', 'aa-finance-record-create-submit');
    const recordUncertainActionsEl = createEl('div', 'aa-finance-record-create-actions-uncertain'); recordUncertainActionsEl.classList.add('hidden');
    const recordUncertainCloseBtn = createEl('button', 'aa-finance-record-create-uncertain-close');
    const recordBlockedActionsEl = createEl('div', 'aa-finance-record-create-actions-blocked'); recordBlockedActionsEl.classList.add('hidden');
    const recordBlockedCloseBtn = createEl('button', 'aa-finance-record-create-blocked-close');
    recordStandardActionsEl.appendChild(recordCancelBtn); recordStandardActionsEl.appendChild(recordSubmitBtn);
    recordCreateForm.appendChild(recordModalErrorEl); recordCreateForm.appendChild(recordTitleInput); recordCreateForm.appendChild(recordDetailsInput); recordCreateForm.appendChild(recordAmountInput); recordCreateForm.appendChild(recordStandardActionsEl); recordCreateForm.appendChild(recordUncertainActionsEl); recordCreateForm.appendChild(recordBlockedActionsEl);
    recordCreateModal.appendChild(recordModalBackdrop); recordCreateModal.appendChild(recordModalCloseBtn); recordCreateModal.appendChild(recordCreateForm);
    root.appendChild(openCreateBtn); root.appendChild(listContainer); root.appendChild(recordsContainer); root.appendChild(createModal); root.appendChild(recordCreateModal); root.appendChild(dom.modal);
    [root, listContainer, statusEl, gridEl, paginationEl, prevBtn, nextBtn, pageIndicatorEl, recordsContainer, recordsBackBtn, recordsHeadingEl, recordsSummaryEl, recordsStatusEl, recordsGridEl, recordsPaginationEl, recordsPrevBtn, recordsNextBtn, recordsPageIndicatorEl, openCreateBtn, createModal, modalBackdrop, modalCloseBtn, createForm, modalErrorEl, titleInput, titleErrorEl, detailsInput, detailsErrorEl, standardActionsEl, cancelBtn, submitBtn, uncertainActionsEl, uncertainCloseBtn, blockedActionsEl, blockedCloseBtn, openRecordBtn, recordCreateModal, recordModalBackdrop, recordModalCloseBtn, recordCreateForm, recordModalErrorEl, recordTitleInput, recordTitleErrorEl, recordDetailsInput, recordDetailsErrorEl, recordAmountInput, recordAmountErrorEl, recordStandardActionsEl, recordCancelBtn, recordSubmitBtn, recordUncertainActionsEl, recordUncertainCloseBtn, recordBlockedActionsEl, recordBlockedCloseBtn, dom.modal, dom.backdrop, dom.closeBtn, dom.bodyEl, dom.amount, dom.modalError, dom.cancelBtn, dom.confirmBtn, dom.uncertain, dom.uncertainClose, dom.blocked, dom.blockedClose].forEach((el) => { el.ownerDocument = document; if (el.id) document._elements[el.id] = el; });
    return Object.assign(dom, { root, statusEl, gridEl, openCreateBtn, openRecordBtn, recordsContainer, recordsBackBtn, recordsHeadingEl, recordsStatusEl, recordsGridEl, recordCreateModal, recordCreateForm, recordTitleInput, recordSubmitBtn, recordUncertainCloseBtn });
}

async function flushMicrotasks() { await new Promise(hostSetImmediate); }
async function waitDelay(ms) { await new Promise((r) => hostSetTimeout(r, ms)); }

function jsonResponse(payload) { return { ok: payload.ok !== false, status: payload.status || 200, json() { return Promise.resolve(payload.body); } }; }
function recordsEnvelope(overrides) {
    return Object.assign({ container: Object.assign({}, SAMPLE_CONTAINER), items: [Object.assign({}, VALID_RECORD)], page: 1, per_page: 15, total: 1, total_pages: 1, has_previous: false, has_next: false, amount_total: '150.85' }, overrides || {});
}
function isListContainersFetch(opts) { return opts?.body?.data?.action === 'aa_list_finance_containers'; }
function isListRecordsFetch(opts) { return opts?.body?.data?.action === 'aa_list_finance_records'; }
function isDeleteRecordFetch(opts) { return opts?.body?.data?.action === 'aa_delete_finance_record'; }
function isGetRecordFetch(opts) { return opts?.body?.data?.action === 'aa_get_finance_record'; }
function isCreateRecordFetch(opts) { return opts?.body?.data?.action === 'aa_create_finance_record'; }

const SNAPSHOT = { recordId: 501, containerId: 7, title: 'Compra', amount: '150.85', sourcePage: 2 };

function bootDeleteController(options) {
    options = options || {};
    const dom = options.dom || buildDeleteModalDom();
    const timerCtrl = options.timerCtrl || createTimerController();
    const fetchCalls = [];
    const deleteDeferred = options.deleteDeferred || null;
    const getDeferred = options.getDeferred || null;
    const callbacks = {
        onDeleteActiveStart: 0, onDeleteActiveEnd: 0, onDeleteConfirmedRefresh: [], onDeleteRecordNotFoundRefresh: [],
        onDeleteUncertainInvalidated: 0, onUncertainReviewStart: [], onReviewAwaitingListSettlement: [],
        onReviewGetUncertain: 0, onReviewRecordExistsNotice: 0, onContainerNotFoundDelete: [], onFocusDetailStatus: 0, onIdle: 0
    };
    const sandbox = {
        document: dom.document,
        window: {},
        FormData: buildTestFormData(),
        setTimeout: timerCtrl.setTimeout.bind(timerCtrl),
        clearTimeout: timerCtrl.clearTimeout.bind(timerCtrl),
        fetch(url, opts) {
            fetchCalls.push({ url, opts });
            if (isDeleteRecordFetch(opts)) {
                if (deleteDeferred) return deleteDeferred.promise;
                if (options.deleteResponse) return Promise.resolve(options.deleteResponse(opts));
            }
            if (isGetRecordFetch(opts)) {
                if (getDeferred) return getDeferred.promise;
                if (options.getResponse) return Promise.resolve(options.getResponse(opts));
            }
            return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: {} } }));
        },
        AbortController: hostAbortController,
        AbortSignal: hostAbortSignal
    };
    vm.runInNewContext(deleteModuleSrc, sandbox);
    const cfg = JSON.parse(JSON.stringify(options.financeData || FINANCE_DATA));
    if (options.stripDeleteAction) delete cfg.actions.deleteRecord;
    const ctrl = sandbox.window.AA_FinanceRecordDelete.createController({
        cfg,
        elements: { modal: dom.modal, backdrop: dom.backdrop, closeBtn: dom.closeBtn, title: dom.document.getElementById('aa-finance-record-delete-modal-title'), body: dom.bodyEl, amount: dom.amount, modalError: dom.modalError, standardActions: dom.document.getElementById('aa-finance-record-delete-actions-standard'), cancelBtn: dom.cancelBtn, confirmBtn: dom.confirmBtn, uncertainActions: dom.uncertain, uncertainCloseBtn: dom.uncertainClose, blockedActions: dom.blocked, blockedCloseBtn: dom.blockedClose },
        isDetailActive: options.isDetailActive || (() => true),
        isDeleteAllowed: options.isDeleteAllowed || (() => true),
        onDeleteActiveStart: () => { callbacks.onDeleteActiveStart++; },
        onDeleteActiveEnd: () => { callbacks.onDeleteActiveEnd++; },
        onDeleteConfirmedRefresh: (c, p) => { callbacks.onDeleteConfirmedRefresh.push({ containerId: c, sourcePage: p }); },
        onDeleteRecordNotFoundRefresh: (c, p) => { callbacks.onDeleteRecordNotFoundRefresh.push({ containerId: c, sourcePage: p }); },
        onDeleteUncertainInvalidated: () => { callbacks.onDeleteUncertainInvalidated++; },
        onUncertainReviewStart: (p) => { callbacks.onUncertainReviewStart.push(p); return options.reviewToken || 42; },
        onReviewAwaitingListSettlement: (p) => { callbacks.onReviewAwaitingListSettlement.push(p); },
        onReviewGetUncertain: () => { callbacks.onReviewGetUncertain++; },
        onReviewRecordExistsNotice: () => { callbacks.onReviewRecordExistsNotice++; },
        onContainerNotFoundDelete: (id) => { callbacks.onContainerNotFoundDelete.push(id); },
        onFocusDetailStatus: () => { callbacks.onFocusDetailStatus++; },
        onIdle: () => { callbacks.onIdle++; }
    });
    return {
        ctrl, dom, sandbox, timerCtrl, fetchCalls, callbacks, deleteDeferred, getDeferred,
        cleanup() {
            timerCtrl.flushAll();
            if (deleteDeferred && !deleteDeferred.settled) {
                const err = new Error('Aborted'); err.name = 'AbortError'; deleteDeferred.reject(err);
            }
            if (getDeferred && !getDeferred.settled) {
                const err = new Error('Aborted'); err.name = 'AbortError'; getDeferred.reject(err);
            }
            ctrl.destroy();
        }
    };
}

function bootFull(options) {
    options = options || {};
    const dom = options.dom || buildFullDom();
    const timerCtrl = createTimerController();
    const fetchCalls = [];
    const deleteDeferred = options.deleteDeferred || null;
    const getDeferred = options.getDeferred || null;
    const recordsDeferred = options.recordsDeferred || null;
    const defaultRecordsResponse = () => jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } });
    const sandbox = {
        document: dom.document,
        window: { AA_FINANCE_DATA: options.financeData || FINANCE_DATA },
        FormData: buildTestFormData(),
        setTimeout: timerCtrl.setTimeout.bind(timerCtrl),
        clearTimeout: timerCtrl.clearTimeout.bind(timerCtrl),
        fetch(url, opts) {
            fetchCalls.push({ url, opts });
            if (isDeleteRecordFetch(opts)) {
                if (deleteDeferred) return deleteDeferred.promise;
                if (options.deleteResponse) return Promise.resolve(options.deleteResponse(opts));
            }
            if (isCreateRecordFetch(opts)) {
                if (options.createRecordDeferred) return options.createRecordDeferred.promise;
                if (options.createRecordResponse) return Promise.resolve(options.createRecordResponse(opts));
            }
            if (isGetRecordFetch(opts)) {
                if (getDeferred) return getDeferred.promise;
                if (options.getResponse) return Promise.resolve(options.getResponse(opts));
            }
            if (isListRecordsFetch(opts)) {
                if (recordsDeferred) return recordsDeferred.promise;
                if (options.recordsResponse) return Promise.resolve(options.recordsResponse(opts));
                return Promise.resolve(defaultRecordsResponse());
            }
            if (isListContainersFetch(opts)) {
                return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: options.listData || AUTHORITATIVE_LIST_DATA } }));
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
    return { dom, sandbox, timerCtrl, fetchCalls, deleteDeferred, getDeferred, recordsDeferred, cleanup() { timerCtrl.flushAll(); if (deleteDeferred && !deleteDeferred.settled) { deleteDeferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } })); } if (getDeferred && !getDeferred.settled) { getDeferred.resolve(jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'record_not_found', message: 'No.' } } })); } if (options.createRecordDeferred && !options.createRecordDeferred.settled) { const err = new Error('Aborted'); err.name = 'AbortError'; options.createRecordDeferred.reject(err); } if (recordsDeferred && !recordsDeferred.settled) recordsDeferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } })); } };
}

async function openDetail(dom) {
    await flushMicrotasks();
    let openBtn = null;
    for (let attempt = 0; attempt < 30; attempt++) {
        openBtn = dom.gridEl.querySelector('.aa-finance-open-records-btn');
        if (openBtn) {
            break;
        }
        await waitDelay(10);
    }
    assert.ok(openBtn, 'botón Ver registros renderizado');
    openBtn.dispatch('click');
    await flushMicrotasks();
    await waitDelay(20);
}

describe('FinanceRecordDeleteModule (Ciclo 3D4A)', () => {

    it('degrada sin factory o acciones delete/get', () => {
        const dom = buildDeleteModalDom();
        const sandbox = { document: dom.document, window: {}, FormData: buildTestFormData(), setTimeout: hostSetTimeout, clearTimeout: hostClearTimeout, fetch: () => Promise.resolve(jsonResponse({ ok: true, body: { success: true, data: {} } })), AbortController: hostAbortController };
        vm.runInNewContext(deleteModuleSrc, sandbox);
        const cfg = JSON.parse(JSON.stringify(FINANCE_DATA)); delete cfg.actions.deleteRecord;
        const ctrl = sandbox.window.AA_FinanceRecordDelete.createController({ cfg, elements: { modal: dom.modal }, isDetailActive: () => true });
        ctrl.openModal(SNAPSHOT); assert.strictEqual(dom.modal.classList.contains('hidden'), true);
        ctrl.destroy();
    });

    it('abre modal con identidad autoritativa y amount null', async () => {
        const boot = bootDeleteController();
        try {
            boot.ctrl.openModal({ recordId: 501, containerId: 7, title: 'Entrada', amount: null, sourcePage: 1 });
            await waitDelay(60);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), false);
            assert.ok(boot.dom.bodyEl.textContent.includes('Entrada'));
            assert.strictEqual(boot.dom.amount.textContent, 'Sin importe');
            assert.strictEqual(boot.callbacks.onDeleteActiveStart, 1);
        } finally { boot.cleanup(); }
    });

    it('cancelar no envía DELETE y restaura foco', async () => {
        const boot = bootDeleteController();
        try {
            const origin = createEl('button'); boot.dom.document.body.appendChild(origin);
            boot.ctrl.openModal(SNAPSHOT, origin);
            await waitDelay(60);
            boot.dom.cancelBtn.dispatch('click');
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteRecordFetch(c.opts)).length, 0);
            assert.strictEqual(boot.callbacks.onDeleteActiveEnd, 1);
        } finally { boot.cleanup(); }
    });

    it('foco inicial en Cancelar y focus trap', async () => {
        const boot = bootDeleteController();
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            assert.strictEqual(boot.dom.document.activeElement, boot.dom.cancelBtn);
            boot.dom.document.dispatch('keydown', { key: 'Tab' });
        } finally { boot.cleanup(); }
    });

    it('Escape y backdrop solo en CONFIRMING', async () => {
        const boot = bootDeleteController({ deleteDeferred: createDeferred() });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.backdrop.dispatch('click');
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            boot.dom.document.dispatch('keydown', { key: 'Escape' });
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), false);
        } finally { boot.cleanup(); }
    });

    it('payload DELETE exacto sin alias id', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            const call = boot.fetchCalls.find((c) => isDeleteRecordFetch(c.opts));
            assert.ok(call);
            const d = call.opts.body.data;
            assert.strictEqual(d.action, 'aa_delete_finance_record');
            assert.strictEqual(d._wpnonce, 'test_nonce');
            assert.strictEqual(d.container_id, '7');
            assert.strictEqual(d.record_id, '501');
            assert.strictEqual(d.variant_key, 'general');
            assert.strictEqual(d.id, undefined);
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } }));
            await flushMicrotasks();
        } finally { boot.cleanup(); }
    });

    it('preserva amount como string y snapshot inmutable', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            const snap = { recordId: 501, containerId: 7, title: 'X', amount: '99.01', sourcePage: 3 };
            boot.ctrl.openModal(snap);
            snap.amount = 'changed';
            await waitDelay(60);
            assert.strictEqual(boot.dom.amount.textContent, '99.01');
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } }));
            await flushMicrotasks();
        } finally { boot.cleanup(); }
    });

    it('doble confirmación envía una petición', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteRecordFetch(c.opts)).length, 1);
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } }));
            await flushMicrotasks();
        } finally { boot.cleanup(); }
    });

    it('éxito validado contra IDs refresca sourcePage', async () => {
        const boot = bootDeleteController({
            deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } })
        });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onDeleteConfirmedRefresh.length, 1);
            assert.deepStrictEqual(boot.callbacks.onDeleteConfirmedRefresh[0], { containerId: 7, sourcePage: 2 });
        } finally { boot.cleanup(); }
    });

    it('envelope 200 corrupto pasa a incierto', async () => {
        const boot = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 999, container_id: 7 } } }) });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.dom.uncertain.classList.contains('hidden'), false);
            assert.strictEqual(boot.callbacks.onDeleteUncertainInvalidated, 1);
        } finally { boot.cleanup(); }
    });

    it('no elimina optimistamente y refresca tras éxito integrado', async () => {
        const boot = bootFull({
            recordsResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } }),
            deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } })
        });
        const dom = boot.dom;
        try {
            await openDetail(dom);
            const deleteBtn = dom.recordsGridEl.querySelector('.aa-finance-delete-record-btn');
            assert.ok(deleteBtn);
            const cardsBefore = dom.recordsGridEl.querySelectorAll('.aa-finance-delete-record-btn').length;
            deleteBtn.dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            assert.strictEqual(dom.recordsGridEl.querySelectorAll('.aa-finance-delete-record-btn').length, cardsBefore);
            await flushMicrotasks();
            const listCalls = boot.fetchCalls.filter((c) => isListRecordsFetch(c.opts));
            assert.ok(listCalls.length >= 2);
        } finally { boot.cleanup(); }
    });

    it('clamp último item y contenedor vacío con amount_total autoritativo', async () => {
        let listCalls = 0;
        const boot = bootFull({
            recordsResponse() {
                listCalls++;
                if (listCalls === 1) {
                    return jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope({ page: 1, items: [Object.assign({}, VALID_RECORD)], total: 1, total_pages: 1, amount_total: '150.85' }) } });
                }
                return jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope({ page: 1, items: [], total: 0, total_pages: 0, amount_total: '0.00' }) } });
            },
            deleteResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } })
        });
        const dom = boot.dom;
        try {
            await openDetail(dom);
            const deleteBtn = dom.recordsGridEl.querySelector('.aa-finance-delete-record-btn');
            assert.ok(deleteBtn);
            deleteBtn.dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            await flushMicrotasks();
            assert.ok(boot.fetchCalls.filter((c) => isListRecordsFetch(c.opts)).length >= 2);
        } finally { boot.cleanup(); }
    });

    it('record_not_found en DELETE refresca neutralmente', async () => {
        const boot = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'record_not_found', message: 'No existe.' } } }) });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onDeleteRecordNotFoundRefresh.length, 1);
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteRecordFetch(c.opts)).length, 1);
        } finally { boot.cleanup(); }
    });

    it('container_not_found en DELETE notifica orquestador', async () => {
        const boot = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'container_not_found', message: 'No.' } } }) });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onContainerNotFoundDelete[0], 7);
        } finally { boot.cleanup(); }
    });

    it('nonce/auth/variant bloqueantes', async () => {
        for (const code of ['bad_nonce', 'unauthorized', 'forbidden', 'invalid_variant_key', 'unknown_variant']) {
            const boot = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: false, status: 403, body: { success: false, data: { code, message: 'Bloqueado.' } } }) });
            try {
                boot.ctrl.openModal(SNAPSHOT);
                await waitDelay(60);
                boot.dom.confirmBtn.dispatch('click');
                await flushMicrotasks();
                assert.strictEqual(boot.dom.blocked.classList.contains('hidden'), false);
            } finally { boot.cleanup(); }
        }
    });

    it('HTTP 500 y red pasan a incierto', async () => {
        const boot500 = bootDeleteController({ deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'Fallo.' } } }) });
        try {
            boot500.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot500.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot500.dom.uncertain.classList.contains('hidden'), false);
        } finally { boot500.cleanup(); }
        const deferred = createDeferred();
        const bootNet = bootDeleteController({ deleteDeferred: deferred });
        try {
            bootNet.ctrl.openModal(SNAPSHOT);
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
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            assert.strictEqual(boot.dom.uncertain.classList.contains('hidden'), false);
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } }));
            await flushMicrotasks();
            assert.strictEqual(boot.dom.uncertain.classList.contains('hidden'), false);
        } finally { boot.cleanup(); }
    });

    it('respuesta DELETE tardía ignorada tras timeout', async () => {
        const deferred = createDeferred();
        const boot = bootDeleteController({ deleteDeferred: deferred });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } }));
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onDeleteConfirmedRefresh.length, 0);
        } finally { boot.cleanup(); }
    });

    it('GET confirma ausencia', async () => {
        const boot = bootDeleteController({ getResponse: () => jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'record_not_found', message: 'No.' } } }) });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewAwaitingListSettlement.length, 1);
            assert.strictEqual(boot.callbacks.onReviewAwaitingListSettlement[0].outcome, 'gone');
        } finally { boot.cleanup(); }
    });

    it('GET confirma existencia', async () => {
        const boot = bootDeleteController({ getResponse: () => jsonResponse({ ok: true, status: 200, body: { success: true, data: { record: Object.assign({}, VALID_RECORD) } } }) });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewAwaitingListSettlement[0].outcome, 'exists');
        } finally { boot.cleanup(); }
    });

    it('GET incierto mantiene pending y retry repite GET', async () => {
        let getCalls = 0;
        const boot = bootDeleteController({ getResponse: () => { getCalls++; return Promise.reject(new Error('net')); } });
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewGetUncertain, 1);
            boot.ctrl.retryReview();
            await flushMicrotasks();
            assert.ok(getCalls >= 2);
            assert.strictEqual(boot.fetchCalls.filter((c) => isDeleteRecordFetch(c.opts)).length, 1);
        } finally { boot.cleanup(); }
    });

    it('navegación aborta GET y regreso reanuda mismo token', async () => {
        const getDef = createDeferred();
        const boot = bootFull({ getDeferred: getDef, deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }) });
        const dom = boot.dom;
        try {
            await openDetail(dom);
            dom.recordsGridEl.querySelector('.aa-finance-delete-record-btn').dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            dom.recordsBackBtn.dispatch('click');
            await flushMicrotasks();
            dom.gridEl.querySelector('.aa-finance-open-records-btn').dispatch('click');
            await flushMicrotasks();
            const getCalls = boot.fetchCalls.filter((c) => isGetRecordFetch(c.opts));
            assert.ok(getCalls.length >= 1);
            getDef.resolve(jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'record_not_found', message: 'No.' } } }));
            await flushMicrotasks();
        } finally { boot.cleanup(); }
    });

    it('interlock pendingRecordReview bloquea delete', async () => {
        const createDef = createDeferred();
        const reviewRecordsDef = createDeferred();
        let recordsCall = 0;
        const boot = bootFull({
            createRecordDeferred: createDef,
            recordsResponse() {
                recordsCall++;
                if (recordsCall >= 2) {
                    return reviewRecordsDef.promise;
                }
                return jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } });
            }
        });
        const dom = boot.dom;
        try {
            await openDetail(dom);
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            dom.recordTitleInput.value = 'Draft';
            dom.recordCreateForm.dispatch('submit');
            await flushMicrotasks();
            createDef.reject(new Error('net'));
            await flushMicrotasks();
            dom.recordUncertainCloseBtn.dispatch('click');
            await flushMicrotasks();
            await waitDelay(30);
            const deleteBtn = dom.recordsGridEl.querySelector('.aa-finance-delete-record-btn');
            assert.ok(deleteBtn);
            assert.strictEqual(deleteBtn.disabled, true);
        } finally {
            reviewRecordsDef.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: recordsEnvelope() } }));
            boot.cleanup();
        }
    });

    it('delete_review bloquea creación', async () => {
        const boot = bootFull({ deleteResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed', message: 'x' } } }) });
        const dom = boot.dom;
        try {
            await openDetail(dom);
            dom.recordsGridEl.querySelector('.aa-finance-delete-record-btn').dispatch('click');
            await waitDelay(60);
            dom.confirmBtn.dispatch('click');
            await flushMicrotasks();
            dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(dom.openRecordBtn.disabled, true);
        } finally { boot.cleanup(); }
    });

    it('segundo delete bloqueado durante delete_active', async () => {
        const deferred = createDeferred();
        const boot = bootFull({
            deleteDeferred: deferred,
            recordsResponse: () => jsonResponse({
                ok: true,
                status: 200,
                body: {
                    success: true,
                    data: recordsEnvelope({
                        items: [
                            Object.assign({}, VALID_RECORD, { id: 501, title: 'A' }),
                            Object.assign({}, VALID_RECORD, { id: 502, title: 'B' })
                        ],
                        total: 2
                    })
                }
            })
        });
        const dom = boot.dom;
        try {
            await openDetail(dom);
            const buttons = dom.recordsGridEl.querySelectorAll('.aa-finance-delete-record-btn');
            buttons[0].dispatch('click');
            await waitDelay(60);
            assert.strictEqual(buttons[1].disabled, true);
            dom.cancelBtn.dispatch('click');
            await flushMicrotasks();
            deferred.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: { deleted: true, id: 501, container_id: 7 } } }));
        } finally { boot.cleanup(); }
    });

    it('foco heading tras refresh y cleanup destroy', async () => {
        const boot = bootDeleteController();
        try {
            boot.ctrl.openModal(SNAPSHOT);
            await waitDelay(60);
            boot.ctrl.destroy();
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
        } finally { boot.cleanup(); }
    });

    it('regresión lectura y creación permanecen operativas', async () => {
        const boot = bootFull();
        const dom = boot.dom;
        try {
            await openDetail(dom);
            assert.ok(dom.recordsHeadingEl.textContent.includes('Caja'));
            dom.openRecordBtn.dispatch('click');
            await waitDelay(60);
            assert.strictEqual(dom.recordCreateModal.classList.contains('hidden'), false);
        } finally { boot.cleanup(); }
    });
});
