'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/canonical-shell-container-form.js'
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

function boot(fetchImpl, payloads) {
    const openBtn = createEl('aa-shell-open-create-btn');
    const modal = createEl('aa-shell-container-modal');
    const form = createEl('aa-shell-container-form');
    const modalTitle = createEl('aa-shell-container-modal-title');
    modalTitle.textContent = 'Nueva lista';
    const titleInput = createEl('aa-shell-container-title');
    const detailsInput = createEl('aa-shell-container-details');
    const titleError = createEl('aa-shell-container-title-error');
    titleError.classList.add('hidden');
    const statusEl = createEl('aa-shell-container-status');
    statusEl.classList.add('hidden');
    const submitBtn = createEl('aa-shell-container-submit-btn');
    submitBtn.textContent = 'Crear lista';
    const cancelBtn = createEl('aa-shell-container-modal-cancel-btn');
    const closeBtn = createEl('aa-shell-container-modal-close-btn');
    const backdrop = createEl('aa-shell-container-modal-backdrop');

    const deleteModal = createEl('aa-shell-delete-container-modal');
    const deleteBackdrop = createEl('aa-shell-delete-container-modal-backdrop');
    const deleteCloseBtn = createEl('aa-shell-delete-container-modal-close-btn');
    const deleteCancelBtn = createEl('aa-shell-delete-container-modal-cancel-btn');
    const deleteConfirmBtn = createEl('aa-shell-delete-container-confirm-btn');
    const deleteReloadBtn = createEl('aa-shell-delete-container-reload-btn');
    deleteReloadBtn.classList.add('hidden');
    const deleteTitleEl = createEl('aa-shell-delete-container-title');
    const deleteStatusEl = createEl('aa-shell-delete-container-status');
    deleteStatusEl.classList.add('hidden');

    const editBtns = [];
    const deleteBtns = [];
    (payloads || []).forEach((payload, idx) => {
        const editBtn = createEl('aa-shell-edit-container-btn-' + idx);
        editBtn.className = 'aa-shell-edit-container-btn';
        editBtn.setAttribute('data-aa-container', JSON.stringify(payload));
        editBtns.push(editBtn);

        const delBtn = createEl('aa-shell-delete-container-btn-' + idx);
        delBtn.className = 'aa-shell-delete-container-btn';
        delBtn.setAttribute('data-aa-container', JSON.stringify(payload));
        deleteBtns.push(delBtn);
    });

    const byId = {
        'aa-shell-open-create-btn': openBtn,
        'aa-shell-container-modal': modal,
        'aa-shell-container-modal-backdrop': backdrop,
        'aa-shell-container-modal-close-btn': closeBtn,
        'aa-shell-container-modal-cancel-btn': cancelBtn,
        'aa-shell-container-modal-title': modalTitle,
        'aa-shell-container-form': form,
        'aa-shell-container-title': titleInput,
        'aa-shell-container-details': detailsInput,
        'aa-shell-container-title-error': titleError,
        'aa-shell-container-status': statusEl,
        'aa-shell-container-submit-btn': submitBtn,
        'aa-shell-delete-container-modal': deleteModal,
        'aa-shell-delete-container-modal-backdrop': deleteBackdrop,
        'aa-shell-delete-container-modal-close-btn': deleteCloseBtn,
        'aa-shell-delete-container-modal-cancel-btn': deleteCancelBtn,
        'aa-shell-delete-container-confirm-btn': deleteConfirmBtn,
        'aa-shell-delete-container-reload-btn': deleteReloadBtn,
        'aa-shell-delete-container-title': deleteTitleEl,
        'aa-shell-delete-container-status': deleteStatusEl
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
            if (selector === '.aa-shell-edit-container-btn') {
                return editBtns;
            }
            if (selector === '.aa-shell-delete-container-btn') {
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
            AA_CANONICAL_SHELL_CONTAINER_FORM: {
                ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
                createAction: 'aa_create_canonical_container',
                createNonce: 'create-nonce',
                updateAction: 'aa_update_canonical_container',
                updateNonce: 'update-nonce',
                deleteAction: 'aa_delete_canonical_container',
                deleteNonce: 'delete-nonce',
                familyKey: 'finance',
                variantKey: 'general',
                maxTitleLength: 200
            },
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

    vm.runInNewContext(fs.readFileSync(jsPath, 'utf8'), env, {
        filename: 'canonical-shell-container-form.js'
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
        deleteTitleEl,
        deleteStatusEl,
        deleteConfirmBtn,
        deleteCancelBtn,
        deleteReloadBtn,
        documentListeners,
        getAssignedUrl: () => assignedUrl,
        getFetchCalls: () => fetchCalls,
        getLastFormData: () => lastFormData,
        getReloaded: () => reloaded
    };
}

describe('canonical-shell-container-form', () => {
    it('abre create y enfoca título', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.modal.classList.contains('hidden'), false);
        assert.equal(ui.modalTitle.textContent, 'Nueva lista');
        assert.equal(ui.submitBtn.textContent, 'Crear lista');
        assert.equal(ui.titleInput.value, '');
        assert.ok(ui.titleInput.focusCalls >= 1, 'focus title');
    });

    it('cierra con Escape y restaura foco create', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.openBtn._listeners.click[0]();
        ui.documentListeners.keydown[0]({ key: 'Escape' });
        assert.equal(ui.modal.classList.contains('hidden'), true);
        assert.ok(ui.openBtn.focusCalls >= 1, 'restore focus');
    });

    it('edit precarga lista correcta y no arrastra entre cards', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }), [
            { id: 7, title: 'Lista "A" & <b>', details: "línea1\nlínea2" },
            { id: 8, title: 'Lista B', details: '' }
        ]);
        ui.editBtns[0]._listeners.click[0]();
        assert.equal(ui.modalTitle.textContent, 'Editar lista');
        assert.equal(ui.submitBtn.textContent, 'Guardar cambios');
        assert.equal(ui.titleInput.value, 'Lista "A" & <b>');
        assert.equal(ui.detailsInput.value, "línea1\nlínea2");

        ui.documentListeners.keydown[0]({ key: 'Escape' });
        ui.editBtns[1]._listeners.click[0]();
        assert.equal(ui.titleInput.value, 'Lista B');
        assert.equal(ui.detailsInput.value, '');
    });

    it('pasar de update a create limpia campos', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }), [
            { id: 3, title: 'Prev', details: 'det' }
        ]);
        ui.editBtns[0]._listeners.click[0]();
        assert.equal(ui.titleInput.value, 'Prev');
        ui.documentListeners.keydown[0]({ key: 'Escape' });
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.modalTitle.textContent, 'Nueva lista');
        assert.equal(ui.submitBtn.textContent, 'Crear lista');
        assert.equal(ui.titleInput.value, '');
        assert.equal(ui.detailsInput.value, '');
    });

    it('create: doble submit una petición y navega', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: {
                    status: 'confirmed',
                    redirect_url: 'https://example.test/list?family=finance&variant=general'
                }
            })
        }));
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'Mi lista';
        const ev = { preventDefault() {} };
        ui.form._listeners.submit[0](ev);
        ui.form._listeners.submit[0](ev);
        assert.equal(ui.getFetchCalls(), 1);
        assert.equal(ui.getLastFormData().action, 'aa_create_canonical_container');
        assert.equal(ui.getLastFormData().nonce, 'create-nonce');
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(
            ui.getAssignedUrl(),
            'https://example.test/list?family=finance&variant=general'
        );
    });

    it('update: action/nonce correctos y redirect', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: {
                    status: 'confirmed',
                    redirect_url: 'https://example.test/list?family=finance&variant=general'
                }
            })
        }), [
            { id: 11, title: 'Edit me', details: null }
        ]);
        ui.editBtns[0]._listeners.click[0]();
        ui.titleInput.value = 'Editado';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        assert.equal(ui.getLastFormData().action, 'aa_update_canonical_container');
        assert.equal(ui.getLastFormData().nonce, 'update-nonce');
        assert.equal(ui.getLastFormData().container_id, '11');
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(
            ui.getAssignedUrl(),
            'https://example.test/list?family=finance&variant=general'
        );
    });

    it('delete: abre confirmación con título seguro y foco Cancelar', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }), [
            { id: 9, title: 'Lista "X" <b>', details: '' },
            { id: 10, title: 'Otra', details: '' }
        ]);
        ui.deleteBtns[0]._listeners.click[0]();
        assert.equal(ui.deleteModal.classList.contains('hidden'), false);
        assert.equal(ui.deleteTitleEl.textContent, 'Lista "X" <b>');
        assert.ok(ui.deleteCancelBtn.focusCalls >= 1, 'focus cancel');
        ui.documentListeners.keydown[0]({ key: 'Escape' });
        assert.equal(ui.deleteModal.classList.contains('hidden'), true);
        assert.ok(ui.deleteBtns[0].focusCalls >= 1, 'restore delete trigger');

        ui.deleteBtns[1]._listeners.click[0]();
        assert.equal(ui.deleteTitleEl.textContent, 'Otra');
    });

    it('delete: doble submit una petición y redirect', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: {
                    status: 'confirmed',
                    redirect_url: 'https://example.test/list?family=finance&variant=general'
                }
            })
        }), [
            { id: 4, title: 'Borrar', details: '' }
        ]);
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        assert.equal(ui.getFetchCalls(), 1);
        assert.equal(ui.getLastFormData().action, 'aa_delete_canonical_container');
        assert.equal(ui.getLastFormData().nonce, 'delete-nonce');
        assert.equal(ui.getLastFormData().container_id, '4');
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(
            ui.getAssignedUrl(),
            'https://example.test/list?family=finance&variant=general'
        );
    });

    it('delete uncertain bloquea retry y muestra recarga', async () => {
        const ui = boot(async () => ({
            status: 409,
            text: async () => JSON.stringify({
                success: false,
                data: {
                    code: 'uncertain',
                    message: 'No fue posible confirmar si la lista se eliminó. Recarga el listado para verificarlo antes de intentarlo nuevamente.',
                    redirect_url: 'https://example.test/list?family=finance&variant=general'
                }
            })
        }), [
            { id: 6, title: 'X', details: '' }
        ]);
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getFetchCalls(), 1);
        assert.equal(ui.getAssignedUrl(), null);
        assert.equal(ui.deleteConfirmBtn.disabled, true);
        assert.equal(ui.deleteReloadBtn.classList.contains('hidden'), false);
        assert.ok(ui.deleteStatusEl.textContent.indexOf('Recarga el listado') !== -1);

        ui.deleteConfirmBtn._listeners.click[0]();
        assert.equal(ui.getFetchCalls(), 1);

        ui.deleteReloadBtn._listeners.click[0]();
        assert.equal(
            ui.getAssignedUrl(),
            'https://example.test/list?family=finance&variant=general'
        );
    });

    it('delete persistence_failed permite retry', async () => {
        let calls = 0;
        const ui = boot(async () => {
            calls += 1;
            return {
                status: 500,
                text: async () => JSON.stringify({
                    success: false,
                    data: { code: 'persistence_failed', message: 'No se pudo eliminar la lista.' }
                })
            };
        }, [
            { id: 2, title: 'Retry', details: '' }
        ]);
        ui.deleteBtns[0]._listeners.click[0]();
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.deleteConfirmBtn.disabled, false);
        ui.deleteConfirmBtn._listeners.click[0]();
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(calls, 2);
    });

    it('uncertain update no navega y restaura controles', async () => {
        const ui = boot(async () => ({
            status: 409,
            text: async () => JSON.stringify({
                success: false,
                data: {
                    code: 'uncertain',
                    message: 'No fue posible confirmar si los cambios se guardaron. Revisa el listado antes de intentarlo nuevamente.'
                }
            })
        }), [
            { id: 5, title: 'X', details: '' }
        ]);
        ui.editBtns[0]._listeners.click[0]();
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getFetchCalls(), 1);
        assert.equal(ui.getAssignedUrl(), null);
        assert.ok(ui.statusEl.textContent.indexOf('Revisa el listado') !== -1);
        assert.equal(ui.submitBtn.disabled, false);
        assert.equal(ui.modal.getAttribute('aria-busy'), null);
    });

    it('invalid_title restaura controles', async () => {
        const ui = boot(async () => ({
            status: 400,
            text: async () => JSON.stringify({
                success: false,
                data: {
                    code: 'invalid_title',
                    message: 'El nombre de la lista no puede estar vacío.'
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

    it('HTML en payload se trata como texto en .value', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }), [
            { id: 1, title: '<script>alert(1)</script>', details: '<img src=x onerror=1>' }
        ]);
        ui.editBtns[0]._listeners.click[0]();
        assert.equal(ui.titleInput.value, '<script>alert(1)</script>');
        assert.equal(ui.detailsInput.value, '<img src=x onerror=1>');
    });
});
