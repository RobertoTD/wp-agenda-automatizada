/**
 * Expediente Create Modal — alta de expediente padre vía aa_create_expediente.
 *
 * API: AAAdmin.ExpedienteCreateModal.openCreate()
 * Event: aa:expediente:saved — { detail: { expediente } }
 */
(function () {
    'use strict';

    var TITLE_MAX = 200;
    var DESCRIPTION_MAX = 10000;
    var MSG_GENERIC_ERROR = 'No se pudo crear el expediente.';
    var MSG_TITLE_REQUIRED = 'El nombre del expediente es obligatorio.';
    var MSG_TITLE_TOO_LONG = 'El nombre supera el máximo permitido.';
    var MSG_DESCRIPTION_TOO_LONG = 'La descripción supera el máximo permitido.';

    var isSubmitting = false;

    function getConfig() {
        var cfg = window.AA_EXPEDIENTES_DATA || {};
        var actions = cfg.actions && typeof cfg.actions === 'object' ? cfg.actions : {};

        return {
            ajaxUrl: cfg.ajaxUrl || window.ajaxurl || '',
            nonce: cfg.nonce || '',
            createAction: actions.create || 'aa_create_expediente'
        };
    }

    function createForm() {
        var form = document.createElement('form');
        form.id = 'aa-expediente-create-form';
        form.className = 'aa-expediente-create-form space-y-4';
        form.setAttribute('novalidate', 'novalidate');

        var titleLabel = document.createElement('label');
        titleLabel.className = 'block text-base font-medium text-gray-600';
        titleLabel.setAttribute('for', 'aa-expediente-create-title');
        titleLabel.textContent = 'Nombre del expediente';

        var titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.id = 'aa-expediente-create-title';
        titleInput.name = 'title';
        titleInput.className = 'aa-expediente-create-input';
        titleInput.maxLength = TITLE_MAX;
        titleInput.required = true;
        titleInput.autocomplete = 'off';

        var descriptionLabel = document.createElement('label');
        descriptionLabel.className = 'block text-base font-medium text-gray-600';
        descriptionLabel.setAttribute('for', 'aa-expediente-create-description');
        descriptionLabel.textContent = 'Descripción (opcional)';

        var descriptionInput = document.createElement('textarea');
        descriptionInput.id = 'aa-expediente-create-description';
        descriptionInput.name = 'description';
        descriptionInput.className = 'aa-expediente-create-textarea';
        descriptionInput.rows = 4;
        descriptionInput.maxLength = DESCRIPTION_MAX;

        var categoryLabel = document.createElement('span');
        categoryLabel.className = 'block text-base font-medium text-gray-600';
        categoryLabel.textContent = 'Categoría';

        var categoryValue = document.createElement('p');
        categoryValue.className = 'aa-expediente-create-category text-sm text-gray-600';
        categoryValue.textContent = 'General';

        var errorEl = document.createElement('p');
        errorEl.id = 'aa-expediente-create-error';
        errorEl.className = 'aa-expediente-create-error hidden';
        errorEl.setAttribute('role', 'alert');

        form.appendChild(titleLabel);
        form.appendChild(titleInput);
        form.appendChild(descriptionLabel);
        form.appendChild(descriptionInput);
        form.appendChild(categoryLabel);
        form.appendChild(categoryValue);
        form.appendChild(errorEl);

        return form;
    }

    function createFooter() {
        var footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        var saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.id = 'aa-expediente-create-save';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Crear expediente';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    function showError(message) {
        var errorEl = document.getElementById('aa-expediente-create-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message || MSG_GENERIC_ERROR;
        errorEl.classList.remove('hidden');
    }

    function hideError() {
        var errorEl = document.getElementById('aa-expediente-create-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }

    function setSubmitting(active) {
        isSubmitting = active;
        var saveBtn = document.getElementById('aa-expediente-create-save');
        if (saveBtn) {
            saveBtn.disabled = active;
            saveBtn.textContent = active ? 'Creando…' : 'Crear expediente';
        }
    }

    function validateClient(title, description) {
        if (!title) {
            return MSG_TITLE_REQUIRED;
        }
        if (title.length > TITLE_MAX) {
            return MSG_TITLE_TOO_LONG;
        }
        if (description && description.length > DESCRIPTION_MAX) {
            return MSG_DESCRIPTION_TOO_LONG;
        }
        return '';
    }

    function mapServerError(code, message) {
        if (code === 'missing_title') {
            return MSG_TITLE_REQUIRED;
        }
        if (code === 'title_too_long') {
            return MSG_TITLE_TOO_LONG;
        }
        if (code === 'description_too_long') {
            return MSG_DESCRIPTION_TOO_LONG;
        }
        if (typeof message === 'string' && message !== '') {
            return message;
        }
        return MSG_GENERIC_ERROR;
    }

    function submitCreate() {
        if (isSubmitting) {
            return;
        }

        var titleInput = document.getElementById('aa-expediente-create-title');
        var descriptionInput = document.getElementById('aa-expediente-create-description');
        if (!titleInput) {
            return;
        }

        var title = typeof titleInput.value === 'string' ? titleInput.value.trim() : '';
        var description = descriptionInput && typeof descriptionInput.value === 'string'
            ? descriptionInput.value.trim()
            : '';

        hideError();

        var clientError = validateClient(title, description);
        if (clientError) {
            showError(clientError);
            return;
        }

        var cfg = getConfig();
        if (!cfg.ajaxUrl || !cfg.nonce) {
            showError(MSG_GENERIC_ERROR);
            return;
        }

        var formData = new FormData();
        formData.append('action', cfg.createAction);
        formData.append('_wpnonce', cfg.nonce);
        formData.append('title', title);
        if (description !== '') {
            formData.append('description', description);
        }

        setSubmitting(true);

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(function (response) {
                if (!response || response.ok === false) {
                    throw new Error('http');
                }
                return response.json();
            })
            .then(function (result) {
                if (!result || result.success !== true) {
                    var payload = result && result.data ? result.data : {};
                    showError(mapServerError(payload.code, payload.message));
                    setSubmitting(false);
                    return;
                }

                var expediente = result.data && typeof result.data === 'object' ? result.data : {};

                if (window.AAAdmin && typeof window.AAAdmin.closeModal === 'function') {
                    window.AAAdmin.closeModal();
                }

                document.dispatchEvent(new CustomEvent('aa:expediente:saved', {
                    detail: { expediente: expediente }
                }));

                isSubmitting = false;
            })
            .catch(function () {
                showError(MSG_GENERIC_ERROR);
                setSubmitting(false);
            });
    }

    function bindSaveHandler() {
        var saveBtn = document.getElementById('aa-expediente-create-save');
        if (!saveBtn) {
            return;
        }

        var freshBtn = saveBtn.cloneNode(true);
        saveBtn.parentNode.replaceChild(freshBtn, saveBtn);
        freshBtn.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            submitCreate();
        });

        var form = document.getElementById('aa-expediente-create-form');
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCreate();
            });
        }
    }

    function openCreate() {
        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('[ExpedienteCreateModal] AAAdmin.openModal no disponible');
            return;
        }

        isSubmitting = false;

        window.AAAdmin.openModal({
            title: 'Nuevo expediente',
            body: createForm(),
            footer: createFooter()
        });

        setTimeout(function () {
            bindSaveHandler();
            var titleInput = document.getElementById('aa-expediente-create-title');
            if (titleInput && typeof titleInput.focus === 'function') {
                titleInput.focus();
            }
        }, 50);
    }

    window.AAAdmin = window.AAAdmin || {};
    window.AAAdmin.ExpedienteCreateModal = {
        openCreate: openCreate,
        TITLE_MAX: TITLE_MAX,
        DESCRIPTION_MAX: DESCRIPTION_MAX
    };
})();
