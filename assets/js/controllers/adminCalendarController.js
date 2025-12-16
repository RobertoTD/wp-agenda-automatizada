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
    let currentDate = null;
    let cargarCitasCallback = null;
    
    /**
     * Inicializar controlador
     * @param {Function} onRecargar - Callback para recargar el calendario
     * @param {Function} onCargarCitas - Callback para cargar citas de un día específico
     */
    function init(onRecargar, onCargarCitas) {
        recargarCallback = onRecargar;
        cargarCitasCallback = onCargarCitas;
        
        // Establecer fecha inicial (hoy)
        if (window.DateUtils) {
            const today = new Date();
            currentDate = window.DateUtils.ymd(today);
        }
    }
    
    /**
     * Establecer fecha actual y cargar citas
     * @param {string} fecha - Fecha en formato YYYY-MM-DD
     */
    function setDate(fecha) {
        if (!fecha || !/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
            console.error('❌ AdminCalendarController.setDate: Formato de fecha inválido:', fecha);
            return;
        }
        
        currentDate = fecha;
        
        // Cargar citas del día seleccionado
        if (cargarCitasCallback) {
            cargarCitasCallback(fecha);
        }
    }
    
    /**
     * Obtener fecha actual
     * @returns {string|null} - Fecha en formato YYYY-MM-DD o null
     */
    function getCurrentDate() {
        return currentDate;
    }
    
    /**
     * Cargar citas del día
     * @param {string} fecha - Fecha en formato YYYY-MM-DD
     */
    function cargarCitasDelDia(fecha) {
        setDate(fecha);
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
                    // AdminConfirmController ya maneja el callback de recarga internamente
                    window.AdminConfirmController.onConfirmar(citaId);
                }
                break;
                
            case 'cancelar':
                if (window.AdminConfirmController?.onCancelar) {
                    if (confirm('¿Cancelar esta cita?')) {
                        // AdminConfirmController ya maneja el callback de recarga internamente
                        window.AdminConfirmController.onCancelar(citaId);
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
        setDate: setDate,
        getCurrentDate: getCurrentDate,
        cargarCitasDelDia: cargarCitasDelDia,
        handleCitaAction: handleCitaAction
    };
})();

