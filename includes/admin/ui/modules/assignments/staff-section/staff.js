/**
 * Staff Section - Staff/Personnel Management
 * 
 * Handles UI logic for managing staff members (personal)
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let staffRoot = null;

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
            
            html += '<li class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200">';
            html += '<span class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex-shrink-0">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>';
            html += '</svg>';
            html += '</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(staff.name) + '</span>';
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
        const toggles = document.querySelectorAll('.toggle-staff-active');
        
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
