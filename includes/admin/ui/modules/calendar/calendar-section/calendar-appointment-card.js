/**
 * Calendar Appointment Card - Card creation and button rendering
 * 
 * Design System: See /docs/DESIGN_BRIEF.md
 * - Cards neutras con barra lateral de estado
 * - Colores como acento, no como dominante
 * - Spacing consistente: 6/8/12/16px
 * - Border-radius: 6px
 */

(function() {
    'use strict';

    // =============================================
    // DESIGN TOKENS (from DESIGN_BRIEF.md)
    // =============================================
    const TOKENS = {
        // Colors - Neutrals
        gray50: '#f9fafb',
        gray100: '#f3f4f6',
        gray200: '#e5e7eb',
        gray300: '#d1d5db',
        gray400: '#9ca3af',
        gray500: '#6b7280',
        gray600: '#4b5563',
        gray700: '#374151',
        gray800: '#1f2937',
        
        // Colors - States
        green500: '#22c55e',
        green600: '#16a34a',
        green100: '#dcfce7',
        green700: '#166534',
        
        amber500: '#f59e0b',
        amber600: '#d97706',
        amber100: '#fef3c7',
        amber700: '#92400e',
        
        red500: '#ef4444',
        red600: '#dc2626',
        red100: '#fee2e2',
        red700: '#991b1b',
        
        blue500: '#3b82f6',
        blue600: '#2563eb',
        
        // Spacing
        space1: '4px',
        space2: '6px',
        space3: '8px',
        space4: '12px',
        space5: '16px',
        
        // Radius
        radiusSm: '4px',
        radiusMd: '6px',
        
        // Shadows
        shadowXs: '0 1px 2px rgba(0, 0, 0, 0.05)',
        shadowSm: '0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06)',
        shadowMd: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
        shadowLg: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
        
        // Typography
        textXs: '11px',
        textSm: '12px',
        textBase: '13px',
        
        // Transitions
        transitionFast: '150ms ease',
        transitionNormal: '200ms ease'
    };

    // Estado colors mapping
    const ESTADO_CONFIG = {
        'pending': {
            barColor: TOKENS.amber500,
            badgeBg: TOKENS.amber100,
            badgeText: TOKENS.amber700,
            badgeLabel: 'Pendiente',
            opacity: '1'
        },
        'confirmed': {
            barColor: TOKENS.green500,
            badgeBg: TOKENS.green100,
            badgeText: TOKENS.green700,
            badgeLabel: 'Confirmada',
            opacity: '1'
        },
        'cancelled': {
            barColor: TOKENS.gray400,
            badgeBg: TOKENS.red100,
            badgeText: TOKENS.red700,
            badgeLabel: 'Cancelada',
            opacity: '0.6'
        },
        'asistió': {
            barColor: TOKENS.green500,
            badgeBg: TOKENS.green100,
            badgeText: TOKENS.green700,
            badgeLabel: 'Asistió',
            opacity: '1'
        },
        'no asistió': {
            barColor: TOKENS.red500,
            badgeBg: TOKENS.red100,
            badgeText: TOKENS.red700,
            badgeLabel: 'No asistió',
            opacity: '0.7'
        }
    };

    /**
     * Crear el DOM de la card de cita
     */
    function crearCardCita(cita) {
        const card = document.createElement('div');
        card.className = 'aa-appointment-card';
        card.setAttribute('data-id', cita.id || '');
        
        const estado = cita.estado || 'pending';
        const config = ESTADO_CONFIG[estado] || ESTADO_CONFIG['pending'];
        
        // =============================================
        // CARD CONTAINER - Diseño neutro con barra lateral
        // =============================================
        Object.assign(card.style, {
            backgroundColor: '#ffffff',
            border: `1px solid ${TOKENS.gray200}`,
            borderWidth: '1px 2px',
            borderLeft: `2px solid ${config.barColor}`,
            borderRadius: TOKENS.radiusMd,
            overflow: 'hidden',
            cursor: 'pointer',
            display: 'flex',
            flexDirection: 'column',
            minHeight: '0',
            height: '100%',
            minWidth: '0',
            maxWidth: '100%',
            boxSizing: 'border-box',
            opacity: config.opacity,
            boxShadow: TOKENS.shadowXs,
            transition: `box-shadow ${TOKENS.transitionFast}, border-color ${TOKENS.transitionFast}`
        });
        
        // Hover effect
        card.addEventListener('mouseenter', function() {
            if (card.dataset.expanded !== 'true') {
                card.style.boxShadow = TOKENS.shadowSm;
                card.style.borderColor = TOKENS.gray300;
            }
        });
        card.addEventListener('mouseleave', function() {
            if (card.dataset.expanded !== 'true') {
                card.style.boxShadow = TOKENS.shadowXs;
                card.style.borderColor = TOKENS.gray200;
            }
        });
        
        // =============================================
        // HEADER - Texto neutral, sin border de color
        // =============================================
        const header = document.createElement('div');
        header.className = 'aa-appointment-header';
        Object.assign(header.style, {
            padding: `${TOKENS.space2} 15px`,
            backgroundColor: TOKENS.gray50,
            borderBottom: `1px solid ${TOKENS.gray100}`,
            color: TOKENS.gray600,
            fontWeight: '600',
            fontSize: '15px',
            lineHeight: '1.3',
            flex: '1',
            display: 'flex',
            alignItems: 'center',
            gap: TOKENS.space3,
            minHeight: '0',
            minWidth: '0',
            overflow: 'hidden',
            boxSizing: 'border-box',
            borderTopLeftRadius: '0',
            borderTopRightRadius: TOKENS.radiusMd,
            borderBottomLeftRadius: '0',
            borderBottomRightRadius: TOKENS.radiusMd,
            transition: `border-radius ${TOKENS.transitionFast}`
        });
        
        // Título: solo cliente (el servicio va en el body)
        const titleText = document.createElement('h3');
        titleText.className = 'text-lg font-semibold text-gray-600 truncate min-w-0 flex-1 m-0';
        titleText.textContent = cita.nombre || 'Sin nombre';
        
        header.appendChild(titleText);
        
        // =============================================
        // INDICADOR DE ESTADO + label Virtual (visible en card colapsada)
        // =============================================
        const statusIndicator = crearIndicadorEstadoCompacto(estado, config);
        const metaCol = document.createElement('div');
        Object.assign(metaCol.style, {
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'flex-end',
            gap: '2px',
            flexShrink: '0'
        });
        metaCol.appendChild(statusIndicator);
        if (cita.attendance_type === 'virtual') {
            const virtualLabel = document.createElement('span');
            virtualLabel.textContent = 'Virtual';
            Object.assign(virtualLabel.style, {
                fontSize: '10px',
                fontWeight: '500',
                lineHeight: '1',
                color: TOKENS.gray500,
                whiteSpace: 'nowrap'
            });
            metaCol.appendChild(virtualLabel);
        }
        header.appendChild(metaCol);
        
        // =============================================
        // BODY - Panel expandido premium
        // =============================================
        const body = document.createElement('div');
        body.className = 'aa-appointment-body';
        body.setAttribute('hidden', '');
        Object.assign(body.style, {
            padding: '0 15px 15px',
            backgroundColor: '#ffffff',
            fontSize: TOKENS.textBase,
            flexShrink: '0',
            borderBottomLeftRadius: TOKENS.radiusMd,
            borderBottomRightRadius: TOKENS.radiusMd,
            borderTop: `1px solid ${TOKENS.gray100}`,
        });
        
        // ----- Sección: Meta (hora + duración) -----
        const estadoSection = document.createElement('div');
        Object.assign(estadoSection.style, {
            marginBottom: '7px',
            display: 'flex',
            alignItems: 'center',
            gap: TOKENS.space3
        });

        const horaStr = cita.fecha
            ? (window.DateUtils?.hm
                ? window.DateUtils.hm(new Date(cita.fecha))
                : (cita.fecha.match(/\d{2}:\d{2}/) || [])[0] || '')
            : '';
        const metaParts = [];
        if (horaStr) metaParts.push(horaStr + ' hrs');
        if (cita.duracion) metaParts.push(cita.duracion + ' min');

        if (metaParts.length > 0) {
            const metaBadge = document.createElement('span');
            metaBadge.className = 'aa-appointment-meta text-base font-medium text-gray-600 inline-flex items-center gap-1.5';
            metaBadge.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
            const metaLabel = document.createElement('span');
            metaLabel.textContent = metaParts.join(' - ');
            metaBadge.appendChild(metaLabel);
            estadoSection.appendChild(metaBadge);
            body.appendChild(estadoSection);
        }
        
        // ----- Sección: Enlace cita virtual (arriba del WhatsApp; no en pendiente, join aún no creado) -----
        if (cita.attendance_type === 'virtual' && cita.join_url && cita.estado !== 'pending') {
            const virtualLinkSection = document.createElement('div');
            Object.assign(virtualLinkSection.style, {
                marginBottom: TOKENS.space4,
                display: 'flex',
                flexDirection: 'column',
                gap: TOKENS.space2
            });
            const virtualLink = document.createElement('a');
            virtualLink.href = cita.join_url;
            virtualLink.target = '_blank';
            virtualLink.rel = 'noopener noreferrer';
            virtualLink.textContent = 'Unirse a la cita virtual';
            Object.assign(virtualLink.style, {
                fontSize: TOKENS.textSm,
                color: TOKENS.blue600,
                fontWeight: '500',
                textDecoration: 'none'
            });
            virtualLink.addEventListener('mouseenter', function() {
                virtualLink.style.textDecoration = 'underline';
            });
            virtualLink.addEventListener('mouseleave', function() {
                virtualLink.style.textDecoration = 'none';
            });
            virtualLinkSection.appendChild(virtualLink);
            body.appendChild(virtualLinkSection);
        }
        
        // ----- Sección: Contacto (+ servicio) -----
        const contactSection = document.createElement('div');
        Object.assign(contactSection.style, {
            marginBottom: TOKENS.space4,
            display: 'flex',
            flexDirection: 'column',
            gap: TOKENS.space2
        });
        
        const serviceRow = crearContactRow('service', cita.servicio || 'Sin servicio', cita);
        contactSection.appendChild(serviceRow);
        
        if (cita.telefono) {
            const phoneRow = crearContactRow('whatsapp', cita.telefono, cita);
            contactSection.appendChild(phoneRow);
        }

        if (cita.service_area_name) {
            const areaRow = crearContactRow('area', cita.service_area_name, cita);
            contactSection.appendChild(areaRow);
        }
        
        body.appendChild(contactSection);
        
        // Determinar si la cita es próxima o pasada usando el service
        const esProxima = window.AdminCalendarService?.esCitaProxima(cita) || false;
        
        // ----- Sección: Acciones (Botones) -----
        const botones = renderizarBotonesYCitas(cita, esProxima);
        if (botones) {
            body.appendChild(botones);
        }
        
        // =============================================
        // Toggle acordeón
        // =============================================
        function actualizarEstilosHeader() {
            const isHidden = body.hasAttribute('hidden');
            if (isHidden) {
                header.style.flex = '1';
                header.style.flexShrink = '0';
                body.style.flex = '0';
                header.style.borderBottomLeftRadius = '0';
                header.style.borderBottomRightRadius = TOKENS.radiusMd;
                header.style.borderBottom = `1px solid ${TOKENS.gray100}`;
            } else {
                header.style.flex = '0 0 auto';
                header.style.flexShrink = '0';
                body.style.flex = '1';
                header.style.borderBottomLeftRadius = '0';
                header.style.borderBottomRightRadius = '0';
                header.style.borderBottom = `1px solid ${TOKENS.gray200}`;
            }
        }
        
        actualizarEstilosHeader();
        
        header.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = body.hasAttribute('hidden');
            if (isHidden) {
                body.removeAttribute('hidden');
            } else {
                body.setAttribute('hidden', '');
            }
            actualizarEstilosHeader();
        });
        
        // Ensamblar card
        card.appendChild(header);
        card.appendChild(body);
        
        return card;
    }

    /**
     * Crear indicador de estado compacto para header (card colapsada)
     * Un dot pequeño + texto mini que es sutil pero legible
     */
    function crearIndicadorEstadoCompacto(estado, config) {
        const indicator = document.createElement('span');
        indicator.className = 'aa-status-indicator';
        
        Object.assign(indicator.style, {
            display: 'inline-flex',
            alignItems: 'center',
            gap: '4px',
            padding: `2px ${TOKENS.space2}`,
            borderRadius: TOKENS.radiusSm,
            fontSize: TOKENS.textSm,
            fontWeight: '600',
            lineHeight: '1',
            backgroundColor: 'transparent',
            color: TOKENS.gray500,
            flexShrink: '0',
            opacity: '0.9',
            whiteSpace: 'nowrap'
        });
        
        // Dot de color
        const dot = document.createElement('span');
        Object.assign(dot.style, {
            width: '6px',
            height: '6px',
            borderRadius: '50%',
            backgroundColor: config.barColor,
            flexShrink: '0'
        });
        
        // Texto abreviado del estado
        const statusText = document.createElement('span');
        const abreviaciones = {
            'pending': 'Pend.',
            'confirmed': 'Conf.',
            'cancelled': 'Canc.',
            'asistió': 'Asist.',
            'no asistió': 'No asist.'
        };
        statusText.textContent = abreviaciones[estado] || config.badgeLabel;
        
        indicator.appendChild(dot);
        indicator.appendChild(statusText);
        
        return indicator;
    }

    /**
     * Crear fila de contacto (WhatsApp o Email)
     */
    function crearContactRow(type, value, cita) {
        const row = document.createElement('div');
        row.className = 'aa-appointment-contact-row flex items-center gap-1.5 text-base font-medium text-gray-600';

        if (type === 'whatsapp') {
            // Logo WhatsApp original, tono dark vía currentColor (sin verde)
            const svgPhone = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>`;

            const phoneLink = document.createElement('span');
            phoneLink.className = 'aa-whatsapp-link aa-appointment-contact-link inline-flex items-center gap-1.5 min-w-0 cursor-pointer';
            phoneLink.innerHTML = svgPhone;
            const phoneText = document.createElement('span');
            phoneText.className = 'aa-wa-phone-text truncate';
            phoneText.textContent = value;
            phoneLink.appendChild(phoneText);

            // Data attributes para delegación
            phoneLink.dataset.phone = value;
            phoneLink.dataset.status = cita.estado || 'pending';
            phoneLink.dataset.service = cita.servicio || '';
            phoneLink.dataset.datetime = cita.fecha || '';
            phoneLink.dataset.name = cita.nombre || '';
            phoneLink.dataset.attendanceType = cita.attendance_type || '';
            phoneLink.dataset.joinUrl = cita.join_url || '';
            phoneLink.title = 'Enviar WhatsApp';

            row.appendChild(phoneLink);
        } else if (type === 'service') {
            // Mismo icono del nav "Servicios" (sidebar), tamaño alineado a filas de contacto
            const svgService = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>`;
            const serviceSpan = document.createElement('span');
            serviceSpan.className = 'inline-flex items-center gap-1.5 min-w-0';
            serviceSpan.innerHTML = svgService;
            const label = document.createElement('span');
            label.className = 'truncate';
            label.textContent = value;
            serviceSpan.appendChild(label);
            row.appendChild(serviceSpan);
        } else if (type === 'area') {
            // Mismo icono de "Zonas de atención" (módulo Asignaciones)
            const svgArea = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>`;
            const areaSpan = document.createElement('span');
            areaSpan.className = 'inline-flex items-center gap-1.5 min-w-0';
            areaSpan.innerHTML = svgArea;
            const label = document.createElement('span');
            label.className = 'truncate';
            label.textContent = value;
            areaSpan.appendChild(label);
            row.appendChild(areaSpan);
        }

        return row;
    }

    /**
     * Renderizar botones y leyendas según estado y si es próxima/pasada
     */
    function renderizarBotonesYCitas(cita, esProxima) {
        const estado = cita.estado || 'pending';
        const contenedor = document.createElement('div');
        Object.assign(contenedor.style, {
            display: 'flex',
            gap: TOKENS.space3,
            flexWrap: 'wrap'
        });
        
        let tieneContenido = false;
        
        if (esProxima) {
            // ===== CITA PRÓXIMA =====
            if (estado === 'confirmed') {
                const btnCancelar = crearBoton('Cancelar', 'cancelar', 'danger', cita.id);
                contenedor.appendChild(btnCancelar);
                tieneContenido = true;
            }
            else if (estado === 'pending') {
                const btnConfirmar = crearBoton('Confirmar', 'confirmar', 'success', cita.id);
                const btnCancelar = crearBoton('Cancelar', 'cancelar', 'danger', cita.id);
                contenedor.appendChild(btnConfirmar);
                contenedor.appendChild(btnCancelar);
                tieneContenido = true;
            }
            else if (estado === 'cancelled' || estado === 'asistió' || estado === 'no asistió') {
                // No mostrar botones, el badge ya indica el estado
            }
        } else {
            // ===== CITA PASADA =====
            if (estado === 'confirmed') {
                const btnAsistio = crearBoton('Asistió', 'asistio', 'success', cita.id);
                const btnNoAsistio = crearBoton('No asistió', 'no-asistio', 'danger', cita.id);
                contenedor.appendChild(btnAsistio);
                contenedor.appendChild(btnNoAsistio);
                tieneContenido = true;
            }
            // Para otros estados (pending, cancelled, asistió, no asistió), el badge ya lo indica
        }
        
        return tieneContenido ? contenedor : null;
    }

    /**
     * Crear un botón con estilo consistente
     * Outline estilo tareas (Completar): fondo blanco, borde y texto tintados
     * @param {string} texto - Texto del botón
     * @param {string} accion - Acción (confirmar, cancelar, asistio, no-asistio)
     * @param {string} variant - Variante: 'success', 'danger', 'secondary'
     * @param {string|number} citaId - ID de la cita
     */
    function crearBoton(texto, accion, variant, citaId) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.textContent = texto;
        boton.setAttribute('data-action', accion);
        boton.setAttribute('data-id', citaId);
        
        // Base alineada a botones de tareas: px-3 py-1.5 text-xs rounded-lg border
        const baseStyles = {
            padding: `${TOKENS.space2} ${TOKENS.space4}`,
            borderRadius: '8px',
            cursor: 'pointer',
            fontSize: TOKENS.textSm,
            fontWeight: '500',
            lineHeight: '1',
            transition: `color ${TOKENS.transitionFast}, background-color ${TOKENS.transitionFast}, border-color ${TOKENS.transitionFast}`,
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: '#ffffff'
        };
        
        // Completar: text-green-700 border-green-200 bg-white hover:text-green-800
        // Danger: misma estructura, rojo un poco desaturado
        const green700 = '#15803d';
        const green800 = '#166534';
        const green200 = '#bbf7d0';
        const redMuted = '#a35d5d';
        const redMutedHover = '#8f4e4e';
        const redMutedBorder = '#e0c4c4';
        
        let variantStyles = {};
        let hoverStyles = {};
        
        switch (variant) {
            case 'success':
                variantStyles = {
                    color: green700,
                    border: `1px solid ${green200}`
                };
                hoverStyles = { color: green800 };
                break;
            case 'danger':
                variantStyles = {
                    color: redMuted,
                    border: `1px solid ${redMutedBorder}`
                };
                hoverStyles = { color: redMutedHover };
                break;
            case 'secondary':
            default:
                variantStyles = {
                    color: TOKENS.gray700,
                    border: `1px solid ${TOKENS.gray300}`
                };
                hoverStyles = { 
                    backgroundColor: TOKENS.gray50,
                    borderColor: TOKENS.gray400
                };
        }
        
        Object.assign(boton.style, baseStyles, variantStyles);
        
        const originalStyles = { ...baseStyles, ...variantStyles };
        
        boton.addEventListener('mouseenter', () => {
            Object.assign(boton.style, hoverStyles);
        });
        boton.addEventListener('mouseleave', () => {
            Object.assign(boton.style, originalStyles);
        });
        
        return boton;
    }

    // Exponer API pública
    window.CalendarAppointmentCard = {
        crearCardCita: crearCardCita
    };

})();
