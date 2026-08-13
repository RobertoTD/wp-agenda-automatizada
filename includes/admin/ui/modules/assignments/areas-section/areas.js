/**
 * Areas Section - Service Areas Management
 * 
 * Handles UI logic for managing service areas (zonas de atención)
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let areasRoot = null;

    // Flag to track if hide handlers are already bound
    let hideHandlersBound = false;

    // Flag to track if aa:area:saved listener is already bound
    let areaSavedListenerBound = false;

    /**
     * @param {'area'} source
     */
    function dispatchOnboardingSetupMutated(source) {
        document.dispatchEvent(new CustomEvent('aa:onboarding:setup-mutated', {
            detail: { source: source }
        }));
    }

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
        
        // Setup hide handlers (only once, using event delegation)
        setupHideHandlers();

        // Refresh list after create/edit modal save (do not treat refresh failure as save failure)
        setupAreaSavedListener();
        
        // Setup create area button handler
        setupCreateAreaHandler();
    }

    /**
     * Recarga canónica tras aa:area:saved (create o edit).
     */
    function setupAreaSavedListener() {
        if (areaSavedListenerBound) {
            return;
        }

        document.addEventListener('aa:area:saved', function() {
            if (!areasRoot) {
                return;
            }

            try {
                loadServiceAreas(areasRoot);
            } catch (error) {
                console.error('[Areas Section] Error al refrescar zonas tras guardar:', error);
            }
        });

        areaSavedListenerBound = true;
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
            html += '<div class="aa-area-header-toggle flex items-center gap-1.5 p-3 cursor-pointer" data-area-id="' + areaId + '">';
            // Color indicator circle
            const areaColor = area.color || '#3b82f6';
            html += '<span class="aa-area-color-bg flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0">';
            html += '<span class="aa-area-color-indicator w-4 h-4 rounded-full border-2 border-white shadow-sm" style="background-color: ' + areaColor + ';"></span>';
            html += '</span>';
            html += '<span class="text-sm font-semibold text-gray-600 min-w-0 flex-1">' + escapeHtml(area.name) + '</span>';
            html += (window.AAAdmin && typeof window.AAAdmin.renderAssignmentItemOptions === 'function')
                ? window.AAAdmin.renderAssignmentItemOptions('area', areaId)
                : '';
            html += '</div>';
            // Collapsable details panel
            html += '<div class="aa-area-details-panel hidden p-3" data-area-id="' + areaId + '">';
            html += '<div class="flex items-center justify-between gap-2">';
            html += '<button type="button" ';
            html += 'class="aa-area-delete px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors" ';
            html += 'data-area-id="' + areaId + '" ';
            html += '>Eliminar</button>';
            html += '<label class="aa-area-active-toggle flex items-center gap-2 cursor-pointer">';
            html += '<div class="relative">';
            html += '<input type="checkbox" ';
            html += 'class="toggle-area-active peer sr-only" ';
            html += 'data-id="' + areaId + '" ';
            html += 'data-active="' + area.active + '" ';
            if (isActive) {
                html += 'checked ';
            }
            html += '/>';
            html += '<div class="w-9 h-5 bg-gray-300 peer-checked:bg-indigo-600 rounded-full transition-colors duration-200"></div>';
            html += '<div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:translate-x-4"></div>';
            html += '</div>';
            html += '<span class="aa-area-active-label text-sm text-gray-600">' + (isActive ? 'Desactivar' : 'Activar') + '</span>';
            html += '</label>';
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
    }

    /**
     * Setup handlers for hide buttons (event delegation)
     * Only registers once to avoid duplicate listeners
     */
    function setupHideHandlers() {
        if (hideHandlersBound) {
            return;
        }

        if (!areasRoot) {
            console.warn('[Areas Section] Cannot setup hide handlers: areasRoot not found');
            return;
        }

        areasRoot.addEventListener('click', function(event) {
            const hideButton = event.target.closest('.aa-area-delete');
            if (!hideButton) {
                return;
            }

            event.preventDefault();
            const areaId = parseInt(hideButton.getAttribute('data-area-id'));
            if (areaId > 0) {
                hideArea(areaId);
            }
        });

        hideHandlersBound = true;
    }

    /**
     * Hide a service area (soft hide via backend)
     * @param {number} areaId - ID of the service area to hide
     */
    function hideArea(areaId) {
        if (!confirm('¿Ocultar esta zona de atención?')) {
            return;
        }

        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl)
            || window.ajaxurl
            || '/wp-admin/admin-ajax.php';

        const formData = new FormData();
        formData.append('action', 'aa_delete_service_area_db');
        formData.append('id', areaId);

        const hideButton = document.querySelector('.aa-area-delete[data-area-id="' + areaId + '"]');
        const originalButtonText = hideButton ? hideButton.textContent : '';
        if (hideButton) {
            hideButton.disabled = true;
            hideButton.textContent = 'Ocultando...';
        }

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                console.log('[Areas Section] Zona ocultada correctamente');
                if (areasRoot) {
                    loadServiceAreas(areasRoot);
                }
            } else {
                console.error('[Areas Section] Error al ocultar zona:', data);
            }
        })
        .catch(function(error) {
            console.error('[Areas Section] Error en petición AJAX:', error);
        })
        .finally(function() {
            if (hideButton) {
                hideButton.disabled = false;
                hideButton.textContent = originalButtonText;
            }
        });
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
     * Setup handlers for details panel toggle (whole header clickable)
     */
    function setupDetailsPanelHandlers() {
        const headers = document.querySelectorAll('.aa-area-header-toggle');
        
        headers.forEach(function(header) {
            header.addEventListener('click', function() {
                const row = this.closest('li');
                const panel = row ? row.querySelector('.aa-area-details-panel') : null;
                
                if (panel) {
                    panel.classList.toggle('hidden');
                }
            });
        });
    }

    /**
     * @param {HTMLInputElement} toggle
     */
    function syncAreaActiveLabel(toggle) {
        const wrapper = toggle.closest('.aa-area-active-toggle');
        const label = wrapper ? wrapper.querySelector('.aa-area-active-label') : null;
        if (!label) {
            return;
        }
        label.textContent = toggle.checked ? 'Desactivar' : 'Activar';
    }
    
    /**
     * Handle toggle change event
     * @param {HTMLElement} toggle - The toggle checkbox element
     */
    function handleToggleChange(toggle) {
        const areaId = parseInt(toggle.getAttribute('data-id'));
        const previousActive = parseInt(toggle.getAttribute('data-active'));
        const newActive = toggle.checked ? 1 : 0;
        syncAreaActiveLabel(toggle);
        
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
                if (newActive === 1) {
                    dispatchOnboardingSetupMutated('area');
                }
            } else {
                // Revert toggle state on error
                toggle.checked = previousActive === 1;
                syncAreaActiveLabel(toggle);
                console.error('[Areas Section] Error al actualizar zona:', data);
            }
        })
        .catch(function(error) {
            // Revert toggle state on error
            toggle.checked = previousActive === 1;
            syncAreaActiveLabel(toggle);
            console.error('[Areas Section] Error en petición AJAX:', error);
        });
    }

    /**
     * Open the transversal Area create modal (Zona de Atención).
     */
    function openAreaCreateModal() {
        if (window.AAAdmin && window.AAAdmin.AreaCreateModal
            && typeof window.AAAdmin.AreaCreateModal.openCreate === 'function') {
            window.AAAdmin.AreaCreateModal.openCreate();
            return;
        }
        console.error('[Areas Section] AAAdmin.AreaCreateModal.openCreate no disponible');
    }

    /**
     * Setup handler for create area button — opens AreaCreateModal
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
            openAreaCreateModal();
        });
        
        // Handle Enter key in input
        nameInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                openAreaCreateModal();
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
                dispatchOnboardingSetupMutated('area');
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
