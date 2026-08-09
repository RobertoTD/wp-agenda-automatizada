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
        name.className = 'min-w-0 truncate';
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

    function renderExpedienteShell(root, contentNode) {
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
        header.className = 'px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white rounded-t-xl';

        var headerInner = document.createElement('div');
        headerInner.className = 'flex items-center';

        var iconWrap = document.createElement('span');
        iconWrap.className = 'flex items-center justify-center w-8 h-8 text-gray-600';
        iconWrap.innerHTML = EXPEDIENTE_FOLDER_SVG;

        var titleWrap = document.createElement('div');
        var title = document.createElement('h3');
        title.className = 'text-lg font-semibold text-gray-600';
        title.textContent = 'Expediente';
        titleWrap.appendChild(title);

        headerInner.appendChild(iconWrap);
        headerInner.appendChild(titleWrap);
        header.appendChild(headerInner);
        panel.appendChild(header);

        var body = document.createElement('div');
        body.className = 'p-4 aa-expediente-body';
        body.appendChild(contentNode);
        panel.appendChild(body);

        root.appendChild(panel);
    }

    function renderExpedienteLoading(root) {
        var p = document.createElement('p');
        p.className = 'text-sm text-gray-500';
        p.textContent = 'Cargando expediente...';
        renderExpedienteShell(root, p);
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
        renderExpedienteShell(root, wrap);
    }

    function renderExpedienteContent(root, cliente) {
        var wrap = document.createElement('div');
        wrap.className = 'aa-expediente-content space-y-4';

        var titleRow = document.createElement('div');
        titleRow.className = 'aa-expediente-title-row flex items-center justify-between gap-3';

        var name = document.createElement('h2');
        name.className = 'text-xl font-semibold text-gray-600 min-w-0';
        name.textContent = cliente.nombre || 'Sin nombre';

        var actions = document.createElement('div');
        actions.id = 'aa-expediente-actions';
        actions.className = 'aa-expediente-actions shrink-0';

        titleRow.appendChild(name);
        titleRow.appendChild(actions);

        var records = document.createElement('div');
        records.id = 'aa-expediente-registros';
        records.className = 'aa-expediente-registros mt-4 pt-4';

        wrap.appendChild(titleRow);
        wrap.appendChild(records);

        renderExpedienteShell(root, wrap);

        mountExpedienteRegistros(cliente.id, records, actions);
    }

    function mountExpedienteRegistros(clientId, recordsRoot, actionsRoot) {
        function tryMount(attemptsLeft) {
            if (window.AAAdmin && window.AAAdmin.ExpedienteRegistros && typeof window.AAAdmin.ExpedienteRegistros.init === 'function') {
                window.AAAdmin.ExpedienteRegistros.init({
                    clientId: clientId,
                    recordsRoot: recordsRoot,
                    actionsRoot: actionsRoot || null
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

})();
