/**
 * Admin Reservation Assignment Flow Controller
 * 
 * Handles the assignment-based flow (Service → Date → Staff → Slots) within the admin reservation modal.
 * Encapsulates service selection, date selection, staff selection, assignment loading, and slot calculation.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

(function() {
    'use strict';

    /**
     * Create a new AdminReservationAssignmentFlowController instance
     * @param {Object} opts - Configuration options
     * @param {Function} opts.getState - Function that returns the state object
     * @param {Function} [opts.setState] - Optional function to update state (if not provided, mutate directly)
     * @param {Object} opts.elements - Element IDs
     * @param {string} opts.elements.serviceSelectId - ID of the service select element
     * @param {string} opts.elements.staffSelectId - ID of the staff select element
     * @param {Object} opts.callbacks - Callback functions
     * @param {Function} opts.callbacks.hideStaffSelector - Hide staff selector
     * @param {Function} opts.callbacks.showStaffSelector - Show staff selector
     * @param {Function} opts.callbacks.resetStaffSelect - Reset staff select to initial state
     * @param {Function} opts.callbacks.updateAssignmentIdInput - Update assignment_id input
     * @param {Function} opts.callbacks.renderAssignmentSlots - Render slots (slotStrings, date, assignmentId)
     * @param {Function} [opts.callbacks.log] - Optional log wrapper function
     * @param {Function} [opts.callbacks.refreshAdminCalendarByService] - Optional callback to refresh calendar by service
     * @returns {Object} Controller instance with destroy() method
     */
    function createController(opts) {
        const {
            getState,
            setState = null,
            elements: {
                serviceSelectId = 'cita-servicio',
                staffSelectId = 'aa-reservation-staff'
            },
            callbacks: {
                hideStaffSelector,
                showStaffSelector,
                resetStaffSelect,
                updateAssignmentIdInput,
                renderAssignmentSlots,
                log = function(msg, ...args) { console.log(msg, ...args); },
                refreshAdminCalendarByService = null
            }
        } = opts;

        // Get DOM elements
        const serviceSelect = document.getElementById(serviceSelectId);
        const staffSelect = document.getElementById(staffSelectId);
        const fechaInput = document.getElementById('cita-fecha');
        const duracionSelect = document.getElementById('cita-duracion');

        if (!serviceSelect || !fechaInput || !staffSelect) {
            console.warn('[AdminReservationAssignmentFlowController] Elementos del flujo de asignaciones no encontrados');
            return null;
        }

        if (!duracionSelect) {
            console.warn('[AdminReservationAssignmentFlowController] Select de duración no encontrado (#cita-duracion)');
        }

        // Helper: Detectar si un servicio es de horario fijo
        function isFixedService(serviceKey) {
            // Delegar a CalendarAvailabilityService si está disponible
            if (window.CalendarAvailabilityService && window.CalendarAvailabilityService.isFixedServiceKey) {
                return window.CalendarAvailabilityService.isFixedServiceKey(serviceKey);
            }
            // Fallback local
            return typeof serviceKey === 'string' && serviceKey.startsWith('fixed::');
        }

        // Helper: Obtener duración seleccionada del select
        function getSelectedDurationMinutes() {
            if (!duracionSelect) {
                // Fallback si el select no existe
                return window.aa_slot_duration || 60;
            }
            const v = parseInt(duracionSelect.value, 10);
            if (isNaN(v) || v <= 0) {
                // Fallback si el valor es inválido
                return window.aa_slot_duration || 60;
            }
            return v;
        }

        // Event handlers (will be stored for cleanup)
        let handleServiceChange = null;
        let handleStaffChange = null;
        let handleDateSelected = null;
        let handleDurationChange = null;
        let dateSelectedBound = false;

        /**
         * Load assignments for service and date using AAAssignmentsAvailability
         * @param {string} serviceKey 
         * @param {string} date 
         */
        async function loadAssignmentsForServiceAndDate(serviceKey, date) {
            // Show loading state
            staffSelect.disabled = true;
            staffSelect.innerHTML = '<option value="">Cargando personal...</option>';

            try {
                // Check if AAAssignmentsAvailability is available
                if (typeof window.AAAssignmentsAvailability === 'undefined') {
                    console.warn('[AdminReservationAssignmentFlowController] AAAssignmentsAvailability no disponible');
                    staffSelect.innerHTML = '<option value="">Sistema de asignaciones no disponible</option>';
                    return;
                }

                // Call the service
                const result = await window.AAAssignmentsAvailability.getAssignmentsByServiceAndDate(serviceKey, date);

                log('[AdminReservationAssignmentFlowController] Resultado de asignaciones:', result);

                if (result.success && result.data && result.data.assignments) {
                    // Update state
                    const state = getState();
                    if (setState) {
                        setState({ currentAssignments: result.data.assignments });
                    } else {
                        state.currentAssignments = result.data.assignments;
                    }
                    populateStaffSelect(result.data.assignments);
                } else {
                    // Update state
                    const state = getState();
                    if (setState) {
                        setState({ currentAssignments: [] });
                    } else {
                        state.currentAssignments = [];
                    }
                    staffSelect.innerHTML = '<option value="">No hay personal disponible</option>';
                    log('[AdminReservationAssignmentFlowController] No hay asignaciones para este servicio y fecha');
                }
            } catch (error) {
                console.error('[AdminReservationAssignmentFlowController] Error al cargar asignaciones:', error);
                staffSelect.innerHTML = '<option value="">Error al cargar personal</option>';
            }
        }

        /**
         * Populate staff select with unique staff from assignments
         * @param {Array} assignments 
         */
        function populateStaffSelect(assignments) {
            // Asegurar que el selector esté visible (para servicios por assignments)
            showStaffSelector();

            // Extract unique staff
            const staffMap = new Map();
            
            assignments.forEach(function(assignment) {
                if (assignment.staff_id && assignment.staff_name) {
                    staffMap.set(assignment.staff_id, assignment.staff_name);
                }
            });

            log('[AdminReservationAssignmentFlowController] Staff únicos encontrados:', staffMap.size);

            if (staffMap.size === 0) {
                staffSelect.innerHTML = '<option value="">No hay personal disponible</option>';
                staffSelect.disabled = true;
                
                // Update state
                const state = getState();
                if (setState) {
                    setState({ selectedStaff: null, currentAssignments: [], selectedAssignmentId: null });
                } else {
                    state.selectedStaff = null;
                    state.currentAssignments = [];
                    state.selectedAssignmentId = null;
                }
                updateAssignmentIdInput(null);
                return;
            }

            // Build options
            let html = '<option value="">-- Selecciona personal --</option>';
            
            staffMap.forEach(function(name, id) {
                html += '<option value="' + id + '">' + name + '</option>';
            });

            staffSelect.innerHTML = html;
            staffSelect.disabled = false;

            log('[AdminReservationAssignmentFlowController] ✅ Select de staff poblado con', staffMap.size, 'opciones');

            // Auto-seleccionar la primera opción válida si existe
            const validOptions = Array.from(staffSelect.options).filter(opt => opt.value !== '');
            if (validOptions.length > 0) {
                const firstOption = validOptions[0];
                staffSelect.value = firstOption.value;
                
                // Update state
                const state = getState();
                if (setState) {
                    setState({ selectedStaff: firstOption.value });
                } else {
                    state.selectedStaff = firstOption.value;
                }
                
                log('[AdminReservationAssignmentFlowController] 🔄 Auto-seleccionando primer staff:', firstOption.value, '-', firstOption.text);
                
                // Ejecutar el mismo flujo que ocurre en una selección manual
                handleStaffSelection(firstOption.value);
            }
        }

        /**
         * Check if both service and date are selected, then load assignments
         */
        async function checkAndLoadAssignments() {
            const state = getState();
            const { selectedService, selectedDate } = state;

            log('[AdminReservationAssignmentFlowController] Verificando:', { selectedService, selectedDate });

            if (!selectedService || !selectedDate) {
                log('[AdminReservationAssignmentFlowController] Faltan datos para cargar asignaciones');
                resetStaffSelect();
                return;
            }

            // Both are defined, load assignments
            log('[AdminReservationAssignmentFlowController] ✅ Servicio y fecha definidos, cargando asignaciones...');
            
            await loadAssignmentsForServiceAndDate(selectedService, selectedDate);
        }

        /**
         * Calculate slots for fixed service in admin
         * @param {string} selectedDate - Date in YYYY-MM-DD format
         */
        function calculateFixedSlotsForAdmin(selectedDate) {
            console.group('[AdminReservationAssignmentFlowController][FIXED] Calculando slots de horario fijo...');
            const state = getState();
            log('[AdminReservationAssignmentFlowController][FIXED] Servicio:', state.selectedService);
            log('[AdminReservationAssignmentFlowController][FIXED] Fecha:', selectedDate);

            try {
                // Validar dependencias necesarias
                if (typeof window.SlotCalculator === 'undefined') {
                    console.error('[AdminReservationAssignmentFlowController][FIXED] ❌ SlotCalculator no disponible');
                    console.groupEnd();
                    return;
                }

                if (typeof window.DateUtils === 'undefined') {
                    console.error('[AdminReservationAssignmentFlowController][FIXED] ❌ DateUtils no disponible');
                    console.groupEnd();
                    return;
                }

                // 1️⃣ Obtener schedule y configuración
                const schedule = window.aa_schedule || {};
                const slotDuration = getSelectedDurationMinutes();

                log('[AdminReservationAssignmentFlowController][FIXED] Configuración:', {
                    schedule: schedule,
                    slotDuration: slotDuration
                });

                // 2️⃣ Construir busyRanges (solo locales)
                log('[AdminReservationAssignmentFlowController][FIXED] Obteniendo busy ranges...');
                
                const built = (window.BusyRanges && window.BusyRanges.buildBusyRanges)
                    ? window.BusyRanges.buildBusyRanges()
                    : { busyRanges: [] };

                const { busyRanges } = built;
                
                log('[AdminReservationAssignmentFlowController][FIXED] Busy Ranges obtenidos:', {
                    total: busyRanges.length
                });
                
                // Loggear rangos en HH:MM para debug (solo si hay rangos)
                if (busyRanges.length > 0) {
                    log('[AdminReservationAssignmentFlowController][FIXED] Rangos ocupados:');
                    busyRanges.forEach(function(r) {
                        const startStr = r.start ? window.DateUtils.hm(r.start) : 'N/A';
                        const endStr = r.end ? window.DateUtils.hm(r.end) : 'N/A';
                        log('   - ' + startStr + ' - ' + endStr);
                    });
                }

                // 3️⃣ Crear objeto Date para la fecha seleccionada
                const selectedDateObj = new Date(selectedDate + 'T00:00:00');

                // 4️⃣ Calcular slots para la fecha específica usando SlotCalculator
                log('[AdminReservationAssignmentFlowController][FIXED] Calculando slots con busy ranges...');
                
                const slots = window.SlotCalculator.calculateSlotsForDate(
                    selectedDateObj,
                    schedule,
                    busyRanges,
                    slotDuration
                );

                log('[AdminReservationAssignmentFlowController][FIXED] Slots calculados:', slots ? slots.length : 0);

                if (slots && slots.length > 0) {
                    // Convertir slots Date a formato "HH:MM"
                    const slotsHHMM = slots.map(function(slot) {
                        if (slot instanceof Date) {
                            return window.DateUtils.hm(slot);
                        }
                        return String(slot);
                    });

                    log('[AdminReservationAssignmentFlowController][FIXED] Horarios disponibles:', slotsHHMM);

                    // Renderizar slots en el formato del admin
                    renderAssignmentSlots(slotsHHMM, selectedDate, null);
                } else {
                    log('[AdminReservationAssignmentFlowController][FIXED] ❌ No hay horarios disponibles para esta fecha');
                    // Limpiar contenedor de slots
                    const container = document.getElementById('slot-container-admin');
                    if (container) {
                        container.innerHTML = 'No hay horarios disponibles para esta fecha.';
                    }
                }

                log('[AdminReservationAssignmentFlowController][FIXED] ✅ Cálculo completado');
                console.groupEnd();

            } catch (error) {
                console.error('[AdminReservationAssignmentFlowController][FIXED] ❌ Error al calcular slots fijos:', error);
                console.groupEnd();
            }
        }

        /**
         * Handle staff selection and calculate slots
         * @param {string} selectedStaffId - Selected staff ID
         */
        async function handleStaffSelection(selectedStaffId) {
            const state = getState();
            const { selectedDate, currentAssignments } = state;

            if (!selectedStaffId) {
                log('[AdminReservationAssignmentFlowController] Staff deseleccionado');
                return;
            }

            // Update state
            if (setState) {
                setState({ selectedStaff: selectedStaffId });
            } else {
                state.selectedStaff = selectedStaffId;
            }

            // Filter assignments for selected staff
            const staffAssignments = currentAssignments.filter(function(a) {
                return String(a.staff_id) === String(selectedStaffId);
            });

            log('[AdminReservationAssignmentFlowController] Staff seleccionado:', selectedStaffId);
            log('[AdminReservationAssignmentFlowController] Asignaciones completas:', staffAssignments);

            // Guardar staffAssignments en contexto global para que reservation.js pueda recalcular assignment_id por slot
            window.AA_RESERVATION_CTX = window.AA_RESERVATION_CTX || {};
            window.AA_RESERVATION_CTX.staffAssignments = staffAssignments || [];
            window.AA_RESERVATION_CTX.selectedDate = selectedDate; // YYYY-MM-DD
            log('[AdminReservationAssignmentFlowController] Contexto global actualizado:', window.AA_RESERVATION_CTX);

            // Guardar assignment_id (por ahora usamos el primer assignment si hay múltiples)
            let assignmentId = null;
            if (staffAssignments && staffAssignments.length > 0) {
                const firstAssignment = staffAssignments[0];
                assignmentId = firstAssignment.id;
                
                // Update state
                if (setState) {
                    setState({ selectedAssignmentId: assignmentId });
                } else {
                    state.selectedAssignmentId = assignmentId;
                }
                updateAssignmentIdInput(assignmentId);
                log('[AdminReservationAssignmentFlowController] Assignment ID seleccionado:', assignmentId);
            } else {
                // Update state
                if (setState) {
                    setState({ selectedAssignmentId: null });
                } else {
                    state.selectedAssignmentId = null;
                }
                updateAssignmentIdInput(null);
            }
            
            // Log details for debugging
            staffAssignments.forEach(function(assignment, index) {
                log('[AdminReservationAssignmentFlowController] Asignación #' + (index + 1) + ':', {
                    id: assignment.id,
                    start: assignment.start_time,
                    end: assignment.end_time,
                    staff: assignment.staff_name,
                    area: assignment.service_area_name,
                    capacity: assignment.capacity
                });
            });

            // ============================================
            // 🧮 CALCULAR SLOTS FINALES (base + filtrado por busy ranges)
            // ============================================
            // appointmentDuration = duración de la cita (30/60/90). El grid interno sigue siendo 30.
            const appointmentDuration = getSelectedDurationMinutes();
            
            // Usar el servicio unificado para obtener slots finales
            if (typeof window.AAAssignmentsAvailability !== 'undefined' && 
                typeof window.AAAssignmentsAvailability.getFinalSlotsForStaffAndDate === 'function') {
                
                try {
                    // Pasar assignmentId como 4to param para filtrar busy ranges solo de esta asignación
                    const result = await window.AAAssignmentsAvailability.getFinalSlotsForStaffAndDate(
                        staffAssignments,
                        selectedDate,
                        appointmentDuration,
                        assignmentId  // Solo considerar reservas de esta asignación específica
                    );
                    
                    const finalSlots = result.finalSlots || [];
                    const baseSlots = result.baseSlots || [];
                    const busyRanges = result.busyRanges || [];
                    
                    log('[AdminReservationAssignmentFlowController] 📊 Slots calculados:', {
                        staffAssignments: staffAssignments.length,
                        baseSlots: baseSlots.length,
                        finalSlots: finalSlots.length,
                        busyRanges: busyRanges.length
                    });
                    
                    // Convertir slots Date a formato "HH:MM" para renderAssignmentSlots
                    const finalSlotStrings = finalSlots.map(function(slot) {
                        if (slot instanceof Date) {
                            return window.DateUtils.hm(slot);
                        }
                        return String(slot);
                    });
                    
                    // Renderizar slots finales
                    renderAssignmentSlots(finalSlotStrings, selectedDate, assignmentId);
                } catch (error) {
                    console.error('[AdminReservationAssignmentFlowController] Error al calcular slots finales:', error);
                    // Fallback seguro: usar getSlotsForStaffAndDate sin filtrado
                    if (typeof window.AAAssignmentsAvailability.getSlotsForStaffAndDate === 'function') {
                        const baseSlots = window.AAAssignmentsAvailability.getSlotsForStaffAndDate(
                            staffAssignments,
                            selectedDate,
                            appointmentDuration
                        );
                        if (baseSlots && baseSlots.length > 0) {
                            const baseSlotStrings = baseSlots.map(function(s) {
                                return window.DateUtils.hm(s);
                            });
                            renderAssignmentSlots(baseSlotStrings, selectedDate, assignmentId);
                        }
                    }
                }
            } else {
                // Fallback seguro si el service no existe
                console.warn('[AdminReservationAssignmentFlowController] AAAssignmentsAvailability.getFinalSlotsForStaffAndDate no disponible, usando fallback');
                if (typeof window.AAAssignmentsAvailability !== 'undefined' && 
                    typeof window.AAAssignmentsAvailability.getSlotsForStaffAndDate === 'function') {
                    const baseSlots = window.AAAssignmentsAvailability.getSlotsForStaffAndDate(
                        staffAssignments,
                        selectedDate,
                        appointmentDuration
                    );
                    if (baseSlots && baseSlots.length > 0) {
                        const baseSlotStrings = baseSlots.map(function(s) {
                            return window.DateUtils.hm(s);
                        });
                        renderAssignmentSlots(baseSlotStrings, selectedDate, assignmentId);
                    }
                }
            }
        }

        // Initialize event handlers

        // Listen for service changes
        handleServiceChange = async function() {
            const state = getState();
            const serviceKey = this.value;
            
            // Update state
            if (setState) {
                setState({ selectedService: serviceKey });
            } else {
                state.selectedService = serviceKey;
            }
            
            log('[AdminReservationAssignmentFlowController] Servicio seleccionado:', serviceKey);
            
            // Actualizar disponibilidad del calendario según el servicio (si callback está disponible)
            if (refreshAdminCalendarByService && typeof refreshAdminCalendarByService === 'function') {
                await refreshAdminCalendarByService(serviceKey);
            } else if (typeof window.CalendarAvailabilityService !== 'undefined' && 
                       typeof window.CalendarAvailabilityService.getAvailableDaysByService === 'function') {
                // Fallback: actualizar calendario directamente
                const futureWindow = window.aa_future_window || 14;
                const { availableDays, minDate, maxDate } = await window.CalendarAvailabilityService.getAvailableDaysByService(serviceKey, { futureWindowDays: futureWindow });
                
                const fechaInputEl = document.getElementById('cita-fecha');
                if (fechaInputEl && typeof window.CalendarAdminUI !== 'undefined' && typeof window.CalendarAdminUI.render === 'function') {
                    const disableDateFn = (date) => {
                        const dayKey = window.DateUtils.ymd(date);
                        return !availableDays[dayKey];
                    };
                    
                    const emptySlotsMap = {};
                    Object.keys(availableDays).forEach(day => {
                        emptySlotsMap[day] = [];
                    });
                    
                    const picker = window.CalendarAdminUI.render({
                        fechaInput: fechaInputEl,
                        slotContainerSelector: 'slot-container-admin',
                        slotsMap: emptySlotsMap,
                        minDate: minDate,
                        maxDate: maxDate,
                        disableDateFn: disableDateFn
                    });
                    
                    // Auto-seleccionar primera fecha válida
                    if (picker && typeof picker.setDate === 'function') {
                        let firstAvailableDate = null;
                        for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
                            const day = new Date(d);
                            const dayKey = window.DateUtils.ymd(day);
                            if (availableDays[dayKey] === true) {
                                firstAvailableDate = day;
                                break;
                            }
                        }
                        if (firstAvailableDate) {
                            picker.setDate(firstAvailableDate, true);
                        }
                    }
                }
            }
            
            // Detectar si es servicio fixed
            if (isFixedService(serviceKey)) {
                log('[AdminReservationAssignmentFlowController] 🔧 Servicio fixed detectado');
                // Ocultar selector de staff (no se usa para servicios fixed)
                hideStaffSelector();
                // Resetear staff select
                resetStaffSelect();
                // Si ya hay fecha seleccionada, calcular slots inmediatamente
                const currentState = getState();
                if (currentState.selectedDate) {
                    calculateFixedSlotsForAdmin(currentState.selectedDate);
                }
                // NO llamar checkAndLoadAssignments para servicios fixed
            } else {
                // Comportamiento normal para servicios con assignments
                // Mostrar selector de staff
                showStaffSelector();
                await checkAndLoadAssignments();
            }
        };
        serviceSelect.addEventListener('change', handleServiceChange);

        // Listen for staff selection
        handleStaffChange = function() {
            const selectedStaffId = this.value;
            handleStaffSelection(selectedStaffId);
        };
        staffSelect.addEventListener('change', handleStaffChange);

        // Listen for duration changes
        if (duracionSelect) {
            handleDurationChange = function() {
                const state = getState();
                
                // Solo recalcular si hay fecha y servicio seleccionados
                if (!state.selectedDate || !state.selectedService) {
                    return;
                }
                
                // Si es servicio fixed, recalcular slots
                if (isFixedService(state.selectedService)) {
                    calculateFixedSlotsForAdmin(state.selectedDate);
                } else {
                    // Para assignments, recalcular solo si ya hay staff seleccionado
                    const selectedStaffId = staffSelect.value;
                    if (selectedStaffId) {
                        handleStaffSelection(selectedStaffId);
                    }
                }
            };
            duracionSelect.addEventListener('change', handleDurationChange);
        }

        // Listen for date changes using the existing 'aa:admin:date-selected' event
        // Bind once: solo registrar una vez (usar flag interno)
        if (!dateSelectedBound) {
            handleDateSelected = function(event) {
                // Verificar que el modal está abierto (select existe)
                const serviceSelectEl = document.getElementById(serviceSelectId);
                if (!serviceSelectEl) {
                    // Modal no está abierto, ignorar evento
                    return;
                }

                const selectedDateObj = event.detail.selectedDate;

                if (!selectedDateObj || !(selectedDateObj instanceof Date)) {
                    return;
                }

                // Use DateUtils.ymd() to convert Date object to YYYY-MM-DD format
                if (typeof window.DateUtils !== 'undefined' && typeof window.DateUtils.ymd === 'function') {
                    const newDate = window.DateUtils.ymd(selectedDateObj);
                    const state = getState();
                    
                    if (!newDate) {
                        return;
                    }
                    
                    // Actualizar state.selectedDate solo si la fecha es distinta
                    if (newDate !== state.selectedDate) {
                        if (setState) {
                            setState({ selectedDate: newDate });
                        } else {
                            state.selectedDate = newDate;
                        }
                        log('[AdminReservationAssignmentFlowController] Fecha seleccionada (desde evento):', newDate);
                    } else {
                        log('[AdminReservationAssignmentFlowController] Fecha recalculada (misma fecha, refrescando slots):', newDate);
                    }
                    
                    // Siempre recalcular slots, incluso si la fecha es la misma
                    // Esto permite refrescar slots cuando aa_local_availability se actualiza
                    if (state.selectedService && isFixedService(state.selectedService)) {
                        log('[AdminReservationAssignmentFlowController] 🔧 Servicio fixed detectado, calculando slots desde schedule...');
                        // Asegurar que el selector de staff esté oculto
                        hideStaffSelector();
                        calculateFixedSlotsForAdmin(newDate);
                    } else if (state.selectedService) {
                        // Comportamiento normal para servicios con assignments
                        // Asegurar que el selector de staff esté visible
                        showStaffSelector();
                        checkAndLoadAssignments();
                    }
                }
            };

            document.addEventListener('aa:admin:date-selected', handleDateSelected);
            dateSelectedBound = true;
        }

        // Set initial service if already selected
        if (serviceSelect.value) {
            const state = getState();
            const initialService = serviceSelect.value;
            
            // Update state
            if (setState) {
                setState({ selectedService: initialService });
            } else {
                state.selectedService = initialService;
            }
            
            log('[AdminReservationAssignmentFlowController] Servicio inicial:', initialService);
            
            // Actualizar disponibilidad del calendario con el servicio inicial
            if (refreshAdminCalendarByService && typeof refreshAdminCalendarByService === 'function') {
                refreshAdminCalendarByService(initialService);
            }
        }

        log('[AdminReservationAssignmentFlowController] ✅ Flujo de asignaciones inicializado');

        // Return controller instance with destroy method
        return {
            destroy: function() {
                // Remove event listeners
                if (handleServiceChange) {
                    serviceSelect.removeEventListener('change', handleServiceChange);
                }
                if (handleStaffChange) {
                    staffSelect.removeEventListener('change', handleStaffChange);
                }
                if (handleDateSelected) {
                    document.removeEventListener('aa:admin:date-selected', handleDateSelected);
                    dateSelectedBound = false;
                }
                if (handleDurationChange && duracionSelect) {
                    duracionSelect.removeEventListener('change', handleDurationChange);
                }

                log('[AdminReservationAssignmentFlowController] ✅ Destruido y limpiado');
            }
        };
    }

    // ============================================
    // Expose to global namespace
    // ============================================
    window.AdminReservationAssignmentFlowController = {
        init: createController
    };

    console.log('✅ [AdminReservationAssignmentFlowController] Módulo cargado');

})();
