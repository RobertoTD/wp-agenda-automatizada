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
        const slotRows = new Map(); // Mapa de minutos -> elemento row para acceso rápido
        
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
            
            // Guardar referencia para acceso rápido
            slotRows.set(minutes, row);
        });

        // Cargar y renderizar citas del día actual
        cargarYRenderizarCitas(slotRows, timeSlots);
    }

    /**
     * Cargar citas del día actual y renderizarlas en el timeline
     */
    function cargarYRenderizarCitas(slotRows, timeSlots) {
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
                    renderizarCitaEnTimeline(cita, slotRows, timeSlots);
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
    function renderizarCitaEnTimeline(cita, slotRows, timeSlots) {
        if (!cita.fecha) return;
        
        // Convertir fecha de inicio a minutos desde medianoche
        const fechaInicio = new Date(cita.fecha);
        const minutosInicio = window.DateUtils.minutesFromDate(fechaInicio);
        
        // Calcular minutos de fin
        let minutosFin;
        if (cita.fecha_fin) {
            const fechaFin = new Date(cita.fecha_fin);
            minutosFin = window.DateUtils.minutesFromDate(fechaFin);
        } else {
            // Si no hay fecha_fin, usar slot_duration (asumiendo 60 min por defecto)
            const slotDuration = 60; // minutos
            minutosFin = minutosInicio + slotDuration;
        }
        
        // Encontrar el slot inicial más cercano (redondear hacia abajo al slot de 30 min más cercano)
        const slotInicio = Math.floor(minutosInicio / 30) * 30;
        
        // Calcular cuántos bloques de 30 min ocupa
        const duracionMinutos = minutosFin - minutosInicio;
        const bloquesOcupados = Math.ceil(duracionMinutos / 30);
        
        // Buscar el slot inicial en el mapa
        const slotRow = slotRows.get(slotInicio);
        if (!slotRow) return; // Slot no encontrado
        
        const contentDiv = slotRow.querySelector('.aa-time-content');
        if (!contentDiv) return;
        
        // Crear la card de la cita
        const card = crearCardCita(cita, bloquesOcupados);
        
        // Insertar la card en el slot inicial
        contentDiv.appendChild(card);
    }

    /**
     * Crear el DOM de la card de cita
     */
    function crearCardCita(cita, bloquesOcupados) {
        const card = document.createElement('div');
        card.className = 'aa-appointment-card';
        card.setAttribute('data-id', cita.id || '');
        
        // Calcular altura aproximada (cada bloque de 30 min = ~60px, ajustar según necesidad)
        const alturaPorBloque = 60;
        const alturaTotal = bloquesOcupados * alturaPorBloque;
        card.style.height = `${alturaTotal}px`;
        card.style.position = 'relative';
        card.style.marginBottom = '4px';
        card.style.border = '1px solid #e5e7eb';
        card.style.borderRadius = '4px';
        card.style.overflow = 'hidden';
        card.style.cursor = 'pointer';
        
        // Header (siempre visible)
        const header = document.createElement('div');
        header.className = 'aa-appointment-header';
        header.style.padding = '8px 12px';
        header.style.backgroundColor = getEstadoColor(cita.estado);
        header.style.color = '#fff';
        header.style.fontWeight = '500';
        header.style.fontSize = '14px';
        
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