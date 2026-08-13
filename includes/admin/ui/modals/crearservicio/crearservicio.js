/**
 * Service Create/Edit Modal - Independent Modal Component
 *
 * Creates a service with name only, via aa_create_service.
 * Edits an existing service via aa_update_service.
 *
 * API:
 * - AAAdmin.ServiceCreateModal.openCreate() - Open modal to create new service
 * - AAAdmin.ServiceCreateModal.openEdit(serviceId) - Open modal to edit existing service
 *
 * Events:
 * - 'aa:service:saved' - Emitted when a service is successfully saved (create or edit)
 *   Event detail: { service: {...}, isEdit: boolean }
 * - 'aa:onboarding:setup-mutated' - Create flow only
 */

(function() {
    'use strict';

    var SERVICE_NAME_MAX_LENGTH = 191;
    var SERVICE_CODE_MAX_LENGTH = 191;
    var ALLOWED_DURATIONS = ['30', '60', '90'];
    var ALLOWED_CHANNELS = ['whatsapp', 'google_meet', 'custom_link'];
    var PRICE_RE = /^(\d{1,8})(?:\.(\d{1,2}))?$/;
    var editState = null;
    var editOpenSeq = 0;
    var editCloseObserver = null;
    var saveCloseTimer = null;

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

    function getAjaxUrl() {
        return (window.AA_ASSIGNMENTS_DATA && window.AA_ASSIGNMENTS_DATA.ajaxurl)
            || window.ajaxurl
            || '/wp-admin/admin-ajax.php';
    }

    function postForm(formData) {
        return fetch(getAjaxUrl(), {
            method: 'POST',
            body: formData
        }).then(function(response) {
            return response.json();
        });
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

    function nullableString(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value);
    }

    function durationToFormValue(raw) {
        if (raw === null || raw === undefined || raw === '') {
            return '';
        }
        return String(raw);
    }

    function attendanceToFormValue(raw) {
        if (raw === 'physical' || raw === 'virtual') {
            return raw;
        }
        return '';
    }

    function channelToFormValue(raw) {
        if (!raw) {
            return '';
        }
        return String(raw);
    }

    function validatePriceString(value) {
        var trimmed = String(value || '').trim();
        if (trimmed === '') {
            return { ok: true, value: '' };
        }
        if (trimmed.charAt(0) === '-') {
            return { ok: false, message: 'El precio no puede ser negativo.' };
        }
        if (!PRICE_RE.test(trimmed)) {
            return { ok: false, message: 'El precio debe tener como máximo 8 enteros y 2 decimales.' };
        }
        return { ok: true, value: trimmed };
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

    function showEditFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-editar-servicio-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    function appendFormGroup(form, labelText, field, hintText) {
        const group = document.createElement('div');
        group.className = 'aa-form-group';

        const label = document.createElement('label');
        label.setAttribute('for', field.id);
        label.textContent = labelText;

        group.appendChild(label);
        group.appendChild(field);

        if (hintText) {
            const hint = document.createElement('p');
            hint.className = 'text-xs text-gray-500 mt-1';
            hint.textContent = hintText;
            group.appendChild(hint);
        }

        form.appendChild(group);
        return group;
    }

    function fillSelect(select, options, selectedValue) {
        select.innerHTML = '';
        options.forEach(function(optionData) {
            var option = document.createElement('option');
            option.value = optionData.value;
            option.textContent = optionData.label;
            if (optionData.value === selectedValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    function durationSelectOptions(currentValue) {
        var options = [
            { value: '', label: 'Usar configuración general' },
            { value: '30', label: '30 min' },
            { value: '60', label: '60 min' },
            { value: '90', label: '90 min' }
        ];

        if (currentValue && ALLOWED_DURATIONS.indexOf(currentValue) === -1) {
            options.push({
                value: currentValue,
                label: currentValue + ' min (histórico)'
            });
        }

        return options;
    }

    function channelSelectOptions(currentValue) {
        var options = [
            { value: '', label: 'Sin definir' },
            { value: 'whatsapp', label: 'WhatsApp' },
            { value: 'google_meet', label: 'Google Meet' },
            { value: 'custom_link', label: 'Enlace personalizado' }
        ];

        if (currentValue && ALLOWED_CHANNELS.indexOf(currentValue) === -1) {
            options.push({
                value: currentValue,
                label: currentValue + ' (histórico)'
            });
        }

        return options;
    }

    function channelHintText(channelValue) {
        return channelValue === 'custom_link'
            ? 'El enlace se definirá al crear la reservación.'
            : 'El enlace se generará automáticamente al agendar.';
    }

    function syncVirtualChannelVisibility() {
        var typeSelect = document.getElementById('modal-editar-servicio-tipo');
        var channelGroup = document.getElementById('modal-editar-servicio-canal-group');
        if (!typeSelect || !channelGroup) {
            return;
        }

        if (typeSelect.value === 'virtual') {
            channelGroup.classList.remove('hidden');
        } else {
            channelGroup.classList.add('hidden');
        }
    }

    /**
     * @param {object} service
     * @returns {HTMLElement}
     */
    function createEditServiceForm(service) {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-editar-servicio';
        form.className = 'aa-modal-form';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.id = 'modal-editar-servicio-id';
        idInput.name = 'id';
        idInput.value = service && service.id ? String(service.id) : '';
        form.appendChild(idInput);

        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-editar-servicio-nombre';
        nombreInput.name = 'name';
        nombreInput.required = true;
        nombreInput.maxLength = SERVICE_NAME_MAX_LENGTH;
        nombreInput.value = service && service.name ? String(service.name) : '';
        nombreInput.placeholder = 'Ej: Consulta médica';
        appendFormGroup(form, 'Nombre *', nombreInput);

        const codeInput = document.createElement('input');
        codeInput.type = 'text';
        codeInput.id = 'modal-editar-servicio-codigo';
        codeInput.name = 'code';
        codeInput.maxLength = SERVICE_CODE_MAX_LENGTH;
        codeInput.value = nullableString(service && service.code);
        appendFormGroup(form, 'Código', codeInput);

        const priceInput = document.createElement('input');
        priceInput.type = 'text';
        priceInput.id = 'modal-editar-servicio-precio';
        priceInput.name = 'price';
        priceInput.inputMode = 'decimal';
        priceInput.autocomplete = 'off';
        priceInput.placeholder = 'Ej: 0.00';
        priceInput.value = nullableString(service && service.price);
        appendFormGroup(form, 'Precio', priceInput);

        const publicGroup = document.createElement('div');
        publicGroup.className = 'aa-form-group';
        const publicLabel = document.createElement('label');
        publicLabel.className = 'flex items-center gap-2 cursor-pointer';
        const publicInput = document.createElement('input');
        publicInput.type = 'checkbox';
        publicInput.id = 'modal-editar-servicio-publico';
        publicInput.name = 'public_calendar';
        publicInput.checked = parseInt(service && service.public_calendar, 10) === 1;
        const publicText = document.createElement('span');
        publicText.textContent = 'Mostrar en calendario público';
        publicLabel.appendChild(publicInput);
        publicLabel.appendChild(publicText);
        publicGroup.appendChild(publicLabel);
        form.appendChild(publicGroup);

        const indicacionesInput = document.createElement('textarea');
        indicacionesInput.id = 'modal-editar-servicio-indicaciones';
        indicacionesInput.name = 'indicaciones_cita';
        indicacionesInput.rows = 3;
        indicacionesInput.value = nullableString(service && service.indicaciones_cita);
        appendFormGroup(
            form,
            'Indicaciones para la cita',
            indicacionesInput,
            'Estas indicaciones se mostrarán en los correos de confirmación al cliente.'
        );

        const durationValue = durationToFormValue(service && service.duration_minutes);
        const durationSelect = document.createElement('select');
        durationSelect.id = 'modal-editar-servicio-duracion';
        durationSelect.name = 'duration_minutes';
        durationSelect.className = 'aa-form-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg';
        fillSelect(durationSelect, durationSelectOptions(durationValue), durationValue);
        appendFormGroup(form, 'Duración', durationSelect);

        const attendanceValue = attendanceToFormValue(service && service.attendance_type);
        const typeSelect = document.createElement('select');
        typeSelect.id = 'modal-editar-servicio-tipo';
        typeSelect.name = 'attendance_type';
        typeSelect.className = 'aa-form-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg';
        fillSelect(typeSelect, [
            { value: '', label: 'Sin definir' },
            { value: 'physical', label: 'Físico' },
            { value: 'virtual', label: 'Virtual' }
        ], attendanceValue);
        appendFormGroup(
            form,
            'Tipo',
            typeSelect,
            'No marca el servicio como videollamada hasta que elijas Virtual.'
        );

        const channelValue = channelToFormValue(service && service.virtual_channel);
        const channelSelect = document.createElement('select');
        channelSelect.id = 'modal-editar-servicio-canal';
        channelSelect.name = 'virtual_channel';
        channelSelect.className = 'aa-form-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg';
        fillSelect(channelSelect, channelSelectOptions(channelValue), channelValue);

        const channelGroup = appendFormGroup(
            form,
            'Canal',
            channelSelect,
            channelHintText(channelValue)
        );
        channelGroup.id = 'modal-editar-servicio-canal-group';
        if (attendanceValue !== 'virtual') {
            channelGroup.classList.add('hidden');
        }

        const channelHint = channelGroup.querySelector('p');
        if (channelHint) {
            channelHint.id = 'modal-editar-servicio-canal-hint';
        }

        typeSelect.addEventListener('change', function() {
            syncVirtualChannelVisibility();
        });

        channelSelect.addEventListener('change', function() {
            var hint = document.getElementById('modal-editar-servicio-canal-hint');
            if (hint) {
                hint.textContent = channelHintText(channelSelect.value);
            }
        });

        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-editar-servicio-status';
        statusMsg.className = 'aa-form-status';
        statusMsg.style.display = 'none';
        form.appendChild(statusMsg);

        editState = {
            serviceId: parseInt(service && service.id, 10) || 0
        };

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
        saveBtn.id = 'aa-modal-save-editar-servicio';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Cambios';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    function saveNewService() {
        const form = document.getElementById('aa-modal-form-servicio');
        if (!form) return;

        const name = document.getElementById('modal-servicio-nombre').value.trim();

        if (!name) {
            showFormStatus('El nombre del servicio es obligatorio.', true);
            return;
        }

        const formData = new FormData();
        formData.append('action', 'aa_create_service');
        formData.append('name', name);

        const saveBtn = document.getElementById('aa-modal-save-servicio');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        postForm(formData)
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

    function readEditDraft() {
        const nameInput = document.getElementById('modal-editar-servicio-nombre');
        const codeInput = document.getElementById('modal-editar-servicio-codigo');
        const priceInput = document.getElementById('modal-editar-servicio-precio');
        const publicInput = document.getElementById('modal-editar-servicio-publico');
        const indicacionesInput = document.getElementById('modal-editar-servicio-indicaciones');
        const durationSelect = document.getElementById('modal-editar-servicio-duracion');
        const typeSelect = document.getElementById('modal-editar-servicio-tipo');
        const channelSelect = document.getElementById('modal-editar-servicio-canal');

        const name = nameInput ? nameInput.value.trim() : '';
        const code = codeInput ? codeInput.value.trim() : '';
        const priceCheck = validatePriceString(priceInput ? priceInput.value : '');
        const attendanceType = typeSelect ? typeSelect.value : '';
        const virtualChannel = attendanceType === 'virtual' && channelSelect
            ? channelSelect.value
            : '';

        return {
            name: name,
            code: code,
            priceCheck: priceCheck,
            public_calendar: publicInput && publicInput.checked ? '1' : '0',
            indicaciones_cita: indicacionesInput ? indicacionesInput.value : '',
            duration_minutes: durationSelect ? durationSelect.value : '',
            attendance_type: attendanceType,
            virtual_channel: virtualChannel
        };
    }

    function saveEditedService() {
        if (!editState || !(editState.serviceId > 0)) {
            return;
        }

        const draft = readEditDraft();

        if (!draft.name) {
            showEditFormStatus('El nombre del servicio es obligatorio.', true);
            return;
        }

        if (draft.name.length > SERVICE_NAME_MAX_LENGTH) {
            showEditFormStatus('El nombre no puede superar ' + SERVICE_NAME_MAX_LENGTH + ' caracteres.', true);
            return;
        }

        if (draft.code.length > SERVICE_CODE_MAX_LENGTH) {
            showEditFormStatus('El código no puede superar ' + SERVICE_CODE_MAX_LENGTH + ' caracteres.', true);
            return;
        }

        if (!draft.priceCheck.ok) {
            showEditFormStatus(draft.priceCheck.message, true);
            return;
        }

        const nonce = window.AA_SERVICE_NONCES ? window.AA_SERVICE_NONCES.update_service : '';
        if (!nonce) {
            showEditFormStatus('Error de seguridad: nonce no disponible.', true);
            return;
        }

        const saveBtn = document.getElementById('aa-modal-save-editar-servicio');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        const formData = new FormData();
        formData.append('action', 'aa_update_service');
        formData.append('_wpnonce', nonce);
        formData.append('id', String(editState.serviceId));
        formData.append('name', draft.name);
        formData.append('code', draft.code);
        formData.append('price', draft.priceCheck.value);
        formData.append('public_calendar', draft.public_calendar);
        formData.append('indicaciones_cita', draft.indicaciones_cita);
        formData.append('duration_minutes', draft.duration_minutes);
        formData.append('attendance_type', draft.attendance_type);
        formData.append('virtual_channel', draft.virtual_channel);

        postForm(formData)
        .then(function(result) {
            if (result.success) {
                showEditFormStatus('Servicio actualizado correctamente.', false);

                const serviceData = result.data && result.data.service ? result.data.service : {
                    id: editState.serviceId,
                    name: draft.name
                };

                document.dispatchEvent(new CustomEvent('aa:service:saved', {
                    detail: {
                        service: serviceData,
                        isEdit: true
                    }
                }));

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
                : 'Error al actualizar el servicio.';
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

    function openCreateServiceModal() {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        cleanupEditModal();
        editOpenSeq += 1;

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

    function fetchServiceById(serviceId) {
        const formData = new FormData();
        formData.append('action', 'aa_get_services_db');

        return postForm(formData).then(function(result) {
            if (!(result && result.success && result.data && Array.isArray(result.data.services))) {
                throw new Error('No se pudieron cargar los servicios');
            }

            const wanted = String(serviceId);
            const list = result.data.services;

            for (var i = 0; i < list.length; i++) {
                if (String(list[i] && list[i].id) === wanted) {
                    return list[i];
                }
            }

            return null;
        });
    }

    function renderEditServiceModal(service, seq) {
        disconnectEditCloseObserver();
        resetEditState();

        const formContent = createEditServiceForm(service);
        const footerContent = createEditModalFooter();

        window.AAAdmin.openModal({
            title: 'Editar servicio',
            body: formContent,
            footer: footerContent
        });

        syncVirtualChannelVisibility();
        watchEditModalClose();

        setTimeout(function() {
            if (seq !== editOpenSeq) {
                return;
            }

            const saveBtn = document.getElementById('aa-modal-save-editar-servicio');
            if (saveBtn && saveBtn.parentNode) {
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', saveEditedService);
            }

            const nombreInput = document.getElementById('modal-editar-servicio-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    /**
     * @param {number|string} serviceId
     * @returns {Promise<object>}
     */
    function openEditServiceModal(serviceId) {
        const id = parseInt(serviceId, 10);

        if (!(id > 0)) {
            console.error('[ServiceCreateModal] ID de servicio inválido:', serviceId);
            return Promise.reject(new Error('ID de servicio inválido'));
        }

        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('AAAdmin.openModal no está disponible');
            return Promise.reject(new Error('Sistema de modales no disponible'));
        }

        var seq = ++editOpenSeq;

        return fetchServiceById(id)
            .then(function(service) {
                if (seq !== editOpenSeq) {
                    return service;
                }

                if (!service) {
                    console.error('[ServiceCreateModal] No se pudo cargar el servicio', id);
                    throw new Error('Servicio no encontrado');
                }

                renderEditServiceModal(service, seq);
                return service;
            })
            .catch(function(error) {
                if (!(error && error.message === 'Servicio no encontrado')) {
                    console.error('[ServiceCreateModal] No se pudo cargar el servicio', id, error);
                }

                return Promise.reject(
                    error instanceof Error ? error : new Error('No se pudo cargar el servicio')
                );
            });
    }

    window.AAAdmin = window.AAAdmin || {};

    window.AAAdmin.ServiceCreateModal = {
        openCreate: openCreateServiceModal,
        openEdit: openEditServiceModal
    };

})();
