'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/settings/module.js');

function createClassList(initialClassName) {
    const classes = new Set((initialClassName || '').split(/\s+/).filter(Boolean));

    return {
        add(cls) {
            classes.add(cls);
        },
        remove(cls) {
            classes.delete(cls);
        },
        contains(cls) {
            return classes.has(cls);
        }
    };
}

function createInteractiveElement(options) {
    const handlers = {};
    let defaultPrevented = false;

    const element = {
        id: options.id || '',
        href: options.href || '',
        className: options.className || '',
        classList: createClassList(options.className),
        style: {},
        open: options.open ?? false,
        parentElement: options.parentElement || null,
        scrollIntoView() {},
        focus() {},
        getAttribute(name) {
            if (name === 'href') {
                return element.href;
            }
            return null;
        },
        addEventListener(type, handler) {
            if (!handlers[type]) {
                handlers[type] = [];
            }
            handlers[type].push(handler);
        },
        dispatchClick() {
            defaultPrevented = false;
            const event = {
                preventDefault() {
                    defaultPrevented = true;
                }
            };
            (handlers.click || []).forEach(function(handler) {
                handler(event);
            });
            return defaultPrevented;
        },
        dispatchButtonClick() {
            (handlers.click || []).forEach(function(handler) {
                handler();
            });
        },
        wasDefaultPrevented() {
            return defaultPrevented;
        },
        closest(selector) {
            if (selector === '.flex.items-center'
                && this.classList.contains('flex')
                && this.classList.contains('items-center')) {
                return this;
            }
            return this.parentElement;
        }
    };

    return element;
}

/**
 * @param {{ locationSearch?: string, settingsData?: object }} options
 */
function loadSettingsModule(options) {
    const locationSearch = options.locationSearch || '';
    const settingsData = options.settingsData || {};

    const businessRoot = createInteractiveElement({ id: 'aa-business-data-root', open: false });
    const nameInput = createInteractiveElement({ id: 'aa_business_name' });
    const addressField = createInteractiveElement({ id: 'aa-business-address' });
    const virtualRow = createInteractiveElement({ className: 'flex items-center gap-3' });
    const virtualCheckbox = createInteractiveElement({
        id: 'aa-is-virtual-checkbox',
        parentElement: virtualRow
    });
    const calendarRoot = createInteractiveElement({ id: 'aa-google-calendar-root', open: false });
    const calendarConnect = createInteractiveElement({
        id: 'aa-google-calendar-connect',
        href: 'http://localhost:3000/oauth/authorize?state=test'
    });
    const notConnectedView = createInteractiveElement({
        id: 'aa-google-calendar-not-connected',
        className: 'text-center py-8'
    });
    const freemiumView = createInteractiveElement({
        id: 'aa-google-calendar-freemium-consent',
        className: 'hidden max-w-md mx-auto py-4'
    });
    const freemiumCta = createInteractiveElement({ id: 'aa-google-calendar-freemium-cta' });

    const elementsById = {
        'aa-business-data-root': businessRoot,
        'aa_business_name': nameInput,
        'aa-business-address': addressField,
        'aa-is-virtual-checkbox': virtualCheckbox,
        'aa-google-calendar-root': calendarRoot,
        'aa-google-calendar-connect': calendarConnect,
        'aa-google-calendar-not-connected': notConnectedView,
        'aa-google-calendar-freemium-consent': freemiumView,
        'aa-google-calendar-freemium-cta': freemiumCta
    };

    const scheduledTimeouts = [];
    let domReadyHandler = null;
    const openedUrls = [];

    const document = {
        getElementById(id) {
            return elementsById[id] || null;
        },
        querySelector() {
            return null;
        },
        createElement() {
            return createInteractiveElement({});
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
        AA_SETTINGS_DATA: Object.assign({}, settingsData),
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
        addEventListener() {},
        open(url, target, features) {
            openedUrls.push({ url, target, features });
            return null;
        }
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
        calendarConnect,
        notConnectedView,
        freemiumView,
        freemiumCta,
        openedUrls
    };
}

describe('settings module google calendar freemium gate', () => {
    it('flag true: connect click shows Freemium card and does not open OAuth immediately', () => {
        const result = loadSettingsModule({
            settingsData: { requiresFreemiumConsentBeforeGoogle: true }
        });

        const prevented = result.calendarConnect.dispatchClick();

        assert.equal(prevented, true);
        assert.equal(result.notConnectedView.classList.contains('hidden'), true);
        assert.equal(result.freemiumView.classList.contains('hidden'), false);
        assert.equal(result.openedUrls.length, 0);
    });

    it('flag true: Freemium CTA opens original OAuth href in a new tab', () => {
        const result = loadSettingsModule({
            settingsData: { requiresFreemiumConsentBeforeGoogle: true }
        });

        result.calendarConnect.dispatchClick();
        result.freemiumCta.dispatchButtonClick();

        assert.equal(result.openedUrls.length, 1);
        assert.equal(result.openedUrls[0].url, 'http://localhost:3000/oauth/authorize?state=test');
        assert.equal(result.openedUrls[0].target, '_blank');
        assert.equal(result.openedUrls[0].features, 'noopener,noreferrer');
    });

    it('flag false: connect click is not intercepted', () => {
        const result = loadSettingsModule({
            settingsData: { requiresFreemiumConsentBeforeGoogle: false }
        });

        const prevented = result.calendarConnect.dispatchClick();

        assert.equal(prevented, false);
        assert.equal(result.notConnectedView.classList.contains('hidden'), false);
        assert.equal(result.freemiumView.classList.contains('hidden'), true);
        assert.equal(result.openedUrls.length, 0);
    });
});
