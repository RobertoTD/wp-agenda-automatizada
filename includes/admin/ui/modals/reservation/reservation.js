/**
 * Reservation Modal Controller
 * 
 * Orquesta la apertura del modal y la inicialización de los controladores
 * existentes (AvailabilityController, AdminReservationController).
 * 
 * NO duplica lógica de negocio - solo coordina.
 * 
 * @package AgendaAutomatizada
 * @since 1.0.0
 */
(function() {
    'use strict';

    /**
     * ReservationModal namespace
     */
    const ReservationModal = {
        /**
         * Modal configuration
         */
        config: {
            buttonId: 'aa-btn-open-reservation-modal',
            title: 'Agendar cita',
            templateId: 'aa-reservation-modal-template'
        },

        /**
         * Track if controllers are initialized
         */
        initialized: false,

        /**
         * State for new assignment-based flow
         */
        state: {
            selectedService: null,
            selectedDate: null,
            selectedStaff: null,
            currentAssignments: [],
            selectedAssignmentId: null
        },

        /**
         * Get the modal body content from template
         * Uses <template> tag to avoid duplicate IDs
         * @returns {string} HTML content for modal body
         */
        getBodyContent: function() {
            const template = document.getElementById(this.config.templateId);
            if (template && template.content) {
                // Clone the template content
                const clone = template.content.cloneNode(true);
                // Create a temporary container to get HTML string
                const container = document.createElement('div');
                container.appendChild(clone);
                return container.innerHTML;
            }

            // Fallback if template not found
            console.error('[ReservationModal] Template not found: #' + this.config.templateId);
            return '<div class="p-4 text-red-600">Error: Template de reservación no encontrado.</div>';
        },

        /**
         * Initialize controllers after modal content is rendered
         */
        initControllers: function() {
            console.log('[ReservationModal] Inicializando controladores...');

            // Pequeño delay para asegurar que el DOM está listo
            setTimeout(() => {
                // 1️⃣ Inicializar AvailabilityController
                if (typeof window.AvailabilityController !== 'undefined') {
                    console.log('[ReservationModal] Inicializando AvailabilityController...');
                    
                    window.AvailabilityController.init({
                        fechaInputSelector: '#cita-fecha',
                        slotContainerSelector: 'slot-container-admin',
                        isAdmin: true
                    });
                    
                    console.log('[ReservationModal] ✅ AvailabilityController inicializado');
                } else {
                    console.error('[ReservationModal] ❌ AvailabilityController no disponible');
                }

                // 2️⃣ Inicializar AdminReservationController
                if (typeof window.AdminReservationController !== 'undefined') {
                    console.log('[ReservationModal] Inicializando AdminReservationController...');
                    
                    const form = document.getElementById('form-crear-cita-admin');
                    const btnCancelar = document.getElementById('btn-cancelar-cita-form');
                    
                    if (form) {
                        // Sobrescribir el comportamiento de submit para cerrar modal después
                        this.setupFormSubmitHandler(form);
                        
                        window.AdminReservationController.init({
                            btnToggle: null, // No se usa toggle en modal
                            formNuevaCita: form,
                            btnCancelar: btnCancelar,
                            form: form
                        });
                        
                        console.log('[ReservationModal] ✅ AdminReservationController inicializado');
                    } else {
                        console.error('[ReservationModal] ❌ Formulario #form-crear-cita-admin no encontrado');
                    }
                } else {
                    console.error('[ReservationModal] ❌ AdminReservationController no disponible');
                }

                // 3️⃣ Setup botón cancelar para cerrar modal
                const btnCancelar = document.getElementById('btn-cancelar-cita-form');
                if (btnCancelar) {
                    btnCancelar.addEventListener('click', () => {
                        this.close();
                    });
                }

                // 4️⃣ Filtrar servicios sin fechas disponibles
                this.filterServiceOptions();

                // 5️⃣ Inicializar flujo Servicio → Fecha → Staff (paralelo)
                this.initAssignmentFlow();

                this.initialized = true;
                console.log('[ReservationModal] ✅ Todos los controladores inicializados');
                
            }, 100); // 100ms delay para asegurar DOM ready
        },

        /**
         * Initialize assignment-based flow (Servicio → Fecha → Staff)
         * This runs in parallel with legacy flow, only logs results
         */
        initAssignmentFlow: function() {
            console.log('[AA][Reservation] Inicializando flujo de asignaciones...');

            const self = this;
            const servicioSelect = document.getElementById('cita-servicio');
            const fechaInput = document.getElementById('cita-fecha');
            const staffSelect = document.getElementById('aa-reservation-staff');

            if (!servicioSelect || !fechaInput || !staffSelect) {
                console.warn('[AA][Reservation] Elementos del flujo de asignaciones no encontrados');
                return;
            }

            // Reset state
            this.resetState();

            // Listen for service changes
            servicioSelect.addEventListener('change', async function() {
                self.state.selectedService = this.value;
                console.log('[AA][Reservation] Servicio seleccionado:', self.state.selectedService);
                
                // Actualizar disponibilidad del calendario según el servicio
                await self.refreshAdminCalendarByService(self.state.selectedService);
                
                // Detectar si es servicio fixed
                if (self.isFixedService(self.state.selectedService)) {
                    console.log('[AA][Reservation] 🔧 Servicio fixed detectado');
                    // Ocultar selector de staff (no se usa para servicios fixed)
                    self.hideStaffSelector();
                    // Resetear staff select
                    self.resetStaffSelect();
                    // Si ya hay fecha seleccionada, calcular slots inmediatamente
                    if (self.state.selectedDate) {
                        self.calculateFixedSlotsForAdmin();
                    }
                    // NO llamar checkAndLoadAssignments para servicios fixed
                } else {
                    // Comportamiento normal para servicios con assignments
                    // Mostrar selector de staff
                    self.showStaffSelector();
                    self.checkAndLoadAssignments();
                }
            });

            // Listen for date changes using the existing 'aa:admin:date-selected' event
            // This event is fired by CalendarAdminUI when flatpickr changes, and provides a Date object
            // This avoids format issues with reading fechaInput.value directly (which has format "d-m-Y")
            document.addEventListener('aa:admin:date-selected', function(event) {
                const selectedDateObj = event.detail.selectedDate;
                
                if (!selectedDateObj || !(selectedDateObj instanceof Date)) {
                    return;
                }
                
                // Use DateUtils.ymd() to convert Date object to YYYY-MM-DD format
                if (typeof window.DateUtils !== 'undefined' && typeof window.DateUtils.ymd === 'function') {
                    const newDate = window.DateUtils.ymd(selectedDateObj);
                    if (newDate && newDate !== self.state.selectedDate) {
                        self.state.selectedDate = newDate;
                        console.log('[AA][Reservation] Fecha seleccionada (desde evento):', self.state.selectedDate);
                        
                        // Detectar si es servicio fixed
                        if (self.state.selectedService && self.isFixedService(self.state.selectedService)) {
                            console.log('[AA][Reservation] 🔧 Servicio fixed detectado, calculando slots desde schedule...');
                            // Asegurar que el selector de staff esté oculto
                            self.hideStaffSelector();
                            self.calculateFixedSlotsForAdmin();
                        } else {
                            // Comportamiento normal para servicios con assignments
                            // Asegurar que el selector de staff esté visible
                            self.showStaffSelector();
                            self.checkAndLoadAssignments();
                        }
                    }
                }
            });

            // Listen for staff selection
            staffSelect.addEventListener('change', function() {
                self.state.selectedStaff = this.value;
                self.handleStaffSelection();
            });

            // Set initial service if already selected
            if (servicioSelect.value) {
                this.state.selectedService = servicioSelect.value;
                console.log('[AA][Reservation] Servicio inicial:', this.state.selectedService);
                
                // Actualizar disponibilidad del calendario con el servicio inicial
                this.refreshAdminCalendarByService(this.state.selectedService);
            }

            console.log('[AA][Reservation] ✅ Flujo de asignaciones inicializado');
        },

        /**
         * Helper: Detecta si un servicio es de horario fijo
         * @param {string} serviceKey - Clave del servicio
         * @returns {boolean} true si el servicio empieza con "fixed::"
         */
        isFixedService: function(serviceKey) {
            return typeof serviceKey === 'string' && serviceKey.startsWith('fixed::');
        },

        /**
         * Evalúa si un servicio tiene fechas disponibles dentro de la ventana futura
         * @param {string} serviceKey - Clave del servicio
         * @returns {Promise<boolean>} true si el servicio tiene al menos una fecha disponible
         */
        hasAvailableDates: async function(serviceKey) {
            if (!serviceKey) return false;

            // Obtener límites de fecha
            const futureWindow = window.aa_future_window || 14;
            const minDate = new Date();
            minDate.setHours(0, 0, 0, 0);
            const maxDate = new Date();
            maxDate.setDate(minDate.getDate() + futureWindow);
            maxDate.setHours(23, 59, 59, 999);

            // Caso: Servicio fixed
            if (this.isFixedService(serviceKey)) {
                const schedule = window.aa_schedule || {};
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
            else {
                try {
                    const result = await window.AAAssignmentsAvailability.getAssignmentDatesByService(
                        serviceKey,
                        window.DateUtils.ymd(minDate),
                        window.DateUtils.ymd(maxDate)
                    );
                    
                    if (result.success && Array.isArray(result.data.dates) && result.data.dates.length > 0) {
                        // Verificar que al menos una fecha esté en el rango
                        const hasValidDate = result.data.dates.some(dateStr => {
                            return window.DateUtils.isDateInRange(dateStr, minDate, maxDate);
                        });
                        return hasValidDate;
                    }
                    return false;
                } catch (error) {
                    console.error('[AA][Reservation] Error al evaluar disponibilidad de servicio:', serviceKey, error);
                    return false;
                }
            }
        },

        /**
         * Filtra las opciones del select de servicios, removiendo las que no tienen fechas disponibles
         */
        filterServiceOptions: async function() {
            console.group('[AA][Reservation] Filtrando servicios sin fechas disponibles...');
            
            const servicioSelect = document.getElementById('cita-servicio');
            if (!servicioSelect) {
                console.warn('[AA][Reservation] ⚠️ Select de servicio no encontrado');
                console.groupEnd();
                return;
            }

            const options = Array.from(servicioSelect.options);
            const selectedValue = servicioSelect.value;
            const servicesToRemove = [];

            // Evaluar cada opción (excepto la primera que es el placeholder vacío)
            for (let i = 1; i < options.length; i++) {
                const option = options[i];
                const serviceKey = option.value;

                if (!serviceKey) continue; // Saltar opciones vacías

                const hasDates = await this.hasAvailableDates(serviceKey);
                
                if (!hasDates) {
                    console.log(`[AA][Reservation] ❌ Servicio sin fechas: ${serviceKey} - removiendo`);
                    servicesToRemove.push(option);
                } else {
                    console.log(`[AA][Reservation] ✅ Servicio con fechas: ${serviceKey} - manteniendo`);
                }
            }

            // Remover servicios sin fechas
            servicesToRemove.forEach(option => {
                option.remove();
            });

            // Si el servicio seleccionado fue removido, resetear el select
            if (selectedValue && servicesToRemove.some(opt => opt.value === selectedValue)) {
                servicioSelect.value = '';
                console.log('[AA][Reservation] 🔄 Servicio seleccionado fue removido, reseteando select');
            }

            console.log(`[AA][Reservation] ✅ Filtrado completado: ${servicesToRemove.length} servicios removidos`);
            console.groupEnd();
        },

        /**
         * Actualiza la disponibilidad del calendario admin según el servicio seleccionado
         * Replica la lógica del frontend (refreshCalendarByService)
         * @param {string} serviceKey - Clave del servicio (puede ser vacío, fixed:: o normal)
         */
        refreshAdminCalendarByService: async function(serviceKey) {
            console.group('[AA][Reservation] Actualizando calendario admin por servicio');
            console.log('Servicio:', serviceKey || '(vacío - reset)');
            
            // Obtener límites de fecha
            const futureWindow = window.aa_future_window || 14;
            const minDate = new Date();
            minDate.setHours(0, 0, 0, 0);
            const maxDate = new Date();
            maxDate.setDate(minDate.getDate() + futureWindow);
            maxDate.setHours(23, 59, 59, 999);
            
            let availableDays = {};
            
            // Caso A: Servicio vacío → reset a disponibilidad general basada en aa_schedule
            if (!serviceKey) {
                console.log('[AA][Reservation] Reseteando a disponibilidad general (schedule)');
                
                const schedule = window.aa_schedule || {};
                for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
                    const day = new Date(d);
                    const weekday = window.DateUtils.getWeekdayName(day);
                    const dayKey = window.DateUtils.ymd(day);
                    const intervals = window.DateUtils.getDayIntervals(schedule, weekday);
                    availableDays[dayKey] = intervals.length > 0;
                }
                console.log('[AA][Reservation] ✅ Disponibilidad calculada desde schedule');
            }
            // Caso B: Servicio por assignments
            else if (!this.isFixedService(serviceKey)) {
                console.log('[AA][Reservation] Obteniendo fechas de assignments para servicio:', serviceKey);
                
                try {
                    const result = await window.AAAssignmentsAvailability.getAssignmentDatesByService(
                        serviceKey,
                        window.DateUtils.ymd(minDate),
                        window.DateUtils.ymd(maxDate)
                    );
                    
                    if (result.success && Array.isArray(result.data.dates)) {
                        result.data.dates.forEach(dateStr => {
                            if (window.DateUtils.isDateInRange(dateStr, minDate, maxDate)) {
                                availableDays[dateStr] = true;
                            }
                        });
                        console.log(`[AA][Reservation] ✅ ${result.data.dates.length} fechas encontradas para servicio`);
                    } else {
                        console.warn('[AA][Reservation] ⚠️ No se pudieron obtener fechas de assignments');
                    }
                } catch (error) {
                    console.error('[AA][Reservation] ❌ Error al obtener fechas de assignments:', error);
                }
            }
            // Caso C: Servicio fixed → calcular disponibilidad por weekday usando schedule
            else {
                console.log('[AA][Reservation] 🔧 Servicio fixed, calculando disponibilidad desde schedule');
                
                const schedule = window.aa_schedule || {};
                for (let d = new Date(minDate); d <= maxDate; d.setDate(d.getDate() + 1)) {
                    const day = new Date(d);
                    const weekday = window.DateUtils.getWeekdayName(day);
                    const dayKey = window.DateUtils.ymd(day);
                    const intervals = window.DateUtils.getDayIntervals(schedule, weekday);
                    availableDays[dayKey] = intervals.length > 0;
                }
                console.log('[AA][Reservation] ✅ Disponibilidad fixed calculada desde schedule');
            }
            
            // Aplicar la disponibilidad al Flatpickr del admin
            const fechaInput = document.getElementById('cita-fecha');
            if (!fechaInput) {
                console.warn('[AA][Reservation] ⚠️ Input de fecha no encontrado');
                console.groupEnd();
                return;
            }
            
            // Crear función disableDateFn basada en availableDays
            const disableDateFn = (date) => {
                const dayKey = window.DateUtils.ymd(date);
                return !availableDays[dayKey];
            };
            
            // Re-renderizar el calendario admin con la nueva disponibilidad
            if (typeof window.CalendarAdminUI !== 'undefined' && typeof window.CalendarAdminUI.render === 'function') {
                const slotContainerSelector = 'slot-container-admin';
                
                // Crear slotsMap vacío (no calculamos slots aquí)
                const emptySlotsMap = {};
                Object.keys(availableDays).forEach(day => {
                    emptySlotsMap[day] = [];
                });
                
                const picker = window.CalendarAdminUI.render({
                    fechaInput: fechaInput,
                    slotContainerSelector: slotContainerSelector,
                    slotsMap: emptySlotsMap,
                    minDate: minDate,
                    maxDate: maxDate,
                    disableDateFn: disableDateFn
                });
                
                console.log('[AA][Reservation] ✅ Calendario admin actualizado');
                
                // Auto-seleccionar la primera fecha válida
                if (picker && typeof picker.setDate === 'function') {
                    // Encontrar la primera fecha válida
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
                        console.log('[AA][Reservation] 📅 Auto-seleccionando primera fecha válida:', window.DateUtils.ymd(firstAvailableDate));
                        // Usar setDate con triggerChange=true para disparar onChange y el evento aa:admin:date-selected
                        picker.setDate(firstAvailableDate, true);
                    } else {
                        console.log('[AA][Reservation] ⚠️ No se encontró ninguna fecha válida para auto-seleccionar');
                        // Limpiar fecha si no hay fechas disponibles
                        picker.clear();
                    }
                }
            } else {
                console.error('[AA][Reservation] ❌ CalendarAdminUI no disponible');
            }
            
            console.groupEnd();
        },

        /**
         * Reset state
         */
        resetState: function() {
            this.state = {
                selectedService: null,
                selectedDate: null,
                selectedStaff: null,
                currentAssignments: [],
                selectedAssignmentId: null
            };
            this.updateAssignmentIdInput(null);
        },

        /**
         * Update or create hidden input for assignment_id
         * @param {number|null} assignmentId 
         */
        updateAssignmentIdInput: function(assignmentId) {
            const form = document.getElementById('form-crear-cita-admin');
            if (!form) return;

            let input = document.getElementById('assignment-id');
            
            if (assignmentId) {
                if (!input) {
                    // Create hidden input if it doesn't exist
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = 'assignment-id';
                    input.name = 'assignment_id';
                    form.appendChild(input);
                }
                input.value = assignmentId;
            } else {
                // Remove input if assignmentId is null
                if (input) {
                    input.remove();
                }
            }
        },

        /**
         * Extract date (YYYY-MM-DD) from datetime string
         * @param {string} datetime - DateTime string (various formats)
         * @returns {string} Date in YYYY-MM-DD format
         */
        extractDate: function(datetime) {
            if (!datetime) return null;
            
            // If already in YYYY-MM-DD format
            if (/^\d{4}-\d{2}-\d{2}$/.test(datetime)) {
                return datetime;
            }
            
            // Try to parse and format
            try {
                const date = new Date(datetime);
                if (!isNaN(date.getTime())) {
                    return date.toISOString().split('T')[0];
                }
            } catch (e) {
                console.warn('[AA][Reservation] Error parsing date:', e);
            }
            
            // Fallback: extract first 10 chars if looks like date
            if (datetime.length >= 10) {
                return datetime.substring(0, 10);
            }
            
            return datetime;
        },

        /**
         * Check if both service and date are selected, then load assignments
         */
        checkAndLoadAssignments: async function() {
            const { selectedService, selectedDate } = this.state;

            console.log('[AA][Reservation] Verificando:', { selectedService, selectedDate });

            if (!selectedService || !selectedDate) {
                console.log('[AA][Reservation] Faltan datos para cargar asignaciones');
                this.resetStaffSelect();
                return;
            }

            // Both are defined, load assignments
            console.log('[AA][Reservation] ✅ Servicio y fecha definidos, cargando asignaciones...');
            
            await this.loadAssignmentsForServiceAndDate(selectedService, selectedDate);
        },

        /**
         * Load assignments for service and date using AAAssignmentsAvailability
         * @param {string} serviceKey 
         * @param {string} date 
         */
        loadAssignmentsForServiceAndDate: async function(serviceKey, date) {
            const staffSelect = document.getElementById('aa-reservation-staff');
            
            if (!staffSelect) {
                console.error('[AA][Reservation] Staff select not found');
                return;
            }

            // Show loading state
            staffSelect.disabled = true;
            staffSelect.innerHTML = '<option value="">Cargando personal...</option>';

            try {
                // Check if AAAssignmentsAvailability is available
                if (typeof window.AAAssignmentsAvailability === 'undefined') {
                    console.warn('[AA][Reservation] AAAssignmentsAvailability no disponible');
                    staffSelect.innerHTML = '<option value="">Sistema de asignaciones no disponible</option>';
                    return;
                }

                // Call the service
                const result = await window.AAAssignmentsAvailability.getAssignmentsByServiceAndDate(serviceKey, date);

                console.log('[AA][Reservation] Resultado de asignaciones:', result);

                if (result.success && result.data && result.data.assignments) {
                    this.state.currentAssignments = result.data.assignments;
                    this.populateStaffSelect(result.data.assignments);
                } else {
                    this.state.currentAssignments = [];
                    staffSelect.innerHTML = '<option value="">No hay personal disponible</option>';
                    console.log('[AA][Reservation] No hay asignaciones para este servicio y fecha');
                }
            } catch (error) {
                console.error('[AA][Reservation] Error al cargar asignaciones:', error);
                staffSelect.innerHTML = '<option value="">Error al cargar personal</option>';
            }
        },

        /**
         * Populate staff select with unique staff from assignments
         * @param {Array} assignments 
         */
        populateStaffSelect: function(assignments) {
            const staffSelect = document.getElementById('aa-reservation-staff');
            
            if (!staffSelect) return;

            // Asegurar que el selector esté visible (para servicios por assignments)
            this.showStaffSelector();

            // Extract unique staff
            const staffMap = new Map();
            
            assignments.forEach(function(assignment) {
                if (assignment.staff_id && assignment.staff_name) {
                    staffMap.set(assignment.staff_id, assignment.staff_name);
                }
            });

            console.log('[AA][Reservation] Staff únicos encontrados:', staffMap.size);

            if (staffMap.size === 0) {
                staffSelect.innerHTML = '<option value="">No hay personal disponible</option>';
                staffSelect.disabled = true;
                return;
            }

            // Build options
            let html = '<option value="">-- Selecciona personal --</option>';
            
            staffMap.forEach(function(name, id) {
                html += '<option value="' + id + '">' + name + '</option>';
            });

            staffSelect.innerHTML = html;
            staffSelect.disabled = false;

            console.log('[AA][Reservation] ✅ Select de staff poblado con', staffMap.size, 'opciones');

            // Auto-seleccionar la primera opción válida si existe
            const validOptions = Array.from(staffSelect.options).filter(opt => opt.value !== '');
            if (validOptions.length > 0) {
                const firstOption = validOptions[0];
                staffSelect.value = firstOption.value;
                this.state.selectedStaff = firstOption.value;
                console.log('[AA][Reservation] 🔄 Auto-seleccionando primer staff:', firstOption.value, '-', firstOption.text);
                
                // Ejecutar el mismo flujo que ocurre en una selección manual
                this.handleStaffSelection();
            }
        },

        /**
         * Ocultar selector de staff (para servicios fixed)
         */
        hideStaffSelector: function() {
            const staffSelect = document.getElementById('aa-reservation-staff');
            if (!staffSelect) return;

            // Ocultar el select
            staffSelect.style.display = 'none';
            
            // Ocultar el contenedor padre (aa-form-cita-group) si existe
            const parentGroup = staffSelect.closest('.aa-form-cita-group');
            if (parentGroup) {
                parentGroup.style.display = 'none';
            }

            // Limpiar estado
            this.state.selectedStaff = null;
            
            console.log('[AA][Reservation] 👤 Selector de staff oculto');
        },

        /**
         * Mostrar selector de staff (para servicios por assignments)
         */
        showStaffSelector: function() {
            const staffSelect = document.getElementById('aa-reservation-staff');
            if (!staffSelect) return;

            // Mostrar el select
            staffSelect.style.display = 'block';
            
            // Mostrar el contenedor padre (aa-form-cita-group) si existe
            const parentGroup = staffSelect.closest('.aa-form-cita-group');
            if (parentGroup) {
                parentGroup.style.display = 'block';
            }
            
            console.log('[AA][Reservation] 👤 Selector de staff visible');
        },

        /**
         * Calcular slots para servicio fixed en admin
         * Replica la lógica del frontend pero sin busy ranges
         */
        calculateFixedSlotsForAdmin: function() {
            console.group('[AA][Reservation][FIXED] Calculando slots de horario fijo...');
            console.log('Servicio:', this.state.selectedService);
            console.log('Fecha:', this.state.selectedDate);

            try {
                // Validar dependencias necesarias
                if (typeof window.SlotCalculator === 'undefined') {
                    console.error('[AA][Reservation][FIXED] ❌ SlotCalculator no disponible');
                    console.groupEnd();
                    return;
                }

                if (typeof window.DateUtils === 'undefined') {
                    console.error('[AA][Reservation][FIXED] ❌ DateUtils no disponible');
                    console.groupEnd();
                    return;
                }

                // 1️⃣ Obtener schedule y configuración
                const schedule = window.aa_schedule || {};
                const slotDuration = window.aa_slot_duration || 30;

                console.log('[AA][Reservation][FIXED] Configuración:', {
                    schedule: schedule,
                    slotDuration: slotDuration
                });

                // 2️⃣ Crear objeto Date para la fecha seleccionada
                const selectedDateObj = new Date(this.state.selectedDate + 'T00:00:00');

                // 3️⃣ Calcular slots para la fecha específica usando SlotCalculator
                // Para servicios fixed en admin, NO usar busy ranges (array vacío)
                console.log('[AA][Reservation][FIXED] Calculando slots sin busy ranges...');
                
                const slots = window.SlotCalculator.calculateSlotsForDate(
                    selectedDateObj,
                    schedule,
                    [], // Busy ranges vacío para servicios fixed
                    slotDuration
                );

                console.log('[AA][Reservation][FIXED] Slots calculados:', slots ? slots.length : 0);

                if (slots && slots.length > 0) {
                    // Convertir slots Date a formato "HH:MM"
                    const slotsHHMM = slots.map(function(slot) {
                        if (slot instanceof Date) {
                            return window.DateUtils.hm(slot);
                        }
                        return String(slot);
                    });

                    console.log('[AA][Reservation][FIXED] Horarios disponibles:', slotsHHMM);

                    // 4️⃣ Renderizar slots en el formato del admin
                    this.renderAssignmentSlots(slotsHHMM, this.state.selectedDate, null);
                } else {
                    console.log('[AA][Reservation][FIXED] ❌ No hay horarios disponibles para esta fecha');
                    // Limpiar contenedor de slots
                    const container = document.getElementById('slot-container-admin');
                    if (container) {
                        container.innerHTML = 'No hay horarios disponibles para esta fecha.';
                    }
                }

                console.log('[AA][Reservation][FIXED] ✅ Cálculo completado');
                console.groupEnd();

            } catch (error) {
                console.error('[AA][Reservation][FIXED] ❌ Error al calcular slots fijos:', error);
                console.groupEnd();
            }
        },

        /**
         * Reset staff select to initial state
         */
        resetStaffSelect: function() {
            const staffSelect = document.getElementById('aa-reservation-staff');
            
            if (staffSelect) {
                staffSelect.innerHTML = '<option value="">Seleccione primero un servicio y una fecha</option>';
                staffSelect.disabled = true;
            }

            this.state.selectedStaff = null;
            this.state.currentAssignments = [];
            this.state.selectedAssignmentId = null;
            this.updateAssignmentIdInput(null);
        },

        /**
         * Handle staff selection (only log, no slot generation yet)
         */
        handleStaffSelection: function() {
            const { selectedStaff, selectedDate, currentAssignments } = this.state;

            if (!selectedStaff) {
                console.log('[AA][Reservation] Staff deseleccionado');
                return;
            }

            // Filter assignments for selected staff
            const staffAssignments = currentAssignments.filter(function(a) {
                return String(a.staff_id) === String(selectedStaff);
            });

            console.log('[AA][Reservation] Staff seleccionado:', selectedStaff);
            console.log('[AA][Reservation] Asignaciones completas:', staffAssignments);

            // Guardar assignment_id (por ahora usamos el primer assignment si hay múltiples)
            // TODO: Refinar para seleccionar assignment específico cuando se implemente selección de slots
            if (staffAssignments && staffAssignments.length > 0) {
                const firstAssignment = staffAssignments[0];
                this.state.selectedAssignmentId = firstAssignment.id;
                this.updateAssignmentIdInput(firstAssignment.id);
                console.log('[AA][Reservation] Assignment ID seleccionado:', firstAssignment.id);
            } else {
                this.state.selectedAssignmentId = null;
                this.updateAssignmentIdInput(null);
            }
            
            // Log details for debugging
            staffAssignments.forEach(function(assignment, index) {
                console.log('[AA][Reservation] Asignación #' + (index + 1) + ':', {
                    id: assignment.id,
                    start: assignment.start_time,
                    end: assignment.end_time,
                    staff: assignment.staff_name,
                    area: assignment.service_area_name,
                    capacity: assignment.capacity
                });
            });

            // ============================================
            // 🧮 CALCULAR SLOTS DE 30 MINUTOS
            // ============================================
            // Delegar cálculo al service
            if (typeof window.AAAssignmentsAvailability !== 'undefined' && 
                typeof window.AAAssignmentsAvailability.getSlotsForStaffAndDate === 'function') {
                
                const availableSlots = window.AAAssignmentsAvailability.getSlotsForStaffAndDate(
                    staffAssignments,
                    selectedDate,
                    30
                );

                if (availableSlots) {
                    console.log('[AA][Reservation] ✅ Slots disponibles calculados:', availableSlots.length);
                    console.log('[AA][Reservation] 🧮 Slots disponibles:', availableSlots.map(function(s) {
                        return typeof window.DateUtils !== 'undefined' && typeof window.DateUtils.hm === 'function' 
                            ? window.DateUtils.hm(s) 
                            : s.toTimeString().substring(0, 5);
                    }));
                    
                    // ============================================
                    // 📊 OBTENER Y CALCULAR SLOTS OCUPADOS
                    // ============================================
                    const self = this;
                    const assignmentIds = staffAssignments.map(function(a) {
                        return parseInt(a.id);
                    });
                    
                    if (assignmentIds.length > 0 && typeof window.AABusyRangesAssignments !== 'undefined' &&
                        typeof window.AABusyRangesAssignments.getBusyRangesByAssignments === 'function') {
                        
                        window.AABusyRangesAssignments.getBusyRangesByAssignments(assignmentIds, selectedDate)
                            .then(function(result) {
                                if (result.success && result.data && result.data.busy_ranges) {
                                    const busyRanges = result.data.busy_ranges;
                                    console.log('[AA][Reservation] 📋 Busy ranges obtenidos:', busyRanges.length);
                                    
                                    // Generar slots ocupados desde busy ranges
                                    const occupiedSlots = [];
                                    
                                    busyRanges.forEach(function(range) {
                                        // range.start es datetime (ej: "2026-01-02 15:00:00")
                                        // Extraer solo la hora
                                        const startDateTime = new Date(range.start);
                                        const startTime = window.DateUtils.hm(startDateTime);
                                        
                                        // Calcular duración en minutos
                                        const endDateTime = new Date(range.end);
                                        const durationMinutes = Math.round((endDateTime - startDateTime) / (1000 * 60));
                                        
                                        // Generar slots ocupados
                                        const reservationSlots = window.DateUtils.generateSlotsFromStartTime(
                                            startTime,
                                            durationMinutes,
                                            30
                                        );
                                        
                                        occupiedSlots.push(...reservationSlots);
                                        console.log('[AA][Reservation] 📌 Reserva:', {
                                            start: startTime,
                                            duration: durationMinutes + ' min',
                                            slots: reservationSlots
                                        });
                                    });
                                    
                                    // Eliminar duplicados y ordenar
                                    const uniqueOccupiedSlots = [...new Set(occupiedSlots)].sort();
                                    console.log('[AA][Reservation] 🚫 Slots ocupados (total):', uniqueOccupiedSlots);
                                    
                                    // Descontar slots ocupados de slots disponibles
                                    if (typeof window.DateUtils !== 'undefined' && typeof window.DateUtils.hm === 'function') {
                                        const availableSlotStrings = availableSlots.map(function(s) {
                                            return window.DateUtils.hm(s);
                                        });
                                        
                                        const finalSlots = availableSlotStrings.filter(function(slot) {
                                            return !uniqueOccupiedSlots.includes(slot);
                                        });
                                        
                                        console.log('[AA][Reservation] ✅ Slots disponibles finales (después de descontar ocupados):', finalSlots);
                                        console.log('[AA][Reservation] 📊 Resumen:', {
                                            disponibles: availableSlotStrings.length,
                                            ocupados: uniqueOccupiedSlots.length,
                                            finales: finalSlots.length
                                        });
                                        
                                        // 🎨 RENDERIZAR SLOTS EN EL SELECTOR LEGACY
                                        self.renderAssignmentSlots(finalSlots, selectedDate, self.state.selectedAssignmentId);
                                    }
                                } else {
                                    // No hay busy ranges, usar slots disponibles directamente
                                    console.log('[AA][Reservation] No hay busy ranges para descontar');
                                    const availableSlotStrings = availableSlots.map(function(s) {
                                        return window.DateUtils.hm(s);
                                    });
                                    // Renderizar slots disponibles (sin ocupados)
                                    self.renderAssignmentSlots(availableSlotStrings, selectedDate, self.state.selectedAssignmentId);
                                }
                            })
                            .catch(function(error) {
                                console.error('[AA][Reservation] Error al obtener busy ranges:', error);
                            });
                    } else {
                        console.log('[AA][Reservation] No se pudieron obtener busy ranges (servicio no disponible)');
                    }
                } else {
                    console.log('[AA][Reservation] No se pudieron calcular slots');
                }
            } else {
                console.warn('[AA][Reservation] AAAssignmentsAvailability.getSlotsForStaffAndDate no disponible');
            }
        },

        /**
         * Render assignment-based slots in the legacy slot selector container
         * Replaces legacy slot UI with assignment-based slots while maintaining compatibility
         * @param {Array<string>} slotsHHMM - Array of time strings in "HH:MM" format
         * @param {string} dateStr - Date string in YYYY-MM-DD format
         * @param {number|string} assignmentId - Assignment ID for data attribute
         */
        renderAssignmentSlots: function(slotsHHMM, dateStr, assignmentId) {
            const container = document.getElementById('slot-container-admin');
            const fechaInput = document.getElementById('cita-fecha');
            
            if (!container) {
                console.warn('[AA][Reservation] No se encontró #slot-container-admin');
                return;
            }
            
            // Limpiar contenedor
            container.innerHTML = '';
            
            // Caso: No hay slots disponibles
            if (!slotsHHMM || slotsHHMM.length === 0) {
                container.textContent = 'No hay horarios disponibles para este personal.';
                console.log('[AA][Reservation] Sin slots para renderizar');
                return;
            }
            
            console.log('[AA][Reservation] 🎨 Renderizando ' + slotsHHMM.length + ' slots en selector legacy');
            
            // Crear select con el mismo formato legacy
            const select = document.createElement('select');
            select.id = 'slot-selector-admin';
            select.className = 'slot-selector-admin';
            select.style.width = '100%';
            select.style.padding = '8px';
            select.style.marginTop = '10px';
            select.style.fontSize = '14px';
            select.style.border = '1px solid #ddd';
            select.style.borderRadius = '4px';
            
            // Agregar opciones
            slotsHHMM.forEach(function(timeStr, index) {
                // Convertir "HH:MM" a Date ISO para el value (compatibilidad legacy)
                const [hours, minutes] = timeStr.split(':').map(Number);
                const slotDate = new Date(dateStr + 'T00:00:00');
                slotDate.setHours(hours, minutes, 0, 0);
                
                const option = document.createElement('option');
                option.value = slotDate.toISOString();
                option.textContent = timeStr;
                
                // Agregar data-assignment-id para futura sustitución
                if (assignmentId) {
                    option.dataset.assignmentId = assignmentId;
                }
                
                if (index === 0) {
                    option.selected = true;
                }
                
                select.appendChild(option);
            });
            
            // Evento de cambio (compatibilidad legacy)
            var self = this;
            select.addEventListener('change', function() {
                const chosenSlot = new Date(select.value);
                const selectedOption = select.options[select.selectedIndex];
                
                // Actualizar fechaInput con formato compatible
                if (fechaInput && typeof window.DateUtils !== 'undefined') {
                    const formattedDate = chosenSlot.toLocaleDateString('es-MX', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    });
                    const formattedTime = window.DateUtils.hm(chosenSlot);
                    fechaInput.value = formattedDate + ' ' + formattedTime;
                }
                
                // Log para debug
                console.log('[AA][Reservation] 🕒 Slot seleccionado:', {
                    time: window.DateUtils ? window.DateUtils.hm(chosenSlot) : chosenSlot.toTimeString(),
                    iso: chosenSlot.toISOString(),
                    assignmentId: selectedOption.dataset.assignmentId || null
                });
            });
            
            // Agregar al contenedor
            container.appendChild(select);
            
            // Seleccionar primer slot por defecto y actualizar fechaInput
            if (slotsHHMM.length > 0 && fechaInput && typeof window.DateUtils !== 'undefined') {
                const firstSlot = new Date(select.value);
                const formattedDate = firstSlot.toLocaleDateString('es-MX', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
                const formattedTime = window.DateUtils.hm(firstSlot);
                fechaInput.value = formattedDate + ' ' + formattedTime;
            }
            
            console.log('[AA][Reservation] ✅ Select de slots renderizado con ' + slotsHHMM.length + ' opciones');
        },

        /**
         * Setup form submit handler to close modal on success
         * @param {HTMLFormElement} form 
         */
        setupFormSubmitHandler: function(form) {
            const self = this;
            
            // Interceptar el submit para cerrar modal después de éxito
            form.addEventListener('submit', async function(e) {
                // El AdminReservationController maneja la lógica de submit
                // Solo necesitamos escuchar el éxito para cerrar el modal
                
                // Crear observer para detectar cuando se muestra el alert de éxito
                // y luego cerrar el modal
                const originalAlert = window.alert;
                window.alert = function(message) {
                    originalAlert(message);
                    
                    // Si el mensaje indica éxito, cerrar modal
                    if (message && message.includes('✅')) {
                        console.log('[ReservationModal] Éxito detectado, cerrando modal...');
                        self.close();
                    }
                    
                    // Restaurar alert original
                    window.alert = originalAlert;
                };
            }, true); // Capture phase para ejecutar antes
        },

        /**
         * Open the reservation modal
         */
        open: function() {
            if (typeof AAAdmin === 'undefined' || typeof AAAdmin.openModal !== 'function') {
                console.error('[ReservationModal] AAAdmin.openModal no disponible');
                return;
            }

            console.log('[ReservationModal] Abriendo modal...');

            AAAdmin.openModal({
                title: this.config.title,
                body: this.getBodyContent()
            });

            // Inicializar controladores después de abrir
            this.initControllers();
            
            console.log('[ReservationModal] ✅ Modal abierto');
        },

        /**
         * Close the reservation modal
         */
        close: function() {
            if (typeof AAAdmin === 'undefined' || typeof AAAdmin.closeModal !== 'function') {
                console.error('[ReservationModal] AAAdmin.closeModal no disponible');
                return;
            }

            AAAdmin.closeModal();
            this.initialized = false;
            this.resetState();
            
            console.log('[ReservationModal] Modal cerrado');
        },

        /**
         * Initialize event listeners
         */
        init: function() {
            const self = this;
            const button = document.getElementById(this.config.buttonId);

            if (!button) {
                console.warn('[ReservationModal] Botón no encontrado: #' + this.config.buttonId);
                return;
            }

            button.addEventListener('click', function(event) {
                event.preventDefault();
                self.open();
            });

            console.log('[ReservationModal] ✅ Inicializado correctamente');
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ReservationModal.init();
        });
    } else {
        ReservationModal.init();
    }

    // Expose to global scope
    window.AAReservationModal = ReservationModal;

})();
