/**
 * Clients Module - Module-specific JavaScript
 *
 * Modes:
 * - list: search / paginate / create / edit (existing behaviour)
 * - expediente: load one client by id and show empty expediente shell
 */

(function() {
    'use strict';

    // Estado del módulo (solo lista)
    let currentQuery = '';
    let currentOffset = 0;
    let currentLimit = 10;
    let hasNext = false;
    let hasPrev = false;
    let searchTimeout = null;
    let clientsOptionsMenuOpen = false;
    let clientsOptionsMenuBound = false;
    let expedienteOptionsMenuOpen = false;
    let expedienteOptionsMenuBound = false;
    /** Total de clientes sin filtro de búsqueda; decide visibilidad del buscador. */
    let unfilteredClientTotal = 0;
    const MIN_CLIENTS_FOR_SEARCH = 3;

    var EXPEDIENTE_FOLDER_SVG = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>';
    var CLIENT_PERSON_SVG = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';

    function getClientsData() {
        return window.AA_CLIENTS_DATA || {};
    }

    function getClientsNonces() {
        return window.AA_CLIENTS_NONCES || {};
    }

    function isExpedienteView() {
        var data = getClientsData();
        return data.view === 'expediente' && parseInt(data.clientId, 10) > 0;
    }

    function buildExpedienteUrl(clientId) {
        var data = getClientsData();
        var base = data.moduleBaseUrl || data.listUrl || '';
        if (!base) {
            return '';
        }
        try {
            var url = new URL(base, window.location.href);
            url.searchParams.set('view', 'expediente');
            url.searchParams.set('client_id', String(clientId));
            return url.toString();
        } catch (err) {
            console.error('[Clients] buildExpedienteUrl failed:', err);
            return '';
        }
    }

    /**
     * Fila de detalle: etiqueta + valor (valor atenuado).
     */
    function createClientDetailRow(label, value) {
        const row = document.createElement('div');
        const title = document.createElement('span');
        title.className = 'font-semibold';
        title.textContent = label + ' ';
        const content = document.createElement('span');
        content.className = 'text-gray-500';
        content.textContent = value;
        row.appendChild(title);
        row.appendChild(content);
        return row;
    }

    let clientCardDetailsSeq = 0;

    function nextClientDetailsPanelId(cliente) {
        var id = parseInt(cliente && cliente.id, 10);
        if (id > 0) {
            return 'aa-client-details-' + id;
        }
        clientCardDetailsSeq += 1;
        return 'aa-client-details-tmp-' + clientCardDetailsSeq;
    }

    function setClientCardDetailsExpanded(toggle, panel, expanded) {
        if (panel) {
            if (expanded) {
                panel.classList.add('is-visible');
            } else {
                panel.classList.remove('is-visible');
            }
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.textContent = expanded ? 'Ver menos' : 'Ver más';
        }
    }

    function createClientDetailsToggle(panelId) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'aa-client-card-details-toggle shrink-0 text-xs font-semibold text-gray-500 underline hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300/60 rounded';
        button.setAttribute('data-aa-client-details-toggle', '1');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', panelId);
        button.textContent = 'Ver más';
        return button;
    }

    function createClientDetailsPanel(cliente, panelId) {
        const panel = document.createElement('div');
        panel.className = 'aa-client-card-details';
        panel.id = panelId;
        if (cliente && cliente.id) {
            panel.setAttribute('data-client-id', String(cliente.id));
        }
        panel.appendChild(createClientDetailRow('Correo:', cliente.correo || 'N/A'));
        panel.appendChild(createClientDetailRow('Fecha de registro:', cliente.created_at || 'N/A'));
        panel.appendChild(createClientDetailRow('Total de citas:', String(cliente.total_citas || 0)));
        return panel;
    }

    /**
     * Renderizar una tarjeta de cliente
     */
    function createClientCard(cliente) {
        // Crear tarjeta
        const card = document.createElement('div');
        card.className = 'aa-appointment-card';
        card.setAttribute('data-aa-card', '');

        // Header con icono + nombre del cliente
        const header = document.createElement('div');
        header.className = 'aa-appointment-header';
        header.setAttribute('data-aa-card-toggle', '');

        const titleRow = document.createElement('div');
        titleRow.className = 'flex items-center min-w-0';

        const iconWrap = document.createElement('span');
        iconWrap.className = 'flex items-center justify-center w-8 h-8 text-gray-600 shrink-0';
        iconWrap.setAttribute('aria-hidden', 'true');
        iconWrap.innerHTML = CLIENT_PERSON_SVG;

        const name = document.createElement('span');
        name.className = 'min-w-0 truncate text-lg font-semibold text-gray-600';
        name.textContent = cliente.nombre || 'Sin nombre';

        titleRow.appendChild(iconWrap);
        titleRow.appendChild(name);
        header.appendChild(titleRow);

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
        telefono.className = 'aa-client-card-contact';
        if (cliente.telefono) {
            const waLink = document.createElement('span');
            waLink.className = 'aa-whatsapp-link';
            waLink.dataset.phone = cliente.telefono;
            waLink.dataset.waMessage = 'none';
            waLink.title = 'Abrir WhatsApp';
            waLink.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
            const phoneText = document.createElement('span');
            phoneText.className = 'aa-wa-phone-text';
            phoneText.textContent = cliente.telefono;
            waLink.appendChild(phoneText);
            telefono.appendChild(waLink);
        } else {
            telefono.textContent = 'N/A';
        }
        body.appendChild(telefono);

        const detailsPanelId = nextClientDetailsPanelId(cliente);
        const detailsToggle = createClientDetailsToggle(detailsPanelId);
        const detailsPanel = createClientDetailsPanel(cliente, detailsPanelId);
        const detailsToggleWrap = document.createElement('div');
        detailsToggleWrap.className = 'aa-client-card-details-meta';
        detailsToggleWrap.appendChild(detailsToggle);
        detailsToggle.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            var expanded = detailsToggle.getAttribute('aria-expanded') === 'true';
            setClientCardDetailsExpanded(detailsToggle, detailsPanel, !expanded);
        });
        body.appendChild(detailsToggleWrap);
        body.appendChild(detailsPanel);

        // Acciones: Editar + Expediente
        const actions = document.createElement('div');
        actions.className = 'aa-client-card-actions';

        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'aa-btn-editar-cliente';
        editButton.title = 'Editar cliente';
        editButton.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Editar';

        editButton.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            if (window.AAAdmin && window.AAAdmin.ClientCreateModal) {
                window.AAAdmin.ClientCreateModal.openEdit(cliente);
            } else {
                console.error('AAAdmin.ClientCreateModal no está disponible');
            }
        });

        const expedienteButton = document.createElement('button');
        expedienteButton.type = 'button';
        expedienteButton.className = 'aa-btn-expediente-cliente';
        expedienteButton.title = 'Abrir expediente';
        expedienteButton.innerHTML = EXPEDIENTE_FOLDER_SVG + ' Expediente';
        if (cliente.id) {
            expedienteButton.setAttribute('data-client-id', String(cliente.id));
        }

        var expedienteAllowed = getClientsData().expedienteAccessAllowed === true;
        if (!expedienteAllowed) {
            expedienteButton.disabled = true;
            expedienteButton.setAttribute('aria-disabled', 'true');
            expedienteButton.title = 'Expediente no disponible';
        }

        expedienteButton.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            if (getClientsData().expedienteAccessAllowed !== true) {
                return;
            }
            var clientId = parseInt(cliente.id, 10);
            if (!(clientId > 0)) {
                console.error('[Clients] Expediente: client id inválido');
                return;
            }
            var target = buildExpedienteUrl(clientId);
            if (!target) {
                console.error('[Clients] Expediente: no se pudo construir la URL');
                return;
            }
            window.location.href = target;
        });

        actions.appendChild(editButton);
        actions.appendChild(expedienteButton);
        body.appendChild(actions);

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

        // Input de búsqueda (oculto hasta tener >= 3 clientes)
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.id = 'aa-clients-search';
        searchInput.placeholder = 'Buscar por nombre, correo o teléfono';
        searchInput.className = 'aa-clients-search-input hidden';

        // Contenedor de paginación (oculto hasta saber si hay más de una página)
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'aa-clients-pagination';
        paginationContainer.style.display = 'none';

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
        actionBar.appendChild(paginationContainer);
        actionBar.classList.add('hidden');

        // Insertar antes del grid
        parent.insertBefore(actionBar, container);

        // Event listeners
        setupEventListeners();
    }

    function getClientsOptionsTrigger() {
        return document.getElementById('aa-clients-options-trigger');
    }

    function getClientsOptionsMenu() {
        return document.getElementById('aa-clients-options-menu');
    }

    function closeClientsOptionsMenu() {
        const trigger = getClientsOptionsTrigger();
        const menu = getClientsOptionsMenu();

        if (menu) {
            menu.classList.add('hidden');
        }
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
        clientsOptionsMenuOpen = false;
    }

    function openClientsOptionsMenu() {
        const trigger = getClientsOptionsTrigger();
        const menu = getClientsOptionsMenu();

        if (!menu || !trigger) {
            return;
        }

        menu.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        clientsOptionsMenuOpen = true;
    }

    function toggleClientsOptionsMenu() {
        if (clientsOptionsMenuOpen) {
            closeClientsOptionsMenu();
        } else {
            openClientsOptionsMenu();
        }
    }

    function openCreateClientModal() {
        if (window.AAAdmin && window.AAAdmin.ClientCreateModal) {
            window.AAAdmin.ClientCreateModal.openCreate();
            return;
        }
        console.error('AAAdmin.ClientCreateModal no está disponible');
    }

    function handleClientsOptionsDocumentClick(event) {
        const target = event && event.target;
        const trigger = target && target.closest
            ? target.closest('#aa-clients-options-trigger')
            : null;
        const createItem = target && target.closest
            ? target.closest('[data-clients-tool="create-client"]')
            : null;
        const insideTools = target && target.closest
            ? target.closest('#aa-clients-area-tools')
            : null;

        if (trigger) {
            event.preventDefault();
            toggleClientsOptionsMenu();
            return;
        }

        if (createItem) {
            event.preventDefault();
            closeClientsOptionsMenu();
            openCreateClientModal();
            return;
        }

        if (clientsOptionsMenuOpen && !insideTools) {
            closeClientsOptionsMenu();
        }
    }

    function handleClientsOptionsDocumentKeydown(event) {
        if (!event || event.key !== 'Escape' || !clientsOptionsMenuOpen) {
            return;
        }
        closeClientsOptionsMenu();
    }

    function bindClientsOptionsMenu() {
        if (clientsOptionsMenuBound || !getClientsOptionsTrigger()) {
            return;
        }

        clientsOptionsMenuBound = true;
        document.addEventListener('click', handleClientsOptionsDocumentClick);
        document.addEventListener('keydown', handleClientsOptionsDocumentKeydown);
    }

    function getExpedienteOptionsTrigger() {
        return document.getElementById('aa-expediente-options-trigger');
    }

    function getExpedienteOptionsMenu() {
        return document.getElementById('aa-expediente-options-menu');
    }

    function closeExpedienteOptionsMenu() {
        var trigger = getExpedienteOptionsTrigger();
        var menu = getExpedienteOptionsMenu();

        if (menu) {
            menu.classList.add('hidden');
        }
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
        expedienteOptionsMenuOpen = false;
    }

    function openExpedienteOptionsMenu() {
        var trigger = getExpedienteOptionsTrigger();
        var menu = getExpedienteOptionsMenu();

        if (!menu || !trigger) {
            return;
        }

        menu.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        expedienteOptionsMenuOpen = true;
    }

    function toggleExpedienteOptionsMenu() {
        if (expedienteOptionsMenuOpen) {
            closeExpedienteOptionsMenu();
        } else {
            openExpedienteOptionsMenu();
        }
    }

    function openCreateRegistroModal(focusReturnEl) {
        if (window.AAAdmin
            && window.AAAdmin.ExpedienteRegistros
            && typeof window.AAAdmin.ExpedienteRegistros.openCreate === 'function') {
            window.AAAdmin.ExpedienteRegistros.openCreate(focusReturnEl || null);
            return;
        }
        console.error('[Clients] ExpedienteRegistros.openCreate no disponible');
    }

    function handleExpedienteOptionsDocumentClick(event) {
        var target = event && event.target;
        var trigger = target && target.closest
            ? target.closest('#aa-expediente-options-trigger')
            : null;
        var createItem = target && target.closest
            ? target.closest('[data-expediente-tool="create-registro"]')
            : null;
        var insideTools = target && target.closest
            ? target.closest('#aa-expediente-area-tools')
            : null;

        if (trigger) {
            event.preventDefault();
            toggleExpedienteOptionsMenu();
            return;
        }

        if (createItem) {
            event.preventDefault();
            closeExpedienteOptionsMenu();
            openCreateRegistroModal(getExpedienteOptionsTrigger());
            return;
        }

        if (expedienteOptionsMenuOpen && !insideTools) {
            closeExpedienteOptionsMenu();
        }
    }

    function handleExpedienteOptionsDocumentKeydown(event) {
        if (!event || event.key !== 'Escape' || !expedienteOptionsMenuOpen) {
            return;
        }
        closeExpedienteOptionsMenu();
    }

    function bindExpedienteOptionsMenu() {
        if (expedienteOptionsMenuBound) {
            return;
        }
        if (!document.getElementById('aa-expediente-root')) {
            return;
        }

        expedienteOptionsMenuBound = true;
        document.addEventListener('click', handleExpedienteOptionsDocumentClick);
        document.addEventListener('keydown', handleExpedienteOptionsDocumentKeydown);
    }

    /**
     * Configurar event listeners
     */
    function setupEventListeners() {
        const searchInput = document.getElementById('aa-clients-search');
        const prevButton = document.getElementById('aa-clients-prev');
        const nextButton = document.getElementById('aa-clients-next');

        bindClientsOptionsMenu();

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

        syncClientsActionBarVisibility();
    }

    /**
     * Muestra el buscador solo cuando hay al menos 3 clientes (inventario sin filtro).
     */
    function updateSearchVisibility() {
        const searchInput = document.getElementById('aa-clients-search');
        if (!searchInput) {
            return;
        }

        if (unfilteredClientTotal >= MIN_CLIENTS_FOR_SEARCH) {
            searchInput.classList.remove('hidden');
        } else {
            searchInput.classList.add('hidden');
            if (currentQuery !== '') {
                currentQuery = '';
                searchInput.value = '';
            }
        }

        syncClientsActionBarVisibility();
    }

    /**
     * Oculta la action bar (y su mb-4) si búsqueda y paginación no aportan UI.
     */
    function syncClientsActionBarVisibility() {
        const actionBar = document.getElementById('aa-clients-action-bar');
        if (!actionBar) {
            return;
        }

        const searchInput = document.getElementById('aa-clients-search');
        const paginationContainer = document.querySelector('.aa-clients-pagination');
        const searchVisible = !!(searchInput && !searchInput.classList.contains('hidden'));
        const paginationVisible = !!(
            paginationContainer
            && paginationContainer.style.display !== 'none'
        );

        if (searchVisible || paginationVisible) {
            actionBar.classList.remove('hidden');
            return;
        }

        actionBar.classList.add('hidden');
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
        const trigger = getClientsOptionsTrigger();

        if (!trigger) {
            return;
        }

        trigger.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        applyTemporaryHighlight(trigger);

        if (typeof trigger.focus === 'function') {
            window.setTimeout(function() {
                trigger.focus({ preventScroll: true });
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

                // Inventario real solo con query vacía (el total filtrado no cuenta)
                if (currentQuery === '') {
                    unfilteredClientTotal = parseInt(data.total, 10) || 0;
                }
                updateSearchVisibility();
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

    function renderExpedienteShell(root, contentNode, headerTitle) {
        // MC4c: liberar observer/firmas/caché del montaje anterior antes de
        // sustituir el shell (loading, error o cliente nuevo).
        if (window.AAAdmin
            && window.AAAdmin.ExpedienteRegistros
            && typeof window.AAAdmin.ExpedienteRegistros.destroy === 'function') {
            window.AAAdmin.ExpedienteRegistros.destroy();
        }
        root.innerHTML = '';
        var panel = document.createElement('div');
        panel.className = 'aa-expediente-panel bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden';

        var header = document.createElement('div');
        header.id = 'aa-expediente-section-header';
        header.className = 'px-4 py-5 bg-white rounded-t-xl';

        var headerInner = document.createElement('div');
        headerInner.className = 'flex items-center justify-between gap-3';

        var titleBlock = document.createElement('div');
        titleBlock.className = 'flex items-center min-w-0';

        var iconWrap = document.createElement('span');
        iconWrap.className = 'flex items-center justify-center w-8 h-8 text-gray-600 shrink-0';
        iconWrap.innerHTML = EXPEDIENTE_FOLDER_SVG;

        var titleWrap = document.createElement('div');
        titleWrap.className = 'min-w-0';
        var title = document.createElement('h3');
        title.className = 'text-lg font-semibold text-gray-600 truncate';
        title.textContent = headerTitle || 'Expediente';
        titleWrap.appendChild(title);

        titleBlock.appendChild(iconWrap);
        titleBlock.appendChild(titleWrap);

        var tools = document.createElement('div');
        tools.id = 'aa-expediente-area-tools';
        tools.className = 'relative shrink-0';
        tools.innerHTML = ''
            + '<button type="button"'
            + ' id="aa-expediente-options-trigger"'
            + ' title="Opciones de expediente"'
            + ' aria-label="Opciones de expediente"'
            + ' aria-haspopup="menu"'
            + ' aria-expanded="false"'
            + ' class="aa-options-trigger-flat">'
            + '<svg class="w-6 h-6 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            + '<circle cx="5" cy="12" r="1.75"/>'
            + '<circle cx="12" cy="12" r="1.75"/>'
            + '<circle cx="19" cy="12" r="1.75"/>'
            + '</svg>'
            + '</button>'
            + '<div id="aa-expediente-options-menu"'
            + ' class="hidden absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"'
            + ' role="menu">'
            + '<button type="button" role="menuitem"'
            + ' data-expediente-tool="create-registro"'
            + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50">'
            + '<span>Nuevo registro</span>'
            + '</button>'
            + '</div>';

        headerInner.appendChild(titleBlock);
        headerInner.appendChild(tools);
        header.appendChild(headerInner);
        panel.appendChild(header);

        var body = document.createElement('div');
        body.className = 'p-4 aa-expediente-body';
        body.appendChild(contentNode);
        panel.appendChild(body);

        root.appendChild(panel);
        bindExpedienteOptionsMenu();
    }

    function renderExpedienteLoading(root) {
        var p = document.createElement('p');
        p.className = 'text-sm text-gray-500';
        p.textContent = 'Cargando expediente...';
        renderExpedienteShell(root, p, 'Expediente');
    }

    function renderExpedienteError(root, message) {
        var wrap = document.createElement('div');
        wrap.className = 'aa-expediente-error space-y-3';

        var title = document.createElement('h3');
        title.className = 'text-lg font-semibold text-gray-600';
        title.textContent = 'No se pudo abrir el expediente';

        var msg = document.createElement('p');
        msg.className = 'text-sm text-gray-600';
        msg.textContent = message || 'Cliente no encontrado.';

        wrap.appendChild(title);
        wrap.appendChild(msg);
        renderExpedienteShell(root, wrap, 'Expediente');
    }

    function renderExpedienteContent(root, cliente) {
        var wrap = document.createElement('div');
        wrap.className = 'aa-expediente-content space-y-4';

        var records = document.createElement('div');
        records.id = 'aa-expediente-registros';
        records.className = 'aa-expediente-registros';

        wrap.appendChild(records);

        renderExpedienteShell(root, wrap, 'Expediente de ' + (cliente.nombre || 'Sin nombre'));

        mountExpedienteRegistros(cliente.id, records, null);
    }

    function buildExpedienteRegistrosTransport() {
        var data = getClientsData();
        var nonces = getClientsNonces();
        var actions = data.actions || {};
        return {
            ajaxUrl: data.ajaxUrl || window.ajaxurl || '',
            nonce: nonces.expediente_registros || '',
            actions: {
                listRegistros: actions.listRegistros || 'aa_list_expediente_registros',
                createRegistro: actions.createRegistro || 'aa_create_expediente_registro',
                updateRegistro: actions.updateRegistro || 'aa_update_expediente_registro',
                deleteRegistro: actions.deleteRegistro || 'aa_delete_expediente_registro',
                attachRegistro: actions.attachRegistro || 'aa_attach_expediente_registro',
                signAdjuntoRead: actions.signAdjuntoRead || 'aa_sign_expediente_adjunto_read',
                deleteAdjunto: actions.deleteAdjunto || 'aa_delete_expediente_adjunto'
            }
        };
    }

    /**
     * Sesión D1 del montaje legacy vigente: recordId → expedienteId + guard nav.
     * null fuera de un montaje vivo.
     * @type {{
     *   alive: boolean,
     *   navigationScheduled: boolean,
     *   recordToExpediente: Object<number, number>
     * }|null}
     */
    var expedienteCreateNavSession = null;

    /**
     * Entero positivo estricto (sin coerciones permisivas).
     * @param {*} value
     * @returns {boolean}
     */
    function isStrictPositiveInt(value) {
        return typeof value === 'number'
            && Number.isFinite(value)
            && Math.floor(value) === value
            && value > 0;
    }

    /**
     * Construye URL de detalle canónico desde base PHP + expediente_id.
     * @param {number} expedienteId
     * @returns {string} vacío si base inválida
     */
    function buildCanonicalDetailUrl(expedienteId) {
        if (!isStrictPositiveInt(expedienteId)) {
            return '';
        }
        var base = getClientsData().detailCanonicalBaseUrl;
        if (typeof base !== 'string' || base.trim() === '') {
            return '';
        }
        try {
            var url = new URL(base, window.location.href);
            if (url.searchParams.get('action') !== 'aa_iframe_content') {
                return '';
            }
            if (url.searchParams.get('module') !== 'expedientes') {
                return '';
            }
            if (url.searchParams.get('view') !== 'detail') {
                return '';
            }
            url.searchParams.delete('client_id');
            url.searchParams.delete('records_page');
            url.searchParams.set('expediente_id', String(expedienteId));
            return url.toString();
        } catch (err) {
            console.error('[Clients] buildCanonicalDetailUrl failed:', err);
            return '';
        }
    }

    function invalidateExpedienteCreateNavSession() {
        if (expedienteCreateNavSession) {
            expedienteCreateNavSession.alive = false;
            expedienteCreateNavSession.navigationScheduled = true;
            expedienteCreateNavSession.recordToExpediente = Object.create(null);
        }
        expedienteCreateNavSession = null;
    }

    /**
     * @param {{alive:boolean, navigationScheduled:boolean, recordToExpediente:Object}} session
     * @param {{recordId?:*, imageOutcome?:string}|null} payload
     */
    function handleLegacyCreateComplete(session, payload) {
        if (!session || !session.alive || session.navigationScheduled) {
            return;
        }
        var recordId = payload && payload.recordId;
        if (!isStrictPositiveInt(recordId)) {
            return;
        }
        var expedienteId = session.recordToExpediente[recordId];
        delete session.recordToExpediente[recordId];
        if (!isStrictPositiveInt(expedienteId)) {
            return;
        }
        var canonicalUrl = buildCanonicalDetailUrl(expedienteId);
        if (!canonicalUrl) {
            return;
        }
        session.navigationScheduled = true;
        try {
            window.location.replace(canonicalUrl);
        } catch (err) {
            console.error('[Clients] canonical navigation failed:', err);
        }
    }

    /**
     * Ports legacy: cierran sobre clientId + transport del montaje.
     * Devuelven { httpStatus, result } como el monolito pre-B1.
     * El port create captura recordId→expedienteId sin alterar el envelope (D1).
     *
     * @param {number} clientId
     * @param {{ajaxUrl:string, nonce:string, actions:Object<string,string>}} transport
     * @param {{alive:boolean, recordToExpediente:Object}} session
     */
    function buildExpedienteRegistrosLegacyPorts(clientId, transport, session) {
        var ajaxUrl = transport.ajaxUrl;
        var nonce = transport.nonce;
        var actions = transport.actions || {};
        var clientIdStr = String(clientId);

        function postJsonForm(action, fields) {
            var formData = new FormData();
            formData.append('action', action);
            formData.append('_wpnonce', nonce);
            Object.keys(fields).forEach(function (key) {
                formData.append(key, fields[key]);
            });
            return fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (result) {
                    return { httpStatus: response.status, result: result };
                });
            });
        }

        return {
            list: function () {
                return postJsonForm(actions.listRegistros, {
                    client_id: clientIdStr
                });
            },
            create: function (draft) {
                draft = draft || {};
                return postJsonForm(actions.createRegistro, {
                    client_id: clientIdStr,
                    title: draft.title,
                    body: draft.body
                }).then(function (envelope) {
                    try {
                        var result = envelope && envelope.result;
                        var data = result && result.success ? result.data : null;
                        var record = data && data.record ? data.record : null;
                        var recordId = record ? record.id : null;
                        var expedienteId = data ? data.expediente_id : null;
                        if (session
                            && session.alive
                            && isStrictPositiveInt(recordId)
                            && isStrictPositiveInt(expedienteId)) {
                            session.recordToExpediente[recordId] = expedienteId;
                        }
                    } catch (err) {
                        console.error('[Clients] create nav capture failed:', err);
                    }
                    return envelope;
                });
            },
            update: function (recordId, draft) {
                draft = draft || {};
                return postJsonForm(actions.updateRegistro, {
                    client_id: clientIdStr,
                    record_id: String(recordId),
                    title: draft.title,
                    body: draft.body
                });
            },
            deleteRegistro: function (recordId) {
                return postJsonForm(actions.deleteRegistro, {
                    client_id: clientIdStr,
                    record_id: String(recordId)
                });
            },
            attach: function (recordId, fileBlob, uploadOperationId) {
                var formData = new FormData();
                formData.append('action', actions.attachRegistro);
                formData.append('_wpnonce', nonce);
                formData.append('client_id', clientIdStr);
                formData.append('record_id', String(recordId || ''));
                formData.append('upload_operation_id', uploadOperationId);
                formData.append('file', fileBlob, 'adjunto.jpg');
                return fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json().then(function (result) {
                        return { httpStatus: response.status, result: result };
                    });
                });
            },
            signRead: function (recordId, attachmentId, variant, signal) {
                var formData = new FormData();
                formData.append('action', actions.signAdjuntoRead);
                formData.append('_wpnonce', nonce);
                formData.append('client_id', clientIdStr);
                formData.append('record_id', String(recordId));
                formData.append('attachment_id', String(attachmentId));
                formData.append('variant', variant);
                var options = {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                };
                if (signal) {
                    options.signal = signal;
                }
                return fetch(ajaxUrl, options).then(function (response) {
                    return response.json().then(function (result) {
                        return { httpStatus: response.status, result: result };
                    });
                });
            },
            deleteAdjunto: function (recordId, attachmentId) {
                return postJsonForm(actions.deleteAdjunto, {
                    client_id: clientIdStr,
                    record_id: String(recordId),
                    attachment_id: String(attachmentId)
                });
            }
        };
    }

    function mountExpedienteRegistros(clientId, recordsRoot, actionsRoot) {
        invalidateExpedienteCreateNavSession();
        var session = {
            alive: true,
            navigationScheduled: false,
            recordToExpediente: Object.create(null)
        };
        expedienteCreateNavSession = session;

        function tryMount(attemptsLeft) {
            if (window.AAAdmin && window.AAAdmin.ExpedienteRegistros && typeof window.AAAdmin.ExpedienteRegistros.init === 'function') {
                if (!session.alive) {
                    return;
                }
                var transport = buildExpedienteRegistrosTransport();
                window.AAAdmin.ExpedienteRegistros.init({
                    clientId: clientId,
                    recordsRoot: recordsRoot,
                    actionsRoot: actionsRoot || null,
                    transport: transport,
                    ports: buildExpedienteRegistrosLegacyPorts(clientId, transport, session),
                    onCreateComplete: function (payload) {
                        handleLegacyCreateComplete(session, payload);
                    }
                });
                return;
            }
            if (attemptsLeft <= 0) {
                var fallback = document.createElement('p');
                fallback.className = 'text-sm text-gray-500';
                fallback.textContent = 'Aún no hay registros en este expediente';
                recordsRoot.appendChild(fallback);
                console.error('[Clients] ExpedienteRegistros no disponible');
                return;
            }
            window.setTimeout(function () {
                tryMount(attemptsLeft - 1);
            }, 30);
        }
        tryMount(40);
    }

    function fetchClienteById(clientId) {
        var data = getClientsData();
        var nonces = getClientsNonces();
        var ajaxurl = data.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        var action = (data.actions && data.actions.getCliente) || 'aa_get_cliente';

        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', nonces.get_cliente || '');
        formData.append('client_id', String(clientId));

        return fetch(ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function(response) {
            return response.json().then(function(result) {
                return { httpStatus: response.status, result: result };
            });
        });
    }

    function initExpedienteView() {
        var root = document.getElementById('aa-expediente-root');
        var listRoot = document.getElementById('aa-clients-list-root');
        if (!root) {
            console.error('[Clients] #aa-expediente-root no encontrado');
            return;
        }

        if (listRoot) {
            listRoot.classList.add('hidden');
        }
        root.classList.remove('hidden');

        var data = getClientsData();
        var clientId = parseInt(data.clientId, 10);
        if (!(clientId > 0)) {
            renderExpedienteError(root, 'Identificador de cliente no válido.');
            return;
        }

        renderExpedienteLoading(root);

        fetchClienteById(clientId)
            .then(function(payload) {
                var result = payload.result;
                if (result && result.success && result.data) {
                    renderExpedienteContent(root, result.data);
                    return;
                }

                var message = 'Cliente no encontrado.';
                if (result && result.data && result.data.message) {
                    message = String(result.data.message);
                }
                renderExpedienteError(root, message);
            })
            .catch(function(error) {
                console.error('[Clients] Error al cargar expediente:', error);
                renderExpedienteError(root, 'No se pudo cargar el expediente. Inténtalo de nuevo.');
            });
    }

    function initListView() {
        var root = document.getElementById('aa-expediente-root');
        var listRoot = document.getElementById('aa-clients-list-root');
        if (listRoot) {
            listRoot.classList.remove('hidden');
        }
        if (root) {
            root.classList.add('hidden');
            root.innerHTML = '';
        }

        renderActionBar();
        bindClientsOptionsMenu();
        applySetupFocusFromUrl();
        searchClients();

        document.addEventListener('aa:client:saved', function() {
            currentOffset = 0;
            searchClients();
        });
    }

    /**
     * Inicializar módulo
     */
    function init() {
        if (isExpedienteView()) {
            initExpedienteView();
            return;
        }

        initListView();
    }

    /**
     * Habilitar la UX de Expedientes. El servidor sigue siendo la autoridad
     * (URL/AJAX fail-closed); esto solo refleja una confirmación `full` viva.
     */
    function enableExpedienteButtons() {
        var data = getClientsData();
        data.expedienteAccessAllowed = true;
        var buttons = document.querySelectorAll('.aa-btn-expediente-cliente');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            btn.disabled = false;
            btn.removeAttribute('aria-disabled');
            btn.title = 'Abrir expediente';
        }
    }

    // Reaccionar a la proyección asíncrona de acceso al shell: solo `full`
    // habilita los botones existentes; los creados después leen el flag ya vivo.
    document.addEventListener('aa:shell-access-resolved', function(ev) {
        if (ev && ev.detail && ev.detail.access === 'full') {
            enableExpedienteButtons();
        }
    });

    // Escuchar DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Clients module loaded');
        init();
    });

    window.AAAdmin = window.AAAdmin || {};
    window.AAAdmin.ClientsModule = window.AAAdmin.ClientsModule || {};
    window.AAAdmin.ClientsModule.__test__ = {
        isStrictPositiveInt: isStrictPositiveInt,
        buildCanonicalDetailUrl: buildCanonicalDetailUrl,
        buildExpedienteRegistrosLegacyPorts: buildExpedienteRegistrosLegacyPorts,
        handleLegacyCreateComplete: handleLegacyCreateComplete,
        invalidateExpedienteCreateNavSession: invalidateExpedienteCreateNavSession,
        mountExpedienteRegistros: mountExpedienteRegistros,
        getCreateNavSession: function () {
            return expedienteCreateNavSession;
        }
    };

})();
