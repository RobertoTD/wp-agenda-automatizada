/**
 * Calendar Appointment Card - Card creation and button rendering
 */

(function() {
    'use strict';

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
        card.style.overflow = 'hidden'; // Prevent content from overflowing
        card.style.cursor = 'pointer';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.minHeight = '40px';
        card.style.minWidth = '0'; // Allow card to shrink below content size in flex/grid
        card.style.maxWidth = '100%'; // Never exceed container width
        card.style.boxSizing = 'border-box';
        
        // Header (siempre visible)
        const header = document.createElement('div');
        header.className = 'aa-appointment-header';
        header.style.padding = '8px 6px'; // Reduced horizontal padding for narrow columns
        header.style.backgroundColor = getEstadoColor(cita.estado);
        header.style.color = '#fff';
        header.style.fontWeight = '500';
        header.style.fontSize = '14px';
        // Inicialmente, cuando el body está oculto, el header ocupa todo el espacio
        header.style.flex = '1';
        header.style.display = 'flex';
        header.style.alignItems = 'center';
        header.style.minHeight = '40px';
        header.style.minWidth = '0'; // Allow header to shrink below content size
        header.style.overflow = 'hidden'; // Hide overflowing text
        header.style.textOverflow = 'ellipsis'; // Show ellipsis for truncated text
        header.style.whiteSpace = 'nowrap'; // Prevent text wrapping
        header.style.boxSizing = 'border-box';
        
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
        body.style.flexShrink = '0';
        
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
        
        // Mostrar duración
        if (cita.duracion) {
            const duracion = document.createElement('div');
            duracion.textContent = `Duración: ${cita.duracion} minutos`;
            duracion.style.marginBottom = '4px';
            info.appendChild(duracion);
        }
        
        // Mostrar assignment_id
        if (cita.assignment_id) {
            const asignacion = document.createElement('div');
            asignacion.textContent = `Asignación: ${cita.assignment_id}`;
            asignacion.style.marginBottom = '4px';
            info.appendChild(asignacion);
        }
        
        body.appendChild(info);
        
        // Log de datos de la card renderizada
        console.log('[CalendarAppointmentCard] Card renderizada:', {
            id: cita.id,
            nombre: cita.nombre,
            servicio: cita.servicio,
            duracion: cita.duracion,
            assignment_id: cita.assignment_id,
            estado: cita.estado
        });
        
        // Determinar si la cita es próxima o pasada usando el service
        const esProxima = window.AdminCalendarService?.esCitaProxima(cita) || false;
        
        // Renderizar botones/leyendas según reglas
        const botones = renderizarBotonesYCitas(cita, esProxima);
        if (botones) {
            body.appendChild(botones);
        }
        
        // Función para actualizar estilos según el estado del body
        function actualizarEstilosHeader() {
            const isHidden = body.hasAttribute('hidden');
            if (isHidden) {
                // Body oculto: header ocupa todo el espacio vertical
                header.style.flex = '1';
                header.style.flexShrink = '0';
                body.style.flex = '0';
            } else {
                // Body visible: header tamaño normal, body ocupa el resto
                header.style.flex = '0 0 auto';
                header.style.flexShrink = '0';
                body.style.flex = '1';
            }
        }
        
        // Inicializar estilos según el estado inicial (body oculto)
        actualizarEstilosHeader();
        
        // Toggle acordeón: click en header abre/cierra body
        header.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = body.hasAttribute('hidden');
            if (isHidden) {
                body.removeAttribute('hidden');
            } else {
                body.setAttribute('hidden', '');
            }
            // Actualizar estilos después del toggle
            actualizarEstilosHeader();
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

    // Exponer API pública
    window.CalendarAppointmentCard = {
        crearCardCita: crearCardCita
    };

})();

