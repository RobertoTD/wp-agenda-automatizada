'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-amount-field.js'
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
        focus() {
            this.focusCalls += 1;
        }
    };
}

function boot() {
    const wrap = createEl('aa-shell-record-amount-field');
    const input = createEl('aa-shell-record-amount');
    const errorEl = createEl('aa-shell-record-amount-error');
    errorEl.classList.add('hidden');
    const unavailableEl = createEl('aa-shell-record-amount-unavailable');
    unavailableEl.classList.add('hidden');

    const byId = {
        'aa-shell-record-amount-field': wrap,
        'aa-shell-record-amount': input,
        'aa-shell-record-amount-error': errorEl,
        'aa-shell-record-amount-unavailable': unavailableEl
    };

    const documentRef = {
        getElementById(id) {
            return byId[id] || null;
        }
    };

    const env = {
        window: { document: documentRef },
        document: documentRef
    };

    vm.runInNewContext(fs.readFileSync(jsPath, 'utf8'), env, {
        filename: 'canonical-shell-amount-field.js'
    });

    return {
        module: env.window.AA_CANONICAL_SHELL_CAPABILITY_MODULES.amount,
        wrap,
        input,
        errorEl,
        unavailableEl
    };
}

describe('canonical-shell-amount-field', () => {
    it('clear deja el campo oculto y omit', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_value', value: '1.00' });
        ui.module.clear();
        assert.equal(ui.wrap.classList.contains('hidden'), true);
        assert.equal(ui.input.disabled, true);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(Object.prototype.hasOwnProperty.call(body._data, 'amount'), false);
    });

    it('known_value precarga y envía valor', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_value', value: '12.50' });
        assert.equal(ui.input.value, '12.50');
        assert.equal(ui.input.disabled, false);
        assert.equal(ui.wrap.classList.contains('hidden'), false);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(body._data.amount, '12.50');
    });

    it('known_absent vacío envía clear', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_absent' });
        assert.equal(ui.input.value, '');
        assert.equal(ui.input.disabled, false);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(body._data.amount, '');
    });

    it('read_failed no envía vacío como borrado', () => {
        const ui = boot();
        ui.module.apply({ status: 'read_failed' });
        assert.equal(ui.input.disabled, true);
        assert.equal(ui.unavailableEl.classList.contains('hidden'), false);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(Object.prototype.hasOwnProperty.call(body._data, 'amount'), false);
    });

    it('cero es válido al enviar', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_value', value: '0.00' });
        ui.input.value = '0';
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(body._data.amount, '0');
    });

    it('handleError de invalid_amount', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_absent' });
        const handled = ui.module.handleError('invalid_amount', 'Importe inválido');
        assert.equal(handled, true);
        assert.equal(ui.errorEl.textContent, 'Importe inválido');
        assert.equal(ui.errorEl.classList.contains('hidden'), false);
    });

    it('cambio de registro limpia estado previo', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_value', value: '5.00' });
        ui.module.handleError('invalid_amount', 'previo');
        ui.module.apply({ status: 'known_absent' });
        assert.equal(ui.input.value, '');
        assert.equal(ui.errorEl.classList.contains('hidden'), true);
        assert.equal(ui.errorEl.textContent, '');
    });
});
