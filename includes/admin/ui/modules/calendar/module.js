/**
 * Calendar Module - Agenda Timeline Implementation
 * 
 * Responsable de:
 * - Construir slots horarios desde aa_schedule
 * - Obtener citas del día actual
 * - Renderizar timeline vertical con filas de tiempo
 * - Renderizar tarjetas de citas colapsables
 * - Reutilizar funciones de confirmar/cancelar/crear cliente
 */

(function() {
    'use strict';

    // Variables globales
    const vars = window.aaCalendarVars || {};
    const ajaxurl = vars.ajaxurl || '';
    const schedule = vars.schedule || {};
    const slotDuration = vars.slot_duration || 60;
    const nonce = vars.nonce || '';
    const nonceConfirmar = vars.nonce_confirmar || '';
    const nonceCancelar = vars.nonce_cancelar || '';
    const nonceCrearCliente = vars.nonce_crear_cliente_desde_cita || '';

    // Elementos del DOM
    let timeGrid = null;
    let timeNowIndicator = null;
    let currentDate = null;
    let appointments = [];

    /**
     * Inicializar módulo
     */
    function init() {
        console.log('📅 Calendar module initialized');
        
        timeGrid = document.getElementById('aa-time-grid');
        timeNowIndicator = document.getElementById('aa-time-now-indicator');
        
        if (!timeGrid) {
            console.error('❌ Time grid container not found');
            return;
        }

        // Establecer fecha actual
        currentDate = new Date();
        
        // Cargar y renderizar timeline
        loadDayAppointments();
    }

    /**
     * Cargar citas del día actual
     */
    function loadDayAppointments() {
        const fechaStr = formatDateForAPI(currentDate);
        
        const formData = new FormData();
        formData.append('action', 'aa_get_citas_dia');
        formData.append('fecha', fechaStr);
        formData.append('_wpnonce', nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appointments = data.data.citas || [];
                renderTimeline();
            } else {
                console.error('Error loading appointments:', data.data?.message);
                showError('No se pudieron cargar las citas del día.');
            }
        })
        .catch(err => {
            console.error('Error fetching appointments:', err);
            showError('Error de conexión al cargar las citas.');
        });
    }

    /**
     * Renderizar timeline completo
     */
    function renderTimeline() {
        if (!timeGrid) return;

        // Limpiar grid
        timeGrid.innerHTML = '';

        // Obtener slots del día
        const slots = buildDaySlots();
        
        if (slots.length === 0) {
            timeGrid.innerHTML = '<div class="p-4 text-center text-gray-500">No hay horarios configurados para este día.</div>';
            return;
        }

        // Renderizar filas de tiempo
        slots.forEach(slot => {
            const row = createTimeRow(slot);
            timeGrid.appendChild(row);
        });

        // Renderizar citas en los slots correspondientes
        renderAppointments();

        // Actualizar indicador de hora actual
        updateTimeIndicator();
    }

    /**
     * Construir slots horarios del día desde el schedule
     */
    function buildDaySlots() {
        const weekday = getWeekdayName(currentDate);
        const daySchedule = schedule[weekday];

        if (!daySchedule || !daySchedule.enabled) {
            return [];
        }

        const intervals = daySchedule.intervals || [];
        if (!Array.isArray(intervals)) {
            return [];
        }

        const slots = [];
        const now = new Date();
        const isToday = isSameDay(currentDate, now);

        intervals.forEach(interval => {
            const startTime = parseTime(interval.start);
            const endTime = parseTime(interval.end);

            if (!startTime || !endTime) return;

            // Crear slots cada 30 minutos dentro del intervalo
            let currentSlot = new Date(currentDate);
            currentSlot.setHours(startTime.hours, startTime.minutes, 0, 0);

            const endSlot = new Date(currentDate);
            endSlot.setHours(endTime.hours, endTime.minutes, 0, 0);

            while (currentSlot < endSlot) {
                // Si es hoy, solo incluir slots futuros (o actual)
                if (isToday && currentSlot < now) {
                    currentSlot = new Date(currentSlot.getTime() + 30 * 60000);
                    continue;
                }

                slots.push(new Date(currentSlot));
                currentSlot = new Date(currentSlot.getTime() + 30 * 60000);
            }
        });

        return slots;
    }

    /**
     * Crear fila de tiempo
     */
    function createTimeRow(slot) {
        const row = document.createElement('div');
        row.className = 'aa-time-row';
        
        const now = new Date();
        const isPast = slot < now && !isSameDay(slot, now);
        const isToday = isSameDay(slot, now);
        
        if (isPast || (isToday && slot < now)) {
            row.classList.add('aa-past');
        } else {
            row.classList.add('aa-future');
        }

        // Label de hora
        const label = document.createElement('div');
        label.className = 'aa-time-label';
        label.textContent = formatTime(slot);
        row.appendChild(label);

        // Contenedor de contenido
        const content = document.createElement('div');
        content.className = 'aa-time-content';
        content.dataset.slotTime = slot.toISOString();
        row.appendChild(content);

        return row;
    }

    /**
     * Renderizar citas en los slots
     */
    function renderAppointments() {
        if (!appointments || appointments.length === 0) return;

        appointments.forEach(cita => {
            if (cita.estado === 'cancelled') return; // No mostrar canceladas

            const citaStart = new Date(cita.fecha.replace(' ', 'T'));
            const slotContent = findNearestSlotContent(citaStart);
            
            if (!slotContent) {
                console.warn('No se encontró slot para cita:', cita.id, citaStart);
                return;
            }

            const card = createAppointmentCard(cita);
            slotContent.appendChild(card);
        });
    }

    /**
     * Crear tarjeta de cita
     */
    function createAppointmentCard(cita) {
        const card = document.createElement('article');
        card.className = 'aa-appointment-card';
        card.dataset.citaId = cita.id;
        card.dataset.citaEstado = cita.estado;

        // Calcular span (duración en slots de 30 min)
        const citaStart = new Date(cita.fecha.replace(' ', 'T'));
        const citaEnd = new Date(cita.fecha_fin || citaStart.getTime() + slotDuration * 60000);
        const durationMinutes = (citaEnd - citaStart) / 60000;
        const span = Math.ceil(durationMinutes / 30);
        
        card.classList.add(`aa-span-${span}`);
        card.classList.add(`aa-status-${cita.estado}`);

        // Contenido colapsado (siempre visible)
        const collapsedContent = document.createElement('div');
        collapsedContent.className = 'aa-card-collapsed';
        
        const clienteName = document.createElement('div');
        clienteName.className = 'aa-card-cliente';
        clienteName.textContent = escapeHtml(cita.nombre || 'Sin nombre');
        collapsedContent.appendChild(clienteName);

        const servicioName = document.createElement('div');
        servicioName.className = 'aa-card-servicio';
        servicioName.textContent = escapeHtml(cita.servicio || 'Sin servicio');
        collapsedContent.appendChild(servicioName);

        card.appendChild(collapsedContent);

        // Contenido expandido (oculto por defecto)
        const expandedContent = document.createElement('div');
        expandedContent.className = 'aa-card-expanded';
        expandedContent.style.display = 'none';

        // Teléfono
        if (cita.telefono) {
            const telefono = document.createElement('div');
            telefono.className = 'aa-card-telefono';
            telefono.textContent = '📞 ' + escapeHtml(cita.telefono);
            expandedContent.appendChild(telefono);
        }

        // Estado
        const estado = document.createElement('div');
        estado.className = `aa-card-estado aa-estado-${cita.estado}`;
        estado.textContent = formatEstado(cita.estado);
        expandedContent.appendChild(estado);

        // Acciones
        const acciones = document.createElement('div');
        acciones.className = 'aa-card-acciones';

        // Botón confirmar (solo si está pendiente)
        if (cita.estado === 'pending') {
            const btnConfirmar = document.createElement('button');
            btnConfirmar.className = 'aa-btn-confirmar';
            btnConfirmar.textContent = '✓ Confirmar';
            btnConfirmar.dataset.id = cita.id;
            btnConfirmar.dataset.nombre = escapeHtml(cita.nombre);
            btnConfirmar.dataset.correo = escapeHtml(cita.correo);
            btnConfirmar.addEventListener('click', () => handleConfirmar(cita.id, cita.nombre, cita.correo));
            acciones.appendChild(btnConfirmar);
        }

        // Botón cancelar (si está confirmada o pendiente)
        if (cita.estado === 'confirmed' || cita.estado === 'pending') {
            const btnCancelar = document.createElement('button');
            btnCancelar.className = 'aa-btn-cancelar';
            btnCancelar.textContent = '✕ Cancelar';
            btnCancelar.dataset.id = cita.id;
            btnCancelar.dataset.nombre = escapeHtml(cita.nombre);
            btnCancelar.dataset.correo = escapeHtml(cita.correo);
            btnCancelar.addEventListener('click', () => handleCancelar(cita.id, cita.nombre, cita.correo));
            acciones.appendChild(btnCancelar);
        }

        // Botón crear cliente (si no tiene id_cliente)
        if (!cita.id_cliente) {
            const btnCrearCliente = document.createElement('button');
            btnCrearCliente.className = 'aa-btn-crear-cliente-desde-cita';
            btnCrearCliente.textContent = '+ Cliente';
            btnCrearCliente.dataset.reservaId = cita.id;
            btnCrearCliente.dataset.nombre = escapeHtml(cita.nombre);
            btnCrearCliente.dataset.telefono = escapeHtml(cita.telefono);
            btnCrearCliente.dataset.correo = escapeHtml(cita.correo);
            btnCrearCliente.addEventListener('click', () => handleCrearCliente(cita.id, cita.nombre, cita.telefono, cita.correo));
            acciones.appendChild(btnCrearCliente);
        }

        expandedContent.appendChild(acciones);
        card.appendChild(expandedContent);

        // Toggle expand/collapse al hacer clic
        card.addEventListener('click', (e) => {
            // No expandir si se hace clic en un botón
            if (e.target.tagName === 'BUTTON') return;
            
            const expanded = expandedContent.style.display !== 'none';
            expandedContent.style.display = expanded ? 'none' : 'block';
            card.classList.toggle('aa-card-expanded-active', !expanded);
        });

        return card;
    }

    /**
     * Actualizar indicador de hora actual
     */
    function updateTimeIndicator() {
        if (!timeNowIndicator) return;

        const now = new Date();
        if (!isSameDay(currentDate, now)) {
            timeNowIndicator.style.display = 'none';
            return;
        }

        timeNowIndicator.style.display = 'block';
        
        // Calcular posición (porcentaje del día)
        const startOfDay = new Date(currentDate);
        startOfDay.setHours(0, 0, 0, 0);
        
        const endOfDay = new Date(currentDate);
        endOfDay.setHours(23, 59, 59, 999);
        
        const totalMinutes = (endOfDay - startOfDay) / 60000;
        const currentMinutes = (now - startOfDay) / 60000;
        const percentage = (currentMinutes / totalMinutes) * 100;
        
        timeNowIndicator.style.top = percentage + '%';
    }

    /**
     * Handlers de acciones
     */
    function handleConfirmar(id, nombre, correo) {
        if (confirm('¿Confirmar la cita de ' + nombre + '?\n\nSe enviará un correo de confirmación a: ' + correo)) {
            const formData = new FormData();
            formData.append('action', 'aa_confirmar_cita');
            formData.append('id', id);
            formData.append('_wpnonce', nonceConfirmar);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Cita confirmada. Se envió correo de confirmación.');
                    loadDayAppointments(); // Recargar
                } else {
                    alert('❌ Error: ' + (data.data?.message || 'No se pudo confirmar la cita.'));
                }
            })
            .catch(err => {
                console.error('Error al confirmar:', err);
                alert('❌ Error de conexión: ' + err.message);
            });
        }
    }

    function handleCancelar(id, nombre, correo) {
        if (confirm('⚠️ ¿CANCELAR la cita de ' + nombre + '?\n\nSe enviará un correo de cancelación a: ' + correo + '\n\nEsta acción no se puede deshacer.')) {
            const formData = new FormData();
            formData.append('action', 'aa_cancelar_cita');
            formData.append('id', id);
            formData.append('_wpnonce', nonceCancelar);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let mensaje = '✅ Cita cancelada correctamente.';
                    if (data.data?.calendar_deleted) {
                        mensaje += '\n🗓️ El evento también fue eliminado de Google Calendar.';
                    }
                    alert(mensaje);
                    loadDayAppointments(); // Recargar
                } else {
                    alert('❌ Error: ' + (data.data?.message || 'No se pudo cancelar la cita.'));
                }
            })
            .catch(err => {
                console.error('Error al cancelar:', err);
                alert('❌ Error de conexión: ' + err.message);
            });
        }
    }

    function handleCrearCliente(reservaId, nombre, telefono, correo) {
        if (confirm('¿Crear cliente con los siguientes datos?\n\nNombre: ' + nombre + '\nTeléfono: ' + telefono + '\nCorreo: ' + correo)) {
            const formData = new FormData();
            formData.append('action', 'aa_crear_cliente_desde_cita');
            formData.append('reserva_id', reservaId);
            formData.append('nombre', nombre);
            formData.append('telefono', telefono);
            formData.append('correo', correo);
            formData.append('_wpnonce', nonceCrearCliente);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.data.message);
                    loadDayAppointments(); // Recargar
                } else {
                    alert('❌ Error: ' + (data.data?.message || 'No se pudo crear el cliente.'));
                }
            })
            .catch(err => {
                console.error('Error al crear cliente:', err);
                alert('❌ Error de conexión: ' + err.message);
            });
        }
    }

    /**
     * Utilidades
     */
    function formatDateForAPI(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatTime(date) {
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${hours}:${minutes}`;
    }

    function formatEstado(estado) {
        const estados = {
            'confirmed': '✓ Confirmada',
            'pending': '⏳ Pendiente',
            'cancelled': '✕ Cancelada'
        };
        return estados[estado] || estado;
    }

    function getWeekdayName(date) {
        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        return days[date.getDay()];
    }

    function parseTime(timeStr) {
        if (!timeStr) return null;
        const parts = timeStr.split(':');
        if (parts.length !== 2) return null;
        return {
            hours: parseInt(parts[0], 10),
            minutes: parseInt(parts[1], 10)
        };
    }

    function isSameDay(date1, date2) {
        return date1.getFullYear() === date2.getFullYear() &&
               date1.getMonth() === date2.getMonth() &&
               date1.getDate() === date2.getDate();
    }

    function getSlotKey(date) {
        return date.toISOString().slice(0, 16); // YYYY-MM-DDTHH:MM
    }

    function findSlotContent(slotKey) {
        const slotDate = new Date(slotKey);
        const allContents = timeGrid.querySelectorAll('.aa-time-content');
        
        for (let content of allContents) {
            const contentSlotDate = new Date(content.dataset.slotTime);
            if (isSameDay(slotDate, contentSlotDate) && 
                slotDate.getHours() === contentSlotDate.getHours() &&
                slotDate.getMinutes() === contentSlotDate.getMinutes()) {
                return content;
            }
        }
        return null;
    }

    /**
     * Encontrar el slot más cercano a una fecha de cita
     */
    function findNearestSlotContent(citaDate) {
        const allContents = timeGrid.querySelectorAll('.aa-time-content');
        let nearestContent = null;
        let minDiff = Infinity;

        allContents.forEach(content => {
            const slotDate = new Date(content.dataset.slotTime);
            
            // Solo considerar slots del mismo día
            if (!isSameDay(citaDate, slotDate)) return;

            // Calcular diferencia en minutos
            const diff = Math.abs((citaDate - slotDate) / 60000);
            
            // Si la cita está dentro de 30 minutos del slot, usar ese slot
            if (diff < 30 && diff < minDiff) {
                minDiff = diff;
                nearestContent = content;
            }
        });

        return nearestContent;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function showError(message) {
        if (timeGrid) {
            timeGrid.innerHTML = '<div class="p-4 text-center text-red-600">❌ ' + escapeHtml(message) + '</div>';
        }
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Actualizar indicador de hora cada minuto
    setInterval(() => {
        if (timeNowIndicator && isSameDay(currentDate, new Date())) {
            updateTimeIndicator();
        }
    }, 60000);

})();
