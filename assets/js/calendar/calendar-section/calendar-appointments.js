/**
 * Calendar Appointments - Load and render appointments
 */

(function() {
    'use strict';

    /**
     * Cargar citas de un día específico y renderizarlas en el timeline
     * @param {Map} slotRowIndex - Mapa de slots
     * @param {Array} timeSlots - Array de time slots
     * @param {string} fechaStr - Fecha en formato YYYY-MM-DD (opcional, usa hoy por defecto)
     */
    function cargarYRenderizarCitas(slotRowIndex, timeSlots, fechaStr) {
        // Si no se proporciona fecha, usar hoy
        if (!fechaStr) {
            const today = new Date();
            fechaStr = window.DateUtils.ymd(today);
        }
        
        // Preparar datos para la petición AJAX
        const formData = new FormData();
        formData.append('action', 'aa_get_citas_por_dia');
        formData.append('fecha', fechaStr);
        
        if (window.AA_CALENDAR_DATA?.nonce) {
            formData.append('_wpnonce', window.AA_CALENDAR_DATA.nonce);
        }
        
        const ajaxurl = window.AA_CALENDAR_DATA?.ajaxurl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.citas) {
                // Renderizar cada cita en su slot correspondiente
                data.data.citas.forEach(cita => {
                    renderizarCitaEnTimeline(cita, slotRowIndex, timeSlots);
                });
            }
        })
        .catch(err => {
            console.error('Error al cargar citas:', err);
        });
    }

    /**
     * Renderizar una cita en el timeline
     */
    function renderizarCitaEnTimeline(cita, slotRowIndex, timeSlots) {
        if (!cita.fecha) return;
        
        // Usar el service para calcular posición
        const posicion = window.AdminCalendarService?.calcularPosicionCita(cita);
        if (!posicion) return;
        
        const { slotInicio, bloquesOcupados } = posicion;
        
        // Obtener el índice de fila del slot inicial
        const slotData = slotRowIndex.get(slotInicio);
        if (!slotData) return; // Slot no encontrado
        const startRow = slotData.rowIndex;
        
        // Obtener el grid
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Crear la card de la cita usando CalendarAppointmentCard
        const card = window.CalendarAppointmentCard?.crearCardCita(cita);
        if (!card) return;
        
        // Configurar posición en el grid
        card.style.gridColumn = '2';
        card.style.gridRow = `${startRow} / span ${bloquesOcupados}`;
        
        // Insertar la card directamente en el grid
        grid.appendChild(card);
    }

    // Exponer API pública
    window.CalendarAppointments = {
        cargarYRenderizarCitas: cargarYRenderizarCitas
    };

})();

