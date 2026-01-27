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
        
        // Estilos base - altura controlada por el grid, NO por min-content
        card.style.border = '1px solid #e5e7eb';
        card.style.borderRadius = '10px';
        card.style.overflow = 'hidden'; // Prevent content from overflowing (se cambia a visible al expandir)
        card.style.cursor = 'pointer';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.minHeight = '0'; // NO forzar altura - el grid la controla
        card.style.height = '100%'; // Ocupar todo el espacio del grid item
        card.style.minWidth = '0'; // Allow card to shrink below content size in flex/grid
        card.style.maxWidth = '100%'; // Never exceed container width
        card.style.boxSizing = 'border-box';
        
        // Header (siempre visible)
        const header = document.createElement('div');
        header.className = 'aa-appointment-header';
        header.style.padding = '4px 6px'; // Padding reducido para caber en 40px
        // Background gris claro fijo
        header.style.backgroundColor = 'rgb(249, 250, 251)';
        // Borde de 3px con color dinámico según estado
        const estadoColor = getEstadoColor(cita.estado);
        header.style.border = '3px solid ' + estadoColor;
        // Color del texto igual al color dinámico del estado
        header.style.color = estadoColor;
        header.style.fontWeight = '500';
        header.style.fontSize = '13px'; // Fuente ligeramente más pequeña
        header.style.lineHeight = '1.2'; // Line-height compacto
        // Inicialmente, cuando el body está oculto, el header ocupa todo el espacio
        header.style.flex = '1';
        header.style.display = 'flex';
        header.style.alignItems = 'center';
        header.style.minHeight = '0'; // NO forzar altura - el grid la controla
        header.style.minWidth = '0'; // Allow header to shrink below content size
        header.style.overflow = 'hidden'; // Hide overflowing text
        header.style.textOverflow = 'ellipsis'; // Show ellipsis for truncated text
        header.style.whiteSpace = 'nowrap'; // Prevent text wrapping
        header.style.boxSizing = 'border-box';
        // Border radius: esquinas superiores siempre a 10px, inferiores solo cuando body está oculto
        header.style.borderTopLeftRadius = '10px';
        header.style.borderTopRightRadius = '10px';
        // Inicialmente el body está oculto, así que el header tiene todas las esquinas redondeadas
        header.style.borderBottomLeftRadius = '10px';
        header.style.borderBottomRightRadius = '10px';
        
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
        // Border radius: esquinas inferiores
        body.style.borderBottomLeftRadius = '10px';
        body.style.borderBottomRightRadius = '10px';
        
        // Información de la cita
        const info = document.createElement('div');
        info.style.marginBottom = '12px';
        
        if (cita.telefono) {
            const telefono = document.createElement('div');
            telefono.style.marginBottom = '4px';
            
            // SVG ícono de WhatsApp (inline)
            const svgWhatsApp = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>`;
            
            // Crear link clickeable para WhatsApp (sin listener - usa delegación)
            const phoneLink = document.createElement('span');
            phoneLink.className = 'aa-whatsapp-link';
            phoneLink.innerHTML = `${svgWhatsApp} <span class="aa-wa-phone-text">${cita.telefono}</span>`;
            
            // Data attributes para delegación
            phoneLink.dataset.phone = cita.telefono;
            phoneLink.dataset.status = cita.estado || 'pending';
            phoneLink.dataset.service = cita.servicio || '';
            phoneLink.dataset.datetime = cita.fecha || '';
            phoneLink.dataset.name = cita.nombre || '';
            
            // Estilos inline para link clickeable con layout pro
            phoneLink.style.display = 'inline-flex';
            phoneLink.style.alignItems = 'center';
            phoneLink.style.gap = '6px';
            phoneLink.style.cursor = 'pointer';
            phoneLink.style.color = '#25D366';
            phoneLink.style.fontWeight = '500';
            phoneLink.title = 'Enviar WhatsApp';
            
            telefono.appendChild(phoneLink);
            info.appendChild(telefono);
        }
        
        if (cita.correo) {
            const correo = document.createElement('div');
            correo.textContent = `✉️ ${cita.correo}`;
            correo.style.marginBottom = '4px';
            info.appendChild(correo);
        }
        
        const estado = document.createElement('div');
        const estadoTraducido = traducirEstado(cita.estado || 'pending');
        estado.textContent = `Estado: ${estadoTraducido}`;
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
                // Body oculto: header ocupa todo el espacio vertical y tiene todas las esquinas redondeadas
                header.style.flex = '1';
                header.style.flexShrink = '0';
                body.style.flex = '0';
                header.style.borderBottomLeftRadius = '10px';
                header.style.borderBottomRightRadius = '10px';
            } else {
                // Body visible: header tamaño normal, body ocupa el resto
                header.style.flex = '0 0 auto';
                header.style.flexShrink = '0';
                body.style.flex = '1';
                header.style.borderBottomLeftRadius = '0';
                header.style.borderBottomRightRadius = '0';
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
     * Traducir estado de la cita al español
     * @param {string} estado - Estado de la cita
     * @returns {string} Estado traducido
     */
    function traducirEstado(estado) {
        const traducciones = {
            'confirmed': 'Confirmada',
            'pending': 'Pendiente',
            'cancelled': 'Cancelada',
            'asistió': 'Asistió',
            'no asistió': 'No asistió'
        };
        
        // Si existe traducción, usarla; si no, capitalizar primera letra
        if (traducciones[estado]) {
            return traducciones[estado];
        }
        
        // Para estados que no tienen traducción específica, capitalizar primera letra
        if (estado && estado.length > 0) {
            return estado.charAt(0).toUpperCase() + estado.slice(1);
        }
        
        return estado || 'Pendiente';
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

