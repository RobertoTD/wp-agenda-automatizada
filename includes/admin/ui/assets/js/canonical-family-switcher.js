/**
 * Canonical family switcher — disclosure in the shared header (canonical_shell).
 *
 * Native button + link list. No menu keyboard pattern.
 */
(function () {
    'use strict';

    var ROOT_ID = 'aa-family-switcher';
    var TRIGGER_ID = 'aa-page-title';
    var PANEL_ID = 'aa-family-switcher-panel';

    var isOpen = false;
    var isBound = false;

    function getRoot() {
        return document.getElementById(ROOT_ID);
    }

    function getTrigger() {
        var el = document.getElementById(TRIGGER_ID);
        if (!el || el.getAttribute('data-aa-title-mode') !== 'family-switcher') {
            return null;
        }
        return el;
    }

    function getPanel() {
        return document.getElementById(PANEL_ID);
    }

    function focusPreferredLink(panel) {
        if (!panel) {
            return;
        }
        var current = panel.querySelector('a[aria-current="page"]');
        var first = panel.querySelector('a[href]');
        var target = current || first;
        if (target && typeof target.focus === 'function') {
            target.focus();
        }
    }

    function openSwitcher() {
        var trigger = getTrigger();
        var panel = getPanel();
        if (!trigger || !panel) {
            return;
        }

        panel.classList.remove('hidden');
        panel.removeAttribute('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        isOpen = true;
        focusPreferredLink(panel);
    }

    function closeSwitcher(options) {
        var trigger = getTrigger();
        var panel = getPanel();
        var restoreFocus = !options || options.restoreFocus !== false;

        if (panel) {
            panel.classList.add('hidden');
            panel.setAttribute('hidden', '');
        }
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus && isOpen && typeof trigger.focus === 'function') {
                trigger.focus();
            }
        }
        isOpen = false;
    }

    function toggleSwitcher() {
        if (isOpen) {
            closeSwitcher({ restoreFocus: false });
        } else {
            openSwitcher();
        }
    }

    function handleDocumentClick(event) {
        var root = getRoot();
        if (!root || !isOpen) {
            return;
        }
        var target = event && event.target;
        if (target && root.contains(target)) {
            return;
        }
        closeSwitcher({ restoreFocus: false });
    }

    function handleDocumentKeydown(event) {
        if (!event || event.key !== 'Escape' || !isOpen) {
            return;
        }
        event.stopPropagation();
        closeSwitcher({ restoreFocus: true });
    }

    function handleTriggerClick(event) {
        if (event) {
            event.preventDefault();
        }
        toggleSwitcher();
    }

    function init() {
        var root = getRoot();
        var trigger = getTrigger();
        var panel = getPanel();
        if (!root || !trigger || !panel || isBound) {
            return;
        }

        isBound = true;
        trigger.addEventListener('click', handleTriggerClick);
        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleDocumentKeydown, true);
    }

    var api = {
        init: init,
        open: openSwitcher,
        close: closeSwitcher,
        isOpen: function () {
            return isOpen;
        }
    };

    if (typeof window !== 'undefined') {
        window.AACanonicalFamilySwitcher = api;
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
