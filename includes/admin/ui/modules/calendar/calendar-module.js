/**
 * Calendar Module - Module-specific JavaScript
 */

(function() {
    'use strict';

    // Variables del módulo para recarga
    let currentSlotRowIndex = null;
    let currentTimeSlots = null;

    // Función para esperar a que las dependencias estén disponibles
    function waitForDependencies(callback, maxAttempts = 50) {
        const hasDateUtils = typeof window.DateUtils !== 'undefined' && 
                            typeof window.DateUtils.getWeekdayName === 'function' &&
                            typeof window.DateUtils.getDayIntervals === 'function';
        
        const hasSchedule = typeof window.AA_CALENDAR_DATA !== 'undefined' && 
                           window.AA_CALENDAR_DATA?.schedule;
        
        const hasCalendarService = typeof window.AdminCalendarService !== 'undefined' &&
                                  typeof window.AdminCalendarService.esCitaProxima === 'function' &&
                                  typeof window.AdminCalendarService.calcularPosicionCita === 'function';
        
        const hasCalendarController = typeof window.AdminCalendarController !== 'undefined' &&
                                     typeof window.AdminCalendarController.handleCitaAction === 'function';
        
        if (hasDateUtils && hasSchedule && hasCalendarService && hasCalendarController) {
            callback();
            return;
        }
        
        if (maxAttempts <= 0) {
            console.error('❌ Dependencias no disponibles después de múltiples intentos');
            console.error('  - DateUtils:', typeof window.DateUtils);
            console.error('  - AA_CALENDAR_DATA:', typeof window.AA_CALENDAR_DATA);
            console.error('  - AdminCalendarService:', typeof window.AdminCalendarService);
            console.error('  - AdminCalendarController:', typeof window.AdminCalendarController);
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
        
        // Obtener hora actual en minutos para diferenciar pasado/futuro
        const now = new Date();
        const minutosActuales = window.DateUtils.minutesFromDate(now);

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
            label.style.position = 'relative';
            
            // Diferenciar visualmente slots pasados vs futuros (SOLO en label)
            if (minutes < minutosActuales) {
                // Slot pasado: fondo gris tenue en el label
                label.style.backgroundColor = '#f3f4f6';
            } else {
                // Slot actual o futuro: fondo normal en el label
                label.style.backgroundColor = '#ffffff';
            }
            
            grid.appendChild(label);
            
            // Content (columna 2) - vacío por ahora, las citas se insertarán aquí
            const content = document.createElement('div');
            content.className = 'aa-time-content';
            content.style.gridColumn = '2';
            content.style.gridRow = `${index + 1}`;
            content.style.minHeight = '40px';
            // Sin estilos de fondo - área limpia para citas
            grid.appendChild(content);
            
            // Guardar referencia al label y índice de fila para acceso rápido
            slotRowIndex.set(minutes, {
                rowIndex: index + 1,
                labelElement: label
            });
        });

        // Agregar indicador de hora actual (SOLO en label)
        agregarIndicadorHoraActual(slotRowIndex, minutosActuales);

        // Guardar referencias para recarga
        currentSlotRowIndex = slotRowIndex;
        currentTimeSlots = timeSlots;

        // Inicializar el controller con callback de recarga
        if (window.AdminCalendarController?.init) {
            window.AdminCalendarController.init(recargarTimelineDelDiaActual);
        }
        
        // Cargar y renderizar citas del día actual
        cargarYRenderizarCitas(slotRowIndex, timeSlots);
        
        // Configurar event listener delegado para acciones de botones
        configurarEventListeners();
    }

    /**
     * Cargar citas del día actual y renderizarlas en el timeline
     */
    function cargarYRenderizarCitas(slotRowIndex, timeSlots) {
        const today = new Date();
        const todayStr = window.DateUtils.ymd(today);
        
        // Preparar datos para la petición AJAX
        const formData = new FormData();
        formData.append('action', 'aa_get_citas_por_dia');
        formData.append('fecha', todayStr);
        
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
        
        // Determinar si la cita es próxima o pasada usando el service
        const esProxima = window.AdminCalendarService?.esCitaProxima(cita) || false;
        
        // Renderizar botones/leyendas según reglas
        const botones = renderizarBotonesYCitas(cita, esProxima);
        if (botones) {
            body.appendChild(botones);
        }
        
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
     * Renderizar botones y leyendas según estado y si es próxima/pasada
     * @param {Object} cita - Objeto de cita
     * @param {boolean} esProxima - true si es próxima, false si es pasada
     * @returns {HTMLElement|null} - Contenedor de botones/leyendas o null
     */
    function renderizarBotonesYCitas(cita, esProxima) {
        const estado = cita.estado || 'pending';
        const contenedor = document.createElement('div');
        contenedor.style.display = 'flex';
        contenedor.style.gap = '8px';
        contenedor.style.flexWrap = 'wrap';
        
        let tieneContenido = false;
        
        if (esProxima) {
            // ===== CITA PRÓXIMA =====
            
            if (estado === 'confirmed') {
                // Mostrar botón Cancelar
                const btnCancelar = crearBoton('Cancelar', 'cancelar', '#ef4444', cita.id);
                contenedor.appendChild(btnCancelar);
                tieneContenido = true;
            }
            else if (estado === 'pending') {
                // Mostrar botones Confirmar y Cancelar
                const btnConfirmar = crearBoton('Confirmar', 'confirmar', '#10b981', cita.id);
                const btnCancelar = crearBoton('Cancelar', 'cancelar', '#ef4444', cita.id);
                contenedor.appendChild(btnConfirmar);
                contenedor.appendChild(btnCancelar);
                tieneContenido = true;
            }
            else if (estado === 'cancelled') {
                // Mostrar leyenda "Cancelada"
                const leyenda = crearLeyenda('Cancelada', '#9ca3af');
                contenedor.appendChild(leyenda);
                tieneContenido = true;
            }
            else if (estado === 'asistió' || estado === 'no asistió') {
                // Caso extremo: mostrar leyenda correspondiente
                const texto = estado === 'asistió' ? 'Asistió' : 'No asistió';
                const leyenda = crearLeyenda(texto, '#6b7280');
                contenedor.appendChild(leyenda);
                tieneContenido = true;
            }
        } else {
            // ===== CITA PASADA =====
            
            if (estado === 'confirmed') {
                // Mostrar botones Asistió y No asistió
                const btnAsistio = crearBoton('Asistió', 'asistio', '#10b981', cita.id);
                const btnNoAsistio = crearBoton('No asistió', 'no-asistio', '#ef4444', cita.id);
                contenedor.appendChild(btnAsistio);
                contenedor.appendChild(btnNoAsistio);
                tieneContenido = true;
            }
            else if (estado === 'pending') {
                // NO mostrar botones ni leyendas
                // No hacer nada
            }
            else if (estado === 'asistió') {
                // Mostrar leyenda "✓ Asistió"
                const leyenda = crearLeyenda('✓ Asistió', '#10b981');
                contenedor.appendChild(leyenda);
                tieneContenido = true;
            }
            else if (estado === 'no asistió') {
                // Mostrar leyenda "✕ No asistió"
                const leyenda = crearLeyenda('✕ No asistió', '#ef4444');
                contenedor.appendChild(leyenda);
                tieneContenido = true;
            }
            else if (estado === 'cancelled') {
                // Mostrar leyenda "Cancelada"
                const leyenda = crearLeyenda('Cancelada', '#9ca3af');
                contenedor.appendChild(leyenda);
                tieneContenido = true;
            }
        }
        
        return tieneContenido ? contenedor : null;
    }

    /**
     * Crear un botón con data-action
     * @param {string} texto - Texto del botón
     * @param {string} accion - Acción (confirmar, cancelar, asistio, no-asistio)
     * @param {string} colorFondo - Color de fondo
     * @param {string|number} citaId - ID de la cita
     * @returns {HTMLElement} - Elemento button
     */
    function crearBoton(texto, accion, colorFondo, citaId) {
        const boton = document.createElement('button');
        boton.textContent = texto;
        boton.setAttribute('data-action', accion);
        boton.setAttribute('data-id', citaId);
        boton.style.padding = '6px 12px';
        boton.style.backgroundColor = colorFondo;
        boton.style.color = '#fff';
        boton.style.border = 'none';
        boton.style.borderRadius = '4px';
        boton.style.cursor = 'pointer';
        boton.style.fontSize = '12px';
        
        return boton;
    }

    /**
     * Crear una leyenda (texto informativo)
     * @param {string} texto - Texto de la leyenda
     * @param {string} color - Color del texto
     * @returns {HTMLElement} - Elemento div
     */
    function crearLeyenda(texto, color) {
        const leyenda = document.createElement('div');
        leyenda.textContent = texto;
        leyenda.style.padding = '6px 12px';
        leyenda.style.color = color;
        leyenda.style.fontWeight = '500';
        leyenda.style.fontSize = '12px';
        leyenda.style.border = `1px solid ${color}`;
        leyenda.style.borderRadius = '4px';
        
        // Color de fondo con transparencia según el color base
        if (color === '#10b981') {
            leyenda.style.backgroundColor = '#d1fae5'; // verde claro
        } else if (color === '#ef4444') {
            leyenda.style.backgroundColor = '#fee2e2'; // rojo claro
        } else {
            leyenda.style.backgroundColor = '#f3f4f6'; // gris claro
        }
        
        return leyenda;
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

    /**
     * Obtener color según el estado de la cita
     */
    function getEstadoColor(estado) {
        const colores = {
            'pending': '#f59e0b',      // amarillo
            'confirmed': '#10b981',    // verde
            'cancelled': '#ef4444',     // rojo
            'attended': '#10b981',      // verde (mismo que confirmed)
            'asistió': '#10b981',       // verde
            'no asistió': '#9ca3af'     // gris
        };
        return colores[estado] || colores['pending'];
    }

    /**
     * Configurar event listeners delegados para acciones de botones
     */
    function configurarEventListeners() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        grid.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            
            const action = btn.getAttribute('data-action');
            const citaId = btn.getAttribute('data-id');
            
            if (!action || !citaId) return;
            
            e.stopPropagation();
            
            // Delegar al controller
            if (window.AdminCalendarController?.handleCitaAction) {
                window.AdminCalendarController.handleCitaAction(action, citaId);
            }
        });
    }

    /**
     * Recargar el timeline del día actual sin recargar la página
     */
    function recargarTimelineDelDiaActual() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid || !currentSlotRowIndex || !currentTimeSlots) return;
        
        // Eliminar todas las cards de citas existentes
        const cards = grid.querySelectorAll('.aa-appointment-card');
        cards.forEach(card => card.remove());
        
        // Recargar citas usando las referencias guardadas
        cargarYRenderizarCitas(currentSlotRowIndex, currentTimeSlots);
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