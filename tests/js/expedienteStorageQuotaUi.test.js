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
    it('source declares commercial storage messages and suppresses retry', () => {
        assert.match(moduleSrc, /storage_not_included/);
        assert.match(moduleSrc, /storage_quota_exceeded/);
        assert.match(
            moduleSrc,
            /Tu plan actual no incluye almacenamiento de imágenes en el servidor\./
        );
        assert.match(
            moduleSrc,
            /No queda espacio de almacenamiento\. Elimina alguna imagen para liberar espacio\./
        );
        assert.match(moduleSrc, /function messageForAttachFailure/);
        assert.match(moduleSrc, /hideRetry\(\)/);
    });

    it('messageForAttachFailure maps commercial codes; others stay generic', () => {
        const mod = loadModule();
        assert.equal(
            mod.messageForAttachFailure('storage_not_included'),
            mod.STORAGE_NOT_INCLUDED_MESSAGE
        );
        assert.equal(
            mod.messageForAttachFailure('storage_quota_exceeded'),
            mod.STORAGE_QUOTA_EXCEEDED_MESSAGE
        );
        assert.equal(
            mod.messageForAttachFailure('authorize_failed'),
            mod.PARTIAL_ATTACH_MESSAGE
        );
        assert.equal(mod.messageForAttachFailure(''), mod.PARTIAL_ATTACH_MESSAGE);
    });
});
