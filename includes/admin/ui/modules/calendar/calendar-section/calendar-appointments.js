/**
 * Calendar Appointments - Load and render appointments
 */

(function() {
    'use strict';

    // Altura fija de cada fila del grid (debe coincidir con calendar-timeline.js)
    const ROW_HEIGHT = 40;
    
    // Mapa de hosts con cards expandidas (para manejar overflow)
    const hostsConExpandidas = new Set();
    
    // Estado global: solo una card expandida a la vez (UX guardrail)
    let currentlyExpandedCard = null;
    
    // Cache de datos de citas para re-renderizado
    let citasCache = null;
    let overlapsCache = null;
    let slotRowIndexCache = null;
    let timeSlotsCache = null;

    /**
     * Cargar citas de un día específico y renderizarlas en el timeline
     * @param {Map} slotRowIndex - Mapa de slots
     * @param {Array} timeSlots - Array de time slots
     * @param {string} fechaStr - Fecha en formato YYYY-MM-DD (opcional, usa hoy por defecto)
     */
    function cargarYRenderizarCitas(slotRowIndex, timeSlots, fechaStr) {
        // Si no se proporciona fecha, usar hoy
        if (!fechaStr) {
            const today = new Date();
            fechaStr = window.DateUtils.ymd(today);
        }
        
        // Preparar datos para la petición AJAX
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
                // Log de citas recibidas desde el servidor
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
                
                // Calcular posiciones de TODAS las citas primero
                const citasConPosicion = [];
                
                data.data.citas.forEach(cita => {
                    // Usar el service para calcular posición
                    const posicion = window.AdminCalendarService?.calcularPosicionCita(cita);
                    if (!posicion) return;
                    
                    const { slotInicio, bloquesOcupados } = posicion;
                    
                    // Obtener el índice de fila del slot inicial
                    const slotData = slotRowIndex.get(slotInicio);
                    if (!slotData) return; // Slot no encontrado
                    const startRow = slotData.rowIndex;
                    
                    citasConPosicion.push({
                        cita: cita,
                        id: cita.id,
                        startRow: startRow,
                        bloquesOcupados: bloquesOcupados,
                        slotInicio: slotInicio
                    });
                });
                
                // Calcular solapamientos usando CalendarOverlap
                const overlaps = window.CalendarOverlap?.computeOverlaps(citasConPosicion) || {};
                
                // Guardar en cache para re-renderizado
                citasCache = citasConPosicion;
                overlapsCache = overlaps;
                slotRowIndexCache = slotRowIndex;
                timeSlotsCache = timeSlots;
                
                // Resetear estado de card expandida al cargar nuevas citas
                currentlyExpandedCard = null;
                
                // Renderizar todas las citas
                renderizarTodasLasCitas(citasConPosicion, overlaps, slotRowIndex, timeSlots);
            }
        })
        .catch(err => {
            console.error('Error al cargar citas:', err);
        });
    }

    /**
     * Renderizar todas las citas con manejo de estado expandido
     */
    function renderizarTodasLasCitas(citasConPosicion, overlaps, slotRowIndex, timeSlots) {
        // Limpiar citas existentes del grid (solo las cards, no los elementos del timeline)
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Restaurar overflow de todos los hosts que tenían cards expandidas
        hostsConExpandidas.forEach(host => {
            if (host) {
                host.style.overflow = host.dataset.overflowPrev || 'hidden';
                host.style.zIndex = host.dataset.zIndexPrev || '5';
                delete host.dataset.overflowPrev;
                delete host.dataset.zIndexPrev;
            }
        });
        hostsConExpandidas.clear();
        
        // Clear all cards from hosts first
        if (window.CalendarAssignments?.clearHosts) {
            window.CalendarAssignments.clearHosts();
        }
        
        // Eliminar solo los wrappers y cards directos del grid (fallback cards no dentro de hosts)
        const elementosAEliminar = [];
        grid.childNodes.forEach(node => {
            if (node.nodeType === 1) {
                // Si es una card directa en el grid (no dentro de un host)
                if (node.classList && (
                    node.classList.contains('aa-appointment-card')
                )) {
                    elementosAEliminar.push(node);
                }
            }
        });
        elementosAEliminar.forEach(el => el.remove());
        
        // Agrupar citas por grupos de solapamiento real
        // Usar un algoritmo de componentes conectados basado en overlaps
        const gruposSolapamiento = [];
        const citasSinGrupo = [];
        const procesadas = new Set();
        
        citasConPosicion.forEach((citaConPos, index) => {
            if (procesadas.has(index)) return;
            
            const overlapInfo = overlaps[citaConPos.id];
            if (overlapInfo && overlapInfo.overlapCount > 1) {
                // Esta cita está en un grupo de solapamiento
                // Encontrar todas las citas que se solapan con esta (transitivamente)
                const grupo = [];
                const porProcesar = [index];
                
                while (porProcesar.length > 0) {
                    const idxActual = porProcesar.pop();
                    if (procesadas.has(idxActual)) continue;
                    
                    procesadas.add(idxActual);
                    const citaActual = citasConPosicion[idxActual];
                    grupo.push(citaActual);
                    
                    // Buscar otras citas que se solapan con esta
                    citasConPosicion.forEach((otraCita, otroIndex) => {
                        if (procesadas.has(otroIndex)) return;
                        
                        const otraOverlap = overlaps[otraCita.id];
                        if (otraOverlap && otraOverlap.overlapCount > 1) {
                            // Verificar si se solapan en el mismo rango de filas
                            const startRow1 = citaActual.startRow;
                            const endRow1 = startRow1 + citaActual.bloquesOcupados;
                            const startRow2 = otraCita.startRow;
                            const endRow2 = startRow2 + otraCita.bloquesOcupados;
                            
                            // Si se solapan verticalmente
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
                // Cita sin solapamiento
                procesadas.add(index);
                citasSinGrupo.push(citaConPos);
            }
        });
        
        // Renderizar grupos de solapamiento
        gruposSolapamiento.forEach((grupo) => {
            // Calcular key del grupo para comparación
            let minStartRow = Infinity;
            let maxEndRow = -Infinity;
            grupo.forEach(c => {
                minStartRow = Math.min(minStartRow, c.startRow);
                maxEndRow = Math.max(maxEndRow, c.startRow + c.bloquesOcupados);
            });
            const groupKey = `${minStartRow}-${maxEndRow}`;
            
            renderizarGrupoCitas(grupo, overlaps, slotRowIndex, timeSlots, groupKey);
        });
        
        // Renderizar citas sin solapamiento
        citasSinGrupo.forEach((citaConPos) => {
            // Cada cita sin solapamiento es su propio grupo
            const groupKey = `${citaConPos.startRow}-${citaConPos.startRow + citaConPos.bloquesOcupados}`;
            renderizarGrupoCitas([citaConPos], overlaps, slotRowIndex, timeSlots, groupKey);
        });
        
        // Crear controles de ciclado para cards apiladas en el mismo slot
        crearControlesCicladoStack();
    }
    
    /**
     * Crear controles de ciclado para cards superpuestas visualmente
     * UN solo control por grupo de cards superpuestas
     */
    function crearControlesCicladoStack() {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Limpiar contenedor de controles
        let controlsContainer = grid.querySelector('.aa-stack-controls-container');
        if (controlsContainer) {
            controlsContainer.remove();
        }
        
        // Obtener todas las cards en hosts
        const hosts = grid.querySelectorAll('.aa-overlay-cards-host');
        hosts.forEach(host => procesarCardsEnHost(host));
    }
    
    /**
     * Procesar cards en un host y crear UN control por grupo superpuesto
     */
    function procesarCardsEnHost(host) {
        const cards = Array.from(host.querySelectorAll('.aa-appointment-card'));
        if (cards.length < 2) return;
        
        // Extraer posición de cada card
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
        
        // Detectar grupos superpuestos (Union-Find)
        const n = cardsInfo.length;
        const parent = Array.from({length: n}, (_, i) => i);
        
        const find = (i) => parent[i] === i ? i : (parent[i] = find(parent[i]));
        const union = (i, j) => { parent[find(i)] = find(j); };
        
        for (let i = 0; i < n; i++) {
            for (let j = i + 1; j < n; j++) {
                // Se superponen si comparten al menos una fila
                if (cardsInfo[i].startRow < cardsInfo[j].endRow && 
                    cardsInfo[j].startRow < cardsInfo[i].endRow) {
                    union(i, j);
                }
            }
        }
        
        // Agrupar
        const grupos = {};
        for (let i = 0; i < n; i++) {
            const root = find(i);
            if (!grupos[root]) grupos[root] = [];
            grupos[root].push(cardsInfo[i]);
        }
        
        // Crear UN control por grupo con más de 1 card
        Object.values(grupos).forEach(grupo => {
            if (grupo.length > 1) crearControlUnico(host, grupo);
        });
    }
    
    /**
     * Crear UN solo control de ciclado para un grupo de cards superpuestas
     */
    function crearControlUnico(host, grupo) {
        // Ordenar por startRow primero, luego priorizar cards con estado "confirmed" al final (arriba visualmente)
        grupo.sort((a, b) => {
            // Primero por startRow
            if (a.startRow !== b.startRow) {
                return a.startRow - b.startRow;
            }
            // Si mismo startRow, priorizar "confirmed" (va al final = arriba visualmente)
            const aConfirmed = a.card.dataset.citaEstado === 'confirmed' ? 1 : 0;
            const bConfirmed = b.card.dataset.citaEstado === 'confirmed' ? 1 : 0;
            return aConfirmed - bConfirmed; // confirmed cards go last (higher z-index)
        });
        
        // Encontrar el índice de la card con estado confirmed (si existe) para ponerla al frente inicialmente
        let currentIndex = grupo.length - 1; // Por defecto, última al frente
        const confirmedIdx = grupo.findIndex(info => info.card.dataset.citaEstado === 'confirmed');
        if (confirmedIdx !== -1) {
            currentIndex = confirmedIdx;
        }
        
        // Asignar z-index inicial (cards confirmed tienen prioridad visual)
        grupo.forEach((info, idx) => {
            info.card.style.zIndex = idx === currentIndex ? '39' : String(20 + idx);
            info.card.style.position = 'relative';
        });
        
        // Crear control
        const control = document.createElement('div');
        control.className = 'aa-slot-stack-control';
        control.innerHTML = '⟳';
        control.title = `Ciclar entre ${grupo.length} citas superpuestas`;
        
        Object.assign(control.style, {
            position: 'absolute',
            top: '4px',
            right: '4px',
            zIndex: '60',
            backgroundColor: 'rgba(0, 0, 0, 0.75)',
            color: '#fff',
            width: '24px',
            height: '24px',
            borderRadius: '50%',
            fontSize: '14px',
            fontWeight: 'bold',
            cursor: 'pointer',
            userSelect: 'none',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: '0 2px 4px rgba(0,0,0,0.4)',
            transition: 'background-color 0.15s, transform 0.15s'
        });
        
        control.addEventListener('mouseenter', () => {
            control.style.backgroundColor = 'rgba(59, 130, 246, 0.9)';
            control.style.transform = 'rotate(180deg)';
        });
        control.addEventListener('mouseleave', () => {
            control.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';
            control.style.transform = 'rotate(0deg)';
        });
        
        control.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            
            // Rotar al siguiente
            currentIndex = (currentIndex + 1) % grupo.length;
            
            // Actualizar z-index: card activa va a 39, las demás a 20+idx
            grupo.forEach((info, idx) => {
                info.card.style.zIndex = idx === currentIndex ? '39' : String(20 + idx);
            });
            
            // Mover control a la nueva card al frente
            grupo[currentIndex].card.appendChild(control);
        });
        
        // Agregar a la card actualmente al frente
        grupo[currentIndex].card.appendChild(control);
    }
    
    /**
     * Renderizar un grupo de citas (pueden estar solapadas o no)
     * SIMPLIFICADO: Siempre renderiza todas las cards del grupo.
     * El z-index se maneja individualmente en cada card al expandir/colapsar.
     * @param {Array} grupo - Array de citas del grupo
     * @param {Object} overlaps - Mapa de overlaps
     * @param {Map} slotRowIndex - Mapa de slots
     * @param {Array} timeSlots - Array de time slots
     * @param {string} groupKey - Identificador único del grupo (ej: "5-8")
     */
    function renderizarGrupoCitas(grupo, overlaps, slotRowIndex, timeSlots, groupKey) {
        if (grupo.length === 0) return;
        
        // Siempre renderizar todas las cards del grupo
        // El z-index se maneja individualmente en cada card
        grupo.forEach((citaConPos, index) => {
            const overlapInfo = overlaps[citaConPos.id];
            renderizarCitaEnHost(citaConPos, overlaps, false, overlapInfo, index);
        });
    }
    
    /**
     * Renderizar una cita dentro de su host de overlay correspondiente
     * REGLA: La card SIEMPRE vive dentro del host, nunca se mueve al grid.
     * La expansión se logra cambiando overflow del host a 'visible'.
     * 
     * @param {Object} citaConPos - Objeto con cita y posición
     * @param {Object} overlaps - Mapa de overlaps
     * @param {boolean} isExpanded - Si la cita está en modo expandido
     * @param {Object} overlapInfo - Información de solapamiento (opcional)
     * @param {number} overlapIndex - Índice para z-index en caso de solapamiento
     */
    function renderizarCitaEnHost(citaConPos, overlaps, isExpanded, overlapInfo, overlapIndex) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Buscar el host apropiado para esta cita
        const host = window.CalendarAssignments?.getCardsHostForCita(citaConPos.cita, citaConPos);
        
        // Crear la card con interacción
        const card = crearCardConInteraccion(citaConPos.cita, overlapInfo || null, isExpanded, host);
        if (!card) return;
        
        // Store position data in card dataset for use when expanding/collapsing
        card.dataset.citaStartRow = citaConPos.startRow;
        card.dataset.citaBloquesOcupados = citaConPos.bloquesOcupados;
        card.dataset.citaSlotInicio = citaConPos.slotInicio;
        card.dataset.citaEstado = citaConPos.cita.estado || 'pending'; // Para priorizar confirmed en stack
        
        if (host) {
            // ===== SIEMPRE RENDER INSIDE HOST =====
            const hostStartRow = parseInt(host.getAttribute('data-start-row'), 10);
            
            // Calculate relative position within the host's grid
            const relativeStart = citaConPos.startRow - hostStartRow + 1;
            
            // Store host reference para poder regresar la card al colapsar
            card.dataset.originalHost = 'true';
            card.dataset.originalGridColumn = '1';
            card.dataset.originalGridRow = relativeStart + ' / span ' + citaConPos.bloquesOcupados;
            
            // Guardar referencia directa al host (simplifica colapsarDeOverlay)
            card.__aaHostRef = host;
            
            // Guardar posición absoluta para calcular top/height en overlay
            card.dataset.citaStartRow = String(citaConPos.startRow);
            card.dataset.citaBloquesOcupados = String(citaConPos.bloquesOcupados);
            
            // Position card using the host's internal grid
            card.style.gridColumn = '1';
            card.style.gridRow = relativeStart + ' / span ' + citaConPos.bloquesOcupados;
            card.style.width = '100%';
            card.style.position = 'relative';
            card.style.overflow = 'hidden';
            card.style.minHeight = '0'; // Evitar que min-content fuerce altura
            
            if (isExpanded) {
                // EXPANDIDA: host permite overflow, z-index elevado
                if (!host.dataset.overflowPrev) {
                    host.dataset.overflowPrev = host.style.overflow || 'hidden';
                    host.dataset.zIndexPrev = host.style.zIndex || '5';
                }
                host.style.overflow = 'visible';
                host.style.zIndex = '70';
                hostsConExpandidas.add(host);
                
                card.style.zIndex = '80';
                card.style.overflow = 'visible';
                
                // Mostrar body
                const body = card.querySelector('.aa-appointment-body');
                const header = card.querySelector('.aa-appointment-header');
                if (body) {
                    body.removeAttribute('hidden');
                    if (header) {
                        header.style.flex = '0 0 auto';
                        header.style.flexShrink = '0';
                        body.style.flex = '1';
                        // Quitar border-radius de esquinas inferiores cuando body está visible
                        header.style.borderBottomLeftRadius = '0';
                        header.style.borderBottomRightRadius = '0';
                    }
                }
            } else {
                // COLAPSADA: z-index normal, overflow hidden
                if (overlapInfo && overlapInfo.overlapCount > 1) {
                    card.style.zIndex = String(20 + (overlapIndex || 0));
                } else {
                    card.style.zIndex = '20';
                }
            }
            
            // SIEMPRE append al host
            host.appendChild(card);
            
        } else {
            // ===== FALLBACK: RENDER DIRECTLY ON GRID (sin host) =====
            console.warn('[CalendarAppointments] No host found for cita:', citaConPos.cita.id, '- using fallback grid positioning');
            
            card.style.gridColumn = '2';
            card.style.gridRow = citaConPos.startRow + ' / span ' + citaConPos.bloquesOcupados;
            card.style.position = 'relative';
            card.style.overflow = isExpanded ? 'visible' : 'hidden';
            card.style.minHeight = '0';
            card.style.zIndex = isExpanded ? '30' : '20';
            
            // If expanded, show body
            if (isExpanded) {
                const body = card.querySelector('.aa-appointment-body');
                const header = card.querySelector('.aa-appointment-header');
                if (body) {
                    body.removeAttribute('hidden');
                    if (header) {
                        header.style.flex = '0 0 auto';
                        header.style.flexShrink = '0';
                        body.style.flex = '1';
                        // Quitar border-radius de esquinas inferiores cuando body está visible
                        header.style.borderBottomLeftRadius = '0';
                        header.style.borderBottomRightRadius = '0';
                    }
                }
            }
            
            grid.appendChild(card);
        }
    }
    
    /**
     * Restaurar overflow/z-index de un host cuando no tiene cards expandidas
     */
    function restaurarHostOverflow(host) {
        if (!host) return;
        
        // Verificar si aún tiene cards expandidas
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
     * Colapsar una card específica (helper reutilizable)
     * @param {HTMLElement} cardToCollapse - La card a colapsar
     */
    function colapsarCard(cardToCollapse) {
        if (!cardToCollapse) return;
        
        const body = cardToCollapse.querySelector('.aa-appointment-body');
        const header = cardToCollapse.querySelector('.aa-appointment-header');
        if (!body || !header) return;
        
        // Ocultar body
        body.setAttribute('hidden', '');
        
        // Restaurar estilos de colapsada
        header.style.flex = '1';
        header.style.flexShrink = '0';
        body.style.flex = '0';
        // Restaurar border-radius de esquinas inferiores cuando body está oculto
        header.style.borderBottomLeftRadius = '10px';
        header.style.borderBottomRightRadius = '10px';
        cardToCollapse.style.overflow = 'hidden';
        cardToCollapse.dataset.expanded = 'false';
        
        // Restaurar z-index (usar el guardado en dataset o default)
        const overlapIndex = cardToCollapse.dataset.overlapIndex;
        if (overlapIndex !== undefined) {
            cardToCollapse.style.zIndex = String(20 + parseInt(overlapIndex, 10));
        } else {
            cardToCollapse.style.zIndex = '20';
        }
        
        // Restaurar overflow del host
        const cardHost = cardToCollapse.closest('.aa-overlay-cards-host');
        if (cardHost) {
            restaurarHostOverflow(cardHost);
        }
        
        // Si la card estaba en el overlay, devolverla al host
        if (cardToCollapse.classList.contains('aa-expanded-in-overlay')) {
            colapsarDeOverlay(cardToCollapse);
        }
        
        // Recrear controles de stack después de colapsar para restaurar la UI
        setTimeout(() => {
            crearControlesCicladoStack();
        }, 0);
    }
    
    /**
     * Mover card al overlay global para que ocupe todo el ancho de columna 2
     * @param {HTMLElement} card - La card a expandir en overlay
     */
    function expandirEnOverlay(card) {
        const overlay = document.getElementById('aa-expanded-cards-overlay');
        if (!overlay) {
            console.warn('[CalendarAppointments] No se encontró #aa-expanded-cards-overlay');
            return;
        }
        
        // No mover si ya está en overlay
        if (card.classList.contains('aa-expanded-in-overlay')) return;
        
        // Calcular posición usando dataset
        const startRow = parseInt(card.dataset.citaStartRow, 10);
        const spans = parseInt(card.dataset.citaBloquesOcupados, 10);
        
        if (isNaN(startRow) || isNaN(spans)) {
            console.warn('[CalendarAppointments] Datos de posición inválidos para expandirEnOverlay');
            return;
        }
        
        const top = (startRow - 1) * ROW_HEIGHT;
        const height = spans * ROW_HEIGHT;
        
        // Mover card al overlay
        overlay.appendChild(card);
        
        // Aplicar estilos de overlay mode
        card.style.position = 'absolute';
        card.style.top = top + 'px';
        card.style.left = '0px';
        card.style.width = '100%'; // Ocupa toda la columna 2
        card.style.height = height + 'px'; // Altura base (body puede desbordar)
        card.style.pointerEvents = 'auto';
        card.style.zIndex = '300';
        card.style.gridColumn = ''; // Quitar propiedades grid
        card.style.gridRow = '';
        card.classList.add('aa-expanded-in-overlay');
    }
    
    /**
     * Devolver card del overlay al host original
     * @param {HTMLElement} card - La card a devolver al host
     */
    function colapsarDeOverlay(card) {
        if (!card.classList.contains('aa-expanded-in-overlay')) return;
        
        // Usar referencia directa al host (guardada en renderizarCitaEnHost)
        const host = card.__aaHostRef;
        
        if (!host) {
            console.warn('[CalendarAppointments] No se encontró referencia al host original para colapsar card');
            return;
        }
        
        // Mover card de vuelta al host
        host.appendChild(card);
        
        // Restaurar estilos de grid interno
        card.style.position = 'relative';
        card.style.top = '';
        card.style.left = '';
        card.style.height = '';
        card.style.width = '100%';
        card.style.pointerEvents = '';
        card.style.gridColumn = card.dataset.originalGridColumn || '1';
        card.style.gridRow = card.dataset.originalGridRow || '';
        card.classList.remove('aa-expanded-in-overlay');
    }
    
    // renderizarCitaExpandida is now handled by renderizarCitaEnHost with isExpanded=true
    
    /**
     * Crear card con interacción de expandir/colapsar
     * REGLA: La card SIEMPRE permanece dentro de su host.
     * La expansión se logra modificando overflow del host, no moviendo la card.
     * 
     * @param {Object} cita - Objeto de cita
     * @param {Object} overlapInfo - Información de solapamiento
     * @param {boolean} isExpanded - Si la cita está en modo expandido
     * @param {HTMLElement} host - Host contenedor de la card (puede ser null)
     */
    function crearCardConInteraccion(cita, overlapInfo, isExpanded, host) {
        if (!cita.fecha) return null;
        
        // Crear la card usando CalendarAppointmentCard
        const card = window.CalendarAppointmentCard?.crearCardCita(cita);
        if (!card) return null;
        
        // Guardar overlapIndex en dataset para poder restaurarlo al colapsar
        if (overlapInfo && overlapInfo.overlapCount > 1) {
            card.dataset.overlapIndex = String(overlapInfo.overlapIndex || 0);
        }
        
        // Marcar si está expandida inicialmente
        if (isExpanded) {
            card.dataset.expanded = 'true';
        }
        
        // Interceptar el click del header para manejar expandir/colapsar
        const header = card.querySelector('.aa-appointment-header');
        const body = card.querySelector('.aa-appointment-body');
        
        if (header && body) {
            // Remover todos los listeners existentes clonando el header
            const nuevoHeader = header.cloneNode(true);
            header.parentNode.replaceChild(nuevoHeader, header);
            
            // Función helper para actualizar estilos de la card (SIN mover entre contenedores)
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
                    // Restaurar border-radius de esquinas inferiores cuando body está oculto
                    currentHeader.style.borderBottomLeftRadius = '10px';
                    currentHeader.style.borderBottomRightRadius = '10px';
                    
                    // Reset z-index
                    if (overlapInfo && overlapInfo.overlapCount > 1) {
                        card.style.zIndex = String(20 + (overlapInfo.overlapIndex || 0));
                    } else {
                        card.style.zIndex = '20';
                    }
                    
                    // Restaurar overflow del host si no tiene más cards expandidas
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
                    // Quitar border-radius de esquinas inferiores cuando body está visible
                    currentHeader.style.borderBottomLeftRadius = '0';
                    currentHeader.style.borderBottomRightRadius = '0';
                    
                    // Elevar overflow del host para permitir que la card desborde
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
            
            // Agregar nuestro handler personalizado - SIMPLIFICADO: solo toggle, sin re-renderizado
            nuevoHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                
                const currentBody = card.querySelector('.aa-appointment-body');
                if (!currentBody) return;
                
                const isHidden = currentBody.hasAttribute('hidden');
                
                if (isHidden) {
                    // === ABRIR ACORDEÓN ===
                    // UX Guardrail: Si hay otra card expandida, colapsarla primero
                    if (currentlyExpandedCard && currentlyExpandedCard !== card) {
                        colapsarCard(currentlyExpandedCard);
                        currentlyExpandedCard = null;
                    }
                    
                    // Expandir la card actual
                    currentBody.removeAttribute('hidden');
                    actualizarEstilosCard();
                    
                    // Mover card al overlay para ocupar todo el ancho de columna 2
                    expandirEnOverlay(card);
                    
                    currentlyExpandedCard = card;
                } else {
                    // === CERRAR ACORDEÓN ===
                    // Devolver card al host original primero
                    colapsarDeOverlay(card);
                    
                    // Ocultar el body y restaurar estilos
                    currentBody.setAttribute('hidden', '');
                    actualizarEstilosCard();
                    
                    // Resetear estado global si esta era la card expandida
                    if (currentlyExpandedCard === card) {
                        currentlyExpandedCard = null;
                    }
                    
                    // Recrear controles de stack después de colapsar para restaurar la UI
                    setTimeout(() => {
                        crearControlesCicladoStack();
                    }, 0);
                }
            });
            
            // Inicializar estilos según el estado inicial
            actualizarEstilosCard();
        }
        
        return card;
    }

    // Exponer API pública
    window.CalendarAppointments = {
        cargarYRenderizarCitas: cargarYRenderizarCitas
    };

})();

