'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

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

function loadReconcileService(options) {
    options = options || {};

    globalThis.AA_ADMIN_CONTEXT = { blogId: 7 };
    globalThis.localStorage = createStorage();
    Object.defineProperty(globalThis, 'crypto', {
        configurable: true,
        writable: true,
        value: {
            getRandomValues: function (bytes) {
                for (var i = 0; i < bytes.length; i += 1) {
                    bytes[i] = 0xaa;
                }

                return bytes;
            }
        }
    });

    if (options.notification === false) {
        delete globalThis.Notification;
    } else {
        globalThis.Notification = options.notification || {
            permission: options.permission || 'default',
            requestPermission: options.requestPermission || function () {
                return Promise.resolve('granted');
            }
        };
    }

    if (options.navigator === false) {
        Object.defineProperty(globalThis, 'navigator', {
            configurable: true,
            writable: true,
            value: undefined
        });
    } else {
        Object.defineProperty(globalThis, 'navigator', {
            configurable: true,
            writable: true,
            value: options.navigator || { serviceWorker: {} }
        });
    }

    if (options.pushManager === false) {
        delete globalThis.PushManager;
        delete globalThis.ServiceWorkerRegistration;
    } else {
        globalThis.ServiceWorkerRegistration = function ServiceWorkerRegistration() {};
        globalThis.ServiceWorkerRegistration.prototype.pushManager = {};
    }

    globalThis.TasksService = {
        reconcilePushActivationTask: options.reconcilePushActivationTask || function (deviceKey, readiness) {
            return Promise.resolve({
                device_key: deviceKey,
                readiness: readiness
            });
        }
    };

    globalThis.PwaPushActivationService = {
        activateFromGrantedPermission: options.activateFromGrantedPermission || function () {
            return Promise.resolve({ registrationSucceeded: true, completed: true, status: 'registered' });
        }
    };

    delete require.cache[deviceKeyPath];
    delete require.cache[reconcilePath];
    require(deviceKeyPath);

    return require(reconcilePath);
}

describe('PushActivationReconcileService', () => {
    afterEach(() => {
        delete globalThis.PushActivationReconcileService;
        delete globalThis.PushDeviceKeyService;
        delete globalThis.TasksService;
        delete globalThis.PwaPushActivationService;
        delete globalThis.Notification;
        delete globalThis.navigator;
        delete globalThis.PushManager;
        delete globalThis.ServiceWorkerRegistration;
        delete globalThis.localStorage;
        delete globalThis.crypto;
        delete globalThis.AA_ADMIN_CONTEXT;
        delete require.cache[deviceKeyPath];
        delete require.cache[reconcilePath];
    });

    it('unsupported no reconcilia', async () => {
        var reconcileCalls = 0;
        var service = loadReconcileService({
            notification: false,
            reconcilePushActivationTask: function () {
                reconcileCalls += 1;
                return Promise.resolve({});
            }
        });

        await service.reconcileOnFeedInit();

        assert.equal(reconcileCalls, 0);
    });

    it('permission default reconcilia unprepared sin prompt', async () => {
        var requestCalls = 0;
        var captured = null;
        var service = loadReconcileService({
            permission: 'default',
            requestPermission: function () {
                requestCalls += 1;
                return Promise.resolve('granted');
            },
            reconcilePushActivationTask: function (deviceKey, readiness) {
                captured = { deviceKey: deviceKey, readiness: readiness };
                return Promise.resolve({});
            }
        });

        await service.reconcileOnFeedInit();

        assert.equal(requestCalls, 0);
        assert.equal(captured.readiness, 'unprepared');
        assert.match(captured.deviceKey, /^[a-f0-9]{32}$/);
    });

    it('permission denied reconcilia unprepared', async () => {
        var captured = null;
        var service = loadReconcileService({
            permission: 'denied',
            reconcilePushActivationTask: function (deviceKey, readiness) {
                captured = { deviceKey: deviceKey, readiness: readiness };
                return Promise.resolve({});
            }
        });

        await service.reconcileOnFeedInit();

        assert.equal(captured.readiness, 'unprepared');
    });

    it('granted + registro exitoso reconcilia prepared', async () => {
        var captured = null;
        var service = loadReconcileService({
            permission: 'granted',
            activateFromGrantedPermission: function () {
                return Promise.resolve({ registrationSucceeded: true, completed: true, status: 'registered' });
            },
            reconcilePushActivationTask: function (deviceKey, readiness) {
                captured = { deviceKey: deviceKey, readiness: readiness };
                return Promise.resolve({});
            }
        });

        await service.reconcileOnFeedInit();

        assert.equal(captured.readiness, 'prepared');
    });

    it('granted + fallo de registro reconcilia unprepared', async () => {
        var captured = null;
        var service = loadReconcileService({
            permission: 'granted',
            activateFromGrantedPermission: function () {
                return Promise.resolve({ registrationSucceeded: false, completed: false, status: 'failed' });
            },
            reconcilePushActivationTask: function (deviceKey, readiness) {
                captured = { deviceKey: deviceKey, readiness: readiness };
                return Promise.resolve({});
            }
        });

        await service.reconcileOnFeedInit();

        assert.equal(captured.readiness, 'unprepared');
    });

    it('data.ok true con first_test fallido sigue siendo prepared', async () => {
        var captured = null;
        var service = loadReconcileService({
            permission: 'granted',
            activateFromGrantedPermission: function () {
                return Promise.resolve({
                    registrationSucceeded: true,
                    completed: true,
                    status: 'failed'
                });
            },
            reconcilePushActivationTask: function (deviceKey, readiness) {
                captured = { deviceKey: deviceKey, readiness: readiness };
                return Promise.resolve({});
            }
        });

        await service.reconcileOnFeedInit();

        assert.equal(captured.readiness, 'prepared');
    });

    it('inicializacion unica reutiliza la misma promesa', async () => {
        var reconcileCalls = 0;
        var service = loadReconcileService({
            permission: 'default',
            reconcilePushActivationTask: function () {
                reconcileCalls += 1;
                return Promise.resolve({});
            }
        });

        var first = service.reconcileOnFeedInit();
        var second = service.reconcileOnFeedInit();

        assert.equal(first, second);
        await first;
        assert.equal(reconcileCalls, 1);
    });

    it('fallo de reconciliacion no rechaza la promesa', async () => {
        var service = loadReconcileService({
            permission: 'default',
            reconcilePushActivationTask: function () {
                return Promise.reject(new Error('network down'));
            }
        });

        await assert.doesNotReject(async () => {
            await service.reconcileOnFeedInit();
        });
    });

    it('sin localStorage ni crypto no reconcilia', async () => {
        var reconcileCalls = 0;
        var service = loadReconcileService({
            permission: 'default',
            reconcilePushActivationTask: function () {
                reconcileCalls += 1;
                return Promise.resolve({});
            }
        });

        delete globalThis.localStorage;

        await service.reconcileOnFeedInit();

        assert.equal(reconcileCalls, 0);
    });
});

describe('PushActivationReconcileService reconcileProducedFeedChanges', () => {
    const reconcileServicePath = path.join(__dirname, '../../assets/js/services/pushActivationReconcileService.js');

    it('created=true indica cambios de feed', () => {
        var api = require(reconcileServicePath);

        assert.equal(api.reconcileProducedFeedChanges({ created: true, completed_task_ids: [] }), true);
    });

    it('completed_task_ids no vacío indica cambios de feed', () => {
        var api = require(reconcileServicePath);

        assert.equal(api.reconcileProducedFeedChanges({ created: false, completed_task_ids: [12] }), true);
    });

    it('resultado sin cambios no indica recarga', () => {
        var api = require(reconcileServicePath);

        assert.equal(api.reconcileProducedFeedChanges({ created: false, completed_task_ids: [] }), false);
        assert.equal(api.reconcileProducedFeedChanges(null), false);
    });
});
