/**
 * Tasks Board Module — orquestación UI de listas/tareas manuales.
 *
 * Consume TasksService; no calcula prioridad localmente.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var isActionPending = false;
    var lastBoardPayload = null;

    function getService() {
        return window.TasksService || null;
    }

    function getRenderer() {
        return window.AATaskBoardRenderer || null;
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

    function showBoardError(message) {
        var errorEl = document.getElementById('aa-tasks-error');
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo completar la acción.';
        }
    }

    function clearBoardError() {
        setVisible(document.getElementById('aa-tasks-error'), false);
    }

    function setBoardDisabled(disabled) {
        document.querySelectorAll(
            '#aa-tasks-module-root [data-tasks-action], #aa-tasks-new-list, #aa-tasks-new-task'
        ).forEach(function (button) {
            button.disabled = disabled;

            if (disabled) {
                button.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    }

    /**
     * @returns {Promise<void>}
     */
    function reloadExecutiveProposalBestEffort() {
        var api = globalRoot.AAExecutiveProposal;

        if (api && typeof api.reload === 'function') {
            return api.reload({ silent: true }).catch(function () {});
        }

        return Promise.resolve();
    }

    function hasUserLists() {
        return lastBoardPayload
            && Array.isArray(lastBoardPayload.lists)
            && lastBoardPayload.lists.length > 0;
    }

    function populateTaskListSelect() {
        var select = document.getElementById('aa-task-form-list-id');

        if (!select) {
            return;
        }

        var lists = lastBoardPayload && Array.isArray(lastBoardPayload.lists)
            ? lastBoardPayload.lists
            : [];
        var renderer = getRenderer();
        var ordered = renderer && typeof renderer.resolveListOrder === 'function'
            ? renderer.resolveListOrder(lists, (lastBoardPayload && lastBoardPayload.organization) || {})
            : lists;

        select.innerHTML = '';

        if (ordered.length === 0) {
            var emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'No hay listas disponibles';
            select.appendChild(emptyOption);
            select.disabled = true;
            return;
        }

        select.disabled = false;

        ordered.forEach(function (list) {
            var option = document.createElement('option');
            option.value = String(list.id);
            option.textContent = list.title || ('Lista #' + list.id);
            select.appendChild(option);
        });
    }

    /**
     * @param {{lists:Array,tasks:Array,organization:Object}} data
     */
    function renderBoardPayload(data) {
        var emptyEl = document.getElementById('aa-tasks-empty');
        var listsRoot = document.getElementById('aa-tasks-lists-root');
        var renderer = getRenderer();

        if (!listsRoot) {
            return;
        }

        var lists = data.lists || [];
        var hasLists = lists.length > 0;

        setVisible(emptyEl, !hasLists);
        setVisible(listsRoot, hasLists);

        if (!renderer || typeof renderer.renderBoard !== 'function') {
            listsRoot.innerHTML = '';
            showBoardError('No se pudo inicializar el renderer del tablero.');
            return;
        }

        listsRoot.innerHTML = hasLists ? renderer.renderBoard(data) : '';
        populateTaskListSelect();
    }

    /**
     * @param {{silent?:boolean}} [options]
     * @returns {Promise<void>}
     */
    function reloadBoardAfterMutation(options) {
        var opts = options || { silent: true };

        return loadBoard(opts).then(function () {
            return reloadExecutableUserFeedBestEffort().then(function () {
                if (opts.skipExecutiveProposal === true) {
                    return undefined;
                }

                return reloadExecutiveProposalBestEffort();
            });
        });
    }

    /**
     * MC13B: refresca feed executable user si el flag visible está activo (best-effort, silent).
     *
     * @returns {Promise<void>}
     */
    function reloadExecutableUserFeedBestEffort() {
        var api = globalRoot.AAExecutableUserListsVisibleFeed;

        if (!api || typeof api.isEnabled !== 'function' || !api.isEnabled()) {
            return Promise.resolve();
        }

        if (typeof api.reload !== 'function') {
            return Promise.resolve();
        }

        return api.reload().catch(function () {});
    }

    /**
     * @param {{silent?:boolean}} [options]
     * @returns {Promise<void>}
     */
    function loadBoard(options) {
        var opts = options || {};
        var silent = opts.silent === true;
        var skipExecutiveProposal = opts.skipExecutiveProposal === true;
        var service = getService();
        var loadingEl = document.getElementById('aa-tasks-loading');
        var root = document.getElementById('aa-tasks-board-root');

        if (!root) {
            return Promise.resolve();
        }

        if (!service || typeof service.getTaskBoard !== 'function') {
            setVisible(loadingEl, false);
            showBoardError('No se pudo inicializar el servicio de tareas.');
            return Promise.resolve();
        }

        if (!silent) {
            setVisible(loadingEl, true);
        }

        clearBoardError();

        return service.getTaskBoard()
            .then(function (data) {
                setVisible(loadingEl, false);
                lastBoardPayload = data;
                renderBoardPayload(data);
            })
            .catch(function (err) {
                setVisible(loadingEl, false);
                renderBoardPayload({ lists: [], tasks: [], organization: {} });
                showBoardError((err && err.message) ? err.message : 'No se pudo cargar el tablero.');
                console.error('[Tasks Board Module]', err);
            });
    }

    function openModal(modalId) {
        var modal = document.getElementById(modalId);

        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeModal(modalId) {
        var modal = document.getElementById(modalId);

        if (modal) {
            modal.classList.add('hidden');
        }

        if (modalId === 'aa-task-modal') {
            collapseTaskFormOptions();
        }
    }

    function collapseTaskFormOptions() {
        var options = document.getElementById('aa-task-form-options');

        if (options) {
            options.open = false;
        }

        var bucketSelect = document.getElementById('aa-task-form-default-bucket');

        if (bucketSelect) {
            bucketSelect.value = 'primary';
        }
    }

    function resetListForm() {
        var form = document.getElementById('aa-task-list-form');

        if (form) {
            form.reset();
        }

        setVisible(document.getElementById('aa-task-list-form-error'), false);
    }

    function resetTaskForm() {
        var form = document.getElementById('aa-task-form');

        if (form) {
            form.reset();
        }

        populateTaskListSelect();
        collapseTaskFormOptions();
        setVisible(document.getElementById('aa-task-form-error'), false);
    }

    function showFormError(elementId, message) {
        var errorEl = document.getElementById(elementId);
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo guardar.';
        }
    }

    function submitListForm(event) {
        event.preventDefault();

        if (isActionPending) {
            return;
        }

        var service = getService();
        var titleInput = document.getElementById('aa-task-list-form-title');
        var descriptionInput = document.getElementById('aa-task-list-form-description');
        var importanceInput = document.getElementById('aa-task-list-form-importance');
        var title = titleInput ? String(titleInput.value || '').trim() : '';

        if (!title) {
            showFormError('aa-task-list-form-error', 'El título de la lista es obligatorio.');
            return;
        }

        if (!service || typeof service.createTaskList !== 'function') {
            showFormError('aa-task-list-form-error', 'Servicio no disponible.');
            return;
        }

        isActionPending = true;
        setBoardDisabled(true);
        setVisible(document.getElementById('aa-task-list-form-error'), false);

        service.createTaskList({
            title: title,
            description: descriptionInput ? descriptionInput.value : '',
            importance: importanceInput && importanceInput.value !== '' ? importanceInput.value : 0
        })
            .then(function () {
                closeModal('aa-task-list-modal');
                resetListForm();
                return reloadBoardAfterMutation({ silent: true });
            })
            .catch(function (err) {
                showFormError('aa-task-list-form-error', (err && err.message) ? err.message : 'No se pudo crear la lista.');
            })
            .finally(function () {
                isActionPending = false;
                setBoardDisabled(false);
            });
    }

    function normalizeDueAtInput(value) {
        var raw = value ? String(value).trim() : '';

        if (!raw) {
            return '';
        }

        if (raw.indexOf('T') !== -1) {
            var parts = raw.split('T');

            if (parts.length === 2) {
                var time = parts[1].length === 5 ? parts[1] + ':00' : parts[1];
                return parts[0] + ' ' + time;
            }
        }

        return raw;
    }

    function submitTaskForm(event) {
        event.preventDefault();

        if (isActionPending) {
            return;
        }

        var service = getService();
        var listSelect = document.getElementById('aa-task-form-list-id');
        var titleInput = document.getElementById('aa-task-form-title');
        var notesInput = document.getElementById('aa-task-form-notes');
        var dueInput = document.getElementById('aa-task-form-due-at');
        var importanceInput = document.getElementById('aa-task-form-importance');
        var bucketSelect = document.getElementById('aa-task-form-default-bucket');
        var listId = listSelect ? String(listSelect.value || '').trim() : '';
        var title = titleInput ? String(titleInput.value || '').trim() : '';

        if (!listId) {
            showFormError('aa-task-form-error', 'Selecciona una lista o crea una primero.');
            return;
        }

        if (!title) {
            showFormError('aa-task-form-error', 'El título de la tarea es obligatorio.');
            return;
        }

        if (!service || typeof service.createTask !== 'function') {
            showFormError('aa-task-form-error', 'Servicio no disponible.');
            return;
        }

        isActionPending = true;
        setBoardDisabled(true);
        setVisible(document.getElementById('aa-task-form-error'), false);

        var createPayload = {
            list_id: listId,
            title: title,
            notes: notesInput ? notesInput.value : '',
            due_at: normalizeDueAtInput(dueInput ? dueInput.value : ''),
            importance: importanceInput && importanceInput.value !== '' ? importanceInput.value : 0
        };
        var defaultBucket = bucketSelect ? String(bucketSelect.value || 'primary').trim() : 'primary';

        if (defaultBucket === 'secondary') {
            createPayload.default_bucket = 'secondary';
        }

        service.createTask(createPayload)
            .then(function () {
                closeModal('aa-task-modal');
                resetTaskForm();
                return reloadBoardAfterMutation({ silent: true });
            })
            .catch(function (err) {
                showFormError('aa-task-form-error', (err && err.message) ? err.message : 'No se pudo crear la tarea.');
            })
            .finally(function () {
                isActionPending = false;
                setBoardDisabled(false);
            });
    }

    /**
     * @param {string} action
     * @param {string} taskId
     */
    function runTaskStatusAction(action, taskId) {
        if (isActionPending || !taskId) {
            return;
        }

        var service = getService();
        var status = action === 'complete' ? 'done' : 'pending';

        if (!service || typeof service.changeTaskStatus !== 'function') {
            showBoardError('Servicio no disponible.');
            return;
        }

        isActionPending = true;
        setBoardDisabled(true);

        service.changeTaskStatus(taskId, status)
            .then(function () {
                return reloadBoardAfterMutation({ silent: true });
            })
            .catch(function (err) {
                showBoardError((err && err.message) ? err.message : 'No se pudo actualizar la tarea.');
            })
            .finally(function () {
                isActionPending = false;
                setBoardDisabled(false);
            });
    }

    /**
     * @param {string} listId
     */
    function runArchiveListAction(listId) {
        if (isActionPending || !listId) {
            return;
        }

        if (!window.confirm('¿Archivar esta lista? Las tareas se conservarán.')) {
            return;
        }

        var service = getService();

        if (!service || typeof service.archiveTaskList !== 'function') {
            showBoardError('Servicio no disponible.');
            return;
        }

        isActionPending = true;
        setBoardDisabled(true);

        service.archiveTaskList(listId)
            .then(function () {
                return reloadBoardAfterMutation({ silent: true });
            })
            .catch(function (err) {
                showBoardError((err && err.message) ? err.message : 'No se pudo archivar la lista.');
            })
            .finally(function () {
                isActionPending = false;
                setBoardDisabled(false);
            });
    }

    function bindBoardDelegation() {
        var root = document.getElementById('aa-tasks-module-root');

        if (!root || root.dataset.tasksActionsBound === '1') {
            return;
        }

        root.dataset.tasksActionsBound = '1';

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-tasks-action]');

            if (!button || button.disabled) {
                return;
            }

            var action = button.getAttribute('data-tasks-action');

            if (action === 'complete' || action === 'pending') {
                event.preventDefault();
                runTaskStatusAction(action, button.getAttribute('data-task-id'));
                return;
            }

            if (action === 'archive-list') {
                event.preventDefault();
                runArchiveListAction(button.getAttribute('data-list-id'));
            }
        });
    }

    function bindModals() {
        var newListBtn = document.getElementById('aa-tasks-new-list');
        var newTaskBtn = document.getElementById('aa-tasks-new-task');
        var listForm = document.getElementById('aa-task-list-form');
        var taskForm = document.getElementById('aa-task-form');

        if (newListBtn) {
            newListBtn.addEventListener('click', function () {
                clearBoardError();
                resetListForm();
                openModal('aa-task-list-modal');
            });
        }

        if (newTaskBtn) {
            newTaskBtn.addEventListener('click', function () {
                if (!hasUserLists()) {
                    showBoardError('Crea una lista primero para poder agregar tareas.');
                    return;
                }

                clearBoardError();
                resetTaskForm();
                openModal('aa-task-modal');
            });
        }

        if (listForm) {
            listForm.addEventListener('submit', submitListForm);
        }

        if (taskForm) {
            taskForm.addEventListener('submit', submitTaskForm);
        }

        document.querySelectorAll('[data-aa-tasks-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                var modalId = button.getAttribute('data-aa-tasks-modal-close');
                closeModal(modalId);
            });
        });
    }

    function initTasksBoardModule() {
        bindBoardDelegation();
        bindModals();
        loadBoard();
    }

    globalRoot.AATasksBoard = {
        reload: function (options) {
            return loadBoard(options || { silent: true });
        }
    };

    var moduleExports = {
        reloadExecutableUserFeedBestEffort: reloadExecutableUserFeedBestEffort,
        reloadBoardAfterMutation: reloadBoardAfterMutation,
        reloadExecutiveProposalBestEffort: reloadExecutiveProposalBestEffort
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTasksBoardModule);
    } else {
        initTasksBoardModule();
    }
})();
