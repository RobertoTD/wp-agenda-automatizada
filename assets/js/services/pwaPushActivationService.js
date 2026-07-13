/**
 * PWA Push Activation Service — subscribe + register Web Push after permission granted (MC6).
 *
 * Depends on window.AA_PUSH_CONFIG and navigator.serviceWorker.
 * Does not request Notification permission.
 */
(function () {
    'use strict';

    var PENDING_KEY_PREFIX = 'aa_pwa_push_activation_pending_v1';
    var PENDING_VALUE = '1';

    var activationInFlight = null;
    var automaticRecoveryAttemptedThisLoad = false;

    function getGlobalRoot() {
        if (typeof window !== 'undefined') {
            return window;
        }

        if (typeof globalThis !== 'undefined') {
            return globalThis;
        }

        return {};
    }

    function warn(message, err) {
        var root = getGlobalRoot();

        if (root.console && typeof root.console.warn === 'function') {
            root.console.warn('[PwaPushActivationService] ' + message, err || '');
        }
    }

    function resolveBlogId() {
        var root = getGlobalRoot();
        var ctx = root.AA_ADMIN_CONTEXT;

        if (!ctx || ctx.blogId === null || ctx.blogId === undefined) {
            return '';
        }

        return String(ctx.blogId);
    }

    function buildPendingKey(blogId) {
        var bid = typeof blogId === 'string' ? blogId.trim() : '';

        if (!bid) {
            bid = resolveBlogId();
        }

        return PENDING_KEY_PREFIX + ':' + bid;
    }

    function hasNotificationApi() {
        var root = getGlobalRoot();

        return !!(root.Notification);
    }

    function getNotificationPermission() {
        if (!hasNotificationApi()) {
            return '';
        }

        try {
            return String(getGlobalRoot().Notification.permission || '');
        } catch (err) {
            return '';
        }
    }

    function markPending(blogId) {
        var key = buildPendingKey(blogId);

        if (!key || key === PENDING_KEY_PREFIX + ':') {
            return false;
        }

        try {
            getGlobalRoot().localStorage.setItem(key, PENDING_VALUE);
            return true;
        } catch (err) {
            return false;
        }
    }

    function clearPending(blogId) {
        var key = buildPendingKey(blogId);

        if (!key || key === PENDING_KEY_PREFIX + ':') {
            return false;
        }

        try {
            getGlobalRoot().localStorage.removeItem(key);
            return true;
        } catch (err) {
            return false;
        }
    }

    function hasPending(blogId) {
        var key = buildPendingKey(blogId);

        if (!key || key === PENDING_KEY_PREFIX + ':') {
            return false;
        }

        try {
            return getGlobalRoot().localStorage.getItem(key) === PENDING_VALUE;
        } catch (err) {
            return false;
        }
    }

    function getConfig() {
        var cfg = getGlobalRoot().AA_PUSH_CONFIG;

        if (!cfg || !cfg.ajaxUrl || !cfg.nonce || !cfg.configAction || !cfg.registerAction) {
            return null;
        }

        return cfg;
    }

    /**
     * @param {string} code
     * @param {string} [message]
     * @returns {Error}
     */
    function createError(code, message) {
        var err = new Error(message || code);
        err.code = code;
        return err;
    }

    /**
     * @param {string} value
     * @returns {Uint8Array}
     */
    function urlBase64ToUint8Array(value) {
        var input = typeof value === 'string' ? value.trim() : '';

        if (!input) {
            throw createError('push_config_unavailable', 'Empty VAPID public key');
        }

        var padding = '='.repeat((4 - input.length % 4) % 4);
        var base64 = (input + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        var output = new Uint8Array(raw.length);

        for (var i = 0; i < raw.length; i += 1) {
            output[i] = raw.charCodeAt(i);
        }

        return output;
    }

    /**
     * @param {ArrayBuffer} buffer
     * @returns {string}
     */
    function arrayBufferToBase64Url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        var i;

        for (i = 0; i < bytes.length; i += 1) {
            binary += String.fromCharCode(bytes[i]);
        }

        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    /**
     * @param {Response} response
     * @returns {Promise<{httpOk:boolean,status:number,json:object|null}>}
     */
    function parseWpEnvelope(response) {
        return response.json()
            .then(function (json) {
                return {
                    httpOk: response.ok,
                    status: response.status,
                    json: json && typeof json === 'object' ? json : null
                };
            })
            .catch(function () {
                return {
                    httpOk: false,
                    status: response.status,
                    json: null
                };
            });
    }

    /**
     * @returns {Promise<string>}
     */
    function fetchVapidPublicKey() {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(createError('push_config_unavailable', 'AA_PUSH_CONFIG not configured'));
        }

        var formData = new FormData();
        formData.append('action', cfg.configAction);
        formData.append('_wpnonce', cfg.nonce);

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(parseWpEnvelope)
            .then(function (envelope) {
                var json = envelope.json;
                var data = json && json.data && typeof json.data === 'object' ? json.data : {};

                if (json && json.success === true && typeof data.vapidPublicKey === 'string') {
                    var publicKey = data.vapidPublicKey.trim();

                    if (publicKey !== '') {
                        return publicKey;
                    }
                }

                if (json && json.success === false && data.error) {
                    throw createError(String(data.error), 'Push config unavailable');
                }

                throw createError('push_config_unavailable', 'Invalid push config response');
            });
    }

    /**
     * @param {PushSubscription} subscription
     * @returns {{endpoint:string,keys:{p256dh:string,auth:string}}}
     */
    function serializePushSubscription(subscription) {
        var endpoint = '';
        var p256dh = '';
        var auth = '';
        var json = null;

        if (subscription && typeof subscription.toJSON === 'function') {
            json = subscription.toJSON();
        }

        if (json && typeof json === 'object') {
            endpoint = typeof json.endpoint === 'string' ? json.endpoint.trim() : '';

            if (json.keys && typeof json.keys === 'object') {
                p256dh = typeof json.keys.p256dh === 'string' ? json.keys.p256dh.trim() : '';
                auth = typeof json.keys.auth === 'string' ? json.keys.auth.trim() : '';
            }
        }

        if ((p256dh === '' || auth === '') && subscription && typeof subscription.getKey === 'function') {
            if (p256dh === '') {
                var p256dhBuffer = subscription.getKey('p256dh');
                if (p256dhBuffer) {
                    p256dh = arrayBufferToBase64Url(p256dhBuffer);
                }
            }

            if (auth === '') {
                var authBuffer = subscription.getKey('auth');
                if (authBuffer) {
                    auth = arrayBufferToBase64Url(authBuffer);
                }
            }
        }

        if (endpoint === '') {
            endpoint = subscription && typeof subscription.endpoint === 'string'
                ? subscription.endpoint.trim()
                : '';
        }

        if (endpoint === '' || p256dh === '' || auth === '') {
            throw createError('invalid_subscription', 'Incomplete push subscription');
        }

        return {
            endpoint: endpoint,
            keys: {
                p256dh: p256dh,
                auth: auth
            }
        };
    }

    /**
     * @param {{endpoint:string,keys:{p256dh:string,auth:string}}} serialized
     * @returns {Promise<object>}
     */
    function registerPushSubscription(serialized) {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(createError('push_backend_unavailable', 'AA_PUSH_CONFIG not configured'));
        }

        var formData = new FormData();
        formData.append('action', cfg.registerAction);
        formData.append('_wpnonce', cfg.nonce);
        formData.append('endpoint', serialized.endpoint);
        formData.append('p256dh', serialized.keys.p256dh);
        formData.append('auth', serialized.keys.auth);

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(parseWpEnvelope)
            .then(function (envelope) {
                var json = envelope.json;
                var data = json && json.data && typeof json.data === 'object' ? json.data : {};

                if (json && json.success === true && data.ok) {
                    return data;
                }

                if (json && json.success === false && data.error) {
                    var code = String(data.error);

                    if (
                        code === 'invalid_subscription'
                        || code === 'no_installation_id'
                        || code === 'endpoint_conflict'
                        || code === 'push_backend_unavailable'
                    ) {
                        throw createError(code, 'Push registration failed');
                    }

                    throw createError(code, 'Push registration failed');
                }

                throw createError('push_backend_unavailable', 'Invalid push registration response');
            });
    }

    /**
     * @param {object} data
     * @returns {{registrationSucceeded:boolean,completed:boolean,status:string}}
     */
    function interpretRegistrationResult(data) {
        var registrationSucceeded = !!(data && data.ok === true);
        var firstTest = data && data.first_test && typeof data.first_test === 'object'
            ? data.first_test
            : {};
        var firstTestStatus = typeof firstTest.status === 'string' ? firstTest.status : '';

        return {
            registrationSucceeded: registrationSucceeded,
            completed: registrationSucceeded,
            status: firstTestStatus || (registrationSucceeded ? 'registered' : 'unknown')
        };
    }

    /**
     * @param {Uint8Array} applicationServerKey
     * @returns {Promise<PushSubscription>}
     */
    function ensurePushSubscription(applicationServerKey) {
        var root = getGlobalRoot();

        if (!root.navigator || !root.navigator.serviceWorker || !root.navigator.serviceWorker.ready) {
            return Promise.reject(createError('push_backend_unavailable', 'Service worker unavailable'));
        }

        return root.navigator.serviceWorker.ready.then(function (registration) {
            var pushManager = registration && registration.pushManager;

            if (!pushManager || typeof pushManager.getSubscription !== 'function') {
                return Promise.reject(createError('push_backend_unavailable', 'PushManager unavailable'));
            }

            return pushManager.getSubscription().then(function (existing) {
                if (existing) {
                    return existing;
                }

                if (typeof pushManager.subscribe !== 'function') {
                    return Promise.reject(createError('push_backend_unavailable', 'PushManager.subscribe unavailable'));
                }

                return pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                });
            });
        });
    }

    /**
     * Lee la suscripción local sin crear una nueva.
     *
     * @returns {Promise<PushSubscription|null>}
     */
    function getExistingPushSubscription() {
        var root = getGlobalRoot();

        if (!root.navigator || !root.navigator.serviceWorker || !root.navigator.serviceWorker.ready) {
            return Promise.resolve(null);
        }

        return root.navigator.serviceWorker.ready
            .then(function (registration) {
                var pushManager = registration && registration.pushManager;

                if (!pushManager || typeof pushManager.getSubscription !== 'function') {
                    return null;
                }

                return pushManager.getSubscription();
            })
            .then(function (subscription) {
                return subscription || null;
            })
            .catch(function () {
                return null;
            });
    }

    /**
     * @returns {Promise<{completed:boolean,status:string}>}
     */
    function runActivation() {
        return fetchVapidPublicKey()
            .then(function (vapidPublicKey) {
                var applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);

                return ensurePushSubscription(applicationServerKey);
            })
            .then(function (subscription) {
                var serialized = serializePushSubscription(subscription);

                return registerPushSubscription(serialized);
            })
            .then(function (data) {
                var outcome = interpretRegistrationResult(data);

                if (outcome.registrationSucceeded) {
                    clearPending();
                }

                return outcome;
            });
    }

    /**
     * @returns {Promise<{completed:boolean,status:string}>}
     */
    function activateFromGrantedPermission() {
        if (getNotificationPermission() !== 'granted') {
            return Promise.reject(createError('permission_not_granted', 'Notification permission not granted'));
        }

        if (activationInFlight) {
            return activationInFlight;
        }

        markPending();

        activationInFlight = runActivation()
            .finally(function () {
                activationInFlight = null;
            });

        return activationInFlight;
    }

    /**
     * Re-registra una suscripción local existente. Nunca llama subscribe().
     *
     * @returns {Promise<{registrationSucceeded:boolean,completed:boolean,status:string}>}
     */
    function reconcileExistingSubscription() {
        if (getNotificationPermission() !== 'granted') {
            return Promise.resolve({
                registrationSucceeded: false,
                completed: false,
                status: 'permission_not_granted'
            });
        }

        if (activationInFlight) {
            return activationInFlight;
        }

        activationInFlight = getExistingPushSubscription()
            .then(function (subscription) {
                if (!subscription) {
                    return {
                        registrationSucceeded: false,
                        completed: false,
                        status: 'subscription_missing'
                    };
                }

                markPending();

                return registerPushSubscription(serializePushSubscription(subscription))
                    .then(function (data) {
                        var outcome = interpretRegistrationResult(data);

                        if (outcome.registrationSucceeded) {
                            clearPending();
                        }

                        return outcome;
                    });
            })
            .finally(function () {
                activationInFlight = null;
            });

        return activationInFlight;
    }

    function maybeAttemptAutomaticRecovery() {
        if (automaticRecoveryAttemptedThisLoad) {
            return Promise.resolve(null);
        }

        automaticRecoveryAttemptedThisLoad = true;

        if (!hasNotificationApi()) {
            return Promise.resolve(null);
        }

        if (getNotificationPermission() !== 'granted') {
            return Promise.resolve(null);
        }

        if (!hasPending()) {
            return Promise.resolve(null);
        }

        return activateFromGrantedPermission()
            .catch(function (err) {
                warn('automatic recovery failed:', err);
                return {
                    registrationSucceeded: false,
                    completed: false,
                    status: 'recovery_failed'
                };
            });
    }

    var api = {
        activateFromGrantedPermission: activateFromGrantedPermission,
        reconcileExistingSubscription: reconcileExistingSubscription,
        maybeAttemptAutomaticRecovery: maybeAttemptAutomaticRecovery
    };

    getGlobalRoot().PwaPushActivationService = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
        module.exports.__test = {
            urlBase64ToUint8Array: urlBase64ToUint8Array,
            serializePushSubscription: serializePushSubscription,
            interpretRegistrationResult: interpretRegistrationResult,
            getExistingPushSubscription: getExistingPushSubscription,
            hasPending: hasPending,
            markPending: markPending,
            clearPending: clearPending,
            buildPendingKey: buildPendingKey,
            resetState: function () {
                activationInFlight = null;
                automaticRecoveryAttemptedThisLoad = false;
            },
            setAutomaticRecoveryAttempted: function (value) {
                automaticRecoveryAttemptedThisLoad = !!value;
            },
            isActivationInFlight: function () {
                return activationInFlight !== null;
            },
            getActivationInFlightPromise: function () {
                return activationInFlight;
            }
        };
    }
})();
