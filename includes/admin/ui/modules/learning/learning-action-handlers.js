/**
 * Learning Action Handlers - Registro genérico de acciones primarias.
 *
 * Los handlers concretos viven detrás de claves estables declaradas por el
 * contrato `action.handler`; este registro solo valida disponibilidad y ejecuta.
 */

(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var handlers = {};
    var availabilityListeners = [];

    function normalizeKey(handlerKey) {
        return typeof handlerKey === 'string' ? handlerKey.trim() : '';
    }

    function getHandlerFromAction(action) {
        if (!action || typeof action !== 'object') {
            return null;
        }

        return get(action.handler || '');
    }

    function notifyAvailabilityChanged() {
        availabilityListeners.slice().forEach(function (callback) {
            try {
                callback();
            } catch (err) {
                console.warn('[LearningActionHandlers] availability listener failed:', err);
            }
        });
    }

    function register(handlerKey, handlerObject) {
        var key = normalizeKey(handlerKey);

        if (!key || !handlerObject || typeof handlerObject !== 'object') {
            return false;
        }

        handlers[key] = handlerObject;
        notifyAvailabilityChanged();
        return true;
    }

    function get(handlerKey) {
        var key = normalizeKey(handlerKey);

        if (!key || !Object.prototype.hasOwnProperty.call(handlers, key)) {
            return null;
        }

        return handlers[key];
    }

    function isAvailable(action, item) {
        var handler = getHandlerFromAction(action);

        if (!handler || typeof handler.run !== 'function') {
            return false;
        }

        if (typeof handler.isAvailable !== 'function') {
            return true;
        }

        try {
            return handler.isAvailable(action, item) === true;
        } catch (err) {
            console.warn('[LearningActionHandlers] availability check failed:', err);
            return false;
        }
    }

    /**
     * Visibilidad runtime de la card completa (conservadora).
     * No usar isAvailable aquí: ese método solo controla el botón primario.
     */
    function shouldShowRecommendation(action, item) {
        if (!action || typeof action !== 'object' || action.type !== 'handler') {
            return true;
        }

        var handler = getHandlerFromAction(action);

        if (!handler) {
            return true;
        }

        if (typeof handler.shouldHideRecommendation !== 'function') {
            return true;
        }

        try {
            return handler.shouldHideRecommendation(action, item) !== true;
        } catch (err) {
            console.warn('[LearningActionHandlers] recommendation visibility check failed:', err);
            return true;
        }
    }

    function run(action, item, ctx) {
        var handler = getHandlerFromAction(action);

        if (!handler || typeof handler.run !== 'function') {
            return Promise.reject(new Error('Handler de recomendación no disponible.'));
        }

        try {
            return Promise.resolve(handler.run(action, item, ctx || {}));
        } catch (err) {
            return Promise.reject(err);
        }
    }

    function onAvailabilityChange(callback) {
        if (typeof callback !== 'function') {
            return function () {};
        }

        availabilityListeners.push(callback);

        return function () {
            availabilityListeners = availabilityListeners.filter(function (registeredCallback) {
                return registeredCallback !== callback;
            });
        };
    }

    globalRoot.LearningActionHandlers = {
        register: register,
        get: get,
        isAvailable: isAvailable,
        shouldShowRecommendation: shouldShowRecommendation,
        run: run,
        onAvailabilityChange: onAvailabilityChange
    };

    // ─── Handler real: pwa.install ───────────────────────────────
    // deferredPrompt vive solo en este closure, nunca expuesto en el objeto window.
    (function registerPwaInstallHandler() {
        var INSTALL_ORIGIN_KEY = 'install_pwa';
        var deferredPrompt = null;
        var installed = false;
        var pendingInstallTaskId = null;
        var completionInFlight = null;
        var standaloneReconciledForLoadCycle = false;

        /**
         * @param {unknown} value
         * @returns {string}
         */
        function asString(value) {
            return value === null || value === undefined ? '' : String(value);
        }

        /**
         * @param {object|null|undefined} item
         * @returns {boolean}
         */
        function isPendingInstallItem(item) {
            if (!item || typeof item !== 'object') {
                return false;
            }

            if (asString(item.origin_key).trim() !== INSTALL_ORIGIN_KEY) {
                return false;
            }

            if (asString(item.status).trim().toLowerCase() === 'done') {
                return false;
            }

            var state = item.state && typeof item.state === 'object' ? item.state : {};

            return state.completed !== true;
        }

        /**
         * @param {object|null|undefined} item
         * @returns {string|null}
         */
        function resolveNumericAgendaAppTaskId(item) {
            if (!isPendingInstallItem(item)) {
                return null;
            }

            var taskId = asString(item.id).trim();

            if (!/^\d+$/.test(taskId)) {
                return null;
            }

            if (asString(item.source_category).trim().toLowerCase() !== 'agenda_app') {
                return null;
            }

            return taskId;
        }

        /**
         * @param {object|null|undefined} item
         */
        function capturePendingInstallTaskIdFromItem(item) {
            var resolved = resolveNumericAgendaAppTaskId(item);

            if (resolved !== null) {
                pendingInstallTaskId = resolved;
            }
        }

        /**
         * @param {object|null|undefined} payload
         * @returns {object|null}
         */
        function findPendingInstallItemInPayload(payload) {
            if (!payload || typeof payload !== 'object') {
                return null;
            }

            var lists = Array.isArray(payload.lists) ? payload.lists : [];
            var preferred = null;
            var fallback = null;
            var listIndex = 0;
            var bucketIndex = 0;
            var itemIndex = 0;

            for (listIndex = 0; listIndex < lists.length; listIndex += 1) {
                var list = lists[listIndex];

                if (!list || typeof list !== 'object') {
                    continue;
                }

                var buckets = Array.isArray(list.buckets) ? list.buckets : [];

                for (bucketIndex = 0; bucketIndex < buckets.length; bucketIndex += 1) {
                    var bucket = buckets[bucketIndex];

                    if (!bucket || typeof bucket !== 'object') {
                        continue;
                    }

                    var items = Array.isArray(bucket.items) ? bucket.items : [];

                    for (itemIndex = 0; itemIndex < items.length; itemIndex += 1) {
                        var item = items[itemIndex];

                        if (!isPendingInstallItem(item)) {
                            continue;
                        }

                        if (asString(item.source).trim().toLowerCase() === 'system') {
                            preferred = item;
                        } else if (!fallback) {
                            fallback = item;
                        }
                    }
                }
            }

            return preferred || fallback;
        }

        function refreshPendingInstallTaskFromPayload(payload) {
            var item = findPendingInstallItemInPayload(payload);
            var resolved = resolveNumericAgendaAppTaskId(item);

            pendingInstallTaskId = resolved !== null ? resolved : null;
        }

        function beginInstallTaskFeedLoadCycle() {
            standaloneReconciledForLoadCycle = false;
        }

        /**
         * @returns {Promise<void>}
         */
        function syncSurfacesAfterPwaInstallComplete() {
            var feedApi = globalRoot.AAExecutableUserListsVisibleFeed;

            if (feedApi && typeof feedApi.reload === 'function') {
                return Promise.resolve(feedApi.reload());
            }

            return Promise.resolve();
        }

        /**
         * @returns {Promise<void>}
         */
        function completePendingInstallTaskIfNeeded() {
            if (pendingInstallTaskId === null || pendingInstallTaskId === '') {
                return Promise.resolve();
            }

            if (completionInFlight) {
                return completionInFlight;
            }

            var tasksService = globalRoot.TasksService;

            if (!tasksService || typeof tasksService.changeTaskStatus !== 'function') {
                return Promise.resolve();
            }

            var taskId = pendingInstallTaskId;

            completionInFlight = Promise.resolve(tasksService.changeTaskStatus(taskId, 'done'))
                .then(function () {
                    pendingInstallTaskId = null;

                    return syncSurfacesAfterPwaInstallComplete();
                })
                .catch(function () {
                    // Conserva pendingInstallTaskId para permitir reintento manual o posterior.
                })
                .finally(function () {
                    completionInFlight = null;
                });

            return completionInFlight;
        }

        /**
         * @returns {Promise<void>}
         */
        function reconcileStandaloneInstallTaskIfNeeded() {
            if (!isStandalone()) {
                return Promise.resolve();
            }

            if (standaloneReconciledForLoadCycle) {
                return Promise.resolve();
            }

            if (pendingInstallTaskId === null || pendingInstallTaskId === '') {
                return Promise.resolve();
            }

            standaloneReconciledForLoadCycle = true;

            return completePendingInstallTaskIfNeeded();
        }

        function isStandalone() {
            try {
                if (globalRoot.matchMedia && globalRoot.matchMedia('(display-mode: standalone)').matches) {
                    return true;
                }
            } catch (err) {
                // matchMedia puede no existir en entornos muy antiguos; tratamos como no standalone.
            }

            return globalRoot.navigator && globalRoot.navigator.standalone === true;
        }

        function canInstallNow() {
            return !!deferredPrompt && !installed && !isStandalone();
        }

        if (typeof globalRoot.addEventListener === 'function') {
            globalRoot.addEventListener('beforeinstallprompt', function (event) {
                event.preventDefault();
                deferredPrompt = event;
                notifyAvailabilityChanged();
            });

            globalRoot.addEventListener('appinstalled', function () {
                deferredPrompt = null;
                installed = true;
                notifyAvailabilityChanged();
                completePendingInstallTaskIfNeeded();
            });
        }

        register('pwa.install', {
            shouldHideRecommendation: function () {
                return isStandalone() || installed;
            },
            isAvailable: function () {
                return canInstallNow();
            },
            run: function (action, item, ctx) {
                capturePendingInstallTaskIdFromItem(item || (ctx && ctx.item));

                if (!canInstallNow()) {
                    return Promise.resolve({ completed: false, outcome: 'unavailable' });
                }

                var promptEvent = deferredPrompt;
                // El prompt solo puede usarse una vez; lo limpiamos de inmediato.
                deferredPrompt = null;

                var promptResult;

                try {
                    promptResult = promptEvent.prompt();
                } catch (err) {
                    notifyAvailabilityChanged();
                    return Promise.reject(err);
                }

                return Promise.resolve(promptResult)
                    .then(function () {
                        return promptEvent.userChoice
                            ? Promise.resolve(promptEvent.userChoice)
                            : Promise.resolve(null);
                    })
                    .then(function (choice) {
                        notifyAvailabilityChanged();

                        var outcome = choice && choice.outcome ? choice.outcome : 'unknown';

                        // El completado persistido lo resuelve appinstalled/standalone vía TasksService.
                        return { completed: false, outcome: outcome };
                    })
                    .catch(function (err) {
                        notifyAvailabilityChanged();
                        throw err;
                    });
            }
        });

        globalRoot.LearningActionHandlers.beginInstallTaskFeedLoadCycle = beginInstallTaskFeedLoadCycle;
        globalRoot.LearningActionHandlers.refreshPendingInstallTaskFromPayload = refreshPendingInstallTaskFromPayload;
        globalRoot.LearningActionHandlers.reconcileStandaloneInstallTaskIfNeeded = reconcileStandaloneInstallTaskIfNeeded;
    })();

    // ─── Handler real: appointment.confirm (MC5) ─────────────────
    (function registerAppointmentConfirmHandler() {
        var ORIGIN_KEY_PREFIX = 'appointment_confirmation:';

        /**
         * @param {object|null|undefined} item
         * @returns {number|null}
         */
        function resolveReservationId(item) {
            if (!item || typeof item !== 'object') {
                return null;
            }

            var originKey = typeof item.origin_key === 'string' ? item.origin_key.trim() : '';

            if (originKey.indexOf(ORIGIN_KEY_PREFIX) !== 0) {
                return null;
            }

            var rawId = originKey.slice(ORIGIN_KEY_PREFIX.length);

            if (rawId === '' || !/^\d+$/.test(rawId)) {
                return null;
            }

            var parsed = parseInt(rawId, 10);

            if (!Number.isFinite(parsed) || parsed < 1) {
                return null;
            }

            return parsed;
        }

        function hasConfirmService() {
            return !!(globalRoot.ConfirmService && typeof globalRoot.ConfirmService.confirmar === 'function');
        }

        function hasConfirmNonce() {
            return !!(
                globalRoot.aa_asistant_vars
                && typeof globalRoot.aa_asistant_vars.nonce_confirmar === 'string'
                && globalRoot.aa_asistant_vars.nonce_confirmar.trim() !== ''
            );
        }

        /**
         * Misma semántica de respuesta que AdminConfirmController.onConfirmar.
         *
         * @param {object} data Respuesta JSON de ConfirmService.confirmar (wp_send_json_*).
         * @returns {{reload: true}}
         */
        function handleConfirmResponse(data) {
            if (!data || data.success !== true) {
                var message = 'No se pudo confirmar la cita.';

                if (data && data.data && typeof data.data.message === 'string' && data.data.message.trim() !== '') {
                    message = data.data.message;
                }

                throw new Error(message);
            }

            var confirmController = globalRoot.AdminConfirmController;

            if (confirmController) {
                if (typeof confirmController.showLocalActionSuccessNotification === 'function') {
                    confirmController.showLocalActionSuccessNotification('appointment_confirmed_local');
                }

                if (
                    typeof confirmController.isConfirmAutomationIncomplete === 'function'
                    && confirmController.isConfirmAutomationIncomplete(data)
                ) {
                    if (typeof confirmController.showAutomationConnectionFailedNotification === 'function') {
                        confirmController.showAutomationConnectionFailedNotification('confirm');
                    }
                } else if (typeof confirmController.showConfirmResultNotification === 'function') {
                    confirmController.showConfirmResultNotification(data);
                }
            }

            var documentRef = globalRoot.document;

            if (documentRef && typeof documentRef.dispatchEvent === 'function') {
                documentRef.dispatchEvent(new CustomEvent('aa-cita-action-completed'));
            }

            return { reload: true };
        }

        register('appointment.confirm', {
            isAvailable: function (action, item) {
                if (!action || action.handler !== 'appointment.confirm') {
                    return false;
                }

                return resolveReservationId(item) !== null
                    && hasConfirmService()
                    && hasConfirmNonce();
            },
            run: function (action, item) {
                var reservationId = resolveReservationId(item);

                if (reservationId === null) {
                    return Promise.reject(new Error('No se pudo resolver la cita a confirmar.'));
                }

                if (!hasConfirmService()) {
                    return Promise.reject(new Error('Servicio de confirmación no disponible.'));
                }

                return globalRoot.ConfirmService.confirmar(reservationId)
                    .then(function (data) {
                        return handleConfirmResponse(data);
                    });
            }
        });

        globalRoot.LearningActionHandlers.resolveAppointmentConfirmationReservationId = resolveReservationId;
    })();
})();
