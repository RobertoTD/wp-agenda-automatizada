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

                this.initialized = true;
                console.log('[ReservationModal] ✅ Todos los controladores inicializados');
                
            }, 100); // 100ms delay para asegurar DOM ready
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
