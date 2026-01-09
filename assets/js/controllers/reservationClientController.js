/**
 * Reservation Client Controller
 * 
 * Handles client search and selection functionality within the reservation modal.
 * Encapsulates debounced search, AJAX requests, select repopulation, and inline client creation.
 * 
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

(function() {
    'use strict';

    /**
     * Create a new ReservationClientController instance
     * @param {Object} opts - Configuration options
     * @param {string} opts.searchInputId - ID of the search input element
     * @param {string} opts.selectId - ID of the client select element
     * @param {string} opts.inlineContainerId - ID of the inline container for client creation
     * @param {string} opts.createButtonId - ID of the "Create client" button
     * @returns {Object} Controller instance with destroy() method
     */
    function createController(opts) {
        const {
            searchInputId = 'aa-cliente-search',
            selectId = 'cita-cliente',
            inlineContainerId = 'aa-reservation-client-inline',
            createButtonId = 'aa-btn-crear-cliente-reservation'
        } = opts;

        // Get DOM elements
        const searchInput = document.getElementById(searchInputId);
        const clienteSelect = document.getElementById(selectId);
        const inlineContainer = document.getElementById(inlineContainerId);
        const btnCrearCliente = document.getElementById(createButtonId);

        if (!searchInput || !clienteSelect) {
            console.warn('[ReservationClientController] Elementos de búsqueda de clientes no encontrados');
            return null;
        }

        // Internal state
        let previousSelectedValue = null;
        let searchTimeoutId = null;

        /**
         * Repopulate select with client results
         * @param {Array} clients - Array of client objects
         * @param {boolean} preserveSelection - Whether to preserve current selection if still valid
         * @param {string|number} [selectClientId] - Optional client ID to select after repopulating
         * @param {string} [selectClientPhone] - Optional client phone to select after repopulating (exact match)
         */
        function repopulateSelect(clients, preserveSelection, selectClientId, selectClientPhone) {
            // Store current selection
            if (preserveSelection) {
                previousSelectedValue = clienteSelect.value;
            }

            // Clear all options
            clienteSelect.innerHTML = '';

            // Add client options
            if (clients && clients.length > 0) {
                clients.forEach(function(cliente) {
                    const option = document.createElement('option');
                    option.value = String(cliente.id);
                    option.textContent = cliente.nombre + ' (' + cliente.telefono + ')';
                    option.dataset.nombre = cliente.nombre || '';
                    option.dataset.telefono = cliente.telefono || '';
                    option.dataset.correo = cliente.correo || '';
                    clienteSelect.appendChild(option);
                });

                // If a specific client phone is provided, try to select by phone first (exact match)
                if (selectClientPhone !== undefined && selectClientPhone !== null && selectClientPhone !== '') {
                    const phoneStr = String(selectClientPhone).trim();
                    const optionByPhone = Array.from(clienteSelect.options).find(function(opt) {
                        return opt.dataset.telefono === phoneStr;
                    });
                    if (optionByPhone) {
                        clienteSelect.value = optionByPhone.value;
                        previousSelectedValue = optionByPhone.value;
                        console.log('[ReservationClientController] Cliente seleccionado automáticamente por teléfono:', phoneStr);
                    } else {
                        // Phone not found, try by ID if provided, otherwise select first
                        if (selectClientId !== undefined && selectClientId !== null) {
                            const clientIdStr = String(selectClientId);
                            const optionExists = Array.from(clienteSelect.options).some(function(opt) {
                                return opt.value === clientIdStr;
                            });
                            if (optionExists) {
                                clienteSelect.value = clientIdStr;
                                previousSelectedValue = clientIdStr;
                                console.log('[ReservationClientController] Cliente seleccionado automáticamente por ID:', clientIdStr);
                            } else {
                                clienteSelect.selectedIndex = 0;
                                console.warn('[ReservationClientController] Cliente no encontrado por teléfono ni ID, seleccionando primero');
                            }
                        } else {
                            clienteSelect.selectedIndex = 0;
                            console.warn('[ReservationClientController] Cliente no encontrado por teléfono, seleccionando primero');
                        }
                    }
                }
                // If a specific client ID is provided (and no phone), select it
                else if (selectClientId !== undefined && selectClientId !== null) {
                    const clientIdStr = String(selectClientId);
                    const optionExists = Array.from(clienteSelect.options).some(function(opt) {
                        return opt.value === clientIdStr;
                    });
                    if (optionExists) {
                        clienteSelect.value = clientIdStr;
                        previousSelectedValue = clientIdStr;
                        console.log('[ReservationClientController] Cliente seleccionado automáticamente por ID:', clientIdStr);
                    } else {
                        clienteSelect.selectedIndex = 0;
                        console.warn('[ReservationClientController] Cliente ID no encontrado en resultados:', clientIdStr);
                    }
                }
                // Restore selection if it still exists, otherwise select first result
                else if (preserveSelection && previousSelectedValue) {
                    const optionExists = Array.from(clienteSelect.options).some(function(opt) {
                        return opt.value === previousSelectedValue;
                    });
                    if (optionExists) {
                        clienteSelect.value = previousSelectedValue;
                    } else {
                        clienteSelect.selectedIndex = 0;
                        previousSelectedValue = null;
                        console.log('[ReservationClientController] Cliente seleccionado ya no está en resultados, seleccionando primero');
                    }
                } else {
                    // Select first result automatically
                    clienteSelect.selectedIndex = 0;
                }
            } else {
                // No results
                const noResultsOption = document.createElement('option');
                noResultsOption.value = '';
                noResultsOption.textContent = 'Sin coincidencias';
                noResultsOption.disabled = true;
                clienteSelect.appendChild(noResultsOption);
                clienteSelect.selectedIndex = 0;
            }
        }

        /**
         * Search clients via AJAX
         * @param {string} query - Search query
         * @param {boolean} preserveSelection - Whether to preserve current selection
         * @param {string|number} [selectClientId] - Optional client ID to select after loading
         * @param {string} [selectClientPhone] - Optional client phone to select after loading (exact match)
         */
        function searchClients(query, preserveSelection, selectClientId, selectClientPhone) {
            const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'aa_search_clientes');
            formData.append('query', query || '');
            formData.append('limit', '15');
            formData.append('offset', '0');

            // Show loading state (disable select but keep current options visible)
            clienteSelect.disabled = true;

            // Make AJAX call
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(result) {
                clienteSelect.disabled = false;

                if (result.success && result.data && result.data.clients) {
                    console.log('[ReservationClientController] Clientes encontrados:', result.data.clients.length);
                    repopulateSelect(result.data.clients, preserveSelection, selectClientId, selectClientPhone);
                } else {
                    console.warn('[ReservationClientController] Error en búsqueda de clientes:', result);
                    repopulateSelect([], preserveSelection, selectClientId, selectClientPhone);
                }
            })
            .catch(function(error) {
                console.error('[ReservationClientController] Error al buscar clientes:', error);
                clienteSelect.disabled = false;
                repopulateSelect([], preserveSelection, selectClientId, selectClientPhone);
            });
        }

        // Event handlers (will be stored for cleanup)
        let handleInput = null;
        let handleKeydown = null;
        let handleCreateClick = null;
        let handleClientSaved = null;

        // Initial load: 15 most active clients (empty query returns most active)
        searchClients('', false);

        // Debounced search on input change
        handleInput = function() {
            const query = this.value.trim();

            // Clear previous timeout
            if (searchTimeoutId) {
                clearTimeout(searchTimeoutId);
            }

            // Set new timeout (300ms debounce)
            searchTimeoutId = setTimeout(function() {
                console.log('[ReservationClientController] Buscando clientes con query:', query);
                searchClients(query, true);
            }, 300);
        };
        searchInput.addEventListener('input', handleInput);

        // Clear search on escape
        handleKeydown = function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.dispatchEvent(new Event('input'));
            }
        };
        searchInput.addEventListener('keydown', handleKeydown);

        // Botón "Crear cliente" - abre formulario inline de crear cliente
        if (btnCrearCliente && inlineContainer) {
            handleCreateClick = function(event) {
                event.preventDefault();
                event.stopPropagation();
                
                if (window.AAAdmin && window.AAAdmin.ClientCreateModal) {
                    console.log('[ReservationClientController] Abriendo formulario inline de crear cliente...');
                    
                    // Mostrar contenedor inline
                    inlineContainer.style.display = 'block';
                    
                    // Abrir formulario en modo inline
                    window.AAAdmin.ClientCreateModal.openCreate({
                        mode: 'inline',
                        container: inlineContainer
                    });
                } else {
                    console.error('[ReservationClientController] AAAdmin.ClientCreateModal no está disponible');
                    alert('Error: Sistema de crear cliente no disponible');
                }
            };
            btnCrearCliente.addEventListener('click', handleCreateClick);
        }

        // Escuchar evento de cliente guardado para recargar y seleccionar
        // Solo procesar si el modal de reservas está abierto (select existe)
        handleClientSaved = function(event) {
            // Verificar que el select de clientes existe (modal está abierto)
            const clienteSelectEl = document.getElementById(selectId);
            if (!clienteSelectEl) {
                // Modal de reservas no está abierto, ignorar evento
                return;
            }

            // Obtener teléfono con prioridad:
            // 1) event.detail.telefono (explícito)
            // 2) event.detail.cliente?.telefono (si existe)
            // 3) fallback: leer del input dentro del inlineContainer si el form sigue montado
            let telefono = null;
            
            if (event.detail && event.detail.telefono) {
                telefono = event.detail.telefono;
            } else if (event.detail && event.detail.cliente && event.detail.cliente.telefono) {
                telefono = event.detail.cliente.telefono;
            } else {
                // Fallback: intentar leer del input del formulario inline si aún está montado
                const inlineContainerEl = document.getElementById(inlineContainerId);
                if (inlineContainerEl) {
                    const telefonoInput = inlineContainerEl.querySelector('#modal-cliente-telefono');
                    if (telefonoInput && telefonoInput.value) {
                        telefono = telefonoInput.value.trim();
                    }
                }
            }
            
            if (!telefono || telefono === '') {
                console.warn('[ReservationClientController] Cliente guardado sin teléfono disponible');
                return;
            }

            console.log('[ReservationClientController] Cliente guardado, recargando lista y seleccionando por teléfono:', telefono);
            
            // Ocultar contenedor inline si está visible
            const inlineContainerEl = document.getElementById(inlineContainerId);
            if (inlineContainerEl) {
                inlineContainerEl.style.display = 'none';
            }
            
            // Setear el input de búsqueda con el teléfono
            const searchInputEl = document.getElementById(searchInputId);
            if (searchInputEl) {
                searchInputEl.value = telefono;
            }
            
            // Recargar clientes y seleccionar el recién creado por teléfono (match exacto)
            searchClients(telefono, false, null, telefono);
        };
        document.addEventListener('aa:client:saved', handleClientSaved);

        console.log('[ReservationClientController] ✅ Inicializado correctamente');

        // Return controller instance with destroy method
        return {
            destroy: function() {
                // Remove event listeners
                if (handleInput) {
                    searchInput.removeEventListener('input', handleInput);
                }
                if (handleKeydown) {
                    searchInput.removeEventListener('keydown', handleKeydown);
                }
                if (handleCreateClick && btnCrearCliente) {
                    btnCrearCliente.removeEventListener('click', handleCreateClick);
                }
                if (handleClientSaved) {
                    document.removeEventListener('aa:client:saved', handleClientSaved);
                }

                // Clear timeout
                if (searchTimeoutId) {
                    clearTimeout(searchTimeoutId);
                    searchTimeoutId = null;
                }

                console.log('[ReservationClientController] ✅ Destruido y limpiado');
            }
        };
    }

    // ============================================
    // Expose to global namespace
    // ============================================
    window.ReservationClientController = {
        init: createController
    };

    console.log('✅ [ReservationClientController] Módulo cargado');

})();
