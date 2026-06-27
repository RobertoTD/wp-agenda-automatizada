/**
 * Task Options Module — menú contextual ⋮ por tarea en el feed executable.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var isBound = false;
    var openTaskId = '';
    var MENU_VIEWPORT_MARGIN = 8;
    var MENU_PLACEMENT_GAP = 8;

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

    function closeListMenus() {
        document.querySelectorAll('.aa-executable-list-options-menu').forEach(function (menu) {
            menu.classList.remove('bottom-full', 'mb-2');
            menu.classList.add('top-full', 'mt-2');
            setVisible(menu, false);
        });

        document.querySelectorAll('.aa-executable-list-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        document.querySelectorAll('.aa-executable-list-card--floating-menu').forEach(function (card) {
            card.classList.remove('aa-executable-list-card--floating-menu');
        });
    }

    function findMenuForTask(taskId) {
        if (!taskId) {
            return null;
        }

        return document.querySelector('.aa-executable-task-options-menu[data-task-id="' + taskId + '"]');
    }

    function findTriggerForTask(taskId) {
        if (!taskId) {
            return null;
        }

        return document.querySelector('.aa-executable-task-options-trigger[data-task-id="' + taskId + '"]');
    }

    function setTriggerExpanded(taskId, expanded) {
        var trigger = findTriggerForTask(taskId);

        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function resetTaskMenuPlacement(menu) {
        if (!menu) {
            return;
        }

        menu.classList.remove('bottom-full', 'mb-2');
        menu.classList.add('top-full', 'mt-2');
    }

    function clearListCardMenuElevation() {
        document.querySelectorAll('.aa-executable-list-card--floating-menu').forEach(function (card) {
            card.classList.remove('aa-executable-list-card--floating-menu');
        });
    }

    function setListCardMenuElevation(menu, elevated) {
        var listCard = menu && menu.closest
            ? menu.closest('details.aa-executable-list-card')
            : null;

        if (!listCard) {
            return;
        }

        if (elevated) {
            listCard.classList.add('aa-executable-list-card--floating-menu');
        } else {
            listCard.classList.remove('aa-executable-list-card--floating-menu');
        }
    }

    function getListsBodyClipTop() {
        var listsBody = document.getElementById('aa-lists-body');

        if (!listsBody || typeof listsBody.getBoundingClientRect !== 'function') {
            return MENU_VIEWPORT_MARGIN;
        }

        return listsBody.getBoundingClientRect().top;
    }

    function getListsBodyClipBottom() {
        var listsBody = document.getElementById('aa-lists-body');

        if (!listsBody || typeof listsBody.getBoundingClientRect !== 'function') {
            return window.innerHeight - MENU_VIEWPORT_MARGIN;
        }

        return listsBody.getBoundingClientRect().bottom;
    }

    function flipMenuUpIfClipped(menu, trigger) {
        var menuRect = menu.getBoundingClientRect();
        var clipBottom = getListsBodyClipBottom() - MENU_VIEWPORT_MARGIN;
        var viewportBottom = window.innerHeight - MENU_VIEWPORT_MARGIN;
        var overflowsListsBody = menuRect.bottom > clipBottom + 0.5;
        var overflowsViewport = menuRect.bottom > viewportBottom;

        if (!overflowsListsBody && !overflowsViewport) {
            return;
        }

        var triggerRect = trigger.getBoundingClientRect();
        var upwardTop = triggerRect.top - menuRect.height - MENU_PLACEMENT_GAP;
        var safeTop = Math.max(MENU_VIEWPORT_MARGIN, getListsBodyClipTop());

        if (upwardTop >= safeTop) {
            menu.classList.remove('top-full', 'mt-2');
            menu.classList.add('bottom-full', 'mb-2');
        }
    }

    function positionTaskMenu(menu, taskId) {
        if (!menu) {
            return;
        }

        resetTaskMenuPlacement(menu);

        var trigger = findTriggerForTask(taskId);

        if (!trigger) {
            return;
        }

        flipMenuUpIfClipped(menu, trigger);
    }

    function closeAllMenus() {
        document.querySelectorAll('.aa-executable-task-options-menu').forEach(function (menu) {
            resetTaskMenuPlacement(menu);
            setVisible(menu, false);
        });

        document.querySelectorAll('.aa-executable-task-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        clearListCardMenuElevation();
        openTaskId = '';
    }

    function openMenu(taskId) {
        var menu = findMenuForTask(taskId);

        if (!menu) {
            return;
        }

        closeListMenus();
        closeAllMenus();
        setVisible(menu, true);
        positionTaskMenu(menu, taskId);
        setListCardMenuElevation(menu, true);
        setTriggerExpanded(taskId, true);
        openTaskId = taskId;
    }

    function toggleMenu(taskId) {
        if (openTaskId === taskId) {
            closeAllMenus();
            return;
        }

        openMenu(taskId);
    }

    function isInsideTaskOptions(target) {
        if (!target || !target.closest) {
            return false;
        }

        return !!target.closest('.aa-executable-task-options');
    }

    function handleDocumentClick(event) {
        var target = event.target;
        var trigger = target && target.closest
            ? target.closest('[data-aa-task-options-trigger]')
            : null;

        if (trigger && !trigger.disabled) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            toggleMenu(asString(trigger.getAttribute('data-task-id')).trim());
            return;
        }

        if (openTaskId !== '' && !isInsideTaskOptions(target)) {
            closeAllMenus();
        }
    }

    function handleDocumentKeydown(event) {
        if (!event || event.key !== 'Escape' || openTaskId === '') {
            return;
        }

        closeAllMenus();
    }

    function isListCard(details) {
        return details
            && details.classList
            && details.classList.contains('aa-executable-list-card');
    }

    function handleListToggle(event) {
        var details = event.target;

        if (!isListCard(details)) {
            return;
        }

        closeAllMenus();
    }

    function handleTaskToggle(event) {
        var details = event.target;

        if (!details
            || !details.classList
            || !details.classList.contains('aa-executable-item')) {
            return;
        }

        closeAllMenus();
    }

    function handleMenuItemClick(event) {
        var target = event.target;
        var menuItem = target && target.closest
            ? target.closest('.aa-executable-task-options-menu [role="menuitem"]')
            : null;

        if (!menuItem) {
            return;
        }

        closeAllMenus();
    }

    function bindTaskOptionsModule() {
        if (isBound || !document.getElementById('aa-tasks-module-root')) {
            return;
        }

        isBound = true;

        document.addEventListener('click', handleDocumentClick, true);
        document.addEventListener('click', handleMenuItemClick);
        document.addEventListener('keydown', handleDocumentKeydown);
        document.addEventListener('toggle', handleListToggle, true);
        document.addEventListener('toggle', handleTaskToggle, true);
    }

    function initTaskOptionsModule() {
        bindTaskOptionsModule();
    }

    var moduleExports = {
        closeAllMenus: closeAllMenus,
        getOpenTaskId: function () {
            return openTaskId;
        },
        openMenu: openMenu,
        toggleMenu: toggleMenu
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTaskOptionsModule);
    } else {
        initTaskOptionsModule();
    }
})();
