/**
 * Expediente Detail Actions — menú del contenedor (editar título + delete).
 *
 * API: AAAdmin.ExpedienteDetailActions.mount() / .destroy()
 * Config: window.AA_EXPEDIENTE_DETAIL_DATA.containerActions (+ expedienteId/ajaxUrl)
 */
(function () {
    'use strict';

    var TITLE_MAX = 200;
    var MSG_BUSY = 'El expediente está siendo modificado. Inténtalo nuevamente.';
    var MSG_INCONSISTENT = 'No fue posible eliminar el expediente porque sus datos requieren revisión.';
    var MSG_STORAGE = 'No fue posible eliminar todas las imágenes. Inténtalo nuevamente.';
    var MSG_DELETE_FALLBACK = 'No se pudo eliminar el expediente.';
    var MSG_UPDATE_FALLBACK = 'No se pudo actualizar el expediente.';
    var MSG_TITLE_REQUIRED = 'El título es obligatorio.';
    var MSG_TITLE_TOO_LONG = 'El título supera el máximo permitido.';
    var MODAL_DELETE_TITLE = 'Eliminar expediente';
    var MODAL_DELETE_BODY = 'Se eliminarán permanentemente este expediente, todos sus registros y sus imágenes. Esta acción no se puede deshacer.';
    var MODAL_EDIT_TITLE = 'Editar expediente';

    var state = {
        mounted: false,
        generation: 0,
        menuOpen: false,
        activeModal: null,
        inFlight: false,
        navigationScheduled: false,
        currentTitle: '',
        focusReturn: null,
        wrappedClose: null,
        originalClose: null,
        onDocClick: null,
        onDocKeydown: null,
        onTriggerClick: null,
        onEditClick: null,
        onDeleteClick: null
    };

    function getRoot() {
        return document.getElementById('aa-expediente-detail-root');
    }

    function getHeader() {
        return document.getElementById('aa-expediente-detail-header');
    }

    function getTrigger() {
        return document.getElementById('aa-expediente-detail-tools-trigger');
    }

    function getMenu() {
        return document.getElementById('aa-expediente-detail-tools-menu');
    }

    function getEditItem() {
        return document.getElementById('aa-expediente-detail-tools-edit');
    }

    function getDeleteItem() {
        return document.getElementById('aa-expediente-detail-tools-delete');
    }

    function getTitleEl() {
        return document.getElementById('aa-expediente-detail-title');
    }

    function getConfig() {
        var cfg = window.AA_EXPEDIENTE_DETAIL_DATA || {};
        var container = cfg.containerActions && typeof cfg.containerActions === 'object'
            ? cfg.containerActions
            : {};
        var caps = container.capabilities && typeof container.capabilities === 'object'
            ? container.capabilities
            : {};

        return {
            ajaxUrl: typeof cfg.ajaxUrl === 'string' ? cfg.ajaxUrl : '',
            expedienteId: cfg.expedienteId,
            updateTitleAction: typeof container.updateTitleAction === 'string' ? container.updateTitleAction : '',
            deleteAction: typeof container.deleteAction === 'string' ? container.deleteAction : '',
            nonce: typeof container.nonce === 'string' ? container.nonce : '',
            listUrl: typeof container.listUrl === 'string' ? container.listUrl : '',
            title: typeof container.title === 'string' ? container.title : '',
            canUpdateTitle: caps.updateTitle === true,
            canDelete: caps.delete === true
        };
    }

    function normalizeExpedienteId(value) {
        if (typeof value === 'number' && value > 0 && Math.floor(value) === value) {
            return String(value);
        }
        if (typeof value === 'string' && /^[1-9][0-9]{0,18}$/.test(value)) {
            return value;
        }
        return null;
    }

    function titleLength(value) {
        if (typeof value !== 'string') {
            return 0;
        }
        return typeof value.length === 'number' ? Array.from(value).length : value.length;
    }

    function isValidListUrl(listUrl) {
        if (!listUrl || typeof listUrl !== 'string') {
            return false;
        }

        var parsed;
        try {
            parsed = new URL(listUrl, window.location.href);
        } catch (e) {
            return false;
        }

        if (parsed.origin !== window.location.origin) {
            return false;
        }
        if (parsed.searchParams.get('action') !== 'aa_iframe_content') {
            return false;
        }
        if (parsed.searchParams.get('module') !== 'expedientes') {
            return false;
        }
        if (parsed.searchParams.has('expediente_id') || parsed.searchParams.has('client_id')) {
            return false;
        }

        return true;
    }

    function configIsValid(cfg) {
        if (!cfg.ajaxUrl || !cfg.nonce) {
            return false;
        }
        if (normalizeExpedienteId(cfg.expedienteId) === null) {
            return false;
        }
        if (!cfg.canUpdateTitle && !cfg.canDelete) {
            return false;
        }
        if (cfg.canUpdateTitle) {
            if (!cfg.updateTitleAction || typeof cfg.title !== 'string') {
                return false;
            }
        }
        if (cfg.canDelete) {
            if (!cfg.deleteAction || !isValidListUrl(cfg.listUrl)) {
                return false;
            }
        }
        return true;
    }

    function modalAvailable() {
        return !!(window.AAAdmin && window.AAAdmin.modal
            && typeof window.AAAdmin.modal.open === 'function'
            && typeof window.AAAdmin.modal.close === 'function'
            && document.getElementById('aa-modal-root'));
    }

    function setMenuOpen(open) {
        var trigger = getTrigger();
        var menu = getMenu();
        if (!trigger || !menu) {
            return;
        }

        state.menuOpen = !!open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            menu.classList.remove('hidden');
            menu.removeAttribute('hidden');
        } else {
            menu.classList.add('hidden');
            menu.setAttribute('hidden', 'hidden');
        }
    }

    function closeMenu() {
        if (!state.menuOpen) {
            return;
        }
        setMenuOpen(false);
    }

    function messageForDeleteError(status, code) {
        var normalized = typeof code === 'string' ? code : '';
        if (status === 409 && (normalized === 'resource_busy' || normalized === 'concurrent_change')) {
            return MSG_BUSY;
        }
        if (status === 409 && normalized === 'aggregate_inconsistent') {
            return MSG_INCONSISTENT;
        }
        if (status === 502 || normalized === 'delete_failed'
            || normalized === 'storage_delete_failed'
            || normalized === 'expediente_attachments_unreachable'
            || normalized === 'expediente_attachments_invalid_response') {
            return MSG_STORAGE;
        }
        return MSG_DELETE_FALLBACK;
    }

    function messageForUpdateError(code) {
        if (code === 'missing_title') {
            return MSG_TITLE_REQUIRED;
        }
        if (code === 'title_too_long') {
            return MSG_TITLE_TOO_LONG;
        }
        return MSG_UPDATE_FALLBACK;
    }

    function unwrapModalClose() {
        if (state.wrappedClose && state.originalClose && window.AAAdmin && window.AAAdmin.modal) {
            window.AAAdmin.modal.close = state.originalClose;
        }
        state.wrappedClose = null;
        state.originalClose = null;
    }

    function onModalClosed() {
        state.activeModal = null;
        state.inFlight = false;
        unwrapModalClose();
        var trigger = state.focusReturn || getTrigger();
        state.focusReturn = null;
        if (trigger && typeof trigger.focus === 'function') {
            try {
                trigger.focus();
            } catch (e) { /* ignore */ }
        }
    }

    function wrapModalClose() {
        if (!window.AAAdmin || !window.AAAdmin.modal || state.wrappedClose) {
            return;
        }
        state.originalClose = window.AAAdmin.modal.close;
        state.wrappedClose = function () {
            var wasOpen = state.activeModal !== null;
            state.originalClose.call(window.AAAdmin.modal);
            if (wasOpen) {
                onModalClosed();
            }
        };
        window.AAAdmin.modal.close = state.wrappedClose;
    }

    function createDeleteFooter(onConfirm) {
        var footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.id = 'aa-expediente-detail-delete-cancel';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        var confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.id = 'aa-expediente-detail-delete-confirm';
        confirmBtn.className = 'aa-btn-guardar aa-expediente-detail-delete-confirm';
        confirmBtn.textContent = 'Eliminar expediente';
        confirmBtn.addEventListener('click', function () {
            if (typeof onConfirm === 'function') {
                onConfirm(confirmBtn);
            }
        });

        footer.appendChild(cancelBtn);
        footer.appendChild(confirmBtn);
        return footer;
    }

    function createDeleteBody(errorText) {
        var wrap = document.createElement('div');
        wrap.className = 'aa-expediente-detail-delete-body space-y-3';

        var p = document.createElement('p');
        p.className = 'text-base text-gray-600';
        p.textContent = MODAL_DELETE_BODY;
        wrap.appendChild(p);

        var err = document.createElement('p');
        err.id = 'aa-expediente-detail-delete-error';
        err.className = 'aa-expediente-detail-delete-error text-sm text-red-600';
        if (!errorText) {
            err.classList.add('hidden');
        }
        err.setAttribute('role', 'alert');
        if (errorText) {
            err.textContent = errorText;
        }
        wrap.appendChild(err);
        return wrap;
    }

    function createEditBody(initialTitle) {
        var wrap = document.createElement('div');
        wrap.className = 'aa-expediente-detail-edit-body space-y-3';

        var group = document.createElement('div');
        group.className = 'aa-form-group';

        var label = document.createElement('label');
        label.setAttribute('for', 'aa-expediente-detail-edit-title');
        label.textContent = 'Título';

        var input = document.createElement('input');
        input.type = 'text';
        input.id = 'aa-expediente-detail-edit-title';
        input.name = 'title';
        input.required = true;
        input.maxLength = TITLE_MAX;
        input.autocomplete = 'off';
        input.className = 'aa-form-input-lg w-full';
        input.value = typeof initialTitle === 'string' ? initialTitle : '';

        group.appendChild(label);
        group.appendChild(input);
        wrap.appendChild(group);

        var err = document.createElement('p');
        err.id = 'aa-expediente-detail-edit-error';
        err.className = 'aa-expediente-detail-edit-error text-sm text-red-600 hidden';
        err.setAttribute('role', 'alert');
        wrap.appendChild(err);

        return wrap;
    }

    function createEditFooter(onSave) {
        var footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.id = 'aa-expediente-detail-edit-cancel';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        var saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.id = 'aa-expediente-detail-edit-save';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar';
        saveBtn.addEventListener('click', function () {
            if (typeof onSave === 'function') {
                onSave(saveBtn);
            }
        });

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);
        return footer;
    }

    function setBusy(btn, busy) {
        if (!btn) {
            return;
        }
        btn.disabled = !!busy;
        if (busy) {
            btn.setAttribute('aria-busy', 'true');
        } else {
            btn.removeAttribute('aria-busy');
        }
    }

    function showModalError(id, message) {
        var err = document.getElementById(id);
        if (!err) {
            return;
        }
        err.textContent = message;
        err.classList.remove('hidden');
    }

    function openDeleteModal(cfg) {
        if (!modalAvailable() || state.inFlight || state.navigationScheduled || state.activeModal) {
            return;
        }
        if (window.AAAdmin.modal.isOpen && window.AAAdmin.modal.isOpen()) {
            return;
        }

        closeMenu();
        state.focusReturn = getTrigger();
        state.activeModal = 'delete';

        wrapModalClose();
        window.AAAdmin.modal.open({
            title: MODAL_DELETE_TITLE,
            body: createDeleteBody(''),
            footer: createDeleteFooter(function (confirmBtn) {
                submitDelete(cfg, confirmBtn);
            })
        });

        window.setTimeout(function () {
            var cancel = document.getElementById('aa-expediente-detail-delete-cancel');
            if (cancel && typeof cancel.focus === 'function') {
                cancel.focus();
            }
        }, 50);
    }

    function openEditModal(cfg) {
        if (!modalAvailable() || state.inFlight || state.navigationScheduled || state.activeModal) {
            return;
        }
        if (window.AAAdmin.modal.isOpen && window.AAAdmin.modal.isOpen()) {
            return;
        }

        closeMenu();
        state.focusReturn = getTrigger();
        state.activeModal = 'edit';

        wrapModalClose();
        window.AAAdmin.modal.open({
            title: MODAL_EDIT_TITLE,
            body: createEditBody(state.currentTitle),
            footer: createEditFooter(function (saveBtn) {
                submitUpdate(cfg, saveBtn);
            })
        });

        window.setTimeout(function () {
            var input = document.getElementById('aa-expediente-detail-edit-title');
            if (input && typeof input.focus === 'function') {
                input.focus();
                if (typeof input.select === 'function') {
                    input.select();
                }
            }
        }, 50);
    }

    function isDeleteSuccess(result, expectedId) {
        if (!result || result.success !== true || !result.data || typeof result.data !== 'object') {
            return false;
        }
        if (result.data.deleted !== true) {
            return false;
        }
        var returned = Number(result.data.expediente_id);
        var expected = Number(expectedId);
        return Number.isFinite(returned) && Number.isFinite(expected) && returned === expected;
    }

    function isUpdateSuccess(result, expectedId) {
        if (!result || result.success !== true || !result.data || typeof result.data !== 'object') {
            return false;
        }
        var exp = result.data.expediente;
        if (!exp || typeof exp !== 'object') {
            return false;
        }
        if (typeof exp.title !== 'string') {
            return false;
        }
        var returned = Number(exp.id);
        var expected = Number(expectedId);
        return Number.isFinite(returned) && Number.isFinite(expected) && returned === expected;
    }

    function applyTitleToDom(title) {
        var el = getTitleEl();
        if (!el) {
            return;
        }
        el.textContent = title !== '' ? title : 'Sin título';
    }

    function syncConfigTitle(title) {
        state.currentTitle = title;
        if (window.AA_EXPEDIENTE_DETAIL_DATA
            && window.AA_EXPEDIENTE_DETAIL_DATA.containerActions
            && typeof window.AA_EXPEDIENTE_DETAIL_DATA.containerActions === 'object') {
            window.AA_EXPEDIENTE_DETAIL_DATA.containerActions.title = title;
        }
    }

    function submitDelete(cfg, confirmBtn) {
        if (state.inFlight || state.navigationScheduled || !state.mounted || state.activeModal !== 'delete') {
            return;
        }

        var generation = state.generation;
        var expedienteId = normalizeExpedienteId(cfg.expedienteId);
        if (expedienteId === null) {
            return;
        }

        state.inFlight = true;
        setBusy(confirmBtn, true);

        var body = new URLSearchParams();
        body.set('action', cfg.deleteAction);
        body.set('_wpnonce', cfg.nonce);
        body.set('expediente_id', expedienteId);

        window.fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return response.text().then(function (text) {
                var json = null;
                try {
                    json = text ? JSON.parse(text) : null;
                } catch (e) {
                    json = null;
                }
                return { status: response.status, ok: response.ok, json: json };
            });
        }).then(function (payload) {
            if (!state.mounted || generation !== state.generation) {
                return;
            }

            if (payload.ok && isDeleteSuccess(payload.json, expedienteId)) {
                if (state.navigationScheduled) {
                    return;
                }
                state.navigationScheduled = true;
                state.inFlight = false;
                state.activeModal = null;
                if (window.AAAdmin && window.AAAdmin.modal) {
                    unwrapModalClose();
                    window.AAAdmin.modal.close();
                }
                window.location.replace(cfg.listUrl);
                return;
            }

            state.inFlight = false;
            setBusy(confirmBtn, false);
            var code = '';
            if (payload.json && payload.json.data && typeof payload.json.data.code === 'string') {
                code = payload.json.data.code;
            }
            showModalError('aa-expediente-detail-delete-error', messageForDeleteError(payload.status, code));
        }).catch(function () {
            if (!state.mounted || generation !== state.generation) {
                return;
            }
            state.inFlight = false;
            setBusy(confirmBtn, false);
            showModalError('aa-expediente-detail-delete-error', MSG_DELETE_FALLBACK);
        });
    }

    function submitUpdate(cfg, saveBtn) {
        if (state.inFlight || !state.mounted || state.activeModal !== 'edit') {
            return;
        }

        var input = document.getElementById('aa-expediente-detail-edit-title');
        if (!input) {
            return;
        }

        var title = typeof input.value === 'string' ? input.value.trim() : '';
        var errEl = document.getElementById('aa-expediente-detail-edit-error');
        if (errEl) {
            errEl.textContent = '';
            errEl.classList.add('hidden');
        }

        if (title === '') {
            showModalError('aa-expediente-detail-edit-error', MSG_TITLE_REQUIRED);
            return;
        }
        if (titleLength(title) > TITLE_MAX) {
            showModalError('aa-expediente-detail-edit-error', MSG_TITLE_TOO_LONG);
            return;
        }

        var generation = state.generation;
        var expedienteId = normalizeExpedienteId(cfg.expedienteId);
        if (expedienteId === null) {
            return;
        }

        state.inFlight = true;
        setBusy(saveBtn, true);

        var body = new URLSearchParams();
        body.set('action', cfg.updateTitleAction);
        body.set('_wpnonce', cfg.nonce);
        body.set('expediente_id', expedienteId);
        body.set('title', title);

        window.fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return response.text().then(function (text) {
                var json = null;
                try {
                    json = text ? JSON.parse(text) : null;
                } catch (e) {
                    json = null;
                }
                return { status: response.status, ok: response.ok, json: json };
            });
        }).then(function (payload) {
            if (!state.mounted || generation !== state.generation) {
                return;
            }

            if (payload.ok && isUpdateSuccess(payload.json, expedienteId)) {
                var nextTitle = payload.json.data.expediente.title;
                applyTitleToDom(nextTitle);
                syncConfigTitle(nextTitle);
                state.inFlight = false;
                if (window.AAAdmin && window.AAAdmin.modal) {
                    window.AAAdmin.modal.close();
                }
                return;
            }

            state.inFlight = false;
            setBusy(saveBtn, false);
            var code = '';
            if (payload.json && payload.json.data && typeof payload.json.data.code === 'string') {
                code = payload.json.data.code;
            }
            showModalError('aa-expediente-detail-edit-error', messageForUpdateError(code));
        }).catch(function () {
            if (!state.mounted || generation !== state.generation) {
                return;
            }
            state.inFlight = false;
            setBusy(saveBtn, false);
            showModalError('aa-expediente-detail-edit-error', MSG_UPDATE_FALLBACK);
        });
    }

    function onDocumentClick(event) {
        if (!state.mounted || !state.menuOpen) {
            return;
        }
        var tools = document.getElementById('aa-expediente-detail-tools');
        if (!tools) {
            return;
        }
        if (tools.contains(event.target)) {
            return;
        }
        closeMenu();
    }

    function onDocumentKeydown(event) {
        if (!state.mounted) {
            return;
        }
        if (event.key !== 'Escape' && event.key !== 'Esc') {
            return;
        }
        if (state.activeModal) {
            return;
        }
        if (state.menuOpen) {
            closeMenu();
            var trigger = getTrigger();
            if (trigger && typeof trigger.focus === 'function') {
                trigger.focus();
            }
        }
    }

    function bindUi(cfg) {
        var trigger = getTrigger();
        if (!trigger || !getMenu()) {
            return false;
        }

        var editItem = getEditItem();
        var deleteItem = getDeleteItem();
        if (cfg.canUpdateTitle && !editItem) {
            return false;
        }
        if (cfg.canDelete && !deleteItem) {
            return false;
        }
        if (!editItem && !deleteItem) {
            return false;
        }

        state.onTriggerClick = function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (state.activeModal || state.inFlight) {
                return;
            }
            setMenuOpen(!state.menuOpen);
        };
        trigger.addEventListener('click', state.onTriggerClick);

        if (editItem) {
            state.onEditClick = function (event) {
                event.preventDefault();
                event.stopPropagation();
                openEditModal(cfg);
            };
            editItem.addEventListener('click', state.onEditClick);
        }

        if (deleteItem) {
            state.onDeleteClick = function (event) {
                event.preventDefault();
                event.stopPropagation();
                openDeleteModal(cfg);
            };
            deleteItem.addEventListener('click', state.onDeleteClick);
        }

        state.onDocClick = onDocumentClick;
        state.onDocKeydown = onDocumentKeydown;
        document.addEventListener('click', state.onDocClick);
        document.addEventListener('keydown', state.onDocKeydown);
        return true;
    }

    function unbindUi() {
        var trigger = getTrigger();
        var editItem = getEditItem();
        var deleteItem = getDeleteItem();
        if (trigger && state.onTriggerClick) {
            trigger.removeEventListener('click', state.onTriggerClick);
        }
        if (editItem && state.onEditClick) {
            editItem.removeEventListener('click', state.onEditClick);
        }
        if (deleteItem && state.onDeleteClick) {
            deleteItem.removeEventListener('click', state.onDeleteClick);
        }
        if (state.onDocClick) {
            document.removeEventListener('click', state.onDocClick);
        }
        if (state.onDocKeydown) {
            document.removeEventListener('keydown', state.onDocKeydown);
        }
        state.onTriggerClick = null;
        state.onEditClick = null;
        state.onDeleteClick = null;
        state.onDocClick = null;
        state.onDocKeydown = null;
    }

    function mount() {
        if (state.mounted) {
            return false;
        }

        var cfg = getConfig();
        if (!configIsValid(cfg)) {
            return false;
        }
        if (!getRoot() || !getHeader() || !getTrigger() || !getMenu()) {
            return false;
        }
        if (cfg.canUpdateTitle && !getTitleEl()) {
            return false;
        }
        if (!modalAvailable()) {
            return false;
        }
        if (!bindUi(cfg)) {
            return false;
        }

        state.mounted = true;
        state.generation += 1;
        state.menuOpen = false;
        state.activeModal = null;
        state.inFlight = false;
        state.navigationScheduled = false;
        state.currentTitle = cfg.title;
        setMenuOpen(false);
        return true;
    }

    function destroy() {
        if (!state.mounted) {
            closeMenu();
            unbindUi();
            unwrapModalClose();
            return;
        }

        state.generation += 1;
        state.mounted = false;
        state.inFlight = false;
        state.navigationScheduled = false;
        closeMenu();
        unbindUi();

        if (state.activeModal && window.AAAdmin && window.AAAdmin.modal) {
            unwrapModalClose();
            window.AAAdmin.modal.close();
        } else {
            unwrapModalClose();
        }
        state.activeModal = null;
        state.focusReturn = null;
    }

    window.AAAdmin = window.AAAdmin || {};
    window.AAAdmin.ExpedienteDetailActions = {
        mount: mount,
        destroy: destroy,
        /** @internal tests */
        _getState: function () {
            return {
                mounted: state.mounted,
                menuOpen: state.menuOpen,
                activeModal: state.activeModal,
                confirmationOpen: state.activeModal === 'delete',
                inFlight: state.inFlight,
                navigationScheduled: state.navigationScheduled,
                currentTitle: state.currentTitle,
                generation: state.generation
            };
        }
    };

    function autoMount() {
        mount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoMount, { once: true });
    } else {
        autoMount();
    }
})();
