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
     * Toast (o alert legacy) tras cancelación exitosa. Recibe respuesta AJAX completa.
     * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
     */
    function showCancelResultNotification(wpResponse) {
        var payload = wpResponse && wpResponse.data ? wpResponse.data : {};

        function legacySuccessAlert() {
            var mensaje = '✅ Cita cancelada correctamente.';
            if (payload.calendar_deleted) {
                mensaje += '\n🗓️ El evento también fue eliminado de Google Calendar.';
            }
            alert(mensaje);
        }

        var stack = getMapperAndToast();
        if (!stack) {
            legacySuccessAlert();
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
                    showCancelResultNotification(data);
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
        showSendConfirmationResultNotification
    };
})();
