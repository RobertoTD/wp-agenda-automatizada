/**
 * Canonical Shell — create/update/delete de registro universal (SB1-5B2…SB1-5B4).
 */
(function () {
    'use strict';

    var cfg = window.AA_CANONICAL_SHELL_RECORD_FORM;
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
    var containerId = typeof cfg.containerId === 'number' ? cfg.containerId : parseInt(cfg.containerId, 10);
    var listsScope = typeof cfg.listsScope === 'string' ? cfg.listsScope : '';
    var page = (typeof cfg.page === 'number' && cfg.page > 1) ? cfg.page : null;
    var containersPage = (typeof cfg.containersPage === 'number' && cfg.containersPage > 1)
        ? cfg.containersPage
        : null;
    var maxTitleLength = typeof cfg.maxTitleLength === 'number' ? cfg.maxTitleLength : 200;
    var capabilityContributions = (cfg.capabilityContributions && typeof cfg.capabilityContributions === 'object')
        ? cfg.capabilityContributions
        : {};
    var capabilityModules = (window.AA_CANONICAL_SHELL_CAPABILITY_MODULES
        && typeof window.AA_CANONICAL_SHELL_CAPABILITY_MODULES === 'object')
        ? window.AA_CANONICAL_SHELL_CAPABILITY_MODULES
        : {};

    if (!ajaxUrl || !createAction || !createNonce || !updateAction || !updateNonce
        || !deleteAction || !deleteNonce
        || !familyKey || !(containerId >= 1)) {
        return;
    }

    function offeredCapabilityKeys() {
        var keys = [];
        for (var key in capabilityContributions) {
            if (
                Object.prototype.hasOwnProperty.call(capabilityContributions, key)
                && capabilityContributions[key]
                && capabilityContributions[key].offered === true
                && capabilityModules[key]
            ) {
                keys.push(key);
            }
        }
        return keys;
    }

    function clearCapabilityModules() {
        var keys = offeredCapabilityKeys();
        for (var i = 0; i < keys.length; i++) {
            var mod = capabilityModules[keys[i]];
            if (mod && typeof mod.clear === 'function') {
                mod.clear();
            }
        }
    }

    /**
     * @param {object|null} recordCapabilities mapa por clave desde data-aa-record
     * @param {boolean} isCreate
     */
    function applyCapabilityModules(recordCapabilities, isCreate) {
        clearCapabilityModules();
        var keys = offeredCapabilityKeys();
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var mod = capabilityModules[key];
            if (!mod || typeof mod.apply !== 'function') {
                continue;
            }
            if (isCreate) {
                mod.apply({ status: 'known_absent' });
                continue;
            }
            var state = null;
            if (
                recordCapabilities
                && typeof recordCapabilities === 'object'
                && recordCapabilities[key]
                && typeof recordCapabilities[key] === 'object'
            ) {
                state = recordCapabilities[key];
            }
            mod.apply(state);
        }
    }

    function clearCapabilityErrors() {
        var keys = offeredCapabilityKeys();
        for (var i = 0; i < keys.length; i++) {
            var mod = capabilityModules[keys[i]];
            if (mod && typeof mod.clearError === 'function') {
                mod.clearError();
            }
        }
    }

    function collectCapabilityModules(body) {
        var keys = offeredCapabilityKeys();
        for (var i = 0; i < keys.length; i++) {
            var mod = capabilityModules[keys[i]];
            if (mod && typeof mod.collect === 'function') {
                mod.collect(body);
            }
        }
    }

    function handleCapabilityError(code, message) {
        var keys = offeredCapabilityKeys();
        for (var i = 0; i < keys.length; i++) {
            var mod = capabilityModules[keys[i]];
            if (mod && typeof mod.handleError === 'function' && mod.handleError(code, message)) {
                return true;
            }
        }
        return false;
    }

    var openCreateBtn = document.getElementById('aa-shell-open-create-record-btn');
    var modal = document.getElementById('aa-shell-record-modal');
    var backdrop = document.getElementById('aa-shell-record-modal-backdrop');
    var closeBtn = document.getElementById('aa-shell-record-modal-close-btn');
    var cancelBtn = document.getElementById('aa-shell-record-modal-cancel-btn');
    var form = document.getElementById('aa-shell-record-form');
    var modalTitle = document.getElementById('aa-shell-record-modal-title');
    var titleInput = document.getElementById('aa-shell-record-title');
    var detailsInput = document.getElementById('aa-shell-record-details');
    var titleError = document.getElementById('aa-shell-record-title-error');
    var statusEl = document.getElementById('aa-shell-record-status');
    var submitBtn = document.getElementById('aa-shell-record-submit-btn');

    var deleteModal = document.getElementById('aa-shell-delete-record-modal');
    var deleteBackdrop = document.getElementById('aa-shell-delete-record-modal-backdrop');
    var deleteCloseBtn = document.getElementById('aa-shell-delete-record-modal-close-btn');
    var deleteCancelBtn = document.getElementById('aa-shell-delete-record-modal-cancel-btn');
    var deleteConfirmBtn = document.getElementById('aa-shell-delete-record-confirm-btn');
    var deleteReloadBtn = document.getElementById('aa-shell-delete-record-reload-btn');
    var deleteTitleEl = document.getElementById('aa-shell-delete-record-title');
    var deleteStatusEl = document.getElementById('aa-shell-delete-record-status');

    if (!modal || !form || !titleInput || !submitBtn || !modalTitle) {
        return;
    }

    var MODE_CREATE = 'create';
    var MODE_UPDATE = 'update';
    var mode = MODE_CREATE;
    var currentRecordId = null;
    var inFlight = false;
    var previousFocus = null;

    var deleteRecordId = null;
    var deleteInFlight = false;
    var deleteBlocked = false;
    var deletePreviousFocus = null;
    var deleteRedirectUrl = null;

    function appendReturnContext(body) {
        if (listsScope === 'all') {
            body.append('lists_scope', 'all');
        }
        if (page !== null) {
            body.append('page', String(page));
        }
        if (containersPage !== null) {
            body.append('containers_page', String(containersPage));
        }
    }

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
        titleInput.setAttribute('aria-describedby', 'aa-shell-record-title-error');
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
            modalTitle.textContent = 'Editar registro';
            submitBtn.textContent = 'Guardar cambios';
        } else {
            modalTitle.textContent = 'Nuevo registro';
            submitBtn.textContent = 'Crear registro';
        }
    }

    function openModal(nextMode, recordId, titleValue, detailsValue, triggerEl, recordCapabilities) {
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            return;
        }
        mode = nextMode === MODE_UPDATE ? MODE_UPDATE : MODE_CREATE;
        currentRecordId = (mode === MODE_UPDATE && recordId >= 1) ? recordId : null;
        previousFocus = triggerEl || document.activeElement;
        setStatus('', false);
        setTitleError('');
        applyModeChrome();
        clearCapabilityModules();
        titleInput.value = typeof titleValue === 'string' ? titleValue : '';
        if (detailsInput) {
            detailsInput.value = typeof detailsValue === 'string' ? detailsValue : '';
        }
        applyCapabilityModules(
            recordCapabilities && typeof recordCapabilities === 'object' ? recordCapabilities : null,
            mode === MODE_CREATE
        );
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
        clearCapabilityModules();
        mode = MODE_CREATE;
        currentRecordId = null;
        applyModeChrome();
        if (restoreFocus) {
            restoreFocusToVisible(previousFocus);
        }
        previousFocus = null;
    }

    function isElementFocusable(el) {
        if (!el || typeof el.focus !== 'function') {
            return false;
        }
        if (el.disabled) {
            return false;
        }
        if (typeof el.getClientRects === 'function') {
            if (el.getClientRects().length === 0) {
                return false;
            }
        }
        if (typeof el.closest === 'function') {
            var hiddenAncestor = el.closest('[hidden], .hidden');
            if (hiddenAncestor) {
                return false;
            }
        }
        return true;
    }

    function restoreFocusToVisible(preferred) {
        if (isElementFocusable(preferred)) {
            preferred.focus();
            return;
        }
        if (preferred && typeof preferred.closest === 'function') {
            var record = preferred.closest('[data-aa-shell-record]');
            var toggle = record ? record.querySelector('.aa-shell-record-toggle') : null;
            if (isElementFocusable(toggle)) {
                toggle.focus();
                return;
            }
            var optionsTrigger = record ? record.querySelector('.aa-shell-record-options-trigger') : null;
            if (isElementFocusable(optionsTrigger)) {
                optionsTrigger.focus();
                return;
            }
        }
        if (isElementFocusable(openCreateBtn)) {
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

    function parseRecordPayload(raw) {
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
            details: typeof data.details === 'string' ? data.details : '',
            capabilities: (data.capabilities && typeof data.capabilities === 'object')
                ? data.capabilities
                : null
        };
    }

    function clientValidate() {
        var raw = titleInput.value || '';
        var trimmed = raw.replace(/^\s+|\s+$/g, '');
        if (trimmed === '') {
            setTitleError('El título del registro no puede estar vacío.');
            titleInput.focus();
            return false;
        }
        if (trimmed.length > maxTitleLength) {
            setTitleError('El título del registro no puede exceder los ' + maxTitleLength + ' caracteres.');
            titleInput.focus();
            return false;
        }
        return true;
    }

    function defaultErrorMessage() {
        return mode === MODE_UPDATE
            ? 'No se pudo actualizar el registro. Inténtalo de nuevo.'
            : 'No se pudo crear el registro. Inténtalo de nuevo.';
    }

    function uncertainMessage(message) {
        if (typeof message === 'string' && message !== '') {
            return message;
        }
        return mode === MODE_UPDATE
            ? 'No fue posible confirmar si los cambios se guardaron. Revisa el registro antes de intentarlo nuevamente.'
            : 'No fue posible confirmar si el registro se creó. Revisa la lista antes de intentarlo nuevamente.';
    }

    function submitForm() {
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        setStatus('', false);
        setTitleError('');
        clearCapabilityErrors();
        if (!clientValidate()) {
            return;
        }
        if (mode === MODE_UPDATE && !(currentRecordId >= 1)) {
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
        body.append('container_id', String(containerId));
        if (mode === MODE_UPDATE) {
            body.append('record_id', String(currentRecordId));
        }
        body.append('title', titleInput.value);
        body.append('details', detailsInput ? detailsInput.value : '');
        collectCapabilityModules(body);
        appendReturnContext(body);

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
                        ? 'Los cambios se guardaron, pero no se pudo redirigir. Recarga la lista.'
                        : 'El registro se creó, pero no se pudo redirigir. Recarga la lista.',
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
                setTitleError(message || 'Revisa el título del registro.');
                titleInput.focus();
                return;
            }
            if (handleCapabilityError(code, message)) {
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

    function openDeleteModal(recordId, titleValue, triggerEl) {
        if (!deleteModal || !deleteConfirmBtn || !deleteTitleEl) {
            return;
        }
        if (inFlight || deleteInFlight || deleteBlocked) {
            return;
        }
        if (!modal.classList.contains('hidden')) {
            return;
        }
        if (!(recordId >= 1)) {
            return;
        }

        deleteRecordId = recordId;
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
        deleteRecordId = null;
        deleteRedirectUrl = null;
        if (deleteTitleEl) {
            deleteTitleEl.textContent = '';
        }
        if (restoreFocus) {
            restoreFocusToVisible(deletePreviousFocus);
        }
        deletePreviousFocus = null;
    }

    function submitDelete() {
        if (!deleteModal || deleteInFlight || deleteBlocked) {
            return;
        }
        if (!(deleteRecordId >= 1)) {
            setDeleteStatus('No se pudo eliminar el registro. Inténtalo de nuevo.', true);
            return;
        }

        setDeleteStatus('', false);
        showDeleteReload(false);
        setDeleteBusy(true);

        var body = new FormData();
        body.append('action', deleteAction);
        body.append('nonce', deleteNonce);
        body.append('family_key', familyKey);
        body.append('container_id', String(containerId));
        body.append('record_id', String(deleteRecordId));
        appendReturnContext(body);

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
                setDeleteStatus('No se pudo eliminar el registro. Inténtalo de nuevo.', true);
                return;
            }

            if (payload.success === true && payload.data && payload.data.status === 'confirmed') {
                var redirect = payload.data.redirect_url;
                if (typeof redirect === 'string' && redirect !== '') {
                    window.location.assign(redirect);
                    return;
                }
                setDeleteBusy(false);
                setDeleteStatus('El registro se eliminó, pero no se pudo redirigir. Recarga la lista.', true);
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
                    message || 'No fue posible confirmar si el registro se eliminó. Recarga la lista para verificarlo antes de intentarlo nuevamente.',
                    false
                );
                showDeleteReload(true);
                return;
            }

            setDeleteBusy(false);
            setDeleteStatus(message || 'No se pudo eliminar el registro. Inténtalo de nuevo.', true);
        }).catch(function () {
            setDeleteBusy(false);
            setDeleteStatus('No se pudo eliminar el registro. Inténtalo de nuevo.', true);
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
            openModal(MODE_CREATE, null, '', '', openCreateBtn, null);
        });
    }

    var editButtons = document.querySelectorAll('.aa-shell-edit-record-btn');
    for (var i = 0; i < editButtons.length; i++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                var record = parseRecordPayload(btn.getAttribute('data-aa-record'));
                if (!record) {
                    return;
                }
                openModal(MODE_UPDATE, record.id, record.title, record.details, btn, record.capabilities);
            });
        })(editButtons[i]);
    }

    var deleteButtons = document.querySelectorAll('.aa-shell-delete-record-btn');
    for (var d = 0; d < deleteButtons.length; d++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                var record = parseRecordPayload(btn.getAttribute('data-aa-record'));
                if (!record) {
                    return;
                }
                openDeleteModal(record.id, record.title, btn);
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
        if (e.defaultPrevented) {
            return;
        }
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            if (!deleteBlocked) {
                closeDeleteModal(true);
            }
            if (typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            return;
        }
        if (modal && !modal.classList.contains('hidden')) {
            closeModal(true);
            if (typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitForm();
    });
})();
