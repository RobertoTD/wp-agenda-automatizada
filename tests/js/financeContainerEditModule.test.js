'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const editModulePath = path.join(__dirname, '../../includes/admin/ui/modules/canonical/finance/finance-container-edit-module.js');
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
        listContainers: 'aa_list_finance_containers',
        createContainer: 'aa_create_finance_container',
        listRecords: 'aa_list_finance_records',
        createRecord: 'aa_create_finance_record',
        deleteRecord: 'aa_delete_finance_record',
        getRecord: 'aa_get_finance_record',
        deleteContainer: 'aa_delete_finance_container',
        getContainer: 'aa_get_finance_container',
        updateContainer: 'aa_update_finance_container'
    }
};

const SAMPLE_CONTAINER = {
    id: 7,
    family_key: 'finance',
    variant_key: 'general',
    title: 'Caja',
    details: 'Notas',
    created_at: '2026-08-31 10:00:00'
};

const AUTHORITATIVE_LIST_DATA = {
    items: [{
        id: 7,
        family_key: 'finance',
        variant_key: 'general',
        title: 'Caja',
        details: 'Notas',
        amount_total: '150.85',
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

function isGetContainerFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_get_finance_container';
}

function isUpdateContainerFetch(opts) {
    return opts && opts.body && opts.body.data && opts.body.data.action === 'aa_update_finance_container';
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
    const modal = createEl('div', 'aa-finance-container-edit-modal'); modal.classList.add('hidden');
    const backdrop = createEl('div', 'aa-finance-container-edit-modal-backdrop');
    const closeBtn = createEl('button', 'aa-finance-container-edit-close');
    const form = createEl('form', 'aa-finance-container-edit-form');
    const modalError = createEl('div', 'aa-finance-container-edit-error'); modalError.classList.add('hidden');
    const titleInput = createEl('input', 'aa-finance-container-edit-title');
    const titleError = createEl('p', 'aa-finance-container-edit-title-error'); titleError.classList.add('hidden');
    const detailsInput = createEl('textarea', 'aa-finance-container-edit-details');
    const detailsError = createEl('p', 'aa-finance-container-edit-details-error'); detailsError.classList.add('hidden');
    const standard = createEl('div', 'aa-finance-container-edit-actions-standard');
    const cancelBtn = createEl('button', 'aa-finance-container-edit-cancel');
    const submitBtn = createEl('button', 'aa-finance-container-edit-submit'); submitBtn.type = 'submit';
    const uncertain = createEl('div', 'aa-finance-container-edit-actions-uncertain'); uncertain.classList.add('hidden');
    const uncertainClose = createEl('button', 'aa-finance-container-edit-uncertain-close');
    const blocked = createEl('div', 'aa-finance-container-edit-actions-blocked'); blocked.classList.add('hidden');
    const blockedClose = createEl('button', 'aa-finance-container-edit-blocked-close');
    standard.appendChild(cancelBtn); standard.appendChild(submitBtn);
    uncertain.appendChild(uncertainClose); blocked.appendChild(blockedClose);
    form.appendChild(modalError); form.appendChild(titleInput); form.appendChild(titleError);
    form.appendChild(detailsInput); form.appendChild(detailsError); form.appendChild(standard);
    form.appendChild(uncertain); form.appendChild(blocked);
    modal.appendChild(backdrop); modal.appendChild(closeBtn); modal.appendChild(form);
    body.appendChild(modal);
    [modal, backdrop, closeBtn, form, modalError, titleInput, titleError, detailsInput, detailsError, standard, cancelBtn, submitBtn, uncertain, uncertainClose, blocked, blockedClose].forEach((el) => { el.ownerDocument = document; if (el.id) document._elements[el.id] = el; });
    return { document, modal, form, titleInput, detailsInput, cancelBtn, submitBtn, uncertain, uncertainClose, blocked, blockedClose, backdrop, closeBtn, modalError, titleError, detailsError };
}

async function flushMicrotasks() { await new Promise(hostSetImmediate); }
async function waitDelay(ms) { await new Promise((r) => hostSetTimeout(r, ms)); }

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
        onReviewPending: [],
        onReviewUncertain: [],
        onReviewAwaitingList: [],
        onFocusStatus: 0
    };
    const sandbox = {
        document: dom.document,
        window: {},
        FormData: buildTestFormData(),
        setTimeout: timerCtrl.setTimeout.bind(timerCtrl),
        clearTimeout: timerCtrl.clearTimeout.bind(timerCtrl),
        fetch(url, opts) {
            fetchCalls.push({ url, opts });
            if (isGetContainerFetch(opts)) {
                if (getDeferred) return getDeferred.promise;
                if (options.getResponse) return Promise.resolve(options.getResponse(opts));
            }
            if (isUpdateContainerFetch(opts)) {
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
    if (options.stripUpdateAction) delete cfg.actions.updateContainer;
    if (options.stripGetAction) delete cfg.actions.getContainer;
    const ctrl = sandbox.window.AA_FinanceContainerEdit.createController({
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
            standardActions: dom.document.getElementById('aa-finance-container-edit-actions-standard'),
            cancelBtn: dom.cancelBtn,
            submitBtn: dom.submitBtn,
            uncertainActions: dom.uncertain,
            uncertainCloseBtn: dom.uncertainClose,
            blockedActions: dom.blocked,
            blockedCloseBtn: dom.blockedClose
        },
        isListActive: options.isListActive || (() => true),
        isEditAllowed: options.isEditAllowed || (() => true),
        onFlowStateChange: (p) => callbacks.onFlowStateChange.push(p),
        onGetSourceFailed: (p) => callbacks.onGetSourceFailed.push(p),
        onRefreshRequested: (p) => callbacks.onRefreshRequested.push(p),
        onReviewPending: (p) => { callbacks.onReviewPending.push(p); return options.reviewToken || 42; },
        onReviewUncertain: (p) => callbacks.onReviewUncertain.push(p),
        onReviewAwaitingList: (p) => callbacks.onReviewAwaitingList.push(p),
        onFocusStatus: () => { callbacks.onFocusStatus++; }
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

function getSuccessEnvelope(container) {
    return jsonResponse({ ok: true, status: 200, body: { success: true, data: { container: container || SAMPLE_CONTAINER } } });
}

describe('FinanceContainerEditModule (Ciclo 3E1B)', () => {

    it('degrada sin factory o acciones update/get', () => {
        const dom = buildEditModalDom();
        const sandbox = { document: dom.document, window: {}, FormData: buildTestFormData(), setTimeout: hostSetTimeout, clearTimeout: hostClearTimeout, fetch: () => Promise.resolve(jsonResponse({ ok: true, body: { success: true, data: {} } })), AbortController: hostAbortController };
        vm.runInNewContext(editModuleSrc, sandbox);
        const cfg = JSON.parse(JSON.stringify(FINANCE_DATA)); delete cfg.actions.updateContainer;
        const ctrl = sandbox.window.AA_FinanceContainerEdit.createController({ cfg, elements: { modal: dom.modal, form: dom.form, titleInput: dom.titleInput, detailsInput: dom.detailsInput, submitBtn: dom.submitBtn } });
        ctrl.beginEdit(7, 1); assert.strictEqual(dom.modal.classList.contains('hidden'), true);
        ctrl.destroy();
    });

    it('GET obligatorio antes de modal y sourcePage en snapshot', async () => {
        const getDeferred = createDeferred();
        const boot = bootEditController({ getDeferred });
        try {
            boot.ctrl.beginEdit(7, 2);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
            assert.strictEqual(boot.ctrl.getModalState(), 'LOADING_SOURCE');
            getDeferred.resolve(getSuccessEnvelope());
            await flushMicrotasks();
            await waitDelay(60);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), false);
            const snap = boot.ctrl.getSourceSnapshot();
            assert.strictEqual(snap.sourcePage, 2);
            assert.strictEqual(snap.containerId, 7);
        } finally { boot.cleanup(); }
    });

    it('GET not_found no abre modal y libera lock', async () => {
        const boot = bootEditController({
            getResponse: () => jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'not_found', message: 'No.' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
            assert.strictEqual(boot.callbacks.onGetSourceFailed.some((p) => p.reason === 'not_found'), true);
            assert.strictEqual(boot.callbacks.onFlowStateChange.some((p) => p.phase === 'edit_active'), true);
            assert.ok(boot.callbacks.onFlowStateChange.filter((p) => p.phase === 'idle').length >= 0);
        } finally { boot.cleanup(); }
    });

    it('GET bloqueante no abre modal', async () => {
        const boot = bootEditController({
            getResponse: () => jsonResponse({ ok: false, status: 403, body: { success: false, data: { code: 'forbidden', message: 'No.' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onGetSourceFailed.some((p) => p.reason === 'blocked'), true);
        } finally { boot.cleanup(); }
    });

    it('GET red/500/timeout no crea review incierta', async () => {
        const boot = bootEditController({
            getResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewPending.length, 0);
            assert.strictEqual(boot.callbacks.onGetSourceFailed.some((p) => p.reason === 'recoverable'), true);
        } finally { boot.cleanup(); }
    });

    it('GET timeout inicial libera sin review', async () => {
        const boot = bootEditController({ getDeferred: createDeferred() });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            const timeoutId = boot.timerCtrl.ids()[0];
            boot.timerCtrl.flush(timeoutId);
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onGetSourceFailed.some((p) => p.reason === 'recoverable'), true);
            assert.strictEqual(boot.callbacks.onReviewPending.length, 0);
        } finally { boot.cleanup(); }
    });

    it('abort y respuesta GET tardía ignorada', async () => {
        const getDeferred = createDeferred();
        const boot = bootEditController({ getDeferred });
        try {
            boot.ctrl.beginEdit(7, 1);
            boot.ctrl.destroy();
            getDeferred.resolve(getSuccessEnvelope());
            await flushMicrotasks();
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
        } finally { boot.cleanup(); }
    });

    it('sourceSnapshot y submissionSnapshot son objetos distintos', async () => {
        const updateDeferred = createDeferred();
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            const source = boot.ctrl.getSourceSnapshot();
            boot.dom.titleInput.value = 'Nuevo';
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            const submission = boot.ctrl.getSubmissionSnapshot();
            assert.notStrictEqual(source, submission);
            assert.strictEqual(submission.title, 'Nuevo');
        } finally { boot.cleanup(); }
    });

    it('details siempre presente en payload UPDATE', async () => {
        let captured = null;
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope({ id: 7, family_key: 'finance', variant_key: 'general', title: 'Caja', details: null, created_at: '2026-08-31 10:00:00' }),
            updateResponse: (opts) => { captured = opts.body.data; return getSuccessEnvelope({ id: 7, family_key: 'finance', variant_key: 'general', title: 'Caja', details: '', created_at: '2026-08-31 10:00:00' }); }
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.detailsInput.value = '';
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.ok(Object.prototype.hasOwnProperty.call(captured, 'details'));
            assert.strictEqual(captured.action, 'aa_update_finance_container');
            assert.strictEqual(captured.id, '7');
            assert.strictEqual(captured.variant_key, 'general');
        } finally { boot.cleanup(); }
    });

    it('snapshot submission inmutable ante cambios post-submit', async () => {
        const updateDeferred = createDeferred();
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.titleInput.value = 'Antes';
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            const snap = boot.ctrl.getSubmissionSnapshot();
            boot.dom.titleInput.value = 'Después';
            assert.strictEqual(snap.title, 'Antes');
        } finally { boot.cleanup(); }
    });

    it('doble submit bloqueado', async () => {
        const updateDeferred = createDeferred();
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            const updates = boot.fetchCalls.filter((c) => isUpdateContainerFetch(c.opts));
            assert.strictEqual(updates.length, 1);
        } finally { boot.cleanup(); }
    });

    it('éxito UPDATE validado cierra modal y refresca', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => getSuccessEnvelope({ id: 7, family_key: 'finance', variant_key: 'general', title: 'Nuevo', details: 'X', created_at: '2026-08-31 10:00:00' })
        });
        try {
            boot.ctrl.beginEdit(7, 2);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.titleInput.value = 'Nuevo';
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
            assert.strictEqual(boot.callbacks.onRefreshRequested.some((p) => p.reason === 'confirmed' && p.sourcePage === 2), true);
        } finally { boot.cleanup(); }
    });

    it('envelope UPDATE con ID discordante es incierto', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => getSuccessEnvelope({ id: 99, family_key: 'finance', variant_key: 'general', title: 'X', details: null, created_at: '2026-08-31 10:00:00' })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'UNCERTAIN');
        } finally { boot.cleanup(); }
    });

    it('rechazo corregible conserva borrador', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => jsonResponse({ ok: false, status: 400, body: { success: false, data: { code: 'title_too_long', message: 'Largo' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.titleInput.value = 'Titulo largo';
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'FIELD_REJECTED');
            assert.strictEqual(boot.dom.titleInput.readOnly, false);
            assert.strictEqual(boot.ctrl.getSubmissionSnapshot(), null);
        } finally { boot.cleanup(); }
    });

    it('rechazo bloqueante permite cerrar', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => jsonResponse({ ok: false, status: 403, body: { success: false, data: { code: 'forbidden', message: 'No' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'BLOCKED_REJECTED');
            boot.dom.blockedClose.dispatch('click');
            assert.strictEqual(boot.ctrl.getModalState(), 'IDLE');
        } finally { boot.cleanup(); }
    });

    it('timeout UPDATE entra en UNCERTAIN', async () => {
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred: createDeferred() });
        try {
            boot.ctrl.beginEdit(7, 1);
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

    it('red/500 UPDATE es incierto', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            assert.strictEqual(boot.ctrl.getModalState(), 'UNCERTAIN');
        } finally { boot.cleanup(); }
    });

    it('respuesta UPDATE tardía ignorada', async () => {
        const updateDeferred = createDeferred();
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred });
        try {
            boot.ctrl.beginEdit(7, 1);
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

    it('Cerrar y revisar crea pending con fase awaiting_get', async () => {
        const boot = bootEditController({
            getResponse: () => getSuccessEnvelope(),
            updateDeferred: createDeferred(),
            reviewToken: 77
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.timerCtrl.flushAll();
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewPending.length, 1);
            assert.strictEqual(boot.dom.modal.classList.contains('hidden'), true);
            assert.strictEqual(boot.callbacks.onFlowStateChange.some((p) => p.phase === 'edit_review'), true);
        } finally { boot.cleanup(); }
    });

    it('GET review presente pasa a awaiting_list', async () => {
        let getCount = 0;
        const boot = bootEditController({
            getResponse: () => { getCount++; return getSuccessEnvelope(); },
            updateResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            await waitDelay(30);
            assert.ok(boot.callbacks.onReviewAwaitingList.some((p) => p.outcome === 'present'));
            assert.ok(getCount >= 2);
        } finally { boot.cleanup(); }
    });

    it('GET review ausente pasa a awaiting_list gone', async () => {
        let getCount = 0;
        const boot = bootEditController({
            getResponse: () => {
                getCount++;
                if (getCount === 1) return getSuccessEnvelope();
                return jsonResponse({ ok: false, status: 404, body: { success: false, data: { code: 'not_found' } } });
            },
            updateResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.ok(boot.callbacks.onReviewAwaitingList.some((p) => p.outcome === 'gone'));
        } finally { boot.cleanup(); }
    });

    it('GET review incierto ofrece retry GET', async () => {
        let getCount = 0;
        const boot = bootEditController({
            getResponse: () => {
                getCount++;
                if (getCount === 1) return getSuccessEnvelope();
                return jsonResponse({ ok: false, status: 500, body: null });
            },
            updateResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            boot.dom.form.dispatch('submit');
            await flushMicrotasks();
            boot.dom.uncertainClose.dispatch('click');
            await flushMicrotasks();
            assert.strictEqual(boot.callbacks.onReviewUncertain.length, 1);
            boot.ctrl.retryReviewGet();
            await flushMicrotasks();
            assert.ok(getCount >= 3);
        } finally { boot.cleanup(); }
    });

    it('retry awaiting_get ejecuta solo GET', async () => {
        const boot = bootEditController({
            getResponse: () => jsonResponse({ ok: false, status: 500, body: null }),
            updateResponse: () => jsonResponse({ ok: false, status: 500, body: { success: false, data: { code: 'persistence_failed' } } })
        });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            boot.ctrl.retryReviewGet();
            await flushMicrotasks();
            const gets = boot.fetchCalls.filter((c) => isGetContainerFetch(c.opts));
            const updates = boot.fetchCalls.filter((c) => isUpdateContainerFetch(c.opts));
            assert.ok(gets.length >= 1);
            assert.strictEqual(updates.length, 0);
        } finally { boot.cleanup(); }
    });

    it('token obsoleto no completa review', async () => {
        const boot = bootEditController({ reviewToken: 1 });
        boot.ctrl.completeListSettlement(99, 'present');
        assert.strictEqual(boot.ctrl.getModalState(), 'IDLE');
        boot.cleanup();
    });

    it('destroy limpia timers y neutraliza callbacks tardíos', async () => {
        const updateDeferred = createDeferred();
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope(), updateDeferred });
        try {
            boot.ctrl.beginEdit(7, 1);
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
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            await waitDelay(60);
            assert.strictEqual(boot.dom.document.activeElement, boot.dom.titleInput);
        } finally { boot.cleanup(); }
    });

    it('XSS: título malicioso no usa innerHTML', async () => {
        const malicious = '<img src=x onerror=alert(1)>';
        const boot = bootEditController({ getResponse: () => getSuccessEnvelope({ id: 7, family_key: 'finance', variant_key: 'general', title: malicious, details: null, created_at: '2026-08-31 10:00:00' }) });
        try {
            boot.ctrl.beginEdit(7, 1);
            await flushMicrotasks();
            assert.strictEqual(boot.dom.titleInput.value, malicious);
        } finally { boot.cleanup(); }
    });
});
