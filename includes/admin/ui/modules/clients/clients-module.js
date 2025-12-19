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
     * Crear contenido del formulario para nuevo cliente
     * @returns {HTMLElement} - Elemento del formulario
     */
    function createNewClientForm() {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-cliente';
        form.className = 'aa-modal-form';

        // Campo: Nombre
        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';
        
        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-cliente-nombre');
        nombreLabel.textContent = 'Nombre completo *';
        
        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-cliente-nombre';
        nombreInput.name = 'nombre';
        nombreInput.required = true;
        nombreInput.placeholder = 'Ej: Juan Pérez';
        
        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        // Campo: Teléfono
        const telefonoGroup = document.createElement('div');
        telefonoGroup.className = 'aa-form-group';
        
        const telefonoLabel = document.createElement('label');
        telefonoLabel.setAttribute('for', 'modal-cliente-telefono');
        telefonoLabel.textContent = 'Teléfono *';
        
        const telefonoInput = document.createElement('input');
        telefonoInput.type = 'tel';
        telefonoInput.id = 'modal-cliente-telefono';
        telefonoInput.name = 'telefono';
        telefonoInput.required = true;
        telefonoInput.placeholder = 'Ej: 5512345678';
        
        telefonoGroup.appendChild(telefonoLabel);
        telefonoGroup.appendChild(telefonoInput);

        // Campo: Correo
        const correoGroup = document.createElement('div');
        correoGroup.className = 'aa-form-group';
        
        const correoLabel = document.createElement('label');
        correoLabel.setAttribute('for', 'modal-cliente-correo');
        correoLabel.textContent = 'Correo electrónico *';
        
        const correoInput = document.createElement('input');
        correoInput.type = 'email';
        correoInput.id = 'modal-cliente-correo';
        correoInput.name = 'correo';
        correoInput.required = true;
        correoInput.placeholder = 'Ej: cliente@email.com';
        
        correoGroup.appendChild(correoLabel);
        correoGroup.appendChild(correoInput);

        // Mensaje de estado (para errores/éxito)
        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-cliente-status';
        statusMsg.className = 'aa-form-status';
        statusMsg.style.display = 'none';

        // Ensamblar formulario
        form.appendChild(nombreGroup);
        form.appendChild(telefonoGroup);
        form.appendChild(correoGroup);
        form.appendChild(statusMsg);

        return form;
    }

    /**
     * Crear footer del modal con botones
     * @returns {HTMLElement} - Elemento del footer
     */
    function createModalFooter() {
        const footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        // Botón Cancelar
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        // Botón Guardar
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.id = 'aa-modal-save-cliente';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Cliente';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    /**
     * Mostrar mensaje de estado en el formulario
     */
    function showFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-cliente-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    /**
     * Guardar cliente via AJAX
     */
    function saveNewClient() {
        const form = document.getElementById('aa-modal-form-cliente');
        if (!form) return;

        const nombre = document.getElementById('modal-cliente-nombre').value.trim();
        const telefono = document.getElementById('modal-cliente-telefono').value.trim();
        const correo = document.getElementById('modal-cliente-correo').value.trim();

        // Validación básica
        if (!nombre || !telefono || !correo) {
            showFormStatus('Todos los campos son obligatorios.', true);
            return;
        }

        // Validar formato de correo
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(correo)) {
            showFormStatus('El correo electrónico no es válido.', true);
            return;
        }

        // Obtener nonce
        const nonce = window.AA_CLIENTS_NONCES ? window.AA_CLIENTS_NONCES.crear_cliente : '';
        if (!nonce) {
            showFormStatus('Error de seguridad: nonce no disponible.', true);
            return;
        }

        // Preparar datos
        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'aa_crear_cliente');
        formData.append('_ajax_nonce', nonce);
        formData.append('nombre', nombre);
        formData.append('telefono', telefono);
        formData.append('correo', correo);

        // Deshabilitar botón mientras se procesa
        const saveBtn = document.getElementById('aa-modal-save-cliente');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
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
            if (result.success) {
                showFormStatus('Cliente guardado correctamente.', false);
                
                // Cerrar modal después de 1 segundo y recargar lista
                setTimeout(function() {
                    if (window.AAAdmin && window.AAAdmin.closeModal) {
                        window.AAAdmin.closeModal();
                    }
                    // Recargar lista de clientes
                    currentOffset = 0;
                    searchClients();
                }, 1000);
            } else {
                const errorMsg = result.data && result.data.message 
                    ? result.data.message 
                    : 'Error al guardar el cliente.';
                showFormStatus(errorMsg, true);
                
                // Rehabilitar botón
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar Cliente';
                }
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            showFormStatus('Error de conexión. Intenta de nuevo.', true);
            
            // Rehabilitar botón
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Cliente';
            }
        });
    }

    /**
     * Abrir modal de nuevo cliente
     */
    function openNewClientModal() {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        const formContent = createNewClientForm();
        const footerContent = createModalFooter();

        window.AAAdmin.openModal({
            title: 'Nuevo Cliente',
            body: formContent,
            footer: footerContent
        });

        // Agregar event listener al botón guardar después de que el modal esté abierto
        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-cliente');
            if (saveBtn) {
                saveBtn.addEventListener('click', saveNewClient);
            }

            // Focus en primer campo
            const nombreInput = document.getElementById('modal-cliente-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    /**
     * Crear contenido del formulario para editar cliente
     * @param {Object} cliente - Datos del cliente a editar
     * @returns {HTMLElement} - Elemento del formulario
     */
    function createEditClientForm(cliente) {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-editar-cliente';
        form.className = 'aa-modal-form';

        // Campo oculto: ID del cliente
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.id = 'modal-editar-cliente-id';
        idInput.name = 'cliente_id';
        idInput.value = cliente.id || '';
        form.appendChild(idInput);

        // Campo: Nombre
        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';
        
        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-editar-cliente-nombre');
        nombreLabel.textContent = 'Nombre completo *';
        
        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-editar-cliente-nombre';
        nombreInput.name = 'nombre';
        nombreInput.required = true;
        nombreInput.value = cliente.nombre || '';
        nombreInput.placeholder = 'Ej: Juan Pérez';
        
        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        // Campo: Teléfono
        const telefonoGroup = document.createElement('div');
        telefonoGroup.className = 'aa-form-group';
        
        const telefonoLabel = document.createElement('label');
        telefonoLabel.setAttribute('for', 'modal-editar-cliente-telefono');
        telefonoLabel.textContent = 'Teléfono *';
        
        const telefonoInput = document.createElement('input');
        telefonoInput.type = 'tel';
        telefonoInput.id = 'modal-editar-cliente-telefono';
        telefonoInput.name = 'telefono';
        telefonoInput.required = true;
        telefonoInput.value = cliente.telefono || '';
        telefonoInput.placeholder = 'Ej: 5512345678';
        
        telefonoGroup.appendChild(telefonoLabel);
        telefonoGroup.appendChild(telefonoInput);

        // Campo: Correo
        const correoGroup = document.createElement('div');
        correoGroup.className = 'aa-form-group';
        
        const correoLabel = document.createElement('label');
        correoLabel.setAttribute('for', 'modal-editar-cliente-correo');
        correoLabel.textContent = 'Correo electrónico *';
        
        const correoInput = document.createElement('input');
        correoInput.type = 'email';
        correoInput.id = 'modal-editar-cliente-correo';
        correoInput.name = 'correo';
        correoInput.required = true;
        correoInput.value = cliente.correo || '';
        correoInput.placeholder = 'Ej: cliente@email.com';
        
        correoGroup.appendChild(correoLabel);
        correoGroup.appendChild(correoInput);

        // Mensaje de estado (para errores/éxito)
        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-editar-cliente-status';
        statusMsg.className = 'aa-form-status';
        statusMsg.style.display = 'none';

        // Ensamblar formulario
        form.appendChild(nombreGroup);
        form.appendChild(telefonoGroup);
        form.appendChild(correoGroup);
        form.appendChild(statusMsg);

        return form;
    }

    /**
     * Crear footer del modal de edición con botones
     * @returns {HTMLElement} - Elemento del footer
     */
    function createEditModalFooter() {
        const footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        // Botón Cancelar
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        // Botón Guardar
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.id = 'aa-modal-save-editar-cliente';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Cambios';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    /**
     * Mostrar mensaje de estado en el formulario de edición
     */
    function showEditFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-editar-cliente-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    /**
     * Guardar cliente editado via AJAX
     */
    function saveEditedClient() {
        const form = document.getElementById('aa-modal-form-editar-cliente');
        if (!form) return;

        const clienteId = document.getElementById('modal-editar-cliente-id').value;
        const nombre = document.getElementById('modal-editar-cliente-nombre').value.trim();
        const telefono = document.getElementById('modal-editar-cliente-telefono').value.trim();
        const correo = document.getElementById('modal-editar-cliente-correo').value.trim();

        // Validación básica
        if (!clienteId || !nombre || !telefono || !correo) {
            showEditFormStatus('Todos los campos son obligatorios.', true);
            return;
        }

        // Validar formato de correo
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(correo)) {
            showEditFormStatus('El correo electrónico no es válido.', true);
            return;
        }

        // Obtener nonce
        const nonce = window.AA_CLIENTS_NONCES ? window.AA_CLIENTS_NONCES.editar_cliente : '';
        if (!nonce) {
            showEditFormStatus('Error de seguridad: nonce no disponible.', true);
            return;
        }

        // Preparar datos
        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'aa_editar_cliente');
        formData.append('_ajax_nonce', nonce);
        formData.append('cliente_id', clienteId);
        formData.append('nombre', nombre);
        formData.append('telefono', telefono);
        formData.append('correo', correo);

        // Deshabilitar botón mientras se procesa
        const saveBtn = document.getElementById('aa-modal-save-editar-cliente');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
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
            if (result.success) {
                showEditFormStatus('Cliente actualizado correctamente.', false);
                
                // Cerrar modal después de 1 segundo y recargar lista
                setTimeout(function() {
                    if (window.AAAdmin && window.AAAdmin.closeModal) {
                        window.AAAdmin.closeModal();
                    }
                    // Recargar lista de clientes
                    searchClients();
                }, 1000);
            } else {
                const errorMsg = result.data && result.data.message 
                    ? result.data.message 
                    : 'Error al actualizar el cliente.';
                showEditFormStatus(errorMsg, true);
                
                // Rehabilitar botón
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar Cambios';
                }
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            showEditFormStatus('Error de conexión. Intenta de nuevo.', true);
            
            // Rehabilitar botón
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Cambios';
            }
        });
    }

    /**
     * Abrir modal de editar cliente
     * @param {Object} cliente - Datos del cliente a editar
     */
    function openEditClientModal(cliente) {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        const formContent = createEditClientForm(cliente);
        const footerContent = createEditModalFooter();

        window.AAAdmin.openModal({
            title: 'Editar Cliente',
            body: formContent,
            footer: footerContent
        });

        // Agregar event listener al botón guardar después de que el modal esté abierto
        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-editar-cliente');
            if (saveBtn) {
                saveBtn.addEventListener('click', saveEditedClient);
            }

            // Focus en primer campo
            const nombreInput = document.getElementById('modal-editar-cliente-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

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

        // Botón de editar
        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'aa-btn-editar-cliente';
        editButton.title = 'Editar cliente';
        editButton.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Editar';
        
        // Event listener para abrir modal de edición
        editButton.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            openEditClientModal(cliente);
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

        // Botón "+ Nuevo" abre modal
        if (newButton) {
            newButton.addEventListener('click', function(event) {
                event.preventDefault();
                openNewClientModal();
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

        // Siempre cargar datos via AJAX (ordenados por total_citas DESC)
        searchClients();
    }

    // Escuchar DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Clients module loaded');
        init();
    });

})();

