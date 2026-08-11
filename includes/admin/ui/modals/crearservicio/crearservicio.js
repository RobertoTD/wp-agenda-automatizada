/**
 * Service Create Modal - Independent Modal Component
 *
 * Creates a service with name only, via aa_create_service.
 *
 * API:
 * - AAAdmin.ServiceCreateModal.openCreate() - Open modal to create new service
 *
 * Events:
 * - 'aa:service:saved' - Emitted when a service is successfully created
 *   Event detail: { service: {...}, isEdit: false }
 * - 'aa:onboarding:setup-mutated' - Same semantics as servicesSection create flow
 */

(function() {
    'use strict';

    /**
     * @param {'service' | 'staff_service_assignment'} source
     */
    function dispatchOnboardingSetupMutated(source) {
        document.dispatchEvent(new CustomEvent('aa:onboarding:setup-mutated', {
            detail: { source: source }
        }));
    }

    /**
     * @param {object|null|undefined} response JSON from aa_create_service
     * @returns {'service' | 'staff_service_assignment'}
     */
    function resolveCreateServiceOnboardingSource(response) {
        var autoAssign = response && response.data && response.data.auto_assign;

        if (autoAssign && autoAssign.created > 0) {
            return 'staff_service_assignment';
        }

        return 'service';
    }

    /**
     * @returns {HTMLElement}
     */
    function createServiceForm() {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-servicio';
        form.className = 'aa-modal-form';

        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';

        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-servicio-nombre');
        nombreLabel.textContent = 'Nombre *';

        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-servicio-nombre';
        nombreInput.name = 'name';
        nombreInput.required = true;
        nombreInput.placeholder = 'Ej: Consulta médica';

        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-servicio-status';
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
        saveBtn.id = 'aa-modal-save-servicio';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Servicio';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    function showFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-servicio-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    function saveNewService() {
        const form = document.getElementById('aa-modal-form-servicio');
        if (!form) return;

        const name = document.getElementById('modal-servicio-nombre').value.trim();

        if (!name) {
            showFormStatus('El nombre del servicio es obligatorio.', true);
            return;
        }

        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'aa_create_service');
        formData.append('name', name);

        const saveBtn = document.getElementById('aa-modal-save-servicio');
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
                showFormStatus('Servicio guardado correctamente.', false);

                const serviceData = result.data && result.data.service ? result.data.service : {
                    name: name
                };

                document.dispatchEvent(new CustomEvent('aa:service:saved', {
                    detail: {
                        service: serviceData,
                        isEdit: false
                    }
                }));

                dispatchOnboardingSetupMutated(resolveCreateServiceOnboardingSource(result));

                setTimeout(function() {
                    if (window.AAAdmin && window.AAAdmin.closeModal) {
                        window.AAAdmin.closeModal();
                    }
                }, 1000);
            } else {
                const errorMsg = result.data && result.data.message
                    ? result.data.message
                    : 'Error al guardar el servicio.';
                showFormStatus(errorMsg, true);

                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar Servicio';
                }
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            showFormStatus('Error de conexión. Intenta de nuevo.', true);

            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Servicio';
            }
        });
    }

    function openCreateServiceModal() {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        const formContent = createServiceForm();
        const footerContent = createModalFooter();

        window.AAAdmin.openModal({
            title: 'Nuevo Servicio',
            body: formContent,
            footer: footerContent
        });

        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-servicio');
            if (saveBtn) {
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', saveNewService);
            }

            const nombreInput = document.getElementById('modal-servicio-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    window.AAAdmin = window.AAAdmin || {};

    window.AAAdmin.ServiceCreateModal = {
        openCreate: openCreateServiceModal
    };

})();
