/**
 * Training Account card — pure presentation helpers (C8A2).
 * No DOM, no fetch. Maps access_state / consent to UI copy and actions.
 */
(function (root) {
    'use strict';

    /** Must match backend TRAINING_EMAIL_CONSENT_TEXT_VERSION. Not shown in UI. */
    var CURRENT_CONSENT_TEXT_VERSION = 'training-email-v1';

    var UNSUBSCRIBE_CONFIRM_MESSAGE =
        '¿Seguro que quieres desinscribirte del curso? Podrás reactivarlo más adelante.';

    var ACCESS = {
        LOADING: 'loading',
        ERROR: 'error',
        NOT_ELIGIBLE: 'not_eligible',
        NOT_ENROLLED: 'not_enrolled',
        ACTIVE: 'active',
        UNSUBSCRIBED: 'unsubscribed',
        SUSPENDED: 'suspended',
        COURSE_UNAVAILABLE: 'course_unavailable'
    };

    var CONSENT = {
        NOT_ACCEPTED: 'not_accepted',
        ACCEPTED: 'accepted',
        REVOKED: 'revoked',
        REACCEPT_REQUIRED: 'reaccept_required',
        ERROR: 'error',
        LOADING: 'loading',
        HIDDEN: 'hidden'
    };

    /**
     * @param {object|null|undefined} statusPayload data from getStatus success
     * @returns {string}
     */
    function resolveAccessState(statusPayload) {
        if (!statusPayload || typeof statusPayload !== 'object') {
            return ACCESS.ERROR;
        }
        var state = statusPayload.access_state;
        if (
            state === ACCESS.NOT_ELIGIBLE
            || state === ACCESS.NOT_ENROLLED
            || state === ACCESS.ACTIVE
            || state === ACCESS.UNSUBSCRIBED
            || state === ACCESS.SUSPENDED
            || state === ACCESS.COURSE_UNAVAILABLE
        ) {
            return state;
        }
        return ACCESS.ERROR;
    }

    /**
     * @param {string} accessState
     * @returns {{
     *   accessState: string,
     *   title: string,
     *   copy: string,
     *   primaryAction: {id: string, label: string, kind: string}|null,
     *   secondaryAction: {id: string, label: string, kind: string}|null,
     *   showConsent: boolean
     * }}
     */
    function buildEnrollmentPresentation(accessState) {
        var title = 'Capacitación DEOIA';
        var copy = '';
        var primaryAction = null;
        var secondaryAction = null;
        var showConsent = false;

        switch (accessState) {
            case ACCESS.LOADING:
                copy = 'Consultando tu capacitación…';
                break;
            case ACCESS.NOT_ELIGIBLE:
                copy = 'La capacitación no está disponible con tu acceso actual.';
                break;
            case ACCESS.NOT_ENROLLED:
                copy = 'Aprende a organizar los objetivos, tareas y citas de tu negocio con el Método DEOIA.';
                primaryAction = { id: 'enroll', label: 'Inscribirme', kind: 'button' };
                break;
            case ACCESS.ACTIVE:
                copy = 'Tu curso está activo.';
                primaryAction = { id: 'open', label: 'Abrir curso', kind: 'link' };
                secondaryAction = { id: 'unsubscribe', label: 'Desinscribirme', kind: 'button' };
                showConsent = true;
                break;
            case ACCESS.UNSUBSCRIBED:
                copy = 'Te desinscribiste del curso. Puedes reactivar tu acceso cuando quieras.';
                primaryAction = { id: 'reactivate', label: 'Reactivar curso', kind: 'button' };
                break;
            case ACCESS.SUSPENDED:
                copy = 'Tu inscripción se conserva, pero tu acceso está suspendido.';
                break;
            case ACCESS.COURSE_UNAVAILABLE:
                copy = 'El curso no está disponible temporalmente.';
                break;
            case ACCESS.ERROR:
            default:
                copy = 'No pudimos consultar tu capacitación.';
                primaryAction = { id: 'retry', label: 'Reintentar', kind: 'button' };
                break;
        }

        return {
            accessState: accessState,
            title: title,
            copy: copy,
            primaryAction: primaryAction,
            secondaryAction: secondaryAction,
            showConsent: showConsent
        };
    }

    /**
     * @param {object|null|undefined} consentPayload data.consent from getConsentStatus
     * @returns {string}
     */
    function resolveConsentUiState(consentPayload) {
        if (!consentPayload || typeof consentPayload !== 'object') {
            return CONSENT.NOT_ACCEPTED;
        }

        var status = consentPayload.status;
        if (status === 'revoked') {
            return CONSENT.REVOKED;
        }
        if (status === 'accepted') {
            var version = typeof consentPayload.text_version === 'string'
                ? consentPayload.text_version
                : '';
            if (version !== '' && version !== CURRENT_CONSENT_TEXT_VERSION) {
                return CONSENT.REACCEPT_REQUIRED;
            }
            return CONSENT.ACCEPTED;
        }
        return CONSENT.NOT_ACCEPTED;
    }

    /**
     * @param {string} consentUiState
     * @returns {{
     *   consentState: string,
     *   intro: string,
     *   statusCopy: string,
     *   primaryAction: {id: string, label: string}|null,
     *   secondaryAction: {id: string, label: string}|null
     * }}
     */
    function buildConsentPresentation(consentUiState) {
        var intro = 'Quiero recibir por correo las guías, materiales y capacitación relacionados con el curso DEOIA.';
        var statusCopy = '';
        var primaryAction = null;
        var secondaryAction = null;

        switch (consentUiState) {
            case CONSENT.LOADING:
                statusCopy = 'Consultando preferencia de correo…';
                break;
            case CONSENT.ERROR:
                statusCopy = 'No pudimos consultar tu preferencia de correo.';
                primaryAction = { id: 'consent_retry', label: 'Reintentar' };
                break;
            case CONSENT.ACCEPTED:
                statusCopy = 'Recibes guías y materiales del curso por correo.';
                secondaryAction = { id: 'revoke', label: 'Dejar de recibirlos' };
                break;
            case CONSENT.REACCEPT_REQUIRED:
                statusCopy = 'Actualizamos la autorización de correos del curso. Revísala y vuelve a aceptarla para continuar recibiéndolos.';
                primaryAction = { id: 'accept', label: 'Aceptar nuevamente' };
                break;
            case CONSENT.REVOKED:
            case CONSENT.NOT_ACCEPTED:
            default:
                primaryAction = { id: 'accept', label: 'Aceptar correos del curso' };
                break;
        }

        return {
            consentState: consentUiState,
            intro: intro,
            statusCopy: statusCopy,
            primaryAction: primaryAction,
            secondaryAction: secondaryAction
        };
    }

    /**
     * Reactivate and first enroll both use TrainingService.enroll().
     * @param {string} actionId
     * @returns {'enroll'|'unsubscribe'|'accept'|'revoke'|'retry'|'consent_retry'|'open'|null}
     */
    function mapActionToService(actionId) {
        if (actionId === 'enroll' || actionId === 'reactivate') {
            return 'enroll';
        }
        if (actionId === 'unsubscribe') {
            return 'unsubscribe';
        }
        if (actionId === 'accept') {
            return 'accept';
        }
        if (actionId === 'revoke') {
            return 'revoke';
        }
        if (actionId === 'retry') {
            return 'retry';
        }
        if (actionId === 'consent_retry') {
            return 'consent_retry';
        }
        if (actionId === 'open') {
            return 'open';
        }
        return null;
    }

    var api = {
        ACCESS: ACCESS,
        CONSENT: CONSENT,
        CURRENT_CONSENT_TEXT_VERSION: CURRENT_CONSENT_TEXT_VERSION,
        UNSUBSCRIBE_CONFIRM_MESSAGE: UNSUBSCRIBE_CONFIRM_MESSAGE,
        resolveAccessState: resolveAccessState,
        buildEnrollmentPresentation: buildEnrollmentPresentation,
        resolveConsentUiState: resolveConsentUiState,
        buildConsentPresentation: buildConsentPresentation,
        mapActionToService: mapActionToService
    };

    root.TrainingAccountUx = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
