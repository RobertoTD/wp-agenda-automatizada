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
         * Track if global listeners are bound (bind once)
         */
        _bound: false,

        /**
         * Store handler references for cleanup
         */
        _handlers: {},

        /**
         * Store timeout IDs for cleanup
         */
        _timeouts: [],

        /**
         * Store interval IDs for cleanup
         */
        _intervals: [],

        /**
         * Assignment flow controller instance (for cleanup)
         */
        _assignmentFlow: null,

        /**
         * Client controller instance (for cleanup)
         */
        _clientController: null,

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
            const timeoutId = setTimeout(() => {
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

                // Reset state antes de inicializar flujo
                this.resetState();

                // 5️⃣ Inicializar flujo Servicio → Fecha → Staff (paralelo)
                if (window.AdminReservationAssignmentFlowController) {
                    this._assignmentFlow = window.AdminReservationAssignmentFlowController.init({
                        getState: () => this.state,
                        elements: {
                            serviceSelectId: 'cita-servicio',
                            staffSelectId: 'aa-reservation-staff'
                        },
                        callbacks: {
                            hideStaffSelector: () => this.hideStaffSelector(),
                            showStaffSelector: () => this.showStaffSelector(),
                            resetStaffSelect: () => this.resetStaffSelect(),
                            updateAssignmentIdInput: (assignmentId) => this.updateAssignmentIdInput(assignmentId),
                            renderAssignmentSlots: (slotStrings, date, assignmentId) => this.renderAssignmentSlots(slotStrings, date, assignmentId),
                            refreshAdminCalendarByService: (serviceKey) => this.refreshAdminCalendarByService(serviceKey)
                        }
                    });
                } else {
                    console.warn('[ReservationModal] AdminReservationAssignmentFlowController no disponible');
                }

                // 6️⃣ Inicializar búsqueda de clientes
                if (window.ReservationClientController) {
                    this._clientController = window.ReservationClientController.init({
                        searchInputId: 'aa-cliente-search',
                        selectId: 'cita-cliente',
                        inlineContainerId: 'aa-reservation-client-inline',
                        createButtonId: 'aa-btn-crear-cliente-reservation'
                    });
                } else {
                    console.warn('[ReservationModal] ReservationClientController no disponible');
                }

                this.initialized = true;
                console.log('[ReservationModal] ✅ Todos los controladores inicializados');
                
                // 7️⃣ Refrescar disponibilidad local al abrir el modal
                if (window.AdminReservationController && typeof window.AdminReservationController.refreshLocalAvailability === 'function') {
                    window.AdminReservationController.refreshLocalAvailability();
                }
                
            }, 100); // 100ms delay para asegurar DOM ready
            
            // Guardar timeout ID para cleanup
            this._timeouts.push(timeoutId);
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

                // Usar CalendarAvailabilityService si está disponible
                const futureWindow = window.aa_future_window || 14;
                let hasDates = false;
                
                if (window.CalendarAvailabilityService) {
                    hasDates = await window.CalendarAvailabilityService.hasAvailableDates(serviceKey, { futureWindowDays: futureWindow });
                } else {
                    console.warn('[AA][Reservation] CalendarAvailabilityService no disponible, usando fallback');
                    // Fallback: asumir que tiene fechas disponibles
                    hasDates = true;
                }
                
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
            
            // Delegar cálculo de disponibilidad al servicio
            const futureWindow = window.aa_future_window || 14;
            const { availableDays, minDate, maxDate } = await window.CalendarAvailabilityService.getAvailableDaysByService(serviceKey, { futureWindowDays: futureWindow });
            
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

            // Cleanup: destruir controller de assignment flow
            if (this._assignmentFlow) {
                this._assignmentFlow.destroy();
                this._assignmentFlow = null;
            }

            // Cleanup: destruir controller de clientes
            if (this._clientController) {
                this._clientController.destroy();
                this._clientController = null;
            }

            // Cleanup: limpiar todos los timeouts
            this._timeouts.forEach(function(timeoutId) {
                clearTimeout(timeoutId);
            });
            this._timeouts = [];

            // Cleanup: limpiar todos los intervals (si hay)
            this._intervals.forEach(function(intervalId) {
                clearInterval(intervalId);
            });
            this._intervals = [];

            AAAdmin.closeModal();
            this.initialized = false;
            this.resetState();
            
            console.log('[ReservationModal] Modal cerrado y limpiado');
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
