/**
 * Area / Zona de Atención Create Modal - Independent Modal Component
 *
 * Creates a service area with name only, via aa_create_service_area.
 * Public API uses AreaCreateModal; UI copy uses "Zona de Atención".
 *
 * API:
 * - AAAdmin.AreaCreateModal.openCreate() - Open modal to create new area/zona
 *
 * Events:
 * - 'aa:area:saved' - Emitted when an area is successfully created
 *   Event detail: { area: {...}, isEdit: false }
 * - 'aa:onboarding:setup-mutated' - Same semantics as areas.js create flow (source: 'area')
 */

(function() {
    'use strict';

    /**
     * @param {'area'} source
     */
    function dispatchOnboardingSetupMutated(source) {
        document.dispatchEvent(new CustomEvent('aa:onboarding:setup-mutated', {
            detail: { source: source }
        }));
    }

    /**
     * @returns {HTMLElement}
     */
    function createAreaForm() {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-zona';
        form.className = 'aa-modal-form';

        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';

        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-zona-nombre');
        nombreLabel.textContent = 'Nombre *';

        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-zona-nombre';
        nombreInput.name = 'name';
        nombreInput.required = true;
        nombreInput.placeholder = 'Ej: Consultorio 3';

        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-zona-status';
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
        saveBtn.id = 'aa-modal-save-zona';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Zona';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    function showFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-zona-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    function saveNewArea() {
        const form = document.getElementById('aa-modal-form-zona');
        if (!form) return;

        const name = document.getElementById('modal-zona-nombre').value.trim();

        if (!name) {
            showFormStatus('El nombre de la zona es obligatorio.', true);
            return;
        }

        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'aa_create_service_area');
        formData.append('name', name);

        const saveBtn = document.getElementById('aa-modal-save-zona');
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
                showFormStatus('Zona de atención guardada correctamente.', false);

                const areaData = result.data && result.data.area ? result.data.area : {
                    name: name
                };

                document.dispatchEvent(new CustomEvent('aa:area:saved', {
                    detail: {
                        area: areaData,
                        isEdit: false
                    }
                }));

                dispatchOnboardingSetupMutated('area');

                setTimeout(function() {
                    if (window.AAAdmin && window.AAAdmin.closeModal) {
                        window.AAAdmin.closeModal();
                    }
                }, 1000);
            } else {
                const errorMsg = result.data && result.data.message
                    ? result.data.message
                    : 'Error al guardar la zona de atención.';
                showFormStatus(errorMsg, true);

                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar Zona';
                }
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            showFormStatus('Error de conexión. Intenta de nuevo.', true);

            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Zona';
            }
        });
    }

    function openCreateAreaModal() {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        const formContent = createAreaForm();
        const footerContent = createModalFooter();

        window.AAAdmin.openModal({
            title: 'Nueva Zona de Atención',
            body: formContent,
            footer: footerContent
        });

        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-zona');
            if (saveBtn) {
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', saveNewArea);
            }

            const nombreInput = document.getElementById('modal-zona-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    window.AAAdmin = window.AAAdmin || {};

    window.AAAdmin.AreaCreateModal = {
        openCreate: openCreateAreaModal
    };

})();
