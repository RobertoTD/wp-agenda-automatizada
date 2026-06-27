'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/settings/module.js');

function createHighlightableElement(options) {
    const element = {
        id: options.id || '',
        className: options.className || '',
        open: options.open ?? false,
        style: {},
        parentElement: options.parentElement || null,
        classList: {
            classes: (options.className || '').split(/\s+/).filter(Boolean),
            contains(cls) {
                return this.classes.indexOf(cls) !== -1;
            }
        },
        scrollIntoView() {},
        focus() {},
        closest(selector) {
            if (selector === '.flex.items-center'
                && this.classList.contains('flex')
                && this.classList.contains('items-center')) {
                return this;
            }

            return this.parentElement;
        },
        addEventListener() {}
    };

    return element;
}

function loadSettingsModule(locationSearch) {
    const businessRoot = createHighlightableElement({ id: 'aa-business-data-root', open: false });
    const nameInput = createHighlightableElement({ id: 'aa_business_name' });
    const addressField = createHighlightableElement({ id: 'aa-business-address' });
    const virtualRow = createHighlightableElement({ className: 'flex items-center gap-3' });
    const virtualCheckbox = createHighlightableElement({
        id: 'aa-is-virtual-checkbox',
        parentElement: virtualRow
    });
    const calendarRoot = createHighlightableElement({ id: 'aa-google-calendar-root', open: false });
    const calendarConnect = createHighlightableElement({ id: 'aa-google-calendar-connect' });

    const elementsById = {
        'aa-business-data-root': businessRoot,
        'aa_business_name': nameInput,
        'aa-business-address': addressField,
        'aa-is-virtual-checkbox': virtualCheckbox,
        'aa-google-calendar-root': calendarRoot,
        'aa-google-calendar-connect': calendarConnect
    };

    const scheduledTimeouts = [];
    let domReadyHandler = null;

    const document = {
        getElementById(id) {
            return elementsById[id] || null;
        },
        querySelector() {
            return null;
        },
        createElement() {
            return createHighlightableElement({});
        },
        addEventListener(type, handler) {
            if (type === 'DOMContentLoaded') {
                domReadyHandler = handler;
            }
        }
    };

    const window = {
        location: { search: locationSearch },
        innerWidth: 1024,
        requestAnimationFrame(callback) {
            callback();
        },
        setTimeout(callback, delay) {
            scheduledTimeouts.push({ callback, delay });
            return scheduledTimeouts.length;
        },
        clearTimeout() {},
        matchMedia() {
            return { matches: false };
        },
        addEventListener() {}
    };

    const context = {
        document,
        window,
        console,
        URLSearchParams,
        setTimeout: window.setTimeout.bind(window),
        clearTimeout: window.clearTimeout.bind(window)
    };

    vm.runInNewContext(fs.readFileSync(modulePath, 'utf8'), context, { filename: modulePath });

    if (typeof domReadyHandler === 'function') {
        domReadyHandler();
    }

    scheduledTimeouts
        .filter(function(entry) {
            return entry.delay === 350;
        })
        .forEach(function(entry) {
            entry.callback();
        });

    return {
        businessRoot,
        nameInput,
        addressField,
        virtualRow,
        calendarRoot,
        calendarConnect
    };
}

function hasTemporaryHighlight(element) {
    return element.style.boxShadow.indexOf('rgba(79, 70, 229, 0.22)') !== -1;
}

describe('settings module setup_focus', () => {
    it('business_data opens business details and highlights name, address and virtual row', () => {
        const result = loadSettingsModule('?setup_focus=business_data');

        assert.equal(result.businessRoot.open, true);
        assert.equal(hasTemporaryHighlight(result.businessRoot), true);
        assert.equal(hasTemporaryHighlight(result.nameInput), true);
        assert.equal(hasTemporaryHighlight(result.addressField), true);
        assert.equal(hasTemporaryHighlight(result.virtualRow), true);
    });

    it('google_calendar still opens calendar section', () => {
        const result = loadSettingsModule('?setup_focus=google_calendar');

        assert.equal(result.calendarRoot.open, true);
        assert.equal(hasTemporaryHighlight(result.calendarRoot), true);
        assert.equal(result.businessRoot.open, false);
    });
});
