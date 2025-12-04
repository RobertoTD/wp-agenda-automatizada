/**
 * CalendarDefaultAdapter - Adaptador de calendario nativo para WPAgenda
 * 
 * Calendario HTML puro sin dependencias externas.
 * Compatible con WPAgenda.registerCalendarAdapter().
 */

(function(global) {
    'use strict';

    const { ymd } = global.DateUtils || {};

    /**
     * Crea una instancia del adaptador de calendario.
     * @returns {Object} Adaptador con métodos render, setDate, getSelectedDate, destroy.
     */
    function create() {
        let container = null;
        let selectedDate = null;
        let viewDate = new Date();
        let config = {};

        const DAYS_ES = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        const MONTHS_ES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        /**
         * Verifica si una fecha está deshabilitada.
         */
        function isDateDisabled(date) {
            if (config.minDate && date < new Date(config.minDate.setHours(0,0,0,0))) return true;
            if (config.maxDate && date > new Date(config.maxDate.setHours(23,59,59,999))) return true;
            if (config.disableDateFn && typeof config.disableDateFn === 'function') {
                return config.disableDateFn(date);
            }
            return false;
        }

        /**
         * Genera el HTML del encabezado con navegación.
         */
        function renderHeader() {
            return `
                <div class="aa-calendar-header">
                    <button type="button" class="aa-calendar-nav aa-calendar-prev">&lt;</button>
                    <span class="aa-calendar-title">${MONTHS_ES[viewDate.getMonth()]} ${viewDate.getFullYear()}</span>
                    <button type="button" class="aa-calendar-nav aa-calendar-next">&gt;</button>
                </div>
            `;
        }

        /**
         * Genera el HTML del grid de días.
         */
        function renderDays() {
            const year = viewDate.getFullYear();
            const month = viewDate.getMonth();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = ymd ? ymd(new Date()) : '';

            let html = '<div class="aa-calendar-weekdays">';
            DAYS_ES.forEach(d => html += `<span>${d}</span>`);
            html += '</div><div class="aa-calendar-grid">';

            // Espacios vacíos antes del primer día
            for (let i = 0; i < firstDay; i++) {
                html += '<span class="aa-day aa-day--empty"></span>';
            }

            // Días del mes
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dateStr = ymd ? ymd(date) : '';
                const isSelected = selectedDate && ymd && ymd(selectedDate) === dateStr;
                const isToday = dateStr === today;
                const isDisabled = isDateDisabled(date);

                let classes = 'aa-day';
                if (isSelected) classes += ' aa-day--selected';
                if (isToday) classes += ' aa-day--today';
                if (isDisabled) classes += ' aa-day--disabled';

                html += `<span class="${classes}" data-date="${dateStr}">${day}</span>`;
            }

            html += '</div>';
            return html;
        }

        /**
         * Maneja el click en un día.
         */
        function handleDayClick(e) {
            const target = e.target;
            if (!target.classList.contains('aa-day') || target.classList.contains('aa-day--disabled')) return;

            const dateStr = target.dataset.date;
            if (!dateStr) return;

            const [year, month, day] = dateStr.split('-').map(Number);
            selectedDate = new Date(year, month - 1, day);

            renderCalendar();

            if (config.onDateSelected && typeof config.onDateSelected === 'function') {
                config.onDateSelected(selectedDate);
            }
        }

        /**
         * Navega al mes anterior.
         */
        function prevMonth() {
            viewDate.setMonth(viewDate.getMonth() - 1);
            renderCalendar();
        }

        /**
         * Navega al mes siguiente.
         */
        function nextMonth() {
            viewDate.setMonth(viewDate.getMonth() + 1);
            renderCalendar();
        }

        /**
         * Renderiza el calendario completo.
         */
        function renderCalendar() {
            if (!container) return;
            container.innerHTML = `
                <div class="aa-calendar">
                    ${renderHeader()}
                    ${renderDays()}
                </div>
            `;

            container.querySelector('.aa-calendar-prev')?.addEventListener('click', prevMonth);
            container.querySelector('.aa-calendar-next')?.addEventListener('click', nextMonth);
            container.querySelector('.aa-calendar-grid')?.addEventListener('click', handleDayClick);
        }

        // =====================================================================
        // API Pública del Adaptador
        // =====================================================================

        return {
            /**
             * Renderiza el calendario en el contenedor especificado.
             * @param {Object} cfg - Configuración: { container, minDate, maxDate, disableDateFn, onDateSelected }
             */
            render(cfg) {
                config = cfg || {};
                container = cfg.container instanceof HTMLElement 
                    ? cfg.container 
                    : document.querySelector(cfg.container);

                if (!container) {
                    console.error('[CalendarDefaultAdapter] Contenedor no encontrado');
                    return;
                }

                viewDate = config.minDate ? new Date(config.minDate) : new Date();
                renderCalendar();
            },

            /**
             * Establece la fecha seleccionada.
             * @param {Date} date - Fecha a seleccionar.
             * @param {boolean} triggerChange - Si true, dispara onDateSelected.
             */
            setDate(date, triggerChange = false) {
                if (!(date instanceof Date)) return;
                selectedDate = date;
                viewDate = new Date(date);
                renderCalendar();

                if (triggerChange && config.onDateSelected) {
                    config.onDateSelected(selectedDate);
                }
            },

            /**
             * Obtiene la fecha seleccionada actualmente.
             * @returns {Date|null}
             */
            getSelectedDate() {
                return selectedDate;
            },

            /**
             * Destruye el calendario y limpia el DOM.
             */
            destroy() {
                if (container) {
                    container.innerHTML = '';
                    container = null;
                }
                selectedDate = null;
                config = {};
            }
        };
    }

    // =========================================================================
    // Exportar globalmente
    // =========================================================================

    global.calendarDefaultAdapter = { create };

    console.log('✅ calendarDefaultAdapter.js cargado');

})(window);
