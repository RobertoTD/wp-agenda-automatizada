/**
 * Calendar Timeline - Timeline rendering and time slots
 */

(function() {
    'use strict';

    // Altura fija de cada fila del grid (debe coincidir en todos los archivos)
    const ROW_HEIGHT = 40;

    /**
     * Renderizar timeline para una fecha específica
     * @param {string} fechaStr - Fecha en formato YYYY-MM-DD
     * @param {Object} options - Opciones adicionales
     * @param {Array<{start: number, end: number}>} options.assignmentIntervals - Intervalos de asignaciones en minutos
     * @returns {Object} - Objeto con slotRowIndex y timeSlots para uso externo
     */
    function renderTimelineForDate(fechaStr, options) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) {
            console.error('❌ No se encontró el contenedor #aa-time-grid');
            return null;
        }

        const schedule = window.AA_CALENDAR_DATA.schedule;
        
        // Convertir fecha string a Date
        const fecha = new Date(fechaStr + 'T00:00:00');
        const weekday = window.DateUtils.getWeekdayName(fecha);
        
        // 1. Obtener intervalos del schedule fijo
        const scheduleIntervals = window.DateUtils.getDayIntervals(schedule, weekday) || [];
        
        // 2. Obtener intervalos de asignaciones (si existen)
        const assignmentIntervals = (options && options.assignmentIntervals) ? options.assignmentIntervals : [];
        
        // 3. Unir ambos sets de intervalos
        const allIntervals = [...scheduleIntervals, ...assignmentIntervals];
        
        // Configurar CSS Grid
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'auto 1fr';
        grid.style.gridAutoRows = ROW_HEIGHT + 'px'; // Altura FIJA para evitar que cards estiren filas
        grid.style.borderRadius = '0'; // Quitar esquinas redondeadas
        grid.style.borderTop = '15px solid rgb(243, 244, 246)'; // Línea horizontal gruesa en la parte superior
        grid.style.paddingTop = '2px'; // Padding superior del grid

        // Limpiar contenido existente
        grid.innerHTML = '';

        // Si no hay intervalos de ninguna fuente (schedule ni assignments)
        if (!allIntervals || allIntervals.length === 0) {
            const mensaje = document.createElement('div');
            mensaje.style.gridColumn = '1 / -1';
            mensaje.style.padding = '2rem';
            mensaje.style.textAlign = 'center';
            mensaje.style.color = '#6b7280';
            mensaje.textContent = 'No hay horarios configurados para hoy';
            grid.appendChild(mensaje);
            return null;
        }

        // Función para convertir minutos a formato HH:MM
        function minutesToTimeStr(minutes) {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        }

        // 4. Resolver el rango total: mínimo start y máximo end
        const minStart = Math.min(...allIntervals.map(iv => iv.start));
        const maxEnd = Math.max(...allIntervals.map(iv => iv.end));

        // 5. Generar timeSlots de 30 minutos sobre el rango unificado (sin duplicar)
        const timeSlots = [];
        for (let min = minStart; min < maxEnd; min += 30) {
            timeSlots.push(min);
        }

        // Mapa de minutos -> índice de fila en el grid (para calcular grid-row)
        const slotRowIndex = new Map();
        
        // Obtener hora actual en minutos para diferenciar pasado/futuro
        // Solo mostrar indicador si es el día actual
        const now = new Date();
        const todayStr = window.DateUtils.ymd(now);
        const isToday = fechaStr === todayStr;
        const minutosActuales = isToday ? window.DateUtils.minutesFromDate(now) : null;

        // Renderizar labels y content directamente en el grid
        timeSlots.forEach((minutes, index) => {
            const timeStr = minutesToTimeStr(minutes);
            
            // Label (columna 1)
            const label = document.createElement('div');
            label.className = 'aa-time-label';
            label.style.gridColumn = '1';
            label.style.gridRow = `${index + 1}`;
            label.style.padding = '0px 5px 10px 5px'; // Padding mínimo arriba para desplazar texto hacia arriba
            label.style.minWidth = '40px';
            label.style.position = 'relative';
            label.style.display = 'flex';
            label.style.alignItems = 'flex-start'; // Alinear texto hacia arriba del contenedor
            label.style.justifyContent = 'flex-start';
            
            // Crear span para el texto para poder aplicar transform solo al texto
            const textSpan = document.createElement('span');
            textSpan.textContent = timeStr;
            textSpan.style.color = '#6b7280'; // Gris medio-claro para el texto
            label.appendChild(textSpan);
            
            // Diferenciar visualmente slots pasados vs futuros (SOLO en label, solo si es hoy)
            if (isToday && minutosActuales !== null && minutes < minutosActuales) {
                // Slot pasado: fondo gris tenue en el label
                label.style.backgroundColor = '#f3f4f6';
            } else {
                // Slot actual o futuro: fondo normal en el label
                label.style.backgroundColor = '#ffffff';
            }
            
            grid.appendChild(label);
            
            // Reducir el tamaño de fuente en 3px y desplazar texto hacia arriba después de que el elemento esté en el DOM
            const computedStyle = window.getComputedStyle(textSpan);
            const currentFontSize = parseFloat(computedStyle.fontSize);
            if (!isNaN(currentFontSize)) {
                textSpan.style.fontSize = `${currentFontSize - 3}px`;
                // Desplazar texto hacia arriba para que la línea divisoria quede en medio del texto
                // La línea está en el borde superior del label, así que desplazamos el texto hacia arriba la mitad de su altura
                const textHeight = currentFontSize - 3; // Altura aproximada del texto
                // Para el primer elemento (index === 0), usar translateY(-3.5px), para los demás usar el cálculo normal
                if (index === 0) {
                    textSpan.style.transform = 'translateY(-3.5px)';
                } else {
                    textSpan.style.transform = `translateY(-${textHeight / 2}px)`;
                }
                textSpan.style.display = 'inline-block'; // Necesario para que transform funcione en span
            }
            
            // Content (columna 2) - vacío por ahora, las citas se insertarán aquí
            const content = document.createElement('div');
            content.className = 'aa-time-content';
            content.style.gridColumn = '2';
            content.style.gridRow = `${index + 1}`;
            content.style.minHeight = ROW_HEIGHT + 'px';
            content.style.maxHeight = ROW_HEIGHT + 'px'; // Forzar altura exacta
            content.style.height = ROW_HEIGHT + 'px';
            content.style.borderBottom = '1px solid #e5e7eb'; // Línea horizontal gris muy clara
            // Sin estilos de fondo - área limpia para citas
            grid.appendChild(content);
            
            // Guardar referencia al label y índice de fila para acceso rápido
            slotRowIndex.set(minutes, {
                rowIndex: index + 1,
                labelElement: label
            });
        });

        // Crear overlay global para cards expandidas (columna 2 completa)
        // Las cards expandidas se mueven aquí para ocupar todo el ancho sin invadir horarios
        const expandedOverlay = document.createElement('div');
        expandedOverlay.id = 'aa-expanded-cards-overlay';
        expandedOverlay.style.gridColumn = '2';
        expandedOverlay.style.gridRow = '1 / ' + (timeSlots.length + 1);
        expandedOverlay.style.position = 'relative';
        expandedOverlay.style.zIndex = '200';
        expandedOverlay.style.pointerEvents = 'none'; // No bloquea clicks en elementos debajo
        expandedOverlay.style.overflow = 'visible'; // Permite que cards expandidas desborden
        grid.appendChild(expandedOverlay);

        // Agregar indicador de hora actual (SOLO en label, solo si es hoy)
        if (isToday && minutosActuales !== null) {
            agregarIndicadorHoraActual(slotRowIndex, minutosActuales);
        }

        // Retornar referencias para uso externo
        return {
            slotRowIndex: slotRowIndex,
            timeSlots: timeSlots
        };
    }

    /**
     * Agregar indicador visual de hora actual en el label
     */
    function agregarIndicadorHoraActual(slotRowIndex, minutosActuales) {
        // Redondear al slot de 30 minutos correspondiente
        const slotActual = Math.floor(minutosActuales / 30) * 30;
        
        // Obtener el label del slot actual
        const slotData = slotRowIndex.get(slotActual);
        if (!slotData || !slotData.labelElement) return; // Slot no encontrado en el timeline
        
        const label = slotData.labelElement;
        
        // Crear indicador dentro del label
        const indicador = document.createElement('div');
        indicador.className = 'aa-time-now-indicator';
        indicador.style.position = 'absolute';
        indicador.style.left = '0';
        indicador.style.right = '0';
        indicador.style.top = '50%';
        indicador.style.transform = 'translateY(-50%)';
        indicador.style.height = '2px';
        indicador.style.backgroundColor = '#ef4444';
        indicador.style.zIndex = '5';
        
        // Agregar un pequeño círculo en el lado izquierdo
        const circulo = document.createElement('div');
        circulo.style.position = 'absolute';
        circulo.style.left = '0';
        circulo.style.top = '50%';
        circulo.style.transform = 'translate(-50%, -50%)';
        circulo.style.width = '8px';
        circulo.style.height = '8px';
        circulo.style.backgroundColor = '#ef4444';
        circulo.style.borderRadius = '50%';
        indicador.appendChild(circulo);
        
        // Insertar el indicador dentro del label
        label.appendChild(indicador);
    }

    // Exponer API pública
    window.CalendarTimeline = {
        renderTimelineForDate: renderTimelineForDate
    };

})();

