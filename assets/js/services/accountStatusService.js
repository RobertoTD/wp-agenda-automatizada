/**
 * Account Status Service — lectura compartida del payload comercial existente.
 *
 * Reutiliza aa_get_account_status y memoriza una sola promesa por carga.
 */
(function () {
    'use strict';

    var root = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : {});
    var statusPromise = null;

    function getConfig() {
        return root.AA_ACCOUNT_STATUS_DATA || root.AA_ACCOUNT_DATA || null;
    }

    /**
     * @returns {Promise<object>}
     */
    function fetchStatus() {
        if (statusPromise) {
            return statusPromise;
        }

        var config = getConfig();

        if (!config || !config.ajaxUrl || !config.nonce) {
            statusPromise = Promise.reject(new Error('AA_ACCOUNT_STATUS_DATA no configurado'));
            return statusPromise;
        }

        var action = config.action || 'aa_get_account_status';
        var url = config.ajaxUrl
            + '?action=' + encodeURIComponent(action)
            + '&_wpnonce=' + encodeURIComponent(config.nonce);

        statusPromise = fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ' fetching account status');
                }

                return response.json();
            })
            .then(function (json) {
                if (!json || json.success !== true || !json.data) {
                    var message = json && json.data && json.data.message
                        ? String(json.data.message)
                        : 'Invalid account status response';
                    throw new Error(message);
                }

                return json.data;
            });

        return statusPromise;
    }

    /**
     * @param {object|null|undefined} payload
     * @returns {boolean}
     */
    function isAppSubscriptionActive(payload) {
        var accountStatus = payload && payload.account_status;

        return !!(
            accountStatus
            && typeof accountStatus === 'object'
            && accountStatus.billing_state === 'active'
        );
    }

    function reset() {
        statusPromise = null;
    }

    var api = {
        fetchStatus: fetchStatus,
        isAppSubscriptionActive: isAppSubscriptionActive
    };

    root.AccountStatusService = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
        module.exports.__test = {
            reset: reset,
            getStatusPromise: function () {
                return statusPromise;
            }
        };
    }
})();
