/**
 * Appointments Modal Controller
 * 
 * Manages the opening of the appointments modal and coordinates
 * with AppointmentsController for data fetching and rendering.
 * 
 * @package AgendaAutomatizada
 * @since 1.0.0
 */
(function() {
    'use strict';

    /**
     * AppointmentsModal namespace
     */
    const AppointmentsModal = {
        /**
         * Modal configuration
         */
        config: {
            templateId: 'aa-appointments-modal-template',
            title: 'Citas'
        },

        /**
         * Current filters
         */
        currentFilters: {},

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
            console.error('[AppointmentsModal] Template not found: #' + this.config.templateId);
            return '<div class="p-4 text-red-600">Error: Template de citas no encontrado.</div>';
        },

        /**
         * Get modal title based on filters
         * @param {Object} filters - Current filters
         * @returns {string} Modal title
         */
        getTitle: function(filters) {
            const titles = {
                'pending': 'Citas Pendientes',
                'confirmed': 'Citas Confirmadas',
                'cancelled': 'Citas Canceladas'
            };
            
            if (filters.type && titles[filters.type]) {
                return titles[filters.type];
            }
            
            return this.config.title;
        },

        /**
         * Open the appointments modal
         * @param {Object} [filters={}] - Optional filters { type, unread }
         */
        open: function(filters = {}) {
            if (typeof AAAdmin === 'undefined' || typeof AAAdmin.openModal !== 'function') {
                console.error('[AppointmentsModal] AAAdmin.openModal no disponible');
                return;
            }

            console.log('[AppointmentsModal] Abriendo modal con filtros:', filters);

            // Store current filters
            this.currentFilters = filters;

            // Open modal with dynamic title
            AAAdmin.openModal({
                title: this.getTitle(filters),
                body: this.getBodyContent()
            });

            // Initialize controller after modal content is rendered
            this.initController(filters);

            console.log('[AppointmentsModal] ✅ Modal abierto');
        },

        /**
         * Initialize the appointments controller
         * @param {Object} filters - Filters to pass to controller
         */
        initController: function(filters) {
            // Small delay to ensure DOM is ready
            setTimeout(function() {
                if (typeof window.AAAppointmentsController !== 'undefined') {
                    console.log('[AppointmentsModal] Inicializando AppointmentsController...');
                    window.AAAppointmentsController.init(filters);
                } else {
                    console.error('[AppointmentsModal] ❌ AAAppointmentsController no disponible');
                }
            }, 50);
        }
    };

    /**
     * Initialize event listeners
     */
    function init() {
        // Botón de búsqueda en el calendario
        const btnSearch = document.getElementById('aa-btn-search');
        
        if (btnSearch) {
            btnSearch.addEventListener('click', function(event) {
                event.preventDefault();
                AppointmentsModal.open();
            });
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose to global scope
    window.AAAppointmentsModal = AppointmentsModal;

})();
