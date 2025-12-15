/**
 * Calendar Module - Module-specific JavaScript
 */

(function() {
    'use strict';

    // Función para esperar a que las dependencias estén disponibles
    function waitForDependencies(callback, maxAttempts = 50) {
        const hasDateUtils = typeof window.DateUtils !== 'undefined' && 
                            typeof window.DateUtils.getWeekdayName === 'function' &&
                            typeof window.DateUtils.getDayIntervals === 'function';
        
        const hasSchedule = typeof window.AA_CALENDAR_DATA !== 'undefined' && 
                           window.AA_CALENDAR_DATA?.schedule;
        
        if (hasDateUtils && hasSchedule) {
            callback();
            return;
        }
        
        if (maxAttempts <= 0) {
            console.error('❌ Dependencias no disponibles después de múltiples intentos');
            console.error('  - DateUtils:', typeof window.DateUtils);
            console.error('  - AA_CALENDAR_DATA:', typeof window.AA_CALENDAR_DATA);
            return;
        }
        
        setTimeout(() => waitForDependencies(callback, maxAttempts - 1), 100);
    }

    function initCalendar() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) {
            console.error('❌ No se encontró el contenedor #aa-time-grid');
            return;
        }

        const schedule = window.AA_CALENDAR_DATA.schedule;
        
        // Obtener día actual y sus intervalos
        const today = new Date();
        const weekday = window.DateUtils.getWeekdayName(today);
        const intervals = window.DateUtils.getDayIntervals(schedule, weekday);

        // Limpiar contenido existente
        grid.innerHTML = '';

        // Si no hay intervalos o el día no está habilitado
        if (!intervals || intervals.length === 0) {
            grid.innerHTML = '<div class="aa-time-row"><div class="aa-time-content" style="padding: 2rem; text-align: center; color: #6b7280;">No hay horarios configurados para hoy</div></div>';
            return;
        }

        // Función para convertir minutos a formato HH:MM
        function minutesToTimeStr(minutes) {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        }

        // Generar todos los bloques de 30 minutos de todos los intervalos
        const timeSlots = [];
        
        intervals.forEach((interval) => {
            // Generar bloques de 30 minutos dentro del intervalo
            for (let min = interval.start; min < interval.end; min += 30) {
                timeSlots.push(min);
            }
        });

        // Renderizar cada bloque como una fila
        timeSlots.forEach(minutes => {
            const timeStr = minutesToTimeStr(minutes);
            const row = document.createElement('div');
            row.className = 'aa-time-row';
            
            const label = document.createElement('div');
            label.className = 'aa-time-label';
            label.textContent = timeStr;
            
            const content = document.createElement('div');
            content.className = 'aa-time-content';
            
            row.appendChild(label);
            row.appendChild(content);
            grid.appendChild(row);
        });
    }

    // Esperar a que el DOM esté listo Y las dependencias estén disponibles
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            waitForDependencies(initCalendar);
        });
    } else {
        waitForDependencies(initCalendar);
    }

})();