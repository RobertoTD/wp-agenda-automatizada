'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const servicePath = path.join(
    __dirname,
    '../../assets/js/services/pwaPushActivationService.js'
);

const VAPID_KEY = 'test-public-key';
const SUBSCRIPTION_ENDPOINT = 'https://push.example.test/subscription/abc';
const SUBSCRIPTION_P256DH = 'p256dh-key';
const SUBSCRIPTION_AUTH = 'auth-key';

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
        },
        _dump: function () {
            return store;
        }
    };
}

function createFormDataMock() {
    function FormDataMock() {
        this._fields = new Map();
    }

    FormDataMock.prototype.append = function (key, value) {
        this._fields.set(String(key), String(value));
    };

    FormDataMock.prototype.get = function (key) {
        return this._fields.has(String(key)) ? this._fields.get(String(key)) : null;
    };

    FormDataMock.prototype.keys = function () {
        return this._fields.keys();
    };

    return FormDataMock;
}

function createSubscription(options) {
    options = options || {};

    return {
        endpoint: options.endpoint || SUBSCRIPTION_ENDPOINT,
        toJSON: function () {
            return {
                endpoint: options.endpoint || SUBSCRIPTION_ENDPOINT,
                keys: {
                    p256dh: options.p256dh || SUBSCRIPTION_P256DH,
                    auth: options.auth || SUBSCRIPTION_AUTH
                }
            };
        },
        getKey: function (name) {
            if (name === 'p256dh') {
                return options.p256dhBuffer || null;
            }

            if (name === 'auth') {
                return options.authBuffer || null;
            }

            return null;
        }
    };
}

function loadService(options) {
    options = options || {};

    var fetchCalls = [];
    var subscribeCalls = 0;
    var getSubscriptionCalls = 0;
    var existingSubscription = options.existingSubscription === undefined
        ? null
        : options.existingSubscription;
    var subscribeError = options.subscribeError || null;
    var fetchImpl = options.fetchImpl;

    var pushManager = {
        getSubscription: function () {
            getSubscriptionCalls += 1;
            return Promise.resolve(existingSubscription);
        },
        subscribe: function (params) {
            subscribeCalls += 1;

            if (subscribeError) {
                return Promise.reject(subscribeError);
            }

            existingSubscription = createSubscription({
                endpoint: SUBSCRIPTION_ENDPOINT,
                p256dh: SUBSCRIPTION_P256DH,
                auth: SUBSCRIPTION_AUTH
            });

            return Promise.resolve(existingSubscription);
        }
    };

    var registration = {
        pushManager: options.pushManager === undefined ? pushManager : options.pushManager
    };

    globalThis.localStorage = options.storage || createStorage();

    if (options.pendingBeforeLoad) {
        globalThis.localStorage.setItem('aa_pwa_push_activation_pending_v1:42', '1');
    }

    globalThis.AA_ADMIN_CONTEXT = options.adminContext || { blogId: 42 };
    globalThis.AA_PUSH_CONFIG = options.pushConfig || {
        ajaxUrl: 'https://tenant.example.test/wp-admin/admin-ajax.php',
        configAction: 'aa_get_push_config',
        registerAction: 'aa_register_push_subscription',
        nonce: 'test-nonce'
    };
    globalThis.Notification = {
        permission: options.permission === undefined ? 'granted' : options.permission
    };
    globalThis.FormData = createFormDataMock();
    globalThis.atob = function (value) {
        return Buffer.from(String(value), 'base64').toString('binary');
    };
    globalThis.btoa = function (value) {
        return Buffer.from(String(value), 'binary').toString('base64');
    };

    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        writable: true,
        value: options.serviceWorker === undefined ? {
            serviceWorker: {
                ready: Promise.resolve(registration)
            }
        } : options.serviceWorker
    });

    globalThis.fetch = fetchImpl || function (url, requestOptions) {
        fetchCalls.push({ url: url, options: requestOptions || {} });
        var action = requestOptions && requestOptions.body ? requestOptions.body.get('action') : '';

        if (action === 'aa_get_push_config') {
            if (options.configResponse) {
                return Promise.resolve(options.configResponse);
            }

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: { vapidPublicKey: VAPID_KEY }
                    });
                }
            });
        }

        if (action === 'aa_register_push_subscription') {
            if (options.registerResponse) {
                return Promise.resolve(options.registerResponse);
            }

            return Promise.resolve({
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: {
                            ok: true,
                            registration: 'created',
                            first_test: { status: 'sent' }
                        }
                    });
                }
            });
        }

        return Promise.resolve({
            ok: false,
            status: 500,
            json: function () {
                return Promise.resolve({ success: false, data: { error: 'push_backend_unavailable' } });
            }
        });
    };

    delete require.cache[servicePath];
    var service = require(servicePath);

    return {
        service: service,
        test: service.__test,
        fetchCalls: fetchCalls,
        get subscribeCalls() {
            return subscribeCalls;
        },
        get getSubscriptionCalls() {
            return getSubscriptionCalls;
        },
        get existingSubscription() {
            return existingSubscription;
        }
    };
}

function readFormField(body, field) {
    return body && typeof body.get === 'function' ? body.get(field) : null;
}

describe('PwaPushActivationService', () => {
    var loaded;
    var originalNavigator;

    beforeEach(() => {
        originalNavigator = globalThis.navigator;
    });

    afterEach(() => {
        delete globalThis.PwaPushActivationService;
        delete globalThis.localStorage;
        delete globalThis.AA_ADMIN_CONTEXT;
        delete globalThis.AA_PUSH_CONFIG;
        delete globalThis.Notification;
        if (originalNavigator === undefined) {
            delete globalThis.navigator;
        } else {
            Object.defineProperty(globalThis, 'navigator', {
                configurable: true,
                writable: true,
                value: originalNavigator
            });
        }
        delete globalThis.fetch;
        delete globalThis.FormData;
        delete globalThis.atob;
        delete globalThis.btoa;
        delete require.cache[servicePath];
    });

    it('obtiene VAPID publica via aa_get_push_config', async () => {
        loaded = loadService({ permission: 'granted' });

        await loaded.service.activateFromGrantedPermission();

        assert.equal(loaded.fetchCalls.length >= 1, true);
        assert.equal(readFormField(loaded.fetchCalls[0].options.body, 'action'), 'aa_get_push_config');
        assert.equal(readFormField(loaded.fetchCalls[0].options.body, '_wpnonce'), 'test-nonce');
    });

    it('convierte applicationServerKey desde Base64URL', () => {
        loaded = loadService({ permission: 'granted' });

        var bytes = loaded.test.urlBase64ToUint8Array('dGVzdA');

        assert.ok(bytes instanceof Uint8Array);
        assert.equal(bytes.length, 4);
    });

    it('reutiliza subscription existente sin llamar subscribe', async () => {
        var existing = createSubscription();
        loaded = loadService({
            permission: 'granted',
            existingSubscription: existing
        });

        await loaded.service.activateFromGrantedPermission();

        assert.equal(loaded.getSubscriptionCalls, 1);
        assert.equal(loaded.subscribeCalls, 0);
    });

    it('crea subscription si no existe', async () => {
        loaded = loadService({
            permission: 'granted',
            existingSubscription: null
        });

        await loaded.service.activateFromGrantedPermission();

        assert.equal(loaded.getSubscriptionCalls, 1);
        assert.equal(loaded.subscribeCalls, 1);
    });

    it('comprobación pasiva sin subscription no llama subscribe ni VAPID', async () => {
        loaded = loadService({
            permission: 'granted',
            existingSubscription: null
        });

        var result = await loaded.service.reconcileExistingSubscription();

        assert.equal(result.registrationSucceeded, false);
        assert.equal(result.status, 'subscription_missing');
        assert.equal(loaded.subscribeCalls, 0);
        assert.equal(loaded.fetchCalls.length, 0);
    });

    it('comprobación pasiva registra una subscription existente sin VAPID', async () => {
        loaded = loadService({
            permission: 'granted',
            existingSubscription: createSubscription()
        });

        var result = await loaded.service.reconcileExistingSubscription();
        var actions = loaded.fetchCalls.map(function (call) {
            return readFormField(call.options.body, 'action');
        });

        assert.equal(result.registrationSucceeded, true);
        assert.equal(loaded.subscribeCalls, 0);
        assert.deepEqual(actions, ['aa_register_push_subscription']);
    });

    it('registra endpoint p256dh auth exactos', async () => {
        loaded = loadService({ permission: 'granted' });

        await loaded.service.activateFromGrantedPermission();

        var registerCall = loaded.fetchCalls.find(function (call) {
            return readFormField(call.options.body, 'action') === 'aa_register_push_subscription';
        });

        assert.ok(registerCall);
        assert.equal(readFormField(registerCall.options.body, 'endpoint'), SUBSCRIPTION_ENDPOINT);
        assert.equal(readFormField(registerCall.options.body, 'p256dh'), SUBSCRIPTION_P256DH);
        assert.equal(readFormField(registerCall.options.body, 'auth'), SUBSCRIPTION_AUTH);
        assert.equal(readFormField(registerCall.options.body, 'installation_id'), null);
        assert.equal(readFormField(registerCall.options.body, 'blogId'), null);
    });

    it('sent limpia pending', async () => {
        loaded = loadService({ permission: 'granted' });
        loaded.test.markPending('42');

        await loaded.service.activateFromGrantedPermission();

        assert.equal(loaded.test.hasPending('42'), false);
    });

    it('already_sent limpia pending', async () => {
        loaded = loadService({
            permission: 'granted',
            registerResponse: {
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: {
                            ok: true,
                            registration: 'updated',
                            first_test: { status: 'already_sent' }
                        }
                    });
                }
            }
        });
        loaded.test.markPending('42');

        await loaded.service.activateFromGrantedPermission();

        assert.equal(loaded.test.hasPending('42'), false);
    });

    it('sent_unconfirmed limpia pending', async () => {
        loaded = loadService({
            permission: 'granted',
            registerResponse: {
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: {
                            ok: true,
                            registration: 'created',
                            first_test: { status: 'sent_unconfirmed' }
                        }
                    });
                }
            }
        });
        loaded.test.markPending('42');

        await loaded.service.activateFromGrantedPermission();

        assert.equal(loaded.test.hasPending('42'), false);
    });

    it('failed first_test con ok true sigue siendo exito y limpia pending', async () => {
        loaded = loadService({
            permission: 'granted',
            registerResponse: {
                ok: true,
                status: 200,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: {
                            ok: true,
                            registration: 'created',
                            first_test: { status: 'failed', reason: 'transport' }
                        }
                    });
                }
            }
        });
        loaded.test.markPending('42');

        var result = await loaded.service.activateFromGrantedPermission();

        assert.equal(result.registrationSucceeded, true);
        assert.equal(result.completed, true);
        assert.equal(result.status, 'failed');
        assert.equal(loaded.test.hasPending('42'), false);
    });

    it('fallo de config conserva pending', async () => {
        loaded = loadService({
            permission: 'granted',
            configResponse: {
                ok: false,
                status: 503,
                json: function () {
                    return Promise.resolve({
                        success: false,
                        data: { ok: false, error: 'push_config_unavailable' }
                    });
                }
            }
        });
        loaded.test.markPending('42');

        await assert.rejects(
            loaded.service.activateFromGrantedPermission(),
            function (err) {
                return err && err.code === 'push_config_unavailable';
            }
        );

        assert.equal(loaded.test.hasPending('42'), true);
    });

    it('fallo subscribe conserva pending', async () => {
        loaded = loadService({
            permission: 'granted',
            existingSubscription: null,
            subscribeError: new Error('subscribe failed')
        });
        loaded.test.markPending('42');

        await assert.rejects(
            loaded.service.activateFromGrantedPermission(),
            function (err) {
                return err && err.message === 'subscribe failed';
            }
        );

        assert.equal(loaded.test.hasPending('42'), true);
    });

    it('fallo AJAX conserva pending y preserva error funcional', async () => {
        loaded = loadService({
            permission: 'granted',
            registerResponse: {
                ok: false,
                status: 409,
                json: function () {
                    return Promise.resolve({
                        success: false,
                        data: { ok: false, error: 'endpoint_conflict' }
                    });
                }
            }
        });
        loaded.test.markPending('42');

        await assert.rejects(
            loaded.service.activateFromGrantedPermission(),
            function (err) {
                return err && err.code === 'endpoint_conflict';
            }
        );

        assert.equal(loaded.test.hasPending('42'), true);
    });

    it('recuperacion pending solo se ejecuta al invocarla y una vez por carga', async () => {
        loaded = loadService({
            permission: 'granted',
            pendingBeforeLoad: true,
            existingSubscription: createSubscription()
        });

        assert.equal(loaded.fetchCalls.length, 0);

        await loaded.service.maybeAttemptAutomaticRecovery();
        await loaded.service.maybeAttemptAutomaticRecovery();

        var configCalls = loaded.fetchCalls.filter(function (call) {
            return readFormField(call.options.body, 'action') === 'aa_get_push_config';
        });

        assert.equal(configCalls.length, 1);
        assert.equal(loaded.test.isActivationInFlight(), false);
    });

    it('granted historico sin pending no activa automaticamente', async () => {
        loaded = loadService({
            permission: 'granted',
            existingSubscription: createSubscription()
        });

        await new Promise(function (resolve) {
            setImmediate(resolve);
        });

        assert.equal(loaded.fetchCalls.length, 0);
    });

    it('doble activacion simultanea no duplica operaciones', async () => {
        loaded = loadService({ permission: 'granted' });

        var first = loaded.service.activateFromGrantedPermission();
        var second = loaded.service.activateFromGrantedPermission();

        assert.equal(first, second);

        await first;

        var configCalls = loaded.fetchCalls.filter(function (call) {
            return readFormField(call.options.body, 'action') === 'aa_get_push_config';
        });
        var registerCalls = loaded.fetchCalls.filter(function (call) {
            return readFormField(call.options.body, 'action') === 'aa_register_push_subscription';
        });

        assert.equal(configCalls.length, 1);
        assert.equal(registerCalls.length, 1);
    });

    it('activationInFlight se libera tras finalizar', async () => {
        loaded = loadService({ permission: 'granted' });

        await loaded.service.activateFromGrantedPermission();

        assert.equal(loaded.test.isActivationInFlight(), false);
    });

    it('recuperacion pending controlada no produce unhandled rejection', async () => {
        loaded = loadService({
            permission: 'granted',
            pendingBeforeLoad: true,
            configResponse: {
                ok: false,
                status: 503,
                json: function () {
                    return Promise.resolve({
                        success: false,
                        data: { ok: false, error: 'push_config_unavailable' }
                    });
                }
            }
        });

        await assert.doesNotReject(
            loaded.service.maybeAttemptAutomaticRecovery()
        );

        assert.equal(loaded.test.hasPending('42'), true);
    });
});
