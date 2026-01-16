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
    
    // Services state management
    let availableServices = []; // Array of {id, name}
    let selectedServices = []; // Array of {id, name}

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
            resetServicesUI(); // Reset services state when opening modal
            loadStaffOptions();
            loadServiceAreaOptions();
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
     * Setup event listeners for modal
     */
    function setupEventListeners() {
        // Save button
        const saveBtn = document.getElementById('aa-assignment-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', handleSave);
        }

        // Staff select change listener
        setupStaffSelectListener();

        // Cancel button - handled by data-aa-modal-close attribute
    }

    /**
     * Listen to changes in staff select and fetch staff services
     */
    function setupStaffSelectListener() {
        const staffSelect = document.getElementById('aa-assignment-staff');
        if (!staffSelect) {
            console.warn('[Assignment Modal] Staff select not found for listener setup');
            return;
        }

        staffSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            console.log('[Assignment Modal] Staff selected:', selectedValue);
            
            // If no staff is selected, reset service select and selected list
            if (!selectedValue || selectedValue === '') {
                console.log('[Assignment Modal] No staff selected, resetting services');
                resetServicesUI();
                return;
            }
            
            // Fetch staff services via AJAX
            fetchStaffServices(selectedValue);
        });
        
        // Listen to service select changes
        const serviceSelect = document.getElementById('aa-assignment-service');
        if (serviceSelect) {
            serviceSelect.addEventListener('change', function() {
                const selectedValue = this.value;
                if (selectedValue && selectedValue !== '') {
                    addSelectedService(parseInt(selectedValue));
                    // Reset select to default option after adding
                    this.value = '';
                }
            });
        }
        
        // Listen to remove button clicks in selected services list
        const selectedDiv = document.getElementById('aa-assignment-services-selected');
        if (selectedDiv) {
            selectedDiv.addEventListener('click', function(event) {
                const removeButton = event.target.closest('.aa-assignment-service-remove');
                if (removeButton) {
                    const serviceId = parseInt(removeButton.getAttribute('data-service-id'));
                    if (serviceId > 0) {
                        removeSelectedService(serviceId);
                    }
                }
            });
        }
    }

    /**
     * Reset services UI (select and selected list)
     */
    function resetServicesUI() {
        availableServices = [];
        selectedServices = [];
        
        const serviceSelect = document.getElementById('aa-assignment-service');
        const selectedDiv = document.getElementById('aa-assignment-services-selected');
        
        if (serviceSelect) {
            serviceSelect.innerHTML = '<option value="">-- Selecciona servicio(s) --</option>';
        }
        
        if (selectedDiv) {
            selectedDiv.innerHTML = '';
        }
    }

    /**
     * Fetch staff services from database via AJAX and populate service select
     * @param {string} staffId - The staff ID
     */
    function fetchStaffServices(staffId) {
        const serviceSelect = document.getElementById('aa-assignment-service');
        if (!serviceSelect) {
            console.warn('[Assignment Modal] Service select not found');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'aa_get_staff_services');
        formData.append('staff_id', staffId);

        fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && data.data.selected) {
                console.log('[Assignment Modal] Staff services:', data.data.selected);
                console.log('[Assignment Modal] Services count:', data.data.count);
                
                // Reset state
                availableServices = data.data.selected.map(function(s) {
                    return { id: parseInt(s.id), name: s.name };
                });
                selectedServices = [];
                
                // Update UI
                updateServiceSelect();
                updateSelectedServicesList();
            } else {
                console.warn('[Assignment Modal] No services found or error in response:', data);
                const serviceSelect = document.getElementById('aa-assignment-service');
                if (serviceSelect) {
                    serviceSelect.innerHTML = '<option value="">-- Selecciona servicio(s) --</option>';
                }
                resetServicesUI();
            }
        })
        .catch(function(error) {
            console.error('[Assignment Modal] Error fetching staff services:', error);
            resetServicesUI();
        });
    }

    /**
     * Update service select with available services
     */
    function updateServiceSelect() {
        const serviceSelect = document.getElementById('aa-assignment-service');
        if (!serviceSelect) return;
        
        // Get IDs of selected services
        const selectedIds = selectedServices.map(function(s) {
            return s.id;
        });
        
        // Build options with only unselected services
        let html = '<option value="">-- Selecciona servicio(s) --</option>';
        
        availableServices.forEach(function(service) {
            if (selectedIds.indexOf(service.id) === -1) {
                html += '<option value="' + escapeHtml(service.id) + '">' + escapeHtml(service.name) + '</option>';
            }
        });
        
        serviceSelect.innerHTML = html;
    }

    /**
     * Add a service to the selected list
     * @param {number} serviceId - The service ID
     */
    function addSelectedService(serviceId) {
        // Find service in available services
        const service = availableServices.find(function(s) {
            return s.id === serviceId;
        });
        
        if (!service) {
            console.warn('[Assignment Modal] Service not found:', serviceId);
            return;
        }
        
        // Check if already selected
        const alreadySelected = selectedServices.find(function(s) {
            return s.id === serviceId;
        });
        
        if (alreadySelected) {
            console.warn('[Assignment Modal] Service already selected:', serviceId);
            return;
        }
        
        // Add to selected
        selectedServices.push(service);
        
        // Update UI
        updateServiceSelect();
        updateSelectedServicesList();
    }

    /**
     * Remove a service from the selected list
     * @param {number} serviceId - The service ID
     */
    function removeSelectedService(serviceId) {
        // Remove from selected
        selectedServices = selectedServices.filter(function(s) {
            return s.id !== serviceId;
        });
        
        // Update UI
        updateServiceSelect();
        updateSelectedServicesList();
    }

    /**
     * Update the selected services list UI
     */
    function updateSelectedServicesList() {
        const selectedDiv = document.getElementById('aa-assignment-services-selected');
        if (!selectedDiv) return;
        
        if (selectedServices.length === 0) {
            selectedDiv.innerHTML = '';
            return;
        }
        
        let html = '<ul class="space-y-2">';
        
        selectedServices.forEach(function(service) {
            html += '<li class="flex items-center justify-between p-2 bg-gray-50 rounded-lg border border-gray-200">';
            html += '<input type="hidden" name="service_ids[]" value="' + escapeHtml(service.id) + '">';
            html += '<span class="text-sm text-gray-900">' + escapeHtml(service.name) + '</span>';
            html += '<button type="button" ';
            html += 'class="aa-assignment-service-remove inline-flex items-center justify-center w-6 h-6 text-red-600 hover:text-red-700 hover:bg-red-50 rounded transition-colors" ';
            html += 'data-service-id="' + service.id + '" ';
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
        const capacityInput = document.getElementById('aa-assignment-capacity');

        // Validate required fields
        if (!dateInput.value || !startInput.value || !endInput.value || 
            !staffSelect.value || !areaSelect.value || selectedServices.length === 0) {
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
