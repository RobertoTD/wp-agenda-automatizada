/**
 * Expediente Detail Actions — menú del contenedor + delete canónico (Ciclo C).
 *
 * API: AAAdmin.ExpedienteDetailActions.mount() / .destroy()
 * Config: window.AA_EXPEDIENTE_DETAIL_DATA.containerActions (+ expedienteId/ajaxUrl)
 */
(function () {
    'use strict';

    var MSG_BUSY = 'El expediente está siendo modificado. Inténtalo nuevamente.';
    var MSG_INCONSISTENT = 'No fue posible eliminar el expediente porque sus datos requieren revisión.';
    var MSG_STORAGE = 'No fue posible eliminar todas las imágenes. Inténtalo nuevamente.';
    var MSG_FALLBACK = 'No se pudo eliminar el expediente.';
    var MODAL_TITLE = 'Eliminar expediente';
    var MODAL_BODY = 'Se eliminarán permanentemente este expediente, todos sus registros y sus imágenes. Esta acción no se puede deshacer.';

    var state = {
        mounted: false,
        generation: 0,
        menuOpen: false,
        confirmationOpen: false,
        inFlight: false,
        navigationScheduled: false,
        focusReturn: null,
        wrappedClose: null,
        originalClose: null,
        onDocClick: null,
        onDocKeydown: null,
        onTriggerClick: null,
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

    function getDeleteItem() {
        return document.getElementById('aa-expediente-detail-tools-delete');
    }

    function getConfig() {
        var cfg = window.AA_EXPEDIENTE_DETAIL_DATA || {};
        var container = cfg.containerActions && typeof cfg.containerActions === 'object'
            ? cfg.containerActions
            : {};

        return {
            ajaxUrl: typeof cfg.ajaxUrl === 'string' ? cfg.ajaxUrl : '',
            expedienteId: cfg.expedienteId,
            deleteAction: typeof container.deleteAction === 'string' ? container.deleteAction : '',
            nonce: typeof container.nonce === 'string' ? container.nonce : '',
            listUrl: typeof container.listUrl === 'string' ? container.listUrl : ''
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
        if (!cfg.ajaxUrl || !cfg.deleteAction || !cfg.nonce) {
            return false;
        }
        if (normalizeExpedienteId(cfg.expedienteId) === null) {
            return false;
        }
        return isValidListUrl(cfg.listUrl);
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

    function messageForError(status, code) {
        var normalized = typeof code === 'string' ? code : '';
        if (status === 409 && normalized === 'resource_busy') {
            return MSG_BUSY;
        }
        if (status === 409 && normalized === 'aggregate_inconsistent') {
            return MSG_INCONSISTENT;
        }
        if (status === 409 && (normalized === 'concurrent_change' || normalized === 'resource_busy')) {
            return MSG_BUSY;
        }
        if (status === 502 || normalized === 'delete_failed'
            || normalized === 'storage_delete_failed'
            || normalized === 'expediente_attachments_unreachable'
            || normalized === 'expediente_attachments_invalid_response') {
            return MSG_STORAGE;
        }
        return MSG_FALLBACK;
    }

    function createConfirmFooter(onCancel, onConfirm) {
        var footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.id = 'aa-expediente-detail-delete-cancel';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');
        cancelBtn.addEventListener('click', function () {
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });

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

    function createConfirmBody(errorText) {
        var wrap = document.createElement('div');
        wrap.className = 'aa-expediente-detail-delete-body space-y-3';

        var p = document.createElement('p');
        p.className = 'text-base text-gray-600';
        p.textContent = MODAL_BODY;
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

    function setConfirmBusy(confirmBtn, busy) {
        if (!confirmBtn) {
            return;
        }
        confirmBtn.disabled = !!busy;
        if (busy) {
            confirmBtn.setAttribute('aria-busy', 'true');
        } else {
            confirmBtn.removeAttribute('aria-busy');
        }
    }

    function showErrorInModal(message) {
        var err = document.getElementById('aa-expediente-detail-delete-error');
        if (!err) {
            return;
        }
        err.textContent = message;
        err.classList.remove('hidden');
    }

    function unwrapModalClose() {
        if (state.wrappedClose && state.originalClose && window.AAAdmin && window.AAAdmin.modal) {
            window.AAAdmin.modal.close = state.originalClose;
        }
        state.wrappedClose = null;
        state.originalClose = null;
    }

    function onConfirmationClosed() {
        state.confirmationOpen = false;
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
            var wasOpen = state.confirmationOpen;
            state.originalClose.call(window.AAAdmin.modal);
            if (wasOpen) {
                onConfirmationClosed();
            }
        };
        window.AAAdmin.modal.close = state.wrappedClose;
    }

    function openConfirmModal(cfg) {
        if (!modalAvailable() || state.inFlight || state.navigationScheduled) {
            return;
        }
        if (window.AAAdmin.modal.isOpen && window.AAAdmin.modal.isOpen()) {
            return;
        }

        closeMenu();
        state.focusReturn = getTrigger();
        state.confirmationOpen = true;

        var footer = createConfirmFooter(
            function () { /* close via data-aa-modal-close */ },
            function (confirmBtn) {
                submitDelete(cfg, confirmBtn);
            }
        );

        wrapModalClose();
        window.AAAdmin.modal.open({
            title: MODAL_TITLE,
            body: createConfirmBody(''),
            footer: footer
        });

        window.setTimeout(function () {
            var cancel = document.getElementById('aa-expediente-detail-delete-cancel');
            if (cancel && typeof cancel.focus === 'function') {
                cancel.focus();
            }
        }, 50);
    }

    function isSuccessPayload(result, expectedId) {
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

    function submitDelete(cfg, confirmBtn) {
        if (state.inFlight || state.navigationScheduled || !state.mounted) {
            return;
        }

        var generation = state.generation;
        var expedienteId = normalizeExpedienteId(cfg.expedienteId);
        if (expedienteId === null) {
            return;
        }

        state.inFlight = true;
        setConfirmBusy(confirmBtn, true);

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

            if (payload.ok && isSuccessPayload(payload.json, expedienteId)) {
                if (state.navigationScheduled) {
                    return;
                }
                state.navigationScheduled = true;
                state.inFlight = false;
                state.confirmationOpen = false;
                if (window.AAAdmin && window.AAAdmin.modal) {
                    unwrapModalClose();
                    window.AAAdmin.modal.close();
                }
                window.location.replace(cfg.listUrl);
                return;
            }

            state.inFlight = false;
            setConfirmBusy(confirmBtn, false);
            var code = '';
            if (payload.json && payload.json.data && typeof payload.json.data.code === 'string') {
                code = payload.json.data.code;
            }
            showErrorInModal(messageForError(payload.status, code));
        }).catch(function () {
            if (!state.mounted || generation !== state.generation) {
                return;
            }
            state.inFlight = false;
            setConfirmBusy(confirmBtn, false);
            showErrorInModal(MSG_FALLBACK);
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
        if (state.confirmationOpen) {
            // Shared modal closes on Escape; wrapped close restores focus.
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
        var deleteItem = getDeleteItem();
        if (!trigger || !deleteItem) {
            return false;
        }

        state.onTriggerClick = function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (state.confirmationOpen || state.inFlight) {
                return;
            }
            setMenuOpen(!state.menuOpen);
        };
        state.onDeleteClick = function (event) {
            event.preventDefault();
            event.stopPropagation();
            openConfirmModal(cfg);
        };

        trigger.addEventListener('click', state.onTriggerClick);
        deleteItem.addEventListener('click', state.onDeleteClick);

        state.onDocClick = onDocumentClick;
        state.onDocKeydown = onDocumentKeydown;
        document.addEventListener('click', state.onDocClick);
        document.addEventListener('keydown', state.onDocKeydown);
        return true;
    }

    function unbindUi() {
        var trigger = getTrigger();
        var deleteItem = getDeleteItem();
        if (trigger && state.onTriggerClick) {
            trigger.removeEventListener('click', state.onTriggerClick);
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
        if (!getRoot() || !getHeader() || !getTrigger() || !getMenu() || !getDeleteItem()) {
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
        state.confirmationOpen = false;
        state.inFlight = false;
        state.navigationScheduled = false;
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

        if (state.confirmationOpen && window.AAAdmin && window.AAAdmin.modal) {
            unwrapModalClose();
            window.AAAdmin.modal.close();
        } else {
            unwrapModalClose();
        }
        state.confirmationOpen = false;
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
                confirmationOpen: state.confirmationOpen,
                inFlight: state.inFlight,
                navigationScheduled: state.navigationScheduled,
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
