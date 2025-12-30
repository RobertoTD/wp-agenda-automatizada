/**
 * Areas Section - Service Areas Management
 * 
 * Handles UI logic for managing service areas (zonas de atención)
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let areasRoot = null;

    /**
     * Initialize the areas section
     */
    function initAreasSection() {
        areasRoot = document.getElementById('aa-areas-root');
        
        // Fail safely if root doesn't exist
        if (!areasRoot) {
            console.warn('[Areas Section] Root element #aa-areas-root not found');
            return;
        }

        // Load and render service areas
        loadServiceAreas(areasRoot);
        
        // Setup create area button handler
        setupCreateAreaHandler();
    }

    /**
     * Load service areas from server via AJAX
     * @param {HTMLElement} root - The root container element
     */
    function loadServiceAreas(root) {
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_get_service_areas');

        // Show loading state
        root.innerHTML = '<p class="text-sm text-gray-500">Cargando zonas de atención...</p>';

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.service_areas) {
                renderServiceAreas(root, data.data.service_areas);
            } else {
                console.error('[Areas Section] Error en respuesta:', data);
                root.innerHTML = '<p class="text-sm text-red-500">Error al cargar las zonas de atención.</p>';
            }
        })
        .catch(function(error) {
            console.error('[Areas Section] Error en petición AJAX:', error);
            root.innerHTML = '<p class="text-sm text-red-500">Error al conectar con el servidor.</p>';
        });
    }

    /**
     * Render service areas list
     * @param {HTMLElement} root - The root container element
     * @param {Array} serviceAreas - Array of service area objects
     */
    function renderServiceAreas(root, serviceAreas) {
        if (!serviceAreas || serviceAreas.length === 0) {
            root.innerHTML = '<p class="text-sm text-gray-500">No hay zonas registradas.</p>';
            return;
        }

        // Build HTML for service areas list
        let html = '<ul class="space-y-2">';
        
        serviceAreas.forEach(function(area) {
            const isActive = parseInt(area.active) === 1;
            const areaId = parseInt(area.id);
            
            html += '<li class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200">';
            html += '<span class="flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 text-green-600 flex-shrink-0">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>';
            html += '</svg>';
            html += '</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(area.name) + '</span>';
            if (area.description) {
                html += '<span class="text-xs text-gray-500 ml-2">- ' + escapeHtml(area.description) + '</span>';
            }
            // Toggle switch
            html += '<div class="ml-auto relative">';
            html += '<label class="flex items-center cursor-pointer">';
            html += '<input type="checkbox" ';
            html += 'class="toggle-area-active peer sr-only" ';
            html += 'data-id="' + areaId + '" ';
            html += 'data-active="' + area.active + '" ';
            if (isActive) {
                html += 'checked ';
            }
            html += '/>';
            html += '<div class="w-11 h-6 bg-gray-300 peer-checked:bg-blue-500 rounded-full transition-colors duration-200"></div>';
            html += '<div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:translate-x-5"></div>';
            html += '</label>';
            html += '</div>';
            html += '</li>';
        });
        
        html += '</ul>';

        root.innerHTML = html;
        
        // Setup toggle handlers after rendering
        setupToggleHandlers();
    }

    /**
     * Setup handlers for toggle switches
     */
    function setupToggleHandlers() {
        const toggles = document.querySelectorAll('.toggle-area-active');
        
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
        const areaId = parseInt(toggle.getAttribute('data-id'));
        const previousActive = parseInt(toggle.getAttribute('data-active'));
        const newActive = toggle.checked ? 1 : 0;
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_toggle_service_area');
        formData.append('id', areaId);
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
                console.error('[Areas Section] Error al actualizar zona:', data);
            }
        })
        .catch(function(error) {
            // Revert toggle state on error
            toggle.checked = previousActive === 1;
            console.error('[Areas Section] Error en petición AJAX:', error);
        });
    }

    /**
     * Setup handler for create area button
     */
    function setupCreateAreaHandler() {
        const addButton = document.getElementById('aa-add-area');
        const nameInput = document.getElementById('aa-area-name-input');
        
        if (!addButton || !nameInput) {
            console.warn('[Areas Section] Create area button or input not found');
            return;
        }
        
        // Handle button click
        addButton.addEventListener('click', function() {
            createServiceArea(nameInput);
        });
        
        // Handle Enter key in input
        nameInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                createServiceArea(nameInput);
            }
        });
    }

    /**
     * Create a new service area
     * @param {HTMLElement} nameInput - The input element containing the area name
     */
    function createServiceArea(nameInput) {
        const name = nameInput.value.trim();
        
        // Validate input
        if (!name) {
            console.warn('[Areas Section] Intento de crear zona con nombre vacío');
            return;
        }
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_create_service_area');
        formData.append('name', name);

        // Disable button during request
        const addButton = document.getElementById('aa-add-area');
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
                
                // Reload the list of service areas
                if (areasRoot) {
                    loadServiceAreas(areasRoot);
                }
            } else {
                console.error('[Areas Section] Error al crear zona:', data);
            }
        })
        .catch(function(error) {
            console.error('[Areas Section] Error en petición AJAX:', error);
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
        document.addEventListener('DOMContentLoaded', initAreasSection);
    } else {
        // DOM already ready
        initAreasSection();
    }

})();
