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
    var coordinatingTaskToggle = false;
    var restoreSkipFollowingResetListId = '';
    var isViewportDismissBound = false;

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

    function getMenuPlacement() {
        return globalRoot.AAExecutableOptionsMenuPlacement || null;
    }

    function resetMenuPlacement(menu) {
        var placement = getMenuPlacement();

        if (placement && typeof placement.resetOptionsMenuPlacement === 'function') {
            placement.resetOptionsMenuPlacement(menu);
            return;
        }

        if (!menu) {
            return;
        }

        menu.classList.remove('bottom-full', 'mb-2');
        menu.classList.add('top-full', 'mt-2');
    }

    function closeTaskMenus() {
        document.querySelectorAll('.aa-executable-task-options-menu').forEach(function (menu) {
            resetMenuPlacement(menu);
            setVisible(menu, false);
        });

        document.querySelectorAll('.aa-executable-task-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        document.querySelectorAll('.aa-executable-list-card--floating-menu').forEach(function (card) {
            card.classList.remove('aa-executable-list-card--floating-menu');
        });
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

    function positionListMenu(menu, listId) {
        if (!menu) {
            return;
        }

        var trigger = findTriggerForList(listId);
        var placement = getMenuPlacement();

        if (!trigger || !placement || typeof placement.positionOptionsMenu !== 'function') {
            return;
        }

        placement.positionOptionsMenu(menu, trigger);
    }

    function closeAllMenus() {
        document.querySelectorAll('.aa-executable-list-options-menu').forEach(function (menu) {
            resetMenuPlacement(menu);
            setVisible(menu, false);
        });

        document.querySelectorAll('.aa-executable-list-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        clearListCardMenuElevation();
        closeTaskMenus();
        openListId = '';
    }

    function openMenu(listId) {
        var menu = findMenuForList(listId);

        if (!menu) {
            return;
        }

        closeTaskMenus();
        closeAllMenus();
        setVisible(menu, true);
        positionListMenu(menu, listId);
        setListCardMenuElevation(menu, true);
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

    function findListDetailsPanel(listId) {
        if (!listId) {
            return null;
        }

        return document.querySelector('.aa-executable-list-details[data-list-id="' + listId + '"]');
    }

    function findListDetailsToggle(listId) {
        if (!listId) {
            return null;
        }

        return document.querySelector('.aa-executable-list-details-toggle[data-list-id="' + listId + '"]');
    }

    function setListDetailsExpanded(listId, expanded) {
        var panel = findListDetailsPanel(listId);
        var toggle = findListDetailsToggle(listId);

        if (panel) {
            if (expanded) {
                panel.classList.add('is-visible');
            } else {
                panel.classList.remove('is-visible');
            }
        }

        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.textContent = expanded ? 'Ver menos' : 'Ver más';
        }
    }

    function resetListDetails(listDetails) {
        if (!listDetails || typeof listDetails.querySelectorAll !== 'function') {
            return;
        }

        listDetails.querySelectorAll('.aa-executable-list-details').forEach(function (panel) {
            panel.classList.remove('is-visible');
        });

        listDetails.querySelectorAll('.aa-executable-list-details-toggle').forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.textContent = 'Ver más';
        });
    }

    function handleDocumentClick(event) {
        var target = event.target;
        var addTaskBtn = target && target.closest
            ? target.closest('[data-aa-list-add-task]')
            : null;

        if (addTaskBtn && !addTaskBtn.disabled) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            var addTaskListId = asString(addTaskBtn.getAttribute('data-list-id')).trim();

            closeAllMenus();

            if (addTaskListId
                && globalRoot.AATasksBoard
                && typeof globalRoot.AATasksBoard.openNewTaskForList === 'function') {
                globalRoot.AATasksBoard.openNewTaskForList(addTaskListId);
            }

            return;
        }

        var detailsToggle = target && target.closest
            ? target.closest('[data-aa-list-details-toggle]')
            : null;

        if (detailsToggle && !detailsToggle.disabled) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            var detailsListId = asString(detailsToggle.getAttribute('data-list-id')).trim();
            var isExpanded = detailsToggle.getAttribute('aria-expanded') === 'true';

            setListDetailsExpanded(detailsListId, !isExpanded);
            return;
        }

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

    function isTaskItem(details) {
        return details
            && details.classList
            && details.classList.contains('aa-executable-item');
    }

    function isFollowingTasksBlock(details) {
        return details
            && details.classList
            && details.classList.contains('aa-executable-following-tasks');
    }

    function isListCard(details) {
        return details
            && details.classList
            && details.classList.contains('aa-executable-list-card');
    }

    /**
     * @param {HTMLElement|null|undefined} root
     * @param {string} listId
     * @returns {HTMLDetailsElement|null}
     */
    function findListCardById(root, listId) {
        var normalizedId = asString(listId).trim();

        if (!root || normalizedId === '' || typeof root.querySelectorAll !== 'function') {
            return null;
        }

        var cards = root.querySelectorAll('details.aa-executable-list-card');
        var index = 0;

        for (index = 0; index < cards.length; index += 1) {
            var card = cards[index];

            if (asString(card.getAttribute('data-list-id')).trim() === normalizedId) {
                return card;
            }
        }

        return null;
    }

    /**
     * @param {HTMLElement|null|undefined} root
     * @returns {string}
     */
    function getOpenListCardId(root) {
        if (!root || typeof root.querySelector !== 'function') {
            return '';
        }

        var open = root.querySelector('details.aa-executable-list-card[open]');

        if (!open) {
            return '';
        }

        return asString(open.getAttribute('data-list-id')).trim();
    }

    /**
     * @param {string} listId
     * @param {HTMLElement|null|undefined} root
     */
    function reopenListCardById(listId, root) {
        var details = findListCardById(root, listId);

        if (!details || details.open) {
            return;
        }

        details.open = true;
    }

    /**
     * @param {HTMLElement|null|undefined} root
     * @returns {{restoreOpenListId: string, restoreFollowingTasksOpen: boolean}|null}
     */
    function getListRestoreSnapshot(root) {
        var listId = getOpenListCardId(root);

        if (listId === '') {
            return null;
        }

        var list = findListCardById(root, listId);
        var followingOpen = !!(list
            && typeof list.querySelector === 'function'
            && list.querySelector('details.aa-executable-following-tasks[open]'));

        return {
            restoreOpenListId: listId,
            restoreFollowingTasksOpen: followingOpen
        };
    }

    /**
     * @param {string} listId
     * @param {HTMLElement|null|undefined} root
     */
    function reopenFollowingTasksInList(listId, root) {
        var list = findListCardById(root, listId);

        if (!list || typeof list.querySelector !== 'function') {
            return;
        }

        var following = list.querySelector('details.aa-executable-following-tasks');

        if (!following || following.open) {
            return;
        }

        following.open = true;
    }

    /**
     * @param {string} listId
     * @param {HTMLElement|null|undefined} root
     * @param {{followingTasksOpen?: boolean}} [options]
     */
    function restoreListAfterReload(listId, root, options) {
        var normalizedListId = asString(listId).trim();
        var opts = options || {};
        var followingTasksOpen = opts.followingTasksOpen === true;

        if (normalizedListId === '' || !root) {
            return;
        }

        var list = findListCardById(root, normalizedListId);

        if (!list) {
            return;
        }

        if (followingTasksOpen) {
            restoreSkipFollowingResetListId = normalizedListId;
        } else {
            restoreSkipFollowingResetListId = '';
        }

        if (!list.open) {
            coordinatingListToggle = true;

            try {
                list.open = true;
            } finally {
                coordinatingListToggle = false;
            }
        }

        closeAllMenus();
        resetListDetails(list);

        if (!followingTasksOpen) {
            resetFollowingTasksBlocks(list);
        }

        coordinatingListToggle = true;

        try {
            closeOtherListCards(list);
            openFirstTaskInList(list);
        } finally {
            coordinatingListToggle = false;
        }

        if (followingTasksOpen) {
            var following = list.querySelector('details.aa-executable-following-tasks');

            if (following && !following.open) {
                following.open = true;
            }

            if (typeof globalRoot.setTimeout === 'function') {
                globalRoot.setTimeout(function () {
                    if (restoreSkipFollowingResetListId === normalizedListId) {
                        restoreSkipFollowingResetListId = '';
                    }
                }, 0);
            }
        }
    }

    function closeTasksInFollowingBlock(followingDetails) {
        if (!followingDetails || typeof followingDetails.querySelectorAll !== 'function') {
            return;
        }

        followingDetails.querySelectorAll('details.aa-executable-item').forEach(function (task) {
            task.open = false;
        });
    }

    function resetFollowingTasksBlocks(listDetails) {
        if (!listDetails || typeof listDetails.querySelectorAll !== 'function') {
            return;
        }

        listDetails.querySelectorAll('details.aa-executable-following-tasks').forEach(function (block) {
            block.open = false;
            closeTasksInFollowingBlock(block);
        });
    }

    function closeAllTasksInList(listDetails) {
        if (!listDetails || typeof listDetails.querySelectorAll !== 'function') {
            return;
        }

        listDetails.querySelectorAll('details.aa-executable-item').forEach(function (task) {
            task.open = false;
        });
    }

    function closeOtherTasksInList(activeTaskDetails, listDetails) {
        if (!listDetails
            || !activeTaskDetails
            || typeof listDetails.querySelectorAll !== 'function') {
            return;
        }

        listDetails.querySelectorAll('details.aa-executable-item[open]').forEach(function (task) {
            if (task !== activeTaskDetails) {
                task.open = false;
            }
        });
    }

    function openFirstTaskInList(listDetails) {
        if (!listDetails || typeof listDetails.querySelector !== 'function') {
            return;
        }

        closeAllTasksInList(listDetails);

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
            var detailsListId = asString(details.getAttribute('data-list-id')).trim();

            if (restoreSkipFollowingResetListId !== ''
                && detailsListId === restoreSkipFollowingResetListId) {
                restoreSkipFollowingResetListId = '';
                return;
            }

            resetListDetails(details);
            resetFollowingTasksBlocks(details);

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
            resetListDetails(details);
            resetFollowingTasksBlocks(details);
            closeAllTasksInList(details);
        } finally {
            coordinatingListToggle = false;
        }
    }

    function handleFollowingTasksToggle(event) {
        var block = event.target;

        if (!isFollowingTasksBlock(block)) {
            return;
        }

        if (coordinatingListToggle) {
            return;
        }

        if (!block.open) {
            closeTasksInFollowingBlock(block);
        }
    }

    function handleTaskToggle(event) {
        var taskDetails = event.target;

        if (!isTaskItem(taskDetails)) {
            return;
        }

        if (coordinatingListToggle || coordinatingTaskToggle) {
            return;
        }

        if (!taskDetails.open) {
            return;
        }

        var listDetails = taskDetails.closest
            ? taskDetails.closest('details.aa-executable-list-card')
            : null;

        if (!listDetails) {
            return;
        }

        coordinatingTaskToggle = true;

        try {
            closeOtherTasksInList(taskDetails, listDetails);
        } finally {
            coordinatingTaskToggle = false;
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

    function handleViewportChange() {
        if (openListId !== '') {
            closeAllMenus();
        }
    }

    function bindViewportDismissHandlers() {
        if (isViewportDismissBound) {
            return;
        }

        isViewportDismissBound = true;
        globalRoot.addEventListener('scroll', handleViewportChange, true);
        globalRoot.addEventListener('resize', handleViewportChange);
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
        document.addEventListener('toggle', handleTaskToggle, true);
        document.addEventListener('toggle', handleFollowingTasksToggle, true);
        bindViewportDismissHandlers();
    }

    function initListOptionsModule() {
        bindListOptionsModule();
    }

    var moduleExports = {
        closeAllMenus: closeAllMenus,
        getOpenListId: function () {
            return openListId;
        },
        getOpenListCardId: getOpenListCardId,
        getListRestoreSnapshot: getListRestoreSnapshot,
        reopenListCardById: reopenListCardById,
        reopenFollowingTasksInList: reopenFollowingTasksInList,
        restoreListAfterReload: restoreListAfterReload,
        findListCardById: findListCardById,
        openMenu: openMenu,
        toggleMenu: toggleMenu,
        closeAllTasksInList: closeAllTasksInList,
        closeOtherTasksInList: closeOtherTasksInList,
        openFirstTaskInList: openFirstTaskInList,
        closeOtherListCards: closeOtherListCards,
        handleListToggle: handleListToggle,
        handleTaskToggle: handleTaskToggle,
        handleFollowingTasksToggle: handleFollowingTasksToggle,
        resetListDetails: resetListDetails,
        resetFollowingTasksBlocks: resetFollowingTasksBlocks,
        closeTasksInFollowingBlock: closeTasksInFollowingBlock,
        setListDetailsExpanded: setListDetailsExpanded
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    globalRoot.AAListOptions = moduleExports;

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initListOptionsModule);
    } else {
        initListOptionsModule();
    }
})();
