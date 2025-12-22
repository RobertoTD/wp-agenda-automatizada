/**
 * Appointments Modal Controller
 * 
 * Manages the opening of the appointments modal.
 * Loads content from template and uses AAAdmin.openModal.
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
         * Open the appointments modal
         * @param {Object} [filters={}] - Optional filters (not used yet)
         */
        open: function(filters = {}) {
            if (typeof AAAdmin === 'undefined' || typeof AAAdmin.openModal !== 'function') {
                console.error('[AppointmentsModal] AAAdmin.openModal no disponible');
                return;
            }

            console.log('[AppointmentsModal] Abriendo modal...');

            AAAdmin.openModal({
                title: this.config.title,
                body: this.getBodyContent()
            });

            console.log('[AppointmentsModal] ✅ Modal abierto');
        }
    };

    // Expose to global scope
    window.AAAppointmentsModal = AppointmentsModal;

})();

