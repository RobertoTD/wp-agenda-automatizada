/**
 * Admin AI chat — human-facing error copy and CTA mapping (UX).
 *
 * Pure mapping; no DOM. Consumed by aichat.js and Node tests.
 */
(function () {
    'use strict';

    var MSG_REQUIRES_ACCOUNT =
        'El Asistente IA requiere una cuenta DEOIA activa. Vincula tu cuenta para habilitar el acceso disponible en tu plan.';

    var MSG_PLAN_DISABLED =
        'Tu plan actual no incluye consultas del Asistente IA. Revisa tu cuenta DEOIA para ver qué está disponible.';

    var MSG_QUOTA_EXCEEDED =
        'Has alcanzado el límite de consultas de IA para este período.';

    var MSG_CONNECTION_UNAVAILABLE =
        'No pude conectarme con el asistente en este momento. Intenta de nuevo más tarde.';

    var MSG_PROVIDER_UNAVAILABLE =
        'El servicio de IA no está disponible en este momento. Intenta más tarde.';

    var MSG_TEMPORARY_UNAVAILABLE =
        'El servicio no está disponible temporalmente. Intenta más tarde.';

    var MSG_GENERIC_UNAVAILABLE =
        'No es posible procesar la consulta en este momento.';

    var GOOGLE_CALENDAR_SETUP_URL_FALLBACK =
        'admin-post.php?action=aa_iframe_content&module=settings&setup_focus=google_calendar#aa-google-calendar-root';

    var LINK_ACCOUNT_LABEL = 'Vincular cuenta';

    var REQUIRES_ACCOUNT_CODES = {
        ai_backend_not_configured: true,
        no_installation_id: true
    };

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
     * @returns {Array<{label:string,url:string}>}
     */
    function actionsForCode(code) {
        if (!code || !REQUIRES_ACCOUNT_CODES[code]) {
            return [];
        }
        return [{
            label: LINK_ACCOUNT_LABEL,
            url: buildGoogleCalendarSetupUrl()
        }];
    }

    /**
     * @param {string|null} code
     * @returns {string}
     */
    function textForCode(code) {
        switch (code) {
            case 'ai_backend_not_configured':
            case 'no_installation_id':
                return MSG_REQUIRES_ACCOUNT;
            case 'backend_disabled':
                return MSG_PLAN_DISABLED;
            case 'quota_exceeded':
                return MSG_QUOTA_EXCEEDED;
            case 'ai_not_configured':
                return MSG_PROVIDER_UNAVAILABLE;
            case 'quota_service_unavailable':
                return MSG_TEMPORARY_UNAVAILABLE;
            case 'quota_denied':
                return MSG_GENERIC_UNAVAILABLE;
            case 'ai_unavailable':
                return MSG_CONNECTION_UNAVAILABLE;
            default:
                return MSG_CONNECTION_UNAVAILABLE;
        }
    }

    /**
     * @param {{ message?: string, code?: string, actions?: unknown }|null|undefined} data
     * @returns {{ text: string, code: string|null, actions: Array<{label:string,url:string}> }}
     */
    function mapChatAjaxErrorToUi(data) {
        var code = data && data.code ? String(data.code) : null;
        var serverActions = normalizeActions(data && data.actions);

        var text = textForCode(code);
        var actions = serverActions.length > 0 ? serverActions : actionsForCode(code);

        return {
            text: text,
            code: code,
            actions: actions
        };
    }

    /**
     * @param {string} text
     * @param {string} blocker
     * @returns {boolean}
     */
    function shouldShowFixBlockerDetail(text, blocker) {
        var main = String(text || '').trim();
        var detail = String(blocker || '').trim();
        return detail !== '' && detail !== main;
    }

    var api = {
        mapChatAjaxErrorToUi: mapChatAjaxErrorToUi,
        buildGoogleCalendarSetupUrl: buildGoogleCalendarSetupUrl,
        shouldShowFixBlockerDetail: shouldShowFixBlockerDetail,
        /** @internal */
        _MSG_REQUIRES_ACCOUNT: MSG_REQUIRES_ACCOUNT
    };

    if (typeof window !== 'undefined') {
        window.AIChatErrorUx = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
