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
            borderLeft: `3px solid ${config.barColor}`,
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
            padding: `${TOKENS.space2} ${TOKENS.space3}`,
            backgroundColor: TOKENS.gray50,
            borderBottom: `1px solid ${TOKENS.gray100}`,
            color: TOKENS.gray700,
            fontWeight: '500',
            fontSize: TOKENS.textBase,
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
        
        // Texto del título (truncado)
        const titleText = document.createElement('span');
        titleText.style.flex = '1';
        titleText.style.overflow = 'hidden';
        titleText.style.textOverflow = 'ellipsis';
        titleText.style.whiteSpace = 'nowrap';
        titleText.style.minWidth = '0';
        
        const clienteNombre = cita.nombre || 'Sin nombre';
        const servicio = cita.servicio || 'Sin servicio';
        titleText.textContent = `${clienteNombre} - ${servicio}`;
        
        header.appendChild(titleText);
        
        // =============================================
        // INDICADOR DE ESTADO (visible en card colapsada)
        // =============================================
        const statusIndicator = crearIndicadorEstadoCompacto(estado, config);
        header.appendChild(statusIndicator);
        
        // =============================================
        // BODY - Panel expandido premium
        // =============================================
        const body = document.createElement('div');
        body.className = 'aa-appointment-body';
        body.setAttribute('hidden', '');
        Object.assign(body.style, {
            padding: TOKENS.space5,
            backgroundColor: '#ffffff',
            fontSize: TOKENS.textBase,
            flexShrink: '0',
            borderBottomLeftRadius: '0',
            borderBottomRightRadius: TOKENS.radiusMd
        });
        
        // ----- Sección: Estado (Badge) -----
        const estadoSection = document.createElement('div');
        Object.assign(estadoSection.style, {
            marginBottom: TOKENS.space4,
            display: 'flex',
            alignItems: 'center',
            gap: TOKENS.space3
        });
        
        const badge = crearBadge(config.badgeLabel, config.badgeBg, config.badgeText);
        estadoSection.appendChild(badge);
        
        // Duración junto al badge
        if (cita.duracion) {
            const duracionBadge = document.createElement('span');
            Object.assign(duracionBadge.style, {
                fontSize: TOKENS.textXs,
                color: TOKENS.gray500,
                display: 'inline-flex',
                alignItems: 'center',
                gap: '4px'
            });
            duracionBadge.innerHTML = `<span style="font-size: 10px;">⏱</span> ${cita.duracion} min`;
            estadoSection.appendChild(duracionBadge);
        }
        
        body.appendChild(estadoSection);
        
        // ----- Sección: Contacto -----
        const contactSection = document.createElement('div');
        Object.assign(contactSection.style, {
            marginBottom: TOKENS.space4,
            display: 'flex',
            flexDirection: 'column',
            gap: TOKENS.space2
        });
        
        if (cita.telefono) {
            const phoneRow = crearContactRow('whatsapp', cita.telefono, cita);
            contactSection.appendChild(phoneRow);
        }
        
        if (cita.correo) {
            const emailRow = crearContactRow('email', cita.correo, cita);
            contactSection.appendChild(emailRow);
        }
        
        if (contactSection.children.length > 0) {
            body.appendChild(contactSection);
        }
        
        // Determinar si la cita es próxima o pasada usando el service
        const esProxima = window.AdminCalendarService?.esCitaProxima(cita) || false;
        
        // ----- Sección: Acciones (Botones) -----
        const botones = renderizarBotonesYCitas(cita, esProxima);
        if (botones) {
            // Separator line before buttons
            const separator = document.createElement('div');
            Object.assign(separator.style, {
                height: '1px',
                backgroundColor: TOKENS.gray100,
                margin: `${TOKENS.space4} 0`
            });
            body.appendChild(separator);
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
            fontSize: '10px',
            fontWeight: '500',
            lineHeight: '1',
            backgroundColor: config.badgeBg,
            color: config.badgeText,
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
     * Crear un badge/pill de estado
     */
    function crearBadge(texto, bgColor, textColor) {
        const badge = document.createElement('span');
        Object.assign(badge.style, {
            display: 'inline-flex',
            alignItems: 'center',
            padding: `2px ${TOKENS.space3}`,
            borderRadius: TOKENS.radiusSm,
            fontSize: TOKENS.textXs,
            fontWeight: '500',
            lineHeight: '1.4',
            backgroundColor: bgColor,
            color: textColor
        });
        badge.textContent = texto;
        return badge;
    }

    /**
     * Crear fila de contacto (WhatsApp o Email)
     */
    function crearContactRow(type, value, cita) {
        const row = document.createElement('div');
        Object.assign(row.style, {
            display: 'flex',
            alignItems: 'center',
            gap: TOKENS.space2
        });
        
        if (type === 'whatsapp') {
            const svgWhatsApp = `<svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366" xmlns="http://www.w3.org/2000/svg">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>`;
            
            const phoneLink = document.createElement('span');
            phoneLink.className = 'aa-whatsapp-link';
            phoneLink.innerHTML = `${svgWhatsApp} <span class="aa-wa-phone-text">${value}</span>`;
            
            // Data attributes para delegación
            phoneLink.dataset.phone = value;
            phoneLink.dataset.status = cita.estado || 'pending';
            phoneLink.dataset.service = cita.servicio || '';
            phoneLink.dataset.datetime = cita.fecha || '';
            phoneLink.dataset.name = cita.nombre || '';
            
            Object.assign(phoneLink.style, {
                display: 'inline-flex',
                alignItems: 'center',
                gap: TOKENS.space2,
                cursor: 'pointer',
                color: '#25D366',
                fontWeight: '500',
                fontSize: TOKENS.textSm,
                padding: `${TOKENS.space1} ${TOKENS.space2}`,
                borderRadius: TOKENS.radiusSm,
                transition: `background-color ${TOKENS.transitionFast}`
            });
            phoneLink.title = 'Enviar WhatsApp';
            
            phoneLink.addEventListener('mouseenter', () => {
                phoneLink.style.backgroundColor = 'rgba(37, 211, 102, 0.1)';
            });
            phoneLink.addEventListener('mouseleave', () => {
                phoneLink.style.backgroundColor = 'transparent';
            });
            
            row.appendChild(phoneLink);
        } else if (type === 'email') {
            const emailSpan = document.createElement('span');
            Object.assign(emailSpan.style, {
                display: 'inline-flex',
                alignItems: 'center',
                gap: TOKENS.space2,
                color: TOKENS.gray600,
                fontSize: TOKENS.textSm
            });
            emailSpan.innerHTML = `<span style="font-size: 12px;">✉️</span> ${value}`;
            row.appendChild(emailSpan);
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
     * @param {string} texto - Texto del botón
     * @param {string} accion - Acción (confirmar, cancelar, asistio, no-asistio)
     * @param {string} variant - Variante: 'success', 'danger', 'secondary'
     * @param {string|number} citaId - ID de la cita
     */
    function crearBoton(texto, accion, variant, citaId) {
        const boton = document.createElement('button');
        boton.textContent = texto;
        boton.setAttribute('data-action', accion);
        boton.setAttribute('data-id', citaId);
        
        // Estilos base consistentes
        const baseStyles = {
            padding: `${TOKENS.space3} ${TOKENS.space4}`,
            border: 'none',
            borderRadius: TOKENS.radiusMd,
            cursor: 'pointer',
            fontSize: TOKENS.textSm,
            fontWeight: '500',
            lineHeight: '1',
            transition: `all ${TOKENS.transitionFast}`,
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center'
        };
        
        // Variantes
        let variantStyles = {};
        let hoverStyles = {};
        
        switch (variant) {
            case 'success':
                variantStyles = {
                    backgroundColor: TOKENS.green500,
                    color: '#ffffff',
                    border: 'none'
                };
                hoverStyles = { backgroundColor: TOKENS.green600 };
                break;
            case 'danger':
                variantStyles = {
                    backgroundColor: '#ffffff',
                    color: TOKENS.red600,
                    border: `1px solid ${TOKENS.red100}`
                };
                hoverStyles = { 
                    backgroundColor: TOKENS.red100,
                    borderColor: TOKENS.red500
                };
                break;
            case 'secondary':
            default:
                variantStyles = {
                    backgroundColor: '#ffffff',
                    color: TOKENS.gray700,
                    border: `1px solid ${TOKENS.gray300}`
                };
                hoverStyles = { 
                    backgroundColor: TOKENS.gray50,
                    borderColor: TOKENS.gray400
                };
        }
        
        Object.assign(boton.style, baseStyles, variantStyles);
        
        // Hover/active effects
        const originalStyles = { ...variantStyles };
        
        boton.addEventListener('mouseenter', () => {
            Object.assign(boton.style, hoverStyles);
        });
        boton.addEventListener('mouseleave', () => {
            Object.assign(boton.style, originalStyles);
        });
        boton.addEventListener('mousedown', () => {
            boton.style.transform = 'scale(0.98)';
        });
        boton.addEventListener('mouseup', () => {
            boton.style.transform = 'scale(1)';
        });
        
        return boton;
    }

    // Exponer API pública
    window.CalendarAppointmentCard = {
        crearCardCita: crearCardCita
    };

})();
