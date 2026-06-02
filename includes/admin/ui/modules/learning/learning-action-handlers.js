/**
 * Learning Action Handlers - Registro genérico de acciones primarias.
 *
 * Los handlers concretos viven detrás de claves estables declaradas por el
 * contrato `action.handler`; este registro solo valida disponibilidad y ejecuta.
 */

(function () {
    'use strict';

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

    window.LearningActionHandlers = {
        register: register,
        get: get,
        isAvailable: isAvailable,
        run: run,
        onAvailabilityChange: onAvailabilityChange
    };

    // ─── Handler real: pwa.install ───────────────────────────────
    // deferredPrompt vive solo en este closure, nunca expuesto en el objeto window.
    (function registerPwaInstallHandler() {
        var deferredPrompt = null;
        var installed = false;

        function isStandalone() {
            try {
                if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
                    return true;
                }
            } catch (err) {
                // matchMedia puede no existir en entornos muy antiguos; tratamos como no standalone.
            }

            return window.navigator && window.navigator.standalone === true;
        }

        function canInstallNow() {
            return !!deferredPrompt && !installed && !isStandalone();
        }

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredPrompt = event;
            notifyAvailabilityChanged();
        });

        window.addEventListener('appinstalled', function () {
            deferredPrompt = null;
            installed = true;
            notifyAvailabilityChanged();
        });

        register('pwa.install', {
            isAvailable: function () {
                return canInstallNow();
            },
            run: function () {
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

                        // 3C completará la recomendación; aquí solo reportamos el resultado.
                        return { completed: false, outcome: outcome };
                    })
                    .catch(function (err) {
                        notifyAvailabilityChanged();
                        throw err;
                    });
            }
        });
    })();
})();
