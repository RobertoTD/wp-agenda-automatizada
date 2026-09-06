/**
 * Canonical Shell — create/update/delete de contenedor universal (SB1-5B1 / SB1-5B5 / SB1-5B6).
 */
(function () {
    'use strict';

    var cfg = window.AA_CANONICAL_SHELL_CONTAINER_FORM;
    if (!cfg || typeof cfg !== 'object') {
        return;
    }

    var ajaxUrl = typeof cfg.ajaxUrl === 'string' ? cfg.ajaxUrl : '';
    var createAction = typeof cfg.createAction === 'string' ? cfg.createAction : '';
    var createNonce = typeof cfg.createNonce === 'string' ? cfg.createNonce : '';
    var updateAction = typeof cfg.updateAction === 'string' ? cfg.updateAction : '';
    var updateNonce = typeof cfg.updateNonce === 'string' ? cfg.updateNonce : '';
    var deleteAction = typeof cfg.deleteAction === 'string' ? cfg.deleteAction : '';
    var deleteNonce = typeof cfg.deleteNonce === 'string' ? cfg.deleteNonce : '';
    var familyKey = typeof cfg.familyKey === 'string' ? cfg.familyKey : '';
    var maxTitleLength = typeof cfg.maxTitleLength === 'number' ? cfg.maxTitleLength : 200;

    if (!ajaxUrl || !createAction || !createNonce || !updateAction || !updateNonce
        || !deleteAction || !deleteNonce
        || !familyKey) {
        return;
    }

    var openCreateBtn = document.getElementById('aa-shell-open-create-btn');
    var modal = document.getElementById('aa-shell-container-modal');
    var backdrop = document.getElementById('aa-shell-container-modal-backdrop');
    var closeBtn = document.getElementById('aa-shell-container-modal-close-btn');
    var cancelBtn = document.getElementById('aa-shell-container-modal-cancel-btn');
    var form = document.getElementById('aa-shell-container-form');
    var modalTitle = document.getElementById('aa-shell-container-modal-title');
    var titleInput = document.getElementById('aa-shell-container-title');
    var detailsInput = document.getElementById('aa-shell-container-details');
    var titleError = document.getElementById('aa-shell-container-title-error');
    var statusEl = document.getElementById('aa-shell-container-status');
    var submitBtn = document.getElementById('aa-shell-container-submit-btn');

    var deleteModal = document.getElementById('aa-shell-delete-container-modal');
    var deleteBackdrop = document.getElementById('aa-shell-delete-container-modal-backdrop');
    var deleteCloseBtn = document.getElementById('aa-shell-delete-container-modal-close-btn');
    var deleteCancelBtn = document.getElementById('aa-shell-delete-container-modal-cancel-btn');
    var deleteConfirmBtn = document.getElementById('aa-shell-delete-container-confirm-btn');
    var deleteReloadBtn = document.getElementById('aa-shell-delete-container-reload-btn');
    var deleteTitleEl = document.getElementById('aa-shell-delete-container-title');
    var deleteStatusEl = document.getElementById('aa-shell-delete-container-status');

    if (!modal || !form || !titleInput || !submitBtn || !modalTitle) {
        return;
    }

    var MODE_CREATE = 'create';
    var MODE_UPDATE = 'update';
    var mode = MODE_CREATE;
    var currentContainerId = null;
    var inFlight = false;
    var previousFocus = null;

    var deleteContainerId = null;
    var deleteInFlight = false;
    var deleteBlocked = false;
    var deletePreviousFocus = null;
    var deleteRedirectUrl = null;

    function setStatus(message, isError) {
        if (!statusEl) {
            return;
        }
        if (!message) {
            statusEl.textContent = '';
            statusEl.classList.add('hidden');
            statusEl.classList.remove('bg-red-50', 'text-red-700', 'bg-amber-50', 'text-amber-900');
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.remove('hidden');
        if (isError) {
            statusEl.classList.add('bg-red-50', 'text-red-700');
            statusEl.classList.remove('bg-amber-50', 'text-amber-900');
        } else {
            statusEl.classList.add('bg-amber-50', 'text-amber-900');
            statusEl.classList.remove('bg-red-50', 'text-red-700');
        }
    }

    function setTitleError(message) {
        if (!titleError) {
            return;
        }
        if (!message) {
            titleError.textContent = '';
            titleError.classList.add('hidden');
            titleInput.removeAttribute('aria-invalid');
            titleInput.removeAttribute('aria-describedby');
            return;
        }
        titleError.textContent = message;
        titleError.classList.remove('hidden');
        titleInput.setAttribute('aria-invalid', 'true');
        titleInput.setAttribute('aria-describedby', 'aa-shell-container-title-error');
    }

    function setBusy(busy) {
        inFlight = busy;
        submitBtn.disabled = busy;
        titleInput.disabled = busy;
        if (detailsInput) {
            detailsInput.disabled = busy;
        }
        if (cancelBtn) {
            cancelBtn.disabled = busy;
        }
        if (closeBtn) {
            closeBtn.disabled = busy;
        }
        if (busy) {
            modal.setAttribute('aria-busy', 'true');
            submitBtn.setAttribute('aria-busy', 'true');
        } else {
            modal.removeAttribute('aria-busy');
            submitBtn.removeAttribute('aria-busy');
        }
    }

    function applyModeChrome() {
        if (mode === MODE_UPDATE) {
            modalTitle.textContent = 'Editar lista';
            submitBtn.textContent = 'Guardar cambios';
        } else {
            modalTitle.textContent = 'Nueva lista';
            submitBtn.textContent = 'Crear lista';
        }
    }

    function openModal(nextMode, containerId, titleValue, detailsValue, triggerEl) {
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            return;
        }
        mode = nextMode === MODE_UPDATE ? MODE_UPDATE : MODE_CREATE;
        currentContainerId = (mode === MODE_UPDATE && containerId >= 1) ? containerId : null;
        previousFocus = triggerEl || document.activeElement;
        setStatus('', false);
        setTitleError('');
        applyModeChrome();
        titleInput.value = typeof titleValue === 'string' ? titleValue : '';
        if (detailsInput) {
            detailsInput.value = typeof detailsValue === 'string' ? detailsValue : '';
        }
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        titleInput.focus();
    }

    function closeModal(restoreFocus) {
        if (inFlight) {
            return;
        }
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        setStatus('', false);
        setTitleError('');
        mode = MODE_CREATE;
        currentContainerId = null;
        applyModeChrome();
        if (restoreFocus && previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        } else if (restoreFocus && openCreateBtn) {
            openCreateBtn.focus();
        }
    }

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function parseContainerPayload(raw) {
        if (typeof raw !== 'string' || raw === '') {
            return null;
        }
        var data = parseJsonSafe(raw);
        if (!data || typeof data !== 'object') {
            return null;
        }
        var id = typeof data.id === 'number' ? data.id : parseInt(data.id, 10);
        if (!(id >= 1)) {
            return null;
        }
        return {
            id: id,
            title: typeof data.title === 'string' ? data.title : '',
            details: typeof data.details === 'string' ? data.details : ''
        };
    }

    function clientValidate() {
        var raw = titleInput.value || '';
        var trimmed = raw.replace(/^\s+|\s+$/g, '');
        if (trimmed === '') {
            setTitleError('El nombre de la lista no puede estar vacío.');
            titleInput.focus();
            return false;
        }
        if (trimmed.length > maxTitleLength) {
            setTitleError('El nombre de la lista no puede exceder los ' + maxTitleLength + ' caracteres.');
            titleInput.focus();
            return false;
        }
        return true;
    }

    function defaultErrorMessage() {
        return mode === MODE_UPDATE
            ? 'No se pudo actualizar la lista. Inténtalo de nuevo.'
            : 'No se pudo crear la lista. Inténtalo de nuevo.';
    }

    function uncertainMessage(message) {
        if (typeof message === 'string' && message !== '') {
            return message;
        }
        return mode === MODE_UPDATE
            ? 'No fue posible confirmar si los cambios se guardaron. Revisa el listado antes de intentarlo nuevamente.'
            : 'No fue posible confirmar si la lista se creó. Revisa el listado antes de intentarlo nuevamente.';
    }

    function submitForm() {
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        setStatus('', false);
        setTitleError('');
        if (!clientValidate()) {
            return;
        }
        if (mode === MODE_UPDATE && !(currentContainerId >= 1)) {
            setStatus(defaultErrorMessage(), true);
            return;
        }

        var action = mode === MODE_UPDATE ? updateAction : createAction;
        var nonce = mode === MODE_UPDATE ? updateNonce : createNonce;

        setBusy(true);

        var body = new FormData();
        body.append('action', action);
        body.append('nonce', nonce);
        body.append('family_key', familyKey);
        if (mode === MODE_UPDATE) {
            body.append('container_id', String(currentContainerId));
        }
        body.append('title', titleInput.value);
        body.append('details', detailsInput ? detailsInput.value : '');

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.text().then(function (text) {
                return { httpStatus: response.status, payload: parseJsonSafe(text) };
            });
        }).then(function (result) {
            var payload = result.payload;
            if (!payload || typeof payload !== 'object') {
                setBusy(false);
                setStatus(defaultErrorMessage(), true);
                return;
            }

            if (payload.success === true && payload.data && payload.data.status === 'confirmed') {
                var redirect = payload.data.redirect_url;
                if (typeof redirect === 'string' && redirect !== '') {
                    window.location.assign(redirect);
                    return;
                }
                setBusy(false);
                setStatus(
                    mode === MODE_UPDATE
                        ? 'Los cambios se guardaron, pero no se pudo redirigir. Recarga el listado.'
                        : 'La lista se creó, pero no se pudo redirigir. Recarga el listado.',
                    true
                );
                return;
            }

            setBusy(false);
            var err = payload.data || {};
            var code = typeof err.code === 'string' ? err.code : '';
            var message = typeof err.message === 'string' ? err.message : '';

            if (code === 'uncertain') {
                setStatus(uncertainMessage(message), false);
                return;
            }
            if (code === 'invalid_title' || code === 'title_too_long') {
                setTitleError(message || 'Revisa el nombre de la lista.');
                titleInput.focus();
                return;
            }
            setStatus(message || defaultErrorMessage(), true);
        }).catch(function () {
            setBusy(false);
            setStatus(defaultErrorMessage(), true);
        });
    }

    function setDeleteStatus(message, isError) {
        if (!deleteStatusEl) {
            return;
        }
        if (!message) {
            deleteStatusEl.textContent = '';
            deleteStatusEl.classList.add('hidden');
            deleteStatusEl.classList.remove('bg-red-50', 'text-red-700', 'bg-amber-50', 'text-amber-900');
            return;
        }
        deleteStatusEl.textContent = message;
        deleteStatusEl.classList.remove('hidden');
        if (isError) {
            deleteStatusEl.classList.add('bg-red-50', 'text-red-700');
            deleteStatusEl.classList.remove('bg-amber-50', 'text-amber-900');
        } else {
            deleteStatusEl.classList.add('bg-amber-50', 'text-amber-900');
            deleteStatusEl.classList.remove('bg-red-50', 'text-red-700');
        }
    }

    function setDeleteBusy(busy) {
        deleteInFlight = busy;
        if (deleteConfirmBtn) {
            deleteConfirmBtn.disabled = busy || deleteBlocked;
            if (busy) {
                deleteConfirmBtn.setAttribute('aria-busy', 'true');
            } else {
                deleteConfirmBtn.removeAttribute('aria-busy');
            }
        }
        if (deleteCancelBtn) {
            deleteCancelBtn.disabled = busy;
        }
        if (deleteCloseBtn) {
            deleteCloseBtn.disabled = busy;
        }
        if (deleteModal) {
            if (busy) {
                deleteModal.setAttribute('aria-busy', 'true');
            } else {
                deleteModal.removeAttribute('aria-busy');
            }
        }
    }

    function showDeleteReload(show) {
        if (!deleteReloadBtn) {
            return;
        }
        if (show) {
            deleteReloadBtn.classList.remove('hidden');
        } else {
            deleteReloadBtn.classList.add('hidden');
        }
    }

    function openDeleteModal(containerId, titleValue, triggerEl) {
        if (!deleteModal || !deleteConfirmBtn || !deleteTitleEl) {
            return;
        }
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        if (!modal.classList.contains('hidden')) {
            return;
        }
        if (!(containerId >= 1)) {
            return;
        }

        deleteContainerId = containerId;
        deletePreviousFocus = triggerEl || document.activeElement;
        deleteRedirectUrl = null;
        deleteBlocked = false;
        setDeleteStatus('', false);
        showDeleteReload(false);
        deleteTitleEl.textContent = typeof titleValue === 'string' ? titleValue : '';
        if (deleteConfirmBtn) {
            deleteConfirmBtn.disabled = false;
        }
        deleteModal.classList.remove('hidden');
        deleteModal.setAttribute('aria-hidden', 'false');
        if (deleteCancelBtn) {
            deleteCancelBtn.focus();
        }
    }

    function closeDeleteModal(restoreFocus) {
        if (!deleteModal) {
            return;
        }
        if (deleteInFlight) {
            return;
        }
        if (deleteBlocked) {
            return;
        }
        deleteModal.classList.add('hidden');
        deleteModal.setAttribute('aria-hidden', 'true');
        setDeleteStatus('', false);
        showDeleteReload(false);
        deleteContainerId = null;
        deleteRedirectUrl = null;
        if (deleteTitleEl) {
            deleteTitleEl.textContent = '';
        }
        if (restoreFocus && deletePreviousFocus && typeof deletePreviousFocus.focus === 'function') {
            deletePreviousFocus.focus();
        }
    }

    function submitDelete() {
        if (!deleteModal || deleteInFlight || deleteBlocked) {
            return;
        }
        if (!(deleteContainerId >= 1)) {
            setDeleteStatus('No se pudo eliminar la lista. Inténtalo de nuevo.', true);
            return;
        }

        setDeleteStatus('', false);
        showDeleteReload(false);
        setDeleteBusy(true);

        var body = new FormData();
        body.append('action', deleteAction);
        body.append('nonce', deleteNonce);
        body.append('family_key', familyKey);
        body.append('container_id', String(deleteContainerId));

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.text().then(function (text) {
                return { httpStatus: response.status, payload: parseJsonSafe(text) };
            });
        }).then(function (result) {
            var payload = result.payload;
            if (!payload || typeof payload !== 'object') {
                setDeleteBusy(false);
                setDeleteStatus('No se pudo eliminar la lista. Inténtalo de nuevo.', true);
                return;
            }

            if (payload.success === true && payload.data && payload.data.status === 'confirmed') {
                var redirect = payload.data.redirect_url;
                if (typeof redirect === 'string' && redirect !== '') {
                    window.location.assign(redirect);
                    return;
                }
                setDeleteBusy(false);
                setDeleteStatus('La lista se eliminó, pero no se pudo redirigir. Recarga el listado.', true);
                return;
            }

            var err = payload.data || {};
            var code = typeof err.code === 'string' ? err.code : '';
            var message = typeof err.message === 'string' ? err.message : '';
            var errRedirect = typeof err.redirect_url === 'string' ? err.redirect_url : '';

            if (code === 'uncertain') {
                deleteBlocked = true;
                deleteRedirectUrl = errRedirect !== '' ? errRedirect : null;
                setDeleteBusy(false);
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = true;
                }
                setDeleteStatus(
                    message || 'No fue posible confirmar si la lista se eliminó. Recarga el listado para verificarlo antes de intentarlo nuevamente.',
                    false
                );
                showDeleteReload(true);
                return;
            }

            setDeleteBusy(false);
            setDeleteStatus(message || 'No se pudo eliminar la lista. Inténtalo de nuevo.', true);
        }).catch(function () {
            setDeleteBusy(false);
            setDeleteStatus('No se pudo eliminar la lista. Inténtalo de nuevo.', true);
        });
    }

    function reloadAfterUncertain() {
        if (typeof deleteRedirectUrl === 'string' && deleteRedirectUrl !== '') {
            window.location.assign(deleteRedirectUrl);
            return;
        }
        window.location.reload();
    }

    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', function () {
            openModal(MODE_CREATE, null, '', '', openCreateBtn);
        });
    }

    var editButtons = document.querySelectorAll('.aa-shell-edit-container-btn');
    for (var i = 0; i < editButtons.length; i++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                var container = parseContainerPayload(btn.getAttribute('data-aa-container'));
                if (!container) {
                    return;
                }
                openModal(MODE_UPDATE, container.id, container.title, container.details, btn);
            });
        })(editButtons[i]);
    }

    var deleteButtons = document.querySelectorAll('.aa-shell-delete-container-btn');
    for (var d = 0; d < deleteButtons.length; d++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                var container = parseContainerPayload(btn.getAttribute('data-aa-container'));
                if (!container) {
                    return;
                }
                openDeleteModal(container.id, container.title, btn);
            });
        })(deleteButtons[d]);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeModal(true);
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            closeModal(true);
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            closeModal(true);
        });
    }

    if (deleteCloseBtn) {
        deleteCloseBtn.addEventListener('click', function () {
            closeDeleteModal(true);
        });
    }
    if (deleteCancelBtn) {
        deleteCancelBtn.addEventListener('click', function () {
            closeDeleteModal(true);
        });
    }
    if (deleteBackdrop) {
        deleteBackdrop.addEventListener('click', function () {
            closeDeleteModal(true);
        });
    }
    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', function () {
            submitDelete();
        });
    }
    if (deleteReloadBtn) {
        deleteReloadBtn.addEventListener('click', function () {
            reloadAfterUncertain();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            if (!deleteBlocked) {
                closeDeleteModal(true);
            }
            return;
        }
        if (modal && !modal.classList.contains('hidden')) {
            closeModal(true);
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitForm();
    });
})();
