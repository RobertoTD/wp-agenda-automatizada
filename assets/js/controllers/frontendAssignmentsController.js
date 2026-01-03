/**
 * Frontend Assignments Controller
 * 
 * Orchestrates the assignment-based availability flow for the frontend.
 * This is a PHASE 2 controller - logs data but does NOT render slots yet.
 * 
 * Exposes:
 * - window.FrontendAssignmentsController
 * 
 * Methods:
 * - init(config)
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

(function() {
    'use strict';

    console.log('🔄 [FrontendAssignments] Cargando módulo...');

    // ============================================
    // Estado interno
    // ============================================
    const state = {
        selectedService: null,
        selectedDate: null,
        selectedStaff: null,
        currentAssignments: [],
        slotDuration: 30
    };

    // ============================================
    // Referencias a elementos DOM
    // ============================================
    let elements = {
        serviceSelect: null,
        dateInput: null,
        staffSelect: null
    };

    // ============================================
    // Flatpickr instance reference
    // ============================================
    let flatpickrInstance = null;

    // ============================================
    // Inicialización
    // ============================================
    function init(config) {
        console.group('🚀 [FrontendAssignments] Inicializando...');
        
        // Validar dependencias
        if (!validateDependencies()) {
            console.groupEnd();
            return false;
        }

        // Obtener referencias a elementos
        elements.serviceSelect = document.querySelector(config.serviceSelect);
        elements.dateInput = document.querySelector(config.dateInput);
        elements.staffSelect = document.querySelector(config.staffSelect);

        // Obtener slot duration de configuración global
        if (typeof window.aa_slot_duration !== 'undefined') {
            state.slotDuration = parseInt(window.aa_slot_duration, 10) || 30;
        }

        console.log('📋 [FrontendAssignments] Configuración:', {
            serviceSelect: config.serviceSelect,
            dateInput: config.dateInput,
            staffSelect: config.staffSelect,
            slotDuration: state.slotDuration
        });

        // Validar elementos
        if (!elements.serviceSelect) {
            console.warn('⚠️ [FrontendAssignments] No se encontró el selector de servicio:', config.serviceSelect);
        }
        if (!elements.dateInput) {
            console.warn('⚠️ [FrontendAssignments] No se encontró el input de fecha:', config.dateInput);
        }
        if (!elements.staffSelect) {
            console.warn('⚠️ [FrontendAssignments] No se encontró el selector de staff:', config.staffSelect);
        }

        // Configurar listeners
        setupListeners();

        // Leer valores iniciales si ya existen
        readInitialValues();

        console.log('✅ [FrontendAssignments] Controlador inicializado');
        console.groupEnd();
        
        return true;
    }

    // ============================================
    // Validar dependencias
    // ============================================
    function validateDependencies() {
        const deps = {
            'AAAssignmentsAvailability': typeof window.AAAssignmentsAvailability !== 'undefined',
            'AABusyRangesAssignments': typeof window.AABusyRangesAssignments !== 'undefined',
            'DateUtils': typeof window.DateUtils !== 'undefined'
        };

        let allValid = true;
        Object.keys(deps).forEach(function(dep) {
            if (!deps[dep]) {
                console.error('❌ [FrontendAssignments] Dependencia no disponible:', dep);
                allValid = false;
            }
        });

        if (allValid) {
            console.log('✅ [FrontendAssignments] Todas las dependencias disponibles');
        }

        return allValid;
    }

    // ============================================
    // Configurar listeners
    // ============================================
    function setupListeners() {
        console.log('🎧 [FrontendAssignments] Configurando listeners...');

        // Listener para selector de servicio
        if (elements.serviceSelect) {
            elements.serviceSelect.addEventListener('change', handleServiceChange);
            console.log('   ✓ Listener en selector de servicio');
        }

        // Listener para input de fecha (legacy - puede no existir)
        if (elements.dateInput) {
            // Listener nativo para cambios directos
            elements.dateInput.addEventListener('change', handleDateChange);
            
            // Intentar detectar Flatpickr y añadir hook
            setupFlatpickrHook();
            
            console.log('   ✓ Listener en input de fecha (legacy)');
        }

        // Listener para eventos del calendario (NUEVO - principal)
        setupCalendarEventListeners();

        // Listener para selector de staff
        if (elements.staffSelect) {
            elements.staffSelect.addEventListener('change', handleStaffChange);
            console.log('   ✓ Listener en selector de staff');
        }
    }

    // ============================================
    // Configurar listeners de eventos del calendario
    // ============================================
    function setupCalendarEventListeners() {
        // 1️⃣ Intentar usar WPAgenda.on si existe
        if (window.WPAgenda && typeof window.WPAgenda.on === 'function') {
            window.WPAgenda.on('dateSelected', function(data) {
                console.log('📅 [FrontendAssignments] Evento dateSelected recibido:', data);
                
                if (data && data.ymd) {
                    processDateChange(data.ymd);
                } else {
                    console.warn('⚠️ [FrontendAssignments] Evento dateSelected sin ymd:', data);
                }
            });
            
            console.log('   ✓ Listener WPAgenda.on("dateSelected") configurado');
        } else {
            console.log('   ⚠️ WPAgenda.on no disponible, usando fallback');
        }

        // 2️⃣ Fallback: Escuchar cambios en window.aa_selected_date
        let lastKnownDate = window.aa_selected_date || null;
        
        // Polling para detectar cambios en window.aa_selected_date
        setInterval(function() {
            const currentDate = window.aa_selected_date;
            
            if (currentDate && currentDate !== lastKnownDate) {
                console.log('📅 [FrontendAssignments] window.aa_selected_date cambió:', currentDate);
                lastKnownDate = currentDate;
                processDateChange(currentDate);
            }
        }, 500); // Verificar cada 500ms
        
        console.log('   ✓ Fallback polling para window.aa_selected_date configurado');
    }

    // ============================================
    // Configurar hook de Flatpickr
    // ============================================
    function setupFlatpickrHook() {
        // Esperar un poco para que Flatpickr se inicialice
        setTimeout(function() {
            if (elements.dateInput && elements.dateInput._flatpickr) {
                flatpickrInstance = elements.dateInput._flatpickr;
                
                // Guardar callback original si existe
                const originalOnChange = flatpickrInstance.config.onChange;
                
                // Añadir nuestro hook
                flatpickrInstance.config.onChange.push(function(selectedDates, dateStr) {
                    console.log('📅 [FrontendAssignments] Flatpickr onChange detectado:', dateStr);
                    handleDateChange({ target: { value: dateStr } });
                });
                
                console.log('   ✓ Hook de Flatpickr configurado');
            } else {
                console.log('   ⚠️ Flatpickr no detectado en input de fecha');
            }
        }, 500);
    }

    // ============================================
    // Leer valores iniciales
    // ============================================
    function readInitialValues() {
        if (elements.serviceSelect && elements.serviceSelect.value) {
            state.selectedService = elements.serviceSelect.value;
            console.log('📋 [FrontendAssignments] Servicio inicial:', state.selectedService);
        }

        // Leer fecha desde múltiples fuentes (prioridad: window.aa_selected_date > input)
        let initialDate = null;
        
        // 1. Intentar desde window.aa_selected_date (calendarios modernos)
        if (window.aa_selected_date) {
            initialDate = extractDateFromValue(window.aa_selected_date);
            if (initialDate) {
                console.log('📋 [FrontendAssignments] Fecha inicial desde window.aa_selected_date:', initialDate);
            }
        }
        
        // 2. Fallback: desde input de fecha (legacy)
        if (!initialDate && elements.dateInput && elements.dateInput.value) {
            initialDate = extractDateFromValue(elements.dateInput.value);
            if (initialDate) {
                console.log('📋 [FrontendAssignments] Fecha inicial desde input:', initialDate);
            }
        }

        if (initialDate) {
            state.selectedDate = initialDate;
        }

        // Si ya tenemos servicio y fecha, cargar asignaciones
        if (state.selectedService && state.selectedDate) {
            loadAssignments();
        }
    }

    // ============================================
    // Manejador: Cambio de servicio
    // ============================================
    function handleServiceChange(event) {
        const newService = event.target.value;
        
        console.group('🔄 [FrontendAssignments] Cambio de servicio');
        console.log('Anterior:', state.selectedService);
        console.log('Nuevo:', newService);
        
        state.selectedService = newService;
        
        // Limpiar staff y asignaciones
        state.selectedStaff = null;
        state.currentAssignments = [];
        clearStaffSelector();
        
        // Si tenemos fecha, cargar asignaciones
        if (state.selectedDate) {
            loadAssignments();
        } else {
            console.log('⚠️ No hay fecha seleccionada, esperando...');
        }
        
        console.groupEnd();
    }

    // ============================================
    // Procesar cambio de fecha (lógica común)
    // ============================================
    function processDateChange(newDate) {
        // Extraer solo la fecha si viene con hora (formato YYYY-MM-DD HH:MM)
        const dateOnly = extractDateFromValue(newDate);
        
        if (!dateOnly) {
            console.warn('⚠️ [FrontendAssignments] No se pudo extraer fecha de:', newDate);
            return;
        }
        
        // Evitar duplicados
        if (dateOnly === state.selectedDate) {
            return;
        }
        
        console.group('📅 [FrontendAssignments] Cambio de fecha');
        console.log('Valor recibido:', newDate);
        console.log('Fecha extraída:', dateOnly);
        console.log('Anterior:', state.selectedDate);
        console.log('Nueva:', dateOnly);
        
        state.selectedDate = dateOnly;
        
        // Limpiar staff y asignaciones
        state.selectedStaff = null;
        state.currentAssignments = [];
        clearStaffSelector();
        
        // Si tenemos servicio, cargar asignaciones
        if (state.selectedService) {
            loadAssignments();
        } else {
            console.log('⚠️ No hay servicio seleccionado, esperando...');
        }
        
        console.groupEnd();
    }

    // ============================================
    // Extraer fecha del valor del input
    // ============================================
    function extractDateFromValue(value) {
        if (!value) return null;
        
        // El input puede tener formato "YYYY-MM-DD HH:MM" o "YYYY-MM-DD"
        // Extraer solo la parte de la fecha
        const match = String(value).match(/^(\d{4}-\d{2}-\d{2})/);
        if (match) {
            return match[1];
        }
        
        // Si no coincide, retornar null
        return null;
    }

    // ============================================
    // Manejador: Cambio de fecha (legacy - input/Flatpickr)
    // ============================================
    function handleDateChange(event) {
        const newDate = event.target ? event.target.value : event;
        processDateChange(newDate);
    }

    // ============================================
    // Manejador: Cambio de staff
    // ============================================
    function handleStaffChange(event) {
        const newStaff = event.target.value;
        
        console.group('👤 [FrontendAssignments] Cambio de staff');
        console.log('Anterior:', state.selectedStaff);
        console.log('Nuevo:', newStaff);
        
        state.selectedStaff = newStaff;
        
        if (newStaff) {
            calculateSlots();
        }
        
        console.groupEnd();
    }

    // ============================================
    // Cargar asignaciones
    // ============================================
    async function loadAssignments() {
        console.group('📥 [FrontendAssignments] Cargando asignaciones...');
        console.log('Servicio:', state.selectedService);
        console.log('Fecha:', state.selectedDate);
        
        try {
            const result = await window.AAAssignmentsAvailability.getAssignmentsByServiceAndDate(
                state.selectedService,
                state.selectedDate
            );
            
            if (result.success && result.data.assignments) {
                state.currentAssignments = result.data.assignments;
                
                console.log('✅ [FrontendAssignments] Asignaciones recibidas:', state.currentAssignments);
                console.table(state.currentAssignments.map(function(a) {
                    return {
                        id: a.id,
                        staff_id: a.staff_id,
                        staff_name: a.staff_name,
                        start_time: a.start_time,
                        end_time: a.end_time
                    };
                }));
                
                // Llenar selector de staff
                populateStaffSelector(state.currentAssignments);
                
            } else {
                console.warn('⚠️ [FrontendAssignments] No se obtuvieron asignaciones:', result);
                state.currentAssignments = [];
                clearStaffSelector();
                showNoStaffAvailable();
            }
            
        } catch (error) {
            console.error('❌ [FrontendAssignments] Error al cargar asignaciones:', error);
            state.currentAssignments = [];
            clearStaffSelector();
        }
        
        console.groupEnd();
    }

    // ============================================
    // Llenar selector de staff
    // ============================================
    function populateStaffSelector(assignments) {
        if (!elements.staffSelect) {
            console.warn('⚠️ [FrontendAssignments] No hay selector de staff disponible');
            return;
        }

        console.log('👥 [FrontendAssignments] Llenando selector de staff con', assignments.length, 'opciones');

        // Limpiar opciones actuales
        elements.staffSelect.innerHTML = '';

        // Opción por defecto
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Selecciona un profesional';
        elements.staffSelect.appendChild(defaultOption);

        // Extraer staff únicos
        const staffMap = new Map();
        assignments.forEach(function(a) {
            if (a.staff_id && !staffMap.has(a.staff_id)) {
                staffMap.set(a.staff_id, {
                    id: a.staff_id,
                    name: a.staff_name || 'Profesional ' + a.staff_id
                });
            }
        });

        // Crear opciones
        staffMap.forEach(function(staff) {
            const option = document.createElement('option');
            option.value = staff.id;
            option.textContent = staff.name;
            elements.staffSelect.appendChild(option);
        });

        // Habilitar el select
        elements.staffSelect.disabled = false;

        console.log('✅ [FrontendAssignments] Staff disponibles:', Array.from(staffMap.values()));
    }

    // ============================================
    // Limpiar selector de staff
    // ============================================
    function clearStaffSelector() {
        if (!elements.staffSelect) return;

        elements.staffSelect.innerHTML = '';
        
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Primero selecciona servicio y fecha';
        elements.staffSelect.appendChild(defaultOption);
        
        elements.staffSelect.disabled = true;
    }

    // ============================================
    // Mostrar "No hay staff disponible"
    // ============================================
    function showNoStaffAvailable() {
        if (!elements.staffSelect) return;

        elements.staffSelect.innerHTML = '';
        
        const noOption = document.createElement('option');
        noOption.value = '';
        noOption.textContent = 'No hay profesionales disponibles';
        elements.staffSelect.appendChild(noOption);
        
        elements.staffSelect.disabled = true;
    }

    // ============================================
    // Calcular slots (LÓGICA PRINCIPAL)
    // ============================================
    async function calculateSlots() {
        console.group('🧮 [FrontendAssignments] Calculando slots...');
        console.log('Servicio:', state.selectedService);
        console.log('Fecha:', state.selectedDate);
        console.log('Staff ID:', state.selectedStaff);
        console.log('Slot Duration:', state.slotDuration);

        try {
            // 1️⃣ Filtrar asignaciones para el staff seleccionado
            const staffAssignments = state.currentAssignments.filter(function(a) {
                return String(a.staff_id) === String(state.selectedStaff);
            });

            console.log('📋 [FrontendAssignments] Asignaciones del staff:', staffAssignments);

            if (staffAssignments.length === 0) {
                console.warn('⚠️ [FrontendAssignments] No hay asignaciones para este staff');
                console.groupEnd();
                return;
            }

            // 2️⃣ Obtener slots base desde las asignaciones
            console.log('🔄 [FrontendAssignments] Obteniendo slots base...');
            
            const baseSlots = window.AAAssignmentsAvailability.getSlotsForStaffAndDate(
                staffAssignments,
                state.selectedDate,
                state.slotDuration
            );

            console.log('📊 [FrontendAssignments] Slots Base:', baseSlots);
            
            if (baseSlots && baseSlots.length > 0) {
                console.log('   Horarios:', baseSlots.map(function(s) {
                    return window.DateUtils.hm(s);
                }));
            }

            // 3️⃣ Obtener busy ranges
            console.log('🔄 [FrontendAssignments] Obteniendo busy ranges...');
            
            const assignmentIds = staffAssignments.map(function(a) {
                return a.id;
            });

            const busyResult = await window.AABusyRangesAssignments.getBusyRangesByAssignments(
                assignmentIds,
                state.selectedDate
            );

            let busyRanges = [];
            if (busyResult.success && busyResult.data.busy_ranges) {
                // Convertir busy ranges a objetos Date
                busyRanges = busyResult.data.busy_ranges.map(function(range) {
                    return {
                        start: new Date(range.start),
                        end: new Date(range.end),
                        title: range.title || 'Ocupado'
                    };
                });
            }

            console.log('📊 [FrontendAssignments] Busy Ranges:', busyRanges);
            
            if (busyRanges.length > 0) {
                console.log('   Rangos ocupados:');
                busyRanges.forEach(function(r) {
                    console.log('   - ' + window.DateUtils.hm(r.start) + ' - ' + window.DateUtils.hm(r.end) + ' (' + r.title + ')');
                });
            }

            // 4️⃣ Filtrar slots disponibles (quitar los que colisionan con busy ranges)
            console.log('🔄 [FrontendAssignments] Filtrando slots disponibles...');
            
            let finalSlots = [];
            
            if (baseSlots && baseSlots.length > 0) {
                finalSlots = baseSlots.filter(function(slot) {
                    // Usar hasEnoughFreeTime de DateUtils para verificar
                    const isAvailable = window.DateUtils.hasEnoughFreeTime(
                        slot,
                        state.slotDuration,
                        busyRanges
                    );
                    return isAvailable;
                });
            }

            // 5️⃣ Resultado final
            console.log('');
            console.log('═══════════════════════════════════════════════════════');
            console.log('✅ [FrontendAssignments] SLOTS FINALES CALCULADOS:', finalSlots.length);
            console.log('═══════════════════════════════════════════════════════');
            
            if (finalSlots.length > 0) {
                console.log('🕐 Horarios disponibles:');
                finalSlots.forEach(function(slot, index) {
                    console.log('   ' + (index + 1) + '. ' + window.DateUtils.hm(slot));
                });
            } else {
                console.log('❌ No hay horarios disponibles para esta selección');
            }

            // Guardar en estado para debugging
            state.finalSlots = finalSlots;

            console.log('');
            console.log('📦 Estado final:', {
                selectedService: state.selectedService,
                selectedDate: state.selectedDate,
                selectedStaff: state.selectedStaff,
                totalAssignments: state.currentAssignments.length,
                staffAssignments: staffAssignments.length,
                baseSlots: baseSlots ? baseSlots.length : 0,
                busyRanges: busyRanges.length,
                finalSlots: finalSlots.length
            });

        } catch (error) {
            console.error('❌ [FrontendAssignments] Error al calcular slots:', error);
        }

        console.groupEnd();
    }

    // ============================================
    // Obtener estado actual (para debugging)
    // ============================================
    function getState() {
        return { ...state };
    }

    // ============================================
    // Expose to global namespace
    // ============================================
    window.FrontendAssignmentsController = {
        init: init,
        getState: getState
    };

    console.log('✅ [FrontendAssignments] Módulo cargado y expuesto en window.FrontendAssignmentsController');

})();

