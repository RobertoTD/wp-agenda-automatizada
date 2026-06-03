/**
 * Clients Module - Module-specific JavaScript
 */

(function() {
    'use strict';

    // Estado del módulo
    let currentQuery = '';
    let currentOffset = 0;
    let currentLimit = 10;
    let hasNext = false;
    let hasPrev = false;
    let searchTimeout = null;

    /**
     * Renderizar una tarjeta de cliente
     */
    function createClientCard(cliente) {
        // Crear tarjeta
        const card = document.createElement('div');
        card.className = 'aa-appointment-card';
        card.setAttribute('data-aa-card', '');

        // Header con nombre del cliente
        const header = document.createElement('div');
        header.className = 'aa-appointment-header';
        header.setAttribute('data-aa-card-toggle', '');
        header.textContent = cliente.nombre || 'Sin nombre';

        // Overlay wrapper
        const overlay = document.createElement('div');
        overlay.className = 'aa-card-overlay';

        // Body wrapper dentro del overlay
        const body = document.createElement('div');
        body.className = 'aa-card-body';

        // Mantener clase original para compatibilidad visual
        body.classList.add('aa-appointment-body');

        // Teléfono / WhatsApp
        const telefono = document.createElement('div');
        if (cliente.telefono) {
            const waLink = document.createElement('span');
            waLink.className = 'aa-whatsapp-link';
            waLink.dataset.phone = cliente.telefono;
            waLink.dataset.waMessage = 'none';
            waLink.title = 'Abrir WhatsApp';
            waLink.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
            const phoneText = document.createElement('span');
            phoneText.className = 'aa-wa-phone-text';
            phoneText.textContent = cliente.telefono;
            waLink.appendChild(phoneText);
            telefono.appendChild(waLink);
        } else {
            telefono.textContent = 'N/A';
        }
        body.appendChild(telefono);

        // Correo
        const correo = document.createElement('div');
        correo.textContent = 'Correo: ' + (cliente.correo || 'N/A');
        body.appendChild(correo);

        // Fecha de registro
        const fechaRegistro = document.createElement('div');
        fechaRegistro.textContent = 'Fecha de registro: ' + (cliente.created_at || 'N/A');
        body.appendChild(fechaRegistro);

        // Total de citas
        const totalCitas = document.createElement('div');
        totalCitas.textContent = 'Total de citas: ' + (cliente.total_citas || 0);
        body.appendChild(totalCitas);

        // Botón de editar
        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'aa-btn-editar-cliente';
        editButton.title = 'Editar cliente';
        editButton.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Editar';
        
        // Event listener para abrir modal de edición usando API global
        editButton.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            if (window.AAAdmin && window.AAAdmin.ClientCreateModal) {
                window.AAAdmin.ClientCreateModal.openEdit(cliente);
            } else {
                console.error('AAAdmin.ClientCreateModal no está disponible');
            }
        });
        
        body.appendChild(editButton);

        // Ensamblar estructura: overlay > body > contenido
        overlay.appendChild(body);

        // Ensamblar tarjeta
        card.appendChild(header);
        card.appendChild(overlay);

        return card;
    }

    /**
     * Renderizar grid de tarjetas de clientes
     */
    function renderClientsGrid(clients) {
        // Obtener el contenedor
        const container = document.getElementById('aa-clients-grid');
        if (!container) {
            console.warn('Contenedor #aa-clients-grid no encontrado');
            return;
        }

        // Validar datos
        if (!Array.isArray(clients) || clients.length === 0) {
            container.innerHTML = '<p>No se encontraron clientes</p>';
            return;
        }

        // Limpiar contenedor
        container.innerHTML = '';

        // Renderizar cada tarjeta
        clients.forEach(function(cliente) {
            const card = createClientCard(cliente);
            container.appendChild(card);
        });
    }

    /**
     * Renderizar barra de acciones
     */
    function renderActionBar() {
        const container = document.getElementById('aa-clients-grid');
        if (!container) return;

        const parent = container.parentElement;
        if (!parent) return;

        // Verificar si ya existe la barra de acciones
        let actionBar = document.getElementById('aa-clients-action-bar');
        if (actionBar) {
            return; // Ya existe, no recrear
        }

        // Crear barra de acciones
        actionBar = document.createElement('div');
        actionBar.id = 'aa-clients-action-bar';
        actionBar.className = 'aa-clients-action-bar';

        // Input de búsqueda
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.id = 'aa-clients-search';
        searchInput.placeholder = 'Buscar por nombre, correo o teléfono';
        searchInput.className = 'aa-clients-search-input';

        // Botón "+ Nuevo"
        const newButton = document.createElement('button');
        newButton.id = 'aa-clients-new';
        newButton.textContent = '+ Nuevo';
        newButton.className = 'aa-clients-new-button';

        // Contenedor de paginación
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'aa-clients-pagination';

        // Botón anterior
        const prevButton = document.createElement('button');
        prevButton.id = 'aa-clients-prev';
        prevButton.textContent = '←';
        prevButton.className = 'aa-clients-pagination-button';
        prevButton.disabled = true;

        // Botón siguiente
        const nextButton = document.createElement('button');
        nextButton.id = 'aa-clients-next';
        nextButton.textContent = '→';
        nextButton.className = 'aa-clients-pagination-button';
        nextButton.disabled = true;

        // Ensamblar paginación
        paginationContainer.appendChild(prevButton);
        paginationContainer.appendChild(nextButton);

        // Ensamblar barra de acciones
        actionBar.appendChild(searchInput);
        actionBar.appendChild(newButton);
        actionBar.appendChild(paginationContainer);

        // Insertar antes del grid
        parent.insertBefore(actionBar, container);

        // Event listeners
        setupEventListeners();
    }

    /**
     * Configurar event listeners
     */
    function setupEventListeners() {
        const searchInput = document.getElementById('aa-clients-search');
        const prevButton = document.getElementById('aa-clients-prev');
        const nextButton = document.getElementById('aa-clients-next');
        const newButton = document.getElementById('aa-clients-new');

        // Botón "+ Nuevo" abre modal usando API global
        if (newButton) {
            newButton.addEventListener('click', function(event) {
                event.preventDefault();
                if (window.AAAdmin && window.AAAdmin.ClientCreateModal) {
                    window.AAAdmin.ClientCreateModal.openCreate();
                } else {
                    console.error('AAAdmin.ClientCreateModal no está disponible');
                }
            });
        }

        // Búsqueda con debounce
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    currentQuery = searchInput.value.trim();
                    currentOffset = 0; // Reiniciar offset al buscar
                    searchClients();
                }, 300);
            });

            // Búsqueda inmediata al presionar Enter
            searchInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.keyCode === 13) {
                    event.preventDefault();
                    
                    // Cancelar cualquier debounce activo
                    clearTimeout(searchTimeout);
                    
                    // Actualizar query y reiniciar offset
                    currentQuery = searchInput.value.trim();
                    currentOffset = 0;
                    
                    // Ejecutar búsqueda inmediatamente
                    searchClients();
                }
            });
        }

        // Paginación anterior
        if (prevButton) {
            prevButton.addEventListener('click', function() {
                if (hasPrev && currentOffset >= currentLimit) {
                    currentOffset -= currentLimit;
                    searchClients();
                }
            });
        }

        // Paginación siguiente
        if (nextButton) {
            nextButton.addEventListener('click', function() {
                if (hasNext) {
                    currentOffset += currentLimit;
                    searchClients();
                }
            });
        }
    }

    /**
     * Actualizar estado de botones de paginación y visibilidad del contenedor
     */
    function updatePaginationButtons(total, limit) {
        const paginationContainer = document.querySelector('.aa-clients-pagination');
        const prevButton = document.getElementById('aa-clients-prev');
        const nextButton = document.getElementById('aa-clients-next');

        // Ocultar completamente la paginación si no hay más de una página
        if (paginationContainer) {
            if (total && limit && total <= limit) {
                paginationContainer.style.display = 'none';
            } else {
                paginationContainer.style.display = '';
                
                // Actualizar estado de botones solo cuando la paginación está visible
                if (prevButton) {
                    prevButton.disabled = !hasPrev;
                }

                if (nextButton) {
                    nextButton.disabled = !hasNext;
                }
            }
        }
    }

    /**
     * Reads optional setup_focus query param used by AI chat actions.
     */
    function applySetupFocusFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const focusKey = params.get('setup_focus');

        if (focusKey !== 'clients' && focusKey !== 'client') {
            return;
        }

        window.requestAnimationFrame(function() {
            focusClientCreateButton();
        });
    }

    /**
     * Guides the user to the client creation action without opening the modal.
     */
    function focusClientCreateButton() {
        const actionBar = document.getElementById('aa-clients-action-bar');
        const newButton = document.getElementById('aa-clients-new');

        if (!newButton) {
            return;
        }

        (actionBar || newButton).scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        applyTemporaryHighlight(newButton);

        if (typeof newButton.focus === 'function') {
            window.setTimeout(function() {
                newButton.focus({ preventScroll: true });
            }, 350);
        }
    }

    /**
     * Applies a temporary inline highlight without relying on generated CSS.
     *
     * @param {HTMLElement} element
     */
    function applyTemporaryHighlight(element) {
        const previousBoxShadow = element.style.boxShadow;
        const previousTransition = element.style.transition;

        element.style.transition = 'box-shadow 180ms ease';
        element.style.boxShadow = '0 0 0 4px rgba(79, 70, 229, 0.22)';

        window.setTimeout(function() {
            element.style.boxShadow = previousBoxShadow;
            element.style.transition = previousTransition;
        }, 2200);
    }

    /**
     * Buscar clientes via AJAX
     */
    function searchClients() {
        // Obtener ajaxurl
        const ajaxurl = window.ajaxurl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

        // Preparar datos
        const formData = new FormData();
        formData.append('action', 'aa_search_clientes');
        formData.append('_wpnonce', window.AA_CLIENTS_NONCES.search_clientes || '');
        formData.append('query', currentQuery);
        formData.append('limit', currentLimit);
        formData.append('offset', currentOffset);

        // Mostrar estado de carga
        const container = document.getElementById('aa-clients-grid');
        if (container) {
            container.innerHTML = '<p>Cargando...</p>';
        }

        // Llamar AJAX
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            if (result.success && result.data) {
                const data = result.data;
                
                // Actualizar estado
                hasNext = data.has_next || false;
                hasPrev = data.has_prev || false;

                // Procesar clientes (agregar total_citas si no existe)
                const clients = (data.clients || []).map(function(cliente) {
                    // Si no tiene total_citas, calcularlo (o usar 0)
                    if (typeof cliente.total_citas === 'undefined') {
                        cliente.total_citas = 0;
                    }
                    return cliente;
                });

                // Renderizar grid
                renderClientsGrid(clients);

                // Actualizar botones de paginación y visibilidad
                updatePaginationButtons(data.total, data.limit);
            } else {
                console.error('Error en búsqueda de clientes:', result);
                if (container) {
                    container.innerHTML = '<p>Error al cargar clientes</p>';
                }
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            if (container) {
                container.innerHTML = '<p>Error al cargar clientes</p>';
            }
        });
    }

    /**
     * Inicializar módulo
     */
    function init() {
        // Renderizar barra de acciones
        renderActionBar();

        applySetupFocusFromUrl();

        // Siempre cargar datos via AJAX (ordenados por total_citas DESC)
        searchClients();

        // Escuchar evento de cliente guardado para recargar lista
        document.addEventListener('aa:client:saved', function(event) {
            // Recargar lista de clientes cuando se guarda un cliente
            currentOffset = 0;
            searchClients();
        });
    }

    // Escuchar DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Clients module loaded');
        init();
    });

})();

