/**
 * Calendar Availability Service
 * 
 * Centralized service for calendar availability logic (fixed vs assignments).
 * No UI rendering - pure business logic.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

(function() {
    'use strict';

    // ============================================
    // Helpers internos
    // ============================================

    /**
     * Verifica si un serviceKey es de tipo fixed
     * @param {string} serviceKey - Clave del servicio
     * @returns {boolean}
     */
    function isFixedServiceKey(serviceKey) {
        return typeof serviceKey === 'string' && serviceKey.startsWith('fixed::');
    }

    /**
     * Obtiene el schedule desde las variables globales
     * @returns {Object}
     */
    function getSchedule() {
        return window.aa_schedule || window.AA_CALENDAR_DATA?.schedule || {};
    }

    /**
     * Calcula el rango de fechas (minDate, maxDate, startYmd, endYmd)
     * @param {number} futureWindowDays - Días futuros desde hoy
     * @returns {Object} { minDate, maxDate, startYmd, endYmd }
     */
    function getRange(futureWindowDays) {
        const minDate = new Date();
        minDate.setHours(0, 0, 0, 0);
        
        const maxDate = new Date();
        maxDate.setDate(minDate.getDate() + futureWindowDays);
        maxDate.setHours(23, 59, 59, 999);
        
        const startYmd = window.DateUtils.ymd(minDate);
        const endYmd = window.DateUtils.ymd(maxDate);
        
        return { minDate, maxDate, startYmd, endYmd };
    }

    // ============================================
    // Métodos públicos
    // ============================================

    /**
     * Evalúa si un servicio tiene fechas disponibles dentro de la ventana futura
     * @param {string} serviceKey - Clave del servicio
     * @param {Object} [options] - Opciones
     * @param {number} [options.futureWindowDays=60] - Días futuros desde hoy
     * @returns {Promise<boolean>} true si el servicio tiene al menos una fecha disponible
     */
    async function hasAvailableDates(serviceKey, { futureWindowDays = 60 } = {}) {
        if (!serviceKey) return false;

        const { minDate, maxDate, startYmd, endYmd } = getRange(futureWindowDays);

        // Caso: Servicio fixed
        if (isFixedServiceKey(serviceKey)) {
            const schedule = getSchedule();
            for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
                const day = new Date(d);
                const weekday = window.DateUtils.getWeekdayName(day);
                const intervals = window.DateUtils.getDayIntervals(schedule, weekday);
                if (intervals.length > 0) {
                    return true; // Encontró al menos un día disponible
                }
            }
            return false; // No hay días disponibles
        }

        // Caso: Servicio por assignments
        if (typeof window.AAAssignmentsAvailability !== 'undefined' && 
            typeof window.AAAssignmentsAvailability.getAssignmentDatesByService === 'function') {
            try {
                const result = await window.AAAssignmentsAvailability.getAssignmentDatesByService(
                    serviceKey,
                    startYmd,
                    endYmd
                );
                
                if (result.success && Array.isArray(result.data?.dates) && result.data.dates.length > 0) {
                    // Verificar que al menos una fecha esté en el rango
                    const hasValidDate = result.data.dates.some(dateStr => {
                        return window.DateUtils.isDateInRange(dateStr, minDate, maxDate);
                    });
                    return hasValidDate;
                }
                return false;
            } catch (error) {
                console.error('[CalendarAvailabilityService] Error al evaluar disponibilidad de servicio:', serviceKey, error);
                return false;
            }
        } else {
            // Fallback permisivo con warn
            console.warn('[CalendarAvailabilityService] AAAssignmentsAvailability.getAssignmentDatesByService no disponible, retornando true (fallback permisivo)');
            return true;
        }
    }

    /**
     * Obtiene los días disponibles por servicio
     * @param {string} serviceKey - Clave del servicio (null/undefined para reset)
     * @param {Object} [options] - Opciones
     * @param {number} [options.futureWindowDays=60] - Días futuros desde hoy
     * @returns {Promise<Object>} { availableDays, minDate, maxDate }
     *   - availableDays: objeto { 'YYYY-MM-DD': true/false }
     *   - minDate: Date objeto
     *   - maxDate: Date objeto
     */
    async function getAvailableDaysByService(serviceKey, { futureWindowDays = 60 } = {}) {
        const { minDate, maxDate, startYmd, endYmd } = getRange(futureWindowDays);
        let availableDays = {};

        // Caso vacío o fixed → calcular desde schedule
        if (!serviceKey || isFixedServiceKey(serviceKey)) {
            const schedule = getSchedule();
            for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
                const day = new Date(d);
                const weekday = window.DateUtils.getWeekdayName(day);
                const dayKey = window.DateUtils.ymd(day);
                const intervals = window.DateUtils.getDayIntervals(schedule, weekday);
                availableDays[dayKey] = intervals.length > 0;
            }
        } else {
            // Servicio con assignments → obtener fechas de assignments
            if (typeof window.AAAssignmentsAvailability !== 'undefined' && 
                typeof window.AAAssignmentsAvailability.getAssignmentDatesByService === 'function') {
                try {
                    const result = await window.AAAssignmentsAvailability.getAssignmentDatesByService(
                        serviceKey,
                        startYmd,
                        endYmd
                    );
                    
                    if (result.success && Array.isArray(result.data?.dates)) {
                        result.data.dates.forEach(dateStr => {
                            if (window.DateUtils.isDateInRange(dateStr, minDate, maxDate)) {
                                availableDays[dateStr] = true;
                            }
                        });
                    }
                } catch (error) {
                    console.error('[CalendarAvailabilityService] Error al obtener fechas de assignments:', error);
                }
            }
        }

        return { availableDays, minDate, maxDate };
    }

    // ============================================
    // Exponer API pública
    // ============================================
    window.CalendarAvailabilityService = {
        hasAvailableDates,
        getAvailableDaysByService,
        isFixedServiceKey,
        getSchedule
    };

    console.log('✅ CalendarAvailabilityService cargado');

})();
