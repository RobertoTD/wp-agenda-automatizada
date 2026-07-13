'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const handlersPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/learning-action-handlers.js');
const deviceKeyPath = path.join(__dirname, '../../assets/js/services/pushDeviceKeyService.js');
const reconcilePath = path.join(__dirname, '../../assets/js/services/pushActivationReconcileService.js');

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
        }
    };
}

function pushAction() {
    return {
        type: 'handler',
        label: 'Activar notificaciones',
        handler: 'push.activate'
    };
}

function loadHandlers(options) {
    options = options || {};

    globalThis.AA_ADMIN_CONTEXT = { blogId: 9 };
    globalThis.localStorage = createStorage();
    Object.defineProperty(globalThis, 'crypto', {
        configurable: true,
        writable: true,
        value: {
            getRandomValues: function (bytes) {
                for (var i = 0; i < bytes.length; i += 1) {
                    bytes[i] = 0x11;
                }

                return bytes;
            }
        }
    });

    globalThis.Notification = {
        permission: options.permission || 'default',
        requestPermission: options.requestPermission || function () {
            return Promise.resolve('granted');
        }
    };

    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        writable: true,
        value: { serviceWorker: {} }
    });
    globalThis.ServiceWorkerRegistration = function ServiceWorkerRegistration() {};
    globalThis.ServiceWorkerRegistration.prototype.pushManager = {};

    globalThis.PwaPushActivationService = {
        activateFromGrantedPermission: options.activateFromGrantedPermission || function () {
            return Promise.resolve({ registrationSucceeded: true, completed: true, status: 'registered' });
        }
    };

    globalThis.TasksService = {
        reconcilePushActivationTask: options.reconcilePushActivationTask || function () {
            return Promise.resolve({ ok: true });
        }
    };

    delete require.cache[deviceKeyPath];
    delete require.cache[reconcilePath];
    delete require.cache[handlersPath];

    require(deviceKeyPath);
    require(reconcilePath);
    require(handlersPath);

    return globalThis.LearningActionHandlers;
}

describe('push.activate handler (MC2)', () => {
    afterEach(() => {
        delete globalThis.LearningActionHandlers;
        delete globalThis.PushActivationReconcileService;
        delete globalThis.PushDeviceKeyService;
        delete globalThis.PwaPushActivationService;
        delete globalThis.TasksService;
        delete globalThis.Notification;
        delete globalThis.navigator;
        delete globalThis.ServiceWorkerRegistration;
        delete globalThis.localStorage;
        delete globalThis.crypto;
        delete globalThis.AA_ADMIN_CONTEXT;
        delete require.cache[handlersPath];
        delete require.cache[deviceKeyPath];
        delete require.cache[reconcilePath];
    });

    it('default pide permiso en click y reconcilia prepared tras registro', async () => {
        var requestCalls = 0;
        var reconcileCalls = 0;
        var handlers = loadHandlers({
            permission: 'default',
            requestPermission: function () {
                requestCalls += 1;
                return Promise.resolve('granted');
            },
            reconcilePushActivationTask: function (deviceKey, readiness) {
                reconcileCalls += 1;
                assert.equal(readiness, 'prepared');
                assert.match(deviceKey, /^[a-f0-9]{32}$/);
                return Promise.resolve({});
            }
        });

        var result = await handlers.run(pushAction(), {});

        assert.equal(requestCalls, 1);
        assert.equal(reconcileCalls, 1);
        assert.equal(result.reload, true);
    });

    it('granted activa directamente y reconcilia prepared', async () => {
        var requestCalls = 0;
        var reconcileCalls = 0;
        var handlers = loadHandlers({
            permission: 'granted',
            requestPermission: function () {
                requestCalls += 1;
                return Promise.resolve('granted');
            },
            reconcilePushActivationTask: function (deviceKey, readiness) {
                reconcileCalls += 1;
                assert.equal(readiness, 'prepared');
                return Promise.resolve({});
            }
        });

        var result = await handlers.run(pushAction(), {});

        assert.equal(requestCalls, 0);
        assert.equal(reconcileCalls, 1);
        assert.equal(result.reload, true);
    });

    it('denied no solicita permiso y muestra instrucciones', async () => {
        var requestCalls = 0;
        var reconcileCalls = 0;
        var handlers = loadHandlers({
            permission: 'denied',
            requestPermission: function () {
                requestCalls += 1;
                return Promise.resolve('granted');
            },
            reconcilePushActivationTask: function () {
                reconcileCalls += 1;
                return Promise.resolve({});
            }
        });

        await assert.rejects(
            () => handlers.run(pushAction(), {}),
            /bloqueadas/i
        );

        assert.equal(requestCalls, 0);
        assert.equal(reconcileCalls, 0);
    });

    it('fallo de registro no completa la tarea', async () => {
        var reconcileCalls = 0;
        var handlers = loadHandlers({
            permission: 'granted',
            activateFromGrantedPermission: function () {
                return Promise.resolve({ registrationSucceeded: false, completed: false, status: 'failed' });
            },
            reconcilePushActivationTask: function () {
                reconcileCalls += 1;
                return Promise.resolve({});
            }
        });

        await assert.rejects(
            () => handlers.run(pushAction(), {}),
            /No se pudo registrar las notificaciones push/
        );

        assert.equal(reconcileCalls, 0);
    });

    it('completado solo despues de registro backend exitoso', async () => {
        var order = [];
        var handlers = loadHandlers({
            permission: 'granted',
            activateFromGrantedPermission: function () {
                order.push('activate');
                return Promise.resolve({ registrationSucceeded: true, completed: true, status: 'registered' });
            },
            reconcilePushActivationTask: function () {
                order.push('reconcile');
                return Promise.resolve({});
            }
        });

        await handlers.run(pushAction(), {});

        assert.deepEqual(order, ['activate', 'reconcile']);
    });
});
