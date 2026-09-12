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
        className: '',
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
        checked: false,
        type: '',
        _children: [],
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
        appendChild(child) {
            this._children.push(child);
        },
        querySelectorAll(selector) {
            if (selector !== 'input[data-aa-capability-key]') {
                return [];
            }
            const out = [];
            const walk = (node) => {
                if (node && typeof node.getAttribute === 'function'
                    && node.getAttribute('data-aa-capability-key')) {
                    out.push(node);
                }
                (node._children || []).forEach(walk);
            };
            walk(this);
            return out;
        },
        focus() {
            this.focusCalls += 1;
            documentRef.activeElement = this;
        },
        reset() {
            this.value = '';
        }
    };
    Object.defineProperty(el, 'innerHTML', {
        configurable: true,
        get() {
            return '';
        },
        set(value) {
            if (value === '') {
                this._children = [];
            }
        }
    });
    return el;
}

let documentRef;
let lastFormData;

function boot(fetchImpl, payloads, cfgOverrides) {
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
    const familyField = createEl('aa-shell-container-family-field');
    familyField.classList.add('hidden');
    const familySelect = createEl('aa-shell-container-family');
    const familyError = createEl('aa-shell-container-family-error');
    familyError.classList.add('hidden');
    const capabilitiesMount = createEl('aa-shell-container-capabilities');
    capabilitiesMount.classList.remove('hidden');
    const capabilitiesStatus = createEl('aa-shell-container-capabilities-status');
    capabilitiesStatus.classList.add('hidden');

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
        'aa-shell-container-family-field': familyField,
        'aa-shell-container-family': familySelect,
        'aa-shell-container-family-error': familyError,
        'aa-shell-container-capabilities': capabilitiesMount,
        'aa-shell-container-capabilities-status': capabilitiesStatus,
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
        createElement(tag) {
            return createEl(tag);
        },
        addEventListener(type, fn) {
            documentListeners[type] = documentListeners[type] || [];
            documentListeners[type].push(fn);
        }
    };

    const formCfg = Object.assign({
        ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
        createAction: 'aa_create_canonical_container',
        createNonce: 'create-nonce',
        updateAction: 'aa_update_canonical_container',
        updateNonce: 'update-nonce',
        deleteAction: 'aa_delete_canonical_container',
        deleteNonce: 'delete-nonce',
        familyKey: 'finance',
        listsScope: '',
        shellView: 'containers',
        availableFamilies: [],
        requireFamilySelect: false,
        maxTitleLength: 200,
        familyCapabilityOptions: {},
        editContainerCapabilities: null
    }, cfgOverrides || {});

    const env = {
        window: {
            AA_CANONICAL_SHELL_CONTAINER_FORM: formCfg,
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
        familyField,
        familySelect,
        familyError,
        capabilitiesMount,
        capabilitiesStatus,
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

    it('Todas + archive: preselecciona archive y mantiene select', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: {
                    status: 'confirmed',
                    redirect_url: 'https://example.test/list'
                }
            })
        }), [], {
            familyKey: '',
            listsScope: 'all',
            requireFamilySelect: true,
            availableFamilies: [
                { family_key: 'finance', label: 'Finanzas' },
                { family_key: 'archive', label: 'Archivo' }
            ]
        });
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.familyField.classList.contains('hidden'), false);
        assert.equal(ui.familySelect.value, 'archive');
        assert.ok(ui.familySelect.focusCalls >= 1, 'focus family select');
        ui.titleInput.value = 'Nueva';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        assert.equal(ui.getLastFormData().family_key, 'archive');
        assert.equal(ui.getLastFormData().lists_scope, 'all');
    });

    it('Todas sin archive + N>1: exige elección explícita', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }), [], {
            familyKey: '',
            listsScope: 'all',
            requireFamilySelect: true,
            availableFamilies: [
                { family_key: 'finance', label: 'Finanzas' },
                { family_key: 'legal', label: 'Legal' }
            ]
        });
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.familySelect.value, '');
        ui.titleInput.value = 'X';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        assert.equal(ui.getFetchCalls(), 0);
        assert.ok(ui.familyError.textContent.indexOf('Selecciona') !== -1);
    });

    it('Todas sin archive + N=1: usa esa familia sin select', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: { status: 'confirmed', redirect_url: 'https://example.test/ok' }
            })
        }), [], {
            familyKey: '',
            listsScope: 'all',
            requireFamilySelect: false,
            availableFamilies: [{ family_key: 'finance', label: 'Finanzas' }]
        });
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.familyField.classList.contains('hidden'), true);
        ui.titleInput.value = 'Solo';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        assert.equal(ui.getLastFormData().family_key, 'finance');
    });

    it('error de validación conserva familia preseleccionada', async () => {
        const ui = boot(async () => ({
            status: 400,
            text: async () => JSON.stringify({
                success: false,
                data: { code: 'invalid_title', message: 'El nombre de la lista no puede estar vacío.' }
            })
        }), [], {
            familyKey: '',
            listsScope: 'all',
            requireFamilySelect: true,
            availableFamilies: [
                { family_key: 'finance', label: 'Finanzas' },
                { family_key: 'archive', label: 'Archivo' }
            ]
        });
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.familySelect.value, 'archive');
        ui.familySelect.value = 'finance';
        ui.titleInput.value = 'Ok';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.familySelect.value, 'finance');
        assert.equal(ui.titleInput.value, 'Ok');
    });

    it('edit usa family_key de la tarjeta aunque listsScope=all', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: { status: 'confirmed', redirect_url: 'https://example.test/ok' }
            })
        }), [
            { id: 21, title: 'Card', details: '', family_key: 'finance' }
        ], {
            familyKey: '',
            listsScope: 'all',
            requireFamilySelect: true,
            availableFamilies: [
                { family_key: 'finance', label: 'Finanzas' },
                { family_key: 'archive', label: 'Archivo' }
            ]
        });
        ui.editBtns[0]._listeners.click[0]();
        assert.equal(ui.familyField.classList.contains('hidden'), true);
        ui.titleInput.value = 'Editado';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        assert.equal(ui.getLastFormData().family_key, 'finance');
        assert.equal(ui.getLastFormData().action, 'aa_update_canonical_container');
    });

    it('create marca defaults del repertorio familiar', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }), [], {
            familyCapabilityOptions: {
                finance: [
                    { key: 'amount', label: 'Importe', is_default: true },
                    { key: 'extra', label: 'Extra', is_default: false }
                ]
            }
        });
        ui.openBtn._listeners.click[0]();
        const inputs = ui.capabilitiesMount.querySelectorAll('input[data-aa-capability-key]');
        assert.equal(inputs.length, 2);
        assert.equal(inputs[0].getAttribute('data-aa-capability-key'), 'amount');
        assert.equal(inputs[0].checked, true);
        assert.equal(inputs[1].getAttribute('data-aa-capability-key'), 'extra');
        assert.equal(inputs[1].checked, false);
        assert.equal(ui.capabilitiesStatus.classList.contains('hidden'), true);
    });

    it('cambio de familia en create re-renderiza opciones y defaults', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }), [], {
            familyKey: '',
            listsScope: 'all',
            requireFamilySelect: true,
            availableFamilies: [
                { family_key: 'finance', label: 'Finanzas' },
                { family_key: 'archive', label: 'Archivo' }
            ],
            familyCapabilityOptions: {
                finance: [{ key: 'amount', label: 'Importe', is_default: true }],
                archive: [{ key: 'notes', label: 'Notas', is_default: false }]
            }
        });
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.familySelect.value, 'archive');
        let inputs = ui.capabilitiesMount.querySelectorAll('input[data-aa-capability-key]');
        assert.equal(inputs.length, 1);
        assert.equal(inputs[0].getAttribute('data-aa-capability-key'), 'notes');
        assert.equal(inputs[0].checked, false);

        ui.familySelect.value = 'finance';
        ui.familySelect._listeners.change[0]();
        inputs = ui.capabilitiesMount.querySelectorAll('input[data-aa-capability-key]');
        assert.equal(inputs.length, 1);
        assert.equal(inputs[0].getAttribute('data-aa-capability-key'), 'amount');
        assert.equal(inputs[0].checked, true);
    });

    it('create con repertorio vacío envía arrays JSON vacíos', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: { status: 'confirmed', redirect_url: 'https://example.test/ok' }
            })
        }), [], {
            familyCapabilityOptions: { finance: [] }
        });
        ui.openBtn._listeners.click[0]();
        ui.titleInput.value = 'Vacía';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        assert.equal(ui.getLastFormData().capability_selection_scope, '[]');
        assert.equal(ui.getLastFormData().capability_selection, '[]');
    });

    it('update unavailable omite campos de selección', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: { status: 'confirmed', redirect_url: 'https://example.test/ok' }
            })
        }), [
            { id: 33, title: 'Lista', details: '' }
        ], {
            familyCapabilityOptions: {
                finance: [{ key: 'amount', label: 'Importe', is_default: true }]
            },
            editContainerCapabilities: { status: 'unavailable' }
        });
        ui.editBtns[0]._listeners.click[0]();
        assert.equal(ui.capabilitiesMount.querySelectorAll('input[data-aa-capability-key]').length, 0);
        assert.equal(ui.capabilitiesStatus.classList.contains('hidden'), false);
        ui.titleInput.value = 'Ok';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        const body = ui.getLastFormData();
        assert.equal(Object.prototype.hasOwnProperty.call(body, 'capability_selection_scope'), false);
        assert.equal(Object.prototype.hasOwnProperty.call(body, 'capability_selection'), false);
    });

    it('records update envía return_view y selección activa del repertorio', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: { status: 'confirmed', redirect_url: 'https://example.test/records' }
            })
        }), [
            { id: 44, title: 'Padre', details: 'd' }
        ], {
            shellView: 'records',
            containersPage: 3,
            familyCapabilityOptions: {
                finance: [
                    { key: 'amount', label: 'Importe', is_default: true },
                    { key: 'extra', label: 'Extra', is_default: false }
                ]
            },
            editContainerCapabilities: {
                status: 'ok',
                active: ['amount', 'ghost'],
                assigned: ['amount', 'ghost']
            }
        });
        ui.editBtns[0]._listeners.click[0]();
        const inputs = ui.capabilitiesMount.querySelectorAll('input[data-aa-capability-key]');
        assert.equal(inputs.length, 2);
        assert.equal(inputs[0].checked, true);
        assert.equal(inputs[1].checked, false);
        ui.titleInput.value = 'Padre';
        ui.form._listeners.submit[0]({ preventDefault() {} });
        const body = ui.getLastFormData();
        assert.equal(body.return_view, 'records');
        assert.equal(body.containers_page, '3');
        assert.equal(body.capability_selection_scope, JSON.stringify(['amount', 'extra']));
        assert.equal(body.capability_selection, JSON.stringify(['amount']));
    });

    it('edit desde tarjeta usa payload.capabilities sobre boot unavailable', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }), [
            {
                id: 55,
                title: 'Con caps',
                details: '',
                family_key: 'finance',
                capabilities: {
                    status: 'ok',
                    active: ['amount'],
                    assigned: ['amount']
                }
            }
        ], {
            familyCapabilityOptions: {
                finance: [
                    { key: 'amount', label: 'Importe', is_default: true },
                    { key: 'extra', label: 'Extra', is_default: false }
                ]
            },
            editContainerCapabilities: { status: 'unavailable' }
        });
        ui.editBtns[0]._listeners.click[0]();
        const inputs = ui.capabilitiesMount.querySelectorAll('input[data-aa-capability-key]');
        assert.equal(inputs.length, 2);
        assert.equal(inputs[0].getAttribute('data-aa-capability-key'), 'amount');
        assert.equal(inputs[0].checked, true);
        assert.equal(inputs[1].checked, false);
        assert.equal(ui.capabilitiesStatus.classList.contains('hidden'), true);
    });
});
