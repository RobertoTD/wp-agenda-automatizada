'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const firstOpportunityPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/dashboard/pwa-install-first-opportunity.js'
);
const handlersPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/learning/learning-action-handlers.js'
);

function createStorage() {
    var store = {};

    return {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null;
        },
        setItem: function (key, value) {
            store[key] = String(value);
        },
        removeItem: function (key) {
            delete store[key];
        },
        clear: function () {
            store = {};
        }
    };
}

function createBeforeInstallPromptEvent(outcome) {
    var evt = new Event('beforeinstallprompt');

    evt.preventDefault = function () {};
    evt.prompt = function () {
        return Promise.resolve();
    };
    evt.userChoice = Promise.resolve({ outcome: outcome });

    return evt;
}

function loadFirstOpportunity() {
    delete require.cache[firstOpportunityPath];
    return require(firstOpportunityPath);
}

function loadHandlers() {
    delete require.cache[handlersPath];
    require(handlersPath);
}

function defineNavigator(overrides) {
    var base = {
        userAgent: 'Mozilla/5.0',
        platform: 'Linux',
        maxTouchPoints: 0,
        standalone: false
    };
    var value = Object.assign({}, base, overrides || {});

    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        writable: true,
        value: value
    });
}

function createTestDocument() {
    var nodesById = {};
    var bodyChildren = [];

    function TestNode(tagName) {
        this.tagName = String(tagName || '').toUpperCase();
        this.id = '';
        this.className = '';
        this.textContent = '';
        this.style = {};
        this.children = [];
        this.parentNode = null;
        this._listeners = {};
        Object.defineProperty(this, 'id', {
            configurable: true,
            enumerable: true,
            get: function () {
                return this._id || '';
            },
            set: function (value) {
                this._id = value;

                if (value) {
                    nodesById[value] = this;
                }
            }
        });
        this.setAttribute = function (name, value) {
            if (name === 'id') {
                this.id = value;
            }
        };
        this.getAttribute = function (name) {
            if (name === 'id') {
                return this.id;
            }

            return null;
        };
        this.appendChild = function (child) {
            child.parentNode = this;
            this.children.push(child);
            return child;
        };
        this.addEventListener = function (type, listener) {
            if (!this._listeners[type]) {
                this._listeners[type] = [];
            }

            this._listeners[type].push(listener);
        };
        this.removeEventListener = function (type, listener) {
            if (!this._listeners[type]) {
                return;
            }

            this._listeners[type] = this._listeners[type].filter(function (registered) {
                return registered !== listener;
            });
        };
        this.click = function () {
            var listeners = (this._listeners.click || []).slice();

            listeners.forEach(function (listener) {
                listener({
                    preventDefault: function () {},
                    stopPropagation: function () {}
                });
            });
        };
        this.querySelector = function (selector) {
            if (selector === '.aa-pwa-install-first-opportunity-dismiss') {
                return findByClass(this, 'aa-pwa-install-first-opportunity-dismiss');
            }

            if (selector === '.aa-pwa-install-first-opportunity-install') {
                return findByClass(this, 'aa-pwa-install-first-opportunity-install');
            }

            return null;
        };
    }

    function findByClass(node, className) {
        if (node.className === className) {
            return node;
        }

        var i;
        var found = null;

        for (i = 0; i < node.children.length; i += 1) {
            found = findByClass(node.children[i], className);

            if (found) {
                return found;
            }
        }

        return null;
    }

    var body = new TestNode('body');
    body.removeChild = function (child) {
        var index = this.children.indexOf(child);

        if (index !== -1) {
            this.children.splice(index, 1);
        }

        child.parentNode = null;
    };
    bodyChildren = body.children;

    return {
        readyState: 'complete',
        body: body,
        nodesById: nodesById,
        createElement: function (tagName) {
            return new TestNode(tagName);
        },
        getElementById: function (id) {
            return nodesById[id] || null;
        },
        addEventListener: function () {}
    };
}

function getInstallButton(doc) {
    var root = doc.getElementById('aa-pwa-install-first-opportunity-root');

    return root ? root.querySelector('.aa-pwa-install-first-opportunity-install') : null;
}

function getDismissButton(doc) {
    var root = doc.getElementById('aa-pwa-install-first-opportunity-root');

    return root ? root.querySelector('.aa-pwa-install-first-opportunity-dismiss') : null;
}

function getCloseButton(doc) {
    var root = doc.getElementById('aa-pwa-install-first-opportunity-root');

    if (!root) {
        return null;
    }

    var i;

    for (i = 0; i < root.children.length; i += 1) {
        if (root.children[i].className === 'aa-pwa-install-first-opportunity-close') {
            return root.children[i];
        }
    }

    return null;
}

describe('PwaInstallFirstOpportunity', () => {
    var originalMatchMedia;
    var originalNavigator;
    var originalAlert;
    var originalAdminContext;
    var originalDocument;
    var alertCalls;
    var runCalls;
    var isAvailableState;
    var availabilityCallbacks;
    var testDocument;

    beforeEach(() => {
        originalMatchMedia = globalThis.matchMedia;
        originalNavigator = globalThis.navigator;
        originalAlert = globalThis.alert;
        originalAdminContext = globalThis.AA_ADMIN_CONTEXT;
        originalDocument = globalThis.document;

        alertCalls = 0;
        runCalls = [];
        isAvailableState = false;
        availabilityCallbacks = [];
        testDocument = createTestDocument();

        globalThis.localStorage = createStorage();
        globalThis.AA_ADMIN_CONTEXT = { blogId: 42 };
        globalThis.document = testDocument;

        globalThis.matchMedia = function () {
            return { matches: false };
        };

        defineNavigator();

        globalThis.alert = function () {
            alertCalls += 1;
        };

        globalThis.LearningActionHandlers = {
            isAvailable: function () {
                return isAvailableState;
            },
            run: function (action, item, ctx) {
                runCalls.push({ action: action, item: item, ctx: ctx });
                return Promise.resolve({ completed: false, outcome: 'accepted' });
            },
            onAvailabilityChange: function (callback) {
                availabilityCallbacks.push(callback);
            }
        };

        loadFirstOpportunity();
    });

    afterEach(() => {
        globalThis.matchMedia = originalMatchMedia;

        if (originalNavigator === undefined) {
            delete globalThis.navigator;
        } else {
            Object.defineProperty(globalThis, 'navigator', {
                configurable: true,
                writable: true,
                value: originalNavigator
            });
        }

        globalThis.alert = originalAlert;

        if (originalAdminContext === undefined) {
            delete globalThis.AA_ADMIN_CONTEXT;
        } else {
            globalThis.AA_ADMIN_CONTEXT = originalAdminContext;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }

        delete globalThis.localStorage;
        delete globalThis.LearningActionHandlers;
        delete globalThis.PwaInstallFirstOpportunity;
        delete require.cache[firstOpportunityPath];
        delete require.cache[handlersPath];
    });

    it('buildStorageKey incluye blogId', () => {
        assert.equal(
            globalThis.PwaInstallFirstOpportunity.buildStorageKey('7'),
            'aa_pwa_install_first_opportunity_v1:7'
        );
    });

    it('renderizar superficie no llama run', async () => {
        isAvailableState = true;

        await globalThis.PwaInstallFirstOpportunity.tryOfferAutomaticInstall();

        assert.equal(runCalls.length, 0);
        assert.equal(globalThis.PwaInstallFirstOpportunity.isAutomaticSurfaceVisible(), true);
        assert.ok(testDocument.getElementById('aa-pwa-install-first-opportunity-root'));
    });

    it('Ahora no consume oportunidad sin llamar run', () => {
        isAvailableState = true;
        globalThis.PwaInstallFirstOpportunity.renderAutomaticInstallSurface();

        var dismissButton = getDismissButton(testDocument);
        assert.ok(dismissButton);

        dismissButton.click();

        assert.equal(runCalls.length, 0);
        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
        assert.equal(globalThis.PwaInstallFirstOpportunity.isAutomaticSurfaceVisible(), false);
    });

    it('× global produce el mismo efecto que Ahora no', () => {
        isAvailableState = true;
        globalThis.PwaInstallFirstOpportunity.renderAutomaticInstallSurface();

        var closeButton = getCloseButton(testDocument);
        assert.ok(closeButton);

        closeButton.click();

        assert.equal(runCalls.length, 0);
        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
        assert.equal(globalThis.PwaInstallFirstOpportunity.isAutomaticSurfaceVisible(), false);
    });

    it('Instalar ahora llama run directamente sin item', async () => {
        isAvailableState = true;
        globalThis.PwaInstallFirstOpportunity.renderAutomaticInstallSurface();

        var installButton = getInstallButton(testDocument);
        assert.ok(installButton);

        installButton.click();

        assert.equal(runCalls.length, 1);
        assert.equal(runCalls[0].item, null);
        assert.equal(runCalls[0].action.handler, 'pwa.install');
        assert.equal(globalThis.PwaInstallFirstOpportunity.isAutomaticSurfaceVisible(), false);

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
    });

    it('dismissed nativo consume primera oportunidad', async () => {
        isAvailableState = true;
        globalThis.LearningActionHandlers.run = function () {
            runCalls.push(1);
            return Promise.resolve({ completed: false, outcome: 'dismissed' });
        };

        globalThis.PwaInstallFirstOpportunity.renderAutomaticInstallSurface();
        getInstallButton(testDocument).click();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(runCalls.length, 1);
        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
    });

    it('unavailable no consume primera oportunidad', async () => {
        isAvailableState = true;
        globalThis.LearningActionHandlers.run = function () {
            runCalls.push(1);
            return Promise.resolve({ completed: false, outcome: 'unavailable' });
        };

        globalThis.PwaInstallFirstOpportunity.renderAutomaticInstallSurface();
        getInstallButton(testDocument).click();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(runCalls.length, 1);
        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('42'), false);
    });

    it('error tecnico no consume primera oportunidad', async () => {
        isAvailableState = true;
        globalThis.LearningActionHandlers.run = function () {
            runCalls.push(1);
            return Promise.reject(new Error('prompt failed'));
        };

        globalThis.PwaInstallFirstOpportunity.renderAutomaticInstallSurface();
        getInstallButton(testDocument).click();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(runCalls.length, 1);
        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('42'), false);
    });

    it('onAvailabilityChange renderiza superficie cuando pasa a disponible', async () => {
        isAvailableState = true;
        assert.equal(availabilityCallbacks.length, 1);

        await availabilityCallbacks[0]();

        assert.equal(runCalls.length, 0);
        assert.equal(globalThis.PwaInstallFirstOpportunity.isAutomaticSurfaceVisible(), true);
    });

    it('standalone no ofrece rama automática ni manual', async () => {
        globalThis.matchMedia = function (query) {
            return { matches: query === '(display-mode: standalone)' };
        };

        isAvailableState = true;
        globalThis.navigator.userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)';

        await globalThis.PwaInstallFirstOpportunity.tryOfferAutomaticInstall();
        globalThis.PwaInstallFirstOpportunity.tryOfferManualInstall();

        assert.equal(alertCalls, 0);
        assert.equal(globalThis.PwaInstallFirstOpportunity.isAutomaticSurfaceVisible(), false);
    });

    it('rama manual iOS muestra alert una sola vez y consume', () => {
        globalThis.navigator.userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)';
        isAvailableState = false;

        globalThis.PwaInstallFirstOpportunity.tryOfferManualInstall();
        globalThis.PwaInstallFirstOpportunity.tryOfferManualInstall();

        assert.equal(alertCalls, 1);
        assert.equal(runCalls.length, 0);
        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
    });

    it('rama manual no corre en Android sin prompt', () => {
        globalThis.navigator.userAgent = 'Mozilla/5.0 (Linux; Android 14)';
        isAvailableState = false;

        globalThis.PwaInstallFirstOpportunity.tryOfferManualInstall();

        assert.equal(alertCalls, 0);
        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('42'), false);
    });

    it('oportunidad consumida bloquea ramas posteriores', async () => {
        globalThis.PwaInstallFirstOpportunity.markConsumedFirstOpportunity('42');
        isAvailableState = true;
        globalThis.navigator.userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)';

        await globalThis.PwaInstallFirstOpportunity.tryOfferAutomaticInstall();
        globalThis.PwaInstallFirstOpportunity.tryOfferManualInstall();

        assert.equal(alertCalls, 0);
        assert.equal(runCalls.length, 0);
        assert.equal(globalThis.PwaInstallFirstOpportunity.isAutomaticSurfaceVisible(), false);
    });
});

describe('PwaInstallFirstOpportunity + learning-action-handlers integration', () => {
    var originalMatchMedia;
    var originalAddEventListener;
    var originalDispatchEvent;
    var eventListeners;
    var promptCalls;
    var testDocument;

    beforeEach(() => {
        originalMatchMedia = globalThis.matchMedia;
        originalAddEventListener = globalThis.addEventListener;
        originalDispatchEvent = globalThis.dispatchEvent;

        eventListeners = {};
        promptCalls = 0;
        testDocument = createTestDocument();

        globalThis.localStorage = createStorage();
        globalThis.AA_ADMIN_CONTEXT = { blogId: 99 };
        globalThis.document = testDocument;

        globalThis.matchMedia = function () {
            return { matches: false };
        };

        globalThis.addEventListener = function (type, listener) {
            if (!eventListeners[type]) {
                eventListeners[type] = [];
            }

            eventListeners[type].push(listener);
        };

        globalThis.dispatchEvent = function (event) {
            var listeners = eventListeners[event.type] || [];

            listeners.slice().forEach(function (listener) {
                listener(event);
            });

            return true;
        };

        loadHandlers();
        loadFirstOpportunity();
    });

    afterEach(() => {
        globalThis.matchMedia = originalMatchMedia;

        if (originalAddEventListener) {
            globalThis.addEventListener = originalAddEventListener;
        } else {
            delete globalThis.addEventListener;
        }

        if (originalDispatchEvent) {
            globalThis.dispatchEvent = originalDispatchEvent;
        } else {
            delete globalThis.dispatchEvent;
        }

        delete globalThis.localStorage;
        delete globalThis.AA_ADMIN_CONTEXT;
        delete globalThis.document;
        delete globalThis.LearningActionHandlers;
        delete globalThis.PwaInstallFirstOpportunity;
        delete require.cache[firstOpportunityPath];
        delete require.cache[handlersPath];
    });

    it('click real en Instalar ahora llega a prompt()', async () => {
        var evt = createBeforeInstallPromptEvent('accepted');

        evt.prompt = function () {
            promptCalls += 1;
            return Promise.resolve();
        };

        globalThis.dispatchEvent(evt);
        await globalThis.PwaInstallFirstOpportunity.tryOfferAutomaticInstall();

        var installButton = getInstallButton(testDocument);
        assert.ok(installButton);

        installButton.click();

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(promptCalls, 1);
        assert.equal(
            globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('99'),
            true
        );
    });

    it('Ahora no preserva deferredPrompt para segunda oportunidad', async () => {
        globalThis.dispatchEvent(createBeforeInstallPromptEvent('accepted'));
        await globalThis.PwaInstallFirstOpportunity.tryOfferAutomaticInstall();

        var dismissButton = getDismissButton(testDocument);
        assert.ok(dismissButton);

        dismissButton.click();

        assert.equal(
            globalThis.LearningActionHandlers.isAvailable(
                { type: 'handler', handler: 'pwa.install' },
                null
            ),
            true
        );
        assert.equal(globalThis.PwaInstallFirstOpportunity.hasConsumedFirstOpportunity('99'), true);
    });
});
