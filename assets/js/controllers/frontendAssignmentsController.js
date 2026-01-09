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
        slotDuration: 30,
        initialAvailableDays: null,
        initialMinDate: null,
        initialMaxDate: null
    };

    // ============================================
    // Referencias a elementos DOM
    // ============================================
    let elements = {
        serviceSelect: null,
        dateInput: null,
        staffSelect: null,
        staffWrapper: null
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
        elements.staffWrapper = document.getElementById('staff-selector-wrapper');

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
        
        // Guardar datos iniciales del calendario
        saveInitialCalendarData();

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
    // Guardar datos iniciales del calendario
    // ============================================
    async function saveInitialCalendarData() {
        try {
            // Obtener datos iniciales desde AvailabilityService
            if (window.AvailabilityService) {
                const localBusyRanges = window.AvailabilityService.loadLocal();
                const initialData = await window.AvailabilityService.calculateInitial(localBusyRanges);
                
                state.initialAvailableDays = { ...initialData.availableDays };
                state.initialMinDate = initialData.minDate;
                state.initialMaxDate = initialData.maxDate;
                
                console.log('✅ [FrontendAssignments] Datos iniciales del calendario guardados');
            }
        } catch (error) {
            console.warn('⚠️ [FrontendAssignments] No se pudieron guardar datos iniciales:', error);
        }
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

        // Si ya tenemos servicio y fecha, procesar según tipo
        if (state.selectedService && state.selectedDate) {
            if (isFixedService(state.selectedService)) {
                console.log('🔧 [FrontendAssignments][FIXED] Servicio fixed detectado en valores iniciales');
                calculateFixedSlots();
            } else {
                loadAssignments();
            }
        }
    }

    // ============================================
    // Detectar servicio de horario fijo
    // ============================================
    function isFixedService(service) {
        // Delegar a CalendarAvailabilityService si está disponible
        if (window.CalendarAvailabilityService && window.CalendarAvailabilityService.isFixedServiceKey) {
            return window.CalendarAvailabilityService.isFixedServiceKey(service);
        }
        // Fallback local
        return typeof service === 'string' && service.startsWith('fixed::');
    }

    // ============================================
    // Helpers para mostrar/ocultar selector de staff
    // ============================================
    function hideStaffSelector() {
        if (elements.staffWrapper) {
            elements.staffWrapper.style.display = 'none';
        }
        if (elements.staffSelect) {
            elements.staffSelect.disabled = true;
            elements.staffSelect.value = '';
        }
        console.log('👤 [FrontendAssignments][STAFF] Selector de staff ocultado');
    }

    function showStaffSelector() {
        if (elements.staffWrapper) {
            elements.staffWrapper.style.display = 'block';
        }
        if (elements.staffSelect) {
            elements.staffSelect.disabled = false;
        }
        console.log('👤 [FrontendAssignments][STAFF] Selector de staff mostrado');
    }

    // ============================================
    // Sincronizar input hidden #assignment-id
    // ============================================
    function syncAssignmentInput(assignmentId) {
        const assignmentInput = document.getElementById('assignment-id');
        if (assignmentInput) {
            assignmentInput.value = assignmentId || '';
            if (assignmentId) {
                console.log('✅ [FrontendAssignments] assignment-id actualizado:', assignmentId);
            } else {
                console.log('🧹 [FrontendAssignments] assignment-id limpiado');
            }
        }
    }

    // ============================================
    // Actualizar calendario según servicio
    // ============================================
    async function refreshCalendarByService(serviceKey) {
        console.group('🔄 [FrontendAssignments] Actualizando calendario por servicio');
        console.log('Servicio:', serviceKey || '(vacío - reset)');
        
        // Obtener límites de fecha
        const futureWindow = window.aa_future_window || 14;
        
        let availableDays = {};
        let minDate, maxDate;
        
        // Caso vacío → reset a disponibilidad inicial
        if (!serviceKey) {
            console.log('🔄 [FrontendAssignments] Reseteando a disponibilidad inicial');
            
            // Si tenemos datos iniciales guardados, usarlos
            if (state.initialAvailableDays) {
                availableDays = { ...state.initialAvailableDays };
                minDate = state.initialMinDate ? new Date(state.initialMinDate) : new Date();
                maxDate = state.initialMaxDate ? new Date(state.initialMaxDate) : new Date();
                console.log('✅ [FrontendAssignments] Usando datos iniciales guardados');
            } else {
                // Delegar cálculo al servicio
                const result = await window.CalendarAvailabilityService.getAvailableDaysByService(null, { futureWindowDays: futureWindow });
                availableDays = result.availableDays;
                minDate = result.minDate;
                maxDate = result.maxDate;
                console.log('✅ [FrontendAssignments] Calculado desde schedule vía CalendarAvailabilityService');
            }
        } else {
            // Delegar cálculo al servicio
            const result = await window.CalendarAvailabilityService.getAvailableDaysByService(serviceKey, { futureWindowDays: futureWindow });
            availableDays = result.availableDays;
            minDate = result.minDate;
            maxDate = result.maxDate;
            console.log('✅ [FrontendAssignments] Obtenido desde CalendarAvailabilityService');
        }
        
        // Actualizar calendario usando WPAgenda
        const calendarAdapterInstance = window.WPAgenda?.ui?.calendar || window.calendarDefaultAdapter?.create();
        
        if (calendarAdapterInstance) {
            // Crear función disableDateFn basada en availableDays
            const disableDateFn = (date) => {
                const dayKey = window.DateUtils.ymd(date);
                return !availableDays[dayKey];
            };
            
            // Destruir calendario anterior si existe
            if (calendarAdapterInstance.destroy) {
                calendarAdapterInstance.destroy();
            }
            
            // Re-renderizar con nuevos availableDays
            calendarAdapterInstance.render({
                container: '#wpagenda-calendar',
                minDate: minDate,
                maxDate: maxDate,
                disableDateFn: disableDateFn,
                onDateSelected: (selectedDate) => {
                    console.log('📅 [FrontendAssignments] Fecha seleccionada:', window.DateUtils.ymd(selectedDate));
                    console.log('ℹ️ [FrontendAssignments] Esperando cálculo de slots vía eventos...');
                }
            });
            
            console.log('✅ [FrontendAssignments] Calendario actualizado');
        } else {
            console.warn('⚠️ [FrontendAssignments] Adaptador de calendario no disponible, no se puede actualizar calendario');
        }
        
        console.groupEnd();
    }

    // ============================================
    // Manejador: Cambio de servicio
    // ============================================
    async function handleServiceChange(event) {
        const newService = event.target.value;
        
        console.group('🔄 [FrontendAssignments] Cambio de servicio');
        console.log('Anterior:', state.selectedService);
        console.log('Nuevo:', newService);
        
        state.selectedService = newService;
        
        // Limpiar staff y asignaciones
        state.selectedStaff = null;
        state.currentAssignments = [];
        hideStaffSelector(); // Ocultar selector de staff
        clearStaffSelector();
        syncAssignmentInput(null); // Limpiar assignment-id
        
        // Limpiar slots visuales inmediatamente
        if (window.WPAgenda && typeof window.WPAgenda.emit === 'function') {
            window.WPAgenda.emit('slotsCalculated', {
                slots: [],
                selectedDate: null,
                service: newService,
                staffId: null
            });
            console.log('🧹 [FrontendAssignments] Slots limpiados');
        }
        
        // Actualizar calendario según servicio
        await refreshCalendarByService(newService);
        
        // Detectar si es servicio fixed
        if (isFixedService(newService)) {
            console.log('🔧 [FrontendAssignments][FIXED] Servicio de horario fijo detectado');
            
            // NO llamar loadAssignments() para servicios fixed
            // Si ya tenemos fecha, calcular slots fijos inmediatamente
            if (state.selectedDate) {
                calculateFixedSlots();
            } else {
                console.log('⚠️ [FrontendAssignments][FIXED] No hay fecha seleccionada, esperando...');
            }
        } else {
            // Comportamiento normal para servicios con assignments
            // Si tenemos fecha, cargar asignaciones
            if (state.selectedDate) {
                loadAssignments();
            } else {
                console.log('⚠️ No hay fecha seleccionada, esperando...');
            }
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
        hideStaffSelector(); // Ocultar selector de staff
        clearStaffSelector();
        syncAssignmentInput(null); // Limpiar assignment-id
        
        // Si tenemos servicio, verificar si es fixed
        if (state.selectedService) {
            if (isFixedService(state.selectedService)) {
                console.log('🔧 [FrontendAssignments][FIXED] Servicio de horario fijo, calculando slots...');
                // NO llamar loadAssignments() para servicios fixed
                calculateFixedSlots();
            } else {
                // Comportamiento normal para servicios con assignments
                loadAssignments();
            }
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
                syncAssignmentInput(null); // Limpiar assignment-id
            }
            
        } catch (error) {
            console.error('❌ [FrontendAssignments] Error al cargar asignaciones:', error);
            state.currentAssignments = [];
            clearStaffSelector();
            syncAssignmentInput(null); // Limpiar assignment-id
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

        console.log('👥 [FrontendAssignments][STAFF] Llenando selector de staff con', assignments.length, 'asignaciones');

        // Limpiar opciones actuales
        elements.staffSelect.innerHTML = '';

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

        const uniqueStaff = Array.from(staffMap.values());
        console.log('👥 [FrontendAssignments][STAFF] Staff únicos encontrados:', uniqueStaff.length);

        // Si hay 0 staff, ocultar selector
        if (uniqueStaff.length === 0) {
            console.log('👤 [FrontendAssignments][STAFF] No hay staff disponible, ocultando selector');
            hideStaffSelector();
            return;
        }

        // Si hay 1 staff, auto-seleccionar y ocultar selector
        if (uniqueStaff.length === 1) {
            const singleStaff = uniqueStaff[0];
            state.selectedStaff = String(singleStaff.id);
            console.log('👤 [FrontendAssignments][STAFF] Auto-seleccionando único staff:', singleStaff.name, '(ID:', singleStaff.id + ')');
            
            hideStaffSelector();
            
            // Continuar con el flujo para calcular slots automáticamente
            calculateSlots();
            return;
        }

        // Si hay 2 o más staff, mostrar selector
        console.log('👤 [FrontendAssignments][STAFF] Múltiples staff disponibles, mostrando selector');

        // Opción por defecto
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Selecciona un profesional';
        elements.staffSelect.appendChild(defaultOption);

        // Crear opciones
        staffMap.forEach(function(staff) {
            const option = document.createElement('option');
            option.value = staff.id;
            option.textContent = staff.name;
            elements.staffSelect.appendChild(option);
        });

        // Mostrar y habilitar el select
        showStaffSelector();

        console.log('✅ [FrontendAssignments][STAFF] Staff disponibles:', uniqueStaff);
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
        
        // Ocultar wrapper
        hideStaffSelector();
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
        
        // Ocultar wrapper
        hideStaffSelector();
    }

    // ============================================
    // Calcular slots para servicio de horario fijo
    // ============================================
    async function calculateFixedSlots() {
        console.group('🧮 [FrontendAssignments][FIXED] Calculando slots de horario fijo...');
        console.log('Servicio:', state.selectedService);
        console.log('Fecha:', state.selectedDate);
        console.log('Slot Duration:', state.slotDuration);

        try {
            // Validar dependencias necesarias
            if (typeof window.AvailabilityService === 'undefined') {
                console.error('❌ [FrontendAssignments][FIXED] AvailabilityService no disponible');
                console.groupEnd();
                return;
            }

            if (typeof window.SlotCalculator === 'undefined') {
                console.error('❌ [FrontendAssignments][FIXED] SlotCalculator no disponible');
                console.groupEnd();
                return;
            }

            if (typeof window.DateUtils === 'undefined') {
                console.error('❌ [FrontendAssignments][FIXED] DateUtils no disponible');
                console.groupEnd();
                return;
            }

            // 1️⃣ Obtener schedule y configuración
            const schedule = window.aa_schedule || {};
            const slotDuration = state.slotDuration;

            console.log('📋 [FrontendAssignments][FIXED] Configuración:', {
                schedule: schedule,
                slotDuration: slotDuration
            });

            // 2️⃣ Obtener busy ranges (locales y externos)
            console.log('🔄 [FrontendAssignments][FIXED] Obteniendo busy ranges...');
            
            const built = (window.BusyRanges && window.BusyRanges.buildBusyRanges)
                ? window.BusyRanges.buildBusyRanges()
                : { busyRanges: [], localBusy: [], externalBusy: [] };

            const { busyRanges, localBusy: localBusyRanges, externalBusy: externalBusyRanges } = built;
            
            console.log('📊 [FrontendAssignments][FIXED] Busy Ranges obtenidos:', busyRanges.length);
            console.log('   - Locales:', localBusyRanges.length);
            console.log('   - Externos:', externalBusyRanges.length);
            
            if (busyRanges && busyRanges.length > 0) {
                console.log('   Rangos ocupados:');
                busyRanges.forEach(function(r) {
                    const startStr = r.start ? window.DateUtils.hm(r.start) : 'N/A';
                    const endStr = r.end ? window.DateUtils.hm(r.end) : 'N/A';
                    console.log('   - ' + startStr + ' - ' + endStr);
                });
            }

            // 3️⃣ Crear objeto Date para la fecha seleccionada
            const selectedDateObj = new Date(state.selectedDate + 'T00:00:00');
            
            // 4️⃣ Calcular slots para la fecha específica usando SlotCalculator
            console.log('🔄 [FrontendAssignments][FIXED] Calculando slots para fecha...');
            
            const slots = window.SlotCalculator.calculateSlotsForDate(
                selectedDateObj,
                schedule,
                busyRanges || [],
                slotDuration
            );

            console.log('📊 [FrontendAssignments][FIXED] Slots calculados:', slots ? slots.length : 0);
            
            if (slots && slots.length > 0) {
                console.log('🕐 Horarios disponibles:');
                slots.forEach(function(slot, index) {
                    const slotTime = slot instanceof Date ? window.DateUtils.hm(slot) : String(slot);
                    console.log('   ' + (index + 1) + '. ' + slotTime);
                });
            } else {
                console.log('❌ [FrontendAssignments][FIXED] No hay horarios disponibles para esta fecha');
            }

            // 5️⃣ Emitir evento para renderizar slots
            if (window.WPAgenda && typeof window.WPAgenda.emit === 'function') {
                window.WPAgenda.emit('slotsCalculated', {
                    slots: slots || [],
                    selectedDate: state.selectedDate,
                    service: state.selectedService,
                    staffId: null
                });
                console.log('📤 [FrontendAssignments][FIXED] Evento slotsCalculated emitido');
            } else {
                console.warn('⚠️ [FrontendAssignments][FIXED] WPAgenda.emit no disponible');
            }

            // Guardar en estado para debugging
            state.finalSlots = slots || [];

            console.log('');
            console.log('═══════════════════════════════════════════════════════');
            console.log('✅ [FrontendAssignments][FIXED] SLOTS FINALES CALCULADOS:', slots ? slots.length : 0);
            console.log('═══════════════════════════════════════════════════════');
            console.log('📦 Estado final:', {
                selectedService: state.selectedService,
                selectedDate: state.selectedDate,
                selectedStaff: null,
                busyRanges: busyRanges ? busyRanges.length : 0,
                finalSlots: slots ? slots.length : 0
            });

        } catch (error) {
            console.error('❌ [FrontendAssignments][FIXED] Error al calcular slots fijos:', error);
        }

        console.groupEnd();
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

            // Sincronizar input hidden #assignment-id con la asignación actual
            if (staffAssignments.length > 0) {
                // Tomar el primer assignment (por ahora)
                const selectedAssignment = staffAssignments[0];
                syncAssignmentInput(selectedAssignment.id);
            } else {
                // Limpiar el input si no hay asignaciones
                syncAssignmentInput(null);
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

            // Emitir evento para que los adaptadores de slots puedan renderizar
            if (window.WPAgenda && typeof window.WPAgenda.emit === 'function') {
                window.WPAgenda.emit('slotsCalculated', {
                    slots: finalSlots,
                    selectedDate: state.selectedDate,
                    service: state.selectedService,
                    staffId: state.selectedStaff
                });
                console.log('📤 [FrontendAssignments] Evento slotsCalculated emitido');
            } else {
                console.warn('⚠️ [FrontendAssignments] WPAgenda.emit no disponible');
            }

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

