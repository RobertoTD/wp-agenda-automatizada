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

        // Teléfono
        const telefono = document.createElement('div');
        telefono.textContent = 'Teléfono: ' + (cliente.telefono || 'N/A');
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
     * Actualizar estado de botones de paginación
     */
    function updatePaginationButtons() {
        const prevButton = document.getElementById('aa-clients-prev');
        const nextButton = document.getElementById('aa-clients-next');

        if (prevButton) {
            prevButton.disabled = !hasPrev;
        }

        if (nextButton) {
            nextButton.disabled = !hasNext;
        }
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

                // Actualizar botones de paginación
                updatePaginationButtons();
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

        // Cargar datos iniciales desde window.AA_CLIENTS_DATA o hacer búsqueda inicial
        if (window.AA_CLIENTS_DATA && Array.isArray(window.AA_CLIENTS_DATA.clients)) {
            const clients = window.AA_CLIENTS_DATA.clients;
            renderClientsGrid(clients);
            
            // Establecer estado inicial de paginación
            hasNext = clients.length >= currentLimit;
            hasPrev = false;
            updatePaginationButtons();
        } else {
            // Si no hay datos iniciales, hacer búsqueda
            searchClients();
        }
    }

    // Escuchar DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Clients module loaded');
        init();
    });

})();

