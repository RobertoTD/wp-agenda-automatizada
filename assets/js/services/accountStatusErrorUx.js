/**
 * Account module — human-facing error copy and CTA mapping (UX).
 *
 * Pure mapping; no DOM. Consumed by account module.js and Node tests.
 */
(function () {
    'use strict';

    var REASON_MISSING_CLIENT_SECRET = 'missing_client_secret';

    var MSG_REQUIRES_LINK =
        'Esta agenda aún no está vinculada a una cuenta DEOIA. Vincula tu cuenta con Google para activar automatizaciones, estado de suscripción y servicios disponibles en tu plan.';

    var MSG_TEMPORARY_UNAVAILABLE =
        'No pudimos consultar el estado de cuenta en este momento. Intenta más tarde.';

    var MSG_INCOMPLETE =
        'No pudimos mostrar el estado de cuenta completo. Intenta más tarde.';

    var GOOGLE_CALENDAR_SETUP_URL_FALLBACK =
        'admin-post.php?action=aa_iframe_content&module=settings&setup_focus=google_calendar#aa-google-calendar-root';

    var LINK_ACCOUNT_LABEL = 'Vincular cuenta';

    /**
     * @returns {string}
     */
    function buildGoogleCalendarSetupUrl() {
        if (typeof window !== 'undefined' && window.location && window.location.href) {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('action', 'aa_iframe_content');
                url.searchParams.set('module', 'settings');
                url.searchParams.set('setup_focus', 'google_calendar');
                url.hash = '#aa-google-calendar-root';
                return url.toString();
            } catch (_err) {
                // fall through
            }
        }
        return GOOGLE_CALENDAR_SETUP_URL_FALLBACK;
    }

    /**
     * @param {string|null} code
     * @param {{ reason?: string }} context
     * @returns {boolean}
     */
    function isRequiresLinkCode(code, context) {
        if (!code) {
            return false;
        }
        if (code === 'account_client_not_found') {
            return true;
        }
        if (code === 'account_backend_not_configured') {
            return context && context.reason === REASON_MISSING_CLIENT_SECRET;
        }
        return false;
    }

    /**
     * @param {unknown} actions
     * @returns {Array<{label:string,url:string}>}
     */
    function normalizeActions(actions) {
        if (!Array.isArray(actions)) {
            return [];
        }
        var out = [];
        for (var i = 0; i < actions.length; i++) {
            var action = actions[i];
            if (!action || typeof action !== 'object') {
                continue;
            }
            var label = action.label == null ? '' : String(action.label).trim();
            var url = action.url == null ? '' : String(action.url).trim();
            if (!label || !url) {
                continue;
            }
            out.push({ label: label, url: url });
        }
        return out;
    }

    /**
     * @param {string|null} code
     * @param {{ reason?: string }} context
     * @returns {Array<{label:string,url:string}>}
     */
    function actionsForCode(code, context) {
        if (!isRequiresLinkCode(code, context)) {
            return [];
        }
        return [{
            label: LINK_ACCOUNT_LABEL,
            url: buildGoogleCalendarSetupUrl()
        }];
    }

    /**
     * @param {string|null} code
     * @param {{ reason?: string }} context
     * @returns {string}
     */
    function textForCode(code, context) {
        if (isRequiresLinkCode(code, context)) {
            return MSG_REQUIRES_LINK;
        }
        if (code === 'account_backend_invalid_response') {
            return MSG_INCOMPLETE;
        }
        return MSG_TEMPORARY_UNAVAILABLE;
    }

    /**
     * @param {{ message?: string, code?: string, reason?: string, actions?: unknown }|null|undefined} data
     * @returns {{ text: string, code: string|null, actions: Array<{label:string,url:string}> }}
     */
    function mapAccountStatusErrorToUi(data) {
        var code = data && data.code ? String(data.code) : null;
        var context = {
            reason: data && data.reason ? String(data.reason) : ''
        };
        var serverActions = normalizeActions(data && data.actions);
        var text = textForCode(code, context);
        var actions = serverActions.length > 0 ? serverActions : actionsForCode(code, context);

        return {
            text: text,
            code: code,
            actions: actions
        };
    }

    var api = {
        mapAccountStatusErrorToUi: mapAccountStatusErrorToUi,
        buildGoogleCalendarSetupUrl: buildGoogleCalendarSetupUrl,
        /** @internal */
        _MSG_REQUIRES_LINK: MSG_REQUIRES_LINK
    };

    if (typeof window !== 'undefined') {
        window.AccountStatusErrorUx = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
