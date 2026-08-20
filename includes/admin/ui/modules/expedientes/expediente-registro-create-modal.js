/**
 * Expediente Registro Create Modal — alta de hijo vía
 * aa_create_expediente_registro_for_expediente (detalle canónico).
 *
 * API: AAAdmin.ExpedienteRegistroCreateModal.openCreate()
 * Config: window.AA_EXPEDIENTE_DETAIL_DATA
 */
(function () {
    'use strict';

    var TITLE_MAX = 200;
    var BODY_MAX = 10000;
    var MSG_GENERIC_ERROR = 'No se pudo guardar el registro.';
    var MSG_TITLE_REQUIRED = 'El título es obligatorio.';
    var MSG_BODY_REQUIRED = 'El texto es obligatorio.';
    var MSG_TITLE_TOO_LONG = 'El título es demasiado largo.';
    var MSG_BODY_TOO_LONG = 'El texto es demasiado largo.';
    var MSG_FORBIDDEN = 'Sesión expirada o acceso no permitido. Recarga la página.';
    var MSG_NOT_FOUND = 'Este expediente ya no está disponible.';

    var isSubmitting = false;
    var bound = false;

    function getConfig() {
        var cfg = window.AA_EXPEDIENTE_DETAIL_DATA || {};

        return {
            ajaxUrl: typeof cfg.ajaxUrl === 'string' ? cfg.ajaxUrl : '',
            nonce: typeof cfg.nonce === 'string' ? cfg.nonce : '',
            action: typeof cfg.action === 'string' ? cfg.action : '',
            expedienteId: cfg.expedienteId,
            successUrl: typeof cfg.successUrl === 'string' ? cfg.successUrl : ''
        };
    }

    function configIsValid(cfg) {
        if (!cfg.ajaxUrl || !cfg.nonce || !cfg.action || !cfg.successUrl) {
            return false;
        }

        if (typeof cfg.expedienteId === 'number') {
            return cfg.expedienteId > 0;
        }

        if (typeof cfg.expedienteId === 'string') {
            return /^[1-9][0-9]{0,18}$/.test(cfg.expedienteId);
        }

        return false;
    }

    function createForm() {
        var form = document.createElement('form');
        form.id = 'aa-expediente-detail-registro-create-form';
        form.className = 'aa-expediente-registro-form space-y-3';
        form.setAttribute('novalidate', 'novalidate');

        var titleLabel = document.createElement('label');
        titleLabel.className = 'block text-base font-medium text-gray-600';
        titleLabel.setAttribute('for', 'aa-expediente-detail-registro-create-title');
        titleLabel.textContent = 'Título';

        var titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.id = 'aa-expediente-detail-registro-create-title';
        titleInput.name = 'title';
        titleInput.className = 'aa-expediente-registro-input';
        titleInput.maxLength = TITLE_MAX;
        titleInput.required = true;
        titleInput.autocomplete = 'off';

        var bodyLabel = document.createElement('label');
        bodyLabel.className = 'block text-base font-medium text-gray-600';
        bodyLabel.setAttribute('for', 'aa-expediente-detail-registro-create-body');
        bodyLabel.textContent = 'Detalles';

        var bodyInput = document.createElement('textarea');
        bodyInput.id = 'aa-expediente-detail-registro-create-body';
        bodyInput.name = 'body';
        bodyInput.className = 'aa-expediente-registro-textarea';
        bodyInput.rows = 6;
        bodyInput.maxLength = BODY_MAX;
        bodyInput.required = true;

        var errorEl = document.createElement('p');
        errorEl.id = 'aa-expediente-detail-registro-create-error';
        errorEl.className = 'aa-expediente-detail-registro-create-error hidden text-sm text-red-600';
        errorEl.setAttribute('role', 'alert');

        form.appendChild(titleLabel);
        form.appendChild(titleInput);
        form.appendChild(bodyLabel);
        form.appendChild(bodyInput);
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
        saveBtn.id = 'aa-expediente-detail-registro-create-save';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    function showError(message) {
        var errorEl = document.getElementById('aa-expediente-detail-registro-create-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message || MSG_GENERIC_ERROR;
        errorEl.classList.remove('hidden');
    }

    function hideError() {
        var errorEl = document.getElementById('aa-expediente-detail-registro-create-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }

    function setSubmitting(active) {
        isSubmitting = active;
        var saveBtn = document.getElementById('aa-expediente-detail-registro-create-save');
        if (saveBtn) {
            saveBtn.disabled = active;
            saveBtn.textContent = active ? 'Guardando…' : 'Guardar';
        }
    }

    function validateClient(title, body) {
        if (!title) {
            return { message: MSG_TITLE_REQUIRED, field: 'title' };
        }
        if (title.length > TITLE_MAX) {
            return { message: MSG_TITLE_TOO_LONG, field: 'title' };
        }
        if (!body) {
            return { message: MSG_BODY_REQUIRED, field: 'body' };
        }
        if (body.length > BODY_MAX) {
            return { message: MSG_BODY_TOO_LONG, field: 'body' };
        }
        return null;
    }

    function focusField(field) {
        var id = field === 'body'
            ? 'aa-expediente-detail-registro-create-body'
            : 'aa-expediente-detail-registro-create-title';
        var el = document.getElementById(id);
        if (el && typeof el.focus === 'function') {
            el.focus();
        }
    }

    function mapServerError(status, code, message) {
        if (status === 403 || code === 'bad_nonce' || code === 'expediente_access_denied') {
            return MSG_FORBIDDEN;
        }
        if (status === 404 || code === 'not_found') {
            return MSG_NOT_FOUND;
        }
        if (code === 'missing_title') {
            return MSG_TITLE_REQUIRED;
        }
        if (code === 'title_too_long') {
            return MSG_TITLE_TOO_LONG;
        }
        if (code === 'missing_body') {
            return MSG_BODY_REQUIRED;
        }
        if (code === 'body_too_long') {
            return MSG_BODY_TOO_LONG;
        }
        if (code === 'invalid_id') {
            return typeof message === 'string' && message !== '' ? message : 'Expediente no válido.';
        }
        if (status === 400 && typeof message === 'string' && message !== '') {
            return message;
        }
        if (status >= 500 || code === 'lookup_failed' || code === 'persistence_failed') {
            return MSG_GENERIC_ERROR;
        }
        if (typeof message === 'string' && message !== '') {
            return message;
        }
        return MSG_GENERIC_ERROR;
    }

    function fieldForCode(code) {
        if (code === 'missing_title' || code === 'title_too_long') {
            return 'title';
        }
        if (code === 'missing_body' || code === 'body_too_long') {
            return 'body';
        }
        return 'title';
    }

    function submitCreate() {
        if (isSubmitting) {
            return;
        }

        var titleInput = document.getElementById('aa-expediente-detail-registro-create-title');
        var bodyInput = document.getElementById('aa-expediente-detail-registro-create-body');
        if (!titleInput || !bodyInput) {
            return;
        }

        var title = typeof titleInput.value === 'string' ? titleInput.value.trim() : '';
        var body = typeof bodyInput.value === 'string' ? bodyInput.value.trim() : '';

        hideError();

        var clientError = validateClient(title, body);
        if (clientError) {
            showError(clientError.message);
            focusField(clientError.field);
            return;
        }

        var cfg = getConfig();
        if (!configIsValid(cfg)) {
            showError(MSG_GENERIC_ERROR);
            return;
        }

        var formData = new FormData();
        formData.append('action', cfg.action);
        formData.append('_wpnonce', cfg.nonce);
        formData.append('expediente_id', String(cfg.expedienteId));
        formData.append('title', title);
        formData.append('body', body);

        setSubmitting(true);

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response) {
                    throw new Error('network');
                }
                return response.json().then(function (result) {
                    return {
                        status: response.status,
                        ok: response.ok,
                        result: result
                    };
                }).catch(function () {
                    throw new Error('invalid_json');
                });
            })
            .then(function (payload) {
                var result = payload.result;
                if (result && result.success === true && result.data && result.data.record) {
                    if (window.AAAdmin && typeof window.AAAdmin.closeModal === 'function') {
                        window.AAAdmin.closeModal();
                    }
                    isSubmitting = false;
                    window.location.assign(cfg.successUrl);
                    return;
                }

                var data = result && result.data ? result.data : {};
                var code = typeof data.code === 'string' ? data.code : '';
                var message = typeof data.message === 'string' ? data.message : '';
                showError(mapServerError(payload.status, code, message));
                focusField(fieldForCode(code));
                setSubmitting(false);
            })
            .catch(function () {
                showError(MSG_GENERIC_ERROR);
                setSubmitting(false);
            });
    }

    function bindSaveHandler() {
        var saveBtn = document.getElementById('aa-expediente-detail-registro-create-save');
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

        var form = document.getElementById('aa-expediente-detail-registro-create-form');
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCreate();
            });
        }
    }

    function openCreate() {
        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('[ExpedienteRegistroCreateModal] AAAdmin.openModal no disponible');
            return;
        }

        if (!configIsValid(getConfig())) {
            console.error('[ExpedienteRegistroCreateModal] configuración inválida');
            return;
        }

        isSubmitting = false;

        window.AAAdmin.openModal({
            title: 'Nuevo registro',
            body: createForm(),
            footer: createFooter()
        });

        setTimeout(function () {
            bindSaveHandler();
            var titleInput = document.getElementById('aa-expediente-detail-registro-create-title');
            if (titleInput && typeof titleInput.focus === 'function') {
                titleInput.focus();
            }
        }, 50);
    }

    function bindFab() {
        var fab = document.getElementById('aa-expediente-detail-new-registro');
        if (!fab || bound) {
            return;
        }
        bound = true;
        fab.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            openCreate();
        });
    }

    function init() {
        if (!configIsValid(getConfig())) {
            return;
        }
        if (!document.getElementById('aa-expediente-detail-new-registro')) {
            return;
        }
        bindFab();
    }

    window.AAAdmin = window.AAAdmin || {};
    window.AAAdmin.ExpedienteRegistroCreateModal = {
        openCreate: openCreate,
        TITLE_MAX: TITLE_MAX,
        BODY_MAX: BODY_MAX
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
