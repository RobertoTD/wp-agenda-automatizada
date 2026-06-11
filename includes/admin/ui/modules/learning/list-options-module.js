/**
 * List Options Module — menú contextual ⋮ y coordinación de expansión en el feed executable.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var isBound = false;
    var openListId = '';
    var coordinatingListToggle = false;

    function asString(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function setVisible(el, visible) {
        if (!el) {
            return;
        }

        if (visible) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    }

    function findMenuForList(listId) {
        if (!listId) {
            return null;
        }

        return document.querySelector('.aa-executable-list-options-menu[data-list-id="' + listId + '"]');
    }

    function findTriggerForList(listId) {
        if (!listId) {
            return null;
        }

        return document.querySelector('.aa-executable-list-options-trigger[data-list-id="' + listId + '"]');
    }

    function setTriggerExpanded(listId, expanded) {
        var trigger = findTriggerForList(listId);

        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function closeAllMenus() {
        document.querySelectorAll('.aa-executable-list-options-menu').forEach(function (menu) {
            setVisible(menu, false);
        });

        document.querySelectorAll('.aa-executable-list-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        openListId = '';
    }

    function openMenu(listId) {
        var menu = findMenuForList(listId);

        if (!menu) {
            return;
        }

        closeAllMenus();
        setVisible(menu, true);
        setTriggerExpanded(listId, true);
        openListId = listId;
    }

    function toggleMenu(listId) {
        if (openListId === listId) {
            closeAllMenus();
            return;
        }

        openMenu(listId);
    }

    function isInsideListOptions(target) {
        if (!target || !target.closest) {
            return false;
        }

        return !!target.closest('.aa-executable-list-options');
    }

    function handleDocumentClick(event) {
        var target = event.target;
        var trigger = target && target.closest
            ? target.closest('[data-aa-list-options-trigger]')
            : null;

        if (trigger && !trigger.disabled) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            toggleMenu(asString(trigger.getAttribute('data-list-id')).trim());
            return;
        }

        if (openListId !== '' && !isInsideListOptions(target)) {
            closeAllMenus();
        }
    }

    function handleDocumentKeydown(event) {
        if (!event || event.key !== 'Escape' || openListId === '') {
            return;
        }

        closeAllMenus();
    }

    function isListCard(details) {
        return details
            && details.classList
            && details.classList.contains('aa-executable-list-card');
    }

    function closeAllTasksInList(listDetails) {
        if (!listDetails || typeof listDetails.querySelectorAll !== 'function') {
            return;
        }

        listDetails.querySelectorAll('details.aa-executable-item').forEach(function (task) {
            task.open = false;
        });
    }

    function openFirstTaskInList(listDetails) {
        if (!listDetails || typeof listDetails.querySelector !== 'function') {
            return;
        }

        var firstTask = listDetails.querySelector('details.aa-executable-item');

        if (firstTask) {
            firstTask.open = true;
        }
    }

    function closeOtherListCards(activeListDetails) {
        if (!activeListDetails) {
            return;
        }

        document.querySelectorAll('details.aa-executable-list-card[open]').forEach(function (listDetails) {
            if (listDetails === activeListDetails) {
                return;
            }

            coordinatingListToggle = true;

            try {
                closeAllTasksInList(listDetails);
                listDetails.open = false;
            } finally {
                coordinatingListToggle = false;
            }
        });
    }

    function handleListToggle(event) {
        var details = event.target;

        if (!isListCard(details)) {
            return;
        }

        closeAllMenus();

        if (coordinatingListToggle) {
            return;
        }

        if (details.open) {
            coordinatingListToggle = true;

            try {
                closeOtherListCards(details);
                openFirstTaskInList(details);
            } finally {
                coordinatingListToggle = false;
            }

            return;
        }

        coordinatingListToggle = true;

        try {
            closeAllTasksInList(details);
        } finally {
            coordinatingListToggle = false;
        }
    }

    function handleMenuItemClick(event) {
        var target = event.target;
        var menuItem = target && target.closest
            ? target.closest('.aa-executable-list-options-menu [role="menuitem"]')
            : null;

        if (!menuItem) {
            return;
        }

        closeAllMenus();
    }

    function bindListOptionsModule() {
        if (isBound || !document.getElementById('aa-tasks-module-root')) {
            return;
        }

        isBound = true;

        document.addEventListener('click', handleDocumentClick, true);
        document.addEventListener('click', handleMenuItemClick);
        document.addEventListener('keydown', handleDocumentKeydown);
        document.addEventListener('toggle', handleListToggle, true);
    }

    function initListOptionsModule() {
        bindListOptionsModule();
    }

    var moduleExports = {
        closeAllMenus: closeAllMenus,
        getOpenListId: function () {
            return openListId;
        },
        openMenu: openMenu,
        toggleMenu: toggleMenu,
        closeAllTasksInList: closeAllTasksInList,
        openFirstTaskInList: openFirstTaskInList,
        closeOtherListCards: closeOtherListCards,
        handleListToggle: handleListToggle
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initListOptionsModule);
    } else {
        initListOptionsModule();
    }
})();
