/**
 * GUARDRAIL — Assignment-based Availability Service
 *
 * Motor: Reservation / public calendar (assignment-based availability)
 *
 * In this motor, assignments ARE the source of availability.
 * Dates and time slots are derived from active assignments.
 *
 * DO NOT reuse this service for Fast Appointment.
 * Fast Appointment has its own availability motor where assignments
 * are only a bridge (assignment_id → staff_id), not a source of
 * availability.  See fastAppointmentTimeAvailabilityService.js.
 *
 * See docs/fast-appointment-vs-assignment-availability.md
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

        try {
            const response = await fetch(getAjaxUrl(), {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

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
        // Detectar si serviceKey es numérico (service_id) o string (service_key legacy)
        const maybeId = parseInt(serviceKey, 10);
        const isNumeric = !isNaN(maybeId) && String(maybeId) === String(serviceKey);
        
        const data = {};
        
        // Si es numérico, enviar service_id; si no, enviar service_key (legacy)
        if (isNumeric) {
            data.service_id = maybeId;
        } else {
            data.service_key = serviceKey;
            console.log(`📊 [AAAssignmentsAvailability] Usando service_key legacy: "${serviceKey}"`);
        }
        
        if (startDate) {
            data.start_date = startDate;
        }
        if (endDate) {
            data.end_date = endDate;
        }
        
        const result = await ajaxRequest('aa_get_assignment_dates_by_service', data);

        return result;
    }

    /**
     * Get assignments by service and date
     * Returns all assignments for a specific service on a specific date
     * 
     * @param {string} serviceKey - Service identifier (can be numeric service_id or string service_key)
     * @param {string} date - Date (YYYY-MM-DD)
     * @returns {Promise<Object>} Response with assignments array
     */
    async function getAssignmentsByServiceAndDate(serviceKey, date) {
        console.log(`🔍 [AAAssignmentsAvailability] getAssignmentsByServiceAndDate("${serviceKey}", "${date}") llamado`);
        
        // Detectar si serviceKey es numérico (service_id) o string (service_key legacy)
        const maybeId = parseInt(serviceKey, 10);
        const isNumeric = !isNaN(maybeId) && String(maybeId) === String(serviceKey);
        
        const data = { date: date };
        
        // Si es numérico, enviar service_id; si no, enviar service_key (legacy)
        if (isNumeric) {
            data.service_id = maybeId;
        } else {
            data.service_key = serviceKey;
            console.log(`📊 [AAAssignmentsAvailability] Usando service_key legacy: "${serviceKey}"`);
        }
        
        const result = await ajaxRequest('aa_get_assignments_by_service_and_date', data);
        
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

                // Normalizar a grilla fija de 30 min (NO usar slotDuration aquí)
                // La validación de si cabe la duración se hace en generateSlotsForDay()
                const normalized = window.DateUtils.normalizeIntervalToSlotGrid(
                    startMin,
                    endMin,
                    30  // Grilla fija de 30 min
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
     * Get final slots for staff and date (after filtering by busy ranges)
     * This method unifies the slot calculation logic for assignments
     * 
     * @param {Array} assignments - Array of assignment objects with start_time and end_time
     * @param {string} date - Date in YYYY-MM-DD format
     * @param {number} slotDuration - Slot duration in minutes (default: 30)
     * @param {number|string|null} selectedAssignmentId - Optional: filter busy ranges to only this assignment
     * @returns {Promise<Object>} { baseSlots: Array<Date>, finalSlots: Array<Date>, busyRanges: Array }
     */
    async function getFinalSlotsForStaffAndDate(assignments, date, slotDuration = 30, selectedAssignmentId = null) {
        console.log('🔍 [AAAssignmentsAvailability] getFinalSlotsForStaffAndDate() llamado', {
            assignmentsCount: assignments?.length || 0,
            date: date,
            slotDuration: slotDuration,
            selectedAssignmentId: selectedAssignmentId
        });

        try {
            // 1️⃣ Obtener slots base
            const baseSlots = getSlotsForStaffAndDate(assignments, date, slotDuration);
            
            if (!baseSlots || baseSlots.length === 0) {
                console.log('[AAAssignmentsAvailability] No hay slots base, retornando vacío');
                return { baseSlots: [], finalSlots: [], busyRanges: [] };
            }

            // 2️⃣ Extraer assignment IDs
            const assignmentIds = assignments.map(a => parseInt(a.id)).filter(Number.isFinite);
            
            if (assignmentIds.length === 0) {
                console.warn('[AAAssignmentsAvailability] No hay assignment IDs válidos');
                return { baseSlots, finalSlots: baseSlots, busyRanges: [] };
            }

            // 3️⃣ Obtener busy ranges externos por assignments
            let rawBusyRanges = [];
            if (typeof window.AABusyRangesAssignments !== 'undefined' && 
                typeof window.AABusyRangesAssignments.getBusyRangesByAssignments === 'function') {
                try {
                    const res = await window.AABusyRangesAssignments.getBusyRangesByAssignments(assignmentIds, date);
                    rawBusyRanges = res?.data?.busy_ranges || [];
                } catch (error) {
                    console.error('[AAAssignmentsAvailability] Error al obtener busy ranges externos:', error);
                    // Continuar con busy ranges locales únicamente
                }
            }

            // 3.1️⃣ Filtrar busy ranges por selectedAssignmentId SOLO si hay exactamente 1 assignment
            // Si hay múltiples assignments del mismo staff, queremos considerar busy ranges de TODAS
            // ya que los slots base se generan combinando intervalos de múltiples assignments
            if (selectedAssignmentId !== null && rawBusyRanges.length > 0 && assignments.length === 1) {
                const selectedId = parseInt(selectedAssignmentId, 10);
                const beforeFilter = rawBusyRanges.length;
                
                rawBusyRanges = rawBusyRanges.filter(function(range) {
                    // Solo incluir rangos que pertenecen a la asignación seleccionada
                    return parseInt(range.assignment_id, 10) === selectedId;
                });
                
                console.log('[AAAssignmentsAvailability] 🔍 Filtrado busy ranges por assignment_id (single assignment):', {
                    selectedAssignmentId: selectedId,
                    antes: beforeFilter,
                    despues: rawBusyRanges.length,
                    filtrados: beforeFilter - rawBusyRanges.length
                });
            } else if (assignments.length > 1) {
                console.log('[AAAssignmentsAvailability] 📊 Múltiples assignments detectados, usando busy ranges de TODAS:', {
                    assignmentsCount: assignments.length,
                    busyRangesCount: rawBusyRanges.length,
                    assignmentIds: assignments.map(a => a.id)
                });
            }

            // 4️⃣ Convertir a rangos Date
            let busyExternal = [];
            if (typeof window.BusyRanges !== 'undefined' && 
                typeof window.BusyRanges.toDateRanges === 'function') {
                busyExternal = window.BusyRanges.toDateRanges(rawBusyRanges);
            } else {
                // Fallback: convertir manualmente si BusyRanges.toDateRanges no está disponible
                busyExternal = rawBusyRanges.map(function(range) {
                    return {
                        start: new Date(range.start),
                        end: new Date(range.end),
                        title: range.title || 'Ocupado'
                    };
                }).filter(function(r) {
                    return !isNaN(r.start.getTime()) && !isNaN(r.end.getTime());
                });
            }

            // 🚫 NO incluir busy ranges locales (fixed) en flujo de assignments
            // Los busy ranges locales representan reservas con assignment_id IS NULL (fixed/legacy)
            // En el flujo de assignments solo importan los busy ranges de las asignaciones específicas
            const busyLocal = [];
            
            // Solo usar busy ranges externos (por assignment IDs)
            const busyRanges = busyExternal;
            
            console.log('[AAAssignmentsAvailability] 📊 Busy ranges (solo externos, sin fixed):', {
                busyExternal: busyExternal.length,
                busyLocal: 0,
                nota: 'busyLocal excluido intencionalmente en flujo assignments'
            });

            // 5️⃣ Filtrar slots
            let finalSlots = baseSlots;
            if (typeof window.BusyRanges !== 'undefined' && 
                typeof window.BusyRanges.filterSlotsByBusyRanges === 'function') {
                finalSlots = window.BusyRanges.filterSlotsByBusyRanges(baseSlots, slotDuration, busyRanges);
            } else {
                // Fallback: filtrar manualmente si BusyRanges.filterSlotsByBusyRanges no está disponible
                if (typeof window.DateUtils !== 'undefined' && 
                    typeof window.DateUtils.hasEnoughFreeTime === 'function') {
                    finalSlots = baseSlots.filter(function(slot) {
                        return window.DateUtils.hasEnoughFreeTime(slot, slotDuration, busyRanges);
                    });
                } else {
                    // Si no hay DateUtils, retornar todos los slots base
                    console.warn('[AAAssignmentsAvailability] DateUtils.hasEnoughFreeTime no disponible, retornando todos los slots base');
                    finalSlots = baseSlots;
                }
            }

            console.log('[AAAssignmentsAvailability] ✅ Slots finales calculados:', {
                baseSlots: baseSlots.length,
                finalSlots: finalSlots.length,
                busyRanges: busyRanges.length,
                busyLocal: busyLocal.length,
                busyExternal: busyExternal.length,
                selectedAssignmentId: selectedAssignmentId
            });

            return { baseSlots, finalSlots, busyRanges };

        } catch (error) {
            console.error('[AAAssignmentsAvailability] Error al calcular slots finales:', error);
            // Fallback: retornar slots base sin filtrar
            const baseSlots = getSlotsForStaffAndDate(assignments, date, slotDuration) || [];
            return { baseSlots, finalSlots: baseSlots, busyRanges: [] };
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
        getFinalSlotsForStaffAndDate: getFinalSlotsForStaffAndDate
    };

})();

