/**
 * Assignments Section - Assignments Management
 * 
 * Handles UI logic for managing assignments (staff + zone + service + time)
 * Renders assignments as collapsible cards with delete functionality
 */

(function() {
    'use strict';

    // Store root element reference for reuse
    let assignmentsRoot = null;

    /**
     * Initialize the assignments section
     */
    function initAssignmentsSection() {
        assignmentsRoot = document.getElementById('aa-assignments-root');
        
        // Fail safely if root doesn't exist
        if (!assignmentsRoot) {
            console.warn('[Assignments Section] Root element #aa-assignments-root not found');
            return;
        }

        // Load and render assignments
        loadAssignments();
    }

    /**
     * Load assignments from server via AJAX
     */
    function loadAssignments() {
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_get_assignments');

        // Show loading state
        assignmentsRoot.innerHTML = '<p class="text-sm text-gray-500">Cargando asignaciones...</p>';

        // Make AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.assignments) {
                renderAssignments(data.data.assignments);
            } else {
                console.error('[Assignments Section] Error en respuesta:', data);
                assignmentsRoot.innerHTML = '<p class="text-sm text-red-500">Error al cargar las asignaciones.</p>';
            }
        })
        .catch(function(error) {
            console.error('[Assignments Section] Error en petición AJAX:', error);
            assignmentsRoot.innerHTML = '<p class="text-sm text-red-500">Error al conectar con el servidor.</p>';
        });
    }

    /**
     * Render assignments as collapsible cards
     * @param {Array} assignments - Array of assignment objects
     */
    function renderAssignments(assignments) {
        if (!assignments || assignments.length === 0) {
            assignmentsRoot.innerHTML = '<p class="text-sm text-gray-500">No hay asignaciones registradas.</p>';
            return;
        }

        // Build HTML for assignments list
        let html = '<div class="space-y-3">';
        
        assignments.forEach(function(assignment) {
            const assignmentId = parseInt(assignment.id);
            const date = formatDate(assignment.assignment_date);
            const timeRange = formatTime(assignment.start_time) + ' - ' + formatTime(assignment.end_time);
            
            // Card container with data-aa-card attribute for collapsible behavior
            html += '<div class="aa-assignment-card border border-gray-200 rounded-lg overflow-hidden" data-aa-card data-id="' + assignmentId + '">';
            
            // Card header (clickable to expand/collapse)
            html += '<div class="aa-assignment-header flex items-center gap-3 p-4 bg-gray-50 cursor-pointer" data-aa-card-toggle>';
            
            // Icon
            html += '<span class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex-shrink-0">';
            html += '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>';
            html += '</svg>';
            html += '</span>';
            
            // Header info
            html += '<div class="flex-1 min-w-0">';
            html += '<div class="text-sm font-medium text-gray-900">' + escapeHtml(date) + '</div>';
            html += '<div class="text-xs text-gray-500">' + escapeHtml(timeRange) + '</div>';
            html += '</div>';
            
            // Chevron indicator (solo uno, al extremo derecho)
            html += '<svg class="aa-card-chevron w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>';
            html += '</svg>';
            
            html += '</div>'; // End header
            
            // Card body (hidden by default, shown when expanded)
            html += '<div class="aa-assignment-body hidden p-4 bg-white border-t border-gray-100">';
            
            // Assignment details grid
            html += '<div class="grid grid-cols-2 gap-4 mb-4">';
            
            // Responsable (staff name)
            html += '<div>';
            html += '<span class="text-xs text-gray-500 block">Responsable</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(assignment.staff_name || '-') + '</span>';
            html += '</div>';
            
            // Zona (service area name)
            html += '<div>';
            html += '<span class="text-xs text-gray-500 block">Zona</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(assignment.service_area_name || '-') + '</span>';
            html += '</div>';
            
            // Servicio (service key)
            html += '<div>';
            html += '<span class="text-xs text-gray-500 block">Servicio</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(assignment.service_key || '-') + '</span>';
            html += '</div>';
            
            // Capacidad (capacity)
            html += '<div>';
            html += '<span class="text-xs text-gray-500 block">Capacidad</span>';
            html += '<span class="text-sm font-medium text-gray-900">' + escapeHtml(assignment.capacity || '1') + '</span>';
            html += '</div>';
            
            html += '</div>'; // End grid
            
            // Delete button
            html += '<div class="pt-3 border-t border-gray-100">';
            html += '<button type="button" class="aa-delete-assignment px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium rounded-lg transition-colors" data-id="' + assignmentId + '">';
            html += '<svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>';
            html += '</svg>';
            html += 'Eliminar';
            html += '</button>';
            html += '</div>';
            
            html += '</div>'; // End body
            
            html += '</div>'; // End card
        });
        
        html += '</div>';

        assignmentsRoot.innerHTML = html;
        
        // Setup card toggle handlers
        setupCardToggleHandlers();
        
        // Setup delete handlers
        setupDeleteHandlers();
    }

    /**
     * Setup handlers for card toggle (expand/collapse)
     */
    function setupCardToggleHandlers() {
        const toggles = assignmentsRoot.querySelectorAll('[data-aa-card-toggle]');
        
        toggles.forEach(function(toggle) {
            toggle.addEventListener('click', function(event) {
                // Don't toggle if clicking on a button inside
                if (event.target.closest('button')) {
                    return;
                }
                
                const card = this.closest('[data-aa-card]');
                if (!card) return;
                
                const body = card.querySelector('.aa-assignment-body');
                const chevron = card.querySelector('.aa-card-chevron');
                
                if (body) {
                    body.classList.toggle('hidden');
                }
                
                if (chevron) {
                    chevron.classList.toggle('rotate-180');
                }
            });
        });
    }

    /**
     * Setup handlers for delete buttons
     */
    function setupDeleteHandlers() {
        const deleteButtons = assignmentsRoot.querySelectorAll('.aa-delete-assignment');
        
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.stopPropagation();
                
                const assignmentId = parseInt(this.getAttribute('data-id'));
                if (assignmentId > 0) {
                    handleDeleteAssignment(assignmentId);
                }
            });
        });
    }

    /**
     * Handle assignment deletion
     * @param {number} id - Assignment ID to delete
     */
    function handleDeleteAssignment(id) {
        // Simple confirmation
        if (!confirm('¿Estás seguro de eliminar esta asignación?')) {
            return;
        }
        
        // Get ajaxurl from global data
        const ajaxurl = (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';

        // Prepare FormData for AJAX request
        const formData = new FormData();
        formData.append('action', 'aa_delete_assignment');
        formData.append('id', id);

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
                // Re-render the list
                loadAssignments();
            } else {
                console.error('[Assignments Section] Error al eliminar:', data);
                alert('Error al eliminar la asignación');
            }
        })
        .catch(function(error) {
            console.error('[Assignments Section] Error en petición AJAX:', error);
            alert('Error al conectar con el servidor');
        });
    }

    /**
     * Format date for display
     * @param {string} dateStr - Date string in YYYY-MM-DD format
     * @returns {string} Formatted date
     */
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        
        try {
            const parts = dateStr.split('-');
            if (parts.length !== 3) return dateStr;
            
            const year = parts[0];
            const month = parts[1];
            const day = parts[2];
            
            // Return in DD/MM/YYYY format
            return day + '/' + month + '/' + year;
        } catch (e) {
            return dateStr;
        }
    }

    /**
     * Format time for display
     * @param {string} timeStr - Time string in HH:MM:SS format
     * @returns {string} Formatted time (HH:MM)
     */
    function formatTime(timeStr) {
        if (!timeStr) return '-';
        
        try {
            const parts = timeStr.split(':');
            if (parts.length < 2) return timeStr;
            
            return parts[0] + ':' + parts[1];
        } catch (e) {
            return timeStr;
        }
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAssignmentsSection);
    } else {
        // DOM already ready
        initAssignmentsSection();
    }

    // Expose loadAssignments globally for modal to reload list
    window.loadAssignments = loadAssignments;
    window.reloadAssignmentsList = loadAssignments;

    // Listen for assignment created event
    document.addEventListener('aa-assignment-created', function() {
        loadAssignments();
    });

})();
