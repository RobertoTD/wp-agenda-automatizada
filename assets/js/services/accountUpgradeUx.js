/**
 * Account module — upgrade to Pro presentation (UX).
 *
 * Pure mapping; no DOM. Consumed by account module.js and Node tests.
 */
(function () {
    'use strict';

    var GENERIC_CHECKOUT_ERROR = 'No pudimos abrir el checkout de Pro. Intenta de nuevo.';
    var UPGRADE_UNAVAILABLE_ERROR =
        'Esta cuenta ya no está disponible para upgrade desde este flujo. Actualiza el estado de cuenta e intenta de nuevo.';

    /**
     * @param {object|null|undefined} status
     * @returns {boolean}
     */
    function shouldShowUpgradeCta(status) {
        return !!(status && status.upgrade_to_pro_available === true);
    }

    /**
     * @param {boolean} showUpgrade
     * @param {boolean} cardOpen
     * @returns {{ sectionVisible: boolean, ctaVisible: boolean, cardVisible: boolean }}
     */
    function buildUpgradeUiState(showUpgrade, cardOpen) {
        return {
            sectionVisible: showUpgrade,
            ctaVisible: showUpgrade && !cardOpen,
            cardVisible: showUpgrade && cardOpen
        };
    }

    /**
     * @param {{ code?: string, message?: string }|null|undefined} data
     * @returns {string}
     */
    function mapUpgradeCheckoutErrorToUi(data) {
        var code = data && data.code ? String(data.code) : '';
        if (code === 'upgrade_unavailable') {
            return UPGRADE_UNAVAILABLE_ERROR;
        }
        if (data && data.message) {
            return String(data.message);
        }
        return GENERIC_CHECKOUT_ERROR;
    }

    /**
     * @param {string|null|undefined} search
     * @returns {{ notice: string|null, className: string|null }}
     */
    function parseUpgradeReturnNotice(search) {
        if (typeof search !== 'string' || search === '') {
            return { notice: null, className: null };
        }

        var params;
        try {
            params = new URLSearchParams(search);
        } catch (_) {
            return { notice: null, className: null };
        }

        var upgrade = params.get('upgrade');
        if (upgrade === 'success') {
            return {
                notice: 'Procesamos tu pago. Actualizando estado de cuenta…',
                className: 'border-emerald-200 bg-emerald-50 text-emerald-800'
            };
        }
        if (upgrade === 'cancelled') {
            return {
                notice: 'El checkout fue cancelado. Tu cuenta sigue en Freemium.',
                className: 'border-gray-200 bg-gray-50 text-gray-700'
            };
        }

        return { notice: null, className: null };
    }

    /**
     * @param {string} url
     * @returns {boolean}
     */
    function isSafeStripeCheckoutUrl(url) {
        if (typeof url !== 'string' || url.trim() === '') {
            return false;
        }

        try {
            var parsed = new URL(url);
            return parsed.protocol === 'https:' && parsed.hostname === 'checkout.stripe.com';
        } catch (_) {
            return false;
        }
    }

    var api = {
        shouldShowUpgradeCta: shouldShowUpgradeCta,
        buildUpgradeUiState: buildUpgradeUiState,
        mapUpgradeCheckoutErrorToUi: mapUpgradeCheckoutErrorToUi,
        parseUpgradeReturnNotice: parseUpgradeReturnNotice,
        isSafeStripeCheckoutUrl: isSafeStripeCheckoutUrl,
        GENERIC_CHECKOUT_ERROR: GENERIC_CHECKOUT_ERROR,
        UPGRADE_UNAVAILABLE_ERROR: UPGRADE_UNAVAILABLE_ERROR
    };

    if (typeof window !== 'undefined') {
        window.AccountUpgradeUx = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
