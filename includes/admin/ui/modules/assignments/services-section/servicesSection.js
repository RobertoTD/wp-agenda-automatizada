/**
 * Services Section - Services Management
 * 
 * Handles UI logic for managing services
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let servicesRoot = null;
    
    // Flag to track if save/delete handlers are already bound
    let saveDeleteHandlersBound = false;

    /**
     * Initialize the services section
     */
    function initServicesSection() {
        servicesRoot = document.getElementById('aa-services-root');
        
        // Fail safely if root doesn't exist
        if (!servicesRoot) {
            console.warn('[Services Section] Root element #aa-services-root not found');
            return;
        }

        // Setup save and delete handlers (only once)
        setupSaveDeleteHandlers();
        
        // Load and render services
        loadServices(servicesRoot);
        
        // Setup create service button handler
        setupCreateServiceHandler();
    }

    /**
     * Load services from server via AJAX
     * @param {HTMLElement} root - The root container element
     */
    function loadServices(root) {
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_get_services_db');

        // Show loading state
        root.innerHTML = '<p class="text-sm text-gray-500">Cargando servicios...</p>';

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.services) {
                renderServices(root, data.data.services);
            } else {
                console.error('[Services Section] Error en respuesta:', data);
                root.innerHTML = '<p class="text-sm text-red-500">Error al cargar los servicios.</p>';
            }
        })
        .catch(function(error) {
            console.error('[Services Section] Error en petición AJAX:', error);
            root.innerHTML = '<p class="text-sm text-red-500">Error al conectar con el servidor.</p>';
        });
    }

    /**
     * Render services list
     * @param {HTMLElement} root - The root container element
     * @param {Array} servicesList - Array of service objects
     */
    function renderServices(root, servicesList) {
        if (!servicesList || servicesList.length === 0) {
            root.innerHTML = '<p class="text-sm text-gray-500">No hay servicios registrados.</p>';
            return;
        }

        // Build HTML for services list
        let html = '<ul class="space-y-2">';
        
        servicesList.forEach(function(service) {
            const isActive = parseInt(service.active) === 1;
            const serviceId = parseInt(service.id);
            
            html += '<li class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">';
            // Main content row
            html += '<div class="flex items-center gap-2 p-3">';
            html += '<span class="flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 text-purple-600 flex-shrink-0">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>';
            html += '</svg>';
            html += '</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(service.name) + '</span>';
            // Toggle details button (chevron)
            html += '<button type="button" class="aa-service-toggle-details inline-flex items-center justify-center w-6 h-6 text-gray-500 hover:text-gray-700 transition-colors" data-service-id="' + serviceId + '" title="Ver detalles">';
            html += '<svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';
            html += '</svg>';
            html += '</button>';
            // Toggle switch
            html += '<div class="ml-auto relative">';
            html += '<label class="flex items-center cursor-pointer">';
            html += '<input type="checkbox" ';
            html += 'class="toggle-service-active peer sr-only" ';
            html += 'data-id="' + serviceId + '" ';
            html += 'data-active="' + service.active + '" ';
            if (isActive) {
                html += 'checked ';
            }
            html += '/>';
            html += '<div class="w-11 h-6 bg-gray-300 peer-checked:bg-blue-500 rounded-full transition-colors duration-200"></div>';
            html += '<div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:translate-x-5"></div>';
            html += '</label>';
            html += '</div>';
            html += '</div>';
            // Collapsable details panel
            html += '<div class="aa-service-details-panel hidden border-t border-gray-200 p-3" data-service-id="' + serviceId + '">';
            html += renderServiceDetails(service);
            html += '</div>';
            html += '</li>';
        });
        
        html += '</ul>';

        root.innerHTML = html;
        
        // Setup toggle handlers after rendering
        setupToggleHandlers();
        
        // Setup details panel toggle handlers
        setupDetailsPanelHandlers();
        
        // Note: save/delete handlers are set up once in initServicesSection()
        // to avoid duplicate listeners on re-render
    }

    /**
     * Setup handlers for toggle switches
     */
    function setupToggleHandlers() {
        const toggles = document.querySelectorAll('.toggle-service-active');
        
        toggles.forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                handleToggleChange(this);
            });
        });
    }

    /**
     * Handle toggle change event
     * @param {HTMLElement} toggle - The toggle checkbox element
     */
    function handleToggleChange(toggle) {
        const serviceId = parseInt(toggle.getAttribute('data-id'));
        const previousActive = parseInt(toggle.getAttribute('data-active'));
        const newActive = toggle.checked ? 1 : 0;
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_toggle_service');
        formData.append('id', serviceId);
        formData.append('active', newActive);

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Update data-active attribute
                toggle.setAttribute('data-active', newActive);
            } else {
                // Revert toggle state on error
                toggle.checked = previousActive === 1;
                console.error('[Services Section] Error al actualizar servicio:', data);
            }
        })
        .catch(function(error) {
            // Revert toggle state on error
            toggle.checked = previousActive === 1;
            console.error('[Services Section] Error en petición AJAX:', error);
        });
    }

    /**
     * Setup handlers for details panel toggle (chevron button)
     */
    function setupDetailsPanelHandlers() {
        const toggleButtons = document.querySelectorAll('.aa-service-toggle-details');
        
        toggleButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const serviceId = this.getAttribute('data-service-id');
                const panel = document.querySelector('.aa-service-details-panel[data-service-id="' + serviceId + '"]');
                const chevron = this.querySelector('svg');
                
                if (panel) {
                    // Toggle panel visibility
                    panel.classList.toggle('hidden');
                    
                    // Rotate chevron
                    if (panel.classList.contains('hidden')) {
                        chevron.classList.remove('rotate-90');
                    } else {
                        chevron.classList.add('rotate-90');
                    }
                }
            });
        });
    }

    /**
     * Handle click events on services root (event delegation)
     * @param {Event} event - Click event
     */
    function onServicesRootClick(event) {
        const target = event.target.closest('.aa-service-save, .aa-service-delete');
        if (!target) return;
        
        const serviceId = parseInt(target.getAttribute('data-service-id'));
        if (!serviceId || serviceId <= 0) return;
        
        if (target.classList.contains('aa-service-save')) {
            event.preventDefault();
            saveService(serviceId);
        } else if (target.classList.contains('aa-service-delete')) {
            event.preventDefault();
            deleteService(serviceId);
        }
    }

    /**
     * Setup handlers for save and delete buttons
     * Only registers once to avoid duplicate listeners
     */
    function setupSaveDeleteHandlers() {
        // Prevent duplicate registration
        if (saveDeleteHandlersBound) {
            return;
        }
        
        // Use event delegation on the root element to handle dynamically added buttons
        if (!servicesRoot) {
            console.warn('[Services Section] Cannot setup save/delete handlers: servicesRoot not found');
            return;
        }
        
        servicesRoot.addEventListener('click', onServicesRootClick);
        saveDeleteHandlersBound = true;
    }

    /**
     * Save a service
     * @param {number} serviceId - ID of the service to save
     */
    function saveService(serviceId) {
        // Get input values
        const codeInput = document.getElementById('aa-service-code-' + serviceId);
        const priceInput = document.getElementById('aa-service-price-' + serviceId);
        const descriptionInput = document.getElementById('aa-service-description-' + serviceId);
        
        if (!codeInput || !priceInput || !descriptionInput) {
            console.warn('[Services Section] Inputs not found for service ID:', serviceId);
            return;
        }
        
        const code = codeInput.value.trim();
        const price = priceInput.value.trim();
        const description = descriptionInput.value.trim();
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_update_service_db');
        formData.append('id', serviceId);
        formData.append('code', code);
        formData.append('price', price);
        formData.append('description', description);

        // Disable button during request
        const saveButton = document.querySelector('.aa-service-save[data-service-id="' + serviceId + '"]');
        const originalButtonText = saveButton ? saveButton.textContent : '';
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = 'Guardando...';
        }

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                console.log('[Services Section] Servicio actualizado correctamente');
                // Reload the list of services
                if (servicesRoot) {
                    loadServices(servicesRoot);
                }
            } else {
                console.error('[Services Section] Error al actualizar servicio:', data);
            }
        })
        .catch(function(error) {
            console.error('[Services Section] Error en petición AJAX:', error);
        })
        .finally(function() {
            // Re-enable button
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.textContent = originalButtonText;
            }
        });
    }

    /**
     * Delete a service
     * @param {number} serviceId - ID of the service to delete
     */
    function deleteService(serviceId) {
        // Confirm hiding
        if (!confirm('¿Ocultar este servicio?')) {
            return;
        }
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_delete_service_db');
        formData.append('id', serviceId);

        // Disable button during request
        const deleteButton = document.querySelector('.aa-service-delete[data-service-id="' + serviceId + '"]');
        const originalButtonText = deleteButton ? deleteButton.textContent : '';
        if (deleteButton) {
            deleteButton.disabled = true;
            deleteButton.textContent = 'Ocultando...';
        }

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                console.log('[Services Section] Servicio ocultado correctamente');
                // Reload the list of services
                if (servicesRoot) {
                    loadServices(servicesRoot);
                }
            } else {
                console.error('[Services Section] Error al ocultar servicio:', data);
            }
        })
        .catch(function(error) {
            console.error('[Services Section] Error en petición AJAX:', error);
        })
        .finally(function() {
            // Re-enable button
            if (deleteButton) {
                deleteButton.disabled = false;
                deleteButton.textContent = originalButtonText;
            }
        });
    }

    /**
     * Setup handler for create service button
     */
    function setupCreateServiceHandler() {
        const addButton = document.getElementById('aa-add-service');
        const nameInput = document.getElementById('aa-service-name-input');
        
        if (!addButton || !nameInput) {
            console.warn('[Services Section] Create service button or input not found');
            return;
        }
        
        // Handle button click
        addButton.addEventListener('click', function() {
            createService(nameInput);
        });
        
        // Handle Enter key in input
        nameInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                createService(nameInput);
            }
        });
    }

    /**
     * Create a new service
     * @param {HTMLElement} nameInput - The input element containing the service name
     */
    function createService(nameInput) {
        const name = nameInput.value.trim();
        
        // Validate input
        if (!name) {
            console.warn('[Services Section] Intento de crear servicio con nombre vacío');
            return;
        }
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_create_service');
        formData.append('name', name);

        // Disable button during request
        const addButton = document.getElementById('aa-add-service');
        const originalButtonText = addButton.textContent;
        addButton.disabled = true;
        addButton.textContent = 'Agregando...';

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Clear input
                nameInput.value = '';
                
                // Reload the list of services
                if (servicesRoot) {
                    loadServices(servicesRoot);
                }
            } else {
                console.error('[Services Section] Error al crear servicio:', data);
            }
        })
        .catch(function(error) {
            console.error('[Services Section] Error en petición AJAX:', error);
        })
        .finally(function() {
            // Re-enable button
            if (addButton) {
                addButton.disabled = false;
                addButton.textContent = originalButtonText;
            }
        });
    }

    /**
     * Render service details in an editable form layout
     * @param {Object} service - Service object with all fields
     * @returns {string} HTML string for service details
     */
    function renderServiceDetails(service) {
        // Helper function to format datetime string
        function formatDateTime(datetimeStr) {
            if (!datetimeStr) return '—';
            try {
                const date = new Date(datetimeStr);
                if (isNaN(date.getTime())) return datetimeStr;
                return date.toLocaleString('es-ES', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return datetimeStr;
            }
        }

        const serviceId = parseInt(service.id);
        
        let html = '<div class="aa-service-details-content">';
        
        // Details grid
        html += '<div class="grid grid-cols-2 gap-4">';
        
        // Código (editable input)
        html += '<div>';
        html += '<label class="text-xs text-gray-500 block mb-1">Código</label>';
        html += '<input type="text" ';
        html += 'id="aa-service-code-' + serviceId + '" ';
        html += 'class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += 'data-field="code" ';
        html += 'value="' + escapeHtml(service.code || '') + '" ';
        html += '/>';
        html += '</div>';
        
        // Precio (editable input number)
        html += '<div>';
        html += '<label class="text-xs text-gray-500 block mb-1">Precio</label>';
        html += '<input type="number" ';
        html += 'step="0.01" ';
        html += 'inputmode="decimal" ';
        html += 'id="aa-service-price-' + serviceId + '" ';
        html += 'class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += 'data-field="price" ';
        html += 'value="' + (service.price !== null && service.price !== undefined ? escapeHtml(service.price) : '') + '" ';
        html += '/>';
        html += '</div>';
        
        // Creado (read-only)
        html += '<div>';
        html += '<span class="text-xs text-gray-500 block mb-1">Creado</span>';
        html += '<span class="text-sm font-medium text-gray-900">' + formatDateTime(service.created_at) + '</span>';
        html += '</div>';
        
        // ID (read-only, pequeño)
        html += '<div>';
        html += '<span class="text-xs text-gray-500 block mb-1">ID</span>';
        html += '<span class="text-xs font-medium text-gray-600">' + escapeHtml(service.id || '—') + '</span>';
        html += '</div>';
        
        html += '</div>'; // End grid
        
        // Descripción (editable textarea)
        html += '<div class="mt-4 pt-4 border-t border-gray-200">';
        html += '<label class="text-xs text-gray-500 block mb-1">Descripción</label>';
        html += '<textarea rows="3" ';
        html += 'id="aa-service-description-' + serviceId + '" ';
        html += 'class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += 'data-field="description" ';
        html += '>';
        html += escapeHtml(service.description || '');
        html += '</textarea>';
        html += '</div>';
        
        // Action buttons (Guardar y Eliminar)
        html += '<div class="mt-4 pt-4 border-t border-gray-200 flex justify-end gap-2">';
        html += '<button type="button" ';
        html += 'class="aa-service-save px-3 py-2 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += '>Guardar</button>';
        html += '<button type="button" ';
        html += 'class="aa-service-delete px-3 py-2 text-sm font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white transition-colors" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += '>Ocultar</button>';
        html += '</div>';
        
        html += '</div>'; // End details content
        
        return html;
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initServicesSection);
    } else {
        // DOM already ready
        initServicesSection();
    }

})();
