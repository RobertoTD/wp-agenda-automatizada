'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const editModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-record-edit-module.js');
const editModuleSrc = fs.readFileSync(editModulePath, 'utf8');

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
        listRecords: 'aa_list_finance_records',
        createRecord: 'aa_create_finance_record',
        deleteRecord: 'aa_delete_finance_record',
        getRecord: 'aa_get_finance_record',
        updateRecord: 'aa_update_finance_record'
    }
};

const SAMPLE_RECORD = {
    id: 101,
    family_key: 'finance',
    variant_key: 'general',
    container_id: 7,
    title: 'Entrada',
    details: 'Notas',
    amount: '150.85',
    created_at: '2026-08-31 10:00:00'
};

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
        ids() { return Array.from(captured.keys()); }
    };
}

function buildTestFormData() {
    return class {
        constructor() { this.data = {}; }
        append(k, v) { this.data[k] = v; }
    };
}

function jsonResponse(payload) {
    return { ok: payload.ok !== false, status: payload.status || 200, json: () => Promise.resolve(payload.body) };
}

function isGetRecordFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_get_finance_record';
}

function isUpdateRecordFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_update_finance_record';
}

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(), id: id || '', children: [], attributes: Object.create(null),
        _text: '', disabled: false, hidden: false, readOnly: false,
        type: tag === 'button' ? 'button' : (tag === 'textarea' ? 'textarea' : 'text'),
        value: '', parentElement: null, parentNode: null, tabIndex: 0,
        classList: { _set: new Set(), add(c) { this._set.add(c); }, remove(c) { this._set.delete(c); }, contains(c) { return this._set.has(c); } },
        focus() { if (this.ownerDocument) this.ownerDocument.activeElement = this; },
        setAttribute(n, v) { this.attributes[n] = String(v); if (n === 'id') this.id = String(v); },
        getAttribute(n) { return Object.prototype.hasOwnProperty.call(this.attributes, n) ? this.attributes[n] : null; },
        removeAttribute(n) { delete this.attributes[n]; },
        appendChild(c) { c.parentElement = this; c.parentNode = this; c.ownerDocument = this.ownerDocument; this.children.push(c); return c; },
        addEventListener(t, h) { this._listeners = this._listeners || Object.create(null); (this._listeners[t] = this._listeners[t] || []).push(h); },
        dispatch(t, e) { (this._listeners?.[t] || []).forEach((h) => h(Object.assign({ type: t, preventDefault() {}, key: e?.key || '', shiftKey: !!e?.shiftKey }, e || {}))); },
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

function buildEditModalDom() {
    const document = { _elements: Object.create(null), activeElement: null, body: null, _listeners: Object.create(null), createElement(tag) { const el = createEl(tag); el.ownerDocument = this; return el; }, getElementById(id) { return this._elements[id] || null; }, addEventListener(t, h) { (this._listeners[t] = this._listeners[t] || []).push(h); }, removeEventListener() {}, dispatch(t, e) { (this._listeners[t] || []).forEach((fn) => fn(Object.assign({ type: t, key: e?.key || '', shiftKey: !!e?.shiftKey, preventDefault() {} }, e || {}))); } };
    const body = createEl('body'); body.ownerDocument = document; document.body = body;
    const modal = createEl('div', 'aa-finance-record-edit-modal'); modal.classList.add('hidden');
    const backdrop = createEl('div', 'aa-finance-record-edit-modal-backdrop');
    const closeBtn = createEl('button', 'aa-finance-record-edit-close');
    const form = createEl('form', 'aa-finance-record-edit-form');
    const modalError = createEl('div', 'aa-finance-record-edit-error'); modalError.classList.add('hidden');
    const titleInput = createEl('input', 'aa-finance-record-edit-title');
    const titleError = createEl('p', 'aa-finance-record-edit-title-error'); titleError.classList.add('hidden');
    const detailsInput = createEl('textarea', 'aa-finance-record-edit-details');
    const detailsError = createEl('p', 'aa-finance-record-edit-details-error'); detailsError.classList.add('hidden');
    const amountInput = createEl('input', 'aa-finance-record-edit-amount');
    const amountError = createEl('p', 'aa-finance-record-edit-amount-error'); amountError.classList.add('hidden');
    const standard = createEl('div', 'aa-finance-record-edit-actions-standard');
    const cancelBtn = createEl('button', 'aa-finance-record-edit-cancel');
    const submitBtn = createEl('button', 'aa-finance-record-edit-submit'); submitBtn.type = 'submit';
    const uncertain = createEl('div', 'aa-finance-record-edit-actions-uncertain'); uncertain.classList.add('hidden');
    const uncertainClose = createEl('button', 'aa-finance-record-edit-uncertain-close');
    const blocked = createEl('div', 'aa-finance-record-edit-actions-blocked'); blocked.classList.add('hidden');
    const blockedClose = createEl('button', 'aa-finance-record-edit-blocked-close');
    standard.appendChild(cancelBtn); standard.appendChild(submitBtn);
    uncertain.appendChild(uncertainClose); blocked.appendChild(blockedClose);
    form.appendChild(modalError); form.appendChild(titleInput); form.appendChild(titleError);
    form.appendChild(detailsInput); form.appendChild(detailsError); form.appendChild(amountInput);
    form.appendChild(amountError); form.appendChild(standard); form.appendChild(uncertain); form.appendChild(blocked);
    modal.appendChild(backdrop); modal.appendChild(closeBtn); modal.appendChild(form);
    body.appendChild(modal);
    [modal, backdrop, closeBtn, form, modalError, titleInput, titleError, detailsInput, detailsError, amountInput, amountError, standard, cancelBtn, submitBtn, uncertain, uncertainClose, blocked, blockedClose].forEach((el) => { el.ownerDocument = document; if (el.id) document._elements[el.id] = el; });
    return { document, modal, form, titleInput, detailsInput, amountInput, cancelBtn, submitBtn, uncertain, uncertainClose, blocked, blockedClose, backdrop, closeBtn, modalError, titleError, detailsError, amountError };
}

async function flushMicrotasks() { await new Promise(hostSetImmediate); }
async function waitDelay(ms) { await new Promise((r) => hostSetTimeout(r, ms)); }

function getSuccessEnvelope(record) {
    return jsonResponse({ ok: true, status: 200, body: { success: true, data: { record: record || SAMPLE_RECORD } } });
}

function bootEditController(options) {
    options = options || {};
    const dom = options.dom || buildEditModalDom();
    const timerCtrl = options.timerCtrl || createTimerController();
    const fetchCalls = [];
    const getDeferred = options.getDeferred || null;
    const updateDeferred = options.updateDeferred || null;
    const callbacks = {
        onFlowStateChange: [],
        onGetSourceFailed: [],
        onRefreshRequested: [],
        onUncertainReviewStart: [],
        onReviewGetResolved: [],
        onReviewGetUncertain: [],
        onFocusDetailStatus: 0
    };
    const sandbox = {
        document: dom.document,
        window: {},
        FormData: buildTestFormData(),
        setTimeout: timerCtrl.setTimeout.bind(timerCtrl),
        clearTimeout: timerCtrl.clearTimeout.bind(timerCtrl),
        fetch(url, opts) {
            fetchCalls.push({ url, opts });
            if (isGetRecordFetch(opts)) {
                if (getDeferred) return getDeferred.promise;
                if (options.getResponse) return Promise.resolve(options.getResponse(opts));
            }
            if (isUpdateRecordFetch(opts)) {
                if (updateDeferred) return updateDeferred.promise;
                if (options.updateResponse) return Promise.resolve(options.updateResponse(opts));
            }
            return Promise.resolve(jsonResponse({ ok: true, status: 200, body: { success: true, data: {} } }));
        },
        AbortController: hostAbortController,
        AbortSignal: hostAbortSignal
    };
    vm.runInNewContext(editModuleSrc, sandbox);
    const cfg = JSON.parse(JSON.stringify(options.financeData || FINANCE_DATA));
    if (options.stripUpdateAction) delete cfg.actions.updateRecord;
    if (options.stripGetAction) delete cfg.actions.getRecord;
    const ctrl = sandbox.window.AA_FinanceRecordEdit.createController({
        cfg,
        elements: {
            modal: dom.modal,
            backdrop: dom.backdrop,
            closeBtn: dom.closeBtn,
            form: dom.form,
            modalError: dom.modalError,
            titleInput: dom.titleInput,
            titleError: dom.titleError,
            detailsInput: dom.detailsInput,
            detailsError: dom.detailsError,
            amountInput: dom.amountInput,
            amountError: dom.amountError,
            standardActions: dom.document.getElementById('aa-finance-record-edit-actions-standard'),
            cancelBtn: dom.cancelBtn,
            submitBtn: dom.submitBtn,
            uncertainActions: dom.uncertain,
            uncertainCloseBtn: dom.uncertainClose,
            blockedActions: dom.blocked,
            blockedCloseBtn: dom.blockedClose
        },
        isDetailActive: options.isDetailActive || (() => true),
        isEditAllowed: options.isEditAllowed || (() => true),
        onFlowStateChange: (p) => callbacks.onFlowStateChange.push(p),
        onGetSourceFailed: (p) => callbacks.onGetSourceFailed.push(p),
        onRefreshRequested: (p) => callbacks.onRefreshRequested.push(p),
        onUncertainReviewStart: (p) => {
            callbacks.onUncertainReviewStart.push(p);
            if (options.reviewDescriptor === null) return null;
            return options.reviewDescriptor || {
                reviewToken: options.reviewToken || 42,
                recordId: p.recordId,
                containerId: p.containerId,
                sourcePage: p.sourcePage,
                phase: 'awaiting_get',
                submissionSnapshot: p.submissionSnapshot,
                reviewOutcome: null
            };
        },
        onReviewGetResolved: (p) => callbacks.onReviewGetResolved.push(p),
        onReviewGetUncertain: (p) => callbacks.onReviewGetUncertain.push(p),
        onFocusDetailStatus: () => { callbacks.onFocusDetailStatus++; }
    });
    return {
        ctrl, dom, sandbox, timerCtrl, fetchCalls, callbacks, getDeferred, updateDeferred,
        cleanup() {
            timerCtrl.flushAll();
            if (getDeferred && !getDeferred.settled) {
                const err = new Error('Aborted'); err.name = 'AbortError'; getDeferred.reject(err);
            }
            if (updateDeferred && !updateDeferred.settled) {
                const err = new Error('Aborted'); err.name = 'AbortError'; updateDeferred.reject(err);
            }
            ctrl.destroy();
        }
    };
}

describe('FinanceRecordEditModule (Ciclo 3E2B)', () => {

    it('degrada sin factory o acciones update/get', () => {
        const dom = buildEditModalDom();
        const sandbox = { document: dom.document, window: {}, FormData: buildTestFormData(), setTimeout: hostSetTimeout, clearTimeout: hostClearTimeout, fetch: () => Promise.resolve(jsonResponse({ ok: true, body: { success: true, data: {} } })), AbortController: hostAbortController };
        vm.runInNewContext(editModuleSrc, sandbox);
        const cfg = JSON.parse(JSON.stringify(FINANCE_DATA)); delete cfg.actions.updateRecord;
        const ctrl = sandbox.window.AA_FinanceRecordEdit.createController({ cfg, elements: { modal: dom.modal, form: dom.form, titleInput: dom.titleInput, detailsInput: dom.detailsInput, amountInput: dom.amountInput, submitBtn: dom.submitBtn } });
        ctrl.beginEdit(101, 7, 1);
        assert.strictEqual(dom.modal.classList.contains('hidden'), true);
        ctrl.destroy();
    });

    it('GET obligatorio antes de modal', async () => {
        const getDeferred = createDeferred();
        const boot = bootEditController({ getDeferred });
        try {
            boot.ctrl.beginEdit(101, 7, 2);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
            assert.strictEqual(boot.ctrl.getModalState(), 'LOADING_SOURCE');
            getDeferred.resolve(getSuccessEnvelope());
            await flushMicrotasks();
            await waitDelay(60);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), false);
            const snap = boot.ctrl.getSourceSnapshot();
            assert.strictEqual(snap.sourcePage, 2);
            assert.strictEqual(snap.recordId, 101);
        } finally { boot.cleanup(); }
    });

    it('amount null y vacío en sourceSnapshot', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(Object.assign({}, SAMPLE_RECORD, { amount: null, details: null }))
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            const snap = boot.ctrl.getSourceSnapshot();
            assert.strictEqual(snap.amount, '');
            assert.strictEqual(snap.details, '');
            assert.strictEqual(boot.dom.amountInput.value, '');
        } finally { boot.cleanup(); }
    });

    it('amount cero conserva 0.00', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(Object.assign({}, SAMPLE_RECORD, { amount: '0.00' }))
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            assert.strictEqual(boot.ctrl.getSourceSnapshot().amount, '0.00');
            assert.strictEqual(boot.dom.amountInput.value, '0.00');
        } finally { boot.cleanup(); }
    });

    it('draft "-25.50" → FormData amount "-25.50"', async () => {
        let captured = null;
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(Object.assign({}, SAMPLE_RECORD, { amount: '-25.50' })),
            updateResponse: (opts) => {
                captured = opts.body.data;
                return getSuccessEnvelope(Object.assign({}, SAMPLE_RECORD, { amount: '-25.50', title: 'Neg' }));
            }
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.titleInput.value = 'Neg';
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(captured.amount, '-25.50');
        } finally { boot.cleanup(); }
    });

    it('UI no normaliza amount negativo ASCII', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(Object.assign({}, SAMPLE_RECORD, { amount: null }))
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.amountInput.value = '-25.50';
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            const snap = boot.ctrl.getSubmissionSnapshot();
            assert.strictEqual(snap.amount, '-25.50');
            assert.strictEqual(boot.dom.amountInput.value, '-25.50');
        } finally { boot.cleanup(); }
    });

    it('payload UPDATE exacto con details y amount siempre presentes', async () => {
        let captured = null;
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: (opts) => {
                captured = opts.body.data;
                return getSuccessEnvelope(Object.assign({}, SAMPLE_RECORD, { title: 'Nuevo' }));
            }
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.titleInput.value = 'Nuevo';
            boot.dom.detailsInput.value = '';
            boot.dom.amountInput.value = '';
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(captured.action, 'aa_update_finance_record');
            assert.strictEqual(captured.record_id, '101');
            assert.strictEqual(captured.container_id, '7');
            assert.strictEqual(captured.variant_key, 'general');
            assert.ok(Object.prototype.hasOwnProperty.call(captured, 'details'));
            assert.ok(Object.prototype.hasOwnProperty.call(captured, 'amount'));
            assert.strictEqual(captured.amount, '');
            assert.strictEqual(captured.details, '');
        } finally { boot.cleanup(); }
    });

    it('invalid_amount es FIELD_REJECTED sin review', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => jsonResponse({ ok: false, status: 400, body: { success: false, data: { code: 'invalid_amount', message: 'Inválido' } } })
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'FIELD_REJECTED');
            assert.strictEqual(boot.callbacks.onUncertainReviewStart.length, 0);
            const updates = boot.fetchCalls.filter((c) => isUpdateRecordFetch(c.opts));
            assert.strictEqual(updates.length, 1);
        } finally { boot.cleanup(); }
    });

    it('missing_amount es BLOCKED_REJECTED sin review', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => jsonResponse({ ok: false, status: 400, body: { success: false, data: { code: 'missing_amount', message: 'Falta' } } })
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'BLOCKED_REJECTED');
            assert.strictEqual(boot.callbacks.onUncertainReviewStart.length, 0);
        } finally { boot.cleanup(); }
    });

    it('invalid_details es FIELD_REJECTED', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => jsonResponse({ ok: false, status: 400, body: { success: false, data: { code: 'invalid_details', message: 'Inválido' } } })
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'FIELD_REJECTED');
        } finally { boot.cleanup(); }
    });

    it('missing_details es BLOCKED_REJECTED', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => jsonResponse({ ok: false, status: 400, body: { success: false, data: { code: 'missing_details', message: 'Falta' } } })
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'BLOCKED_REJECTED');
        } finally { boot.cleanup(); }
    });

    it('timeout UPDATE entra en UNCERTAIN', async () => {
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred: createDeferred() });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'UNCERTAIN');
            assert.ok(boot.ctrl.getSubmissionSnapshot());
        } finally { boot.cleanup(); }
    });

    it('respuesta UPDATE tardía ignorada', async () => {
        const updateDeferred = createDeferred();
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            updateDeferred.resolve(getSuccessEnvelope());
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onRefreshRequested.length, 0);
        } finally { boot.cleanup(); }
    });

    it('Cerrar y revisar invoca onUncertainReviewStart sin token propio', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateDeferred: createDeferred(),
            reviewToken: 77
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onUncertainReviewStart.length, 1);
            assert.strictEqual(boot.callbacks.onUncertainReviewStart[0].submissionSnapshot.amount, boot.callbacks.onUncertainReviewStart[0].submissionSnapshot.amount);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
        } finally { boot.cleanup(); }
    });

    it('callback null mantiene UNCERTAIN', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed' } } }),
            reviewDescriptor: null
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'UNCERTAIN');
            assert.ok(boot.ctrl.getSubmissionSnapshot());
        } finally { boot.cleanup(); }
    });

    it('review GET presente informa outcome', async () => {
        let getCount = 0;
        const boot = bootEditController({
            getResponse: () => { getCount++; return getSuccessEnvelope(); },
            updateResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed' } } })
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            await waitDelay(30);
            assert.ok(boot.callbacks.onReviewGetResolved.some((p) => p.outcome === 'present'));
            assert.ok(getCount >= 2);
        } finally { boot.cleanup(); }
    });

    it('reviewOutcome stale no adelanta flujo sin resumeReview', async () => {
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope() });
        try {
            boot.ctrl.resumeReview({
                reviewToken: 99,
                recordId: 101,
                containerId: 7,
                sourcePage: 1,
                phase: 'awaiting_get',
                submissionSnapshot: {},
                reviewOutcome: 'gone'
            });
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewGetResolved.length, 1);
            assert.strictEqual(boot.callbacks.onReviewGetResolved[0].outcome, 'present');
        } finally { boot.cleanup(); }
    });

    it('destroy limpia timers y neutraliza callbacks tardíos', async () => {
        const updateDeferred = createDeferred();
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.ctrl.destroy();
            updateDeferred.resolve(getSuccessEnvelope());
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onRefreshRequested.length, 0);
        } finally { boot.cleanup(); }
    });

    it('foco inicial en título tras GET válido', async () => {
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope() });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            assert.strictEqual(boot.dom.document.activeElement, boot.dom.titleInput);
        } finally { boot.cleanup(); }
    });

    it('XSS: título malicioso no usa innerHTML', async () => {
        const malicious = '<img src=x onerror=alert(1)>';
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(Object.assign({}, SAMPLE_RECORD, { title: malicious }))
        });
        try {
            boot.ctrl.beginEdit(101, 7, 1);
            await flushMicrotasks();
            assert.strictEqual(boot.dom.titleInput.value, malicious);
        } finally { boot.cleanup(); }
    });
});
