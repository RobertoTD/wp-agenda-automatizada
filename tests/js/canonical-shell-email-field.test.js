'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const jsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-email-field.js'
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
    const wrap = createEl('aa-shell-record-email-field');
    const input = createEl('aa-shell-record-email');
    const errorEl = createEl('aa-shell-record-email-error');
    errorEl.classList.add('hidden');
    const unavailableEl = createEl('aa-shell-record-email-unavailable');
    unavailableEl.classList.add('hidden');

    const byId = {
        'aa-shell-record-email-field': wrap,
        'aa-shell-record-email': input,
        'aa-shell-record-email-error': errorEl,
        'aa-shell-record-email-unavailable': unavailableEl
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
        filename: 'canonical-shell-email-field.js'
    });

    return {
        module: env.window.AA_CANONICAL_SHELL_CAPABILITY_MODULES.email,
        wrap,
        input,
        unavailableEl
    };
}

describe('canonical-shell-email-field', () => {
    it('read_failed → omit y no envía email', () => {
        const ui = boot();
        ui.module.apply({ status: 'read_failed' });
        assert.equal(ui.unavailableEl.classList.contains('hidden'), false);
        assert.equal(ui.input.disabled, true);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(Object.prototype.hasOwnProperty.call(body._data, 'email'), false);
    });

    it('known_value precarga y envía email', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_value', value: 'User+Tag@example.com' });
        assert.equal(ui.input.value, 'User+Tag@example.com');
        assert.equal(ui.input.disabled, false);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(body._data.email, 'User+Tag@example.com');
    });

    it('conocido ausente con vacío envía clear', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_absent' });
        ui.input.value = '';
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(body._data.email, '');
    });

    it('valor no interpretable → unavailable + omit', () => {
        const ui = boot();
        ui.module.apply({ status: 'known_value', value: 'not-an-email' });
        assert.equal(ui.unavailableEl.classList.contains('hidden'), false);
        const body = { _data: {}, append(k, v) { this._data[k] = v; } };
        ui.module.collect(body);
        assert.equal(Object.prototype.hasOwnProperty.call(body._data, 'email'), false);
    });
});
