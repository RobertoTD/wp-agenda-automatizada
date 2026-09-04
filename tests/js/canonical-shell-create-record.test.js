'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/canonical-shell-create-record.js'
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
        disabled: false,
        textContent: '',
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

function boot(fetchImpl) {
    const openBtn = createEl('aa-shell-open-create-record-btn');
    const modal = createEl('aa-shell-create-record-modal');
    const form = createEl('aa-shell-create-record-form');
    const titleInput = createEl('aa-shell-create-record-title');
    const detailsInput = createEl('aa-shell-create-record-details');
    const titleError = createEl('aa-shell-create-record-title-error');
    titleError.classList.add('hidden');
    const statusEl = createEl('aa-shell-create-record-status');
    statusEl.classList.add('hidden');
    const submitBtn = createEl('aa-shell-create-record-submit-btn');
    const cancelBtn = createEl('aa-shell-create-record-modal-cancel-btn');
    const closeBtn = createEl('aa-shell-create-record-modal-close-btn');
    const backdrop = createEl('aa-shell-create-record-modal-backdrop');

    const byId = {
        'aa-shell-open-create-record-btn': openBtn,
        'aa-shell-create-record-modal': modal,
        'aa-shell-create-record-modal-backdrop': backdrop,
        'aa-shell-create-record-modal-close-btn': closeBtn,
        'aa-shell-create-record-modal-cancel-btn': cancelBtn,
        'aa-shell-create-record-form': form,
        'aa-shell-create-record-title': titleInput,
        'aa-shell-create-record-details': detailsInput,
        'aa-shell-create-record-title-error': titleError,
        'aa-shell-create-record-status': statusEl,
        'aa-shell-create-record-submit-btn': submitBtn
    };

    const documentListeners = {};
    let assignedUrl = null;
    let fetchCalls = 0;

    documentRef = {
        activeElement: openBtn,
        getElementById(id) {
            return byId[id] || null;
        },
        addEventListener(type, fn) {
            documentListeners[type] = documentListeners[type] || [];
            documentListeners[type].push(fn);
        }
    };

    const env = {
        window: {
            AA_CANONICAL_SHELL_CREATE_RECORD: {
                ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
                action: 'aa_create_canonical_record',
                nonce: 'test-nonce',
                familyKey: 'finance',
                variantKey: 'general',
                containerId: 42,
                maxTitleLength: 200
            },
            location: {
                assign(url) {
                    assignedUrl = url;
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
        fetch: () => {
            fetchCalls += 1;
            return fetchImpl();
        },
        console
    };

    vm.runInNewContext(fs.readFileSync(jsPath, 'utf8'), env, {
        filename: 'canonical-shell-create-record.js'
    });

    return {
        openBtn,
        modal,
        form,
        titleInput,
        titleError,
        statusEl,
        submitBtn,
        documentListeners,
        getAssignedUrl: () => assignedUrl,
        getFetchCalls: () => fetchCalls
    };
}

describe('canonical-shell-create-record', () => {
    it('abre modal y enfoca título', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.openBtn._listeners.click[0]();
        assert.equal(ui.modal.classList.contains('hidden'), false);
        assert.ok(ui.titleInput.focusCalls >= 1, 'focus title');
    });

    it('cierra con Escape y restaura foco', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.openBtn._listeners.click[0]();
        ui.documentListeners.keydown[0]({ key: 'Escape' });
        assert.equal(ui.modal.classList.contains('hidden'), true);
        assert.ok(ui.openBtn.focusCalls >= 1, 'restore focus');
    });

    it('doble submit produce una sola petición y navega', async () => {
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
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(ui.getAssignedUrl(), 'https://example.test/records?container_id=42');
    });

    it('uncertain no navega', async () => {
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
        assert.equal(ui.submitBtn.disabled, false);
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
});
