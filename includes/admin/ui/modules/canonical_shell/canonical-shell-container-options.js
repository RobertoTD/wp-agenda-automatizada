/**
 * Canonical Shell — popup de opciones en cards de listas (contenedores).
 * Trigger accesible + Escape + clic fuera; sin role=menu (Tab nativo).
 */
(function () {
    'use strict';

    var root = document.getElementById('aa-canonical-shell-root');
    if (!root) {
        return;
    }

    var openPopup = null;
    var openTrigger = null;

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

    function closePopup(restoreFocus) {
        if (!openPopup) {
            return;
        }
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
        if (!openPopup) {
            return;
        }
        closePopup(true);
        if (typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
    });
})();
