/**
 * Assignment Modal - Logic Controller
 * 
 * Handles:
 * - Open/close modal using template from index.php
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
     * Get body content from template
     * @returns {string} HTML string for modal body
     */
    function getBodyFromTemplate() {
        const template = document.getElementById('aa-assignment-modal-template');
        if (!template) {
            console.error('[Assignment Modal] Template not found: aa-assignment-modal-template');
            console.error('[Assignment Modal] Available templates:', 
                Array.from(document.querySelectorAll('template')).map(t => t.id));
            return '<p class="text-red-500">Error: Template no encontrado. Verifique que index.php se haya cargado correctamente.</p>';
        }
        
        // Clone template content and serialize to HTML string
        const clone = template.content.cloneNode(true);
        const container = document.createElement('div');
        container.appendChild(clone);
        return container.innerHTML;
    }

    /**
     * Get footer content from template
     * @returns {string} HTML string for modal footer
     */
    function getFooterFromTemplate() {
        const template = document.getElementById('aa-assignment-modal-footer-template');
        if (!template) {
            console.error('[Assignment Modal] Footer template not found: aa-assignment-modal-footer-template');
            console.error('[Assignment Modal] Available templates:', 
                Array.from(document.querySelectorAll('template')).map(t => t.id));
            return '<div class="text-red-500 text-sm">Error: Footer template no encontrado</div>';
        }
        
        // Clone template content and serialize to HTML string
        const clone = template.content.cloneNode(true);
        const container = document.createElement('div');
        container.appendChild(clone);
        return container.innerHTML;
    }

    /**
     * Open the assignment modal
     */
    function openModal() {
        // Check if modal system exists
        if (!window.AAAdmin || !window.AAAdmin.modal) {
            console.error('[Assignment Modal] Modal system not found');
            console.error('[Assignment Modal] AAAdmin available:', !!window.AAAdmin);
            return;
        }

        // Get templates
        const bodyContent = getBodyFromTemplate();
        const footerContent = getFooterFromTemplate();

        // Check if templates were loaded correctly
        if (bodyContent.includes('Error: Template no encontrado')) {
            console.error('[Assignment Modal] Cannot open modal: body template not found');
            return;
        }

        // Clean up any existing date picker instance before opening
        cleanupDatePicker();

        // Open modal with content from templates
        AAAdmin.modal.open({
            title: 'Abrir horario',
            body: bodyContent,
            footer: footerContent
        });

        // Initialize modal after opening
        setTimeout(function() {
            initializeDatePicker();
            setDefaultStartTime();
            loadStaffOptions();
            loadServiceAreaOptions();
            loadServiceOptions();
            setupEventListeners();
        }, 100);
    }

    /**
     * Initialize date picker with Flatpickr
     */
    function initializeDatePicker() {
        const dateInput = document.getElementById('aa-assignment-date');
        if (!dateInput) {
            console.warn('[Assignment Modal] Date input not found');
            return;
        }

        // Check if Flatpickr is available
        if (typeof flatpickr === 'undefined') {
            console.error('[Assignment Modal] Flatpickr is not available');
            // Fallback to native date input
            dateInput.type = 'date';
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
            dateInput.value = today; // Set current date as default
            return;
        }

        // Destroy existing instance if any
        if (datePicker) {
            datePicker.destroy();
            datePicker = null;
        }

        // Initialize Flatpickr
        try {
            // Get current date in YYYY-MM-DD format
            const today = new Date();
            const todayStr = today.getFullYear() + '-' + 
                           String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(today.getDate()).padStart(2, '0');
            
            datePicker = flatpickr(dateInput, {
                dateFormat: 'Y-m-d',
                locale: 'es',
                minDate: 'today',
                allowInput: false,
                clickOpens: true,
                defaultDate: todayStr,
                onChange: function(selectedDates, dateStr, instance) {
                    // Validate that a date was selected
                    if (dateStr) {
                        console.log('[Assignment Modal] Date selected:', dateStr);
                    }
                }
            });

            console.log('[Assignment Modal] Flatpickr initialized successfully');
        } catch (error) {
            console.error('[Assignment Modal] Error initializing Flatpickr:', error);
            // Fallback to native date input
            dateInput.type = 'date';
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
            dateInput.value = today; // Set current date as default
        }
    }

    /**
     * Set default start time to current time
     */
    function setDefaultStartTime() {
        const startInput = document.getElementById('aa-assignment-start');
        if (!startInput) return;
        
        // Get current time in HH:MM format
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const currentTime = hours + ':' + minutes;
        
        // Set the value
        startInput.value = currentTime;
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
                // Clean up date picker before closing
                cleanupDatePicker();
                
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
     * Clean up date picker instance
     */
    function cleanupDatePicker() {
        if (datePicker) {
            try {
                datePicker.destroy();
                datePicker = null;
                console.log('[Assignment Modal] Flatpickr instance destroyed');
            } catch (error) {
                console.error('[Assignment Modal] Error destroying Flatpickr:', error);
            }
        }
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

        // Clean up when modal closes (listen to modal close events)
        // This is a fallback - the modal system should handle cleanup, but we ensure it here
        if (window.AAAdmin && window.AAAdmin.modal) {
            // Store original close function
            const originalClose = window.AAAdmin.modal.close;
            if (originalClose) {
                window.AAAdmin.modal.close = function() {
                    cleanupDatePicker();
                    originalClose.apply(this, arguments);
                };
            }
        }
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
