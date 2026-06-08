/**
 * Lists Area Tools — herramientas globales del header #aa-lists-section (MC13I / MC13N-2).
 *
 * Menú de opciones del área + desarchivar listas + regresar tareas ignoradas.
 * No usa executable-actions-coordinator.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var MODAL_ID = 'aa-restore-archived-lists-modal';
    var OPTIONS_TRIGGER_SELECTOR = '#aa-lists-options-trigger';
    var OPTIONS_MENU_ID = 'aa-lists-options-menu';
    var RESTORE_TOOL_SELECTOR = '[data-lists-tool="restore-archived"]';
    var RETURN_IGNORED_TOOL_SELECTOR = '[data-lists-tool="return-ignored-tasks"]';
    var OPTIONS_MENU_TRIGGER_SELECTOR = '[data-lists-tool="options-menu"]';
    var RETURN_IGNORED_CONFIRM_MESSAGE = 'Todas las tareas ignoradas de tus listas activas regresarán a sus listas. ¿Quieres continuar?';

    var isActionPending = false;
    var isBound = false;
    var isMenuOpen = false;

    function getService() {
        return globalRoot.TasksService || null;
    }

    function getModal() {
        return document.getElementById(MODAL_ID);
    }

    function getSelect() {
        return document.getElementById('aa-restore-archived-lists-select');
    }

    function getSubmitButton() {
        return document.getElementById('aa-restore-archived-lists-submit');
    }

    function getLoadingEl() {
        return document.getElementById('aa-restore-archived-lists-loading');
    }

    function getEmptyEl() {
        return document.getElementById('aa-restore-archived-lists-empty');
    }

    function getErrorEl() {
        return document.getElementById('aa-restore-archived-lists-error');
    }

    function getAreaErrorEl() {
        return document.getElementById('aa-lists-area-tools-error');
    }

    function getSelectWrap() {
        return document.getElementById('aa-restore-archived-lists-select-wrap');
    }

    function getOptionsTrigger() {
        return document.querySelector(OPTIONS_TRIGGER_SELECTOR);
    }

    function getOptionsMenu() {
        return document.getElementById(OPTIONS_MENU_ID);
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

    function openModal(modalId) {
        var modal = document.getElementById(modalId);

        if (modal) {
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeModal(modalId) {
        var modal = document.getElementById(modalId);

        if (modal) {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function clearError() {
        setVisible(getErrorEl(), false);

        if (getErrorEl()) {
            getErrorEl().textContent = '';
        }
    }

    function clearAreaError() {
        setVisible(getAreaErrorEl(), false);

        if (getAreaErrorEl()) {
            getAreaErrorEl().textContent = '';
        }
    }

    function showError(message) {
        var errorEl = getErrorEl();
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo completar la acción.';
        }
    }

    function showAreaError(message) {
        var errorEl = getAreaErrorEl();
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo completar la acción.';
        }
    }

    function setMenuItemDisabled(button, disabled) {
        if (!button) {
            return;
        }

        button.disabled = disabled;

        if (disabled) {
            button.classList.add('opacity-60', 'cursor-not-allowed');
        } else {
            button.classList.remove('opacity-60', 'cursor-not-allowed');
        }
    }

    function setControlsDisabled(disabled) {
        var select = getSelect();
        var submit = getSubmitButton();
        var trigger = getOptionsTrigger();
        var menu = getOptionsMenu();

        if (select) {
            select.disabled = disabled;
        }

        if (submit) {
            submit.disabled = disabled || !select || !select.value;
        }

        if (trigger) {
            trigger.disabled = disabled;

            if (disabled) {
                trigger.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                trigger.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        }

        if (menu) {
            menu.querySelectorAll('[role="menuitem"]').forEach(function (item) {
                setMenuItemDisabled(item, disabled);
            });
        }
    }

    function updateSubmitEnabled() {
        var select = getSelect();
        var submit = getSubmitButton();

        if (!submit || isActionPending) {
            return;
        }

        submit.disabled = !select || select.disabled || !select.value;
    }

    function resetModalState() {
        var select = getSelect();

        clearError();
        setVisible(getLoadingEl(), false);
        setVisible(getEmptyEl(), false);
        setVisible(getSelectWrap(), true);

        if (select) {
            select.innerHTML = '<option value="">Selecciona una lista</option>';
            select.value = '';
            select.disabled = true;
        }

        if (getSubmitButton()) {
            getSubmitButton().disabled = true;
        }
    }

    function openOptionsMenu() {
        var menu = getOptionsMenu();
        var trigger = getOptionsTrigger();

        if (!menu) {
            return;
        }

        setVisible(menu, true);
        isMenuOpen = true;

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
    }

    function closeOptionsMenu() {
        var menu = getOptionsMenu();
        var trigger = getOptionsTrigger();

        if (!menu) {
            isMenuOpen = false;
            return;
        }

        setVisible(menu, false);
        isMenuOpen = false;

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleOptionsMenu() {
        if (isMenuOpen) {
            closeOptionsMenu();
            return;
        }

        openOptionsMenu();
    }

    /**
     * @param {Array} lists
     */
    function populateSelect(lists) {
        var select = getSelect();

        if (!select) {
            return;
        }

        select.innerHTML = '<option value="">Selecciona una lista</option>';

        lists.forEach(function (list) {
            if (!list || list.id === undefined || list.id === null) {
                return;
            }

            var option = document.createElement('option');
            var title = list.title ? String(list.title) : ('Lista #' + list.id);
            var updatedAt = list.updated_at ? String(list.updated_at) : '';

            option.value = String(list.id);
            option.textContent = updatedAt !== '' ? title + ' — ' + updatedAt : title;
            select.appendChild(option);
        });
    }

    function refreshListsAreaFeeds() {
        var feedApi = globalRoot.AAExecutableUserListsVisibleFeed;
        var board = globalRoot.AATasksBoard;
        var reloadPromise = Promise.resolve();

        if (feedApi && typeof feedApi.reload === 'function') {
            reloadPromise = Promise.resolve(feedApi.reload());
        }

        return reloadPromise.then(function () {
            if (board && typeof board.reload === 'function') {
                return board.reload({ silent: true }).catch(function () {});
            }

            return undefined;
        });
    }

    function loadArchivedListsIntoModal() {
        var service = getService();

        resetModalState();
        setVisible(getLoadingEl(), true);
        setControlsDisabled(true);

        if (!service || typeof service.getArchivedTaskLists !== 'function') {
            setVisible(getLoadingEl(), false);
            showError('Servicio no disponible.');
            setControlsDisabled(false);
            return Promise.resolve();
        }

        return service.getArchivedTaskLists()
            .then(function (data) {
                var lists = data && Array.isArray(data.lists) ? data.lists : [];

                setVisible(getLoadingEl(), false);

                if (lists.length === 0) {
                    setVisible(getEmptyEl(), true);
                    setVisible(getSelectWrap(), false);

                    if (getSelect()) {
                        getSelect().disabled = true;
                    }

                    if (getSubmitButton()) {
                        getSubmitButton().disabled = true;
                    }

                    setControlsDisabled(false);
                    return;
                }

                populateSelect(lists);

                if (getSelect()) {
                    getSelect().disabled = false;
                }

                updateSubmitEnabled();
                setControlsDisabled(false);
            })
            .catch(function (err) {
                setVisible(getLoadingEl(), false);
                showError((err && err.message) ? err.message : 'No se pudieron cargar las listas archivadas.');
                setControlsDisabled(false);
            });
    }

    function handleRestoreSubmit() {
        if (isActionPending) {
            return Promise.resolve();
        }

        var select = getSelect();
        var listId = select ? String(select.value || '').trim() : '';

        if (!listId) {
            return Promise.resolve();
        }

        var service = getService();

        if (!service || typeof service.restoreTaskList !== 'function') {
            showError('Servicio no disponible.');
            return Promise.resolve();
        }

        isActionPending = true;
        clearError();
        setControlsDisabled(true);

        return service.restoreTaskList(listId)
            .then(function () {
                closeModal(MODAL_ID);
                resetModalState();
                return refreshListsAreaFeeds();
            })
            .catch(function (err) {
                showError((err && err.message) ? err.message : 'No se pudo desarchivar la lista.');
            })
            .finally(function () {
                isActionPending = false;
                setControlsDisabled(false);
                updateSubmitEnabled();
            });
    }

    function handleRestoreToolClick(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        closeOptionsMenu();
        openModal(MODAL_ID);
        loadArchivedListsIntoModal();
    }

    function handleReturnIgnoredTasksClick(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (isActionPending) {
            return Promise.resolve();
        }

        closeOptionsMenu();

        var confirmFn = globalRoot.confirm;

        if (typeof confirmFn !== 'function' || !confirmFn(RETURN_IGNORED_CONFIRM_MESSAGE)) {
            return Promise.resolve();
        }

        var service = getService();

        if (!service || typeof service.returnIgnoredUserTasks !== 'function') {
            showAreaError('Servicio no disponible.');
            return Promise.resolve();
        }

        isActionPending = true;
        clearAreaError();
        setControlsDisabled(true);

        return service.returnIgnoredUserTasks()
            .then(function () {
                return refreshListsAreaFeeds();
            })
            .catch(function (err) {
                showAreaError((err && err.message) ? err.message : 'No se pudieron regresar las tareas ignoradas.');
            })
            .finally(function () {
                isActionPending = false;
                setControlsDisabled(false);
                updateSubmitEnabled();
            });
    }

    function handleDocumentClick(event) {
        var target = event.target;
        var restoreButton = target && target.closest
            ? target.closest(RESTORE_TOOL_SELECTOR)
            : null;
        var returnIgnoredButton = target && target.closest
            ? target.closest(RETURN_IGNORED_TOOL_SELECTOR)
            : null;
        var optionsTrigger = target && target.closest
            ? target.closest(OPTIONS_MENU_TRIGGER_SELECTOR)
            : null;
        var insideMenu = target && target.closest
            ? target.closest('#' + OPTIONS_MENU_ID)
            : null;
        var insideTools = target && target.closest
            ? target.closest('#aa-lists-area-tools')
            : null;

        if (optionsTrigger && !optionsTrigger.disabled) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            toggleOptionsMenu();
            return;
        }

        if (restoreButton && !restoreButton.disabled) {
            handleRestoreToolClick(event);
            return;
        }

        if (returnIgnoredButton && !returnIgnoredButton.disabled) {
            handleReturnIgnoredTasksClick(event);
            return;
        }

        if (isMenuOpen && !insideMenu && !insideTools) {
            closeOptionsMenu();
        }
    }

    function handleDocumentKeydown(event) {
        if (!event || event.key !== 'Escape' || !isMenuOpen) {
            return;
        }

        closeOptionsMenu();
    }

    function bindModalCloseHandlers() {
        document.querySelectorAll('[data-aa-tasks-modal-close="' + MODAL_ID + '"]').forEach(function (button) {
            if (button.dataset.listsAreaToolsCloseBound === '1') {
                return;
            }

            button.dataset.listsAreaToolsCloseBound = '1';
            button.addEventListener('click', function () {
                if (!isActionPending) {
                    closeModal(MODAL_ID);
                    resetModalState();
                }
            });
        });
    }

    function bindListsAreaTools() {
        if (isBound || !document.getElementById('aa-lists-area-tools')) {
            return;
        }

        isBound = true;

        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleDocumentKeydown);

        var select = getSelect();

        if (select && select.dataset.listsAreaToolsSelectBound !== '1') {
            select.dataset.listsAreaToolsSelectBound = '1';
            select.addEventListener('change', updateSubmitEnabled);
        }

        var submit = getSubmitButton();

        if (submit && submit.dataset.listsAreaToolsSubmitBound !== '1') {
            submit.dataset.listsAreaToolsSubmitBound = '1';
            submit.addEventListener('click', handleRestoreSubmit);
        }

        bindModalCloseHandlers();
    }

    function initListsAreaTools() {
        if (!document.getElementById('aa-tasks-module-root')) {
            return;
        }

        bindListsAreaTools();
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initListsAreaTools);
        } else {
            initListsAreaTools();
        }
    }

    var moduleExports = {
        MODAL_ID: MODAL_ID,
        OPTIONS_MENU_ID: OPTIONS_MENU_ID,
        RESTORE_TOOL_SELECTOR: RESTORE_TOOL_SELECTOR,
        RETURN_IGNORED_TOOL_SELECTOR: RETURN_IGNORED_TOOL_SELECTOR,
        RETURN_IGNORED_CONFIRM_MESSAGE: RETURN_IGNORED_CONFIRM_MESSAGE,
        openModal: openModal,
        closeModal: closeModal,
        openOptionsMenu: openOptionsMenu,
        closeOptionsMenu: closeOptionsMenu,
        toggleOptionsMenu: toggleOptionsMenu,
        resetModalState: resetModalState,
        populateSelect: populateSelect,
        loadArchivedListsIntoModal: loadArchivedListsIntoModal,
        handleRestoreSubmit: handleRestoreSubmit,
        handleRestoreToolClick: handleRestoreToolClick,
        handleReturnIgnoredTasksClick: handleReturnIgnoredTasksClick,
        refreshListsAreaFeeds: refreshListsAreaFeeds,
        refreshFeedsAfterRestore: refreshListsAreaFeeds,
        updateSubmitEnabled: updateSubmitEnabled,
        bindListsAreaTools: bindListsAreaTools,
        initListsAreaTools: initListsAreaTools
    };

    globalRoot.AAListsAreaTools = moduleExports;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }
})();
