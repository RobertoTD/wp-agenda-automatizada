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
                    let mensaje = '✅ Cita cancelada correctamente.';
                    
                    if (data.data?.calendar_deleted) {
                        mensaje += '\n🗓️ El evento también fue eliminado de Google Calendar.';
                    }
                    
                    alert(mensaje);
                    if (recargarCallback) {
                        recargarCallback();
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
