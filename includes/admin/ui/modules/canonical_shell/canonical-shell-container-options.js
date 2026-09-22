/**
 * Canonical Shell — popups de opciones de lista y disclosure de vistas.
 * Triggers accesibles + Escape por nivel + clic fuera; sin role=menu (Tab nativo).
 */
(function () {
    'use strict';

    var root = document.getElementById('aa-canonical-shell-root');
    if (!root) {
        return;
    }

    var openPopup = null;
    var openTrigger = null;
    var openViewsPanel = null;
    var openViewsTrigger = null;

    var createModal = document.getElementById('aa-shell-container-modal');
    var deleteModal = document.getElementById('aa-shell-delete-container-modal');

    function isModalLayerOpen() {
        if (deleteModal && !deleteModal.classList.contains('hidden')) {
            return true;
        }
        if (createModal && !createModal.classList.contains('hidden')) {
            return true;
        }
        return false;
    }

    function findPopup(trigger) {
        var wrap = trigger.closest('.aa-shell-container-options');
        return wrap ? wrap.querySelector('.aa-shell-container-options-popup') : null;
    }

    function findViewsPanel(trigger) {
        var popup = trigger.closest('.aa-shell-container-options-popup');
        return popup ? popup.querySelector('.aa-shell-record-views-panel') : null;
    }

    function closeViewsPanel(restoreFocus) {
        if (!openViewsPanel) {
            return;
        }
        openViewsPanel.classList.add('hidden');
        openViewsPanel.setAttribute('hidden', '');
        if (openViewsTrigger) {
            openViewsTrigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus && typeof openViewsTrigger.focus === 'function') {
                openViewsTrigger.focus();
            }
        }
        openViewsPanel = null;
        openViewsTrigger = null;
    }

    function openViewsPanelFor(trigger, panel) {
        closeViewsPanel(false);
        openViewsPanel = panel;
        openViewsTrigger = trigger;
        panel.classList.remove('hidden');
        panel.removeAttribute('hidden');
        trigger.setAttribute('aria-expanded', 'true');
    }

    function closePopup(restoreFocus) {
        if (!openPopup) {
            return;
        }
        closeViewsPanel(false);
        openPopup.classList.add('hidden');
        openPopup.setAttribute('hidden', '');
        if (openTrigger) {
            openTrigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus && typeof openTrigger.focus === 'function') {
                openTrigger.focus();
            }
        }
        openPopup = null;
        openTrigger = null;
    }

    function openPopupFor(trigger, popup) {
        closePopup(false);
        openPopup = popup;
        openTrigger = trigger;
        popup.classList.remove('hidden');
        popup.removeAttribute('hidden');
        trigger.setAttribute('aria-expanded', 'true');
    }

    root.addEventListener('click', function (event) {
        var trigger = event.target.closest('.aa-shell-container-options-trigger');
        if (trigger && root.contains(trigger)) {
            event.preventDefault();
            event.stopPropagation();
            var popup = findPopup(trigger);
            if (!popup) {
                return;
            }
            if (openPopup === popup) {
                closePopup(true);
                return;
            }
            openPopupFor(trigger, popup);
            return;
        }

        var viewsTrigger = event.target.closest('.aa-shell-record-views-trigger');
        if (viewsTrigger && openPopup && openPopup.contains(viewsTrigger)) {
            event.preventDefault();
            event.stopPropagation();
            var viewsPanel = findViewsPanel(viewsTrigger);
            if (!viewsPanel) {
                return;
            }
            if (openViewsPanel === viewsPanel) {
                closeViewsPanel(true);
                return;
            }
            openViewsPanelFor(viewsTrigger, viewsPanel);
            return;
        }

        if (event.target.closest('.aa-shell-edit-container-btn, .aa-shell-delete-container-btn')) {
            if (event.target.closest('.aa-shell-container-options-popup')) {
                closePopup(false);
            }
            return;
        }

        if (event.target.closest('.aa-shell-container-options-popup')) {
            event.stopPropagation();
        }
    });

    document.addEventListener('click', function (event) {
        if (!openPopup) {
            return;
        }
        if (event.target.closest('.aa-shell-container-options')) {
            return;
        }
        closePopup(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (e.defaultPrevented) {
            return;
        }
        if (isModalLayerOpen()) {
            return;
        }
        if (openViewsPanel) {
            closeViewsPanel(true);
            if (typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            return;
        }
        if (!openPopup) {
            return;
        }
        closePopup(true);
        if (typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
    });
})();
