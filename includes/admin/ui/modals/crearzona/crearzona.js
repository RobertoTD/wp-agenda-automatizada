/**
 * Area / Zona de Atención Create/Edit Modal - Independent Modal Component
 *
 * Creates a service area with name only, via aa_create_service_area.
 * Edits an existing area (name + color) via aa_update_service_area.
 * Public API uses AreaCreateModal; UI copy uses "Zona de Atención".
 *
 * API:
 * - AAAdmin.AreaCreateModal.openCreate() - Open modal to create new area/zona
 * - AAAdmin.AreaCreateModal.openEdit(areaId) - Open modal to edit existing area/zona
 *
 * Events:
 * - 'aa:area:saved' - Emitted when an area is successfully saved (create or edit)
 *   Event detail: { area: {...}, isEdit: boolean }
 * - 'aa:onboarding:setup-mutated' - Same semantics as areas.js create flow (source: 'area')
 */

(function() {
    'use strict';

    var DEFAULT_AREA_COLOR = '#3b82f6';
    var editColorPicker$ = null;
    var editCloseObserver = null;
    var HEX_COLOR_RE = /^#[a-fA-F0-9]{6}$/;

    /**
     * @param {'area'} source
     */
    function dispatchOnboardingSetupMutated(source) {
        document.dispatchEvent(new CustomEvent('aa:onboarding:setup-mutated', {
            detail: { source: source }
        }));
    }

    function getAjaxUrl() {
        return (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl)
            || window.ajaxurl
            || '/wp-admin/admin-ajax.php';
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

    function showEditFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-editar-zona-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    function normalizeAreaColor(color) {
        var value = color == null ? '' : String(color).trim();
        if (HEX_COLOR_RE.test(value)) {
            return value;
        }
        return DEFAULT_AREA_COLOR;
    }

    function disconnectEditCloseObserver() {
        if (editCloseObserver) {
            editCloseObserver.disconnect();
            editCloseObserver = null;
        }
    }

    function destroyEditColorPicker() {
        if (!editColorPicker$) {
            return;
        }

        try {
            if (typeof editColorPicker$.wpColorPicker === 'function') {
                editColorPicker$.wpColorPicker('close');
                editColorPicker$.wpColorPicker('destroy');
            }
        } catch (error) {
            console.warn('[AreaCreateModal] No se pudo destruir wpColorPicker', error);
        }

        editColorPicker$ = null;
    }

    function cleanupEditModal() {
        disconnectEditCloseObserver();
        destroyEditColorPicker();
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

    function initEditColorPicker(input) {
        destroyEditColorPicker();

        if (!input) {
            return;
        }

        if (typeof jQuery === 'undefined' || typeof jQuery.fn.wpColorPicker === 'undefined') {
            console.warn('[AreaCreateModal] wp-color-picker no disponible');
            return;
        }

        editColorPicker$ = jQuery(input);
        editColorPicker$.wpColorPicker({
            defaultColor: DEFAULT_AREA_COLOR,
            change: function(event, ui) {
                if (ui && ui.color) {
                    input.value = ui.color.toString();
                }
            },
            clear: function() {
                input.value = DEFAULT_AREA_COLOR;
            }
        });
    }

    /**
     * @param {object} area
     * @returns {HTMLElement}
     */
    function createEditAreaForm(area) {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-editar-zona';
        form.className = 'aa-modal-form';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.id = 'modal-editar-zona-id';
        idInput.name = 'id';
        idInput.value = area && area.id ? String(area.id) : '';
        form.appendChild(idInput);

        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';

        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-editar-zona-nombre');
        nombreLabel.textContent = 'Nombre *';

        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-editar-zona-nombre';
        nombreInput.name = 'name';
        nombreInput.required = true;
        nombreInput.value = area && area.name ? String(area.name) : '';
        nombreInput.placeholder = 'Ej: Consultorio 3';

        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        const colorGroup = document.createElement('div');
        colorGroup.className = 'aa-form-group';

        const colorLabel = document.createElement('label');
        colorLabel.setAttribute('for', 'modal-editar-zona-color');
        colorLabel.textContent = 'Color';

        const colorInput = document.createElement('input');
        colorInput.type = 'text';
        colorInput.id = 'modal-editar-zona-color';
        colorInput.name = 'color';
        colorInput.className = 'aa-modal-zona-color-picker';
        colorInput.value = normalizeAreaColor(area && area.color);
        colorInput.setAttribute('data-default-color', DEFAULT_AREA_COLOR);

        colorGroup.appendChild(colorLabel);
        colorGroup.appendChild(colorInput);

        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-editar-zona-status';
        statusMsg.className = 'aa-form-status';
        statusMsg.style.display = 'none';

        form.appendChild(nombreGroup);
        form.appendChild(colorGroup);
        form.appendChild(statusMsg);

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
        saveBtn.id = 'aa-modal-save-editar-zona';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Cambios';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    function saveNewArea() {
        const form = document.getElementById('aa-modal-form-zona');
        if (!form) return;

        const name = document.getElementById('modal-zona-nombre').value.trim();

        if (!name) {
            showFormStatus('El nombre de la zona es obligatorio.', true);
            return;
        }

        const ajaxurl = getAjaxUrl();
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

    function saveEditedArea() {
        const form = document.getElementById('aa-modal-form-editar-zona');
        if (!form) {
            return;
        }

        const areaId = parseInt((document.getElementById('modal-editar-zona-id') || {}).value, 10);
        const name = ((document.getElementById('modal-editar-zona-nombre') || {}).value || '').trim();
        const color = normalizeAreaColor((document.getElementById('modal-editar-zona-color') || {}).value);

        if (!(areaId > 0) || !name) {
            showEditFormStatus('El nombre de la zona es obligatorio.', true);
            return;
        }

        if (!HEX_COLOR_RE.test(color)) {
            showEditFormStatus('El color no es válido.', true);
            return;
        }

        const nonce = window.AA_AREAS_NONCES ? window.AA_AREAS_NONCES.update_service_area : '';
        if (!nonce) {
            showEditFormStatus('Error de seguridad: nonce no disponible.', true);
            return;
        }

        const saveBtn = document.getElementById('aa-modal-save-editar-zona');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        const formData = new FormData();
        formData.append('action', 'aa_update_service_area');
        formData.append('_wpnonce', nonce);
        formData.append('id', String(areaId));
        formData.append('name', name);
        formData.append('color', color);

        fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            if (result.success) {
                showEditFormStatus('Zona de atención actualizada correctamente.', false);

                const areaData = result.data && result.data.area ? result.data.area : {
                    id: areaId,
                    name: name,
                    color: color
                };

                document.dispatchEvent(new CustomEvent('aa:area:saved', {
                    detail: {
                        area: areaData,
                        isEdit: true
                    }
                }));

                setTimeout(function() {
                    destroyEditColorPicker();
                    if (window.AAAdmin && window.AAAdmin.closeModal) {
                        window.AAAdmin.closeModal();
                    }
                }, 1000);
                return;
            }

            const errorMsg = result.data && result.data.message
                ? result.data.message
                : 'Error al actualizar la zona de atención.';
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

    function openCreateAreaModal() {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        cleanupEditModal();

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

    /**
     * @param {number} areaId
     * @returns {Promise<object|null>}
     */
    function fetchServiceAreaById(areaId) {
        const formData = new FormData();
        formData.append('action', 'aa_get_service_areas');

        return fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            const areas = result && result.success && result.data && Array.isArray(result.data.service_areas)
                ? result.data.service_areas
                : [];
            const wanted = String(areaId);

            for (var i = 0; i < areas.length; i++) {
                if (String(areas[i] && areas[i].id) === wanted) {
                    return areas[i];
                }
            }

            return null;
        });
    }

    /**
     * @param {object} area
     */
    function renderEditAreaModal(area) {
        cleanupEditModal();

        const formContent = createEditAreaForm(area);
        const footerContent = createEditModalFooter();

        window.AAAdmin.openModal({
            title: 'Editar zona de atención',
            body: formContent,
            footer: footerContent
        });

        watchEditModalClose();

        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-editar-zona');
            if (saveBtn && saveBtn.parentNode) {
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', saveEditedArea);
            }

            const colorInput = document.getElementById('modal-editar-zona-color');
            initEditColorPicker(colorInput);

            const nombreInput = document.getElementById('modal-editar-zona-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    /**
     * @param {number|string} areaId
     * @returns {Promise<object>}
     */
    function openEditAreaModal(areaId) {
        const id = parseInt(areaId, 10);

        if (!(id > 0)) {
            console.error('[AreaCreateModal] ID de zona inválido:', areaId);
            return Promise.reject(new Error('ID de zona inválido'));
        }

        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('AAAdmin.openModal no está disponible');
            return Promise.reject(new Error('Sistema de modales no disponible'));
        }

        return fetchServiceAreaById(id)
            .then(function(area) {
                if (!area) {
                    console.error('[AreaCreateModal] No se pudo cargar la zona de atención', id);
                    throw new Error('Zona de atención no encontrada');
                }

                renderEditAreaModal(area);
                return area;
            })
            .catch(function(error) {
                if (!(error && error.message === 'Zona de atención no encontrada')) {
                    console.error('[AreaCreateModal] No se pudo cargar la zona de atención', id, error);
                }

                return Promise.reject(
                    error instanceof Error ? error : new Error('No se pudo cargar la zona de atención')
                );
            });
    }

    window.AAAdmin = window.AAAdmin || {};

    window.AAAdmin.AreaCreateModal = {
        openCreate: openCreateAreaModal,
        openEdit: openEditAreaModal
    };

})();
