'use strict';

const assert = require('node:assert/strict');
const { describe, it, afterEach } = require('node:test');
const path = require('node:path');

const handlersPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/learning-action-handlers.js');
const reconcilePath = path.join(__dirname, '../../assets/js/services/pushActivationReconcileService.js');

function pushAction() {
    return {
        type: 'handler',
        label: 'Activar notificaciones',
        handler: 'push.activate'
    };
}

function loadHandlers(options) {
    options = options || {};

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

    globalThis.PushActivationReconcileService = {
        isPushSupported: function () {
            return true;
        },
        markPushReady: options.markPushReady || function () {
            return { app_subscription_active: true, push_ready: true };
        }
    };

    delete require.cache[reconcilePath];
    delete require.cache[handlersPath];

    require(handlersPath);

    return globalThis.LearningActionHandlers;
}

describe('push.activate handler (MC2)', () => {
    afterEach(() => {
        delete globalThis.LearningActionHandlers;
        delete globalThis.PushActivationReconcileService;
        delete globalThis.PwaPushActivationService;
        delete globalThis.TasksService;
        delete globalThis.Notification;
        delete globalThis.navigator;
        delete globalThis.ServiceWorkerRegistration;
        delete require.cache[handlersPath];
        delete require.cache[reconcilePath];
    });

    it('default pide permiso y marca push_ready tras registro', async () => {
        var requestCalls = 0;
        var readyCalls = 0;
        var handlers = loadHandlers({
            permission: 'default',
            requestPermission: function () {
                requestCalls += 1;
                return Promise.resolve('granted');
            },
            markPushReady: function (value) {
                readyCalls += 1;
                assert.equal(value, true);
            }
        });

        var result = await handlers.run(pushAction(), {});

        assert.equal(requestCalls, 1);
        assert.equal(readyCalls, 1);
        assert.equal(result.reload, true);
    });

    it('granted activa directamente y marca push_ready', async () => {
        var requestCalls = 0;
        var readyCalls = 0;
        var handlers = loadHandlers({
            permission: 'granted',
            requestPermission: function () {
                requestCalls += 1;
                return Promise.resolve('granted');
            },
            markPushReady: function () {
                readyCalls += 1;
            }
        });

        var result = await handlers.run(pushAction(), {});

        assert.equal(requestCalls, 0);
        assert.equal(readyCalls, 1);
        assert.equal(result.reload, true);
    });

    it('denied no solicita permiso y muestra instrucciones', async () => {
        var requestCalls = 0;
        var readyCalls = 0;
        var handlers = loadHandlers({
            permission: 'denied',
            requestPermission: function () {
                requestCalls += 1;
                return Promise.resolve('granted');
            },
            markPushReady: function () {
                readyCalls += 1;
            }
        });

        await assert.rejects(
            () => handlers.run(pushAction(), {}),
            /bloqueadas/i
        );

        assert.equal(requestCalls, 0);
        assert.equal(readyCalls, 0);
    });

    it('fallo de registro no completa la tarea', async () => {
        var readyCalls = 0;
        var handlers = loadHandlers({
            permission: 'granted',
            activateFromGrantedPermission: function () {
                return Promise.resolve({ registrationSucceeded: false, completed: false, status: 'failed' });
            },
            markPushReady: function () {
                readyCalls += 1;
            }
        });

        await assert.rejects(
            () => handlers.run(pushAction(), {}),
            /No se pudo registrar las notificaciones push/
        );

        assert.equal(readyCalls, 0);
    });

    it('ready solo se establece después del registro backend exitoso', async () => {
        var order = [];
        var handlers = loadHandlers({
            permission: 'granted',
            activateFromGrantedPermission: function () {
                order.push('activate');
                return Promise.resolve({ registrationSucceeded: true, completed: true, status: 'registered' });
            },
            markPushReady: function () {
                order.push('ready');
            }
        });

        await handlers.run(pushAction(), {});

        assert.deepEqual(order, ['activate', 'ready']);
    });
});
