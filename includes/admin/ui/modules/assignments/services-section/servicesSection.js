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
    
    // Flag to track if attendance/virtual change handlers are already bound
    let attendanceVirtualHandlersBound = false;

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

        // Setup save and delete handlers (only once)
        setupSaveDeleteHandlers();
        
        // Setup attendance/virtual change handlers (only once)
        setupAttendanceVirtualHandlers();
        
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
            html += '<div class="aa-service-header-toggle flex items-center gap-1.5 p-3 cursor-pointer" data-service-id="' + serviceId + '">';
            html += '<span class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 flex-shrink-0">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>';
            html += '</svg>';
            html += '</span>';
            html += '<span class="text-sm font-semibold text-gray-600">' + escapeHtml(service.name) + '</span>';
            // Toggle switch (visible solo cuando la fila está expandida)
            html += '<div class="aa-service-active-toggle ml-auto relative hidden">';
            html += '<label class="flex items-center cursor-pointer">';
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
                if (newActive === 1) {
                    dispatchOnboardingSetupMutated('service');
                }
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
     * Setup handlers for details panel toggle (whole header clickable, except the active switch)
     */
    function setupDetailsPanelHandlers() {
        const headers = document.querySelectorAll('.aa-service-header-toggle');
        
        headers.forEach(function(header) {
            header.addEventListener('click', function(e) {
                // No togglear al interactuar con el switch de activo
                if (e.target.closest('.aa-service-active-toggle')) {
                    return;
                }
                
                const row = this.closest('li');
                const panel = row ? row.querySelector('.aa-service-details-panel') : null;
                const activeToggle = row ? row.querySelector('.aa-service-active-toggle') : null;
                
                if (panel) {
                    // Toggle panel visibility
                    panel.classList.toggle('hidden');
                    
                    // Mostrar el switch de activo solo cuando la fila está expandida
                    if (activeToggle) {
                        activeToggle.classList.toggle('hidden', panel.classList.contains('hidden'));
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
     * Handle change events on attendance_type and virtual_channel (event delegation)
     * @param {Event} event - Change event
     */
    function onServicesRootChange(event) {
        const target = event.target;
        if (!target) return;
        
        if (target.classList.contains('aa-service-attendance-type')) {
            const serviceId = target.getAttribute('data-service-id');
            const container = document.getElementById('aa-service-virtual-container-' + serviceId);
            if (container) {
                if (target.value === 'virtual') {
                    container.classList.remove('hidden');
                } else {
                    container.classList.add('hidden');
                }
            }
        } else if (target.classList.contains('aa-service-virtual-channel')) {
            const serviceId = target.getAttribute('data-service-id');
            const hint = document.querySelector('.aa-service-virtual-hint[data-service-id="' + serviceId + '"]');
            if (hint) {
                hint.textContent = target.value === 'custom_link'
                    ? 'El enlace se definirá al crear la reservación.'
                    : 'El enlace se generará automáticamente al agendar.';
            }
        }
    }

    /**
     * Setup handlers for attendance_type and virtual_channel change events
     * Only registers once using event delegation
     */
    function setupAttendanceVirtualHandlers() {
        if (attendanceVirtualHandlersBound) return;
        if (!servicesRoot) return;
        
        servicesRoot.addEventListener('change', onServicesRootChange);
        attendanceVirtualHandlersBound = true;
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
        const indicacionesInput = document.getElementById('aa-service-indicaciones-cita-' + serviceId);
        const durationMinutesInput = document.getElementById('aa-service-duration-minutes-' + serviceId);
        const attendanceTypeInput = document.getElementById('aa-service-attendance-type-' + serviceId);
        const virtualChannelInput = document.getElementById('aa-service-virtual-channel-' + serviceId);
        const publicCalendarInput = document.getElementById('aa-service-public-calendar-' + serviceId);
        
        if (!codeInput || !priceInput || !descriptionInput) {
            console.warn('[Services Section] Inputs not found for service ID:', serviceId);
            return;
        }
        
        const code = codeInput.value.trim();
        const price = priceInput.value.trim();
        const description = descriptionInput.value.trim();
        const indicaciones = indicacionesInput ? indicacionesInput.value.trim() : '';
        const durationMinutes = durationMinutesInput ? durationMinutesInput.value.trim() : '';
        const attendanceType = attendanceTypeInput ? attendanceTypeInput.value.trim() : '';
        const virtualChannel = (attendanceType === 'virtual' && virtualChannelInput)
            ? virtualChannelInput.value.trim()
            : '';

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
        formData.append('indicaciones_cita', indicaciones);
        formData.append('duration_minutes', durationMinutes);
        formData.append('attendance_type', attendanceType || '');
        formData.append('virtual_channel', virtualChannel);
        formData.append('public_calendar', (publicCalendarInput && publicCalendarInput.checked) ? '1' : '0');

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
        
        // Details grid
        html += '<div class="grid grid-cols-2 gap-4">';
        
        // Código (editable input)
        html += '<div>';
        html += '<label class="text-xs text-gray-500 block mb-1">Código</label>';
        html += '<input type="text" ';
        html += 'id="aa-service-code-' + serviceId + '" ';
        html += 'class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" ';
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
        html += 'class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += 'data-field="price" ';
        html += 'value="' + (service.price !== null && service.price !== undefined ? escapeHtml(service.price) : '') + '" ';
        html += '/>';
        html += '</div>';
        
        html += '</div>'; // End grid
        
        // Mostrar en calendario público (checkbox; backend may return number or string from DB)
        var publicCalendarChecked = (Number(service.public_calendar) === 1);
        html += '<div class="mt-4 pt-4 border-t border-gray-200">';
        html += '<label class="flex items-center gap-2 cursor-pointer">';
        html += '<input type="checkbox" id="aa-service-public-calendar-' + serviceId + '" ';
        html += 'class="aa-service-public-calendar rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" ';
        html += 'data-service-id="' + serviceId + '" ';
        if (publicCalendarChecked) { html += 'checked '; }
        html += '/>';
        html += '<span class="text-sm text-gray-600">Mostrar en calendario público</span>';
        html += '</label>';
        html += '</div>';
        
        // Descripción (editable textarea)
        html += '<div class="mt-4 pt-4 border-t border-gray-200">';
        html += '<label class="text-xs text-gray-500 block mb-1">Descripción</label>';
        html += '<textarea rows="3" ';
        html += 'id="aa-service-description-' + serviceId + '" ';
        html += 'class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += 'data-field="description" ';
        html += '>';
        html += escapeHtml(service.description || '');
        html += '</textarea>';
        html += '</div>';
        
        // Indicaciones para cita (editable textarea)
        html += '<div class="mt-4 pt-4 border-t border-gray-200">';
        html += '<label class="text-xs text-gray-500 block mb-1">Indicaciones para cita</label>';
        html += '<textarea rows="3" ';
        html += 'id="aa-service-indicaciones-cita-' + serviceId + '" ';
        html += 'class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += 'data-field="indicaciones_cita" ';
        html += 'placeholder="Estas indicaciones se mostrarán en los correos de confirmación al cliente." ';
        html += '>';
        html += escapeHtml(service.indicaciones_cita || '');
        html += '</textarea>';
        html += '</div>';

        // Duración del servicio (opcional; vacío = usar configuración general)
        var durationMinutesVal = '';
        if (service.duration_minutes !== null && service.duration_minutes !== undefined && service.duration_minutes !== '') {
            durationMinutesVal = String(service.duration_minutes);
        }
        html += '<div class="mt-4 pt-4 border-t border-gray-200">';
        html += '<label class="text-xs text-gray-500 block mb-1">Duración</label>';
        html += '<select id="aa-service-duration-minutes-' + serviceId + '" ';
        html += 'class="aa-form-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" ';
        html += 'data-service-id="' + serviceId + '">';
        html += '<option value=""' + (durationMinutesVal === '' ? ' selected' : '') + '>Usar configuración general</option>';
        html += '<option value="30"' + (durationMinutesVal === '30' ? ' selected' : '') + '>30 min</option>';
        html += '<option value="60"' + (durationMinutesVal === '60' ? ' selected' : '') + '>60 min</option>';
        html += '<option value="90"' + (durationMinutesVal === '90' ? ' selected' : '') + '>90 min</option>';
        html += '</select>';
        html += '</div>';
        
        // Tipo (physical/virtual)
        var defaultType = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.defaultAttendanceType) || 'physical';
        var attendanceTypeVal = (service.attendance_type && (service.attendance_type === 'physical' || service.attendance_type === 'virtual'))
            ? service.attendance_type
            : defaultType;
        html += '<div class="mt-4 pt-4 border-t border-gray-200">';
        html += '<label class="text-xs text-gray-500 block mb-1">Tipo</label>';
        html += '<select id="aa-service-attendance-type-' + serviceId + '" ';
        html += 'class="aa-form-select aa-service-attendance-type w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" ';
        html += 'data-service-id="' + serviceId + '">';
        html += '<option value="physical"' + (attendanceTypeVal === 'physical' ? ' selected' : '') + '>Físico</option>';
        html += '<option value="virtual"' + (attendanceTypeVal === 'virtual' ? ' selected' : '') + '>Virtual</option>';
        html += '</select>';
        html += '</div>';
        
        // Bloque virtual (condicional)
        var virtualChannelVal = (service.virtual_channel && ['whatsapp', 'google_meet', 'custom_link'].indexOf(service.virtual_channel) >= 0)
            ? service.virtual_channel
            : 'whatsapp';
        var virtualContainerVisible = attendanceTypeVal === 'virtual' ? '' : ' hidden';
        html += '<div id="aa-service-virtual-container-' + serviceId + '" class="aa-service-virtual-container mt-4 pt-4 border-t border-gray-200' + virtualContainerVisible + '" data-service-id="' + serviceId + '">';
        html += '<label class="text-xs text-gray-500 block mb-1">Canal</label>';
        html += '<select id="aa-service-virtual-channel-' + serviceId + '" ';
        html += 'class="aa-form-select aa-service-virtual-channel w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" ';
        html += 'data-service-id="' + serviceId + '">';
        html += '<option value="whatsapp"' + (virtualChannelVal === 'whatsapp' ? ' selected' : '') + '>WhatsApp</option>';
        html += '<option value="google_meet"' + (virtualChannelVal === 'google_meet' ? ' selected' : '') + '>Google Meet</option>';
        html += '<option value="custom_link"' + (virtualChannelVal === 'custom_link' ? ' selected' : '') + '>Enlace personalizado</option>';
        html += '</select>';
        var virtualHintText = virtualChannelVal === 'custom_link'
            ? 'El enlace se definirá al crear la reservación.'
            : 'El enlace se generará automáticamente al agendar.';
        html += '<p class="aa-service-virtual-hint text-xs text-gray-500 mt-1" data-service-id="' + serviceId + '">' + escapeHtml(virtualHintText) + '</p>';
        html += '</div>';
        
        // Action buttons (Guardar y Eliminar)
        html += '<div class="mt-4 pt-4 border-t border-gray-200 flex justify-end gap-2">';
        html += '<button type="button" ';
        html += 'class="aa-service-save px-3 py-2 text-sm font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += '>Guardar</button>';
        html += '<button type="button" ';
        html += 'class="aa-service-delete px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors" ';
        html += 'data-service-id="' + serviceId + '" ';
        html += '>Eliminar</button>';
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
