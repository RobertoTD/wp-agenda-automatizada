/**
 * Onboarding Status Service — read-only fetch of initial activation state.
 *
 * Depends on window.AA_ONBOARDING_DATA (ajaxUrl, action, nonce).
 * Returns the use case payload from aa_get_onboarding_status; no business rules here.
 */
(function () {
    'use strict';

    function getConfig() {
        return window.AA_ONBOARDING_DATA || null;
    }

    /**
     * @returns {Promise<object>}
     */
    function fetchStatus() {
        var config = getConfig();

        if (!config || !config.ajaxUrl || !config.action || !config.nonce) {
            var msg = '[OnboardingStatusService] AA_ONBOARDING_DATA is missing ajaxUrl, action, or nonce';
            console.warn(msg);
            return Promise.reject(new Error(msg));
        }

        var url = config.ajaxUrl
            + '?action=' + encodeURIComponent(config.action)
            + '&_wpnonce=' + encodeURIComponent(config.nonce);

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ' fetching onboarding status');
                }
                return response.json();
            })
            .then(function (json) {
                if (json && json.success === true && json.data) {
                    return json.data;
                }

                var message = json && json.data && json.data.message
                    ? String(json.data.message)
                    : 'Invalid onboarding status response';

                console.warn('[OnboardingStatusService]', message, json);
                return Promise.reject(new Error(message));
            })
            .catch(function (err) {
                console.error('[OnboardingStatusService] Fetch failed:', err);
                return Promise.reject(err);
            });
    }

    window.OnboardingStatusService = {
        fetchStatus: fetchStatus
    };
})();
