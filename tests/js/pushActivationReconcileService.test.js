'use strict';

const assert = require('node:assert/strict');
const { describe, it, afterEach } = require('node:test');
const path = require('node:path');

const servicePath = path.join(__dirname, '../../assets/js/services/pushActivationReconcileService.js');

function loadService(options) {
    options = options || {};
    var ensureCalls = 0;
    var pushCalls = 0;
    var accountCalls = 0;

    globalThis.Notification = options.notification === false
        ? undefined
        : { permission: options.permission || 'default' };
    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        writable: true,
        value: options.navigator === false ? undefined : { serviceWorker: {} }
    });
    globalThis.ServiceWorkerRegistration = function ServiceWorkerRegistration() {};
    globalThis.ServiceWorkerRegistration.prototype.pushManager = {};

    globalThis.AccountStatusService = {
        fetchStatus: function () {
            accountCalls += 1;
            if (options.accountError) {
                return Promise.reject(new Error('account down'));
            }
            return Promise.resolve({
                account_status: {
                    billing_state: options.billingState || 'active',
                    effective_access_tier: options.effectiveTier || 'freemium'
                }
            });
        },
        isAppSubscriptionActive: function (payload) {
            return payload.account_status.billing_state === 'active';
        }
    };

    globalThis.PwaPushActivationService = {
        maybeAttemptAutomaticRecovery: function () {
            pushCalls += 1;
            return Promise.resolve(options.recoveryOutcome || null);
        },
        reconcileExistingSubscription: function () {
            pushCalls += 1;
            if (options.registrationError) {
                return Promise.reject(new Error('push down'));
            }
            return Promise.resolve({
                registrationSucceeded: options.registrationSucceeded === true
            });
        }
    };

    globalThis.TasksService = {
        ensurePushActivationTask: function () {
            ensureCalls += 1;
            return Promise.resolve({ created: ensureCalls === 1 });
        }
    };

    delete require.cache[servicePath];
    var service = require(servicePath);

    return {
        service: service,
        get ensureCalls() {
            return ensureCalls;
        },
        get pushCalls() {
            return pushCalls;
        },
        get accountCalls() {
            return accountCalls;
        }
    };
}

describe('PushActivationReconcileService', () => {
    afterEach(() => {
        delete globalThis.PushActivationReconcileService;
        delete globalThis.AccountStatusService;
        delete globalThis.PwaPushActivationService;
        delete globalThis.TasksService;
        delete globalThis.Notification;
        delete globalThis.navigator;
        delete globalThis.ServiceWorkerRegistration;
        delete require.cache[servicePath];
    });

    it('suscripción inactiva oculta y no toca Push ni ensure', async () => {
        var loaded = loadService({ billingState: 'missing', permission: 'granted' });
        var context = await loaded.service.initializeFeedContext();

        assert.deepEqual(context, {
            app_subscription_active: false,
            push_ready: false
        });
        assert.equal(loaded.pushCalls, 0);
        assert.equal(loaded.ensureCalls, 0);
    });

    it('fallo account-status cierra en false sin pipeline Push', async () => {
        var loaded = loadService({ accountError: true, permission: 'granted' });
        var context = await loaded.service.initializeFeedContext();

        assert.equal(context.app_subscription_active, false);
        assert.equal(context.push_ready, false);
        assert.equal(loaded.pushCalls, 0);
    });

    it('permission default y denied producen push_ready false y ensure', async () => {
        for (const permission of ['default', 'denied']) {
            var loaded = loadService({ permission: permission });
            var context = await loaded.service.initializeFeedContext();

            assert.equal(context.app_subscription_active, true);
            assert.equal(context.push_ready, false);
            assert.equal(loaded.pushCalls, 0);
            assert.equal(loaded.ensureCalls, 1);
            loaded.service.__test.resetState();
        }
    });

    it('granted con registro existente confirmado produce push_ready true', async () => {
        var loaded = loadService({
            permission: 'granted',
            registrationSucceeded: true
        });
        var context = await loaded.service.initializeFeedContext();

        assert.equal(context.push_ready, true);
        assert.equal(loaded.ensureCalls, 0);
    });

    it('backend Push caído produce false y asegura tarea', async () => {
        var loaded = loadService({
            permission: 'granted',
            registrationError: true
        });
        var context = await loaded.service.initializeFeedContext();

        assert.equal(context.push_ready, false);
        assert.equal(loaded.ensureCalls, 1);
    });

    it('memoriza una sola inicialización por carga', async () => {
        var loaded = loadService({ permission: 'default' });
        var first = loaded.service.initializeFeedContext();
        var second = loaded.service.initializeFeedContext();

        assert.strictEqual(first, second);
        await first;
        assert.equal(loaded.accountCalls, 1);
        assert.equal(loaded.ensureCalls, 1);
    });

    it('click exitoso actualiza el contexto local', async () => {
        var loaded = loadService({ permission: 'default' });
        await loaded.service.initializeFeedContext();

        loaded.service.markPushReady(true);

        assert.deepEqual(loaded.service.getFeedContext(), {
            app_subscription_active: true,
            push_ready: true
        });
    });
});
