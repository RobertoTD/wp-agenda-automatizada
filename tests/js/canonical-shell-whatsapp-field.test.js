'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-whatsapp-field.js'
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
        listeners: {},
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
        },
        addEventListener(type, fn) {
            this.listeners[type] = fn;
        }
    };
}

function boot() {
    const wrap = createEl('aa-shell-record-whatsapp-field');
    const countrySelect = createEl('aa-shell-record-whatsapp-country');
    countrySelect.value = '52';
    const input = createEl('aa-shell-record-whatsapp');
    const helpEl = createEl('aa-shell-record-whatsapp-help');
    helpEl.classList.add('hidden');
    const errorEl = createEl('aa-shell-record-whatsapp-error');
    errorEl.classList.add('hidden');
    const unavailableEl = createEl('aa-shell-record-whatsapp-unavailable');
    unavailableEl.classList.add('hidden');

    const byId = {
        'aa-shell-record-whatsapp-field': wrap,
        'aa-shell-record-whatsapp-country': countrySelect,
        'aa-shell-record-whatsapp': input,
        'aa-shell-record-whatsapp-help': helpEl,
        'aa-shell-record-whatsapp-error': errorEl,
        'aa-shell-record-whatsapp-unavailable': unavailableEl
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
        filename: 'canonical-shell-whatsapp-field.js'
    });

    return {
        module: env.window.AA_CANONICAL_SHELL_CAPABILITY_MODULES.whatsapp,
        wrap,
        countrySelect,
        input,
        unavailableEl
    };
}

describe('canonical-shell-whatsapp-field', () => {
    it('read_failed → omit y no envía whatsapp', () => {
        const ui = boot();
        ui.module.apply({ status: 'read_failed' });
        assert.equal(ui.unavailableEl.classList.contains('hidden'), false);
        assert.equal(ui.input.disabled, true);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(Object.prototype.hasOwnProperty.call(body._data, 'whatsapp'), false);
    });

    it('known_value precarga y envía E.164', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_value', value: '+5491112345678' });
        assert.equal(ui.countrySelect.value, '54');
        assert.equal(ui.input.value, '91112345678');
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(body._data.whatsapp, '+5491112345678');
    });

    it('nacional vacío envía clear vacío, no solo código', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_absent' });
        ui.countrySelect.value = '54';
        ui.input.value = '';
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(body._data.whatsapp, '');
    });

    it('valor no interpretable → unavailable + omit', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_value', value: '+999111111111' });
        assert.equal(ui.unavailableEl.classList.contains('hidden'), false);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(Object.prototype.hasOwnProperty.call(body._data, 'whatsapp'), false);
    });
});
