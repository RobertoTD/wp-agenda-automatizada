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
            
            html += '<li class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">';
            // Main content row (header)
            html += '<div class="flex items-center gap-2 p-3">';
            // Color indicator circle
            const areaColor = area.color || '#3b82f6';
            html += '<span class="aa-area-color-bg flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0" style="background-color: ' + areaColor + '20;">';
            html += '<span class="aa-area-color-indicator w-4 h-4 rounded-full border-2 border-white shadow-sm" style="background-color: ' + areaColor + ';"></span>';
            html += '</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(area.name) + '</span>';
            // Toggle details button (chevron)
            html += '<button type="button" class="aa-area-toggle-details inline-flex items-center justify-center w-6 h-6 text-gray-500 hover:text-gray-700 transition-colors" data-area-id="' + areaId + '" title="Ver detalles">';
            html += '<svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';
            html += '</svg>';
            html += '</button>';
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
            html += '</div>';
            // Collapsable details panel
            html += '<div class="aa-area-details-panel hidden border-t border-gray-200 p-3" data-area-id="' + areaId + '">';
            if (area.description) {
                html += '<div class="mb-3">';
                html += '<label class="block text-xs font-medium text-gray-700 mb-1">Descripción</label>';
                html += '<p class="text-sm text-gray-600">' + escapeHtml(area.description) + '</p>';
                html += '</div>';
            } else {
                html += '<p class="text-xs text-gray-500 mb-3">No hay descripción disponible.</p>';
            }
            // Color picker field
            html += '<div class="mb-2">';
            html += '<label class="block text-xs font-medium text-gray-700 mb-1">Color</label>';
            html += '<input type="text" ';
            html += 'class="aa-area-color-picker" ';
            html += 'data-area-id="' + areaId + '" ';
            html += 'value="' + (area.color || '#3b82f6') + '" ';
            html += 'style="width: 100%; max-width: 200px;" />';
            html += '</div>';
            html += '</div>';
            html += '</li>';
        });
        
        html += '</ul>';

        root.innerHTML = html;
        
        // Setup toggle handlers after rendering
        setupToggleHandlers();
        
        // Setup details panel toggle handlers
        setupDetailsPanelHandlers();
        
        // Initialize color pickers after rendering
        initializeColorPickers();
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
     * Setup handlers for details panel toggle (chevron button)
     */
    function setupDetailsPanelHandlers() {
        const toggleButtons = document.querySelectorAll('.aa-area-toggle-details');
        
        toggleButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const areaId = this.getAttribute('data-area-id');
                const panel = document.querySelector('.aa-area-details-panel[data-area-id="' + areaId + '"]');
                const chevron = this.querySelector('svg');
                
                if (panel) {
                    // Toggle panel visibility
                    const isOpening = panel.classList.contains('hidden');
                    panel.classList.toggle('hidden');
                    
                    // Rotate chevron
                    if (panel.classList.contains('hidden')) {
                        chevron.classList.remove('rotate-90');
                    } else {
                        chevron.classList.add('rotate-90');
                        
                        // Initialize color picker if panel is being opened and picker not yet initialized
                        if (isOpening) {
                            // Pequeño delay para asegurar que el DOM esté listo
                            setTimeout(function() {
                                const colorPicker = panel.querySelector('.aa-area-color-picker');
                                if (colorPicker && !jQuery(colorPicker).hasClass('wp-color-picker')) {
                                    initializeSingleColorPicker(colorPicker);
                                }
                            }, 50);
                        }
                    }
                }
            });
        });
    }
    
    /**
     * Initialize a single color picker
     * @param {HTMLElement} picker - The color picker input element
     */
    function initializeSingleColorPicker(picker) {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.wpColorPicker === 'undefined') {
            console.warn('[Areas Section] wp-color-picker no disponible');
            return;
        }
        
        jQuery(picker).wpColorPicker({
            defaultColor: '#3b82f6',
            change: function(event, ui) {
                const areaId = picker.getAttribute('data-area-id');
                const newColor = ui.color.toString();
                
                // Actualizar indicador visual en la card
                const card = picker.closest('li');
                if (card) {
                    const colorIndicator = card.querySelector('.aa-area-color-indicator');
                    if (colorIndicator) {
                        colorIndicator.style.backgroundColor = newColor;
                        const parentBg = card.querySelector('.aa-area-color-bg');
                        if (parentBg) {
                            parentBg.style.backgroundColor = newColor + '20';
                        }
                    }
                }
                
                // Guardar color en BD vía AJAX
                updateServiceAreaColor(areaId, newColor);
            },
            clear: function() {
                const areaId = picker.getAttribute('data-area-id');
                const defaultColor = '#3b82f6';
                
                const card = picker.closest('li');
                if (card) {
                    const colorIndicator = card.querySelector('.aa-area-color-indicator');
                    if (colorIndicator) {
                        colorIndicator.style.backgroundColor = defaultColor;
                        const parentBg = card.querySelector('.aa-area-color-bg');
                        if (parentBg) {
                            parentBg.style.backgroundColor = defaultColor + '20';
                        }
                    }
                }
                
                // Guardar color por defecto en BD vía AJAX
                updateServiceAreaColor(areaId, defaultColor);
            }
        });
    }
    
    /**
     * Update service area color via AJAX
     * @param {number} areaId - ID of the service area
     * @param {string} color - Color in hexadecimal format (e.g., #16225b)
     */
    function updateServiceAreaColor(areaId, color) {
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        const formData = new FormData();
        formData.append('action', 'aa_update_service_area_color');
        formData.append('id', areaId);
        formData.append('color', color);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                console.log('[Areas Section] Color guardado correctamente para zona ' + areaId + ': ' + color);
            } else {
                console.error('[Areas Section] Error al guardar color:', data.message);
            }
        })
        .catch(function(error) {
            console.error('[Areas Section] Error en petición AJAX para guardar color:', error);
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
     * Initialize WordPress color pickers
     */
    function initializeColorPickers() {
        // Wait for wp-color-picker to be available
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.wpColorPicker === 'undefined') {
            console.warn('[Areas Section] wp-color-picker no disponible, reintentando...');
            setTimeout(initializeColorPickers, 100);
            return;
        }
        
        const colorPickers = document.querySelectorAll('.aa-area-color-picker');
        
        colorPickers.forEach(function(picker) {
            // Only initialize if not already initialized
            if (!jQuery(picker).hasClass('wp-color-picker')) {
                initializeSingleColorPicker(picker);
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
