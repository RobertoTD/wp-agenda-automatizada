/**
 * Calendar Timeline - Timeline rendering and time slots
 * 
 * Design System: See /docs/DESIGN_BRIEF.md
 * - Estilos consistentes con el sistema de diseño
 * - Grid limpio y profesional
 */

(function() {
    'use strict';

    // =============================================
    // DESIGN TOKENS
    // =============================================
    const TOKENS = {
        gray50: '#f9fafb',
        gray100: '#f3f4f6',
        gray200: '#e5e7eb',
        gray400: '#9ca3af',
        gray500: '#6b7280',
        
        radiusMd: '6px',
        
        space2: '6px',
        space3: '8px',
        
        textSm: '12px'
    };

    // Altura fija de cada fila del grid
    const ROW_HEIGHT = 40;

    /**
     * Renderizar timeline para una fecha específica
     */
    function renderTimelineForDate(fechaStr, options) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) {
            console.error('❌ No se encontró el contenedor #aa-time-grid');
            return null;
        }

        const schedule = window.AA_CALENDAR_DATA.schedule;
        
        const fecha = new Date(fechaStr + 'T00:00:00');
        const weekday = window.DateUtils.getWeekdayName(fecha);
        
        const scheduleIntervals = window.DateUtils.getDayIntervals(schedule, weekday) || [];
        const assignmentIntervals = (options && options.assignmentIntervals) ? options.assignmentIntervals : [];
        const allIntervals = [...scheduleIntervals, ...assignmentIntervals];
        
        // =============================================
        // GRID CONTAINER STYLES
        // =============================================
        Object.assign(grid.style, {
            display: 'grid',
            gridTemplateColumns: 'auto 1fr',
            gridAutoRows: ROW_HEIGHT + 'px',
            borderRadius: '0',
            borderTop: `15px solid ${TOKENS.gray100}`, // Header bar
            paddingTop: '2px',
            backgroundColor: '#ffffff'
        });

        grid.innerHTML = '';

        if (!allIntervals || allIntervals.length === 0) {
            const mensaje = document.createElement('div');
            Object.assign(mensaje.style, {
                gridColumn: '1 / -1',
                padding: '2rem',
                textAlign: 'center',
                color: TOKENS.gray500
            });
            mensaje.textContent = 'No hay horarios configurados para hoy';
            grid.appendChild(mensaje);
            return null;
        }

        function minutesToTimeStr(minutes) {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        }

        const minStart = Math.min(...allIntervals.map(iv => iv.start));
        const maxEnd = Math.max(...allIntervals.map(iv => iv.end));

        const timeSlots = [];
        for (let min = minStart; min < maxEnd; min += 30) {
            timeSlots.push(min);
        }

        const slotRowIndex = new Map();
        
        const now = new Date();
        const todayStr = window.DateUtils.ymd(now);
        const isToday = fechaStr === todayStr;
        const minutosActuales = isToday ? window.DateUtils.minutesFromDate(now) : null;

        // =============================================
        // RENDER TIME SLOTS
        // =============================================
        timeSlots.forEach((minutes, index) => {
            const timeStr = minutesToTimeStr(minutes);
            const isPast = isToday && minutosActuales !== null && minutes < minutosActuales;
            
            // Label (columna 1)
            const label = document.createElement('div');
            label.className = 'aa-time-label';
            Object.assign(label.style, {
                gridColumn: '1',
                gridRow: `${index + 1}`,
                padding: '0px 8px 10px 8px',
                minWidth: '45px',
                position: 'relative',
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'flex-end',
                backgroundColor: isPast ? TOKENS.gray50 : '#ffffff',
                borderRight: `1px solid ${TOKENS.gray100}`
            });
            
            const textSpan = document.createElement('span');
            textSpan.textContent = timeStr;
            Object.assign(textSpan.style, {
                color: isPast ? TOKENS.gray400 : TOKENS.gray500,
                fontSize: TOKENS.textSm,
                fontWeight: '400',
                fontVariantNumeric: 'tabular-nums'
            });
            
            label.appendChild(textSpan);
            grid.appendChild(label);
            
            // Ajustar posición vertical del texto
            const computedStyle = window.getComputedStyle(textSpan);
            const currentFontSize = parseFloat(computedStyle.fontSize);
            if (!isNaN(currentFontSize)) {
                const textHeight = currentFontSize;
                if (index === 0) {
                    textSpan.style.transform = 'translateY(-3px)';
                } else {
                    textSpan.style.transform = `translateY(-${textHeight / 2}px)`;
                }
                textSpan.style.display = 'inline-block';
            }
            
            // Content (columna 2)
            const content = document.createElement('div');
            content.className = 'aa-time-content';
            Object.assign(content.style, {
                gridColumn: '2',
                gridRow: `${index + 1}`,
                minHeight: ROW_HEIGHT + 'px',
                maxHeight: ROW_HEIGHT + 'px',
                height: ROW_HEIGHT + 'px',
                borderBottom: `1px solid ${TOKENS.gray100}`,
                backgroundColor: isPast ? TOKENS.gray50 : 'transparent'
            });
            
            grid.appendChild(content);
            
            slotRowIndex.set(minutes, {
                rowIndex: index + 1,
                labelElement: label
            });
        });

        // =============================================
        // EXPANDED CARDS OVERLAY
        // =============================================
        const expandedOverlay = document.createElement('div');
        expandedOverlay.id = 'aa-expanded-cards-overlay';
        Object.assign(expandedOverlay.style, {
            gridColumn: '2',
            gridRow: '1 / ' + (timeSlots.length + 1),
            position: 'relative',
            zIndex: '200',
            pointerEvents: 'none',
            overflow: 'visible'
        });
        grid.appendChild(expandedOverlay);
        
        // DEBUG: Log overlay creation if debug flag is active
        if (window.AA_DEBUG_CALENDAR_OVERFLOW) {
            console.log('[Timeline] overlay created', expandedOverlay, {
                gridRow: expandedOverlay.style.gridRow,
                timeSlots: timeSlots.length,
                overflow: expandedOverlay.style.overflow
            });
        }

        // =============================================
        // CURRENT TIME INDICATOR
        // =============================================
        if (isToday && minutosActuales !== null) {
            agregarIndicadorHoraActual(slotRowIndex, minutosActuales);
        }

        return {
            slotRowIndex: slotRowIndex,
            timeSlots: timeSlots
        };
    }

    /**
     * Agregar indicador visual de hora actual
     */
    function agregarIndicadorHoraActual(slotRowIndex, minutosActuales) {
        const slotActual = Math.floor(minutosActuales / 30) * 30;
        const slotData = slotRowIndex.get(slotActual);
        if (!slotData || !slotData.labelElement) return;
        
        const label = slotData.labelElement;
        
        const indicador = document.createElement('div');
        indicador.className = 'aa-time-now-indicator';
        Object.assign(indicador.style, {
            position: 'absolute',
            left: '0',
            right: '0',
            top: '50%',
            transform: 'translateY(-50%)',
            height: '2px',
            backgroundColor: '#ef4444',
            zIndex: '5'
        });
        
        const circulo = document.createElement('div');
        Object.assign(circulo.style, {
            position: 'absolute',
            left: '0',
            top: '50%',
            transform: 'translate(-50%, -50%)',
            width: '8px',
            height: '8px',
            backgroundColor: '#ef4444',
            borderRadius: '50%',
            boxShadow: '0 0 0 2px rgba(239, 68, 68, 0.2)'
        });
        indicador.appendChild(circulo);
        
        label.appendChild(indicador);
    }

    // Exponer API pública
    window.CalendarTimeline = {
        renderTimelineForDate: renderTimelineForDate
    };

})();
