/**
 * Staff / Personal Create/Edit Modal - Independent Modal Component
 *
 * Creates a staff member with name only, via aa_create_staff.
 * Edits an existing member (name + services) via aa_update_staff.
 *
 * API:
 * - AAAdmin.StaffCreateModal.openCreate() - Open modal to create new staff
 * - AAAdmin.StaffCreateModal.openEdit(staffId) - Open modal to edit existing staff
 *
 * Events:
 * - 'aa:staff:saved' - Emitted when staff is successfully saved (create or edit)
 *   Event detail: { staff: {...}, isEdit: boolean }
 * - 'aa:onboarding:setup-mutated' - Create flow, or edit when at least one service was added
 */

(function() {
    'use strict';

    var STAFF_NAME_MAX_LENGTH = 191;
    var editState = null;
    var editOpenSeq = 0;
    var editCloseObserver = null;
    var saveCloseTimer = null;

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

    function getAjaxUrl() {
        return (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl)
            || window.ajaxurl
            || '/wp-admin/admin-ajax.php';
    }

    function escapeHtml(text) {
        if (!text) {
            return '';
        }
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function postForm(formData) {
        return fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData
        }).then(function(response) {
            return response.json();
        });
    }

    /**
     * @returns {HTMLElement}
     */
    function createStaffForm() {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-staff';
        form.className = 'aa-modal-form';

        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';

        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-staff-nombre');
        nombreLabel.textContent = 'Nombre *';

        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-staff-nombre';
        nombreInput.name = 'name';
        nombreInput.required = true;
        nombreInput.placeholder = 'Ej: Juan Pérez';

        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-staff-status';
        statusMsg.className = 'aa-form-status';
        statusMsg.style.display = 'none';

        form.appendChild(nombreGroup);
        form.appendChild(statusMsg);

        return form;
    }

    /**
     * @returns {HTMLElement}
     */
    function createModalFooter() {
        const footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.id = 'aa-modal-save-staff';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Personal';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    function showFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-staff-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    function showEditFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-editar-staff-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    function resetEditState() {
        editState = null;
    }

    function clearSaveCloseTimer() {
        if (saveCloseTimer) {
            clearTimeout(saveCloseTimer);
            saveCloseTimer = null;
        }
    }

    function disconnectEditCloseObserver() {
        if (editCloseObserver) {
            editCloseObserver.disconnect();
            editCloseObserver = null;
        }
    }

    function cleanupEditModal() {
        disconnectEditCloseObserver();
        clearSaveCloseTimer();
        resetEditState();
    }

    function watchEditModalClose() {
        disconnectEditCloseObserver();

        var root = document.getElementById('aa-modal-root');
        if (!root || typeof MutationObserver === 'undefined') {
            return;
        }

        editCloseObserver = new MutationObserver(function() {
            if (!root.classList || !root.classList.contains('hidden')) {
                return;
            }

            cleanupEditModal();
        });

        editCloseObserver.observe(root, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    /**
     * @param {Array} services
     * @returns {Array<{id:number,name:string}>}
     */
    function normalizeSelectedServices(services) {
        var selected = [];
        var seen = {};
        var list = Array.isArray(services) ? services : [];

        list.forEach(function(service) {
            var id = parseInt(service && service.id, 10);
            var name = service && service.name ? String(service.name) : '';
            if (!(id > 0) || seen[id]) {
                return;
            }
            seen[id] = true;
            selected.push({ id: id, name: name });
        });

        selected.sort(function(a, b) {
            return a.name.localeCompare(b.name, 'es', { sensitivity: 'base' });
        });

        return selected;
    }

    /**
     * @param {Array} services
     * @returns {Array<{id:number,name:string,active:number}>}
     */
    function normalizeAssignableCatalog(services) {
        var catalog = [];
        var list = Array.isArray(services) ? services : [];

        list.forEach(function(service) {
            var id = parseInt(service && service.id, 10);
            var active = parseInt(service && service.active, 10) === 1;
            if (!(id > 0) || !active) {
                return;
            }
            catalog.push({
                id: id,
                name: service && service.name ? String(service.name) : '',
                active: 1
            });
        });

        catalog.sort(function(a, b) {
            return a.name.localeCompare(b.name, 'es', { sensitivity: 'base' });
        });

        return catalog;
    }

    function getSelectedIds() {
        if (!editState || !Array.isArray(editState.selected)) {
            return [];
        }
        return editState.selected.map(function(service) {
            return service.id;
        });
    }

    function renderEditServicesUI() {
        if (!editState) {
            return;
        }

        var select = document.getElementById('modal-editar-staff-service-select');
        var selectedDiv = document.getElementById('modal-editar-staff-services-selected');
        if (!select || !selectedDiv) {
            return;
        }

        var selectedIds = getSelectedIds();
        select.innerHTML = '<option value="">Selecciona los servicios que ofrece</option>';

        editState.catalog.forEach(function(service) {
            if (selectedIds.indexOf(service.id) !== -1) {
                return;
            }
            var option = document.createElement('option');
            option.value = String(service.id);
            option.textContent = service.name;
            select.appendChild(option);
        });

        if (editState.selected.length === 0) {
            selectedDiv.innerHTML = '<p class="text-xs text-gray-500">No hay servicios asignados.</p>';
            return;
        }

        var html = '<ul class="space-y-2">';
        editState.selected.forEach(function(service) {
            html += '<li class="flex items-center justify-between p-2 bg-gray-50 rounded-lg border border-gray-200">';
            html += '<span class="text-sm text-gray-600">' + escapeHtml(service.name) + '</span>';
            html += '<button type="button" ';
            html += 'class="aa-modal-staff-service-remove inline-flex items-center justify-center w-6 h-6 text-red-600 hover:text-red-700 hover:bg-red-50 rounded transition-colors" ';
            html += 'data-service-id="' + service.id + '" ';
            html += 'title="Quitar servicio">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            html += '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
            html += '</svg>';
            html += '</button>';
            html += '</li>';
        });
        html += '</ul>';
        selectedDiv.innerHTML = html;
    }

    function addLocalService(serviceId) {
        if (!editState) {
            return;
        }

        var id = parseInt(serviceId, 10);
        if (!(id > 0) || getSelectedIds().indexOf(id) !== -1) {
            return;
        }

        var catalogItem = null;
        for (var i = 0; i < editState.catalog.length; i++) {
            if (editState.catalog[i].id === id) {
                catalogItem = editState.catalog[i];
                break;
            }
        }

        if (!catalogItem) {
            return;
        }

        editState.selected.push({
            id: catalogItem.id,
            name: catalogItem.name
        });
        editState.selected.sort(function(a, b) {
            return a.name.localeCompare(b.name, 'es', { sensitivity: 'base' });
        });
        renderEditServicesUI();
    }

    function removeLocalService(serviceId) {
        if (!editState) {
            return;
        }

        var id = parseInt(serviceId, 10);
        editState.selected = editState.selected.filter(function(service) {
            return service.id !== id;
        });
        renderEditServicesUI();
    }

    /**
     * @param {object} staff
     * @param {Array} selectedServices
     * @param {Array} catalog
     * @returns {HTMLElement}
     */
    function createEditStaffForm(staff, selectedServices, catalog) {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-editar-staff';
        form.className = 'aa-modal-form';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.id = 'modal-editar-staff-id';
        idInput.name = 'id';
        idInput.value = staff && staff.id ? String(staff.id) : '';
        form.appendChild(idInput);

        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';

        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-editar-staff-nombre');
        nombreLabel.textContent = 'Nombre *';

        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-editar-staff-nombre';
        nombreInput.name = 'name';
        nombreInput.required = true;
        nombreInput.maxLength = STAFF_NAME_MAX_LENGTH;
        nombreInput.value = staff && staff.name ? String(staff.name) : '';
        nombreInput.placeholder = 'Ej: Juan Pérez';

        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        const servicesGroup = document.createElement('div');
        servicesGroup.className = 'aa-form-group';

        const servicesLabel = document.createElement('label');
        servicesLabel.setAttribute('for', 'modal-editar-staff-service-select');
        servicesLabel.textContent = 'Servicios que ofrece';

        const serviceSelect = document.createElement('select');
        serviceSelect.id = 'modal-editar-staff-service-select';
        serviceSelect.className = 'aa-form-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg';

        const selectedDiv = document.createElement('div');
        selectedDiv.id = 'modal-editar-staff-services-selected';
        selectedDiv.className = 'mt-3';

        servicesGroup.appendChild(servicesLabel);
        servicesGroup.appendChild(serviceSelect);
        servicesGroup.appendChild(selectedDiv);

        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-editar-staff-status';
        statusMsg.className = 'aa-form-status';
        statusMsg.style.display = 'none';

        form.appendChild(nombreGroup);
        form.appendChild(servicesGroup);
        form.appendChild(statusMsg);

        editState = {
            staffId: parseInt(staff && staff.id, 10) || 0,
            originalName: staff && staff.name ? String(staff.name) : '',
            originalServiceIds: normalizeSelectedServices(selectedServices).map(function(service) {
                return service.id;
            }),
            selected: normalizeSelectedServices(selectedServices),
            catalog: normalizeAssignableCatalog(catalog)
        };

        serviceSelect.addEventListener('change', function() {
            addLocalService(serviceSelect.value);
            serviceSelect.value = '';
        });

        selectedDiv.addEventListener('click', function(event) {
            var button = event.target.closest('.aa-modal-staff-service-remove');
            if (!button) {
                return;
            }
            event.preventDefault();
            removeLocalService(button.getAttribute('data-service-id'));
        });

        return form;
    }

    /**
     * @returns {HTMLElement}
     */
    function createEditModalFooter() {
        const footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.id = 'aa-modal-save-editar-staff';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Cambios';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    function saveNewStaff() {
        const form = document.getElementById('aa-modal-form-staff');
        if (!form) return;

        const name = document.getElementById('modal-staff-nombre').value.trim();

        if (!name) {
            showFormStatus('El nombre del personal es obligatorio.', true);
            return;
        }

        const formData = new FormData();
        formData.append('action', 'aa_create_staff');
        formData.append('name', name);

        const saveBtn = document.getElementById('aa-modal-save-staff');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        postForm(formData)
        .then(function(result) {
            if (result.success) {
                showFormStatus('Personal guardado correctamente.', false);

                const staffData = result.data && result.data.staff ? result.data.staff : {
                    name: name
                };

                document.dispatchEvent(new CustomEvent('aa:staff:saved', {
                    detail: {
                        staff: staffData,
                        isEdit: false
                    }
                }));

                dispatchOnboardingSetupMutated(resolveCreateStaffOnboardingSource(result));

                setTimeout(function() {
                    if (window.AAAdmin && window.AAAdmin.closeModal) {
                        window.AAAdmin.closeModal();
                    }
                }, 1000);
            } else {
                const errorMsg = result.data && result.data.message
                    ? result.data.message
                    : 'Error al guardar el personal.';
                showFormStatus(errorMsg, true);

                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar Personal';
                }
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            showFormStatus('Error de conexión. Intenta de nuevo.', true);

            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Personal';
            }
        });
    }

    function countAddedServices(originalIds, selectedIds) {
        var added = 0;
        selectedIds.forEach(function(id) {
            if (originalIds.indexOf(id) === -1) {
                added += 1;
            }
        });
        return added;
    }

    function saveEditedStaff() {
        if (!editState || !(editState.staffId > 0)) {
            return;
        }

        const nameInput = document.getElementById('modal-editar-staff-nombre');
        const name = nameInput ? nameInput.value.trim() : '';
        const selectedIds = getSelectedIds();

        if (!name) {
            showEditFormStatus('El nombre del personal es obligatorio.', true);
            return;
        }

        if (name.length > STAFF_NAME_MAX_LENGTH) {
            showEditFormStatus('El nombre no puede superar ' + STAFF_NAME_MAX_LENGTH + ' caracteres.', true);
            return;
        }

        const nonce = window.AA_STAFF_NONCES ? window.AA_STAFF_NONCES.update_staff : '';
        if (!nonce) {
            showEditFormStatus('Error de seguridad: nonce no disponible.', true);
            return;
        }

        const saveBtn = document.getElementById('aa-modal-save-editar-staff');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        const formData = new FormData();
        formData.append('action', 'aa_update_staff');
        formData.append('_wpnonce', nonce);
        formData.append('id', String(editState.staffId));
        formData.append('name', name);
        selectedIds.forEach(function(serviceId) {
            formData.append('service_ids[]', String(serviceId));
        });

        postForm(formData)
        .then(function(result) {
            if (result.success) {
                showEditFormStatus('Personal actualizado correctamente.', false);

                const staffData = result.data && result.data.staff ? result.data.staff : {
                    id: editState.staffId,
                    name: name,
                    services: editState.selected.slice()
                };

                document.dispatchEvent(new CustomEvent('aa:staff:saved', {
                    detail: {
                        staff: staffData,
                        isEdit: true
                    }
                }));

                var addedCount = result.data && typeof result.data.added_count === 'number'
                    ? result.data.added_count
                    : countAddedServices(editState.originalServiceIds, selectedIds);

                if (addedCount > 0) {
                    dispatchOnboardingSetupMutated('staff_service_assignment');
                }

                clearSaveCloseTimer();
                saveCloseTimer = setTimeout(function() {
                    saveCloseTimer = null;
                    resetEditState();
                    if (window.AAAdmin && window.AAAdmin.closeModal) {
                        window.AAAdmin.closeModal();
                    }
                }, 1000);
                return;
            }

            const errorMsg = result.data && result.data.message
                ? result.data.message
                : 'Error al actualizar el personal.';
            showEditFormStatus(errorMsg, true);

            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Cambios';
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            showEditFormStatus('Error de conexión. Intenta de nuevo.', true);

            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Cambios';
            }
        });
    }

    function openCreateStaffModal() {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        cleanupEditModal();
        editOpenSeq += 1;

        const formContent = createStaffForm();
        const footerContent = createModalFooter();

        window.AAAdmin.openModal({
            title: 'Nuevo Personal',
            body: formContent,
            footer: footerContent
        });

        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-staff');
            if (saveBtn) {
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', saveNewStaff);
            }

            const nombreInput = document.getElementById('modal-staff-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    function fetchStaffById(staffId) {
        const formData = new FormData();
        formData.append('action', 'aa_get_staff');

        return postForm(formData).then(function(result) {
            const list = result && result.success && result.data && Array.isArray(result.data.staff)
                ? result.data.staff
                : [];
            const wanted = String(staffId);

            for (var i = 0; i < list.length; i++) {
                if (String(list[i] && list[i].id) === wanted) {
                    return list[i];
                }
            }

            return null;
        });
    }

    function fetchStaffServices(staffId) {
        const formData = new FormData();
        formData.append('action', 'aa_get_staff_services');
        formData.append('staff_id', String(staffId));

        return postForm(formData).then(function(result) {
            if (!(result && result.success && result.data && Array.isArray(result.data.selected))) {
                throw new Error('No se pudieron cargar los servicios del personal');
            }
            return result.data.selected;
        });
    }

    function fetchServicesCatalog() {
        const formData = new FormData();
        formData.append('action', 'aa_get_services_db');

        return postForm(formData).then(function(result) {
            if (!(result && result.success && result.data && Array.isArray(result.data.services))) {
                throw new Error('No se pudo cargar el catálogo de servicios');
            }
            return result.data.services;
        });
    }

    function renderEditStaffModal(staff, selectedServices, catalog, seq) {
        disconnectEditCloseObserver();
        resetEditState();

        const formContent = createEditStaffForm(staff, selectedServices, catalog);
        const footerContent = createEditModalFooter();

        window.AAAdmin.openModal({
            title: 'Editar personal',
            body: formContent,
            footer: footerContent
        });

        renderEditServicesUI();
        watchEditModalClose();

        setTimeout(function() {
            if (seq !== editOpenSeq) {
                return;
            }

            const saveBtn = document.getElementById('aa-modal-save-editar-staff');
            if (saveBtn && saveBtn.parentNode) {
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', saveEditedStaff);
            }

            const nombreInput = document.getElementById('modal-editar-staff-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    /**
     * @param {number|string} staffId
     * @returns {Promise<object>}
     */
    function openEditStaffModal(staffId) {
        const id = parseInt(staffId, 10);

        if (!(id > 0)) {
            console.error('[StaffCreateModal] ID de personal inválido:', staffId);
            return Promise.reject(new Error('ID de personal inválido'));
        }

        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('AAAdmin.openModal no está disponible');
            return Promise.reject(new Error('Sistema de modales no disponible'));
        }

        var seq = ++editOpenSeq;

        return Promise.all([
            fetchStaffById(id),
            fetchStaffServices(id),
            fetchServicesCatalog()
        ])
            .then(function(results) {
                if (seq !== editOpenSeq) {
                    return results[0];
                }

                var staff = results[0];
                var selectedServices = results[1];
                var catalog = results[2];

                if (!staff) {
                    console.error('[StaffCreateModal] No se pudo cargar el personal', id);
                    throw new Error('Personal no encontrado');
                }

                renderEditStaffModal(staff, selectedServices, catalog, seq);
                return staff;
            })
            .catch(function(error) {
                if (!(error && error.message === 'Personal no encontrado')) {
                    console.error('[StaffCreateModal] No se pudo cargar el personal', id, error);
                }

                return Promise.reject(
                    error instanceof Error ? error : new Error('No se pudo cargar el personal')
                );
            });
    }

    window.AAAdmin = window.AAAdmin || {};

    window.AAAdmin.StaffCreateModal = {
        openCreate: openCreateStaffModal,
        openEdit: openEditStaffModal
    };

})();
