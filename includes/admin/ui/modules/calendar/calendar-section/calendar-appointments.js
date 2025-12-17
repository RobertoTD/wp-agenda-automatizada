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
                // Calcular posiciones de TODAS las citas primero
                const citasConPosicion = [];
                
                data.data.citas.forEach(cita => {
                    // Usar el service para calcular posición
                    const posicion = window.AdminCalendarService?.calcularPosicionCita(cita);
                    if (!posicion) return;
                    
                    const { slotInicio, bloquesOcupados } = posicion;
                    
                    // Obtener el índice de fila del slot inicial
                    const slotData = slotRowIndex.get(slotInicio);
                    if (!slotData) return; // Slot no encontrado
                    const startRow = slotData.rowIndex;
                    
                    citasConPosicion.push({
                        cita: cita,
                        id: cita.id,
                        startRow: startRow,
                        bloquesOcupados: bloquesOcupados,
                        slotInicio: slotInicio
                    });
                });
                
                // Calcular solapamientos usando CalendarOverlap
                const overlaps = window.CalendarOverlap?.computeOverlaps(citasConPosicion) || {};
                
                // Renderizar cada cita con información de solapamiento
                citasConPosicion.forEach(citaConPos => {
                    renderizarCitaEnTimeline(
                        citaConPos.cita,
                        slotRowIndex,
                        timeSlots,
                        citaConPos.startRow,
                        citaConPos.bloquesOcupados,
                        overlaps[citaConPos.id]
                    );
                });
            }
        })
        .catch(err => {
            console.error('Error al cargar citas:', err);
        });
    }

    /**
     * Renderizar una cita en el timeline
     * @param {Object} cita - Objeto de cita
     * @param {Map} slotRowIndex - Mapa de slots
     * @param {Array} timeSlots - Array de time slots
     * @param {number} startRow - Fila de inicio (ya calculada)
     * @param {number} bloquesOcupados - Bloques ocupados (ya calculado)
     * @param {Object} overlapInfo - Información de solapamiento { overlapIndex, overlapCount }
     */
    function renderizarCitaEnTimeline(cita, slotRowIndex, timeSlots, startRow, bloquesOcupados, overlapInfo) {
        if (!cita.fecha) return;
        
        // Obtener el grid
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Crear la card de la cita usando CalendarAppointmentCard
        const card = window.CalendarAppointmentCard?.crearCardCita(cita);
        if (!card) return;
        
        // Si hay solapamiento, usar wrapper para split horizontal
        if (overlapInfo && overlapInfo.overlapCount > 1) {
            const width = 100 / overlapInfo.overlapCount;
            const left = overlapInfo.overlapIndex * width;
            
            // Crear wrapper contenedor que ocupa la celda del grid
            const wrapper = document.createElement('div');
            wrapper.style.gridColumn = '2';
            wrapper.style.gridRow = `${startRow} / span ${bloquesOcupados}`;
            wrapper.style.position = 'relative';
            wrapper.style.width = '100%';
            wrapper.style.height = '100%';
            
            // Configurar card con posición absoluta dentro del wrapper
            card.style.position = 'absolute';
            card.style.width = width + '%';
            card.style.left = left + '%';
            card.style.height = '100%';
            card.style.top = '0';
            
            // Insertar card en wrapper y wrapper en grid
            wrapper.appendChild(card);
            grid.appendChild(wrapper);
        } else {
            // Sin solapamiento: comportamiento normal (card directamente en grid)
            card.style.gridColumn = '2';
            card.style.gridRow = `${startRow} / span ${bloquesOcupados}`;
            grid.appendChild(card);
        }
    }

    // Exponer API pública
    window.CalendarAppointments = {
        cargarYRenderizarCitas: cargarYRenderizarCitas
    };

})();

