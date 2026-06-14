/**
 * Executive Proposal Module — orquestación de Propuesta ejecutiva (MC2/MC3).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var isLoading = false;
    var isActionPending = false;
    var EXECUTIVE_ACTION_SELECTOR = '[data-executive-action]';

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

    function getService() {
        return globalRoot.AAExecutiveProposalService || null;
    }

    function getRenderer() {
        return globalRoot.AAExecutiveProposalRenderer || null;
    }

    function getHandlers() {
        return globalRoot.LearningActionHandlers || null;
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

    /**
     * @returns {Promise<void>}
     */
    function syncListsAfterExecutiveAction() {
        var board = globalRoot.AATasksBoard;
        var feedApi = globalRoot.AAExecutableUserListsVisibleFeed;
        var boardReload = board && typeof board.reload === 'function'
            ? board.reload({ silent: true, skipExecutiveProposal: true })
            : Promise.resolve();
        var feedReload = feedApi
            && typeof feedApi.isEnabled === 'function'
            && feedApi.isEnabled()
            && typeof feedApi.reloadFeedOnly === 'function'
            ? feedApi.reloadFeedOnly().catch(function () {})
            : feedApi
                && typeof feedApi.isEnabled === 'function'
                && feedApi.isEnabled()
                && typeof feedApi.reload === 'function'
                ? feedApi.reload().catch(function () {})
                : Promise.resolve();

        return boardReload.then(function () {
            return feedReload;
        });
    }

    function setExecutiveButtonsDisabled(disabled) {
        var root = document.getElementById('aa-executive-proposal');

        if (!root) {
            return;
        }

        root.querySelectorAll(EXECUTIVE_ACTION_SELECTOR).forEach(function (button) {
            button.disabled = disabled;

            if (disabled) {
                button.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    }

    function showProposalError(message) {
        var errorEl = document.getElementById('aa-executive-proposal-error');
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo cargar la propuesta ejecutiva.';
        }
    }

    function clearProposalError() {
        setVisible(document.getElementById('aa-executive-proposal-error'), false);
    }

    /**
     * @param {object|null} clientAction
     * @returns {Promise<void>}
     */
    function runClientAction(clientAction) {
        if (!clientAction || typeof clientAction !== 'object') {
            return Promise.resolve();
        }

        var type = String(clientAction.type || '');

        if (type === 'navigate') {
            var url = String(clientAction.url || '').trim();

            if (url !== '') {
                globalRoot.location.href = url;
            }

            return Promise.resolve();
        }

        if (type === 'handler') {
            var handlers = getHandlers();
            var handlerName = String(clientAction.handler || '').trim();
            var originKey = String(clientAction.origin_key || '').trim();
            var taskId = String(clientAction.task_id || '').trim();
            var source = String(clientAction.source || 'system').trim() || 'system';
            var label = String(clientAction.label || '').trim();

            if (!handlers || typeof handlers.run !== 'function' || handlerName === '') {
                return Promise.resolve();
            }

            var item = {
                id: taskId,
                origin_key: originKey,
                source: source,
                primary_action: {
                    type: 'handler',
                    label: label,
                    handler: handlerName
                },
                visible_actions: [{
                    type: 'handler',
                    label: label,
                    handler: handlerName
                }]
            };
            var action = {
                type: 'handler',
                label: label,
                handler: handlerName
            };

            if (typeof handlers.isAvailable === 'function' && handlers.isAvailable(action, item) !== true) {
                return Promise.resolve();
            }

            return Promise.resolve(handlers.run(action, item, {
                key: originKey,
                item: item,
                showError: showProposalError
            })).catch(function (err) {
                showProposalError((err && err.message) ? err.message : 'No se pudo ejecutar la acción.');
            });
        }

        return Promise.resolve();
    }

    /**
     * @param {{silent?:boolean}} [options]
     * @returns {Promise<void>}
     */
    function loadProposal(options) {
        var opts = options || {};
        var silent = opts.silent === true;
        var service = getService();
        var renderer = getRenderer();
        var loadingEl = document.getElementById('aa-executive-proposal-loading');
        var root = document.getElementById('aa-executive-proposal');

        if (!root) {
            return Promise.resolve();
        }

        if (!service || typeof service.getExecutiveProposal !== 'function') {
            showProposalError('No se pudo inicializar el servicio de propuesta ejecutiva.');
            return Promise.resolve();
        }

        if (!renderer || typeof renderer.renderProposal !== 'function') {
            showProposalError('No se pudo inicializar el renderer de propuesta ejecutiva.');
            return Promise.resolve();
        }

        if (isLoading) {
            return Promise.resolve();
        }

        isLoading = true;

        if (!silent) {
            setVisible(loadingEl, true);
        }

        clearProposalError();

        return service.getExecutiveProposal()
            .then(function (payload) {
                renderer.renderProposal(payload);
            })
            .catch(function (err) {
                if (!silent) {
                    showProposalError((err && err.message) ? err.message : 'No se pudo cargar la propuesta ejecutiva.');
                }
            })
            .finally(function () {
                isLoading = false;
                setVisible(loadingEl, false);
            });
    }

    /**
     * @param {HTMLElement} button
     * @returns {Promise<void>}
     */
    function handleExecutiveActionClick(button) {
        var service = getService();
        var renderer = getRenderer();
        var taskId = button.getAttribute('data-executive-task-id') || '';
        var actionKey = button.getAttribute('data-executive-action-key') || '';

        if (!service || typeof service.postExecutiveAction !== 'function') {
            showProposalError('Servicio de acciones ejecutivas no disponible.');
            return Promise.resolve();
        }

        if (!renderer || typeof renderer.renderProposal !== 'function') {
            showProposalError('Renderer de propuesta ejecutiva no disponible.');
            return Promise.resolve();
        }

        if (taskId === '' || actionKey === '') {
            showProposalError('No se pudo identificar la acción ejecutiva.');
            return Promise.resolve();
        }

        if (isActionPending) {
            return Promise.resolve();
        }

        isActionPending = true;
        setExecutiveButtonsDisabled(true);
        clearProposalError();

        return service.postExecutiveAction({
            taskId: taskId,
            actionKey: actionKey
        })
            .then(function (response) {
                if (response.proposal && typeof response.proposal === 'object') {
                    renderer.renderProposal(response.proposal);
                }

                return runClientAction(response.client_action).then(function () {
                    if (!response.action || response.action.mutated !== true) {
                        return undefined;
                    }

                    return syncListsAfterExecutiveAction();
                });
            })
            .catch(function (err) {
                showProposalError((err && err.message) ? err.message : 'No se pudo ejecutar la acción ejecutiva.');
            })
            .finally(function () {
                isActionPending = false;
                setExecutiveButtonsDisabled(false);
            });
    }

    function bindExecutiveDelegation() {
        var root = document.getElementById('aa-executive-proposal');

        if (!root) {
            return;
        }

        root.addEventListener('click', function (event) {
            if (!event || !event.target || typeof event.target.closest !== 'function') {
                return;
            }

            var button = event.target.closest(EXECUTIVE_ACTION_SELECTOR);

            if (!button || button.disabled || !root.contains(button)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            handleExecutiveActionClick(button);
        });
    }

    function initExecutiveProposalModule() {
        bindExecutiveDelegation();
        loadProposal();
    }

    globalRoot.AAExecutiveProposal = {
        reload: loadProposal
    };

    var moduleExports = {
        loadProposal: loadProposal,
        reloadExecutiveProposalBestEffort: reloadExecutiveProposalBestEffort,
        handleExecutiveActionClick: handleExecutiveActionClick,
        runClientAction: runClientAction,
        syncListsAfterExecutiveAction: syncListsAfterExecutiveAction
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExecutiveProposalModule);
    } else {
        initExecutiveProposalModule();
    }
})();
