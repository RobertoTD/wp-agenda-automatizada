'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

const modalPath = path.join(__dirname, '../../includes/admin/ui/modals/crearcliente/crearcliente.js');
const modalSrc = fs.readFileSync(modalPath, 'utf8');

describe('ClientCreateModal optional email collapse', () => {
    it('envuelve el email en details/summary nativo con copy Opcionales', () => {
        assert.match(modalSrc, /function wrapInOptionalDetails/);
        assert.match(modalSrc, /createElement\('details'\)/);
        assert.match(modalSrc, /createElement\('summary'\)/);
        assert.match(modalSrc, /textContent = 'Opcionales'/);
        assert.match(modalSrc, /id = 'aa-cliente-form-opcionales'/);
        assert.match(modalSrc, /wrapInOptionalDetails\(correoGroup, false\)/);
    });

    it('en edición abre el toggle si el cliente ya tiene correo', () => {
        assert.match(
            modalSrc,
            /wrapInOptionalDetails\(correoGroup, !!\(cliente\.correo && String\(cliente\.correo\)\.trim\(\)\)\)/
        );
    });
});
