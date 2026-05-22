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
                    alert('✅ Cita confirmada. Se envió correo de confirmación.');
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

        var mapper = window.BenefitNotificationMapper;
        var toastApi = window.AAAdmin && window.AAAdmin.toast;
        if (
            !mapper ||
            typeof mapper.mapBenefitResponseToNotifications !== 'function' ||
            !toastApi ||
            typeof toastApi.showMany !== 'function'
        ) {
            legacySuccessAlert();
            return;
        }

        var notifications = mapper.mapBenefitResponseToNotifications({
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

        if (mayAddCalendarDeleted && first.details.indexOf(CALENDAR_DELETED_DETAIL) === -1) {
            first.details.push(CALENDAR_DELETED_DETAIL);
        }

        if (first.title === 'Cita cancelada' && first.details.length > 0) {
            first.message = 'Cita cancelada.';
        }

        toastApi.showMany(notifications);
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
        onCrearCliente
    };
})();
