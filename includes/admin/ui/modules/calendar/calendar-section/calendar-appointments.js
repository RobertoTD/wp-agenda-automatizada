/**
 * Calendar Appointments - Load and render appointments
 * 
 * Design System: See /docs/DESIGN_BRIEF.md
 * - Panel expandido premium con sombras y organización clara
 * - Transiciones suaves
 * - Z-index respetando la escala del sistema
 */

(function() {
    'use strict';

    // =============================================
    // DESIGN TOKENS
    // =============================================
    const TOKENS = {
        gray200: '#e5e7eb',
        gray300: '#d1d5db',
        
        radiusMd: '6px',
        
        shadowXs: '0 1px 2px rgba(0, 0, 0, 0.05)',
        shadowSm: '0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06)',
        shadowLg: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
        
        transitionNormal: '200ms ease'
    };

    // Altura fija de cada fila del grid
    const ROW_HEIGHT = 40;
    
    // Mapa de hosts con cards expandidas
    const hostsConExpandidas = new Set();
    
    // Estado global: solo una card expandida a la vez
    let currentlyExpandedCard = null;
    
    // Cache de datos
    let citasCache = null;
    let overlapsCache = null;
    let slotRowIndexCache = null;
    let timeSlotsCache = null;

    // =============================================
    // DEBUG HELPERS - Overflow diagnostics
    // =============================================
    
    /**
     * Snapshot de overflow para diagnosticar recortes de cards expandidas
     * @param {string} label - Etiqueta del snapshot (ej: 'OPEN', 'CLOSE')
     * @param {HTMLElement} card - Card a inspeccionar
     */
    function debugOverflowSnapshot(label, card) {
        // Solo si el flag de debug está activo
        if (!window.AA_DEBUG_CALENDAR_OVERFLOW) return;
        
        console.groupCollapsed(`[OverflowDebug] ${label} @ ${new Date().toISOString().split('T')[1].slice(0, -1)}`);
        
        try {
            // Seleccionar elementos clave
            const section = document.querySelector('.aa-day-timeline');
            const overlay = document.getElementById('aa-expanded-cards-overlay');
            const grid = document.getElementById('aa-time-grid');
            
            // Obtener header y body de la card
            const header = card ? card.querySelector('.aa-appointment-header') : null;
            const body = card ? (card.querySelector('.aa-appointment-body:not([hidden])') || card.querySelector('.aa-appointment-body')) : null;
            
            // Obtener rects
            const sectionRect = section ? section.getBoundingClientRect() : null;
            const cardRect = card ? card.getBoundingClientRect() : null;
            const headerRect = header ? header.getBoundingClientRect() : null;
            const bodyRect = body ? body.getBoundingClientRect() : null;
            const overlayRect = overlay ? overlay.getBoundingClientRect() : null;
            const gridRect = grid ? grid.getBoundingClientRect() : null;
            
            // Calcular el bottom real del contenido (max entre card y body)
            const contentBottom = Math.max(
                cardRect?.bottom ?? -Infinity,
                bodyRect?.bottom ?? -Infinity
            );
            
            // Calcular overflow vs section
            const overflowVsSection = (sectionRect && contentBottom > -Infinity) 
                ? contentBottom - sectionRect.bottom 
                : null;
            
            // Warning si body no existe o está hidden
            const bodyVisible = body && !body.hasAttribute('hidden');
            if (!body) {
                console.warn('⚠️  .aa-appointment-body NO encontrado en la card');
            } else if (!bodyVisible) {
                console.warn('⚠️  .aa-appointment-body encontrado pero está [hidden]');
            }
            
            // Datos de la card
            const cardData = card ? {
                citaStartRow: card.dataset.citaStartRow,
                citaBloquesOcupados: card.dataset.citaBloquesOcupados,
                expanded: card.dataset.expanded,
                hasClass_expanded: card.classList.contains('aa-expanded-in-overlay')
            } : null;
            
            // Estilos computados del section
            const sectionStyles = section ? {
                paddingBottom: getComputedStyle(section).paddingBottom,
                marginBottom: getComputedStyle(section).marginBottom,
                overflow: getComputedStyle(section).overflow,
                overflowY: getComputedStyle(section).overflowY
            } : null;
            
            // Heights
            const heights = {
                documentScrollHeight: document.documentElement.scrollHeight,
                documentClientHeight: document.documentElement.clientHeight,
                gridScrollHeight: grid ? grid.scrollHeight : null,
                overlayScrollHeight: overlay ? overlay.scrollHeight : null,
                sectionScrollHeight: section ? section.scrollHeight : null
            };
            
            // Detectar primer ancestro recortador
            let clipper = null;
            let clipperRect = null;
            if (card) {
                let el = card.parentElement;
                while (el && el !== document.body) {
                    const computed = getComputedStyle(el);
                    const ovf = computed.overflow;
                    const ovfY = computed.overflowY;
                    
                    if (ovf !== 'visible' && ovfY !== 'visible') {
                        const elRect = el.getBoundingClientRect();
                        clipperRect = elRect;
                        clipper = {
                            element: `${el.tagName}.${el.className || '(no-class)'}`,
                            id: el.id || '(no-id)',
                            overflow: ovf,
                            overflowY: ovfY,
                            rectBottom: elRect.bottom,
                            rectHeight: elRect.height
                        };
                        break;
                    }
                    el = el.parentElement;
                }
            }
            
            // Calcular overflow vs clipper
            const overflowVsClipper = (clipperRect && contentBottom > -Infinity)
                ? contentBottom - clipperRect.bottom
                : null;
            
            // Log estructurado
            console.log('📍 Label:', label);
            console.log('📊 Card data:', cardData);
            console.log('');
            console.log('📐 Bottoms (Y positions):');
            console.log('  - Header rect.bottom:', headerRect?.bottom?.toFixed(2) ?? 'N/A');
            console.log('  - Body rect.bottom:', bodyRect?.bottom?.toFixed(2) ?? 'N/A');
            console.log('  - Card rect.bottom:', cardRect?.bottom?.toFixed(2) ?? 'N/A');
            console.log('  - Section rect.bottom:', sectionRect?.bottom?.toFixed(2) ?? 'N/A');
            console.log('  - Content bottom (real):', contentBottom > -Infinity ? contentBottom.toFixed(2) : 'N/A');
            console.log('');
            console.log('⚠️  Overflow vs Section:', overflowVsSection !== null ? `${overflowVsSection.toFixed(2)}px` : 'N/A');
            if (overflowVsClipper !== null) {
                console.log('⚠️  Overflow vs Clipper:', `${overflowVsClipper.toFixed(2)}px`);
            }
            console.log('');
            console.log('🎨 Section styles:', sectionStyles);
            console.log('📏 Heights:', heights);
            console.log('✂️  First clipper ancestor:', clipper || 'none (all visible)');
            console.log('📦 Overlay rect:', {
                top: overlayRect?.top,
                bottom: overlayRect?.bottom,
                height: overlayRect?.height
            });
            console.log('📦 Grid rect:', {
                top: gridRect?.top,
                bottom: gridRect?.bottom,
                height: gridRect?.height
            });
            
            // Si hay overflow, resaltar
            if (overflowVsSection && overflowVsSection > 0) {
                console.warn(`🚨 OVERFLOW DETECTADO: ${overflowVsSection.toFixed(2)}px por debajo del section`);
            }
            if (overflowVsClipper && overflowVsClipper > 0) {
                console.warn(`🚨 OVERFLOW vs CLIPPER: ${overflowVsClipper.toFixed(2)}px por debajo del elemento recortador`);
            }
            
        } catch (err) {
            console.error('Error en debugOverflowSnapshot:', err);
        }
        
        console.groupEnd();
    }

    // =============================================
    // TIMELINE PADDING HELPERS - Fix para cards expandidas
    // =============================================
    
    /**
     * Obtener el section del timeline
     * @returns {HTMLElement|null}
     */
    function getTimelineSection() {
        return document.querySelector('.aa-day-timeline');
    }
    
    /**
     * Resetear el padding-bottom del section a 0
     */
    function resetTimelinePadding() {
        const section = getTimelineSection();
        if (section) {
            section.style.paddingBottom = '0px';
        }
    }
    
    /**
     * Aplicar padding-bottom dinámico al section para evitar recorte de card expandida
     * @param {HTMLElement} card - Card expandida
     */
    function applyTimelinePaddingForExpandedCard(card) {
        const section = getTimelineSection();
        if (!section || !card) return;
        
        const sectionRect = section.getBoundingClientRect();
        
        // Obtener body de la card (el contenido real)
        const body = card.querySelector('.aa-appointment-body:not([hidden])') || card.querySelector('.aa-appointment-body');
        const cardRect = card.getBoundingClientRect();
        const bodyRect = body ? body.getBoundingClientRect() : null;
        
        // Calcular el bottom real del contenido
        const contentBottom = Math.max(
            cardRect.bottom,
            bodyRect?.bottom ?? -Infinity
        );
        
        // Calcular overflow vs section
        const overflow = contentBottom - sectionRect.bottom;
        
        if (overflow > 0) {
            // Agregar padding extra para dar "piso" (+2px colchón)
            const extraPx = Math.ceil(overflow) + 2;
            section.style.paddingBottom = extraPx + 'px';
            
            // Debug opcional
            if (window.AA_DEBUG_CALENDAR_OVERFLOW) {
                console.log(`[TimelinePadding] Applied ${extraPx}px padding-bottom to section (overflow: ${overflow.toFixed(2)}px)`);
            }
        } else {
            section.style.paddingBottom = '0px';
            
            if (window.AA_DEBUG_CALENDAR_OVERFLOW) {
                console.log('[TimelinePadding] No overflow detected, padding reset to 0px');
            }
        }
    }

    /**
     * Cargar citas de un día específico y renderizarlas
     */
    function cargarYRenderizarCitas(slotRowIndex, timeSlots, fechaStr) {
        if (!fechaStr) {
            const today = new Date();
            fechaStr = window.DateUtils.ymd(today);
        }
        
        const formData = new FormData();
        formData.append('action', 'aa_get_citas_por_dia');
        formData.append('fecha', fechaStr);
        
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
                console.log('[CalendarAppointments] Citas recibidas:', data.data.citas.length);
                data.data.citas.forEach(cita => {
                    console.log('[CalendarAppointments] Cita procesada:', {
                        id: cita.id,
                        nombre: cita.nombre,
                        servicio: cita.servicio,
                        duracion: cita.duracion,
                        assignment_id: cita.assignment_id,
                        estado: cita.estado,
                        fecha: cita.fecha
                    });
                });
                
                const citasConPosicion = [];
                
                data.data.citas.forEach(cita => {
                    const posicion = window.AdminCalendarService?.calcularPosicionCita(cita);
                    if (!posicion) return;
                    
                    const { slotInicio, bloquesOcupados } = posicion;
                    const slotData = slotRowIndex.get(slotInicio);
                    if (!slotData) return;
                    const startRow = slotData.rowIndex;
                    
                    citasConPosicion.push({
                        cita: cita,
                        id: cita.id,
                        startRow: startRow,
                        bloquesOcupados: bloquesOcupados,
                        slotInicio: slotInicio
                    });
                });
                
                const overlaps = window.CalendarOverlap?.computeOverlaps(citasConPosicion) || {};
                
                citasCache = citasConPosicion;
                overlapsCache = overlaps;
                slotRowIndexCache = slotRowIndex;
                timeSlotsCache = timeSlots;
                
                currentlyExpandedCard = null;
                
                // Reset padding al cargar nuevas citas
                resetTimelinePadding();
                
                renderizarTodasLasCitas(citasConPosicion, overlaps, slotRowIndex, timeSlots);
            }
        })
        .catch(err => {
            console.error('Error al cargar citas:', err);
        });
    }

    /**
     * Renderizar todas las citas
     */
    function renderizarTodasLasCitas(citasConPosicion, overlaps, slotRowIndex, timeSlots) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Restaurar overflow de hosts
        hostsConExpandidas.forEach(host => {
            if (host) {
                host.style.overflow = host.dataset.overflowPrev || 'hidden';
                host.style.zIndex = host.dataset.zIndexPrev || '5';
                delete host.dataset.overflowPrev;
                delete host.dataset.zIndexPrev;
            }
        });
        hostsConExpandidas.clear();
        
        if (window.CalendarAssignments?.clearHosts) {
            window.CalendarAssignments.clearHosts();
        }
        
        const elementosAEliminar = [];
        grid.childNodes.forEach(node => {
            if (node.nodeType === 1) {
                if (node.classList && node.classList.contains('aa-appointment-card')) {
                    elementosAEliminar.push(node);
                }
            }
        });
        elementosAEliminar.forEach(el => el.remove());
        
        // Agrupar citas por solapamiento
        const gruposSolapamiento = [];
        const citasSinGrupo = [];
        const procesadas = new Set();
        
        citasConPosicion.forEach((citaConPos, index) => {
            if (procesadas.has(index)) return;
            
            const overlapInfo = overlaps[citaConPos.id];
            if (overlapInfo && overlapInfo.overlapCount > 1) {
                const grupo = [];
                const porProcesar = [index];
                
                while (porProcesar.length > 0) {
                    const idxActual = porProcesar.pop();
                    if (procesadas.has(idxActual)) continue;
                    
                    procesadas.add(idxActual);
                    const citaActual = citasConPosicion[idxActual];
                    grupo.push(citaActual);
                    
                    citasConPosicion.forEach((otraCita, otroIndex) => {
                        if (procesadas.has(otroIndex)) return;
                        
                        const otraOverlap = overlaps[otraCita.id];
                        if (otraOverlap && otraOverlap.overlapCount > 1) {
                            const startRow1 = citaActual.startRow;
                            const endRow1 = startRow1 + citaActual.bloquesOcupados;
                            const startRow2 = otraCita.startRow;
                            const endRow2 = startRow2 + otraCita.bloquesOcupados;
                            
                            if (startRow1 < endRow2 && startRow2 < endRow1) {
                                porProcesar.push(otroIndex);
                            }
                        }
                    });
                }
                
                if (grupo.length > 0) {
                    gruposSolapamiento.push(grupo);
                }
            } else {
                procesadas.add(index);
                citasSinGrupo.push(citaConPos);
            }
        });
        
        gruposSolapamiento.forEach((grupo) => {
            let minStartRow = Infinity;
            let maxEndRow = -Infinity;
            grupo.forEach(c => {
                minStartRow = Math.min(minStartRow, c.startRow);
                maxEndRow = Math.max(maxEndRow, c.startRow + c.bloquesOcupados);
            });
            const groupKey = `${minStartRow}-${maxEndRow}`;
            renderizarGrupoCitas(grupo, overlaps, slotRowIndex, timeSlots, groupKey);
        });
        
        citasSinGrupo.forEach((citaConPos) => {
            const groupKey = `${citaConPos.startRow}-${citaConPos.startRow + citaConPos.bloquesOcupados}`;
            renderizarGrupoCitas([citaConPos], overlaps, slotRowIndex, timeSlots, groupKey);
        });
        
        crearControlesCicladoStack();
    }
    
    /**
     * Crear controles de ciclado para cards superpuestas
     */
    function crearControlesCicladoStack() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        let controlsContainer = grid.querySelector('.aa-stack-controls-container');
        if (controlsContainer) {
            controlsContainer.remove();
        }
        
        const hosts = grid.querySelectorAll('.aa-overlay-cards-host');
        hosts.forEach(host => procesarCardsEnHost(host));
    }
    
    /**
     * Procesar cards en un host
     */
    function procesarCardsEnHost(host) {
        const cards = Array.from(host.querySelectorAll('.aa-appointment-card'));
        if (cards.length < 2) return;
        
        const cardsInfo = cards.map(card => {
            const gridRowStyle = card.style.gridRow || '';
            let startRow = 1, span = 1;
            
            const matchSpan = gridRowStyle.match(/^(\d+)\s*\/\s*span\s+(\d+)/);
            if (matchSpan) {
                startRow = parseInt(matchSpan[1], 10);
                span = parseInt(matchSpan[2], 10);
            } else {
                const match = gridRowStyle.match(/^(\d+)/);
                if (match) startRow = parseInt(match[1], 10);
                const bloques = parseInt(card.dataset.citaBloquesOcupados, 10);
                if (!isNaN(bloques)) span = bloques;
            }
            
            return { card, startRow, endRow: startRow + span };
        });
        
        // Union-Find para detectar grupos
        const n = cardsInfo.length;
        const parent = Array.from({length: n}, (_, i) => i);
        
        const find = (i) => parent[i] === i ? i : (parent[i] = find(parent[i]));
        const union = (i, j) => { parent[find(i)] = find(j); };
        
        for (let i = 0; i < n; i++) {
            for (let j = i + 1; j < n; j++) {
                if (cardsInfo[i].startRow < cardsInfo[j].endRow && 
                    cardsInfo[j].startRow < cardsInfo[i].endRow) {
                    union(i, j);
                }
            }
        }
        
        const grupos = {};
        for (let i = 0; i < n; i++) {
            const root = find(i);
            if (!grupos[root]) grupos[root] = [];
            grupos[root].push(cardsInfo[i]);
        }
        
        Object.values(grupos).forEach(grupo => {
            if (grupo.length > 1) crearControlUnico(host, grupo);
        });
    }
    
    /**
     * Crear control de ciclado para un grupo
     */
    function crearControlUnico(host, grupo) {
        grupo.sort((a, b) => {
            if (a.startRow !== b.startRow) return a.startRow - b.startRow;
            const aConfirmed = a.card.dataset.citaEstado === 'confirmed' ? 1 : 0;
            const bConfirmed = b.card.dataset.citaEstado === 'confirmed' ? 1 : 0;
            return aConfirmed - bConfirmed;
        });
        
        let currentIndex = grupo.length - 1;
        const confirmedIdx = grupo.findIndex(info => info.card.dataset.citaEstado === 'confirmed');
        if (confirmedIdx !== -1) {
            currentIndex = confirmedIdx;
        }
        
        // Opacidad para cards apiladas
        const FRONT_OPACITY = '1';
        const BACK_OPACITY = '0.5';
        
        grupo.forEach((info, idx) => {
            const isFront = idx === currentIndex;
            info.card.style.zIndex = isFront ? '39' : String(20 + idx);
            info.card.style.position = 'relative';
            // Opacidad: card frontal completa, cards traseras semi-transparentes
            info.card.style.opacity = isFront ? FRONT_OPACITY : BACK_OPACITY;
        });
        
        // =============================================
        // CONTROL STYLING - Diseño premium
        // Colocado como hijo del host (no de la card) y z-index > ceja (45)
        // para que sea clickeable por encima de la ceja sin cambiar z de ceja/cards
        // =============================================
        const control = document.createElement('div');
        control.className = 'aa-slot-stack-control';
        control.innerHTML = '⟳';
        control.title = `Ciclar entre ${grupo.length} citas superpuestas`;
        
        function posicionarControlSobreCardFrontal() {
            const frontInfo = grupo[currentIndex];
            const topPx = (frontInfo.startRow - 1) * ROW_HEIGHT + 6;
            control.style.top = topPx + 'px';
            control.style.right = '6px';
        }
        
        Object.assign(control.style, {
            position: 'absolute',
            top: '6px',
            right: '6px',
            zIndex: '50',
            backgroundColor: 'rgba(55, 65, 81, 0.9)',
            color: '#fff',
            width: '22px',
            height: '22px',
            borderRadius: '6px',
            fontSize: '12px',
            fontWeight: '600',
            cursor: 'pointer',
            userSelect: 'none',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: TOKENS.shadowSm,
            transition: `all ${TOKENS.transitionNormal}`,
            border: '1px solid rgba(255, 255, 255, 0.1)'
        });
        posicionarControlSobreCardFrontal();
        
        control.addEventListener('mouseenter', () => {
            control.style.backgroundColor = 'rgba(59, 130, 246, 0.95)';
            control.style.transform = 'rotate(180deg) scale(1.05)';
        });
        control.addEventListener('mouseleave', () => {
            control.style.backgroundColor = 'rgba(55, 65, 81, 0.9)';
            control.style.transform = 'rotate(0deg) scale(1)';
        });
        
        control.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            
            currentIndex = (currentIndex + 1) % grupo.length;
            
            grupo.forEach((info, idx) => {
                const isFront = idx === currentIndex;
                info.card.style.zIndex = isFront ? '39' : String(20 + idx);
                info.card.style.opacity = isFront ? FRONT_OPACITY : BACK_OPACITY;
            });
            
            posicionarControlSobreCardFrontal();
        });
        
        host.appendChild(control);
    }
    
    /**
     * Renderizar un grupo de citas
     */
    function renderizarGrupoCitas(grupo, overlaps, slotRowIndex, timeSlots, groupKey) {
        if (grupo.length === 0) return;
        
        grupo.forEach((citaConPos, index) => {
            const overlapInfo = overlaps[citaConPos.id];
            renderizarCitaEnHost(citaConPos, overlaps, false, overlapInfo, index);
        });
    }
    
    /**
     * Renderizar una cita dentro de su host
     */
    function renderizarCitaEnHost(citaConPos, overlaps, isExpanded, overlapInfo, overlapIndex) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        const host = window.CalendarAssignments?.getCardsHostForCita(citaConPos.cita, citaConPos);
        
        const card = crearCardConInteraccion(citaConPos.cita, overlapInfo || null, isExpanded, host);
        if (!card) return;
        
        card.dataset.citaStartRow = citaConPos.startRow;
        card.dataset.citaBloquesOcupados = citaConPos.bloquesOcupados;
        card.dataset.citaSlotInicio = citaConPos.slotInicio;
        card.dataset.citaEstado = citaConPos.cita.estado || 'pending';
        
        if (host) {
            const hostStartRow = parseInt(host.getAttribute('data-start-row'), 10);
            const relativeStart = citaConPos.startRow - hostStartRow + 1;
            
            card.dataset.originalHost = 'true';
            card.dataset.originalGridColumn = '1';
            card.dataset.originalGridRow = relativeStart + ' / span ' + citaConPos.bloquesOcupados;
            card.__aaHostRef = host;
            card.dataset.citaStartRow = String(citaConPos.startRow);
            card.dataset.citaBloquesOcupados = String(citaConPos.bloquesOcupados);
            
            Object.assign(card.style, {
                gridColumn: '1',
                gridRow: relativeStart + ' / span ' + citaConPos.bloquesOcupados,
                width: '100%',
                position: 'relative',
                overflow: 'hidden',
                minHeight: '0',
                transition: `box-shadow ${TOKENS.transitionNormal}, border-color ${TOKENS.transitionNormal}`
            });
            
            if (isExpanded) {
                if (!host.dataset.overflowPrev) {
                    host.dataset.overflowPrev = host.style.overflow || 'hidden';
                    host.dataset.zIndexPrev = host.style.zIndex || '5';
                }
                host.style.overflow = 'visible';
                host.style.zIndex = '70';
                hostsConExpandidas.add(host);
                
                card.style.zIndex = '80';
                card.style.overflow = 'visible';
                card.style.boxShadow = TOKENS.shadowLg;
                
                const body = card.querySelector('.aa-appointment-body');
                const header = card.querySelector('.aa-appointment-header');
                if (body) {
                    body.removeAttribute('hidden');
                    if (header) {
                        header.style.flex = '0 0 auto';
                        header.style.flexShrink = '0';
                        body.style.flex = '1';
                        header.style.borderBottomLeftRadius = '0';
                        header.style.borderBottomRightRadius = '0';
                    }
                }
            } else {
                if (overlapInfo && overlapInfo.overlapCount > 1) {
                    card.style.zIndex = String(20 + (overlapIndex || 0));
                } else {
                    card.style.zIndex = '20';
                }
            }
            
            host.appendChild(card);
            
        } else {
            console.warn('[CalendarAppointments] No host found for cita:', citaConPos.cita.id, '- using fallback grid positioning');
            
            Object.assign(card.style, {
                gridColumn: '2',
                gridRow: citaConPos.startRow + ' / span ' + citaConPos.bloquesOcupados,
                position: 'relative',
                overflow: isExpanded ? 'visible' : 'hidden',
                minHeight: '0',
                zIndex: isExpanded ? '30' : '20'
            });
            
            if (isExpanded) {
                card.style.boxShadow = TOKENS.shadowLg;
                const body = card.querySelector('.aa-appointment-body');
                const header = card.querySelector('.aa-appointment-header');
                if (body) {
                    body.removeAttribute('hidden');
                    if (header) {
                        header.style.flex = '0 0 auto';
                        header.style.flexShrink = '0';
                        body.style.flex = '1';
                        header.style.borderBottomLeftRadius = '0';
                        header.style.borderBottomRightRadius = '0';
                    }
                }
            }
            
            grid.appendChild(card);
        }
    }
    
    /**
     * Restaurar overflow de un host
     */
    function restaurarHostOverflow(host) {
        if (!host) return;
        
        const cardsExpandidas = host.querySelectorAll('.aa-appointment-card[data-expanded="true"]');
        if (cardsExpandidas.length === 0) {
            host.style.overflow = host.dataset.overflowPrev || 'hidden';
            host.style.zIndex = host.dataset.zIndexPrev || '5';
            delete host.dataset.overflowPrev;
            delete host.dataset.zIndexPrev;
            hostsConExpandidas.delete(host);
        }
    }
    
    /**
     * Colapsar una card
     */
    function colapsarCard(cardToCollapse) {
        if (!cardToCollapse) return;
        
        const body = cardToCollapse.querySelector('.aa-appointment-body');
        const header = cardToCollapse.querySelector('.aa-appointment-header');
        if (!body || !header) return;
        
        body.setAttribute('hidden', '');
        
        header.style.flex = '1';
        header.style.flexShrink = '0';
        body.style.flex = '0';
        header.style.borderBottomLeftRadius = '0';
        header.style.borderBottomRightRadius = TOKENS.radiusMd;
        cardToCollapse.style.overflow = 'hidden';
        cardToCollapse.dataset.expanded = 'false';
        cardToCollapse.style.boxShadow = TOKENS.shadowXs;
        
        const overlapIndex = cardToCollapse.dataset.overlapIndex;
        if (overlapIndex !== undefined) {
            cardToCollapse.style.zIndex = String(20 + parseInt(overlapIndex, 10));
        } else {
            cardToCollapse.style.zIndex = '20';
        }
        
        const cardHost = cardToCollapse.closest('.aa-overlay-cards-host');
        if (cardHost) {
            restaurarHostOverflow(cardHost);
        }
        
        if (cardToCollapse.classList.contains('aa-expanded-in-overlay')) {
            colapsarDeOverlay(cardToCollapse);
        }
        
        setTimeout(() => {
            crearControlesCicladoStack();
        }, 0);
    }
    
    /**
     * Mover card al overlay global
     */
    function expandirEnOverlay(card) {
        const overlay = document.getElementById('aa-expanded-cards-overlay');
        if (!overlay) {
            console.warn('[CalendarAppointments] No se encontró #aa-expanded-cards-overlay');
            return;
        }
        
        if (card.classList.contains('aa-expanded-in-overlay')) return;
        
        const startRow = parseInt(card.dataset.citaStartRow, 10);
        const spans = parseInt(card.dataset.citaBloquesOcupados, 10);
        
        if (isNaN(startRow) || isNaN(spans)) {
            console.warn('[CalendarAppointments] Datos de posición inválidos para expandirEnOverlay');
            return;
        }
        
        const top = (startRow - 1) * ROW_HEIGHT;
        const height = spans * ROW_HEIGHT;
        
        overlay.appendChild(card);
        
        Object.assign(card.style, {
            position: 'absolute',
            top: top + 'px',
            left: '0px',
            width: '100%',
            height: height + 'px',
            pointerEvents: 'auto',
            zIndex: '300',
            gridColumn: '',
            gridRow: '',
            boxShadow: TOKENS.shadowLg,
            borderColor: TOKENS.gray300,
            opacity: '1' // Card expandida siempre opacidad completa
        });
        card.classList.add('aa-expanded-in-overlay');
    }
    
    /**
     * Devolver card del overlay al host
     */
    function colapsarDeOverlay(card) {
        if (!card.classList.contains('aa-expanded-in-overlay')) return;
        
        const host = card.__aaHostRef;
        
        if (!host) {
            console.warn('[CalendarAppointments] No se encontró referencia al host original para colapsar card');
            return;
        }
        
        host.appendChild(card);
        
        Object.assign(card.style, {
            position: 'relative',
            top: '',
            left: '',
            height: '',
            width: '100%',
            pointerEvents: '',
            gridColumn: card.dataset.originalGridColumn || '1',
            gridRow: card.dataset.originalGridRow || '',
            boxShadow: TOKENS.shadowXs,
            borderColor: TOKENS.gray200
        });
        card.classList.remove('aa-expanded-in-overlay');
    }
    
    /**
     * Crear card con interacción
     */
    function crearCardConInteraccion(cita, overlapInfo, isExpanded, host) {
        if (!cita.fecha) return null;
        
        const card = window.CalendarAppointmentCard?.crearCardCita(cita);
        if (!card) return null;
        
        if (overlapInfo && overlapInfo.overlapCount > 1) {
            card.dataset.overlapIndex = String(overlapInfo.overlapIndex || 0);
        }
        
        if (isExpanded) {
            card.dataset.expanded = 'true';
        }
        
        const header = card.querySelector('.aa-appointment-header');
        const body = card.querySelector('.aa-appointment-body');
        
        if (header && body) {
            const nuevoHeader = header.cloneNode(true);
            header.parentNode.replaceChild(nuevoHeader, header);
            
            function actualizarEstilosCard() {
                const currentHeader = card.querySelector('.aa-appointment-header');
                const currentBody = card.querySelector('.aa-appointment-body');
                if (!currentHeader || !currentBody) return;
                
                const isHidden = currentBody.hasAttribute('hidden');
                const cardHost = card.closest('.aa-overlay-cards-host');
                
                if (isHidden) {
                    // === COLAPSADA ===
                    currentHeader.style.flex = '1';
                    currentHeader.style.flexShrink = '0';
                    currentBody.style.flex = '0';
                    card.style.overflow = 'hidden';
                    card.dataset.expanded = 'false';
                    currentHeader.style.borderBottomLeftRadius = '0';
                    currentHeader.style.borderBottomRightRadius = TOKENS.radiusMd;
                    card.style.boxShadow = TOKENS.shadowXs;
                    card.style.borderColor = TOKENS.gray200;
                    
                    if (overlapInfo && overlapInfo.overlapCount > 1) {
                        card.style.zIndex = String(20 + (overlapInfo.overlapIndex || 0));
                    } else {
                        card.style.zIndex = '20';
                    }
                    
                    if (cardHost) {
                        restaurarHostOverflow(cardHost);
                    }
                } else {
                    // === EXPANDIDA ===
                    currentHeader.style.flex = '0 0 auto';
                    currentHeader.style.flexShrink = '0';
                    currentBody.style.flex = '1';
                    card.style.overflow = 'visible';
                    card.style.zIndex = '80';
                    card.dataset.expanded = 'true';
                    currentHeader.style.borderBottomLeftRadius = '0';
                    currentHeader.style.borderBottomRightRadius = '0';
                    card.style.boxShadow = TOKENS.shadowLg;
                    card.style.borderColor = TOKENS.gray300;
                    // Card expandida siempre tiene opacidad completa
                    card.style.opacity = '1';
                    
                    if (cardHost) {
                        if (!cardHost.dataset.overflowPrev) {
                            cardHost.dataset.overflowPrev = cardHost.style.overflow || 'hidden';
                            cardHost.dataset.zIndexPrev = cardHost.style.zIndex || '5';
                        }
                        cardHost.style.overflow = 'visible';
                        cardHost.style.zIndex = '70';
                        hostsConExpandidas.add(cardHost);
                    }
                }
            }
            
            nuevoHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                
                const currentBody = card.querySelector('.aa-appointment-body');
                if (!currentBody) return;
                
                const isHidden = currentBody.hasAttribute('hidden');
                
                if (isHidden) {
                    // === ABRIR ===
                    if (currentlyExpandedCard && currentlyExpandedCard !== card) {
                        colapsarCard(currentlyExpandedCard);
                        currentlyExpandedCard = null;
                        
                        // Reset padding al colapsar la anterior
                        resetTimelinePadding();
                        window.AAAdmin?.iframeResize?.();
                    }
                    
                    currentBody.removeAttribute('hidden');
                    actualizarEstilosCard();
                    expandirEnOverlay(card);
                    currentlyExpandedCard = card;
                    
                    // DEBUG: Medir después de pintar (2 frames para asegurar layout completo)
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            debugOverflowSnapshot('OPEN', card);
                            
                            // Aplicar padding dinámico para evitar recorte
                            applyTimelinePaddingForExpandedCard(card);
                            window.AAAdmin?.iframeResize?.();
                        });
                    });
                } else {
                    // === CERRAR ===
                    colapsarDeOverlay(card);
                    currentBody.setAttribute('hidden', '');
                    actualizarEstilosCard();
                    
                    if (currentlyExpandedCard === card) {
                        currentlyExpandedCard = null;
                    }
                    
                    // DEBUG: Medir después de colapsar
                    requestAnimationFrame(() => {
                        debugOverflowSnapshot('CLOSE', card);
                        
                        // Reset padding al cerrar
                        resetTimelinePadding();
                        window.AAAdmin?.iframeResize?.();
                    });
                    
                    setTimeout(() => {
                        crearControlesCicladoStack();
                    }, 0);
                }
            });
            
            actualizarEstilosCard();
        }
        
        return card;
    }

    // Exponer API pública
    window.CalendarAppointments = {
        cargarYRenderizarCitas: cargarYRenderizarCitas,
        /** Colapsa la card de cita expandida si hay alguna (p. ej. al abrir el sidebar). */
        colapsarCardExpandida: function() {
            if (currentlyExpandedCard) {
                colapsarCard(currentlyExpandedCard);
                currentlyExpandedCard = null;
                
                // Reset padding al colapsar programáticamente
                resetTimelinePadding();
                window.AAAdmin?.iframeResize?.();
            }
        }
    };

})();
