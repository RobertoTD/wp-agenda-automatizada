/**
 * Assignment Modal - Logic Controller
 * 
 * Handles:
 * - Open/close modal
 * - Initialize flatpickr on date input
 * - Load staff via AJAX
 * - Load service areas via AJAX
 * - Load services from aa_google_motivo
 * - Submit form via AJAX
 * - Display backend errors
 */

(function() {
    'use strict';

    // Flatpickr instance reference
    let datePicker = null;

    /**
     * Get AJAX URL
     * @returns {string}
     */
    function getAjaxUrl() {
        return (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl) 
            || window.ajaxurl 
            || '/wp-admin/admin-ajax.php';
    }

    /**
     * Open the assignment modal
     */
    function openModal() {
        // Check if modal system exists
        if (!window.AAAdmin || !window.AAAdmin.modal) {
            console.error('[Assignment Modal] Modal system not found');
            return;
        }

        // Check if HTML generator exists
        if (!window.AAAssignmentModalHTML) {
            console.error('[Assignment Modal] Modal HTML generator not found');
            return;
        }

        // Open modal with content
        AAAdmin.modal.open({
            title: 'Abrir horario',
            body: AAAssignmentModalHTML.getBody(),
            footer: AAAssignmentModalHTML.getFooter()
        });

        // Initialize modal after opening
        setTimeout(function() {
            initializeDatePicker();
            loadStaffOptions();
            loadServiceAreaOptions();
            loadServiceOptions();
            setupEventListeners();
        }, 100);
    }

    /**
     * Initialize date picker
     * Uses native HTML5 date input (flatpickr not available in iframe)
     */
    function initializeDatePicker() {
        const dateInput = document.getElementById('aa-assignment-date');
        if (!dateInput) return;

        // Set min date to today
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
    }

    /**
     * Load active staff into select
     */
    function loadStaffOptions() {
        const staffSelect = document.getElementById('aa-assignment-staff');
        if (!staffSelect) return;

        const formData = new FormData();
        formData.append('action', 'aa_get_staff');

        fetch(getAjaxUrl() + '?only_active=true', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.staff) {
                let html = '<option value="">-- Selecciona personal --</option>';
                
                data.data.staff.forEach(function(member) {
                    // Only show active staff
                    if (parseInt(member.active) === 1) {
                        html += '<option value="' + escapeHtml(member.id) + '">' + escapeHtml(member.name) + '</option>';
                    }
                });
                
                staffSelect.innerHTML = html;
            } else {
                staffSelect.innerHTML = '<option value="">No hay personal disponible</option>';
            }
        })
        .catch(function(error) {
            console.error('[Assignment Modal] Error loading staff:', error);
            staffSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
    }

    /**
     * Load active service areas into select
     */
    function loadServiceAreaOptions() {
        const areaSelect = document.getElementById('aa-assignment-area');
        if (!areaSelect) return;

        const formData = new FormData();
        formData.append('action', 'aa_get_service_areas');

        fetch(getAjaxUrl() + '?only_active=true', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.service_areas) {
                let html = '<option value="">-- Selecciona zona --</option>';
                
                data.data.service_areas.forEach(function(area) {
                    // Only show active areas
                    if (parseInt(area.active) === 1) {
                        html += '<option value="' + escapeHtml(area.id) + '">' + escapeHtml(area.name) + '</option>';
                    }
                });
                
                areaSelect.innerHTML = html;
            } else {
                areaSelect.innerHTML = '<option value="">No hay zonas disponibles</option>';
            }
        })
        .catch(function(error) {
            console.error('[Assignment Modal] Error loading areas:', error);
            areaSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
    }

    /**
     * Load services into select
     */
    function loadServiceOptions() {
        const serviceSelect = document.getElementById('aa-assignment-service');
        if (!serviceSelect) return;

        const formData = new FormData();
        formData.append('action', 'aa_get_services');

        fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.services) {
                let html = '<option value="">-- Selecciona servicio --</option>';
                
                data.data.services.forEach(function(service) {
                    html += '<option value="' + escapeHtml(service) + '">' + escapeHtml(service) + '</option>';
                });
                
                serviceSelect.innerHTML = html;
            } else {
                serviceSelect.innerHTML = '<option value="">No hay servicios configurados</option>';
            }
        })
        .catch(function(error) {
            console.error('[Assignment Modal] Error loading services:', error);
            serviceSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
    }

    /**
     * Setup event listeners for modal
     */
    function setupEventListeners() {
        // Save button
        const saveBtn = document.getElementById('aa-assignment-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', handleSave);
        }

        // Cancel button - handled by data-aa-modal-close attribute
    }

    /**
     * Handle save button click
     */
    function handleSave() {
        // Get form values
        const form = document.getElementById('aa-assignment-form');
        if (!form) return;

        const dateInput = document.getElementById('aa-assignment-date');
        const startInput = document.getElementById('aa-assignment-start');
        const endInput = document.getElementById('aa-assignment-end');
        const staffSelect = document.getElementById('aa-assignment-staff');
        const areaSelect = document.getElementById('aa-assignment-area');
        const serviceSelect = document.getElementById('aa-assignment-service');
        const capacityInput = document.getElementById('aa-assignment-capacity');

        // Validate required fields
        if (!dateInput.value || !startInput.value || !endInput.value || 
            !staffSelect.value || !areaSelect.value || !serviceSelect.value) {
            showError('Por favor completa todos los campos requeridos');
            return;
        }

        // Validate time range
        if (startInput.value >= endInput.value) {
            showError('La hora de fin debe ser posterior a la hora de inicio');
            return;
        }

        // Disable save button
        const saveBtn = document.getElementById('aa-assignment-save');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'aa_create_assignment');
        formData.append('assignment_date', dateInput.value);
        formData.append('start_time', startInput.value);
        formData.append('end_time', endInput.value);
        formData.append('staff_id', staffSelect.value);
        formData.append('service_area_id', areaSelect.value);
        formData.append('service_key', serviceSelect.value);
        formData.append('capacity', capacityInput.value || 1);

        // Send AJAX request
        fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Close modal
                if (window.AAAdmin && window.AAAdmin.modal) {
                    AAAdmin.modal.close();
                }
                
                // Reload assignments list if function exists
                if (window.reloadAssignmentsList) {
                    window.reloadAssignmentsList();
                } else {
                    // Fallback: trigger custom event
                    document.dispatchEvent(new CustomEvent('aa-assignment-created'));
                }
            } else {
                const message = data.data && data.data.message 
                    ? data.data.message 
                    : 'Error al guardar la asignación';
                showError(message);
            }
        })
        .catch(function(error) {
            console.error('[Assignment Modal] Error saving:', error);
            showError('Error de conexión al guardar');
        })
        .finally(function() {
            // Re-enable save button
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar asignación';
            }
        });
    }

    /**
     * Show error message in modal
     * @param {string} message
     */
    function showError(message) {
        const errorDiv = document.getElementById('aa-assignment-error');
        if (!errorDiv) return;

        const errorText = errorDiv.querySelector('p');
        if (errorText) {
            errorText.textContent = message;
        }
        
        errorDiv.classList.remove('hidden');
    }

    /**
     * Hide error message
     */
    function hideError() {
        const errorDiv = document.getElementById('aa-assignment-error');
        if (errorDiv) {
            errorDiv.classList.add('hidden');
        }
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize modal trigger
     */
    function init() {
        const openButton = document.getElementById('aa-open-assignment-modal');
        if (openButton) {
            openButton.addEventListener('click', openModal);
        }

        // Listen for assignment created event to reload list
        document.addEventListener('aa-assignment-created', function() {
            // Try to reload assignments section
            const assignmentsRoot = document.getElementById('aa-assignments-root');
            if (assignmentsRoot && window.loadAssignments) {
                window.loadAssignments(assignmentsRoot);
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose open function globally for other modules
    window.openAssignmentModal = openModal;

})();
