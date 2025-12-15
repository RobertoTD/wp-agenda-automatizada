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
        
        // Configurar CSS Grid
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'auto 1fr';
        grid.style.gridAutoRows = 'auto';

        // Limpiar contenido existente
        grid.innerHTML = '';

        // Si no hay intervalos o el día no está habilitado
        if (!intervals || intervals.length === 0) {
            const mensaje = document.createElement('div');
            mensaje.style.gridColumn = '1 / -1';
            mensaje.style.padding = '2rem';
            mensaje.style.textAlign = 'center';
            mensaje.style.color = '#6b7280';
            mensaje.textContent = 'No hay horarios configurados para hoy';
            grid.appendChild(mensaje);
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

        // Mapa de minutos -> índice de fila en el grid (para calcular grid-row)
        const slotRowIndex = new Map();

        // Renderizar labels y content directamente en el grid
        timeSlots.forEach((minutes, index) => {
            const timeStr = minutesToTimeStr(minutes);
            
            // Label (columna 1)
            const label = document.createElement('div');
            label.className = 'aa-time-label';
            label.textContent = timeStr;
            label.style.gridColumn = '1';
            label.style.gridRow = `${index + 1}`;
            label.style.padding = '8px 12px';
            label.style.minWidth = '40px';
            grid.appendChild(label);
            
            // Content (columna 2) - vacío por ahora, las citas se insertarán aquí
            const content = document.createElement('div');
            content.className = 'aa-time-content';
            content.style.gridColumn = '2';
            content.style.gridRow = `${index + 1}`;
            content.style.minHeight = '40px';
            grid.appendChild(content);
            
            // Guardar índice de fila para acceso rápido
            slotRowIndex.set(minutes, index + 1);
        });

        // Cargar y renderizar citas del día actual
        cargarYRenderizarCitas(slotRowIndex, timeSlots);
    }

    /**
     * Cargar citas del día actual y renderizarlas en el timeline
     */
    function cargarYRenderizarCitas(slotRowIndex, timeSlots) {
        const today = new Date();
        const todayStr = window.DateUtils.ymd(today);
        
        // Preparar datos para la petición AJAX
        const formData = new FormData();
        formData.append('action', 'aa_get_proximas_citas');
        formData.append('buscar', '');
        formData.append('ordenar', 'fecha_asc');
        formData.append('pagina', '1');
        
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
                // Filtrar solo las citas del día actual
                const citasHoy = data.data.citas.filter(cita => {
                    if (!cita.fecha) return false;
                    const fechaCita = new Date(cita.fecha);
                    const fechaCitaStr = window.DateUtils.ymd(fechaCita);
                    return fechaCitaStr === todayStr;
                });
                
                // Renderizar cada cita en su slot correspondiente
                citasHoy.forEach(cita => {
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
        
        // Convertir fecha de inicio a minutos desde medianoche
        const fechaInicio = new Date(cita.fecha);
        const minutosInicio = window.DateUtils.minutesFromDate(fechaInicio);
        
        // Calcular minutos de fin usando fecha_fin o duracion
        let minutosFin;
        if (cita.fecha_fin) {
            const fechaFin = new Date(cita.fecha_fin);
            minutosFin = window.DateUtils.minutesFromDate(fechaFin);
        } else if (cita.duracion) {
            minutosFin = minutosInicio + parseInt(cita.duracion);
        } else {
            // Fallback: 60 minutos por defecto
            minutosFin = minutosInicio + 60;
        }
        
        // Encontrar el slot inicial (redondear hacia abajo al slot de 30 min más cercano)
        const slotInicio = Math.floor(minutosInicio / 30) * 30;
        
        // Calcular cuántos bloques de 30 min ocupa
        const duracionMinutos = minutosFin - minutosInicio;
        const bloquesOcupados = Math.ceil(duracionMinutos / 30);
        
        // Obtener el índice de fila del slot inicial
        const startRow = slotRowIndex.get(slotInicio);
        if (!startRow) return; // Slot no encontrado
        
        // Obtener el grid
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Crear la card de la cita
        const card = crearCardCita(cita);
        
        // Configurar posición en el grid
        card.style.gridColumn = '2';
        card.style.gridRow = `${startRow} / span ${bloquesOcupados}`;
        
        // Insertar la card directamente en el grid
        grid.appendChild(card);
    }

    /**
     * Crear el DOM de la card de cita
     */
    function crearCardCita(cita) {
        const card = document.createElement('div');
        card.className = 'aa-appointment-card';
        card.setAttribute('data-id', cita.id || '');
        
        // Estilos base sin altura fija
        card.style.border = '1px solid #e5e7eb';
        card.style.borderRadius = '4px';
        card.style.overflow = 'hidden';
        card.style.cursor = 'pointer';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.minHeight = '40px';
        
        // Header (siempre visible)
        const header = document.createElement('div');
        header.className = 'aa-appointment-header';
        header.style.padding = '8px 12px';
        header.style.backgroundColor = getEstadoColor(cita.estado);
        header.style.color = '#fff';
        header.style.fontWeight = '500';
        header.style.fontSize = '14px';
        header.style.flexShrink = '0';
        
        const clienteNombre = cita.nombre || 'Sin nombre';
        const servicio = cita.servicio || 'Sin servicio';
        header.textContent = `${clienteNombre} - ${servicio}`;
        
        // Body (oculto por defecto)
        const body = document.createElement('div');
        body.className = 'aa-appointment-body';
        body.setAttribute('hidden', '');
        body.style.padding = '12px';
        body.style.backgroundColor = '#f9fafb';
        body.style.fontSize = '13px';
        body.style.flex = '1';
        
        // Información de la cita
        const info = document.createElement('div');
        info.style.marginBottom = '12px';
        
        if (cita.telefono) {
            const telefono = document.createElement('div');
            telefono.textContent = `📞 ${cita.telefono}`;
            telefono.style.marginBottom = '4px';
            info.appendChild(telefono);
        }
        
        if (cita.correo) {
            const correo = document.createElement('div');
            correo.textContent = `✉️ ${cita.correo}`;
            correo.style.marginBottom = '4px';
            info.appendChild(correo);
        }
        
        const estado = document.createElement('div');
        estado.textContent = `Estado: ${cita.estado || 'pending'}`;
        estado.style.marginBottom = '8px';
        estado.style.fontWeight = '500';
        info.appendChild(estado);
        
        body.appendChild(info);
        
        // Botones de acción
        const botones = document.createElement('div');
        botones.style.display = 'flex';
        botones.style.gap = '8px';
        
        // Botón Confirmar (solo si está pending)
        if (cita.estado === 'pending' || !cita.estado) {
            const btnConfirmar = document.createElement('button');
            btnConfirmar.textContent = 'Confirmar';
            btnConfirmar.style.padding = '6px 12px';
            btnConfirmar.style.backgroundColor = '#10b981';
            btnConfirmar.style.color = '#fff';
            btnConfirmar.style.border = 'none';
            btnConfirmar.style.borderRadius = '4px';
            btnConfirmar.style.cursor = 'pointer';
            btnConfirmar.style.fontSize = '12px';
            btnConfirmar.addEventListener('click', function(e) {
                e.stopPropagation();
                if (window.AdminConfirmController && window.AdminConfirmController.onConfirmar) {
                    window.AdminConfirmController.onConfirmar(cita.id);
                    // Recargar citas después de confirmar
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            });
            botones.appendChild(btnConfirmar);
        }
        
        // Botón Cancelar (si está pending o confirmed)
        if (cita.estado === 'pending' || cita.estado === 'confirmed' || !cita.estado) {
            const btnCancelar = document.createElement('button');
            btnCancelar.textContent = 'Cancelar';
            btnCancelar.style.padding = '6px 12px';
            btnCancelar.style.backgroundColor = '#ef4444';
            btnCancelar.style.color = '#fff';
            btnCancelar.style.border = 'none';
            btnCancelar.style.borderRadius = '4px';
            btnCancelar.style.cursor = 'pointer';
            btnCancelar.style.fontSize = '12px';
            btnCancelar.addEventListener('click', function(e) {
                e.stopPropagation();
                if (window.AdminConfirmController && window.AdminConfirmController.onCancelar) {
                    if (confirm('¿Estás seguro de cancelar esta cita?')) {
                        window.AdminConfirmController.onCancelar(cita.id);
                        // Recargar citas después de cancelar
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                }
            });
            botones.appendChild(btnCancelar);
        }
        
        body.appendChild(botones);
        
        // Toggle acordeón: click en header abre/cierra body
        header.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = body.hasAttribute('hidden');
            if (isHidden) {
                body.removeAttribute('hidden');
            } else {
                body.setAttribute('hidden', '');
            }
        });
        
        // Ensamblar card
        card.appendChild(header);
        card.appendChild(body);
        
        return card;
    }

    /**
     * Obtener color según el estado de la cita
     */
    function getEstadoColor(estado) {
        const colores = {
            'pending': '#f59e0b',    // amarillo
            'confirmed': '#10b981',  // verde
            'cancelled': '#ef4444',   // rojo
            'attended': '#3b82f6'     // azul
        };
        return colores[estado] || colores['pending'];
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