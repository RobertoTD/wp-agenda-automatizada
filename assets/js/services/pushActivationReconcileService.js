/**
 * Push Activation Reconcile Service — readiness + aa_reconcile_push_activation_task.
 */
(function () {
    'use strict';

    var initReconcilePromise = null;

    function getGlobalRoot() {
        if (typeof window !== 'undefined') {
            return window;
        }

        if (typeof globalThis !== 'undefined') {
            return globalThis;
        }

        return {};
    }

    function getDeviceKeyService() {
        return getGlobalRoot().PushDeviceKeyService || null;
    }

    function getTasksService() {
        return getGlobalRoot().TasksService || null;
    }

    function getPushActivationService() {
        return getGlobalRoot().PwaPushActivationService || null;
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
     * @returns {Promise<'prepared'|'unprepared'>}
     */
    function resolveReadiness() {
        var permission = getNotificationPermission();

        if (permission === 'default' || permission === 'denied' || permission === '') {
            return Promise.resolve('unprepared');
        }

        if (permission !== 'granted') {
            return Promise.resolve('unprepared');
        }

        var activationService = getPushActivationService();

        if (!activationService || typeof activationService.activateFromGrantedPermission !== 'function') {
            return Promise.resolve('unprepared');
        }

        return Promise.resolve(activationService.activateFromGrantedPermission())
            .then(function (outcome) {
                if (outcome && outcome.registrationSucceeded === true) {
                    return 'prepared';
                }

                return 'unprepared';
            })
            .catch(function () {
                return 'unprepared';
            });
    }

    /**
     * @param {object|null|undefined} result
     * @returns {boolean}
     */
    function reconcileProducedFeedChanges(result) {
        if (!result || typeof result !== 'object') {
            return false;
        }

        if (result.created === true) {
            return true;
        }

        return Array.isArray(result.completed_task_ids) && result.completed_task_ids.length > 0;
    }

    /**
     * @param {string} deviceKey
     * @param {'prepared'|'unprepared'} readiness
     * @returns {Promise<object|null>}
     */
    function reconcilePushActivation(deviceKey, readiness) {
        var tasksService = getTasksService();

        if (!tasksService || typeof tasksService.reconcilePushActivationTask !== 'function') {
            return Promise.resolve(null);
        }

        return Promise.resolve(tasksService.reconcilePushActivationTask(deviceKey, readiness))
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
    function runFeedInitReconcile() {
        if (!isPushSupported()) {
            return Promise.resolve(null);
        }

        var deviceKeyService = getDeviceKeyService();

        if (!deviceKeyService || typeof deviceKeyService.getOrCreateDeviceKey !== 'function') {
            return Promise.resolve(null);
        }

        var deviceKey = deviceKeyService.getOrCreateDeviceKey();

        if (!deviceKey) {
            return Promise.resolve(null);
        }

        return resolveReadiness()
            .then(function (readiness) {
                return reconcilePushActivation(deviceKey, readiness);
            });
    }

    /**
     * Una sola evaluación por carga del módulo.
     *
     * @returns {Promise<object|null>}
     */
    function reconcileOnFeedInit() {
        if (initReconcilePromise) {
            return initReconcilePromise;
        }

        initReconcilePromise = runFeedInitReconcile();

        return initReconcilePromise;
    }

    var api = {
        isPushSupported: isPushSupported,
        resolveReadiness: resolveReadiness,
        reconcileProducedFeedChanges: reconcileProducedFeedChanges,
        reconcilePushActivation: reconcilePushActivation,
        reconcileOnFeedInit: reconcileOnFeedInit
    };

    getGlobalRoot().PushActivationReconcileService = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
        module.exports.__test = {
            resetState: function () {
                initReconcilePromise = null;
            },
            getInitReconcilePromise: function () {
                return initReconcilePromise;
            },
            hasPushManagerApi: hasPushManagerApi,
            getNotificationPermission: getNotificationPermission,
            reconcileProducedFeedChanges: reconcileProducedFeedChanges
        };
    }
})();
