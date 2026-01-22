/**
 * Calendar Appointments - Load and render appointments
 */

(function() {
    'use strict';

    // Estado UI local para trackear cita expandida
    let expandedCitaId = null;
    let expandedGroupKey = null; // Identificador del grupo expandido (ej: "5-8")
    
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
                
                // Resetear estado expandido al cargar nuevas citas
                expandedCitaId = null;
                expandedGroupKey = null;
                
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
        // Ordenar por startRow
        grupo.sort((a, b) => a.startRow - b.startRow);
        
        let currentIndex = grupo.length - 1; // Última al frente
        
        // Asignar z-index inicial
        grupo.forEach((info, idx) => {
            info.card.style.zIndex = String(20 + idx);
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
            zIndex: '50',
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
            
            // Actualizar z-index
            grupo.forEach((info, idx) => {
                info.card.style.zIndex = idx === currentIndex ? '29' : String(20 + idx);
            });
            
            // Mover control a la nueva card al frente
            grupo[currentIndex].card.appendChild(control);
        });
        
        // Agregar a la card actualmente al frente
        grupo[currentIndex].card.appendChild(control);
    }
    
    /**
     * Renderizar un grupo de citas (pueden estar solapadas o no)
     * Ahora renderiza cards DENTRO del host del overlay de asignación correspondiente.
     * @param {Array} grupo - Array de citas del grupo
     * @param {Object} overlaps - Mapa de overlaps
     * @param {Map} slotRowIndex - Mapa de slots
     * @param {Array} timeSlots - Array de time slots
     * @param {string} groupKey - Identificador único del grupo (ej: "5-8")
     */
    function renderizarGrupoCitas(grupo, overlaps, slotRowIndex, timeSlots, groupKey) {
        if (grupo.length === 0) return;
        
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        // Flag para determinar si este grupo está expandido
        const isExpandedGroup = expandedCitaId !== null && expandedGroupKey === groupKey;
        
        if (isExpandedGroup) {
            // Modo expandido: renderizar solo la cita expandida
            const citaExpandida = grupo.find(c => c.id === expandedCitaId);
            if (citaExpandida) {
                renderizarCitaEnHost(citaExpandida, overlaps, true);
            }
        } else {
            // Renderizar todas las citas del grupo dentro de sus hosts correspondientes
            grupo.forEach((citaConPos, index) => {
                const overlapInfo = overlaps[citaConPos.id];
                renderizarCitaEnHost(citaConPos, overlaps, false, overlapInfo, index);
            });
        }
    }
    
    /**
     * Renderizar una cita dentro de su host de overlay correspondiente
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
        const card = crearCardConInteraccion(citaConPos.cita, overlapInfo || null, isExpanded);
        if (!card) return;
        
        // Store position data in card dataset for use when expanding/collapsing
        card.dataset.citaStartRow = citaConPos.startRow;
        card.dataset.citaBloquesOcupados = citaConPos.bloquesOcupados;
        card.dataset.citaSlotInicio = citaConPos.slotInicio; // Store slotInicio in minutes for host matching
        
        if (host) {
            // ===== RENDER INSIDE HOST =====
            const hostStartRow = parseInt(host.getAttribute('data-start-row'), 10);
            
            // Calculate relative position within the host's grid
            const relativeStart = citaConPos.startRow - hostStartRow + 1;
            
            // Store original host and position data for restoration when collapsed
            card.dataset.originalHost = 'true';
            card.dataset.originalGridColumn = '1';
            card.dataset.originalGridRow = relativeStart + ' / span ' + citaConPos.bloquesOcupados;
            
            // Handle overlaps: use z-index to stack cards (no horizontal split)
            // If expanded, use higher z-index (30) to ensure it's above other cards
            if (isExpanded) {
                // When expanded, move card to grid directly to escape host restrictions
                card.style.gridColumn = '2';
                card.style.gridRow = citaConPos.startRow + ' / span ' + citaConPos.bloquesOcupados;
                card.style.width = '100%';
                card.style.marginLeft = '0%';
                card.style.position = 'relative';
                card.style.zIndex = '30';
                
                // Move card from host to grid
                grid.appendChild(card);
            } else {
                // Position card using the host's internal grid
                card.style.gridColumn = '1';
                card.style.gridRow = relativeStart + ' / span ' + citaConPos.bloquesOcupados;
                card.style.width = '100%';
                card.style.position = 'relative';
                
                if (overlapInfo && overlapInfo.overlapCount > 1) {
                    card.style.zIndex = String(20 + (overlapIndex || 0));
                } else {
                    card.style.zIndex = '20';
                }
                
                // Append to host
                host.appendChild(card);
            }
            
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
                    }
                }
            }
        } else {
            // ===== FALLBACK: RENDER DIRECTLY ON GRID =====
            console.warn('[CalendarAppointments] No host found for cita:', citaConPos.cita.id, '- using fallback grid positioning');
            
            card.style.gridColumn = '2';
            card.style.gridRow = citaConPos.startRow + ' / span ' + citaConPos.bloquesOcupados;
            card.style.position = 'relative';
            // If expanded, use higher z-index (30) to ensure it's above other cards
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
                    }
                }
            }
            
            grid.appendChild(card);
        }
    }
    
    // renderizarCitaExpandida is now handled by renderizarCitaEnHost with isExpanded=true
    
    /**
     * Crear card con interacción de expandir/colapsar
     * @param {Object} cita - Objeto de cita
     * @param {Object} overlapInfo - Información de solapamiento
     * @param {boolean} isExpanded - Si la cita está en modo expandido
     */
    function crearCardConInteraccion(cita, overlapInfo, isExpanded) {
        if (!cita.fecha) return null;
        
        // Crear la card usando CalendarAppointmentCard
        const card = window.CalendarAppointmentCard?.crearCardCita(cita);
        if (!card) return null;
        
        // Interceptar el click del header para manejar expandir/colapsar
        const header = card.querySelector('.aa-appointment-header');
        const body = card.querySelector('.aa-appointment-body');
        
        if (header && body) {
            // Remover todos los listeners existentes clonando el header
            const nuevoHeader = header.cloneNode(true);
            header.parentNode.replaceChild(nuevoHeader, header);
            
            // Función helper para actualizar estilos del header según el estado del body
            function actualizarEstilosHeaderCard() {
                const currentHeader = card.querySelector('.aa-appointment-header');
                const currentBody = card.querySelector('.aa-appointment-body');
                if (!currentHeader || !currentBody) return;
                
                const grid = document.getElementById('aa-time-grid');
                const isHidden = currentBody.hasAttribute('hidden');
                const wasInHost = card.dataset.originalHost === 'true';
                
                if (isHidden) {
                    // Body oculto: header ocupa todo el espacio vertical
                    currentHeader.style.flex = '1';
                    currentHeader.style.flexShrink = '0';
                    currentBody.style.flex = '0';
                    // Reset z-index when collapsed
                    if (overlapInfo && overlapInfo.overlapCount > 1) {
                        card.style.zIndex = String(20 + (overlapInfo.overlapIndex || 0));
                    } else {
                        card.style.zIndex = '20';
                    }
                    
                    // If card was originally in host, move it back to host
                    if (wasInHost && grid) {
                        // Build position object from saved dataset for host matching
                        const savedPosicion = {
                            slotInicio: parseInt(card.dataset.citaSlotInicio, 10),
                            bloquesOcupados: parseInt(card.dataset.citaBloquesOcupados, 10)
                        };
                        const host = window.CalendarAssignments?.getCardsHostForCita(cita, savedPosicion);
                        if (host && card.parentNode !== host) {
                            // Restore original position within host
                            card.style.gridColumn = card.dataset.originalGridColumn || '1';
                            card.style.gridRow = card.dataset.originalGridRow || '1';
                            card.style.width = '100%';
                            card.style.marginLeft = '';
                            // Move card back to host
                            host.appendChild(card);
                        }
                    }
                } else {
                    // Body visible: header tamaño normal, body ocupa el resto
                    currentHeader.style.flex = '0 0 auto';
                    currentHeader.style.flexShrink = '0';
                    currentBody.style.flex = '1';
                    // Increase z-index when expanded to ensure it's above other cards
                    card.style.zIndex = '30';
                    
                    // If card was originally in host, move it to grid to escape host restrictions
                    if (wasInHost && grid && card.parentNode !== grid) {
                        // Get position data from card dataset
                        const startRow = parseInt(card.dataset.citaStartRow, 10);
                        const bloquesOcupados = parseInt(card.dataset.citaBloquesOcupados, 10);
                        
                        if (!isNaN(startRow) && !isNaN(bloquesOcupados)) {
                            // Position directly on grid with full width
                            card.style.gridColumn = '2';
                            card.style.gridRow = startRow + ' / span ' + bloquesOcupados;
                            card.style.width = '100%';
                            card.style.marginLeft = '0%';
                            // Move card from host to grid
                            grid.appendChild(card);
                        }
                    }
                }
            }
            
            // Agregar nuestro handler personalizado
            nuevoHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                
                const currentBody = card.querySelector('.aa-appointment-body');
                if (!currentBody) return;
                
                const isHidden = currentBody.hasAttribute('hidden');
                
                if (isHidden) {
                    // Abrir acordeón
                    if (overlapInfo && overlapInfo.overlapCount > 1) {
                        // Si hay solapamiento, expandir esta cita (ocultar solo las del mismo grupo)
                        if (expandedCitaId !== cita.id) {
                            expandedCitaId = cita.id;
                            // Calcular el groupKey de esta cita encontrando su grupo
                            if (citasCache && overlapsCache) {
                                const citaData = citasCache.find(ct => ct.id === cita.id);
                                if (citaData) {
                                    let minStartRow = citaData.startRow;
                                    let maxEndRow = citaData.startRow + citaData.bloquesOcupados;
                                    
                                    // Encontrar todas las citas que se solapan con esta
                                    citasCache.forEach(c => {
                                        if (c.id === cita.id) return;
                                        
                                        const o = overlapsCache[c.id];
                                        if (o && o.overlapCount > 1) {
                                            const cStartRow = c.startRow;
                                            const cEndRow = c.startRow + c.bloquesOcupados;
                                            
                                            // Si se solapan verticalmente
                                            if (citaData.startRow < cEndRow && cStartRow < (citaData.startRow + citaData.bloquesOcupados)) {
                                                minStartRow = Math.min(minStartRow, cStartRow);
                                                maxEndRow = Math.max(maxEndRow, cEndRow);
                                            }
                                        }
                                    });
                                    
                                    expandedGroupKey = `${minStartRow}-${maxEndRow}`;
                                }
                            }
                            
                            // Re-renderizar todas las citas
                            if (citasCache && overlapsCache && slotRowIndexCache && timeSlotsCache) {
                                renderizarTodasLasCitas(citasCache, overlapsCache, slotRowIndexCache, timeSlotsCache);
                            }
                        } else {
                            // Ya está expandida, solo mostrar el body
                            currentBody.removeAttribute('hidden');
                            actualizarEstilosHeaderCard();
                        }
                    } else {
                        // Sin solapamiento, solo toggle normal
                        currentBody.removeAttribute('hidden');
                        actualizarEstilosHeaderCard();
                    }
                } else {
                    // Cerrar acordeón
                    currentBody.setAttribute('hidden', '');
                    actualizarEstilosHeaderCard();
                    
                    // Si esta cita estaba expandida, colapsar y re-renderizar
                    if (expandedCitaId === cita.id) {
                        expandedCitaId = null;
                        expandedGroupKey = null;
                        // Re-renderizar todas las citas
                        if (citasCache && overlapsCache && slotRowIndexCache && timeSlotsCache) {
                            renderizarTodasLasCitas(citasCache, overlapsCache, slotRowIndexCache, timeSlotsCache);
                        }
                    }
                }
            });
            
            // Inicializar estilos según el estado inicial
            actualizarEstilosHeaderCard();
        }
        
        return card;
    }

    // Exponer API pública
    window.CalendarAppointments = {
        cargarYRenderizarCitas: cargarYRenderizarCitas
    };

})();

