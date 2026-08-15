/**
 * Staff Section - Staff/Personnel Management
 * 
 * Handles UI logic for managing staff members (personal)
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let staffRoot = null;
    
    // Flag to track if delete handlers are already bound
    let deleteHandlersBound = false;

    // Flag to track if aa:staff:saved listener is already bound
    let staffSavedListenerBound = false;

    /**
     * @param {'staff' | 'staff_service_assignment'} source
     */
    function dispatchOnboardingSetupMutated(source) {
        document.dispatchEvent(new CustomEvent('aa:onboarding:setup-mutated', {
            detail: { source: source }
        }));
    }

    /**
     * @param {object|null|undefined} response JSON from aa_create_staff
     * @returns {'staff' | 'staff_service_assignment'}
     */
    function resolveCreateStaffOnboardingSource(response) {
        var autoAssign = response && response.data && response.data.auto_assign;

        if (autoAssign && autoAssign.created > 0) {
            return 'staff_service_assignment';
        }

        return 'staff';
    }

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

        // Setup delete handlers (only once, using event delegation)
        setupDeleteHandlers();

        // Refresh list after create/edit modal save (do not treat refresh failure as save failure)
        setupStaffSavedListener();
        
        // Load and render staff
        loadStaff(staffRoot);
        
        // Setup create staff button handler
        setupCreateStaffHandler();
    }

    /**
     * Recarga canónica tras aa:staff:saved (create o edit).
     */
    function setupStaffSavedListener() {
        if (staffSavedListenerBound) {
            return;
        }

        document.addEventListener('aa:staff:saved', function() {
            if (!staffRoot) {
                return;
            }

            try {
                loadStaff(staffRoot);
            } catch (error) {
                console.error('[Staff Section] Error al refrescar personal tras guardar:', error);
            }
        });

        staffSavedListenerBound = true;
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
            html += '<div class="aa-staff-header-toggle flex items-center gap-1.5 p-3 cursor-pointer" data-staff-id="' + staffId + '">';
            html += '<span class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 flex-shrink-0">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>';
            html += '</svg>';
            html += '</span>';
            html += '<span class="text-base font-semibold text-gray-600 min-w-0 flex-1">' + escapeHtml(staff.name) + '</span>';
            html += (window.AAAdmin && typeof window.AAAdmin.renderAssignmentItemOptions === 'function')
                ? window.AAAdmin.renderAssignmentItemOptions('staff', staffId)
                : '';
            html += '</div>';
            // Collapsable services panel
            html += '<div class="aa-staff-services-panel hidden p-3" data-staff-id="' + staffId + '">';
            html += '<div class="flex items-center justify-between gap-2">';
            html += '<div class="flex items-center gap-2">';
            html += '<button type="button" ';
            html += 'class="aa-staff-delete px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors" ';
            html += 'data-staff-id="' + staffId + '" ';
            html += '>Eliminar</button>';
            html += '<button type="button" ';
            html += 'class="aa-staff-edit px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition-colors" ';
            html += 'data-staff-id="' + staffId + '" ';
            html += '>Editar</button>';
            html += '</div>';
            html += '<label class="aa-staff-active-toggle flex items-center gap-2 cursor-pointer">';
            html += '<div class="relative">';
            html += '<input type="checkbox" ';
            html += 'class="toggle-staff-active peer sr-only" ';
            html += 'data-id="' + staffId + '" ';
            html += 'data-active="' + staff.active + '" ';
            if (isActive) {
                html += 'checked ';
            }
            html += '/>';
            html += '<div class="w-9 h-5 bg-gray-300 peer-checked:bg-indigo-600 rounded-full transition-colors duration-200"></div>';
            html += '<div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:translate-x-4"></div>';
            html += '</div>';
            html += '<span class="aa-staff-active-label text-sm text-gray-600">' + (isActive ? 'Desactivar' : 'Activar') + '</span>';
            html += '</label>';
            html += '</div>';
            html += '</div>';
            html += '</li>';
        });
        
        html += '</ul>';

        root.innerHTML = html;
        
        // Setup toggle handlers after rendering
        setupToggleHandlers();
        
        // Setup services panel toggle handlers
        setupServicesPanelHandlers();
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
     * Setup handlers for services panel toggle (whole header clickable)
     */
    function setupServicesPanelHandlers() {
        const headers = document.querySelectorAll('.aa-staff-header-toggle');
        
        headers.forEach(function(header) {
            header.addEventListener('click', function() {
                const row = this.closest('li');
                const panel = row ? row.querySelector('.aa-staff-services-panel') : null;
                
                if (panel) {
                    panel.classList.toggle('hidden');
                }
            });
        });
    }

    /**
     * @param {HTMLInputElement} toggle
     */
    function syncStaffActiveLabel(toggle) {
        const wrapper = toggle.closest('.aa-staff-active-toggle');
        const label = wrapper ? wrapper.querySelector('.aa-staff-active-label') : null;
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
        const staffId = parseInt(toggle.getAttribute('data-id'));
        const previousActive = parseInt(toggle.getAttribute('data-active'));
        const newActive = toggle.checked ? 1 : 0;
        syncStaffActiveLabel(toggle);
        
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
                if (newActive === 1) {
                    dispatchOnboardingSetupMutated('staff');
                }
            } else {
                // Revert toggle state on error
                toggle.checked = previousActive === 1;
                syncStaffActiveLabel(toggle);
                console.error('[Staff Section] Error al actualizar personal:', data);
            }
        })
        .catch(function(error) {
            // Revert toggle state on error
            toggle.checked = previousActive === 1;
            syncStaffActiveLabel(toggle);
            console.error('[Staff Section] Error en petición AJAX:', error);
        });
    }

    /**
     * Open the transversal Staff create modal (Personal).
     */
    function openStaffCreateModal() {
        if (window.AAAdmin && window.AAAdmin.StaffCreateModal
            && typeof window.AAAdmin.StaffCreateModal.openCreate === 'function') {
            window.AAAdmin.StaffCreateModal.openCreate();
            return;
        }
        console.error('[Staff Section] AAAdmin.StaffCreateModal.openCreate no disponible');
    }

    /**
     * Open the transversal Staff edit modal.
     * @param {number} staffId
     */
    function openStaffEditModal(staffId) {
        if (window.AAAdmin && window.AAAdmin.StaffCreateModal
            && typeof window.AAAdmin.StaffCreateModal.openEdit === 'function') {
            window.AAAdmin.StaffCreateModal.openEdit(staffId);
            return;
        }
        console.error('[Staff Section] AAAdmin.StaffCreateModal.openEdit no disponible');
    }

    /**
     * Setup handler for create staff button — opens StaffCreateModal
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
            openStaffCreateModal();
        });
        
        // Handle Enter key in input
        nameInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                openStaffCreateModal();
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
                dispatchOnboardingSetupMutated(resolveCreateStaffOnboardingSource(data));
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
     * Setup handlers for hide buttons (event delegation)
     * Only registers once to avoid duplicate listeners
     */
    function setupDeleteHandlers() {
        if (deleteHandlersBound) {
            return;
        }

        if (!staffRoot) {
            console.warn('[Staff Section] Cannot setup delete handlers: staffRoot not found');
            return;
        }

        staffRoot.addEventListener('click', function(event) {
            const editButton = event.target.closest('.aa-staff-edit');
            if (editButton) {
                event.preventDefault();
                const editStaffId = parseInt(editButton.getAttribute('data-staff-id'));
                if (editStaffId > 0) {
                    openStaffEditModal(editStaffId);
                }
                return;
            }

            const hideButton = event.target.closest('.aa-staff-delete');
            if (!hideButton) {
                return;
            }

            event.preventDefault();
            const staffId = parseInt(hideButton.getAttribute('data-staff-id'));
            if (staffId > 0) {
                hideStaff(staffId);
            }
        });

        deleteHandlersBound = true;
    }

    /**
     * Hide a staff member (soft hide via backend)
     * @param {number} staffId - ID of the staff member to hide
     */
    function hideStaff(staffId) {
        if (!confirm('¿Ocultar este personal?')) {
            return;
        }

        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl)
            || window.ajaxurl
            || '/wp-admin/admin-ajax.php';

        const formData = new FormData();
        formData.append('action', 'aa_delete_staff_db');
        formData.append('id', staffId);

        const hideButton = document.querySelector('.aa-staff-delete[data-staff-id="' + staffId + '"]');
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
                console.log('[Staff Section] Personal ocultado correctamente');
                if (staffRoot) {
                    loadStaff(staffRoot);
                }
            } else {
                console.error('[Staff Section] Error al ocultar personal:', data);
            }
        })
        .catch(function(error) {
            console.error('[Staff Section] Error en petición AJAX:', error);
        })
        .finally(function() {
            if (hideButton) {
                hideButton.disabled = false;
                hideButton.textContent = originalButtonText;
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
