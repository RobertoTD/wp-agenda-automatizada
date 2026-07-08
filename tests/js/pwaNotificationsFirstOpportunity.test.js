'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const firstOpportunityPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/dashboard/pwa-notifications-first-opportunity.js'
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

function createTestDocument() {
    var nodesById = {};

    function TestNode(tagName) {
        this.tagName = String(tagName || '').toUpperCase();
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
            if (selector === '.aa-pwa-notifications-first-opportunity-dismiss') {
                return findByClass(this, 'aa-pwa-notifications-first-opportunity-dismiss');
            }

            if (selector === '.aa-pwa-notifications-first-opportunity-enable') {
                return findByClass(this, 'aa-pwa-notifications-first-opportunity-enable');
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

function loadFirstOpportunity() {
    delete require.cache[firstOpportunityPath];
    return require(firstOpportunityPath);
}

function getEnableButton(doc) {
    var root = doc.getElementById('aa-pwa-notifications-first-opportunity-root');

    return root ? root.querySelector('.aa-pwa-notifications-first-opportunity-enable') : null;
}

function getDismissButton(doc) {
    var root = doc.getElementById('aa-pwa-notifications-first-opportunity-root');

    return root ? root.querySelector('.aa-pwa-notifications-first-opportunity-dismiss') : null;
}

function flushMicrotasks() {
    return new Promise(function (resolve) {
        setImmediate(resolve);
    });
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

describe('PwaNotificationsFirstOpportunity', () => {
    var originalMatchMedia;
    var originalNavigator;
    var originalAdminContext;
    var originalDocument;
    var originalWindowRef;
    var originalNotification;
    var testDocument;
    var requestPermissionCalls;
    var activationCalls;

    function setStandalone(isStandalone) {
        globalThis.matchMedia = function (query) {
            return { matches: isStandalone && query === '(display-mode: standalone)' };
        };

        defineNavigator({ standalone: isStandalone });
    }

    function setNotificationApi(options) {
        var permission = options && options.permission !== undefined ? options.permission : 'default';
        var requestPermission = options && options.requestPermission;

        globalThis.window = globalThis;
        globalThis.Notification = {
            permission: permission,
            requestPermission: requestPermission || function () {
                requestPermissionCalls += 1;
                return Promise.resolve('granted');
            }
        };
    }

    function setupEligibleEnvironment() {
        setStandalone(true);
        setNotificationApi({ permission: 'default' });
    }

    beforeEach(() => {
        originalMatchMedia = globalThis.matchMedia;
        originalNavigator = globalThis.navigator;
        originalAdminContext = globalThis.AA_ADMIN_CONTEXT;
        originalDocument = globalThis.document;
        originalWindowRef = globalThis.window;
        originalNotification = globalThis.Notification;

        requestPermissionCalls = 0;
        activationCalls = 0;
        testDocument = createTestDocument();

        globalThis.localStorage = createStorage();
        globalThis.AA_ADMIN_CONTEXT = { blogId: 42 };
        globalThis.document = testDocument;
        globalThis.PwaPushActivationService = {
            activateFromGrantedPermission: function () {
                activationCalls += 1;
                return Promise.resolve({ completed: true, status: 'sent' });
            }
        };

        setStandalone(false);
        setNotificationApi({ permission: 'default' });

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

        if (originalWindowRef === undefined) {
            delete globalThis.window;
        } else {
            globalThis.window = originalWindowRef;
        }

        if (originalNotification === undefined) {
            delete globalThis.Notification;
        } else {
            globalThis.Notification = originalNotification;
        }

        delete globalThis.localStorage;
        delete globalThis.PwaNotificationsFirstOpportunity;
        delete globalThis.PwaPushActivationService;
        delete require.cache[firstOpportunityPath];
    });

    it('buildStorageKey incluye blogId', () => {
        assert.equal(
            globalThis.PwaNotificationsFirstOpportunity.buildStorageKey('7'),
            'aa_pwa_notifications_first_opportunity_v1:7'
        );
    });

    it('no standalone no muestra superficie', () => {
        setStandalone(false);
        setNotificationApi({ permission: 'default' });

        var offered = globalThis.PwaNotificationsFirstOpportunity.tryOfferNotificationsFirstOpportunity();

        assert.equal(offered, false);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), false);
        assert.equal(testDocument.getElementById('aa-pwa-notifications-first-opportunity-root'), null);
    });

    it('API Notification ausente no muestra superficie', () => {
        setStandalone(true);
        delete globalThis.Notification;
        globalThis.window = globalThis;

        var offered = globalThis.PwaNotificationsFirstOpportunity.tryOfferNotificationsFirstOpportunity();

        assert.equal(offered, false);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), false);
    });

    it('permiso granted no muestra superficie', () => {
        setupEligibleEnvironment();
        globalThis.Notification.permission = 'granted';

        var offered = globalThis.PwaNotificationsFirstOpportunity.tryOfferNotificationsFirstOpportunity();

        assert.equal(offered, false);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), false);
    });

    it('permiso denied no muestra superficie', () => {
        setupEligibleEnvironment();
        globalThis.Notification.permission = 'denied';

        var offered = globalThis.PwaNotificationsFirstOpportunity.tryOfferNotificationsFirstOpportunity();

        assert.equal(offered, false);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), false);
    });

    it('permiso default sin consumir muestra superficie', () => {
        setupEligibleEnvironment();

        var rendered = globalThis.PwaNotificationsFirstOpportunity.renderSurface();

        assert.equal(rendered, true);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), true);
        assert.ok(testDocument.getElementById('aa-pwa-notifications-first-opportunity-root'));

        var title = testDocument.nodesById['aa-pwa-notifications-first-opportunity-title'];
        assert.ok(title);
        assert.equal(title.textContent, 'Activa las notificaciones de DEOIA');
    });

    it('Ahora no no llama requestPermission y consume', () => {
        setupEligibleEnvironment();
        globalThis.PwaNotificationsFirstOpportunity.renderSurface();

        var dismissButton = getDismissButton(testDocument);
        assert.ok(dismissButton);

        dismissButton.click();

        assert.equal(requestPermissionCalls, 0);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), false);
    });

    it('Activar notificaciones llama requestPermission directamente desde el click', async () => {
        setupEligibleEnvironment();
        globalThis.PwaNotificationsFirstOpportunity.renderSurface();

        var enableButton = getEnableButton(testDocument);
        assert.ok(enableButton);

        enableButton.click();

        assert.equal(requestPermissionCalls, 1);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), false);

        await flushMicrotasks();

        assert.equal(globalThis.PwaNotificationsFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
    });

    it('resultado granted consume', async () => {
        setupEligibleEnvironment();
        globalThis.Notification.requestPermission = function () {
            requestPermissionCalls += 1;
            return Promise.resolve('granted');
        };

        globalThis.PwaNotificationsFirstOpportunity.renderSurface();
        getEnableButton(testDocument).click();

        await flushMicrotasks();

        assert.equal(requestPermissionCalls, 1);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
        assert.equal(activationCalls, 1);
    });

    it('resultado denied consume sin delegar activacion push', async () => {
        setupEligibleEnvironment();
        globalThis.Notification.requestPermission = function () {
            requestPermissionCalls += 1;
            return Promise.resolve('denied');
        };

        globalThis.PwaNotificationsFirstOpportunity.renderSurface();
        getEnableButton(testDocument).click();

        await flushMicrotasks();

        assert.equal(requestPermissionCalls, 1);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
        assert.equal(activationCalls, 0);
    });

    it('resultado default tras prompt consume sin delegar activacion push', async () => {
        setupEligibleEnvironment();
        globalThis.Notification.requestPermission = function () {
            requestPermissionCalls += 1;
            return Promise.resolve('default');
        };

        globalThis.PwaNotificationsFirstOpportunity.renderSurface();
        getEnableButton(testDocument).click();

        await flushMicrotasks();

        assert.equal(requestPermissionCalls, 1);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.hasConsumedFirstOpportunity('42'), true);
        assert.equal(activationCalls, 0);
    });

    it('error tecnico no consume', async () => {
        setupEligibleEnvironment();
        globalThis.Notification.requestPermission = function () {
            requestPermissionCalls += 1;
            return Promise.reject(new Error('permission failed'));
        };

        globalThis.PwaNotificationsFirstOpportunity.renderSurface();
        getEnableButton(testDocument).click();

        await flushMicrotasks();

        assert.equal(requestPermissionCalls, 1);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.hasConsumedFirstOpportunity('42'), false);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isRequestInFlight(), false);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), false);
    });

    it('no hay doble ejecucion mientras requestInFlight esta activo', () => {
        setupEligibleEnvironment();
        globalThis.Notification.requestPermission = function () {
            requestPermissionCalls += 1;
            return new Promise(function () {});
        };

        globalThis.PwaNotificationsFirstOpportunity.renderSurface();

        var event = {
            preventDefault: function () {},
            stopPropagation: function () {}
        };

        globalThis.PwaNotificationsFirstOpportunity.onEnableNotificationsClick(event);
        globalThis.PwaNotificationsFirstOpportunity.onEnableNotificationsClick(event);

        assert.equal(requestPermissionCalls, 1);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isRequestInFlight(), true);
    });

    it('oportunidad consumida bloquea render posterior', () => {
        setupEligibleEnvironment();
        globalThis.PwaNotificationsFirstOpportunity.markConsumedFirstOpportunity('42');

        var rendered = globalThis.PwaNotificationsFirstOpportunity.renderSurface();

        assert.equal(rendered, false);
        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), false);
    });
});

describe('PwaNotificationsFirstOpportunity init en standalone elegible', () => {
    var originalMatchMedia;
    var originalNavigator;
    var originalAdminContext;
    var originalDocument;
    var originalWindowRef;
    var originalNotification;
    var testDocument;

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

        if (originalWindowRef === undefined) {
            delete globalThis.window;
        } else {
            globalThis.window = originalWindowRef;
        }

        if (originalNotification === undefined) {
            delete globalThis.Notification;
        } else {
            globalThis.Notification = originalNotification;
        }

        delete globalThis.localStorage;
        delete globalThis.PwaNotificationsFirstOpportunity;
        delete globalThis.PwaPushActivationService;
        delete require.cache[firstOpportunityPath];
    });

    it('init muestra superficie al cargar en standalone con permiso default', () => {
        originalMatchMedia = globalThis.matchMedia;
        originalNavigator = globalThis.navigator;
        originalAdminContext = globalThis.AA_ADMIN_CONTEXT;
        originalDocument = globalThis.document;
        originalWindowRef = globalThis.window;
        originalNotification = globalThis.Notification;

        testDocument = createTestDocument();
        globalThis.localStorage = createStorage();
        globalThis.AA_ADMIN_CONTEXT = { blogId: 42 };
        globalThis.document = testDocument;
        globalThis.matchMedia = function (query) {
            return { matches: query === '(display-mode: standalone)' };
        };
        defineNavigator({ standalone: true });
        globalThis.window = globalThis;
        globalThis.Notification = {
            permission: 'default',
            requestPermission: function () {
                return Promise.resolve('default');
            }
        };

        loadFirstOpportunity();

        assert.equal(globalThis.PwaNotificationsFirstOpportunity.isSurfaceVisible(), true);
        assert.ok(testDocument.getElementById('aa-pwa-notifications-first-opportunity-root'));
    });
});
