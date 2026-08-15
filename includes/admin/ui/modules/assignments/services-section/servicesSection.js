/**
 * Services Section - Services Management
 * 
 * Handles UI logic for managing services
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let servicesRoot = null;
    
    // Flag to track if delete handlers are already bound
    let deleteHandlersBound = false;
    
    // Flag to track if aa:service:saved listener is already bound
    let serviceSavedListenerBound = false;

    /**
     * @param {'service' | 'staff_service_assignment'} source
     */
    function dispatchOnboardingSetupMutated(source) {
        document.dispatchEvent(new CustomEvent('aa:onboarding:setup-mutated', {
            detail: { source: source }
        }));
    }

    /**
     * @param {object|null|undefined} response JSON from aa_create_service
     * @returns {'service' | 'staff_service_assignment'}
     */
    function resolveCreateServiceOnboardingSource(response) {
        var autoAssign = response && response.data && response.data.auto_assign;

        if (autoAssign && autoAssign.created > 0) {
            return 'staff_service_assignment';
        }

        return 'service';
    }

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

        // Setup delete handlers (only once)
        setupDeleteHandlers();

        // Refresh list after create/edit modal save (do not treat refresh failure as save failure)
        setupServiceSavedListener();
        
        // Load and render services
        loadServices(servicesRoot);
        
        // Setup create service button handler
        setupCreateServiceHandler();
    }

    /**
     * Recarga canónica tras aa:service:saved (create o edit).
     */
    function setupServiceSavedListener() {
        if (serviceSavedListenerBound) {
            return;
        }

        document.addEventListener('aa:service:saved', function() {
            if (!servicesRoot) {
                return;
            }

            try {
                loadServices(servicesRoot);
            } catch (error) {
                console.error('[Services Section] Error al refrescar servicios tras guardar:', error);
            }
        });

        serviceSavedListenerBound = true;
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
            const serviceId = parseInt(service.id);
            
            html += '<li class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">';
            // Main content row
            html += '<div class="aa-service-header-toggle flex items-center gap-1.5 p-3 cursor-pointer" data-service-id="' + serviceId + '">';
            html += '<span class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 flex-shrink-0">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>';
            html += '</svg>';
            html += '</span>';
            html += '<span class="text-base font-semibold text-gray-600 min-w-0 flex-1">' + escapeHtml(service.name) + '</span>';
            html += (window.AAAdmin && typeof window.AAAdmin.renderAssignmentItemOptions === 'function')
                ? window.AAAdmin.renderAssignmentItemOptions('service', serviceId)
                : '';
            html += '</div>';
            // Collapsable details panel
            html += '<div class="aa-service-details-panel hidden p-3" data-service-id="' + serviceId + '">';
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
        
        // Note: delete handlers are set up once in initServicesSection()
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
        syncServiceActiveLabel(toggle);
        
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
                if (newActive === 1) {
                    dispatchOnboardingSetupMutated('service');
                }
            } else {
                // Revert toggle state on error
                toggle.checked = previousActive === 1;
                syncServiceActiveLabel(toggle);
                console.error('[Services Section] Error al actualizar servicio:', data);
            }
        })
        .catch(function(error) {
            // Revert toggle state on error
            toggle.checked = previousActive === 1;
            syncServiceActiveLabel(toggle);
            console.error('[Services Section] Error en petición AJAX:', error);
        });
    }

    /**
     * Setup handlers for details panel toggle (whole header clickable)
     */
    function setupDetailsPanelHandlers() {
        const headers = document.querySelectorAll('.aa-service-header-toggle');
        
        headers.forEach(function(header) {
            header.addEventListener('click', function() {
                const row = this.closest('li');
                const panel = row ? row.querySelector('.aa-service-details-panel') : null;
                
                if (panel) {
                    panel.classList.toggle('hidden');
                }
            });
        });
    }

    /**
     * @param {HTMLInputElement} toggle
     */
    function syncServiceActiveLabel(toggle) {
        const wrapper = toggle.closest('.aa-service-active-toggle');
        const label = wrapper ? wrapper.querySelector('.aa-service-active-label') : null;
        if (!label) {
            return;
        }
        label.textContent = toggle.checked ? 'Desactivar' : 'Activar';
    }

    /**
     * Handle click events on services root (event delegation)
     * @param {Event} event - Click event
     */
    function onServicesRootClick(event) {
        const editTarget = event.target.closest('.aa-service-edit');
        if (editTarget) {
            const editServiceId = parseInt(editTarget.getAttribute('data-service-id'));
            if (!editServiceId || editServiceId <= 0) {
                return;
            }

            event.preventDefault();
            openServiceEditModal(editServiceId);
            return;
        }

        const target = event.target.closest('.aa-service-delete');
        if (!target) return;
        
        const serviceId = parseInt(target.getAttribute('data-service-id'));
        if (!serviceId || serviceId <= 0) return;

        event.preventDefault();
        deleteService(serviceId);
    }

    /**
     * Setup handlers for delete buttons
     * Only registers once to avoid duplicate listeners
     */
    function setupDeleteHandlers() {
        if (deleteHandlersBound) {
            return;
        }

        if (!servicesRoot) {
            console.warn('[Services Section] Cannot setup delete handlers: servicesRoot not found');
            return;
        }

        servicesRoot.addEventListener('click', onServicesRootClick);
        deleteHandlersBound = true;
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
     * Open the transversal Service create modal.
     */
    function openServiceCreateModal() {
        if (window.AAAdmin && window.AAAdmin.ServiceCreateModal
            && typeof window.AAAdmin.ServiceCreateModal.openCreate === 'function') {
            window.AAAdmin.ServiceCreateModal.openCreate();
            return;
        }
        console.error('[Services Section] AAAdmin.ServiceCreateModal.openCreate no disponible');
    }

    /**
     * Open the transversal Service edit modal.
     * @param {number} serviceId
     */
    function openServiceEditModal(serviceId) {
        if (window.AAAdmin && window.AAAdmin.ServiceCreateModal
            && typeof window.AAAdmin.ServiceCreateModal.openEdit === 'function') {
            window.AAAdmin.ServiceCreateModal.openEdit(serviceId);
            return;
        }
        console.error('[Services Section] AAAdmin.ServiceCreateModal.openEdit no disponible');
    }

    /**
     * Setup handler for create service button — opens ServiceCreateModal
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
            openServiceCreateModal();
        });
        
        // Handle Enter key in input
        nameInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                openServiceCreateModal();
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
                dispatchOnboardingSetupMutated(resolveCreateServiceOnboardingSource(data));
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
        const serviceId = parseInt(service.id);
        
        let html = '<div class="aa-service-details-content">';

        var priceDisplay = formatServicePriceDisplay(service.price);
        var typeDisplay = formatServiceTypeDisplay(service.attendance_type);

        html += '<div class="aa-service-readonly-facts space-y-1">';
        if (priceDisplay) {
            html += renderServiceFact('Precio:', priceDisplay, 'aa-service-price-value');
        }
        if (typeDisplay) {
            html += renderServiceFact('Tipo:', typeDisplay, 'aa-service-type-value');
        }
        html += '</div>';

        // Eliminar + active toggle
        const isActive = parseInt(service.active) === 1;
        html += '<div class="mt-4 pt-4 flex items-center justify-between gap-2">';
        html += '<div class="flex items-center gap-2">';
        html += '<button type="button" ';
        html += 'class="aa-service-delete px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += '>Eliminar</button>';
        html += '<button type="button" ';
        html += 'class="aa-service-edit px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += '>Editar</button>';
        html += '</div>';
        html += '<label class="aa-service-active-toggle flex items-center gap-2 cursor-pointer">';
        html += '<div class="relative">';
        html += '<input type="checkbox" ';
        html += 'class="toggle-service-active peer sr-only" ';
        html += 'data-id="' + serviceId + '" ';
        html += 'data-active="' + service.active + '" ';
        if (isActive) {
            html += 'checked ';
        }
        html += '/>';
        html += '<div class="w-9 h-5 bg-gray-300 peer-checked:bg-indigo-600 rounded-full transition-colors duration-200"></div>';
        html += '<div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:translate-x-4"></div>';
        html += '</div>';
        html += '<span class="aa-service-active-label text-sm text-gray-600">' + (isActive ? 'Desactivar' : 'Activar') + '</span>';
        html += '</label>';
        html += '</div>';
        
        html += '</div>'; // End details content
        
        return html;
    }

    /**
     * @param {string} label
     * @param {string} value
     * @param {string} valueClass
     * @returns {string}
     */
    function renderServiceFact(label, value, valueClass) {
        return ''
            + '<div class="flex items-baseline gap-2 text-sm font-semibold text-gray-600">'
            + '<span>' + escapeHtml(label) + '</span>'
            + '<span class="' + valueClass + '">' + escapeHtml(value) + '</span>'
            + '</div>';
    }

    /**
     * @param {string|null|undefined} raw
     * @returns {string|null}
     */
    function formatServiceTypeDisplay(raw) {
        var value = String(raw || '').trim();
        if (value === 'physical') {
            return 'Físico';
        }
        if (value === 'virtual') {
            return 'Virtual';
        }
        return null;
    }

    /**
     * @param {string|number|null|undefined} raw
     * @returns {string|null} Display like "$566", or null when there is no price
     */
    function formatServicePriceDisplay(raw) {
        if (raw === null || raw === undefined) {
            return null;
        }

        var value = String(raw).trim();
        if (value === '') {
            return null;
        }

        var amount = Number(value);
        if (!isFinite(amount) || amount < 0) {
            return null;
        }

        if (Math.abs(amount % 1) < 1e-9) {
            return '$' + String(Math.round(amount));
        }

        return '$' + amount.toFixed(2);
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
