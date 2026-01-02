/**
 * Availability Assignments Service
 * 
 * Provides methods to query assignment-based availability.
 * This is a PARALLEL service - does NOT replace legacy AvailabilityService.
 * 
 * Exposes:
 * - window.AAAssignmentsAvailability
 * 
 * Methods:
 * - getAssignmentDates()
 * - getAssignmentDatesByService(serviceKey, startDate, endDate)
 * - getAssignmentsByServiceAndDate(serviceKey, date)
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

(function() {
    'use strict';

    console.log('🔄 [AAAssignmentsAvailability] Cargando módulo...');

    /**
     * Get AJAX URL
     * @returns {string}
     */
    function getAjaxUrl() {
        return window.ajaxurl || '/wp-admin/admin-ajax.php';
    }

    /**
     * Make AJAX request
     * @param {string} action - AJAX action name
     * @param {Object} data - Additional data to send
     * @returns {Promise<Object>}
     */
    async function ajaxRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        console.log(`📤 [AAAssignmentsAvailability] Enviando request: ${action}`, data);

        try {
            const response = await fetch(getAjaxUrl(), {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            console.log(`📥 [AAAssignmentsAvailability] Respuesta de ${action}:`, result);

            return result;
        } catch (error) {
            console.error(`❌ [AAAssignmentsAvailability] Error en ${action}:`, error);
            throw error;
        }
    }

    /**
     * Get all assignment dates
     * Returns all unique dates that have active assignments
     * 
     * @returns {Promise<Object>} Response with dates array
     */
    async function getAssignmentDates() {
        console.log('🔍 [AAAssignmentsAvailability] getAssignmentDates() llamado');
        
        const result = await ajaxRequest('aa_get_assignment_dates');
        
        if (result.success) {
            console.log(`✅ [AAAssignmentsAvailability] ${result.data.dates?.length || 0} fechas encontradas`);
        }
        
        return result;
    }

    /**
     * Get assignment dates by service
     * Returns dates that have assignments for a specific service
     * 
     * @param {string} serviceKey - Service identifier (e.g., 'consulta_general')
     * @param {string} startDate - Start date (YYYY-MM-DD), optional
     * @param {string} endDate - End date (YYYY-MM-DD), optional
     * @returns {Promise<Object>} Response with dates array
     */
    async function getAssignmentDatesByService(serviceKey, startDate = null, endDate = null) {
        console.log(`🔍 [AAAssignmentsAvailability] getAssignmentDatesByService("${serviceKey}", "${startDate}", "${endDate}") llamado`);
        
        const data = { service_key: serviceKey };
        
        if (startDate) {
            data.start_date = startDate;
        }
        if (endDate) {
            data.end_date = endDate;
        }
        
        const result = await ajaxRequest('aa_get_assignment_dates_by_service', data);
        
        if (result.success) {
            console.log(`✅ [AAAssignmentsAvailability] ${result.data.dates?.length || 0} fechas encontradas para servicio "${serviceKey}"`);
        }
        
        return result;
    }

    /**
     * Get assignments by service and date
     * Returns all assignments for a specific service on a specific date
     * 
     * @param {string} serviceKey - Service identifier
     * @param {string} date - Date (YYYY-MM-DD)
     * @returns {Promise<Object>} Response with assignments array
     */
    async function getAssignmentsByServiceAndDate(serviceKey, date) {
        console.log(`🔍 [AAAssignmentsAvailability] getAssignmentsByServiceAndDate("${serviceKey}", "${date}") llamado`);
        
        const result = await ajaxRequest('aa_get_assignments_by_service_and_date', {
            service_key: serviceKey,
            date: date
        });
        
        if (result.success) {
            console.log(`✅ [AAAssignmentsAvailability] ${result.data.assignments?.length || 0} asignaciones encontradas`);
        }
        
        return result;
    }

    /**
     * Get slots for staff and date from assignments
     * Calculates available time slots based on assignment intervals
     * 
     * @param {Array} assignments - Array of assignment objects with start_time and end_time
     * @param {string} date - Date in YYYY-MM-DD format
     * @param {number} slotDuration - Slot duration in minutes (default: 30)
     * @returns {Array<Date>|null} Array of slot Date objects, or null if error
     */
    function getSlotsForStaffAndDate(assignments, date, slotDuration = 30) {
        console.log('🔍 [AAAssignmentsAvailability] getSlotsForStaffAndDate() llamado', {
            assignmentsCount: assignments?.length || 0,
            date: date,
            slotDuration: slotDuration
        });

        // Validar inputs
        if (!assignments || !Array.isArray(assignments) || assignments.length === 0) {
            console.warn('[AAAssignmentsAvailability] No hay asignaciones para calcular slots');
            return null;
        }

        if (!date) {
            console.warn('[AAAssignmentsAvailability] No hay fecha para calcular slots');
            return null;
        }

        // Verificar que DateUtils esté disponible
        if (typeof window.DateUtils === 'undefined') {
            console.error('[AAAssignmentsAvailability] DateUtils no disponible');
            return null;
        }

        try {
            // 1️⃣ Construir intervalos desde assignments y normalizarlos a grilla de slots
            const normalizedIntervals = assignments.map(function(a) {
                if (!a.start_time || !a.end_time) {
                    console.warn('[AAAssignmentsAvailability] Asignación sin start_time o end_time:', a);
                    return null;
                }

                // Convertir a minutos
                const startMin = window.DateUtils.timeStrToMinutes(a.start_time);
                const endMin = window.DateUtils.timeStrToMinutes(a.end_time);

                // Normalizar a grilla de slots
                const normalized = window.DateUtils.normalizeIntervalToSlotGrid(
                    startMin,
                    endMin,
                    slotDuration
                );

                if (!normalized) {
                    console.warn('[AAAssignmentsAvailability] Asignación sin slots válidos después de normalizar:', {
                        start_time: a.start_time,
                        end_time: a.end_time,
                        startMin: startMin,
                        endMin: endMin
                    });
                }

                return normalized;
            }).filter(function(iv) {
                return iv !== null;
            });

            if (normalizedIntervals.length === 0) {
                console.warn('[AAAssignmentsAvailability] No se pudieron construir intervalos normalizados válidos');
                return null;
            }

            console.log('[AAAssignmentsAvailability] Intervalos normalizados:', normalizedIntervals);

            // 2️⃣ Crear objeto Date para la fecha
            const dateObj = new Date(date + 'T00:00:00');
            
            if (isNaN(dateObj.getTime())) {
                console.error('[AAAssignmentsAvailability] Fecha inválida:', date);
                return null;
            }

            // 3️⃣ Generar slots usando DateUtils.generateSlotsForDay con intervalos normalizados
            const slots = window.DateUtils.generateSlotsForDay(
                dateObj,
                normalizedIntervals,
                [],        // busyRanges vacío por ahora
                slotDuration
            );

            console.log('[AAAssignmentsAvailability] ✅ Slots generados:', slots.length);
            console.log('[AAAssignmentsAvailability] 🧮 Slots calculados:', 
                slots.map(function(s) {
                    return window.DateUtils.hm(s);
                })
            );

            return slots;

        } catch (error) {
            console.error('[AAAssignmentsAvailability] Error al calcular slots:', error);
            return null;
        }
    }

    /**
     * Check if a slot collides with any busy range
     * A slot is considered busy if: slotStart < busyEnd AND slotEnd > busyStart
     * 
     * @param {number} slotStartMin - Slot start in minutes from midnight
     * @param {number} slotDuration - Slot duration in minutes
     * @param {Array<{start: number, end: number}>} busyRanges - Busy ranges in minutes
     * @returns {boolean} True if slot is busy, false if available
     */
    function isSlotBusyByMinutes(slotStartMin, slotDuration, busyRanges) {
        const slotEndMin = slotStartMin + slotDuration;
        
        for (let i = 0; i < busyRanges.length; i++) {
            const busy = busyRanges[i];
            // Collision: slotStart < busyEnd AND slotEnd > busyStart
            if (slotStartMin < busy.end && slotEndMin > busy.start) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get available slots for a specific assignment
     * Generates normalized slots and subtracts busy ranges from reservations
     * 
     * @param {Object} options - Options object
     * @param {number} options.assignmentId - Assignment ID
     * @param {string} options.date - Date (YYYY-MM-DD)
     * @param {number} options.slotDuration - Slot duration in minutes (default: 30)
     * @param {Object} options.assignment - Optional: pre-loaded assignment object with start_time, end_time
     * @returns {Promise<Array<string>|null>} Array of available slot times as HH:MM strings, or null on error
     */
    async function getAvailableSlotsForAssignment(options) {
        const { assignmentId, date, slotDuration = 30, assignment } = options;

        console.log('🔍 [AAAssignmentsAvailability] getAvailableSlotsForAssignment() llamado', {
            assignmentId: assignmentId,
            date: date,
            slotDuration: slotDuration
        });

        // Validaciones
        if (!assignmentId) {
            console.warn('[AAAssignmentsAvailability] assignmentId es requerido');
            return null;
        }

        if (!date) {
            console.warn('[AAAssignmentsAvailability] date es requerido');
            return null;
        }

        // Verificar que DateUtils esté disponible
        if (typeof window.DateUtils === 'undefined') {
            console.error('[AAAssignmentsAvailability] DateUtils no disponible');
            return null;
        }

        // Verificar que AABusyRangesAssignments esté disponible
        if (typeof window.AABusyRangesAssignments === 'undefined' || 
            typeof window.AABusyRangesAssignments.getBusyRangesByAssignmentIds === 'undefined') {
            console.error('[AAAssignmentsAvailability] AABusyRangesAssignments.getBusyRangesByAssignmentIds no disponible');
            return null;
        }

        try {
            // 1️⃣ Generar slots iniciales desde el assignment
            let assignmentData = assignment;
            
            // Si no se pasó el assignment, necesitamos obtener los datos del intervalo
            if (!assignmentData) {
                // Buscar en caché o hacer llamada AJAX para obtener los datos del assignment
                // Por ahora, asumimos que se pasa el assignment
                console.error('[AAAssignmentsAvailability] Se requiere pasar el objeto assignment con start_time y end_time');
                return null;
            }

            if (!assignmentData.start_time || !assignmentData.end_time) {
                console.warn('[AAAssignmentsAvailability] Assignment sin start_time o end_time');
                return null;
            }

            // Convertir a minutos y normalizar
            const startMin = window.DateUtils.timeStrToMinutes(assignmentData.start_time);
            const endMin = window.DateUtils.timeStrToMinutes(assignmentData.end_time);
            
            const normalizedInterval = window.DateUtils.normalizeIntervalToSlotGrid(
                startMin,
                endMin,
                slotDuration
            );

            if (!normalizedInterval) {
                console.warn('[AAAssignmentsAvailability] No se pudo normalizar el intervalo del assignment');
                return null;
            }

            // Generar slots iniciales (solo los minutos)
            const initialSlots = [];
            for (let min = normalizedInterval.start; min <= normalizedInterval.end; min += slotDuration) {
                initialSlots.push(min);
            }

            console.log('[AAAssignmentsAvailability] Slots iniciales (minutos):', initialSlots);
            console.log('[AAAssignmentsAvailability] Slots iniciales:', initialSlots.map(function(min) {
                const hh = String(Math.floor(min / 60)).padStart(2, '0');
                const mm = String(min % 60).padStart(2, '0');
                return hh + ':' + mm;
            }));

            // 2️⃣ Obtener busy ranges desde reservas existentes
            const busyRanges = await window.AABusyRangesAssignments.getBusyRangesByAssignmentIds(
                [assignmentId],
                date
            );

            console.log('[AAAssignmentsAvailability] Busy ranges:', busyRanges);

            if (busyRanges === null) {
                console.error('[AAAssignmentsAvailability] Error al obtener busy ranges');
                return null;
            }

            // 3️⃣ Filtrar slots que colisionan con busy ranges
            const availableSlots = initialSlots.filter(function(slotMin) {
                return !isSlotBusyByMinutes(slotMin, slotDuration, busyRanges);
            });

            // 4️⃣ Convertir a formato HH:MM
            const availableSlotsFormatted = availableSlots.map(function(min) {
                const hh = String(Math.floor(min / 60)).padStart(2, '0');
                const mm = String(min % 60).padStart(2, '0');
                return hh + ':' + mm;
            });

            console.log('[AAAssignmentsAvailability] Slots disponibles finales:', availableSlotsFormatted);

            return availableSlotsFormatted;

        } catch (error) {
            console.error('[AAAssignmentsAvailability] Error al calcular slots disponibles:', error);
            return null;
        }
    }

    // ============================================
    // Expose to global namespace
    // ============================================
    window.AAAssignmentsAvailability = {
        getAssignmentDates: getAssignmentDates,
        getAssignmentDatesByService: getAssignmentDatesByService,
        getAssignmentsByServiceAndDate: getAssignmentsByServiceAndDate,
        getSlotsForStaffAndDate: getSlotsForStaffAndDate,
        getAvailableSlotsForAssignment: getAvailableSlotsForAssignment
    };

    console.log('✅ [AAAssignmentsAvailability] Módulo cargado y expuesto en window.AAAssignmentsAvailability');
    console.log('📋 Métodos disponibles:');
    console.log('   - AAAssignmentsAvailability.getAssignmentDates()');
    console.log('   - AAAssignmentsAvailability.getAssignmentDatesByService(serviceKey, startDate?, endDate?)');
    console.log('   - AAAssignmentsAvailability.getAssignmentsByServiceAndDate(serviceKey, date)');
    console.log('   - AAAssignmentsAvailability.getSlotsForStaffAndDate(assignments, date, slotDuration?)');
    console.log('   - AAAssignmentsAvailability.getAvailableSlotsForAssignment({ assignmentId, date, slotDuration?, assignment })');

})();

