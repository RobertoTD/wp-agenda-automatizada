/**
 * Servicio: Calendario Admin
 * 
 * Responsable de:
 * - Llamadas AJAX para obtener citas del calendario
 * - Gestión de datos del calendario
 * - Lógica de dominio (cálculos, decisiones)
 * 
 * NO contiene lógica de UI ni callbacks.
 */

window.AdminCalendarService = (function() {
    'use strict';
    
    /**
     * Obtener citas por día
     * @param {string} fecha - Fecha en formato YYYY-MM-DD
     * @returns {Promise<Object>} Respuesta del servidor
     */
    function getCitasPorDia(fecha) {
        // TODO: Implementar
        return Promise.resolve({ success: false, data: { citas: [] } });
    }
    
    /**
     * Determinar si una cita es próxima/activa o pasada
     * Delega a DateUtils.isAppointmentActive para mantener la lógica centralizada
     * 
     * @param {Object} cita - Objeto de cita con fecha, fecha_fin y/o duracion
     * @returns {boolean} - true si es próxima/activa, false si ya terminó
     */
    function esCitaProxima(cita) {
        // Delegar a DateUtils si está disponible
        if (window.DateUtils && typeof window.DateUtils.isAppointmentActive === 'function') {
            return window.DateUtils.isAppointmentActive(cita);
        }
        
        // Fallback simple si DateUtils no está disponible
        return false;
    }
    
    /**
     * Calcular datos de posición de una cita en el timeline
     * @param {Object} cita - Objeto de cita con fecha, fecha_fin, duracion
     * @returns {Object|null} - { minutosInicio, minutosFin, slotInicio, bloquesOcupados } o null si no válida
     */
    function calcularPosicionCita(cita) {
        if (!cita.fecha) return null;
        
        // Convertir fecha de inicio a minutos desde medianoche
        const fechaInicio = new Date(cita.fecha);
        const minutosInicio = window.DateUtils.minutesFromDate(fechaInicio);
        
        // Calcular minutos de fin usando fecha_fin o duracion
        let minutosFin;
        if (cita.fecha_fin) {
            const fechaFin = new Date(cita.fecha_fin);
            minutosFin = window.DateUtils.minutesFromDate(fechaFin);
        } else if (cita.duracion) {
            minutosFin = minutosInicio + parseInt(cita.duracion);
        } else {
            // Fallback: 60 minutos por defecto
            minutosFin = minutosInicio + 60;
        }
        
        // Encontrar el slot inicial (redondear hacia abajo al slot de 30 min más cercano)
        const slotInicio = Math.floor(minutosInicio / 30) * 30;
        
        // Calcular cuántos bloques de 30 min ocupa
        const duracionMinutos = minutosFin - minutosInicio;
        const bloquesOcupados = Math.ceil(duracionMinutos / 30);
        
        return {
            minutosInicio: minutosInicio,
            minutosFin: minutosFin,
            slotInicio: slotInicio,
            bloquesOcupados: bloquesOcupados
        };
    }
    
    // API pública
    return {
        getCitasPorDia: getCitasPorDia,
        esCitaProxima: esCitaProxima,
        calcularPosicionCita: calcularPosicionCita
    };
})();

