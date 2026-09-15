'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-images-field.js'
);

function createEl(id) {
    return {
        id: id || '',
        classList: {
            _set: new Set(['hidden']),
            add(name) { this._set.add(name); },
            remove(name) { this._set.delete(name); },
            contains(name) { return this._set.has(name); }
        },
        attributes: { hidden: 'hidden' },
        value: '',
        textContent: '',
        disabled: true,
        files: null,
        _listeners: {},
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
        }
    };
}

function boot(fetchImpl) {
    const wrap = createEl('aa-shell-record-images-field');
    const fileInput = createEl('aa-shell-record-image-input');
    const trigger = createEl('aa-shell-record-image-trigger');
    const previewWrap = createEl('aa-shell-record-image-preview-wrap');
    const previewImg = createEl('aa-shell-record-image-preview');
    const previewMeta = createEl('aa-shell-record-image-preview-meta');
    const removeBtn = createEl('aa-shell-record-image-remove');
    const retryBtn = createEl('aa-shell-record-image-retry');
    const errorEl = createEl('aa-shell-record-images-error');
    errorEl.classList.add('hidden');
    const statusEl = createEl('aa-shell-record-images-status');
    statusEl.classList.add('hidden');

    const byId = {
        'aa-shell-record-images-field': wrap,
        'aa-shell-record-image-input': fileInput,
        'aa-shell-record-image-trigger': trigger,
        'aa-shell-record-image-preview-wrap': previewWrap,
        'aa-shell-record-image-preview': previewImg,
        'aa-shell-record-image-preview-meta': previewMeta,
        'aa-shell-record-image-remove': removeBtn,
        'aa-shell-record-image-retry': retryBtn,
        'aa-shell-record-images-error': errorEl,
        'aa-shell-record-images-status': statusEl
    };

    let fetchCalls = 0;
    let lastBody = null;

    const documentRef = {
        getElementById(id) {
            return byId[id] || null;
        },
        createElement() {
            return createEl('canvas');
        }
    };

    const env = {
        window: { AA_CANONICAL_SHELL_CAPABILITY_MODULES: {} },
        document: documentRef,
        FormData: class {
            constructor() { this._data = {}; }
            append(k, v) { this._data[k] = v; }
        },
        URL: {
            createObjectURL() { return 'blob:preview'; },
            revokeObjectURL() {}
        },
        fetch: (url, options) => {
            fetchCalls += 1;
            lastBody = options && options.body ? options.body._data : null;
            return fetchImpl();
        },
        console
    };
    env.window.document = documentRef;

    vm.runInNewContext(fs.readFileSync(jsPath, 'utf8'), env, {
        filename: 'canonical-shell-images-field.js'
    });

    const mod = env.window.AA_CANONICAL_SHELL_CAPABILITY_MODULES.images;
    return {
        mod,
        wrap,
        fileInput,
        errorEl,
        retryBtn,
        getFetchCalls: () => fetchCalls,
        getLastBody: () => lastBody
    };
}

describe('canonical-shell-images-field', () => {
    it('apply muestra; clear oculta y limpia pending', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.mod.apply({ status: 'known_absent' });
        assert.equal(ui.wrap.classList.contains('hidden'), false);
        assert.equal(ui.fileInput.disabled, false);
        ui.mod._test.setPendingForTests({
            blob: { size: 10 },
            previewUrl: 'blob:x',
            operationId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'
        });
        assert.equal(ui.mod.hasPending(), true);
        ui.mod.clear();
        assert.equal(ui.wrap.classList.contains('hidden'), true);
        assert.equal(ui.mod.hasPending(), false);
        assert.equal(ui.fileInput.disabled, true);
    });

    it('collect es no-op (no WriteBag)', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        const body = { append() { throw new Error('should not append'); } };
        ui.mod.apply({ status: 'known_absent' });
        ui.mod.collect(body);
    });

    it('prepare null no deja pending', async () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        const result = await ui.mod._test.prepareCanonicalImage(null);
        assert.equal(result.ok, false);
        assert.equal(ui.mod.hasPending(), false);
    });

    it('afterRecordSaved sin pending no fetch', async () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.mod.apply({ status: 'known_absent' });
        const result = await ui.mod.afterRecordSaved({
            recordId: 1,
            ajaxUrl: 'https://example.test/ajax',
            attachAction: 'aa_attach_canonical_record_image',
            attachNonce: 'n',
            familyKey: 'archive',
            containerId: 2
        });
        assert.equal(result.ok, true);
        assert.equal(result.skipped, true);
        assert.equal(ui.getFetchCalls(), 0);
    });

    it('afterRecordSaved exitoso limpia pending', async () => {
        const ui = boot(async () => ({
            status: 200,
            text: async () => JSON.stringify({
                success: true,
                data: { image: { id: 5, width: 1, height: 1, byte_size: 1, created_at: '' } }
            })
        }));
        ui.mod.apply({ status: 'known_absent' });
        const op = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
        ui.mod._test.setPendingForTests({
            blob: { size: 50 },
            previewUrl: 'blob:p',
            operationId: op
        });
        const result = await ui.mod.afterRecordSaved({
            recordId: 9,
            ajaxUrl: 'https://example.test/ajax',
            attachAction: 'aa_attach_canonical_record_image',
            attachNonce: 'nonce',
            familyKey: 'archive',
            containerId: 3
        });
        assert.equal(result.ok, true);
        assert.equal(ui.mod.hasPending(), false);
        assert.equal(ui.getLastBody().upload_operation_id, op);
        assert.equal(ui.getLastBody().record_id, '9');
    });

    it('fallo no recuperable mantiene pending para mensaje; retry oculto', async () => {
        const ui = boot(async () => ({
            status: 409,
            text: async () => JSON.stringify({
                success: false,
                data: { code: 'capability_inactive', message: 'Inactiva' }
            })
        }));
        ui.mod.apply({ status: 'known_absent' });
        ui.mod._test.setPendingForTests({
            blob: { size: 50 },
            previewUrl: 'blob:p',
            operationId: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'
        });
        const result = await ui.mod.afterRecordSaved({
            recordId: 9,
            ajaxUrl: 'https://example.test/ajax',
            attachAction: 'aa_attach_canonical_record_image',
            attachNonce: 'nonce',
            familyKey: 'archive',
            containerId: 3
        });
        assert.equal(result.ok, false);
        assert.equal(result.recoverable, false);
        assert.equal(ui.retryBtn.classList.contains('hidden'), true);
        assert.equal(ui.mod.hasPending(), true);
    });

    it('re-apply no duplica listeners (un solo change handler)', () => {
        const ui = boot(async () => ({ status: 200, text: async () => '{}' }));
        ui.mod.apply({ status: 'known_absent' });
        ui.mod.clear();
        ui.mod.apply({ status: 'known_absent' });
        assert.equal(ui.fileInput._listeners.change.length, 1);
    });
});
