/**
 * Push Activation Context Service — suscripción DEOIA + readiness del navegador.
 */
(function () {
    'use strict';

    var initContextPromise = null;
    var currentContext = {
        app_subscription_active: false,
        push_ready: false
    };

    function getGlobalRoot() {
        if (typeof window !== 'undefined') {
            return window;
        }

        if (typeof globalThis !== 'undefined') {
            return globalThis;
        }

        return {};
    }

    function getTasksService() {
        return getGlobalRoot().TasksService || null;
    }

    function getPushActivationService() {
        return getGlobalRoot().PwaPushActivationService || null;
    }

    function getAccountStatusService() {
        return getGlobalRoot().AccountStatusService || null;
    }

    function hasPushManagerApi(root) {
        if (typeof root.PushManager !== 'undefined') {
            return true;
        }

        if (typeof ServiceWorkerRegistration !== 'undefined') {
            return 'pushManager' in ServiceWorkerRegistration.prototype;
        }

        return false;
    }

    function isPushSupported() {
        var root = getGlobalRoot();

        return !!(
            root.Notification
            && root.navigator
            && root.navigator.serviceWorker
            && hasPushManagerApi(root)
        );
    }

    function getNotificationPermission() {
        var root = getGlobalRoot();

        if (!root.Notification) {
            return '';
        }

        try {
            return String(root.Notification.permission || '');
        } catch (err) {
            return '';
        }
    }

    /**
     * Comprobación pasiva: nunca crea una PushSubscription.
     *
     * @returns {Promise<boolean>}
     */
    function resolvePushReady() {
        var permission = getNotificationPermission();

        if (permission === 'default' || permission === 'denied' || permission === '') {
            return Promise.resolve(false);
        }

        if (permission !== 'granted') {
            return Promise.resolve(false);
        }

        var activationService = getPushActivationService();

        if (
            !activationService
            || typeof activationService.reconcileExistingSubscription !== 'function'
        ) {
            return Promise.resolve(false);
        }

        var recovery = typeof activationService.maybeAttemptAutomaticRecovery === 'function'
            ? Promise.resolve(activationService.maybeAttemptAutomaticRecovery())
            : Promise.resolve(null);

        return recovery
            .then(function (recoveryOutcome) {
                if (
                    recoveryOutcome
                    && typeof recoveryOutcome.registrationSucceeded === 'boolean'
                ) {
                    return recoveryOutcome.registrationSucceeded === true;
                }

                return activationService.reconcileExistingSubscription()
                    .then(function (outcome) {
                        return !!(outcome && outcome.registrationSucceeded === true);
                    });
            })
            .catch(function () {
                return false;
            });
    }

    /**
     * @returns {Promise<boolean>}
     */
    function resolveAppSubscriptionActive() {
        var accountService = getAccountStatusService();

        if (
            !accountService
            || typeof accountService.fetchStatus !== 'function'
            || typeof accountService.isAppSubscriptionActive !== 'function'
        ) {
            return Promise.resolve(false);
        }

        return Promise.resolve(accountService.fetchStatus())
            .then(function (payload) {
                return accountService.isAppSubscriptionActive(payload) === true;
            })
            .catch(function () {
                return false;
            });
    }

    /**
     * @returns {Promise<object|null>}
     */
    function ensurePushActivationTask() {
        var tasksService = getTasksService();

        if (!tasksService || typeof tasksService.ensurePushActivationTask !== 'function') {
            return Promise.resolve(null);
        }

        return Promise.resolve(tasksService.ensurePushActivationTask())
            .then(function (data) {
                return data && typeof data === 'object' ? data : null;
            })
            .catch(function () {
                return null;
            });
    }

    /**
     * @returns {Promise<object|null>}
     */
    function resolveInitialContext() {
        return resolveAppSubscriptionActive()
            .then(function (appSubscriptionActive) {
                if (!appSubscriptionActive) {
                    currentContext = {
                        app_subscription_active: false,
                        push_ready: false
                    };
                    return currentContext;
                }

                if (!isPushSupported()) {
                    return false;
                }

                return resolvePushReady();
            })
            .then(function (pushReadyOrContext) {
                if (pushReadyOrContext && typeof pushReadyOrContext === 'object') {
                    return pushReadyOrContext;
                }

                var pushReady = pushReadyOrContext === true;
                currentContext = {
                    app_subscription_active: true,
                    push_ready: pushReady
                };

                if (pushReady) {
                    return currentContext;
                }

                return ensurePushActivationTask()
                    .catch(function () {
                        return null;
                    })
                    .then(function () {
                        return currentContext;
                    });
            });
    }

    /**
     * Una sola consulta de Cuenta y evaluación Push por carga.
     * @returns {Promise<{app_subscription_active:boolean,push_ready:boolean}>}
     */
    function initializeFeedContext() {
        if (initContextPromise) {
            return initContextPromise;
        }

        initContextPromise = resolveInitialContext()
            .catch(function () {
                currentContext = {
                    app_subscription_active: false,
                    push_ready: false
                };
                return currentContext;
            });

        return initContextPromise;
    }

    function markPushReady(value) {
        currentContext = {
            app_subscription_active: currentContext.app_subscription_active === true,
            push_ready: currentContext.app_subscription_active === true && value === true
        };

        return getFeedContext();
    }

    function getFeedContext() {
        return {
            app_subscription_active: currentContext.app_subscription_active === true,
            push_ready: currentContext.push_ready === true
        };
    }

    var api = {
        isPushSupported: isPushSupported,
        resolveAppSubscriptionActive: resolveAppSubscriptionActive,
        resolvePushReady: resolvePushReady,
        ensurePushActivationTask: ensurePushActivationTask,
        initializeFeedContext: initializeFeedContext,
        markPushReady: markPushReady,
        getFeedContext: getFeedContext
    };

    getGlobalRoot().PushActivationReconcileService = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
        module.exports.__test = {
            resetState: function () {
                initContextPromise = null;
                currentContext = {
                    app_subscription_active: false,
                    push_ready: false
                };
            },
            getInitContextPromise: function () {
                return initContextPromise;
            },
            hasPushManagerApi: hasPushManagerApi,
            getNotificationPermission: getNotificationPermission
        };
    }
})();
