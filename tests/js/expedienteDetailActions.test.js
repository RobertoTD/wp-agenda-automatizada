'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { URL } = require('node:url');

const scriptPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/expedientes/expediente-detail-actions.js'
);
const scriptSrc = fs.readFileSync(scriptPath, 'utf8');
const detailSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/expedientes/detail.php'),
    'utf8'
);
const moduleSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/expedientes/index.php'),
    'utf8'
);
const clientsSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/clients/index.php'),
    'utf8'
);

function createEl(tag, id) {
    const el = {
        tagName: String(tag).toUpperCase(),
        _id: id || '',
        _className: '',
        children: [],
        attributes: Object.create(null),
        textContent: '',
        disabled: false,
        parentNode: null,
        _listeners: Object.create(null),
        classList: {
            _set: new Set(),
            add(c) {
                this._set.add(c);
                el._className = Array.from(this._set).join(' ');
            },
            remove(c) {
                this._set.delete(c);
                el._className = Array.from(this._set).join(' ');
            },
            contains(c) { return this._set.has(c); }
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
            if (name === 'id') this._id = String(value);
            if (name === 'class') this.className = String(value);
        },
        getAttribute(name) {
            if (name === 'id') return this._id || null;
            if (name === 'class') return this._className || null;
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
        },
        removeAttribute(name) {
            delete this.attributes[name];
            if (name === 'id') this._id = '';
            if (name === 'class') this.className = '';
        },
        appendChild(child) {
            child.parentNode = this;
            this.children.push(child);
            return child;
        },
        contains(node) {
            if (node === this) return true;
            return this.children.some((c) => c === node || (c.contains && c.contains(node)));
        },
        closest(selector) {
            let node = this;
            while (node) {
                if (selector === '[data-aa-modal-close]') {
                    if (node.getAttribute && node.getAttribute('data-aa-modal-close') !== null) {
                        return node;
                    }
                } else if (selector.startsWith('.') && node.classList
                    && node.classList.contains(selector.slice(1))) {
                    return node;
                } else if (selector.startsWith('#') && node.id === selector.slice(1)) {
                    return node;
                }
                node = node.parentNode;
            }
            return null;
        },
        addEventListener(type, handler) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        removeEventListener(type, handler) {
            const list = this._listeners[type] || [];
            this._listeners[type] = list.filter((h) => h !== handler);
        },
        dispatch(type, event) {
            const list = this._listeners[type] || [];
            list.forEach((handler) => handler(event || {
                type,
                preventDefault() {},
                stopPropagation() {},
                target: this,
                key: undefined
            }));
        },
        focus() {
            this._focused = true;
        },
        querySelector() { return null; },
        querySelectorAll() { return []; }
    };
    Object.defineProperty(el, 'id', {
        get() { return this._id; },
        set(v) {
            this._id = String(v || '');
            this.attributes.id = this._id;
        }
    });
    Object.defineProperty(el, 'className', {
        get() { return this._className; },
        set(v) {
            this._className = String(v || '');
            this.classList._set = new Set(this._className.split(/\s+/).filter(Boolean));
        }
    });
    if (id) el.id = id;
    return el;
}

function flush() {
    return new Promise((resolve) => setImmediate(resolve));
}

function buildDom() {
    const root = createEl('div', 'aa-expediente-detail-root');
    const header = createEl('div', 'aa-expediente-detail-header');
    const tools = createEl('div', 'aa-expediente-detail-tools');
    const trigger = createEl('button', 'aa-expediente-detail-tools-trigger');
    trigger.setAttribute('aria-expanded', 'false');
    const menu = createEl('div', 'aa-expediente-detail-tools-menu');
    menu.classList.add('hidden');
    menu.setAttribute('hidden', 'hidden');
    const deleteItem = createEl('button', 'aa-expediente-detail-tools-delete');
    menu.appendChild(deleteItem);
    tools.appendChild(trigger);
    tools.appendChild(menu);
    header.appendChild(tools);
    root.appendChild(header);

    // Shared #aa-modal-root shape (modals.php)
    const modalRoot = createEl('div', 'aa-modal-root');
    modalRoot.classList.add('hidden');
    const overlay = createEl('div');
    overlay.className = 'aa-modal-overlay';
    overlay.setAttribute('data-aa-modal-close', '');
    const modalPanel = createEl('div');
    modalPanel.className = 'aa-modal';
    const modalHeader = createEl('div');
    modalHeader.className = 'aa-modal-header';
    const modalTitle = createEl('h2');
    modalTitle.className = 'aa-modal-title';
    const closeBtn = createEl('button');
    closeBtn.className = 'aa-modal-close';
    closeBtn.setAttribute('data-aa-modal-close', '');
    closeBtn.setAttribute('aria-label', 'Cerrar');
    modalHeader.appendChild(modalTitle);
    modalHeader.appendChild(closeBtn);
    const modalBody = createEl('div');
    modalBody.className = 'aa-modal-body';
    const modalFooter = createEl('div');
    modalFooter.className = 'aa-modal-footer';
    modalPanel.appendChild(modalHeader);
    modalPanel.appendChild(modalBody);
    modalPanel.appendChild(modalFooter);
    modalRoot.appendChild(overlay);
    modalRoot.appendChild(modalPanel);

    return {
        root,
        header,
        tools,
        trigger,
        menu,
        deleteItem,
        modalRoot,
        overlay,
        closeBtn,
        modalTitle,
        modalBody,
        modalFooter
    };
}

/**
 * Mirrors main.js shared-modal contract: interaction paths call the *current*
 * AAAdmin.modal.close property (so temporary wrappers receive Cancel/X/overlay/Escape).
 */
function attachSharedModalDispatch(AAAdmin, document) {
    document.addEventListener('click', function (event) {
        if (!AAAdmin.modal.isOpen()) return;
        const target = event.target;
        if (!target || typeof target.closest !== 'function') return;
        const closeTrigger = target.closest('[data-aa-modal-close]');
        if (!closeTrigger) return;
        AAAdmin.modal.close();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && AAAdmin.modal.isOpen()) {
            AAAdmin.modal.close();
        }
    });
}

function loadScript(options) {
    const dom = buildDom();
    const byId = {
        'aa-expediente-detail-root': dom.root,
        'aa-expediente-detail-header': dom.header,
        'aa-expediente-detail-tools': dom.tools,
        'aa-expediente-detail-tools-trigger': dom.trigger,
        'aa-expediente-detail-tools-menu': dom.menu,
        'aa-expediente-detail-tools-delete': dom.deleteItem,
        'aa-modal-root': dom.modalRoot
    };

    const docListeners = Object.create(null);
    const location = {
        href: 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes&view=detail&expediente_id=11',
        origin: 'https://example.test',
        replaceCalls: [],
        replace(url) { this.replaceCalls.push(url); }
    };

    let modalOpen = false;
    let lastModal = null;
    const baseClose = function () {
        modalOpen = false;
        lastModal = null;
        dom.modalRoot.classList.add('hidden');
        if (dom.modalBody) {
            dom.modalBody.children = [];
        }
        if (dom.modalFooter) {
            dom.modalFooter.children = [];
        }
        // clear dynamic ids
        delete byId['aa-expediente-detail-delete-cancel'];
        delete byId['aa-expediente-detail-delete-confirm'];
        delete byId['aa-expediente-detail-delete-error'];
    };

    const AAAdmin = {
        modal: {
            open(opts) {
                modalOpen = true;
                lastModal = opts;
                dom.modalRoot.classList.remove('hidden');
                const footer = opts.footer;
                if (footer && footer.children) {
                    footer.children.forEach((child) => {
                        if (child.id) byId[child.id] = child;
                        child.parentNode = footer;
                        if (child.children) {
                            child.children.forEach((nested) => {
                                if (nested.id) byId[nested.id] = nested;
                                nested.parentNode = child;
                            });
                        }
                    });
                    if (dom.modalFooter) {
                        dom.modalFooter.children = [footer];
                        footer.parentNode = dom.modalFooter;
                    }
                }
                const body = opts.body;
                if (body && body.children) {
                    body.children.forEach((child) => {
                        if (child.id) byId[child.id] = child;
                        child.parentNode = body;
                    });
                    if (dom.modalBody) {
                        dom.modalBody.children = [body];
                        body.parentNode = dom.modalBody;
                    }
                }
            },
            close: baseClose,
            isOpen() { return modalOpen; }
        }
    };

    const fetches = [];
    const fetchImpl = options.fetchImpl || function (url, init) {
        fetches.push({ url, init });
        return Promise.resolve({
            ok: true,
            status: 200,
            text: () => Promise.resolve(JSON.stringify({
                success: true,
                data: { deleted: true, expediente_id: 11 }
            }))
        });
    };

    const cfg = options.config !== undefined ? options.config : {
        ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
        expedienteId: '11',
        containerActions: {
            deleteAction: 'aa_delete_expediente',
            nonce: 'nonce-aa_expedientes_nonce',
            listUrl: 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes'
        }
    };

    const sandbox = {
        window: {
            AAAdmin,
            AA_EXPEDIENTE_DETAIL_DATA: cfg,
            location,
            setTimeout: (fn) => { fn(); return 1; },
            fetch: fetchImpl,
            URL,
            URLSearchParams
        },
        document: {
            readyState: 'complete',
            getElementById(id) { return byId[id] || null; },
            createElement(tag) { return createEl(tag); },
            addEventListener(type, handler) {
                docListeners[type] = docListeners[type] || [];
                docListeners[type].push(handler);
            },
            removeEventListener(type, handler) {
                const list = docListeners[type] || [];
                docListeners[type] = list.filter((h) => h !== handler);
            }
        },
        console,
        URL,
        URLSearchParams
    };
    sandbox.window.document = sandbox.document;
    sandbox.window.window = sandbox.window;

    // Shared modal interaction listeners before consumer auto-mount (main.js order).
    attachSharedModalDispatch(AAAdmin, sandbox.document);

    vm.runInNewContext(scriptSrc, sandbox);

    return {
        sandbox,
        dom,
        byId,
        fetches,
        location,
        AAAdmin,
        baseClose,
        getModalOpen: () => modalOpen,
        getLastModal: () => lastModal,
        docListeners,
        dispatchDoc(type, event) {
            (docListeners[type] || []).forEach((h) => h(event));
        },
        /** Real shared-modal paths: click target with data-aa-modal-close / Escape. */
        closeViaSharedClick(target) {
            this.dispatchDoc('click', {
                type: 'click',
                target,
                preventDefault() {},
                stopPropagation() {}
            });
        },
        closeViaEscape() {
            this.dispatchDoc('keydown', {
                type: 'keydown',
                key: 'Escape',
                preventDefault() {},
                stopPropagation() {}
            });
        },
        openDeleteConfirm() {
            const { trigger, deleteItem } = this.dom;
            trigger.dispatch('click', {
                type: 'click', preventDefault() {}, stopPropagation() {}, target: trigger
            });
            deleteItem.dispatch('click', {
                type: 'click', preventDefault() {}, stopPropagation() {}, target: deleteItem
            });
        },
        assertMenuCanReopen() {
            const { trigger, menu } = this.dom;
            const api = this.sandbox.window.AAAdmin.ExpedienteDetailActions;
            assert.equal(api._getState().confirmationOpen, false);
            assert.equal(this.AAAdmin.modal.close, this.baseClose);
            assert.ok(this.byId['aa-expediente-detail-tools-trigger']);
            trigger._focused = false;
            trigger.dispatch('click', {
                type: 'click', preventDefault() {}, stopPropagation() {}, target: trigger
            });
            assert.equal(trigger.getAttribute('aria-expanded'), 'true');
            assert.equal(menu.classList.contains('hidden'), false);
            assert.equal(api._getState().menuOpen, true);
            // close menu for subsequent steps
            trigger.dispatch('click', {
                type: 'click', preventDefault() {}, stopPropagation() {}, target: trigger
            });
            assert.equal(trigger.getAttribute('aria-expanded'), 'false');
        }
    };
}

describe('expediente-detail-actions source guards', () => {
    it('detail.php wires tools + containerActions + script once after create modal', () => {
        assert.match(detailSrc, /aa-expediente-detail-tools-trigger/);
        assert.match(detailSrc, /containerActions/);
        assert.match(detailSrc, /aa_delete_expediente/);
        assert.match(detailSrc, /aa_expedientes_nonce/);
        assert.match(detailSrc, /expediente-detail-actions\.js/);
        assert.equal((detailSrc.match(/expediente-detail-actions\.js/g) || []).length, 1);
        assert.match(
            detailSrc,
            /expediente-registro-create-modal\.js[\s\S]*expediente-detail-actions\.js/
        );
        assert.doesNotMatch(detailSrc, /aa-expediente-detail-tools[\s\S]*Editar/);
    });

    it('listado y clients no cargan detail-actions', () => {
        assert.doesNotMatch(moduleSrc, /expediente-detail-actions\.js/);
        assert.doesNotMatch(clientsSrc, /expediente-detail-actions\.js/);
        assert.doesNotMatch(clientsSrc, /AA_EXPEDIENTE_DETAIL_DATA/);
    });
});

describe('ExpedienteDetailActions', () => {
    let ctx;

    afterEach(() => {
        if (ctx && ctx.sandbox.window.AAAdmin.ExpedienteDetailActions) {
            ctx.sandbox.window.AAAdmin.ExpedienteDetailActions.destroy();
        }
        ctx = null;
    });

    it('mount válido + auto-mount único', () => {
        ctx = loadScript({});
        const api = ctx.sandbox.window.AAAdmin.ExpedienteDetailActions;
        assert.equal(api._getState().mounted, true);
        assert.equal(api.mount(), false);
    });

    it('config inválida → cero efectos', () => {
        ctx = loadScript({
            config: {
                ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
                expedienteId: '11',
                containerActions: {
                    deleteAction: 'aa_delete_expediente',
                    nonce: 'x',
                    listUrl: 'https://evil.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes'
                }
            }
        });
        assert.equal(ctx.sandbox.window.AAAdmin.ExpedienteDetailActions._getState().mounted, false);
        assert.equal(ctx.fetches.length, 0);
    });

    it('listUrl con expediente_id → fail closed', () => {
        ctx = loadScript({
            config: {
                ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
                expedienteId: '11',
                containerActions: {
                    deleteAction: 'aa_delete_expediente',
                    nonce: 'x',
                    listUrl: 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes&expediente_id=11'
                }
            }
        });
        assert.equal(ctx.sandbox.window.AAAdmin.ExpedienteDetailActions._getState().mounted, false);
    });

    it('abre/cierra menú, aria-expanded, Escape y fuera', () => {
        ctx = loadScript({});
        const { trigger, menu } = ctx.dom;
        trigger.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}, target: trigger
        });
        assert.equal(trigger.getAttribute('aria-expanded'), 'true');
        assert.equal(menu.classList.contains('hidden'), false);

        ctx.dispatchDoc('keydown', { key: 'Escape', type: 'keydown' });
        assert.equal(trigger.getAttribute('aria-expanded'), 'false');

        trigger.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}, target: trigger
        });
        ctx.dispatchDoc('click', { type: 'click', target: { id: 'outside' } });
        assert.equal(trigger.getAttribute('aria-expanded'), 'false');
    });

    it('abrir confirmación y cancelar/X/overlay/Escape → cero fetch', () => {
        ctx = loadScript({});
        const { trigger, deleteItem } = ctx.dom;
        trigger.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}, target: trigger
        });
        deleteItem.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}, target: deleteItem
        });
        assert.equal(ctx.getModalOpen(), true);
        assert.equal(ctx.fetches.length, 0);

        // Cancel via real shared-modal data-aa-modal-close click path
        const cancel = ctx.byId['aa-expediente-detail-delete-cancel'];
        assert.ok(cancel);
        assert.equal(cancel.getAttribute('data-aa-modal-close'), '');
        ctx.closeViaSharedClick(cancel);
        assert.equal(ctx.getModalOpen(), false);
        assert.equal(ctx.fetches.length, 0);
        assert.equal(trigger._focused, true);
        assert.equal(ctx.sandbox.window.AAAdmin.ExpedienteDetailActions._getState().confirmationOpen, false);
        assert.equal(ctx.AAAdmin.modal.close, ctx.baseClose);
    });

    it('Cancelar/X/overlay/Escape → reabrir menú; cancel→reabrir→confirmar un POST', async () => {
        ctx = loadScript({});
        const api = ctx.sandbox.window.AAAdmin.ExpedienteDetailActions;
        const { trigger, deleteItem, overlay, closeBtn } = ctx.dom;

        function cycleClose(closeFn) {
            ctx.openDeleteConfirm();
            assert.equal(ctx.getModalOpen(), true);
            assert.equal(api._getState().confirmationOpen, true);
            assert.notEqual(ctx.AAAdmin.modal.close, ctx.baseClose);
            closeFn();
            assert.equal(ctx.getModalOpen(), false);
            assert.equal(ctx.fetches.length, 0);
            assert.equal(api._getState().confirmationOpen, false);
            assert.equal(ctx.AAAdmin.modal.close, ctx.baseClose);
            assert.equal(trigger.getAttribute('aria-expanded'), 'false');
            ctx.assertMenuCanReopen();
        }

        cycleClose(() => ctx.closeViaSharedClick(ctx.byId['aa-expediente-detail-delete-cancel']));
        cycleClose(() => ctx.closeViaSharedClick(closeBtn));
        cycleClose(() => ctx.closeViaSharedClick(overlay));
        cycleClose(() => ctx.closeViaEscape());

        // Cancel → reopen → confirm → exactly one POST
        ctx.openDeleteConfirm();
        ctx.closeViaSharedClick(ctx.byId['aa-expediente-detail-delete-cancel']);
        ctx.assertMenuCanReopen();
        ctx.openDeleteConfirm();
        const confirm = ctx.byId['aa-expediente-detail-delete-confirm'];
        confirm.dispatch('click', { type: 'click', preventDefault() {}, stopPropagation() {} });
        await flush();
        await flush();
        assert.equal(ctx.fetches.length, 1);
        assert.equal(ctx.location.replaceCalls.length, 1);
        assert.equal(api._getState().confirmationOpen, false);

        // Trigger still present; no duplicate listeners (second mount rejected)
        assert.ok(ctx.byId['aa-expediente-detail-tools-trigger']);
        assert.equal(api.mount(), false);
        assert.equal(deleteItem._listeners.click.length, 1);
        assert.equal(trigger._listeners.click.length, 1);
    });

    it('abrir/cerrar repetido no acumula wrappers ni fetch; destroy tras cancel restaura close', () => {
        ctx = loadScript({});
        const api = ctx.sandbox.window.AAAdmin.ExpedienteDetailActions;
        const { closeBtn } = ctx.dom;

        for (let i = 0; i < 3; i++) {
            ctx.openDeleteConfirm();
            ctx.closeViaSharedClick(closeBtn);
            assert.equal(ctx.AAAdmin.modal.close, ctx.baseClose);
            assert.equal(api._getState().confirmationOpen, false);
        }
        assert.equal(ctx.fetches.length, 0);

        ctx.openDeleteConfirm();
        ctx.closeViaSharedClick(ctx.byId['aa-expediente-detail-delete-cancel']);
        api.destroy();
        assert.equal(api._getState().mounted, false);
        assert.equal(ctx.AAAdmin.modal.close, ctx.baseClose);
        assert.equal(ctx.dom.trigger._listeners.click.length, 0);
        assert.equal(ctx.dom.deleteItem._listeners.click.length, 0);
    });

    it('confirmación → un POST exacto sin client_id; éxito → un replace', async () => {
        ctx = loadScript({});
        const { deleteItem } = ctx.dom;
        deleteItem.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}, target: deleteItem
        });
        const confirm = ctx.byId['aa-expediente-detail-delete-confirm'];
        assert.ok(confirm);
        confirm.dispatch('click', { type: 'click', preventDefault() {}, stopPropagation() {} });
        await flush();
        await flush();

        assert.equal(ctx.fetches.length, 1);
        const body = ctx.fetches[0].init.body;
        assert.match(body, /action=aa_delete_expediente/);
        assert.match(body, /_wpnonce=nonce-aa_expedientes_nonce/);
        assert.match(body, /expediente_id=11/);
        assert.doesNotMatch(body, /client_id/);
        assert.doesNotMatch(body, /scopeKey/);
        assert.equal(ctx.location.replaceCalls.length, 1);
        assert.equal(
            ctx.location.replaceCalls[0],
            'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=expedientes'
        );
    });

    it('doble clic → un request; botón aria-busy', async () => {
        let resolveFetch;
        ctx = loadScript({
            fetchImpl() {
                return new Promise((resolve) => {
                    resolveFetch = resolve;
                });
            }
        });
        const { deleteItem } = ctx.dom;
        deleteItem.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}, target: deleteItem
        });
        const confirm = ctx.byId['aa-expediente-detail-delete-confirm'];
        confirm.dispatch('click', { type: 'click' });
        confirm.dispatch('click', { type: 'click' });
        assert.equal(confirm.disabled, true);
        assert.equal(confirm.getAttribute('aria-busy'), 'true');
        assert.equal(ctx.sandbox.window.AAAdmin.ExpedienteDetailActions._getState().inFlight, true);

        resolveFetch({
            ok: true,
            status: 200,
            text: () => Promise.resolve(JSON.stringify({
                success: true,
                data: { deleted: true, expediente_id: 11 }
            }))
        });
        await flush();
        await flush();
        assert.equal(ctx.location.replaceCalls.length, 1);
    });

    it('HTTP 200 envelope incompleto / id distinto → no navegar', async () => {
        ctx = loadScript({
            fetchImpl: () => Promise.resolve({
                ok: true,
                status: 200,
                text: () => Promise.resolve(JSON.stringify({
                    success: true,
                    data: { deleted: true, expediente_id: 99 }
                }))
            })
        });
        const { deleteItem } = ctx.dom;
        deleteItem.dispatch('click', { type: 'click', preventDefault() {}, stopPropagation() {} });
        ctx.byId['aa-expediente-detail-delete-confirm'].dispatch('click', { type: 'click' });
        await flush();
        await flush();
        assert.equal(ctx.location.replaceCalls.length, 0);
        const err = ctx.byId['aa-expediente-detail-delete-error'];
        assert.ok(err);
        assert.equal(err.classList.contains('hidden'), false);
        assert.match(err.textContent, /No se pudo eliminar/);
    });

    it('409 resource_busy y 502 → mensajes sanitizados, sin navegar', async () => {
        const cases = [
            { status: 409, code: 'resource_busy', msg: /siendo modificado/ },
            { status: 409, code: 'aggregate_inconsistent', msg: /requieren revisión/ },
            { status: 502, code: 'delete_failed', msg: /imágenes/ }
        ];
        for (const c of cases) {
            if (ctx) ctx.sandbox.window.AAAdmin.ExpedienteDetailActions.destroy();
            ctx = loadScript({
                fetchImpl: () => Promise.resolve({
                    ok: false,
                    status: c.status,
                    text: () => Promise.resolve(JSON.stringify({
                        success: false,
                        data: { code: c.code, message: 'SECRET path/uuid' }
                    }))
                })
            });
            ctx.dom.deleteItem.dispatch('click', {
                type: 'click', preventDefault() {}, stopPropagation() {}
            });
            ctx.byId['aa-expediente-detail-delete-confirm'].dispatch('click', { type: 'click' });
            await flush();
            await flush();
            assert.equal(ctx.location.replaceCalls.length, 0);
            const err = ctx.byId['aa-expediente-detail-delete-error'];
            assert.match(err.textContent, c.msg);
            assert.doesNotMatch(err.textContent, /SECRET|path|uuid/i);
        }
    });

    it('network fail → no navegar; nueva confirmación permite reintento', async () => {
        let calls = 0;
        ctx = loadScript({
            fetchImpl: () => {
                calls += 1;
                if (calls === 1) return Promise.reject(new Error('network'));
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve(JSON.stringify({
                        success: true,
                        data: { deleted: true, expediente_id: 11 }
                    }))
                });
            }
        });
        ctx.dom.deleteItem.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}
        });
        ctx.byId['aa-expediente-detail-delete-confirm'].dispatch('click', { type: 'click' });
        await flush();
        await flush();
        assert.equal(ctx.location.replaceCalls.length, 0);

        ctx.AAAdmin.modal.close();
        ctx.dom.deleteItem.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}
        });
        ctx.byId['aa-expediente-detail-delete-confirm'].dispatch('click', { type: 'click' });
        await flush();
        await flush();
        assert.equal(calls, 2);
        assert.equal(ctx.location.replaceCalls.length, 1);
    });

    it('destroy limpia; respuesta stale no navega', async () => {
        let resolveFetch;
        ctx = loadScript({
            fetchImpl: () => new Promise((resolve) => { resolveFetch = resolve; })
        });
        const api = ctx.sandbox.window.AAAdmin.ExpedienteDetailActions;
        ctx.dom.deleteItem.dispatch('click', {
            type: 'click', preventDefault() {}, stopPropagation() {}
        });
        ctx.byId['aa-expediente-detail-delete-confirm'].dispatch('click', { type: 'click' });
        api.destroy();
        resolveFetch({
            ok: true,
            status: 200,
            text: () => Promise.resolve(JSON.stringify({
                success: true,
                data: { deleted: true, expediente_id: 11 }
            }))
        });
        await flush();
        await flush();
        assert.equal(ctx.location.replaceCalls.length, 0);
        assert.equal(api._getState().mounted, false);
    });
});
