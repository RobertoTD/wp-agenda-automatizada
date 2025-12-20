/**
 * Reservation Modal Controller
 * 
 * Handles opening/closing of the reservation modal.
 * No business logic - only UI interaction.
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
            title: 'Agendar cita'
        },

        /**
         * Get the modal body content
         * @returns {string} HTML content for modal body
         */
        getBodyContent: function() {
            // Check if template exists in DOM (loaded from index.php)
            const template = document.getElementById('aa-reservation-modal-content');
            if (template) {
                return template.outerHTML;
            }

            // Fallback: inline placeholder content
            return `
                <div class="p-6 text-center">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-blue-100">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="text-gray-600">
                        Contenido en construcción
                    </p>
                    <p class="text-sm text-gray-400 mt-2">
                        El formulario de reservación estará disponible próximamente.
                    </p>
                </div>
            `;
        },

        /**
         * Open the reservation modal
         */
        open: function() {
            if (typeof AAAdmin === 'undefined' || typeof AAAdmin.openModal !== 'function') {
                console.error('[ReservationModal] AAAdmin.openModal no disponible');
                return;
            }

            AAAdmin.openModal({
                title: this.config.title,
                body: this.getBodyContent()
            });

            console.log('[ReservationModal] Modal abierto');
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

            console.log('[ReservationModal] Inicializado correctamente');
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

    // Expose to global scope for potential external use
    window.AAReservationModal = ReservationModal;

})();

