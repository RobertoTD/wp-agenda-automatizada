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
        
        // Eliminar solo los wrappers y cards, no los elementos del timeline (labels y content)
        const elementosAEliminar = [];
        grid.childNodes.forEach(node => {
            if (node.nodeType === 1) {
                // Si es un wrapper de cita o una card directa
                if (node.classList && (
                    node.classList.contains('aa-appointment-card') ||
                    (node.querySelector && node.querySelector('.aa-appointment-card'))
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
    }
    
    /**
     * Renderizar un grupo de citas (pueden estar solapadas o no)
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
        
        // Calcular el rango que cubre todas las citas del grupo
        let minStartRow = Infinity;
        let maxEndRow = -Infinity;
        
        grupo.forEach(citaConPos => {
            minStartRow = Math.min(minStartRow, citaConPos.startRow);
            maxEndRow = Math.max(maxEndRow, citaConPos.startRow + citaConPos.bloquesOcupados);
        });
        
        const startRow = minStartRow;
        const bloquesOcupados = maxEndRow - minStartRow;
        
        // Verificar si hay solapamiento en este grupo
        const tieneSolapamiento = grupo.some(c => overlaps[c.id] && overlaps[c.id].overlapCount > 1);
        
        // Flag para determinar si este grupo está expandido
        const isExpandedGroup = expandedCitaId !== null && expandedGroupKey === groupKey;
        
        // Estructura clara: una sola rama lógica por caso
        if (isExpandedGroup) {
            // Modo expandido: renderizar solo la cita expandida
            const citaExpandida = grupo.find(c => c.id === expandedCitaId);
            if (citaExpandida) {
                // Renderizar solo la cita expandida con ancho 100%
                // Usar el rango original de la cita, no el del grupo
                renderizarCitaExpandida(citaExpandida, citaExpandida.startRow, citaExpandida.bloquesOcupados);
            }
        } else if (tieneSolapamiento) {
            // Modo split horizontal: renderizar todas las citas del grupo con flexbox
            const wrapper = document.createElement('div');
            wrapper.style.gridColumn = '2';
            wrapper.style.gridRow = `${startRow} / span ${bloquesOcupados}`;
            wrapper.style.display = 'flex';
            wrapper.style.width = '100%';
            wrapper.style.gap = '2px';
            wrapper.style.position = 'relative';
            
            grupo.forEach(citaConPos => {
                const overlapInfo = overlaps[citaConPos.id];
                const card = crearCardConInteraccion(citaConPos.cita, overlapInfo, false);
                if (card) {
                    // Usar flex: 1 para dividir el espacio equitativamente
                    card.style.flex = '1';
                    card.style.minWidth = '0'; // Permite que flex funcione correctamente
                    card.style.position = 'relative'; // Para que los clicks funcionen
                    wrapper.appendChild(card);
                }
            });
            
            grid.appendChild(wrapper);
        } else {
            // Sin solapamiento: renderizar normalmente
            grupo.forEach(citaConPos => {
                const card = crearCardConInteraccion(citaConPos.cita, null, false);
                if (card) {
                    card.style.gridColumn = '2';
                    card.style.gridRow = `${citaConPos.startRow} / span ${citaConPos.bloquesOcupados}`;
                    grid.appendChild(card);
                }
            });
        }
    }
    
    /**
     * Renderizar una cita en modo expandido (ancho 100%)
     */
    function renderizarCitaExpandida(citaConPos, startRow, bloquesOcupados) {
        const grid = document.getElementById('aa-time-grid');
        if (!grid) return;
        
        const card = crearCardConInteraccion(citaConPos.cita, null, true);
        if (!card) return;
        
        // Configurar para modo expandido
        card.style.gridColumn = '2';
        card.style.gridRow = `${startRow} / span ${bloquesOcupados}`;
        card.style.width = '100%';
        card.style.position = 'relative'; // Permite crecimiento vertical
        
        // Mostrar el body automáticamente en modo expandido
        const body = card.querySelector('.aa-appointment-body');
        if (body) {
            body.removeAttribute('hidden');
        }
        
        grid.appendChild(card);
    }
    
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
                        }
                    } else {
                        // Sin solapamiento, solo toggle normal
                        currentBody.removeAttribute('hidden');
                    }
                } else {
                    // Cerrar acordeón
                    currentBody.setAttribute('hidden', '');
                    
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
        }
        
        return card;
    }

    // Exponer API pública
    window.CalendarAppointments = {
        cargarYRenderizarCitas: cargarYRenderizarCitas
    };

})();

