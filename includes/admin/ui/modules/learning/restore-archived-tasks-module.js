/**
 * Restore Archived Tasks Module — modal por lista para desarchivar tareas user.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var MODAL_ID = 'aa-restore-archived-tasks-modal';
    var isActionPending = false;

    function getService() {
        return globalRoot.TasksService || null;
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

    function getModal() {
        return document.getElementById(MODAL_ID);
    }

    function getListIdInput() {
        return document.getElementById('aa-restore-archived-tasks-form-list-id');
    }

    function getSelect() {
        return document.getElementById('aa-restore-archived-tasks-select');
    }

    function getSubmitButton() {
        return document.getElementById('aa-restore-archived-tasks-submit');
    }

    function getLoadingEl() {
        return document.getElementById('aa-restore-archived-tasks-loading');
    }

    function getEmptyEl() {
        return document.getElementById('aa-restore-archived-tasks-empty');
    }

    function getErrorEl() {
        return document.getElementById('aa-restore-archived-tasks-error');
    }

    function getSelectWrap() {
        return document.getElementById('aa-restore-archived-tasks-select-wrap');
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

        if (modalId === MODAL_ID) {
            resetModalState();
        }
    }

    function clearError() {
        setVisible(getErrorEl(), false);

        if (getErrorEl()) {
            getErrorEl().textContent = '';
        }
    }

    function showError(message) {
        setVisible(getErrorEl(), true);

        if (getErrorEl()) {
            getErrorEl().textContent = message || 'No se pudo completar la acción.';
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

    function setControlsDisabled(disabled) {
        var select = getSelect();
        var submit = getSubmitButton();

        if (select) {
            select.disabled = disabled;
        }

        if (submit) {
            submit.disabled = disabled || !select || !select.value;
        }
    }

    function resetModalState() {
        var select = getSelect();
        var listIdInput = getListIdInput();

        clearError();
        setVisible(getLoadingEl(), false);
        setVisible(getEmptyEl(), false);
        setVisible(getSelectWrap(), true);

        if (listIdInput) {
            listIdInput.value = '';
        }

        if (select) {
            select.innerHTML = '<option value="">Selecciona una tarea</option>';
            select.value = '';
            select.disabled = true;
        }

        if (getSubmitButton()) {
            getSubmitButton().disabled = true;
        }
    }

    function closeListOptionsMenus() {
        document.querySelectorAll('.aa-executable-list-options-menu').forEach(function (menu) {
            setVisible(menu, false);
        });

        document.querySelectorAll('.aa-executable-list-options-trigger').forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });
    }

    function reloadAfterMutation() {
        var boardApi = globalRoot.AATasksBoard;
        var feedApi = globalRoot.AAExecutableUserListsVisibleFeed;
        var boardReload = boardApi && typeof boardApi.reload === 'function'
            ? boardApi.reload({ silent: true })
            : Promise.resolve();
        var feedReload = feedApi
            && typeof feedApi.isEnabled === 'function'
            && feedApi.isEnabled()
            && typeof feedApi.reload === 'function'
            ? feedApi.reload().catch(function () {})
            : Promise.resolve();

        return boardReload.then(function () {
            return feedReload;
        });
    }

    /**
     * @param {object} task
     * @returns {string}
     */
    function formatTaskOptionLabel(task) {
        if (!task || typeof task !== 'object') {
            return 'Tarea sin título';
        }

        var title = String(task.title || '').trim();

        if (title === '') {
            title = 'Tarea sin título';
        }

        var archivedAt = String(task.archived_at || '').trim();
        var status = String(task.status || '').trim().toLowerCase();
        var parts = [title];

        if (status === 'done') {
            parts.push('completada');
        }

        if (archivedAt !== '') {
            parts.push(archivedAt);
        }

        return parts.join(' — ');
    }

    /**
     * @param {Array} tasks
     */
    function populateSelect(tasks) {
        var select = getSelect();

        if (!select) {
            return;
        }

        select.innerHTML = '<option value="">Selecciona una tarea</option>';

        tasks.forEach(function (task) {
            if (!task || task.id === undefined || task.id === null) {
                return;
            }

            var option = document.createElement('option');
            option.value = String(task.id);
            option.textContent = formatTaskOptionLabel(task);
            select.appendChild(option);
        });
    }

    /**
     * @param {string} listId
     * @returns {Promise<void>}
     */
    function loadArchivedTasksIntoModal(listId) {
        var service = getService();

        resetModalState();

        if (getListIdInput()) {
            getListIdInput().value = listId;
        }

        setVisible(getLoadingEl(), true);
        setControlsDisabled(true);

        if (!service || typeof service.listArchivedTasksInList !== 'function') {
            setVisible(getLoadingEl(), false);
            showError('Servicio no disponible.');
            setControlsDisabled(false);
            return Promise.resolve();
        }

        return service.listArchivedTasksInList(listId)
            .then(function (data) {
                var tasks = data && Array.isArray(data.tasks) ? data.tasks : [];

                setVisible(getLoadingEl(), false);

                if (tasks.length === 0) {
                    setVisible(getEmptyEl(), true);
                    setVisible(getSelectWrap(), false);

                    if (getSelect()) {
                        getSelect().disabled = true;
                    }

                    if (getSubmitButton()) {
                        getSubmitButton().disabled = true;
                    }

                    return;
                }

                populateSelect(tasks);

                if (getSelect()) {
                    getSelect().disabled = false;
                }

                updateSubmitEnabled();
            })
            .catch(function (err) {
                setVisible(getLoadingEl(), false);
                showError((err && err.message) ? err.message : 'No se pudieron cargar las tareas archivadas.');
            })
            .finally(function () {
                if (!isActionPending) {
                    setControlsDisabled(false);
                    updateSubmitEnabled();
                }
            });
    }

    /**
     * @param {HTMLElement} button
     * @returns {Promise<void>}
     */
    function openRestoreModalFromButton(button) {
        var listId = String(button.getAttribute('data-list-id') || '').trim();

        if (!listId) {
            return Promise.resolve();
        }

        closeListOptionsMenus();
        clearError();
        openModal(MODAL_ID);

        return loadArchivedTasksIntoModal(listId);
    }

    function handleRestoreSubmit() {
        if (isActionPending) {
            return Promise.resolve();
        }

        var select = getSelect();
        var taskId = select ? String(select.value || '').trim() : '';

        if (!taskId) {
            return Promise.resolve();
        }

        var service = getService();

        if (!service || typeof service.restoreTask !== 'function') {
            showError('Servicio no disponible.');
            return Promise.resolve();
        }

        isActionPending = true;
        clearError();
        setControlsDisabled(true);

        return service.restoreTask(taskId)
            .then(function () {
                closeModal(MODAL_ID);
                return reloadAfterMutation();
            })
            .catch(function (err) {
                showError((err && err.message) ? err.message : 'No se pudo desarchivar la tarea.');
            })
            .finally(function () {
                isActionPending = false;
                setControlsDisabled(false);
                updateSubmitEnabled();
            });
    }

    function bindRestoreDelegation() {
        var root = document.getElementById('aa-tasks-module-root');

        if (!root || root.dataset.restoreArchivedTasksBound === '1') {
            return;
        }

        root.dataset.restoreArchivedTasksBound = '1';

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-aa-list-restore-archived-tasks]');

            if (!button || button.disabled) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            openRestoreModalFromButton(button);
        }, true);
    }

    function bindSubmitButton() {
        var submit = getSubmitButton();

        if (!submit || submit.dataset.bound === '1') {
            return;
        }

        submit.dataset.bound = '1';
        submit.addEventListener('click', function () {
            handleRestoreSubmit();
        });
    }

    function bindSelectChange() {
        var select = getSelect();

        if (!select || select.dataset.bound === '1') {
            return;
        }

        select.dataset.bound = '1';
        select.addEventListener('change', updateSubmitEnabled);
    }

    function bindModalClose() {
        document.querySelectorAll('[data-aa-tasks-modal-close="' + MODAL_ID + '"]').forEach(function (button) {
            if (button.dataset.restoreArchivedTasksCloseBound === '1') {
                return;
            }

            button.dataset.restoreArchivedTasksCloseBound = '1';
            button.addEventListener('click', function () {
                closeModal(MODAL_ID);
            });
        });
    }

    function initRestoreArchivedTasksModule() {
        if (!document.getElementById('aa-tasks-module-root')) {
            return;
        }

        bindRestoreDelegation();
        bindSubmitButton();
        bindSelectChange();
        bindModalClose();
    }

    var moduleExports = {
        MODAL_ID: MODAL_ID,
        openRestoreModalFromButton: openRestoreModalFromButton,
        loadArchivedTasksIntoModal: loadArchivedTasksIntoModal,
        handleRestoreSubmit: handleRestoreSubmit,
        closeModal: closeModal,
        resetModalState: resetModalState,
        formatTaskOptionLabel: formatTaskOptionLabel,
        reloadAfterMutation: reloadAfterMutation,
        closeListOptionsMenus: closeListOptionsMenus
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRestoreArchivedTasksModule);
    } else {
        initRestoreArchivedTasksModule();
    }
})();
