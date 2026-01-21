/**
 * Staff Section - Staff/Personnel Management
 * 
 * Handles UI logic for managing staff members (personal)
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let staffRoot = null;
    
    // Cache for all available services (loaded once)
    let servicesCache = [];
    
    // Flag to track if services handlers are already bound
    let servicesHandlersBound = false;

    /**
     * Initialize the staff section
     */
    function initStaffSection() {
        staffRoot = document.getElementById('aa-staff-root');
        
        // Fail safely if root doesn't exist
        if (!staffRoot) {
            console.warn('[Staff Section] Root element #aa-staff-root not found');
            return;
        }

        // Load services catalog once
        loadServicesCatalog();
        
        // Setup services handlers (only once, using event delegation)
        setupServicesHandlers();
        
        // Load and render staff
        loadStaff(staffRoot);
        
        // Setup create staff button handler
        setupCreateStaffHandler();
    }

    /**
     * Load staff from server via AJAX
     * @param {HTMLElement} root - The root container element
     */
    function loadStaff(root) {
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_get_staff');

        // Show loading state
        root.innerHTML = '<p class="text-sm text-gray-500">Cargando personal...</p>';

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.staff) {
                renderStaff(root, data.data.staff);
            } else {
                console.error('[Staff Section] Error en respuesta:', data);
                root.innerHTML = '<p class="text-sm text-red-500">Error al cargar el personal.</p>';
            }
        })
        .catch(function(error) {
            console.error('[Staff Section] Error en petición AJAX:', error);
            root.innerHTML = '<p class="text-sm text-red-500">Error al conectar con el servidor.</p>';
        });
    }

    /**
     * Render staff list
     * @param {HTMLElement} root - The root container element
     * @param {Array} staffList - Array of staff objects
     */
    function renderStaff(root, staffList) {
        if (!staffList || staffList.length === 0) {
            root.innerHTML = '<p class="text-sm text-gray-500">No hay personal registrado.</p>';
            return;
        }

        // Build HTML for staff list
        let html = '<ul class="space-y-2">';
        
        staffList.forEach(function(staff) {
            const isActive = parseInt(staff.active) === 1;
            const staffId = parseInt(staff.id);
            
            html += '<li class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">';
            // Main content row
            html += '<div class="flex items-center gap-2 p-3">';
            html += '<span class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex-shrink-0">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>';
            html += '</svg>';
            html += '</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(staff.name) + '</span>';
            // Toggle services button (chevron)
            html += '<button type="button" class="aa-staff-toggle-services inline-flex items-center justify-center w-6 h-6 text-gray-500 hover:text-gray-700 transition-colors" data-staff-id="' + staffId + '" title="Ver servicios">';
            html += '<svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';
            html += '</svg>';
            html += '</button>';
            // Toggle switch
            html += '<div class="ml-auto relative">';
            html += '<label class="flex items-center cursor-pointer">';
            html += '<input type="checkbox" ';
            html += 'class="toggle-staff-active peer sr-only" ';
            html += 'data-id="' + staffId + '" ';
            html += 'data-active="' + staff.active + '" ';
            if (isActive) {
                html += 'checked ';
            }
            html += '/>';
            html += '<div class="w-9 h-5 bg-gray-300 peer-checked:bg-blue-500 rounded-full transition-colors duration-200"></div>';
            html += '<div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:translate-x-4"></div>';
            html += '</label>';
            html += '</div>';
            html += '</div>';
            // Collapsable services panel
            html += '<div class="aa-staff-services-panel hidden border-t border-gray-200 p-3" data-staff-id="' + staffId + '">';
            html += '<select class="aa-staff-services-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" data-staff-id="' + staffId + '">';
            html += '<option value="">Selecciona los servicios que ofrece</option>';
            html += '</select>';
            html += '<div class="aa-staff-services-selected mt-3" data-staff-id="' + staffId + '"></div>';
            html += '</div>';
            html += '</li>';
        });
        
        html += '</ul>';

        root.innerHTML = html;
        
        // Setup toggle handlers after rendering
        setupToggleHandlers();
        
        // Setup services panel toggle handlers
        setupServicesPanelHandlers();
        
        // Populate services if catalog is already loaded
        // Otherwise, it will be populated when catalog loads in loadServicesCatalog()
        if (servicesCache.length > 0) {
            populateStaffServices();
        }
    }

    /**
     * Setup handlers for toggle switches
     */
    function setupToggleHandlers() {
        const toggles = document.querySelectorAll('.toggle-staff-active');
        
        toggles.forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                handleToggleChange(this);
            });
        });
    }

    /**
     * Setup handlers for services panel toggle (chevron button)
     */
    function setupServicesPanelHandlers() {
        const toggleButtons = document.querySelectorAll('.aa-staff-toggle-services');
        
        toggleButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const staffId = this.getAttribute('data-staff-id');
                const panel = document.querySelector('.aa-staff-services-panel[data-staff-id="' + staffId + '"]');
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
     * Handle toggle change event
     * @param {HTMLElement} toggle - The toggle checkbox element
     */
    function handleToggleChange(toggle) {
        const staffId = parseInt(toggle.getAttribute('data-id'));
        const previousActive = parseInt(toggle.getAttribute('data-active'));
        const newActive = toggle.checked ? 1 : 0;
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_toggle_staff');
        formData.append('id', staffId);
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
                // Update data attribute to reflect new state
                toggle.setAttribute('data-active', newActive);
            } else {
                // Revert toggle state on error
                toggle.checked = previousActive === 1;
                console.error('[Staff Section] Error al actualizar personal:', data);
            }
        })
        .catch(function(error) {
            // Revert toggle state on error
            toggle.checked = previousActive === 1;
            console.error('[Staff Section] Error en petición AJAX:', error);
        });
    }

    /**
     * Setup handler for create staff button
     */
    function setupCreateStaffHandler() {
        const addButton = document.getElementById('aa-add-staff');
        const nameInput = document.getElementById('aa-staff-name-input');
        
        if (!addButton || !nameInput) {
            console.warn('[Staff Section] Create staff button or input not found');
            return;
        }
        
        // Handle button click
        addButton.addEventListener('click', function() {
            createStaff(nameInput);
        });
        
        // Handle Enter key in input
        nameInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                createStaff(nameInput);
            }
        });
    }

    /**
     * Create a new staff member
     * @param {HTMLElement} nameInput - The input element containing the staff name
     */
    function createStaff(nameInput) {
        const name = nameInput.value.trim();
        
        // Validate input
        if (!name) {
            console.warn('[Staff Section] Intento de crear personal con nombre vacío');
            return;
        }
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_create_staff');
        formData.append('name', name);

        // Disable button during request
        const addButton = document.getElementById('aa-add-staff');
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
                
                // Reload the list of staff
                if (staffRoot) {
                    loadStaff(staffRoot);
                }
            } else {
                console.error('[Staff Section] Error al crear personal:', data);
            }
        })
        .catch(function(error) {
            console.error('[Staff Section] Error en petición AJAX:', error);
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
     * Load services catalog from database (once)
     */
    function loadServicesCatalog() {
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_get_services_db');

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
                servicesCache = data.data.services;
                console.log('[Staff Section] Servicios cargados:', servicesCache.length);
                // After loading catalog, populate services for all staff
                populateStaffServices();
            } else {
                console.error('[Staff Section] Error al cargar servicios:', data);
                servicesCache = [];
            }
        })
        .catch(function(error) {
            console.error('[Staff Section] Error en petición AJAX para servicios:', error);
            servicesCache = [];
        });
    }

    /**
     * Setup handlers for services select and remove buttons (event delegation)
     * Only registers once to avoid duplicate listeners
     */
    function setupServicesHandlers() {
        // Prevent duplicate registration
        if (servicesHandlersBound) {
            return;
        }
        
        if (!staffRoot) {
            console.warn('[Staff Section] Cannot setup services handlers: staffRoot not found');
            return;
        }
        
        // Event delegation for select change
        staffRoot.addEventListener('change', function(event) {
            if (event.target.classList.contains('aa-staff-services-select')) {
                const staffId = parseInt(event.target.getAttribute('data-staff-id'));
                const serviceId = parseInt(event.target.value);
                
                if (staffId > 0 && serviceId > 0) {
                    addStaffService(staffId, serviceId);
                }
            }
        });
        
        // Event delegation for remove button clicks
        staffRoot.addEventListener('click', function(event) {
            const removeButton = event.target.closest('.aa-staff-service-remove');
            if (removeButton) {
                const staffId = parseInt(removeButton.getAttribute('data-staff-id'));
                const serviceId = parseInt(removeButton.getAttribute('data-service-id'));
                
                if (staffId > 0 && serviceId > 0) {
                    removeStaffService(staffId, serviceId);
                }
            }
        });
        
        servicesHandlersBound = true;
    }

    /**
     * Populate services select and selected list for each staff member
     */
    function populateStaffServices() {
        if (!staffRoot || servicesCache.length === 0) {
            return;
        }
        
        const staffItems = staffRoot.querySelectorAll('li');
        
        staffItems.forEach(function(item) {
            const staffIdAttr = item.querySelector('.aa-staff-services-select');
            if (!staffIdAttr) return;
            
            const staffId = parseInt(staffIdAttr.getAttribute('data-staff-id'));
            if (!staffId || staffId <= 0) return;
            
            // Load selected services for this staff
            loadStaffServices(staffId);
        });
    }

    /**
     * Load services assigned to a staff member
     * @param {number} staffId - ID of the staff member
     */
    function loadStaffServices(staffId) {
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_get_staff_services');
        formData.append('staff_id', staffId);

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.selected) {
                updateStaffServicesUI(staffId, data.data.selected);
            } else {
                console.error('[Staff Section] Error al cargar servicios del personal:', data);
                updateStaffServicesUI(staffId, []);
            }
        })
        .catch(function(error) {
            console.error('[Staff Section] Error en petición AJAX:', error);
            updateStaffServicesUI(staffId, []);
        });
    }

    /**
     * Update UI for staff services (select and selected list)
     * @param {number} staffId - ID of the staff member
     * @param {Array} selectedServices - Array of selected services [{id, name}, ...]
     */
    function updateStaffServicesUI(staffId, selectedServices) {
        const select = document.querySelector('.aa-staff-services-select[data-staff-id="' + staffId + '"]');
        const selectedDiv = document.querySelector('.aa-staff-services-selected[data-staff-id="' + staffId + '"]');
        
        if (!select || !selectedDiv) {
            return;
        }
        
        // Get IDs of selected services
        const selectedIds = selectedServices.map(function(s) {
            return parseInt(s.id);
        });
        
        // Clear and populate select with unselected services
        select.innerHTML = '<option value="">Selecciona los servicios que ofrece</option>';
        
        servicesCache.forEach(function(service) {
            const serviceId = parseInt(service.id);
            if (selectedIds.indexOf(serviceId) === -1) {
                const option = document.createElement('option');
                option.value = serviceId;
                option.textContent = escapeHtml(service.name);
                select.appendChild(option);
            }
        });
        
        // Render selected services list
        if (selectedServices.length === 0) {
            selectedDiv.innerHTML = '<p class="text-xs text-gray-500">No hay servicios asignados.</p>';
        } else {
            let html = '<ul class="space-y-2">';
            
            selectedServices.forEach(function(service) {
                const serviceId = parseInt(service.id);
                html += '<li class="flex items-center justify-between p-2 bg-gray-50 rounded-lg border border-gray-200">';
                html += '<span class="text-sm text-gray-900">' + escapeHtml(service.name) + '</span>';
                html += '<button type="button" ';
                html += 'class="aa-staff-service-remove inline-flex items-center justify-center w-6 h-6 text-red-600 hover:text-red-700 hover:bg-red-50 rounded transition-colors" ';
                html += 'data-staff-id="' + staffId + '" ';
                html += 'data-service-id="' + serviceId + '" ';
                html += 'title="Eliminar servicio">';
                html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
                html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>';
                html += '</svg>';
                html += '</button>';
                html += '</li>';
            });
            
            html += '</ul>';
            selectedDiv.innerHTML = html;
        }
    }

    /**
     * Add a service to a staff member
     * @param {number} staffId - ID of the staff member
     * @param {number} serviceId - ID of the service to add
     */
    function addStaffService(staffId, serviceId) {
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_add_staff_service');
        formData.append('staff_id', staffId);
        formData.append('service_id', serviceId);

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
                // Reload services for this staff to update UI
                loadStaffServices(staffId);
            } else {
                console.error('[Staff Section] Error al agregar servicio:', data);
            }
        })
        .catch(function(error) {
            console.error('[Staff Section] Error en petición AJAX:', error);
        });
    }

    /**
     * Remove a service from a staff member
     * @param {number} staffId - ID of the staff member
     * @param {number} serviceId - ID of the service to remove
     */
    function removeStaffService(staffId, serviceId) {
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_remove_staff_service');
        formData.append('staff_id', staffId);
        formData.append('service_id', serviceId);

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
                // Reload services for this staff to update UI
                loadStaffServices(staffId);
            } else {
                console.error('[Staff Section] Error al eliminar servicio:', data);
            }
        })
        .catch(function(error) {
            console.error('[Staff Section] Error en petición AJAX:', error);
        });
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
        document.addEventListener('DOMContentLoaded', initStaffSection);
    } else {
        // DOM already ready
        initStaffSection();
    }

})();
