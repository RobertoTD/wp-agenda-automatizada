/**
 * Controlador: Calendario Admin
 * 
 * Responsable de:
 * - Coordinación entre UI y servicios del calendario
 * - Callbacks y manejo de eventos
 * - Actualización de la vista del calendario
 * - Orquestación de acciones (confirmar, cancelar, asistencia)
 * 
 * NO contiene llamadas AJAX directas.
 */

window.AdminCalendarController = (function() {
    'use strict';
    
    let recargarCallback = null;
    
    /**
     * Inicializar controlador
     * @param {Function} onRecargar - Callback para recargar el calendario
     */
    function init(onRecargar) {
        recargarCallback = onRecargar;
    }
    
    /**
     * Cargar citas del día
     * @param {string} fecha - Fecha en formato YYYY-MM-DD
     */
    function cargarCitasDelDia(fecha) {
        // TODO: Implementar
        if (recargarCallback) {
            recargarCallback();
        }
    }
    
    /**
     * Handler único de acciones de citas
     * @param {string} action - Acción a ejecutar
     * @param {string|number} citaId - ID de la cita
     */
    function handleCitaAction(action, citaId) {
        switch (action) {
            case 'confirmar':
                if (window.AdminConfirmController?.onConfirmar) {
                    window.AdminConfirmController.onConfirmar(citaId);
                    // Recargar después de confirmar
                    setTimeout(() => {
                        if (recargarCallback) {
                            recargarCallback();
                        }
                    }, 1000);
                }
                break;
                
            case 'cancelar':
                if (window.AdminConfirmController?.onCancelar) {
                    if (confirm('¿Cancelar esta cita?')) {
                        window.AdminConfirmController.onCancelar(citaId);
                        // Recargar después de cancelar
                        setTimeout(() => {
                            if (recargarCallback) {
                                recargarCallback();
                            }
                        }, 1000);
                    }
                }
                break;
                
            case 'asistio':
                marcarAsistencia(citaId);
                break;
                
            case 'no-asistio':
                marcarNoAsistencia(citaId);
                break;
        }
    }
    
    /**
     * Marcar cita como "asistió"
     * @param {string|number} citaId - ID de la cita
     */
    function marcarAsistencia(citaId) {
        if (!confirm('¿Confirmar que el cliente asistió?')) return;
        
        const formData = new FormData();
        formData.append('action', 'aa_marcar_asistencia');
        formData.append('cita_id', citaId);
        
        const nonce = window.AA_CALENDAR_DATA?.historialNonce;
        if (nonce) {
            formData.append('_wpnonce', nonce);
        }
        
        const ajaxurl = window.AA_CALENDAR_DATA?.ajaxurl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Asistencia registrada');
                if (recargarCallback) {
                    recargarCallback();
                }
            } else {
                alert(data.data?.message || 'Error al registrar asistencia');
            }
        })
        .catch(err => {
            console.error('Error al marcar asistencia:', err);
            alert('Error de conexión');
        });
    }
    
    /**
     * Marcar cita como "no asistió"
     * @param {string|number} citaId - ID de la cita
     */
    function marcarNoAsistencia(citaId) {
        if (!confirm('¿Confirmar que el cliente NO asistió?')) return;
        
        const formData = new FormData();
        formData.append('action', 'aa_marcar_no_asistencia');
        formData.append('cita_id', citaId);
        
        const nonce = window.AA_CALENDAR_DATA?.historialNonce;
        if (nonce) {
            formData.append('_wpnonce', nonce);
        }
        
        const ajaxurl = window.AA_CALENDAR_DATA?.ajaxurl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('❌ No asistencia registrada');
                if (recargarCallback) {
                    recargarCallback();
                }
            } else {
                alert(data.data?.message || 'Error al registrar no asistencia');
            }
        })
        .catch(err => {
            console.error('Error al marcar no asistencia:', err);
            alert('Error de conexión');
        });
    }
    
    // API pública
    return {
        init: init,
        cargarCitasDelDia: cargarCitasDelDia,
        handleCitaAction: handleCitaAction
    };
})();

