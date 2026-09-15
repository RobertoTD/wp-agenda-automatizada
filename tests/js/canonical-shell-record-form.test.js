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
    const imagesOffered = options.imagesOffered === true;
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

    const deleteImageModal = createEl('aa-shell-delete-image-modal');
    const deleteImageBackdrop = createEl('aa-shell-delete-image-modal-backdrop');
    const deleteImageCloseBtn = createEl('aa-shell-delete-image-modal-close-btn');
    const deleteImageCancelBtn = createEl('aa-shell-delete-image-modal-cancel-btn');
    const deleteImageConfirmBtn = createEl('aa-shell-delete-image-confirm-btn');
    deleteImageConfirmBtn.textContent = 'Eliminar imagen';
    const deleteImageAbortBtn = createEl('aa-shell-delete-image-abort-btn');
    deleteImageAbortBtn.classList.add('hidden');
    const deleteImageReloadBtn = createEl('aa-shell-delete-image-reload-btn');
    deleteImageReloadBtn.classList.add('hidden');
    const deleteImageIdLabel = createEl('aa-shell-delete-image-id-label');
    const deleteImageStatusEl = createEl('aa-shell-delete-image-status');
    deleteImageStatusEl.classList.add('hidden');

    const editBtns = [];
    const deleteBtns = [];
    const deleteImageBtns = [];
    const imageNodes = [];
    const imagePayloads = options.imagePayloads || [{ id: 77, record_id: 9 }];
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
    imagePayloads.forEach((payload, idx) => {
        const imgBtn = createEl('aa-shell-delete-image-btn-' + idx);
        imgBtn.className = 'aa-shell-delete-image-btn';
        imgBtn.setAttribute('data-aa-image', JSON.stringify(payload));
        deleteImageBtns.push(imgBtn);

        const node = createEl('aa-shell-record-image-' + idx);
        node.setAttribute('data-aa-image-id', String(payload.id));
        const parent = {
            children: [node],
            removeChild(child) {
                const i = this.children.indexOf(child);
                if (i >= 0) {
                    this.children.splice(i, 1);
                }
                child._removed = true;
            }
        };
        node.parentNode = parent;
        imageNodes.push(node);
    });

    const amountWrap = createEl('aa-shell-record-amount-field');
    amountWrap.classList.add('hidden');
    const amountInput = createEl('aa-shell-record-amount');
    amountInput.disabled = true;
    const amountError = createEl('aa-shell-record-amount-error');
    amountError.classList.add('hidden');
    const amountUnavailable = createEl('aa-shell-record-amount-unavailable');
    amountUnavailable.classList.add('hidden');

    const imagesWrap = createEl('aa-shell-record-images-field');
    imagesWrap.classList.add('hidden');
    const imageInput = createEl('aa-shell-record-image-input');
    imageInput.disabled = true;
    imageInput.files = null;
    const imageTrigger = createEl('aa-shell-record-image-trigger');
    const imagePreviewWrap = createEl('aa-shell-record-image-preview-wrap');
    imagePreviewWrap.classList.add('hidden');
    const imagePreview = createEl('aa-shell-record-image-preview');
    const imagePreviewMeta = createEl('aa-shell-record-image-preview-meta');
    const imageRemove = createEl('aa-shell-record-image-remove');
    const imageRetry = createEl('aa-shell-record-image-retry');
    imageRetry.classList.add('hidden');
    imageRetry.disabled = true;
    const imagesError = createEl('aa-shell-record-images-error');
    imagesError.classList.add('hidden');
    const imagesStatus = createEl('aa-shell-record-images-status');
    imagesStatus.classList.add('hidden');

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
        'aa-shell-record-images-field': imagesWrap,
        'aa-shell-record-image-input': imageInput,
        'aa-shell-record-image-trigger': imageTrigger,
        'aa-shell-record-image-preview-wrap': imagePreviewWrap,
        'aa-shell-record-image-preview': imagePreview,
        'aa-shell-record-image-preview-meta': imagePreviewMeta,
        'aa-shell-record-image-remove': imageRemove,
        'aa-shell-record-image-retry': imageRetry,
        'aa-shell-record-images-error': imagesError,
        'aa-shell-record-images-status': imagesStatus,
        'aa-shell-delete-record-modal': deleteModal,
        'aa-shell-delete-record-modal-backdrop': deleteBackdrop,
        'aa-shell-delete-record-modal-close-btn': deleteCloseBtn,
        'aa-shell-delete-record-modal-cancel-btn': deleteCancelBtn,
        'aa-shell-delete-record-confirm-btn': deleteConfirmBtn,
        'aa-shell-delete-record-abort-btn': deleteAbortBtn,
        'aa-shell-delete-record-reload-btn': deleteReloadBtn,
        'aa-shell-delete-record-title': deleteTitleEl,
        'aa-shell-delete-record-status': deleteStatusEl,
        'aa-shell-delete-image-modal': deleteImageModal,
        'aa-shell-delete-image-modal-backdrop': deleteImageBackdrop,
        'aa-shell-delete-image-modal-close-btn': deleteImageCloseBtn,
        'aa-shell-delete-image-modal-cancel-btn': deleteImageCancelBtn,
        'aa-shell-delete-image-confirm-btn': deleteImageConfirmBtn,
        'aa-shell-delete-image-abort-btn': deleteImageAbortBtn,
        'aa-shell-delete-image-reload-btn': deleteImageReloadBtn,
        'aa-shell-delete-image-id-label': deleteImageIdLabel,
        'aa-shell-delete-image-status': deleteImageStatusEl
    };

    const documentListeners = {};
    let assignedUrl = null;
    let fetchCalls = 0;
    let reloaded = false;
    const formDataCalls = [];

    const capabilityContributions = {};
    if (amountOffered) {
        capabilityContributions.amount = { offered: true };
    }
    if (imagesOffered) {
        capabilityContributions.images = { offered: true };
    }

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
            if (selector === '.aa-shell-delete-image-btn, .aa-shell-resume-image-delete-btn') {
                return deleteImageBtns;
            }
            if (typeof selector === 'string' && selector.indexOf('[data-aa-image-id=') === 0) {
                const match = selector.match(/data-aa-image-id="(\d+)"/);
                if (!match) {
                    return [];
                }
                return imageNodes.filter((n) => n.getAttribute('data-aa-image-id') === match[1] && !n._removed);
            }
            return [];
        },
        addEventListener(type, fn) {
            documentListeners[type] = documentListeners[type] || [];
            documentListeners[type].push(fn);
        },
        createElement(tag) {
            return createEl(tag);
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
                deleteImageAction: 'aa_delete_canonical_record_image',
                deleteImageNonce: 'delete-image-nonce',
                attachImageAction: 'aa_attach_canonical_record_image',
                attachImageNonce: 'attach-image-nonce',
                familyKey: 'finance',
                containerId: 42,
                maxTitleLength: 200,
                capabilityContributions: capabilityContributions
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
        URL: {
            createObjectURL() {
                return 'blob:mock-preview';
            },
            revokeObjectURL() {}
        },
        fetch: (url, options) => {
            fetchCalls += 1;
            lastFormData = options && options.body ? options.body._data : null;
            formDataCalls.push(lastFormData ? Object.assign({}, lastFormData) : null);
            return fetchImpl();
        },
        console
    };

    const amountJsPath = path.join(
        __dirname,
        '../../includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-amount-field.js'
    );
    const imagesJsPath = path.join(
        __dirname,
        '../../includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-field.js'
    );
    vm.runInNewContext(fs.readFileSync(amountJsPath, 'utf8'), env, {
        filename: 'canonical-shell-amount-field.js'
    });
    vm.runInNewContext(fs.readFileSync(imagesJsPath, 'utf8'), env, {
        filename: 'canonical-shell-images-field.js'
    });
    vm.runInNewContext(fs.readFileSync(jsPath, 'utf8'), env, {
        filename: 'canonical-shell-record-form.js'
    });

    return {
        openBtn,
        editBtns,
        deleteBtns,
        deleteImageBtns,
        imageNodes,
        modal,
        deleteModal,
        deleteImageModal,
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
        imagesWrap,
        imageInput,
        imageRetry,
        imagesError,
        imagesStatus,
        deleteTitleEl,
        deleteStatusEl,
        deleteConfirmBtn,
        deleteAbortBtn,
        deleteCancelBtn,
        deleteReloadBtn,
        deleteImageConfirmBtn,
        deleteImageCancelBtn,
        deleteImageStatusEl,
        deleteImageIdLabel,
        documentListeners,
        modules: env.window.AA_CANONICAL_SHELL_CAPABILITY_MODULES,
        getAssignedUrl: () => assignedUrl,
        getFetchCalls: () => fetchCalls,
        getLastFormData: () => lastFormData,
        getFormDataCalls: () => formDataCalls,
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

    it('image delete: un clic un request y remueve del DOM', async () => {
        const ui = boot(
            async () => ({
                status: 200,
                text: async () => JSON.stringify({
                    success: true,
                    data: { status: 'confirmed', image_id: 77, record_id: 9, container_id: 42 }
                })
            }),
            [{ id: 9, title: 'R', details: '' }],
            { imagePayloads: [{ id: 77, record_id: 9 }] }
        );
        assert.ok(ui.deleteImageBtns[0]._listeners.click);
        ui.deleteImageBtns[0]._listeners.click[0]({});
        assert.equal(ui.deleteImageModal.classList.contains('hidden'), false);
        ui.deleteImageConfirmBtn._listeners.click[0]({});
        ui.deleteImageConfirmBtn._listeners.click[0]({});
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getFetchCalls(), 1);
        assert.equal(ui.getLastFormData().action, 'aa_delete_canonical_record_image');
        assert.equal(ui.getLastFormData().image_id, '77');
        assert.equal(ui.imageNodes[0]._removed, true);
        assert.equal(ui.deleteImageModal.classList.contains('hidden'), true);
    });

    it('image delete: incomplete deja Continuar sin auto-reintento', async () => {
        let calls = 0;
        const ui = boot(
            async () => {
                calls += 1;
                return {
                    status: 409,
                    text: async () => JSON.stringify({
                        success: false,
                        data: {
                            code: 'incomplete',
                            message: 'La eliminación no terminó. Pulsa Continuar para seguir.',
                            can_continue: true
                        }
                    })
                };
            },
            [{ id: 9, title: 'R', details: '' }],
            { imagePayloads: [{ id: 77, record_id: 9 }] }
        );
        ui.deleteImageBtns[0]._listeners.click[0]({});
        ui.deleteImageConfirmBtn._listeners.click[0]({});
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(calls, 1);
        assert.equal(ui.deleteImageConfirmBtn.textContent, 'Continuar');
        assert.equal(ui.deleteImageModal.classList.contains('hidden'), false);
        assert.equal(ui.imageNodes[0]._removed, undefined);
    });

    it('image delete: Cerrar cierra solo el modal', async () => {
        const ui = boot(
            async () => ({
                status: 409,
                text: async () => JSON.stringify({
                    success: false,
                    data: { code: 'incomplete', message: 'incompleto', can_continue: true }
                })
            }),
            [{ id: 9, title: 'R', details: '' }],
            { imagePayloads: [{ id: 77, record_id: 9 }] }
        );
        ui.deleteImageBtns[0]._listeners.click[0]({});
        ui.deleteImageConfirmBtn._listeners.click[0]({});
        await new Promise((resolve) => setTimeout(resolve, 0));
        ui.deleteImageCancelBtn._listeners.click[0]();
        assert.equal(ui.deleteImageModal.classList.contains('hidden'), true);
        assert.equal(ui.getAssignedUrl(), null);
        assert.equal(ui.getReloaded(), false);
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

    it('images offered: control visible en create; inactive/no offered: oculto', () => {
        const offered = boot(async () => ({ status: 200, text: async () => '{}' }), [], {
            imagesOffered: true
        });
        offered.openBtn._listeners.click[0]();
        assert.equal(offered.imagesWrap.classList.contains('hidden'), false);
        assert.equal(offered.imageInput.disabled, false);

        const hidden = boot(async () => ({ status: 200, text: async () => '{}' }));
        hidden.openBtn._listeners.click[0]();
        assert.equal(hidden.imagesWrap.classList.contains('hidden'), true);
    });

    it('create: attach solo tras resource_id; luego redirect', async () => {
        let call = 0;
        const ui = boot(
            async () => {
                call += 1;
                if (call === 1) {
                    return {
                        status: 200,
                        text: async () => JSON.stringify({
                            success: true,
                            data: {
                                status: 'confirmed',
                                resource_id: 501,
                                redirect_url: 'https://example.test/records?container_id=42'
                            }
                        })
                    };
                }
                return {
                    status: 200,
                    text: async () => JSON.stringify({
                        success: true,
                        data: { image: { id: 9, width: 10, height: 10, byte_size: 100, created_at: '' } }
                    })
                };
            },
            [],
            { imagesOffered: true }
        );
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'Con imagen';
        ui.modules.images._test.setPendingForTests({
            blob: { size: 1200 },
            previewUrl: 'blob:x',
            operationId: '11111111-1111-4111-8111-111111111111'
        });
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getFetchCalls(), 2);
        const calls = ui.getFormDataCalls();
        assert.equal(calls[0].action, 'aa_create_canonical_record');
        assert.equal(calls[0].record_id, undefined);
        assert.equal(calls[1].action, 'aa_attach_canonical_record_image');
        assert.equal(calls[1].record_id, '501');
        assert.equal(calls[1].upload_operation_id, '11111111-1111-4111-8111-111111111111');
        assert.equal(ui.getAssignedUrl(), 'https://example.test/records?container_id=42');
    });

    it('edit existente: attach con record_id del registro', async () => {
        let call = 0;
        const ui = boot(
            async () => {
                call += 1;
                if (call === 1) {
                    return {
                        status: 200,
                        text: async () => JSON.stringify({
                            success: true,
                            data: {
                                status: 'confirmed',
                                resource_id: 11,
                                redirect_url: 'https://example.test/records?r=1'
                            }
                        })
                    };
                }
                return {
                    status: 200,
                    text: async () => JSON.stringify({
                        success: true,
                        data: { image: { id: 3 } }
                    })
                };
            },
            [{ id: 11, title: 'Old', details: '', capabilities: { images: { status: 'known_absent' } } }],
            { imagesOffered: true }
        );
        ui.editBtns[0]._listeners.click[0]();
        ui.modules.images._test.setPendingForTests({
            blob: { size: 800 },
            previewUrl: 'blob:y',
            operationId: '22222222-2222-4222-8222-222222222222'
        });
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
        const calls = ui.getFormDataCalls();
        assert.equal(calls[1].record_id, '11');
        assert.equal(ui.getAssignedUrl(), 'https://example.test/records?r=1');
    });

    it('cancelar picker: sin pending no llama attach', async () => {
        const ui = boot(
            async () => ({
                status: 200,
                text: async () => JSON.stringify({
                    success: true,
                    data: {
                        status: 'confirmed',
                        resource_id: 77,
                        redirect_url: 'https://example.test/ok'
                    }
                })
            }),
            [],
            { imagesOffered: true }
        );
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'Sin imagen';
        assert.equal(ui.modules.images.hasPending(), false);
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getFetchCalls(), 1);
        assert.equal(ui.getAssignedUrl(), 'https://example.test/ok');
    });

    it('fallo attach recuperable: conserva registro y reutiliza operation_id en retry', async () => {
        let call = 0;
        const opId = '33333333-3333-4333-8333-333333333333';
        const ui = boot(
            async () => {
                call += 1;
                if (call === 1) {
                    return {
                        status: 200,
                        text: async () => JSON.stringify({
                            success: true,
                            data: {
                                status: 'confirmed',
                                resource_id: 88,
                                redirect_url: 'https://example.test/after'
                            }
                        })
                    };
                }
                if (call === 2) {
                    return {
                        status: 502,
                        text: async () => JSON.stringify({
                            success: false,
                            data: {
                                code: 'transfer_failed',
                                message: 'Transferencia fallida'
                            }
                        })
                    };
                }
                return {
                    status: 200,
                    text: async () => JSON.stringify({
                        success: true,
                        data: { image: { id: 4 } }
                    })
                };
            },
            [],
            { imagesOffered: true }
        );
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'Retry';
        ui.modules.images._test.setPendingForTests({
            blob: { size: 900 },
            previewUrl: 'blob:z',
            operationId: opId
        });
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), null);
        assert.equal(ui.modal.classList.contains('hidden'), false);
        assert.ok(ui.statusEl.textContent.indexOf('Transferencia') !== -1);
        assert.equal(ui.modules.images.hasPending(), true);
        assert.equal(ui.imageRetry.classList.contains('hidden'), false);

        ui.imageRetry._listeners.click[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        await new Promise((resolve) => setTimeout(resolve, 0));
        const calls = ui.getFormDataCalls();
        assert.equal(calls[1].upload_operation_id, opId);
        assert.equal(calls[2].upload_operation_id, opId);
        assert.equal(ui.getAssignedUrl(), 'https://example.test/after');
    });

    it('images + amount: amount sigue en WriteBag; images no', async () => {
        const ui = boot(
            async () => ({
                status: 200,
                text: async () => JSON.stringify({
                    success: true,
                    data: {
                        status: 'confirmed',
                        resource_id: 1,
                        redirect_url: 'https://example.test/x'
                    }
                })
            }),
            [],
            { amountOffered: true, imagesOffered: true }
        );
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'Both';
        ui.amountInput.value = '12.50';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getLastFormData().amount, '12.50');
        assert.equal(ui.getLastFormData().file, undefined);
        assert.equal(ui.getFetchCalls(), 1);
    });
});
