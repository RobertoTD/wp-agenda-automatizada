'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

describe('expediente registros MC4b attach', () => {
    it('source: contracts and bounds', () => {
        assert.match(moduleSrc, /aa_attach_expediente_registro/);
        assert.match(moduleSrc, /upload_operation_id/);
        assert.match(moduleSrc, /1048576/);
        assert.match(moduleSrc, /2048/);
        assert.match(moduleSrc, /Registro guardado\. No se pudo subir la imagen\./);
        assert.match(moduleSrc, /Reintentar imagen/);
        assert.match(
            moduleSrc,
            /Este formato no se puede procesar aquí\. Guarda o exporta la foto como JPG e inténtalo de nuevo\./
        );
        assert.doesNotMatch(moduleSrc, /capture\s*=/);
        assert.match(moduleSrc, /partial_attachment_failed/);
        assert.match(moduleSrc, /promoteCreateToEdit/);
        assert.match(moduleSrc, /runAttachRetry/);
        assert.match(moduleSrc, /prepareExpedienteImage/);
        assert.match(moduleSrc, /generateUploadOperationId/);
        assert.match(moduleSrc, /revokeObjectURL/);
        // No enviar secretos/metadatos autoritativos desde el browser.
        assert.doesNotMatch(moduleSrc, /formData\.append\(['"]signed_url['"]/);
        assert.doesNotMatch(moduleSrc, /formData\.append\(['"]upload_intent['"]/);
        assert.doesNotMatch(moduleSrc, /formData\.append\(['"]storage_path['"]/);
        assert.doesNotMatch(moduleSrc, /formData\.append\(['"]token['"]/);
    });

    it('source: postAttach only minimal fields', () => {
        const start = moduleSrc.indexOf('function postAttach');
        assert.ok(start > 0);
        const chunk = moduleSrc.slice(start, start + 900);
        assert.match(chunk, /formData\.append\('client_id'/);
        assert.match(chunk, /formData\.append\('record_id'/);
        assert.match(chunk, /formData\.append\('upload_operation_id'/);
        assert.match(chunk, /formData\.append\('file'/);
        assert.doesNotMatch(chunk, /width/);
        assert.doesNotMatch(chunk, /height/);
        assert.doesNotMatch(chunk, /mime_type/);
    });

    it('source: retry path never calls create', () => {
        const retryStart = moduleSrc.indexOf('function runAttachRetry');
        assert.ok(retryStart > 0);
        const retryChunk = moduleSrc.slice(retryStart, retryStart + 800);
        assert.match(retryChunk, /attachPendingImage/);
        assert.doesNotMatch(retryChunk, /createRegistro/);
        assert.doesNotMatch(retryChunk, /updateRegistro/);
        assert.doesNotMatch(retryChunk, /postForm\(/);
    });

    it('UUID v4 helper shape', () => {
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
            crypto: {
                randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'
            }
        };
        vm.runInNewContext(moduleSrc, sandbox);
        const api = sandbox.window.AAAdmin.ExpedienteRegistros.__test__;
        const id = api.generateUploadOperationId();
        assert.match(id, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
        assert.equal(api.MAX_IMAGE_BYTES, 1048576);
        assert.equal(api.MAX_IMAGE_EDGE, 2048);
    });

    it('clearPendingImage revokes preview URL', () => {
        const revoked = [];
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
                revokeObjectURL: (u) => { revoked.push(u); }
            },
            Image: function () {},
            setTimeout: (fn) => fn(),
            crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
        };
        vm.runInNewContext(moduleSrc, sandbox);
        const api = sandbox.window.AAAdmin.ExpedienteRegistros.__test__;
        const out = api.clearPendingImage({
            operationId: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            blob: { size: 10 },
            previewUrl: 'blob:pending-1',
            width: 10,
            height: 10,
            byteSize: 10
        });
        assert.equal(out, null);
        assert.deepEqual(revoked, ['blob:pending-1']);
    });

    it('HEIC message constant is Mexican Spanish', () => {
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
            URL: { createObjectURL: () => 'blob:x', revokeObjectURL: () => {} },
            Image: function () {},
            setTimeout: (fn) => fn(),
            crypto: { randomUUID: () => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' }
        };
        vm.runInNewContext(moduleSrc, sandbox);
        const api = sandbox.window.AAAdmin.ExpedienteRegistros.__test__;
        assert.equal(
            api.HEIC_UNSUPPORTED_MESSAGE,
            'Este formato no se puede procesar aquí. Guarda o exporta la foto como JPG e inténtalo de nuevo.'
        );
        assert.equal(api.PARTIAL_ATTACH_MESSAGE, 'Registro guardado. No se pudo subir la imagen.');
    });
});
