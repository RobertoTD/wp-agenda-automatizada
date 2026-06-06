/**
 * Executable Actions Coordinator — ejecución debug MC12A (user tasks only).
 *
 * Delega clicks en #aa-executable-lists-root; no sustituye módulos legacy.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var isActionPending = false;
    var isDelegationBound = false;
    var boundRoot = null;

    var ARCHIVE_CONFIRM_MESSAGE = '¿Archivar esta lista? Las tareas se conservarán.';

    var TASK_ACTION_SELECTOR = '[data-tasks-action]';

    /**
     * @param {object} deps
     * @returns {object}
     */
    function createCoordinator(deps) {
        var resolveTasksService = deps.getTasksService || function () {
            return globalRoot.TasksService || null;
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

            root.querySelectorAll(TASK_ACTION_SELECTOR).forEach(function (button) {
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
         * @param {string} listId
         * @returns {Promise<void>}
         */
        function runArchiveListAction(listId) {
            if (!confirmFn(ARCHIVE_CONFIRM_MESSAGE)) {
                return Promise.resolve(null);
            }

            var service = resolveTasksService();

            if (!service || typeof service.archiveTaskList !== 'function') {
                return Promise.reject(new Error('TasksService no disponible.'));
            }

            return Promise.resolve(service.archiveTaskList(listId));
        }

        /**
         * @param {MouseEvent|object} event
         * @param {object} ctx
         * @returns {Promise<boolean>} true si manejó el click
         */
        function handleClick(event, ctx) {
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

            if (action !== 'complete' && action !== 'pending' && action !== 'archive-list') {
                return Promise.resolve(false);
            }

            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            if (isActionPending) {
                return Promise.resolve(true);
            }

            var taskId = button.getAttribute('data-task-id');
            var listId = button.getAttribute('data-list-id');
            var actionPromise = null;

            if (action === 'complete' || action === 'pending') {
                if (!taskId) {
                    return Promise.resolve(true);
                }

                actionPromise = runTaskStatusAction(action, taskId);
            } else if (action === 'archive-list') {
                if (!listId) {
                    return Promise.resolve(true);
                }

                actionPromise = runArchiveListAction(listId);
            }

            if (!actionPromise) {
                return Promise.resolve(true);
            }

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

        return {
            handleClick: handleClick,
            runTaskStatusAction: runTaskStatusAction,
            runArchiveListAction: runArchiveListAction,
            setRootButtonsDisabled: setRootButtonsDisabled,
            getIsActionPending: function () {
                return isActionPending;
            },
            resetPending: function () {
                isActionPending = false;
            }
        };
    }

    var defaultCoordinator = createCoordinator({});

    /**
     * @param {object} options root, reload, showError, clearError
     */
    function init(options) {
        var opts = options || {};
        var root = opts.root;

        if (!root || isDelegationBound) {
            return;
        }

        isDelegationBound = true;
        boundRoot = root;

        var ctx = {
            root: root,
            reload: opts.reload,
            showError: opts.showError,
            clearError: opts.clearError
        };

        root.addEventListener('click', function (event) {
            defaultCoordinator.handleClick(event, ctx);
        }, true);
    }

    var api = {
        init: init,
        createCoordinator: createCoordinator,
        ARCHIVE_CONFIRM_MESSAGE: ARCHIVE_CONFIRM_MESSAGE,
        isDelegationBound: function () {
            return isDelegationBound;
        },
        resetBinding: function () {
            isDelegationBound = false;
            boundRoot = null;
            isActionPending = false;
        }
    };

    globalRoot.ExecutableActionsCoordinator = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
