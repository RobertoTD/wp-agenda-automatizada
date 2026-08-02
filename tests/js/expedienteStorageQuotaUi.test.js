'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

function loadModule() {
    const sandbox = {
        window: { AAAdmin: {} },
        document: {
            createElement: () => ({}),
            getElementById: () => null,
            querySelector: () => null,
            contains: () => true
        },
        console,
        fetch: () => Promise.resolve({ status: 200, json: async () => ({}) }),
        URL: {
            createObjectURL: () => 'blob:test',
            revokeObjectURL: () => {}
        },
        Image: function () {},
        setTimeout: (fn) => fn(),
        clearTimeout: () => {},
        FormData: function () {
            this.append = () => {};
        },
        Blob: function () {},
        File: function () {},
        Promise,
        Math,
        JSON,
        String,
        Number,
        Array,
        Object,
        parseInt,
        isNaN,
        Error
    };
    vm.runInNewContext(moduleSrc, sandbox, { filename: 'expediente-registros.js' });
    return sandbox.window.AAAdmin.ExpedienteRegistros.__test__;
}

describe('expediente storage quota UI', () => {
    it('source handles commercial storage codes via toast close path', () => {
        assert.match(moduleSrc, /storage_not_included/);
        assert.match(moduleSrc, /storage_quota_exceeded/);
        assert.match(moduleSrc, /function buildSaveNotification/);
        assert.match(moduleSrc, /finishWithToast\(savedRecordOutcome/);
        assert.match(
            moduleSrc,
            /La imagen no se guardó: tu plan no incluye almacenamiento de imágenes\./
        );
        assert.match(
            moduleSrc,
            /La imagen no se guardó porque agotaste el almacenamiento de tu plan Freemium\./
        );
    });

    it('messageForAttachFailure stays generic for technical retry path', () => {
        const mod = loadModule();
        assert.equal(
            mod.messageForAttachFailure('authorize_failed'),
            mod.PARTIAL_ATTACH_MESSAGE
        );
        assert.equal(mod.messageForAttachFailure(''), mod.PARTIAL_ATTACH_MESSAGE);
    });

    it('buildSaveNotification maps commercial denials without retry CTA inventado', () => {
        const mod = loadModule();
        const free = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_not_included',
            account: { commercialState: 'free', upgradeAvailable: false }
        });
        assert.equal(free.severity, 'warning');
        assert.equal(free.actions[0].target, 'settings_freemium');

        const quotaUnknown = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_quota_exceeded',
            account: null
        });
        assert.equal(quotaUnknown.actions.length, 0);
    });
});
