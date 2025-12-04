/**
 * SlotsDefaultAdapter - Adaptador de slots nativo para WPAgenda
 * 
 * Renderiza lista de horarios disponibles en HTML puro.
 * Compatible con WPAgenda.registerSlotsAdapter().
 */

(function(global) {
    'use strict';

    /**
     * Crea una instancia del adaptador de slots.
     * @returns {Object} Adaptador con métodos render, getSelectedSlot, clear.
     */
    function create() {
        let container = null;
        let selectedSlot = null;
        let onSelectCallback = null;

        /**
         * Formatea un Date a string HH:MM.
         * @param {Date} date
         * @returns {string}
         */
        function formatTime(date) {
            const h = String(date.getHours()).padStart(2, '0');
            const m = String(date.getMinutes()).padStart(2, '0');
            return `${h}:${m}`;
        }

        /**
         * Compara dos fechas por valor de tiempo.
         * @param {Date} a
         * @param {Date} b
         * @returns {boolean}
         */
        function isSameSlot(a, b) {
            if (!a || !b) return false;
            return a.getTime() === b.getTime();
        }

        /**
         * Maneja el click en un slot.
         * @param {Event} e
         * @param {Date[]} slots
         */
        function handleSlotClick(e, slots) {
            const target = e.target;
            if (!target.classList.contains('aa-slot') || target.classList.contains('aa-slot--disabled')) return;

            const index = parseInt(target.dataset.index, 10);
            if (isNaN(index) || !slots[index]) return;

            selectedSlot = slots[index];

            // Actualizar clases visuales
            container.querySelectorAll('.aa-slot').forEach(el => {
                el.classList.remove('aa-slot--selected');
            });
            target.classList.add('aa-slot--selected');

            if (onSelectCallback && typeof onSelectCallback === 'function') {
                onSelectCallback(selectedSlot);
            }
        }

        /**
         * Renderiza el HTML de los slots.
         * @param {Date[]} slots
         */
        function renderSlots(slots) {
            if (!container) return;

            if (!slots || slots.length === 0) {
                container.innerHTML = '<p class="aa-slots-empty">No hay horarios disponibles</p>';
                return;
            }

            let html = '<div class="aa-slots-grid">';

            slots.forEach((slot, index) => {
                const isSelected = isSameSlot(slot, selectedSlot);
                let classes = 'aa-slot';
                if (isSelected) classes += ' aa-slot--selected';

                html += `<button type="button" class="${classes}" data-index="${index}">${formatTime(slot)}</button>`;
            });

            html += '</div>';
            container.innerHTML = html;

            container.querySelector('.aa-slots-grid')?.addEventListener('click', (e) => handleSlotClick(e, slots));
        }

        // =====================================================================
        // API Pública del Adaptador
        // =====================================================================

        return {
            /**
             * Renderiza los slots en el contenedor especificado.
             * @param {string|HTMLElement} containerId - Selector o elemento contenedor.
             * @param {Date[]} validSlots - Array de fechas con horarios válidos.
             * @param {Function} onSelectSlot - Callback al seleccionar un slot.
             */
            render(containerId, validSlots, onSelectSlot) {
                container = containerId instanceof HTMLElement
                    ? containerId
                    : document.querySelector(containerId);

                if (!container) {
                    console.error('[SlotsDefaultAdapter] Contenedor no encontrado');
                    return;
                }

                onSelectCallback = onSelectSlot;
                selectedSlot = null;
                renderSlots(validSlots);
            },

            /**
             * Obtiene el slot seleccionado actualmente.
             * @returns {Date|null}
             */
            getSelectedSlot() {
                return selectedSlot;
            },

            /**
             * Limpia el contenedor y reinicia el estado.
             */
            clear() {
                if (container) {
                    container.innerHTML = '';
                }
                selectedSlot = null;
                onSelectCallback = null;
            }
        };
    }

    // =========================================================================
    // Exportar globalmente
    // =========================================================================

    global.slotsDefaultAdapter = { create };

    console.log('✅ slotsDefaultAdapter.js cargado');

})(window);
