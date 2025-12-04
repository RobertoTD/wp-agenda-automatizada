/**
 * ModalDefaultAdapter - Adaptador de modal nativo para WPAgenda
 * 
 * Modal HTML puro para confirmación de reservas.
 * Compatible con WPAgenda.registerModalAdapter().
 */

(function(global) {
    'use strict';

    /**
     * Crea una instancia del adaptador de modal.
     * @returns {Object} Adaptador con métodos open, close, setLoading.
     */
    function create() {
        let overlayEl = null;
        let modalEl = null;
        let submitBtn = null;
        let onSubmitCallback = null;
        const originalBtnText = 'Confirmar';

        /**
         * Genera el HTML del modal.
         * @param {Object} options - { servicio, fecha, hora }
         * @returns {string}
         */
        function buildModalHTML(options) {
            const { servicio, fecha, hora } = options;

            return `
                <div class="aa-modal-overlay"></div>
                <div class="aa-modal">
                    <button type="button" class="aa-modal-close">&times;</button>
                    <h3 class="aa-modal-title">Confirmar reserva</h3>

                    <div class="aa-modal-summary">
                        <p><strong>Servicio:</strong> ${servicio || 'No especificado'}</p>
                        <p><strong>Fecha:</strong> ${fecha || ''}</p>
                        <p><strong>Hora:</strong> ${hora || ''}</p>
                    </div>

                    <form class="aa-modal-form">
                        <label for="aa-nombre">Nombre</label>
                        <input type="text" id="aa-nombre" class="aa-input" required>

                        <label for="aa-telefono">Teléfono</label>
                        <input type="tel" id="aa-telefono" class="aa-input" required>

                        <label for="aa-correo">Correo</label>
                        <input type="email" id="aa-correo" class="aa-input" required>

                        <button type="submit" class="aa-btn">${originalBtnText}</button>
                    </form>
                </div>
            `;
        }

        /**
         * Maneja el envío del formulario.
         * @param {Event} e
         */
        function handleSubmit(e) {
            e.preventDefault();

            const nombre = modalEl.querySelector('#aa-nombre').value.trim();
            const telefono = modalEl.querySelector('#aa-telefono').value.trim();
            const correo = modalEl.querySelector('#aa-correo').value.trim();

            // Validación simple
            if (!nombre || !telefono || !correo) {
                console.warn('[ModalDefaultAdapter] Todos los campos son obligatorios');
                return;
            }

            if (onSubmitCallback && typeof onSubmitCallback === 'function') {
                onSubmitCallback({ nombre, telefono, correo });
            }
        }

        /**
         * Remueve el modal del DOM.
         */
        function removeFromDOM() {
            if (overlayEl && overlayEl.parentNode) {
                overlayEl.parentNode.removeChild(overlayEl);
            }
            if (modalEl && modalEl.parentNode) {
                modalEl.parentNode.removeChild(modalEl);
            }
            overlayEl = null;
            modalEl = null;
            submitBtn = null;
            onSubmitCallback = null;
        }

        // =====================================================================
        // API Pública del Adaptador
        // =====================================================================

        return {
            /**
             * Abre el modal con la información de la reserva.
             * @param {Object} options - { servicio, fecha, hora, onSubmit }
             */
            open(options) {
                // Cerrar modal previo si existe
                this.close();

                const { onSubmit } = options || {};
                onSubmitCallback = onSubmit;

                // Crear contenedor temporal para parsear HTML
                const wrapper = document.createElement('div');
                wrapper.innerHTML = buildModalHTML(options);

                overlayEl = wrapper.querySelector('.aa-modal-overlay');
                modalEl = wrapper.querySelector('.aa-modal');
                submitBtn = modalEl.querySelector('.aa-btn');

                // Insertar en el DOM
                document.body.appendChild(overlayEl);
                document.body.appendChild(modalEl);

                // Event listeners
                overlayEl.addEventListener('click', () => this.close());
                modalEl.querySelector('.aa-modal-close')?.addEventListener('click', () => this.close());
                modalEl.querySelector('.aa-modal-form')?.addEventListener('submit', handleSubmit);
            },

            /**
             * Cierra el modal y limpia el DOM.
             */
            close() {
                removeFromDOM();
            },

            /**
             * Establece el estado de carga del botón submit.
             * @param {boolean} isLoading
             */
            setLoading(isLoading) {
                if (!submitBtn) return;

                if (isLoading) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Procesando…';
                } else {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            }
        };
    }

    // =========================================================================
    // Exportar globalmente
    // =========================================================================

    global.modalDefaultAdapter = { create };

    console.log('✅ modalDefaultAdapter.js cargado');

})(window);
