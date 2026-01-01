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
            currentAssignments: []
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

                // 4️⃣ Inicializar flujo Servicio → Fecha → Staff (paralelo)
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
            servicioSelect.addEventListener('change', function() {
                self.state.selectedService = this.value;
                console.log('[AA][Reservation] Servicio seleccionado:', self.state.selectedService);
                self.checkAndLoadAssignments();
            });

            // Listen for date changes (flatpickr sets value)
            // Use MutationObserver since flatpickr doesn't trigger 'change' reliably
            const dateObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        const newDate = fechaInput.value;
                        if (newDate && newDate !== self.state.selectedDate) {
                            // Extract date part (YYYY-MM-DD) from datetime
                            self.state.selectedDate = self.extractDate(newDate);
                            console.log('[AA][Reservation] Fecha seleccionada:', self.state.selectedDate);
                            self.checkAndLoadAssignments();
                        }
                    }
                });
            });

            dateObserver.observe(fechaInput, { attributes: true });

            // Also listen for input event as fallback
            fechaInput.addEventListener('input', function() {
                const newDate = this.value;
                if (newDate && newDate !== self.state.selectedDate) {
                    self.state.selectedDate = self.extractDate(newDate);
                    console.log('[AA][Reservation] Fecha seleccionada (input):', self.state.selectedDate);
                    self.checkAndLoadAssignments();
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
            }

            console.log('[AA][Reservation] ✅ Flujo de asignaciones inicializado');
        },

        /**
         * Reset state
         */
        resetState: function() {
            this.state = {
                selectedService: null,
                selectedDate: null,
                selectedStaff: null,
                currentAssignments: []
            };
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
                
                const slots = window.AAAssignmentsAvailability.getSlotsForStaffAndDate(
                    staffAssignments,
                    selectedDate,
                    30
                );

                if (slots) {
                    console.log('[AA][Reservation] ✅ Slots calculados exitosamente');
                } else {
                    console.log('[AA][Reservation] No se pudieron calcular slots');
                }
            } else {
                console.warn('[AA][Reservation] AAAssignmentsAvailability.getSlotsForStaffAndDate no disponible');
            }
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
