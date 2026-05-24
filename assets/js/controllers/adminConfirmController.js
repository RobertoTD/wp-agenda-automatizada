/**
 * Controlador: Confirmación de Citas (Admin)
 * 
 * Responsable de:
 * - Callbacks para confirmar/cancelar/crear cliente
 * - Delegación a ConfirmService
 * - Mostrar alertas y recargar tabla
 * 
 * NO contiene llamadas AJAX directas.
 */

window.AdminConfirmController = (function() {
    'use strict';
    
    let recargarCallback = null;
    
    /**
     * Inicializar controlador
     * @param {Function} onRecargar - Callback para recargar la tabla
     */
    function init(onRecargar) {
        recargarCallback = onRecargar;
    }
    
    /**
     * Confirmar una cita
     * @param {number} id - ID de la cita
     */
    function onConfirmar(id) {
        if (!window.ConfirmService) {
            console.error('❌ ConfirmService no está cargado');
            alert('❌ Error: Servicio de confirmación no disponible.');
            return;
        }
        
        window.ConfirmService.confirmar(id)
            .then(data => {
                if (data.success) {
                    showConfirmResultNotification(data);
                    document.dispatchEvent(new CustomEvent('aa-cita-action-completed'));
                    if (recargarCallback) {
                        recargarCallback();
                    }
                } else {
                    alert('❌ Error: ' + (data.data?.message || 'No se pudo confirmar la cita.'));
                }
            })
            .catch(err => {
                console.error('Error al confirmar cita:', err);
                alert('❌ Error de conexión: ' + err.message);
            });
    }
    
    var CALENDAR_DELETED_DETAIL = 'Evento de Google Calendar eliminado.';
    var CALENDAR_CREATED_DETAIL = 'Evento de Google Calendar creado.';
    var CALENDAR_EXISTED_DETAIL = 'El evento ya existía en Google Calendar.';
    var EMAIL_SENT_DETAIL = 'Correo de confirmación enviado.';
    var SEND_CONFIRMATION_CLIENT_DETAIL = 'Correo de solicitud enviado al cliente.';
    var SEND_CONFIRMATION_OWNER_DETAIL = 'Correo enviado al negocio.';

    /**
     * @param {{ details?: string[] }} notification
     * @param {string} line
     */
    function pushDetailUnique(notification, line) {
        if (!line) {
            return;
        }
        if (!notification.details || !Array.isArray(notification.details)) {
            notification.details = [];
        }
        if (notification.details.indexOf(line) === -1) {
            notification.details.push(line);
        }
    }

    /**
     * @param {Record<string, unknown>} payload
     * @returns {Record<string, unknown>}
     */
    function getPayloadDataNode(payload) {
        var data = payload.data;
        if (data !== null && data !== undefined && typeof data === 'object' && !Array.isArray(data)) {
            return /** @type {Record<string, unknown>} */ (data);
        }
        return {};
    }

    /**
     * @param {Record<string, unknown>} payload
     * @param {{ notices?: unknown[] }} notification
     * @returns {unknown[]}
     */
    function getNoticeList(payload, notification) {
        if (notification.notices && Array.isArray(notification.notices) && notification.notices.length > 0) {
            return notification.notices;
        }
        if (payload.benefit_notices && Array.isArray(payload.benefit_notices)) {
            return payload.benefit_notices;
        }
        return [];
    }

    /**
     * @param {unknown[]} notices
     * @param {string} resource
     * @param {string} operation
     * @returns {boolean}
     */
    function hasSkippedNotice(notices, resource, operation) {
        for (var i = 0; i < notices.length; i++) {
            var notice = notices[i];
            if (!notice || typeof notice !== 'object') {
                continue;
            }
            var n = /** @type {Record<string, unknown>} */ (notice);
            var res = String(n.resource || '').toLowerCase();
            var op = String(n.operation || '').toLowerCase();
            var status = String(n.status || '').toLowerCase();
            if (res === resource && op === operation && status === 'skipped') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param {unknown[]} notices
     * @param {string} resource
     * @param {string} operation
     * @param {string[]} statuses
     * @returns {boolean}
     */
    function hasNoticeWithStatus(notices, resource, operation, statuses) {
        for (var i = 0; i < notices.length; i++) {
            var notice = notices[i];
            if (!notice || typeof notice !== 'object') {
                continue;
            }
            var n = /** @type {Record<string, unknown>} */ (notice);
            var res = String(n.resource || '').toLowerCase();
            var op = String(n.operation || '').toLowerCase();
            var status = String(n.status || '').toLowerCase();
            if (
                res === resource &&
                op === operation &&
                statuses.indexOf(status) !== -1
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @returns {{ mapper: object, toastApi: object }|null}
     */
    function getMapperAndToast() {
        var mapper = window.BenefitNotificationMapper;
        var toastApi = window.AAAdmin && window.AAAdmin.toast;
        if (
            !mapper ||
            typeof mapper.mapBenefitResponseToNotifications !== 'function' ||
            !toastApi ||
            typeof toastApi.showMany !== 'function'
        ) {
            return null;
        }
        return { mapper: mapper, toastApi: toastApi };
    }

    /**
     * @param {Record<string, unknown>} payload
     * @param {{ notices?: unknown[] }} notification
     * @returns {boolean}
     */
    function hasCalendarCreateSkipped(payload, notification) {
        var dataNode = getPayloadDataNode(payload);
        if (payload.calendar_skipped === true || dataNode.calendarSkipped === true) {
            return true;
        }
        return hasSkippedNotice(
            getNoticeList(payload, notification),
            'google_calendar_sync',
            'create_event'
        );
    }

    /**
     * @param {Record<string, unknown>} payload
     * @param {{ notices?: unknown[] }} notification
     * @returns {boolean}
     */
    function hasEmailSendSkipped(payload, notification) {
        var email = payload.email;
        if (email !== null && email !== undefined && typeof email === 'object' && !Array.isArray(email)) {
            var emailObj = /** @type {Record<string, unknown>} */ (email);
            if (emailObj.skipped === true) {
                return true;
            }
        }
        return hasSkippedNotice(
            getNoticeList(payload, notification),
            'email',
            'send_confirmed_email'
        );
    }

    /**
     * @param {Record<string, unknown>} payload
     * @returns {boolean}
     */
    function hasCalendarEventEvidence(payload) {
        var dataNode = getPayloadDataNode(payload);
        return !!(payload.calendar_uid || dataNode.event_id);
    }

    /**
     * @param {{ details?: string[], notices?: unknown[] }} first
     * @param {Record<string, unknown>} payload
     */
    function appendConfirmPositiveDetails(first, payload) {
        if (!first.details || !Array.isArray(first.details)) {
            first.details = [];
        }
        var dataNode = getPayloadDataNode(payload);

        if (!hasCalendarCreateSkipped(payload, first) && hasCalendarEventEvidence(payload)) {
            var calendarLine = dataNode.existed === true
                ? CALENDAR_EXISTED_DETAIL
                : CALENDAR_CREATED_DETAIL;
            pushDetailUnique(first, calendarLine);
        }

        var email = payload.email;
        if (
            email !== null &&
            email !== undefined &&
            typeof email === 'object' &&
            !Array.isArray(email)
        ) {
            var emailObj = /** @type {Record<string, unknown>} */ (email);
            if (emailObj.sent === true && !hasEmailSendSkipped(payload, first)) {
                pushDetailUnique(first, EMAIL_SENT_DETAIL);
            }
        }
    }

    /**
     * @param {{ title?: string, message?: string, details?: string[] }} first
     */
    function normalizeConfirmMessage(first) {
        if (first.title === 'Cita confirmada' && first.details && first.details.length > 0) {
            first.message = 'Cita confirmada.';
        }
    }

    /**
     * @param {Record<string, unknown>} payload
     * @param {{ notices?: unknown[] }} notification
     * @returns {boolean}
     */
    function hasSendConfirmationSkippedOrBlocked(payload, notification) {
        if (payload.skipped === true) {
            return true;
        }
        return hasNoticeWithStatus(
            getNoticeList(payload, notification),
            'email',
            'send_confirmation_request',
            ['skipped', 'blocked']
        );
    }

    /**
     * @param {{ details?: string[], notices?: unknown[] }} first
     * @param {Record<string, unknown>} payload
     */
    function appendSendConfirmationPositiveDetails(first, payload) {
        if (!first.details || !Array.isArray(first.details)) {
            first.details = [];
        }
        if (hasSendConfirmationSkippedOrBlocked(payload, first)) {
            return;
        }

        var sent = payload.sent;
        if (sent === null || sent === undefined || typeof sent !== 'object' || Array.isArray(sent)) {
            return;
        }

        var sentObj = /** @type {Record<string, unknown>} */ (sent);
        if (sentObj.client) {
            pushDetailUnique(first, SEND_CONFIRMATION_CLIENT_DETAIL);
        }
        if (sentObj.owner) {
            pushDetailUnique(first, SEND_CONFIRMATION_OWNER_DETAIL);
        }
    }

    /**
     * @param {{ title?: string, message?: string, details?: string[] }} first
     */
    function normalizeSendConfirmationMessage(first) {
        if (!first.details || first.details.length === 0) {
            return;
        }
        if (first.title === 'Solicitud enviada') {
            first.message = 'Solicitud de confirmacion enviada al cliente vía correo electrónico.';
        } else if (first.title === 'Solicitud no enviada') {
            first.message = 'Solicitud de confirmacion por correo al cliente no enviada.';
        }
    }

    var LOCAL_ACTION_SUCCESS_COPY = {
        appointment_pending_created: {
            title: 'Cita pendiente creada',
            message: 'Cita pendiente de confirmación creada localmente.'
        },
        appointment_confirmed_local: {
            title: 'Cita confirmada',
            message: 'Cita confirmada localmente.'
        },
        appointment_cancelled_local: {
            title: 'Cita cancelada',
            message: 'Cita cancelada localmente.'
        },
        attendance_registered: {
            title: 'Asistencia registrada',
            message: 'Asistencia registrada.'
        },
        no_show_registered: {
            title: 'No asistencia registrada',
            message: 'No asistencia registrada.'
        }
    };

    /**
     * Toast verde de acción local exitosa en WordPress (sin mapper ni Node).
     * @param {string} actionType — clave en LOCAL_ACTION_SUCCESS_COPY
     * @param {{ title?: string, message?: string, durationMs?: number }} [options]
     */
    function showLocalActionSuccessNotification(actionType, options) {
        var copy = LOCAL_ACTION_SUCCESS_COPY[actionType];
        if (!copy) {
            console.warn('[LocalAction] Tipo de acción desconocido:', actionType);
            return;
        }
        var opts = options || {};
        var title = opts.title != null ? String(opts.title) : copy.title;
        var message = opts.message != null ? String(opts.message) : copy.message;
        var durationMs = typeof opts.durationMs === 'number' ? opts.durationMs : 3500;
        var toastApi = window.AAAdmin && window.AAAdmin.toast;
        if (!toastApi || typeof toastApi.showMany !== 'function') {
            console.log('[LocalAction] ' + title + ': ' + message);
            return;
        }
        toastApi.showMany([{
            severity: 'success',
            title: title,
            message: message,
            details: [],
            fallback: null,
            durationMs: durationMs,
            blocking: false,
            actions: [],
            notices: []
        }]);
    }

    var AUTOMATION_CONNECTION_FAILED_COPY = {
        auto_confirm: {
            severity: 'warning',
            title: 'Automatización incompleta',
            message: 'No se pudo conectar con el servicio de automatización.',
            details: ['Revisa el estado de la cita.'],
            fallback: 'Notifica manualmente al cliente si corresponde.',
            durationMs: 7000
        },
        cancel: {
            severity: 'warning',
            title: 'Automatización incompleta',
            message: 'No se pudo conectar con el servicio de automatización.',
            details: ['La cita se canceló localmente, pero algunas acciones externas no pudieron completarse.'],
            fallback: 'Realiza manualmente las acciones externas si corresponde.',
            durationMs: 7000
        }
    };

    var AUTOMATION_BACKEND_MESSAGE_MARKERS = [
        'backend',
        'notificar al backend',
        'no se pudo notificar',
        'no pudo notificar'
    ];

    /**
     * Detecta confirmación local OK pero automatización externa (Node) degradada por fallo de conexión.
     * Conservador: no activar si hay benefit_notices (cuota/billing ya cubiertos por mapper).
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     * @returns {boolean}
     */
    function isConfirmAutomationIncomplete(wpResponse) {
        if (!wpResponse || wpResponse.success !== true) {
            return false;
        }
        var payload = wpResponse.data;
        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
            return false;
        }
        if (payload.calendar_sync !== false) {
            return false;
        }
        var notices = payload.benefit_notices;
        if (notices && Array.isArray(notices) && notices.length > 0) {
            return false;
        }
        var msg = String(payload.message || '').toLowerCase();
        if (!msg) {
            return false;
        }
        for (var i = 0; i < AUTOMATION_BACKEND_MESSAGE_MARKERS.length; i++) {
            if (msg.indexOf(AUTOMATION_BACKEND_MESSAGE_MARKERS[i]) !== -1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Toast warning adicional cuando falla la conexión con el servicio de automatización (Node).
     * No usa mapper ni benefit_notices. Complementa el toast de éxito local si aplica.
     * @param {string} context — p. ej. 'auto_confirm'
     */
    function showAutomationConnectionFailedNotification(context) {
        var copy = AUTOMATION_CONNECTION_FAILED_COPY[context];
        if (!copy) {
            console.warn('[Automation] Contexto de conexión desconocido:', context);
            return;
        }
        var toastApi = window.AAAdmin && window.AAAdmin.toast;
        if (!toastApi || typeof toastApi.showMany !== 'function') {
            console.warn('[Automation] No se pudo conectar con el servicio de automatización.');
            return;
        }
        toastApi.showMany([{
            severity: copy.severity,
            title: copy.title,
            message: copy.message,
            details: copy.details.slice(),
            fallback: copy.fallback,
            durationMs: copy.durationMs,
            blocking: false,
            actions: [],
            notices: []
        }]);
    }

    /**
     * Toast local cuando se crea una cita pendiente sin correo del cliente.
     * No hay llamada a aa_enviar_confirmacion ni benefit_notices reales.
     */
    function showPendingCreatedWithoutEmailNotification() {
        var toastApi = window.AAAdmin && window.AAAdmin.toast;
        if (!toastApi || typeof toastApi.showMany !== 'function') {
            console.log('ℹ️ Cita pendiente creada sin correo: el cliente no tiene correo electrónico registrado.');
            return;
        }
        toastApi.showMany([{
            severity: 'warning',
            title: 'Cita pendiente creada',
            message: 'Cita pendiente creada.',
            details: ['El cliente no tiene correo electrónico registrado.'],
            fallback: 'Puedes notificar al cliente manualmente.',
            durationMs: 5000,
            blocking: false,
            actions: [],
            notices: []
        }]);
    }

    /**
     * Toast (o alert legacy) tras solicitud de confirmación por correo.
     * Recibe respuesta AJAX completa de aa_enviar_confirmacion.
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     */
    function showSendConfirmationResultNotification(wpResponse) {
        var payload = wpResponse && wpResponse.data ? wpResponse.data : {};

        function legacyAlert() {
            if (wpResponse && wpResponse.success === true) {
                alert('✅ ' + (payload.message || 'Solicitud enviada.'));
            } else {
                alert('❌ ' + (payload.error || payload.message || 'No se pudo enviar la solicitud de confirmación.'));
            }
        }

        var stack = getMapperAndToast();
        if (!stack) {
            legacyAlert();
            return;
        }

        var notifications = stack.mapper.mapBenefitResponseToNotifications({
            response: wpResponse,
            context: 'send_confirmation_request',
            baseOutcome: {
                status: 'success',
                message: 'Solicitud enviada.'
            }
        });

        if (!notifications || notifications.length === 0) {
            if (wpResponse && wpResponse.success === true) {
                notifications = [{
                    severity: 'success',
                    title: 'Solicitud enviada',
                    message: 'Solicitud enviada.',
                    details: [],
                    fallback: null,
                    durationMs: 3500,
                    blocking: false,
                    actions: [],
                    notices: []
                }];
            } else {
                notifications = [{
                    severity: 'error',
                    title: 'Solicitud no enviada',
                    message: payload.error || payload.message || 'No se pudo enviar la solicitud de confirmación.',
                    details: [],
                    fallback: null,
                    durationMs: 7000,
                    blocking: false,
                    actions: [],
                    notices: []
                }];
            }
        }

        var first = notifications[0];
        appendSendConfirmationPositiveDetails(first, payload);
        normalizeSendConfirmationMessage(first);

        stack.toastApi.showMany(notifications);
    }

    /**
     * Toast (o alert legacy) tras confirmación exitosa. Recibe respuesta AJAX completa.
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     */
    function showConfirmResultNotification(wpResponse) {
        var payload = wpResponse && wpResponse.data ? wpResponse.data : {};

        function legacySuccessAlert() {
            var message = payload.message || 'Cita confirmada correctamente.';
            alert('✅ ' + message);
        }

        var stack = getMapperAndToast();
        if (!stack) {
            legacySuccessAlert();
            return;
        }

        var notifications = stack.mapper.mapBenefitResponseToNotifications({
            response: wpResponse,
            context: 'confirm_admin',
            baseOutcome: {
                status: 'success',
                message: 'Cita confirmada.'
            }
        });

        if (!notifications || notifications.length === 0) {
            notifications = [{
                severity: 'success',
                title: 'Cita confirmada',
                message: 'Cita confirmada.',
                details: [],
                fallback: null,
                durationMs: 3500,
                blocking: false,
                actions: [],
                notices: []
            }];
        }

        var first = notifications[0];
        appendConfirmPositiveDetails(first, payload);
        normalizeConfirmMessage(first);

        stack.toastApi.showMany(notifications);
    }

    /**
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     * @returns {Record<string, unknown>}
     */
    function getCancelPayload(wpResponse) {
        return wpResponse && wpResponse.data ? wpResponse.data : {};
    }

    /**
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     * @returns {boolean}
     */
    function hasCancelQuotaSignals(wpResponse) {
        var payload = getCancelPayload(wpResponse);
        return !!(
            payload.calendar_delete_skipped === true ||
            payload.calendar_quota_code ||
            (Array.isArray(payload.benefit_notices) && payload.benefit_notices.length > 0)
        );
    }

    /**
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     * @returns {boolean}
     */
    function hasCancelBackendConnectionFailure(wpResponse) {
        if (!wpResponse || wpResponse.success !== true) {
            return false;
        }
        var payload = getCancelPayload(wpResponse);
        if (!payload || payload.local_cancelled === false) {
            return false;
        }
        if (payload.calendar_delete_attempted !== true) {
            return false;
        }
        if (payload.calendar_delete_backend_failed !== true) {
            return false;
        }
        if (hasCancelQuotaSignals(wpResponse)) {
            return false;
        }
        return true;
    }

    /**
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     * @returns {boolean}
     */
    function shouldShowCancelExternalNotification(wpResponse) {
        var payload = getCancelPayload(wpResponse);
        if (payload.calendar_deleted === true) {
            return true;
        }
        if (hasCancelQuotaSignals(wpResponse)) {
            return true;
        }
        return false;
    }

    /**
     * Toast verde externo: evento eliminado en Google Calendar (sin mapper).
     */
    function showCancelCalendarDeletedNotification() {
        var toastApi = window.AAAdmin && window.AAAdmin.toast;
        if (!toastApi || typeof toastApi.showMany !== 'function') {
            console.log('[Cancelación] Evento de Google Calendar eliminado.');
            return;
        }
        toastApi.showMany([{
            severity: 'success',
            title: 'Google Calendar actualizado',
            message: 'Evento de Google Calendar eliminado.',
            details: [],
            fallback: null,
            durationMs: 3500,
            blocking: false,
            actions: [],
            notices: []
        }]);
    }

    /**
     * Toast (o alert legacy) tras cancelación exitosa. Recibe respuesta AJAX completa.
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     * @param {{ localAlreadyShown?: boolean }} [options]
     */
    function showCancelResultNotification(wpResponse, options) {
        var opts = options || {};
        var localAlreadyShown = opts.localAlreadyShown === true;
        var payload = getCancelPayload(wpResponse);

        function legacySuccessAlert() {
            var mensaje = '✅ Cita cancelada correctamente.';
            if (payload.calendar_deleted) {
                mensaje += '\n🗓️ El evento también fue eliminado de Google Calendar.';
            }
            alert(mensaje);
        }

        var stack = getMapperAndToast();
        if (!stack) {
            if (localAlreadyShown) {
                console.warn('[Cancelación] Automatizaciones externas no mostradas (mapper/toast no disponible).');
            } else {
                legacySuccessAlert();
            }
            return;
        }

        var notifications = stack.mapper.mapBenefitResponseToNotifications({
            response: wpResponse,
            context: 'cancel_admin',
            baseOutcome: {
                status: 'success',
                message: 'Cita cancelada.'
            }
        });

        if (!notifications || notifications.length === 0) {
            if (localAlreadyShown) {
                return;
            }
            notifications = [{
                severity: 'success',
                title: 'Cita cancelada',
                message: 'Cita cancelada.',
                details: [],
                fallback: null,
                durationMs: 3500,
                blocking: false,
                actions: [],
                notices: []
            }];
        }

        var first = notifications[0];
        if (!first.details || !Array.isArray(first.details)) {
            first.details = [];
        }

        if (localAlreadyShown) {
            if (first.title === 'Cita cancelada') {
                first.title = 'Sincronización omitida';
            }
            if (first.details.length > 0) {
                first.message = 'Algunas automatizaciones no se completaron.';
            } else if (first.title === 'Sincronización omitida') {
                first.message = 'Algunas automatizaciones no se completaron.';
            }
        } else {
            var severity = typeof first.severity === 'string' ? first.severity.toLowerCase() : '';
            var mayAddCalendarDeleted =
                payload.calendar_deleted === true &&
                severity !== 'warning' &&
                severity !== 'error';

            if (mayAddCalendarDeleted) {
                pushDetailUnique(first, CALENDAR_DELETED_DETAIL);
            }

            if (first.title === 'Cita cancelada' && first.details.length > 0) {
                first.message = 'Cita cancelada.';
            }
        }

        stack.toastApi.showMany(notifications);
    }

    /**
     * Cancelar una cita
     * @param {number} id - ID de la cita
     */
    function onCancelar(id) {
        if (!window.ConfirmService) {
            console.error('❌ ConfirmService no está cargado');
            alert('❌ Error: Servicio de cancelación no disponible.');
            return;
        }
        
        window.ConfirmService.cancelar(id)
            .then(data => {
                if (data.success) {
                    var cancelPayload = getCancelPayload(data);
                    if (cancelPayload.local_cancelled !== false) {
                        showLocalActionSuccessNotification('appointment_cancelled_local');
                    }
                    if (hasCancelBackendConnectionFailure(data)) {
                        showAutomationConnectionFailedNotification('cancel');
                    } else if (shouldShowCancelExternalNotification(data)) {
                        if (cancelPayload.calendar_deleted === true && !hasCancelQuotaSignals(data)) {
                            showCancelCalendarDeletedNotification();
                        } else if (hasCancelQuotaSignals(data)) {
                            showCancelResultNotification(data, { localAlreadyShown: true });
                        }
                    } else if (cancelPayload.local_cancelled === false) {
                        showCancelResultNotification(data);
                    }
                    document.dispatchEvent(new CustomEvent('aa-cita-action-completed'));
                    if (recargarCallback) {
                        recargarCallback();
                    }
                    
                    // Refrescar disponibilidad local después de cancelar (delegar al controller)
                    if (window.AdminReservationController && typeof window.AdminReservationController.refreshLocalAvailability === 'function') {
                        window.AdminReservationController.refreshLocalAvailability();
                    }
                } else {
                    alert('❌ Error: ' + (data.data?.message || 'No se pudo cancelar la cita.'));
                }
            })
            .catch(err => {
                console.error('Error al cancelar cita:', err);
                alert('❌ Error de conexión: ' + err.message);
            });
    }
    
    /**
     * Crear cliente desde una cita
     * @param {number} reservaId - ID de la reserva
     * @param {string} nombre - Nombre del cliente
     * @param {string} telefono - Teléfono del cliente
     * @param {string} correo - Correo del cliente
     */
    function onCrearCliente(reservaId, nombre, telefono, correo) {
        if (!window.ConfirmService) {
            console.error('❌ ConfirmService no está cargado');
            alert('❌ Error: Servicio de clientes no disponible.');
            return;
        }
        
        window.ConfirmService.crearClienteDesdeCita(reservaId, nombre, telefono, correo)
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.data.message);
                    if (recargarCallback) {
                        recargarCallback();
                    }
                } else {
                    alert('❌ Error: ' + (data.data?.message || 'No se pudo crear el cliente.'));
                }
            })
            .catch(err => {
                console.error('Error al crear cliente desde cita:', err);
                alert('❌ Error de conexión: ' + err.message);
            });
    }
    
    // ===============================
    // 🔹 API Pública
    // ===============================
    return {
        init,
        onConfirmar,
        onCancelar,
        onCrearCliente,
        showSendConfirmationResultNotification,
        showConfirmResultNotification,
        showPendingCreatedWithoutEmailNotification,
        isConfirmAutomationIncomplete,
        showAutomationConnectionFailedNotification,
        showLocalActionSuccessNotification
    };
})();
