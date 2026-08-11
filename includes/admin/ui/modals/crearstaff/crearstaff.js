/**
 * Staff / Personal Create Modal - Independent Modal Component
 *
 * Creates a staff member with name only, via aa_create_staff.
 *
 * API:
 * - AAAdmin.StaffCreateModal.openCreate() - Open modal to create new staff
 *
 * Events:
 * - 'aa:staff:saved' - Emitted when staff is successfully created
 *   Event detail: { staff: {...}, isEdit: false }
 * - 'aa:onboarding:setup-mutated' - Same semantics as staff.js create flow
 */

(function() {
    'use strict';

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

    function saveNewStaff() {
        const form = document.getElementById('aa-modal-form-staff');
        if (!form) return;

        const name = document.getElementById('modal-staff-nombre').value.trim();

        if (!name) {
            showFormStatus('El nombre del personal es obligatorio.', true);
            return;
        }

        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'aa_create_staff');
        formData.append('name', name);

        const saveBtn = document.getElementById('aa-modal-save-staff');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
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

    function openCreateStaffModal() {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

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

    window.AAAdmin = window.AAAdmin || {};

    window.AAAdmin.StaffCreateModal = {
        openCreate: openCreateStaffModal
    };

})();
