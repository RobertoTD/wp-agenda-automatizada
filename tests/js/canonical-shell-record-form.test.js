'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js'
);

function createEl(id) {
    const el = {
        id: id || '',
        classList: {
            _set: new Set(['hidden']),
            add(name) {
                this._set.add(name);
            },
            remove(name) {
                this._set.delete(name);
            },
            contains(name) {
                return this._set.has(name);
            }
        },
        attributes: {},
        value: '',
        textContent: '',
        disabled: false,
        _listeners: {},
        focusCalls: 0,
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
        },
        removeAttribute(name) {
            delete this.attributes[name];
        },
        addEventListener(type, fn) {
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(fn);
        },
        focus() {
            this.focusCalls += 1;
            documentRef.activeElement = this;
        },
        reset() {
            this.value = '';
        }
    };
    return el;
}

let documentRef;
let lastFormData;

function boot(fetchImpl, payloads, options) {
    options = options || {};
    const amountOffered = options.amountOffered === true;
    const openBtn = createEl('aa-shell-open-create-record-btn');
    const modal = createEl('aa-shell-record-modal');
    const form = createEl('aa-shell-record-form');
    const modalTitle = createEl('aa-shell-record-modal-title');
    modalTitle.textContent = 'Nuevo registro';
    const titleInput = createEl('aa-shell-record-title');
    const detailsInput = createEl('aa-shell-record-details');
    const titleError = createEl('aa-shell-record-title-error');
    titleError.classList.add('hidden');
    const statusEl = createEl('aa-shell-record-status');
    statusEl.classList.add('hidden');
    const submitBtn = createEl('aa-shell-record-submit-btn');
    submitBtn.textContent = 'Crear registro';
    const cancelBtn = createEl('aa-shell-record-modal-cancel-btn');
    const closeBtn = createEl('aa-shell-record-modal-close-btn');
    const backdrop = createEl('aa-shell-record-modal-backdrop');

    const deleteModal = createEl('aa-shell-delete-record-modal');
    const deleteBackdrop = createEl('aa-shell-delete-record-modal-backdrop');
    const deleteCloseBtn = createEl('aa-shell-delete-record-modal-close-btn');
    const deleteCancelBtn = createEl('aa-shell-delete-record-modal-cancel-btn');
    const deleteConfirmBtn = createEl('aa-shell-delete-record-confirm-btn');
    deleteConfirmBtn.textContent = 'Eliminar registro';
    const deleteAbortBtn = createEl('aa-shell-delete-record-abort-btn');
    deleteAbortBtn.classList.add('hidden');
    const deleteReloadBtn = createEl('aa-shell-delete-record-reload-btn');
    deleteReloadBtn.classList.add('hidden');
    const deleteTitleEl = createEl('aa-shell-delete-record-title');
    const deleteStatusEl = createEl('aa-shell-delete-record-status');
    deleteStatusEl.classList.add('hidden');

    const editBtns = [];
    const deleteBtns = [];
    (payloads || []).forEach((payload, idx) => {
        const editBtn = createEl('aa-shell-edit-record-btn-' + idx);
        editBtn.className = 'aa-shell-edit-record-btn';
        editBtn.setAttribute('data-aa-record', JSON.stringify(payload));
        editBtns.push(editBtn);

        const delBtn = createEl('aa-shell-delete-record-btn-' + idx);
        delBtn.className = 'aa-shell-delete-record-btn';
        delBtn.setAttribute('data-aa-record', JSON.stringify(payload));
        deleteBtns.push(delBtn);
    });

    const amountWrap = createEl('aa-shell-record-amount-field');
    amountWrap.classList.add('hidden');
    const amountInput = createEl('aa-shell-record-amount');
    amountInput.disabled = true;
    const amountError = createEl('aa-shell-record-amount-error');
    amountError.classList.add('hidden');
    const amountUnavailable = createEl('aa-shell-record-amount-unavailable');
    amountUnavailable.classList.add('hidden');

    const byId = {
        'aa-shell-open-create-record-btn': openBtn,
        'aa-shell-record-modal': modal,
        'aa-shell-record-modal-backdrop': backdrop,
        'aa-shell-record-modal-close-btn': closeBtn,
        'aa-shell-record-modal-cancel-btn': cancelBtn,
        'aa-shell-record-modal-title': modalTitle,
        'aa-shell-record-form': form,
        'aa-shell-record-title': titleInput,
        'aa-shell-record-details': detailsInput,
        'aa-shell-record-title-error': titleError,
        'aa-shell-record-status': statusEl,
        'aa-shell-record-submit-btn': submitBtn,
        'aa-shell-record-amount-field': amountWrap,
        'aa-shell-record-amount': amountInput,
        'aa-shell-record-amount-error': amountError,
        'aa-shell-record-amount-unavailable': amountUnavailable,
        'aa-shell-delete-record-modal': deleteModal,
        'aa-shell-delete-record-modal-backdrop': deleteBackdrop,
        'aa-shell-delete-record-modal-close-btn': deleteCloseBtn,
        'aa-shell-delete-record-modal-cancel-btn': deleteCancelBtn,
        'aa-shell-delete-record-confirm-btn': deleteConfirmBtn,
        'aa-shell-delete-record-abort-btn': deleteAbortBtn,
        'aa-shell-delete-record-reload-btn': deleteReloadBtn,
        'aa-shell-delete-record-title': deleteTitleEl,
        'aa-shell-delete-record-status': deleteStatusEl
    };

    const documentListeners = {};
    let assignedUrl = null;
    let fetchCalls = 0;
    let reloaded = false;

    documentRef = {
        activeElement: openBtn,
        getElementById(id) {
            return byId[id] || null;
        },
        querySelectorAll(selector) {
            if (selector === '.aa-shell-edit-record-btn') {
                return editBtns;
            }
            if (selector === '.aa-shell-delete-record-btn') {
                return deleteBtns;
            }
            return [];
        },
        addEventListener(type, fn) {
            documentListeners[type] = documentListeners[type] || [];
            documentListeners[type].push(fn);
        }
    };

    const env = {
        window: {
            AA_CANONICAL_SHELL_RECORD_FORM: {
                ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
                createAction: 'aa_create_canonical_record',
                createNonce: 'create-nonce',
                updateAction: 'aa_update_canonical_record',
                updateNonce: 'update-nonce',
                deleteAction: 'aa_delete_canonical_record',
                deleteNonce: 'delete-nonce',
                familyKey: 'finance',
                containerId: 42,
                maxTitleLength: 200,
                capabilityContributions: amountOffered
                    ? { amount: { offered: true } }
                    : {}
            },
            AA_CANONICAL_SHELL_CAPABILITY_MODULES: {},
            location: {
                assign(url) {
                    assignedUrl = url;
                },
                reload() {
                    reloaded = true;
                }
            },
            document: documentRef
        },
        document: documentRef,
        FormData: class {
            constructor() {
                this._data = {};
            }
            append(k, v) {
                this._data[k] = v;
            }
        },
        fetch: (url, options) => {
            fetchCalls += 1;
            lastFormData = options && options.body ? options.body._data : null;
            return fetchImpl();
        },
        console
    };

    const amountJsPath = path.join(
        __dirname,
        '../../includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-amount-field.js'
    );
    vm.runInNewContext(fs.readFileSync(amountJsPath, 'utf8'), env, {
        filename: 'canonical-shell-amount-field.js'
    });
    vm.runInNewContext(fs.readFileSync(jsPath, 'utf8'), env, {
        filename: 'canonical-shell-record-form.js'
    });

    return {
        openBtn,
        editBtns,
        deleteBtns,
        modal,
        deleteModal,
        modalTitle,
        form,
        titleInput,
        detailsInput,
        titleError,
        statusEl,
        submitBtn,
        amountWrap,
        amountInput,
        amountError,
        amountUnavailable,
        deleteTitleEl,
        deleteStatusEl,
        deleteConfirmBtn,
        deleteAbortBtn,
        deleteCancelBtn,
        deleteReloadBtn,
        documentListeners,
        getAssignedUrl: () => assignedUrl,
        getFetchCalls: () => fetchCalls,
        getLastFormData: () => lastFormData,
        getReloaded: () => reloaded
    };
}

describe('canonical-shell-record-form', () => {
    it('abre create y enfoca título', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.modal.classList.contains('hidden'), false);
        assert.equal(ui.modalTitle.textContent, 'Nuevo registro');
        assert.equal(ui.submitBtn.textContent, 'Crear registro');
        assert.ok(ui.titleInput.focusCalls >= 1, 'focus title');
    });

    it('cierra con Escape y restaura foco create', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.openBtn._listeners.click[0]();
        ui.documentListeners.keydown[0]({ key: 'Escape' });
        assert.equal(ui.modal.classList.contains('hidden'), true);
        assert.ok(ui.openBtn.focusCalls >= 1, 'restore focus');
    });

    it('create: doble submit una petición y navega', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: {
                    status: 'confirmed',
                    redirect_url: 'https://example.test/records?container_id=42'
                }
            })
        }));
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'Mi registro';
        const ev = { preventDefault() {} };
        ui.form._listeners.submit[0](ev);
        ui.form._listeners.submit[0](ev);
        assert.equal(ui.getFetchCalls(), 1);
        assert.equal(ui.getLastFormData().action, 'aa_create_canonical_record');
        assert.equal(ui.getLastFormData().nonce, 'create-nonce');
        assert.equal(ui.getLastFormData().record_id, undefined);
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), 'https://example.test/records?container_id=42');
    });

    it('edit: precarga dos cards distintas y no arrastra datos', () => {
        const ui = boot(
            async () => ({ status: 200, text: async () => '{}' }),
            [
                {
                    id: 11,
                    title: 'Titulo "A" & <b>x</b>',
                    details: "linea1\nlinea2"
                },
                {
                    id: 22,
                    title: "Café 'B'",
                    details: ''
                }
            ]
        );

        ui.editBtns[0]._listeners.click[0]();
        assert.equal(ui.modalTitle.textContent, 'Editar registro');
        assert.equal(ui.submitBtn.textContent, 'Guardar cambios');
        assert.equal(ui.titleInput.value, 'Titulo "A" & <b>x</b>');
        assert.equal(ui.detailsInput.value, "linea1\nlinea2");

        ui.documentListeners.keydown[0]({ key: 'Escape' });
        assert.ok(ui.editBtns[0].focusCalls >= 1, 'restore edit focus');

        ui.editBtns[1]._listeners.click[0]();
        assert.equal(ui.titleInput.value, "Café 'B'");
        assert.equal(ui.detailsInput.value, '');
        assert.ok(ui.statusEl.classList.contains('hidden'), 'status cleared');
    });

    it('edit: action/nonce update y confirmed redirect', async () => {
        const ui = boot(
            async () => ({
                status: 200,
                text: async () => JSON.stringify({
                    success: true,
                    data: {
                        status: 'confirmed',
                        redirect_url: 'https://example.test/records?container_id=42'
                    }
                })
            }),
            [{ id: 11, title: 'Old', details: '' }]
        );
        ui.editBtns[0]._listeners.click[0]();
        ui.titleInput.value = 'New';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        assert.equal(ui.getLastFormData().action, 'aa_update_canonical_record');
        assert.equal(ui.getLastFormData().nonce, 'update-nonce');
        assert.equal(ui.getLastFormData().record_id, '11');
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), 'https://example.test/records?container_id=42');
    });

    it('uncertain update no navega', async () => {
        const ui = boot(
            async () => ({
                status: 409,
                text: async () => JSON.stringify({
                    success: false,
                    data: {
                        code: 'uncertain',
                        message: 'No fue posible confirmar si los cambios se guardaron. Revisa el registro antes de intentarlo nuevamente.'
                    }
                })
            }),
            [{ id: 5, title: 'X', details: '' }]
        );
        ui.editBtns[0]._listeners.click[0]();
        ui.titleInput.value = 'Y';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), null);
        assert.ok(ui.statusEl.textContent.indexOf('Revisa el registro') !== -1);
        assert.equal(ui.submitBtn.disabled, false);
        assert.equal(ui.modal.classList.contains('hidden'), false);
    });

    it('create uncertain no navega', async () => {
        const ui = boot(async () => ({
            status: 409,
            text: async () => JSON.stringify({
                success: false,
                data: {
                    code: 'uncertain',
                    message: 'No fue posible confirmar si el registro se creó. Revisa la lista antes de intentarlo nuevamente.'
                }
            })
        }));
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'X';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), null);
        assert.ok(ui.statusEl.textContent.indexOf('Revisa la lista') !== -1);
    });

    it('error restaura controles', async () => {
        const ui = boot(async () => ({
            status: 400,
            text: async () => JSON.stringify({
                success: false,
                data: {
                    code: 'invalid_title',
                    message: 'El título del registro no puede estar vacío.'
                }
            })
        }));
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'Ok';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.submitBtn.disabled, false);
        assert.ok(ui.titleError.textContent.indexOf('vacío') !== -1);
    });

    it('delete: título seguro y card correcta', () => {
        const ui = boot(
            async () => ({ status: 200, text: async () => '{}' }),
            [
                { id: 11, title: 'Titulo "A" & <b>x</b>', details: '' },
                { id: 22, title: "Café 'B'", details: '' }
            ]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        assert.equal(ui.deleteModal.classList.contains('hidden'), false);
        assert.equal(ui.deleteTitleEl.textContent, 'Titulo "A" & <b>x</b>');
        assert.ok(ui.deleteCancelBtn.focusCalls >= 1, 'focus cancel');

        ui.documentListeners.keydown[0]({ key: 'Escape' });
        assert.equal(ui.deleteModal.classList.contains('hidden'), true);
        assert.ok(ui.deleteBtns[0].focusCalls >= 1, 'restore delete focus');

        ui.deleteBtns[1]._listeners.click[0]();
        assert.equal(ui.deleteTitleEl.textContent, "Café 'B'");
    });

    it('delete: confirmed redirect y una sola petición', async () => {
        const ui = boot(
            async () => ({
                status: 200,
                text: async () => JSON.stringify({
                    success: true,
                    data: {
                        status: 'confirmed',
                        redirect_url: 'https://example.test/records?container_id=42'
                    }
                })
            }),
            [{ id: 11, title: 'Borrar', details: '' }]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        assert.equal(ui.getFetchCalls(), 1);
        assert.equal(ui.getLastFormData().action, 'aa_delete_canonical_record');
        assert.equal(ui.getLastFormData().nonce, 'delete-nonce');
        assert.equal(ui.getLastFormData().record_id, '11');
        assert.equal(ui.getLastFormData().retire_action, undefined);
        assert.equal(ui.getLastFormData().mandate_id, undefined);
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), 'https://example.test/records?container_id=42');
    });

    it('delete: persistence_failed permite retry', async () => {
        let calls = 0;
        const ui = boot(
            async () => {
                calls += 1;
                return {
                    status: 500,
                    text: async () => JSON.stringify({
                        success: false,
                        data: {
                            code: 'persistence_failed',
                            message: 'No se pudo eliminar el registro.'
                        }
                    })
                };
            },
            [{ id: 9, title: 'X', details: '' }]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.deleteConfirmBtn.disabled, false);
        assert.ok(ui.deleteStatusEl.textContent.indexOf('No se pudo eliminar') !== -1);
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(calls, 2);
    });

    it('delete: uncertain bloquea retry y muestra recarga', async () => {
        const ui = boot(
            async () => ({
                status: 409,
                text: async () => JSON.stringify({
                    success: false,
                    data: {
                        code: 'uncertain',
                        message: 'No fue posible confirmar si el registro se eliminó. Recarga la lista para verificarlo antes de intentarlo nuevamente.',
                        redirect_url: 'https://example.test/records?container_id=42'
                    }
                })
            }),
            [{ id: 9, title: 'X', details: '' }]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), null);
        assert.equal(ui.deleteConfirmBtn.disabled, true);
        assert.equal(ui.deleteReloadBtn.classList.contains('hidden'), false);
        assert.ok(ui.deleteStatusEl.textContent.indexOf('Recarga la lista') !== -1);

        const before = ui.getFetchCalls();
        ui.deleteConfirmBtn._listeners.click[0]();
        assert.equal(ui.getFetchCalls(), before);

        ui.deleteReloadBtn._listeners.click[0]();
        assert.equal(ui.getAssignedUrl(), 'https://example.test/records?container_id=42');
    });

    it('delete: incomplete deja Continuar sin bloquear', async () => {
        const ui = boot(
            async () => ({
                status: 409,
                text: async () => JSON.stringify({
                    success: false,
                    data: {
                        code: 'incomplete',
                        message: 'La eliminación no terminó. Pulsa Continuar para seguir.',
                        can_continue: true,
                        can_cancel: false
                    }
                })
            }),
            [{ id: 9, title: 'X', details: '' }]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), null);
        assert.equal(ui.deleteConfirmBtn.disabled, false);
        assert.equal(ui.deleteConfirmBtn.textContent, 'Continuar');
        assert.equal(ui.deleteAbortBtn.classList.contains('hidden'), true);
        assert.ok(ui.deleteStatusEl.textContent.indexOf('Continuar') !== -1);

        const before = ui.getFetchCalls();
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getFetchCalls(), before + 1);
        assert.equal(ui.getLastFormData().retire_action, undefined);
        assert.equal(ui.getLastFormData().mandate_id, undefined);
    });

    it('delete: conflict muestra cancelar local y no envía mandate_id', async () => {
        let lastAction = null;
        const ui = boot(
            async () => ({
                status: 409,
                text: async () => JSON.stringify({
                    success: false,
                    data: {
                        code: 'conflict',
                        message: 'No se pudo preparar la eliminación.',
                        can_continue: true,
                        can_cancel: true
                    }
                })
            }),
            [{ id: 9, title: 'X', details: '' }]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.deleteConfirmBtn.textContent, 'Reintentar');
        assert.equal(ui.deleteAbortBtn.classList.contains('hidden'), false);

        ui.deleteAbortBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        lastAction = ui.getLastFormData().retire_action;
        assert.equal(lastAction, 'cancel');
        assert.equal(ui.getLastFormData().mandate_id, undefined);
        assert.equal(ui.getLastFormData().record_id, '9');
    });

    it('delete: cancel_rejected oculta abortar y ofrece Continuar', async () => {
        const ui = boot(
            async () => ({
                status: 409,
                text: async () => JSON.stringify({
                    success: false,
                    data: {
                        code: 'cancel_rejected',
                        message: 'Ya hubo comunicación remota. No se puede cancelar. Pulsa Continuar para recuperar el protocolo.',
                        can_continue: true,
                        can_cancel: false
                    }
                })
            }),
            [{ id: 9, title: 'X', details: '' }]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteAbortBtn.classList.remove('hidden');
        ui.deleteAbortBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.deleteConfirmBtn.textContent, 'Continuar');
        assert.equal(ui.deleteAbortBtn.classList.contains('hidden'), true);
        assert.equal(ui.deleteConfirmBtn.disabled, false);
        assert.ok(ui.deleteStatusEl.textContent.indexOf('No se puede cancelar') !== -1);
    });

    it('delete: intervention_required no promete Continuar', async () => {
        const ui = boot(
            async () => ({
                status: 409,
                text: async () => JSON.stringify({
                    success: false,
                    data: {
                        code: 'intervention_required',
                        message: 'Esta eliminación no puede continuar sola. Recarga la lista.',
                        can_continue: false,
                        can_cancel: false,
                        redirect_url: 'https://example.test/records?container_id=42'
                    }
                })
            }),
            [{ id: 9, title: 'X', details: '' }]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), null);
        assert.equal(ui.deleteConfirmBtn.disabled, true);
        assert.equal(ui.deleteAbortBtn.classList.contains('hidden'), true);
        assert.equal(ui.deleteReloadBtn.classList.contains('hidden'), false);
        assert.ok(ui.deleteStatusEl.textContent.indexOf('no puede continuar sola') !== -1);
    });

    it('delete: cancelled cierra el modal sin redirect', async () => {
        const ui = boot(
            async () => ({
                status: 200,
                text: async () => JSON.stringify({
                    success: true,
                    data: { status: 'cancelled', resource_id: 9, container_id: 42 }
                })
            }),
            [{ id: 9, title: 'X', details: '' }]
        );
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteAbortBtn.classList.remove('hidden');
        ui.deleteAbortBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), null);
        assert.equal(ui.deleteModal.classList.contains('hidden'), true);
        assert.equal(ui.getLastFormData().retire_action, 'cancel');
    });

    it('amount offered: create muestra vacío y envía amount', async () => {
        const ui = boot(
            async () => ({
                status: 200,
                text: async () => JSON.stringify({
                    success: true,
                    data: { status: 'confirmed', redirect_url: 'https://example.test/ok' }
                })
            }),
            [],
            { amountOffered: true }
        );
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.amountWrap.classList.contains('hidden'), false);
        assert.equal(ui.amountInput.disabled, false);
        assert.equal(ui.amountInput.value, '');
        ui.titleInput.value = 'Con cero';
        ui.amountInput.value = '0';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getLastFormData().amount, '0');
    });

    it('amount: limpia entre registros y omite read_failed', async () => {
        const ui = boot(
            async () => ({
                status: 200,
                text: async () => JSON.stringify({
                    success: true,
                    data: { status: 'confirmed', redirect_url: 'https://example.test/ok' }
                })
            }),
            [
                {
                    id: 1,
                    title: 'A',
                    details: '',
                    capabilities: { amount: { status: 'known_value', value: '7.25' } }
                },
                {
                    id: 2,
                    title: 'B',
                    details: '',
                    capabilities: { amount: { status: 'read_failed' } }
                }
            ],
            { amountOffered: true }
        );

        ui.editBtns[0]._listeners.click[0]();
        assert.equal(ui.amountInput.value, '7.25');
        assert.equal(ui.amountInput.disabled, false);

        ui.editBtns[1]._listeners.click[0]();
        assert.equal(ui.amountInput.value, '');
        assert.equal(ui.amountInput.disabled, true);
        assert.equal(ui.amountUnavailable.classList.contains('hidden'), false);
        ui.titleInput.value = 'B edit';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(Object.prototype.hasOwnProperty.call(ui.getLastFormData(), 'amount'), false);
    });

    it('lista sin amount no monta contribución', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.amountWrap.classList.contains('hidden'), true);
    });

    it('error invalid_amount en módulo amount', async () => {
        const ui = boot(
            async () => ({
                status: 400,
                text: async () => JSON.stringify({
                    success: false,
                    data: { code: 'invalid_amount', message: 'Importe inválido' }
                })
            }),
            [],
            { amountOffered: true }
        );
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'X';
        ui.amountInput.value = 'abc';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.amountError.textContent, 'Importe inválido');
        assert.equal(ui.amountError.classList.contains('hidden'), false);
        assert.ok(ui.statusEl.classList.contains('hidden') || ui.statusEl.textContent === '');
    });
});
