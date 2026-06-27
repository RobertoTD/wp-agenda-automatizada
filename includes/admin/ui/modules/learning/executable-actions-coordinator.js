/**
 * Executable Actions Coordinator — ejecución debug MC12A/12B/12C.
 *
 * MC12A: user tasks (complete, pending, archive-list).
 * MC12B: Learning simple (defer, dismiss, complete).
 * MC12C: Learning primary-handler vía LearningActionHandlers.
 * Delega clicks en #aa-executable-lists-root; no sustituye módulos legacy.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var isActionPending = false;
    var boundRoots = {};

    var ARCHIVE_LIST_CONFIRM_MESSAGE = '¿Archivar esta lista? Las tareas se conservarán.';
    var DELETE_LIST_CONFIRM_MESSAGE = 'Eliminar esta lista implica eliminar definitivamente la lista y todas sus tareas contenidas. Esta acción no se puede deshacer. Si solo deseas sacarla provisionalmente de tu estrategia operativa actual, puedes archivarla.';
    var ARCHIVE_TASK_CONFIRM_MESSAGE = '¿Archivar esta tarea? Dejará de aparecer en tus listas activas hasta que la restaures.';
    var DELETE_TASK_CONFIRM_MESSAGE = '¿Deseas realmente eliminar esta tarea? Esta acción no se puede deshacer. Si solo deseas sacarla provisionalmente de tu estrategia actual, puedes archivarla.';

    var TASK_ACTION_SELECTOR = '[data-tasks-action]';
    var LEARNING_ACTION_SELECTOR = '[data-learning-action]';
    var ACTIONABLE_BUTTON_SELECTOR = TASK_ACTION_SELECTOR + ', ' + LEARNING_ACTION_SELECTOR;

    var LEARNING_ACTIONS_MC12B = {
        defer: true,
        dismiss: true,
        complete: true
    };

    /**
     * @param {object} deps
     * @returns {object}
     */
    function createCoordinator(deps) {
        var resolveTasksService = deps.getTasksService || function () {
            return globalRoot.TasksService || null;
        };
        var resolveLearningService = deps.getLearningService || function () {
            return globalRoot.LearningService || null;
        };
        var resolveLearningActionHandlers = deps.getLearningActionHandlers || function () {
            return globalRoot.LearningActionHandlers || null;
        };
        var confirmFn = deps.confirm || function (message) {
            return globalRoot.confirm(message);
        };

        /**
         * @param {HTMLElement} root
         * @param {boolean} disabled
         */
        function setRootButtonsDisabled(root, disabled) {
            if (!root) {
                return;
            }

            root.querySelectorAll(ACTIONABLE_BUTTON_SELECTOR).forEach(function (button) {
                button.disabled = disabled;

                if (!button.classList) {
                    return;
                }

                if (disabled) {
                    button.classList.add('opacity-60', 'cursor-not-allowed');
                } else {
                    button.classList.remove('opacity-60', 'cursor-not-allowed');
                }
            });
        }

        /**
         * @param {MouseEvent|object} event
         */
        function stopEvent(event) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
        }

        /**
         * @param {string} action
         * @param {string} taskId
         * @returns {Promise<void>}
         */
        function runTaskStatusAction(action, taskId) {
            var service = resolveTasksService();
            var status = action === 'complete' ? 'done' : 'pending';

            if (!service || typeof service.changeTaskStatus !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.changeTaskStatus(taskId, status));
        }

        /**
         * @param {string} taskId
         * @returns {Promise<void>}
         */
        function runTaskDeferAction(taskId) {
            var service = resolveTasksService();

            if (!service || typeof service.deferTask !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.deferTask(taskId));
        }

        /**
         * @param {string} taskId
         * @returns {Promise<void>}
         */
        function runTaskDismissAction(taskId) {
            var service = resolveTasksService();

            if (!service || typeof service.dismissTask !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.dismissTask(taskId));
        }

        /**
         * @param {string} taskId
         * @returns {Promise<void>}
         */
        function runTaskMissedAction(taskId) {
            var service = resolveTasksService();

            if (!service || typeof service.markTaskMissed !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.markTaskMissed(taskId));
        }

        /**
         * @param {string} listId
         * @returns {Promise<void|null>}
         */
        function runArchiveListAction(listId) {
            if (!confirmFn(ARCHIVE_LIST_CONFIRM_MESSAGE)) {
                return Promise.resolve(null);
            }

            var service = resolveTasksService();

            if (!service || typeof service.archiveTaskList !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.archiveTaskList(listId));
        }

        /**
         * @param {string} listId
         * @returns {Promise<void|null>}
         */
        function runDeleteListAction(listId) {
            if (!confirmFn(DELETE_LIST_CONFIRM_MESSAGE)) {
                return Promise.resolve(null);
            }

            var service = resolveTasksService();

            if (!service || typeof service.deleteTaskList !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.deleteTaskList(listId));
        }

        /**
         * @param {string} taskId
         * @returns {Promise<void|null>}
         */
        function runArchiveTaskAction(taskId) {
            if (!confirmFn(ARCHIVE_TASK_CONFIRM_MESSAGE)) {
                return Promise.resolve(null);
            }

            var service = resolveTasksService();

            if (!service || typeof service.archiveTask !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.archiveTask(taskId));
        }

        /**
         * @param {string} taskId
         * @returns {Promise<void|null>}
         */
        function runDeleteTaskAction(taskId) {
            if (!confirmFn(DELETE_TASK_CONFIRM_MESSAGE)) {
                return Promise.resolve(null);
            }

            var service = resolveTasksService();

            if (!service || typeof service.deleteTask !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.deleteTask(taskId));
        }

        /**
         * @param {string} action
         * @param {string} recommendationKey
         * @returns {Promise<void>}
         */
        function runLearningAction(action, recommendationKey) {
            var service = resolveLearningService();

            if (!service) {
                return Promise.reject(new Error('LearningService no disponible.'));
            }

            if (action === 'defer') {
                if (typeof service.ignoreRecommendation !== 'function') {
                    return Promise.reject(new Error('LearningService no disponible.'));
                }

                return Promise.resolve(service.ignoreRecommendation(recommendationKey));
            }

            if (action === 'dismiss') {
                if (typeof service.dismissRecommendation !== 'function') {
                    return Promise.reject(new Error('LearningService no disponible.'));
                }

                return Promise.resolve(service.dismissRecommendation(recommendationKey));
            }

            if (action === 'complete') {
                if (typeof service.completeRecommendation !== 'function') {
                    return Promise.reject(new Error('LearningService no disponible.'));
                }

                return Promise.resolve(service.completeRecommendation(recommendationKey));
            }

            return Promise.reject(new Error('Acción Learning no soportada.'));
        }

        /**
         * @param {object} item
         * @param {string} handlerName
         * @returns {object|null}
         */
        function findHandlerVisibleAction(item, handlerName) {
            var visibleActions = Array.isArray(item.visible_actions) ? item.visible_actions : [];
            var index = 0;

            for (index = 0; index < visibleActions.length; index += 1) {
                var visibleAction = visibleActions[index];

                if (!visibleAction || visibleAction.type !== 'handler') {
                    continue;
                }

                if (asString(visibleAction.handler).trim() !== handlerName) {
                    continue;
                }

                return {
                    type: 'handler',
                    label: visibleAction.label || '',
                    handler: visibleAction.handler || ''
                };
            }

            return null;
        }

        /**
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @param {Promise<*>} actionPromise
         * @returns {Promise<boolean>}
         */
        function executeActionPromise(event, ctx, actionPromise) {
            stopEvent(event);

            if (isActionPending) {
                return Promise.resolve(true);
            }

            var root = ctx.root;

            isActionPending = true;
            setRootButtonsDisabled(root, true);

            if (typeof ctx.clearError === 'function') {
                ctx.clearError();
            }

            return actionPromise
                .then(function (result) {
                    if (result === null) {
                        return false;
                    }

                    if (typeof ctx.reload === 'function') {
                        return Promise.resolve(ctx.reload());
                    }

                    return undefined;
                })
                .then(function (didReload) {
                    return didReload !== false;
                })
                .catch(function (err) {
                    if (typeof ctx.showError === 'function') {
                        ctx.showError((err && err.message) ? err.message : 'No se pudo completar la acción.');
                    }

                    return true;
                })
                .finally(function () {
                    isActionPending = false;
                    setRootButtonsDisabled(root, false);
                });
        }

        /**
         * MC12C: handlers runtime — reload solo si result.reload === true.
         *
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @param {Promise<*>} actionPromise
         * @returns {Promise<boolean>}
         */
        function executeHandlerPromise(event, ctx, actionPromise) {
            stopEvent(event);

            if (isActionPending) {
                return Promise.resolve(true);
            }

            var root = ctx.root;

            isActionPending = true;
            setRootButtonsDisabled(root, true);

            if (typeof ctx.clearError === 'function') {
                ctx.clearError();
            }

            return actionPromise
                .then(function (result) {
                    if (result && result.reload === true && typeof ctx.reload === 'function') {
                        return Promise.resolve(ctx.reload());
                    }

                    return undefined;
                })
                .then(function () {
                    return true;
                })
                .catch(function (err) {
                    if (typeof ctx.showError === 'function') {
                        ctx.showError((err && err.message) ? err.message : 'No se pudo completar la acción.');
                    }

                    return true;
                })
                .finally(function () {
                    isActionPending = false;
                    setRootButtonsDisabled(root, false);
                });
        }

        /**
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @returns {Promise<boolean>}
         */
        function handlePrimaryHandlerClick(event, ctx) {
            var root = ctx && ctx.root;

            if (!root || !event || !event.target) {
                return Promise.resolve(false);
            }

            var button = event.target.closest
                ? event.target.closest(LEARNING_ACTION_SELECTOR)
                : null;

            if (!button || button.disabled || !root.contains(button)) {
                return Promise.resolve(false);
            }

            if (asString(button.getAttribute('data-learning-action')).trim().toLowerCase() !== 'primary-handler') {
                return Promise.resolve(false);
            }

            var recommendationKey = asString(button.getAttribute('data-recommendation-key')).trim();
            var handlerName = asString(button.getAttribute('data-learning-handler')).trim();

            if (recommendationKey === '' || handlerName === '') {
                return Promise.resolve(false);
            }

            stopEvent(event);

            if (isActionPending) {
                return Promise.resolve(true);
            }

            var findLearningItem = ctx.findLearningItem;
            var item = typeof findLearningItem === 'function'
                ? findLearningItem(recommendationKey)
                : null;
            var handlerAction = item ? findHandlerVisibleAction(item, handlerName) : null;
            var registry = resolveLearningActionHandlers();

            if (
                !item
                || !handlerAction
                || !registry
                || typeof registry.run !== 'function'
                || typeof registry.isAvailable !== 'function'
                || registry.isAvailable(handlerAction, item) !== true
            ) {
                return Promise.resolve(true);
            }

            var handlerCtx = {
                key: recommendationKey,
                item: item,
                reload: ctx.reload,
                showError: ctx.showError
            };

            return executeHandlerPromise(
                event,
                ctx,
                Promise.resolve(registry.run(handlerAction, item, handlerCtx))
            );
        }

        /**
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @returns {Promise<boolean>}
         */
        function handleTaskClick(event, ctx) {
            var root = ctx && ctx.root;

            if (!root || !event || !event.target) {
                return Promise.resolve(false);
            }

            var button = event.target.closest
                ? event.target.closest(TASK_ACTION_SELECTOR)
                : null;

            if (!button || button.disabled || !root.contains(button)) {
                return Promise.resolve(false);
            }

            var action = button.getAttribute('data-tasks-action');

            if (
                action !== 'complete'
                && action !== 'pending'
                && action !== 'defer'
                && action !== 'dismiss'
                && action !== 'missed'
                && action !== 'archive-list'
                && action !== 'delete-list'
                && action !== 'archive-task'
                && action !== 'delete-task'
            ) {
                return Promise.resolve(false);
            }

            var taskId = button.getAttribute('data-task-id');
            var listId = button.getAttribute('data-list-id');
            var actionPromise = null;

            if (action === 'complete' || action === 'pending') {
                if (!taskId) {
                    return Promise.resolve(false);
                }

                actionPromise = runTaskStatusAction(action, taskId);
            } else if (action === 'defer') {
                if (!taskId) {
                    return Promise.resolve(false);
                }

                actionPromise = runTaskDeferAction(taskId);
            } else if (action === 'dismiss') {
                if (!taskId) {
                    return Promise.resolve(false);
                }

                actionPromise = runTaskDismissAction(taskId);
            } else if (action === 'missed') {
                if (!taskId) {
                    return Promise.resolve(false);
                }

                actionPromise = runTaskMissedAction(taskId);
            } else if (action === 'archive-list') {
                if (!listId) {
                    return Promise.resolve(false);
                }

                actionPromise = runArchiveListAction(listId);
            } else if (action === 'delete-list') {
                if (!listId) {
                    return Promise.resolve(false);
                }

                actionPromise = runDeleteListAction(listId);
            } else if (action === 'archive-task') {
                if (!taskId) {
                    return Promise.resolve(false);
                }

                actionPromise = runArchiveTaskAction(taskId);
            } else if (action === 'delete-task') {
                if (!taskId) {
                    return Promise.resolve(false);
                }

                actionPromise = runDeleteTaskAction(taskId);
            }

            if (!actionPromise) {
                return Promise.resolve(false);
            }

            return executeActionPromise(event, ctx, actionPromise);
        }

        /**
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @returns {Promise<boolean>}
         */
        function handleLearningClick(event, ctx) {
            var root = ctx && ctx.root;

            if (!root || !event || !event.target) {
                return Promise.resolve(false);
            }

            var button = event.target.closest
                ? event.target.closest(LEARNING_ACTION_SELECTOR)
                : null;

            if (!button || button.disabled || !root.contains(button)) {
                return Promise.resolve(false);
            }

            var action = asString(button.getAttribute('data-learning-action')).trim().toLowerCase();

            if (action === 'primary-handler') {
                return handlePrimaryHandlerClick(event, ctx);
            }

            if (!LEARNING_ACTIONS_MC12B[action]) {
                return Promise.resolve(false);
            }

            var recommendationKey = asString(button.getAttribute('data-recommendation-key')).trim();

            if (recommendationKey === '') {
                return Promise.resolve(false);
            }

            return executeActionPromise(event, ctx, runLearningAction(action, recommendationKey));
        }

        /**
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @returns {boolean}
         */
        function isActionableTaskButton(event, ctx) {
            var root = ctx && ctx.root;

            if (!root || !event || !event.target || !event.target.closest) {
                return false;
            }

            var button = event.target.closest(TASK_ACTION_SELECTOR);

            if (!button || button.disabled || !root.contains(button)) {
                return false;
            }

            var action = button.getAttribute('data-tasks-action');

            return action === 'complete'
                || action === 'pending'
                || action === 'defer'
                || action === 'dismiss'
                || action === 'missed'
                || action === 'archive-list'
                || action === 'delete-list'
                || action === 'archive-task'
                || action === 'delete-task';
        }

        /**
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @returns {boolean}
         */
        function isPrimaryHandlerButton(event, ctx) {
            var root = ctx && ctx.root;

            if (!root || !event || !event.target || !event.target.closest) {
                return false;
            }

            var button = event.target.closest(LEARNING_ACTION_SELECTOR);

            if (!button || button.disabled || !root.contains(button)) {
                return false;
            }

            return asString(button.getAttribute('data-learning-action')).trim().toLowerCase() === 'primary-handler';
        }

        /**
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @returns {Promise<boolean>} true si manejó el click
         */
        function handleClick(event, ctx) {
            if (isActionPending) {
                stopEvent(event);
                return Promise.resolve(true);
            }

            if (isActionableTaskButton(event, ctx)) {
                return handleTaskClick(event, ctx);
            }

            if (isPrimaryHandlerButton(event, ctx)) {
                return handlePrimaryHandlerClick(event, ctx);
            }

            return handleLearningClick(event, ctx);
        }

        return {
            handleClick: handleClick,
            handleTaskClick: handleTaskClick,
            handleLearningClick: handleLearningClick,
            handlePrimaryHandlerClick: handlePrimaryHandlerClick,
            runTaskStatusAction: runTaskStatusAction,
            runTaskDeferAction: runTaskDeferAction,
            runTaskDismissAction: runTaskDismissAction,
            runArchiveListAction: runArchiveListAction,
            runDeleteListAction: runDeleteListAction,
            runArchiveTaskAction: runArchiveTaskAction,
            runDeleteTaskAction: runDeleteTaskAction,
            runLearningAction: runLearningAction,
            findHandlerVisibleAction: findHandlerVisibleAction,
            setRootButtonsDisabled: setRootButtonsDisabled,
            getIsActionPending: function () {
                return isActionPending;
            },
            resetPending: function () {
                isActionPending = false;
            }
        };
    }

    /**
     * @param {unknown} value
     * @returns {string}
     */
    function asString(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    var defaultCoordinator = createCoordinator({});

    /**
     * @param {HTMLElement|null|undefined} root
     * @returns {string}
     */
    function resolveRootKey(root) {
        if (!root) {
            return '';
        }

        var rootId = asString(root.id).trim();

        if (rootId !== '') {
            return rootId;
        }

        return '__anonymous_root__';
    }

    /**
     * @param {object} options root, reload, showError, clearError, findLearningItem
     */
    function init(options) {
        var opts = options || {};
        var root = opts.root;
        var rootKey = resolveRootKey(root);

        if (!root || rootKey === '' || boundRoots[rootKey]) {
            return;
        }

        boundRoots[rootKey] = true;

        var ctx = {
            root: root,
            reload: opts.reload,
            showError: opts.showError,
            clearError: opts.clearError,
            findLearningItem: opts.findLearningItem
        };

        root.addEventListener('click', function (event) {
            defaultCoordinator.handleClick(event, ctx);
        }, true);
    }

    /**
     * @param {HTMLElement|null|undefined} [root]
     * @returns {boolean}
     */
    function isDelegationBound(root) {
        if (!root) {
            return Object.keys(boundRoots).length > 0;
        }

        return !!boundRoots[resolveRootKey(root)];
    }

    function resetBinding() {
        boundRoots = {};
        isActionPending = false;
    }

    var api = {
        init: init,
        createCoordinator: createCoordinator,
        ARCHIVE_LIST_CONFIRM_MESSAGE: ARCHIVE_LIST_CONFIRM_MESSAGE,
        DELETE_LIST_CONFIRM_MESSAGE: DELETE_LIST_CONFIRM_MESSAGE,
        ARCHIVE_TASK_CONFIRM_MESSAGE: ARCHIVE_TASK_CONFIRM_MESSAGE,
        DELETE_TASK_CONFIRM_MESSAGE: DELETE_TASK_CONFIRM_MESSAGE,
        TASK_ACTION_SELECTOR: TASK_ACTION_SELECTOR,
        LEARNING_ACTION_SELECTOR: LEARNING_ACTION_SELECTOR,
        ACTIONABLE_BUTTON_SELECTOR: ACTIONABLE_BUTTON_SELECTOR,
        isDelegationBound: isDelegationBound,
        resetBinding: resetBinding
    };

    globalRoot.ExecutableActionsCoordinator = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
