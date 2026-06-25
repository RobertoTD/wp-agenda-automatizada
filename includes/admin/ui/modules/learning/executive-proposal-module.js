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
    var EXECUTIVE_FOCUS_ACTION_SELECTOR = '[data-executive-focus-action]';
    var lastProposalPayload = null;
    var sprintWatchTimer = null;
    var workZone = 'executive';
    var choosingMode = false;
    var pendingWorkZoneRender = false;
    var pendingWorkZoneRenderId = null;

    function resolveUiMode() {
        if (workZone === 'organizing') {
            return 'organizing';
        }

        if (choosingMode) {
            return 'choosing';
        }

        return null;
    }

    function renderProposalPayload(payload) {
        var renderer = getRenderer();

        if (!renderer || typeof renderer.renderProposal !== 'function') {
            return;
        }

        renderer.renderProposal(payload, { uiMode: resolveUiMode() });
    }

    function scheduleWorkZoneRender() {
        if (pendingWorkZoneRender) {
            return;
        }

        pendingWorkZoneRender = true;
        var schedule = typeof globalRoot.requestAnimationFrame === 'function'
            ? globalRoot.requestAnimationFrame.bind(globalRoot)
            : function (callback) {
                return setTimeout(callback, 0);
            };

        pendingWorkZoneRenderId = schedule(function () {
            pendingWorkZoneRender = false;
            pendingWorkZoneRenderId = null;
            renderProposalPayload(lastProposalPayload);
        });
    }

    function flushPendingWorkZoneRender() {
        if (!pendingWorkZoneRender || pendingWorkZoneRenderId == null) {
            return;
        }

        var cancel = typeof globalRoot.cancelAnimationFrame === 'function'
            ? globalRoot.cancelAnimationFrame.bind(globalRoot)
            : clearTimeout;

        cancel(pendingWorkZoneRenderId);
        pendingWorkZoneRender = false;
        pendingWorkZoneRenderId = null;
        renderProposalPayload(lastProposalPayload);
    }

    function setChoosingMode(active) {
        choosingMode = !!active;
        renderProposalPayload(lastProposalPayload);
    }

    function setWorkZone(zone) {
        var nextZone = zone === 'organizing' ? 'organizing' : 'executive';

        if (nextZone === workZone) {
            return;
        }

        workZone = nextZone;

        if (workZone === 'executive') {
            choosingMode = false;
        }

        scheduleWorkZoneRender();
    }

    function isSprintActive(payload) {
        return !!(payload
            && payload.meta
            && payload.meta.sprint
            && payload.meta.sprint.sprint_active === true);
    }

    function afterExecutiveActionSuccess(response) {
        var proposal = response && response.proposal ? response.proposal : null;
        var actionKey = response && response.action ? String(response.action.key || '') : '';

        if (!proposal) {
            return;
        }

        if (isSprintActive(proposal)) {
            choosingMode = false;
        } else if (actionKey === 'dismiss') {
            choosingMode = true;
        }

        lastProposalPayload = proposal;
        renderProposalPayload(proposal);
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

    function setFocusButtonsDisabled(disabled) {
        var root = document.getElementById('aa-executive-proposal');

        if (!root) {
            return;
        }

        root.querySelectorAll(EXECUTIVE_FOCUS_ACTION_SELECTOR).forEach(function (button) {
            button.disabled = disabled;

            if (disabled) {
                button.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    }

    /**
     * @param {HTMLElement} button
     * @param {boolean} pending
     */
    function setExecutiveButtonPending(button, pending) {
        if (!button) {
            return;
        }

        button.disabled = !!pending;

        if (pending) {
            button.classList.add('opacity-60', 'cursor-not-allowed');
        } else {
            button.classList.remove('opacity-60', 'cursor-not-allowed');
        }
    }

    /**
     * @param {HTMLElement} button
     */
    function clearExecutiveButtonPendingIfPresent(button) {
        if (!button || typeof document === 'undefined' || typeof document.contains !== 'function') {
            return;
        }

        if (!document.contains(button)) {
            return;
        }

        setExecutiveButtonPending(button, false);
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
            }))
                .then(function (result) {
                    if (result && result.reload === true) {
                        return syncListsAfterExecutiveAction().then(function () {
                            return reloadExecutiveProposalBestEffort();
                        });
                    }

                    return undefined;
                })
                .catch(function (err) {
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
                lastProposalPayload = payload && typeof payload === 'object' ? payload : null;
                renderProposalPayload(lastProposalPayload);

                return lastProposalPayload;
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
                afterExecutiveActionSuccess(response);

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

    /**
     * @param {HTMLElement} button
     * @returns {Promise<void>}
     */
    function handleFocusActionClick(button) {
        var service = getService();
        var renderer = getRenderer();
        var focusAction = button.getAttribute('data-executive-focus-action') || '';

        if (!service || typeof service.postFocusAction !== 'function') {
            showProposalError('Servicio de foco ejecutivo no disponible.');
            return Promise.resolve();
        }

        if (!renderer || typeof renderer.renderProposal !== 'function') {
            showProposalError('Renderer de propuesta ejecutiva no disponible.');
            return Promise.resolve();
        }

        if (focusAction === '') {
            showProposalError('No se pudo identificar la acción de foco.');
            return Promise.resolve();
        }

        if (isActionPending) {
            return Promise.resolve();
        }

        isActionPending = true;
        setExecutiveButtonPending(button, true);
        clearProposalError();

        return service.postFocusAction(focusAction)
            .then(function (response) {
                if (response.proposal && typeof response.proposal === 'object') {
                    lastProposalPayload = response.proposal;

                    if (!isSprintActive(response.proposal)) {
                        choosingMode = true;
                    }

                    renderProposalPayload(response.proposal);
                }
            })
            .catch(function (err) {
                showProposalError((err && err.message) ? err.message : 'No se pudo ejecutar la acción de foco.');
            })
            .finally(function () {
                isActionPending = false;
                clearExecutiveButtonPendingIfPresent(button);
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

            var focusButton = event.target.closest(EXECUTIVE_FOCUS_ACTION_SELECTOR);

            if (focusButton && !focusButton.disabled && root.contains(focusButton)) {
                event.preventDefault();
                event.stopPropagation();
                handleFocusActionClick(focusButton);

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

    function formatSprintDuration(seconds) {
        var total = Math.max(0, parseInt(seconds, 10) || 0);
        var minutes = Math.floor(total / 60);
        var remainingSeconds = total % 60;

        return minutes + 'm ' + remainingSeconds + 's';
    }

    /**
     * @param {object|null} payload
     * @returns {string[]}
     */
    function buildSprintDebugLines(payload) {
        var sprint = payload
            && payload.meta
            && payload.meta.sprint
            && typeof payload.meta.sprint === 'object'
            ? payload.meta.sprint
            : null;
        var lines = ['[DEOIA Executive Sprint]'];

        if (!sprint) {
            lines.push('active: false');
            lines.push('reason: no_active_sprint');
            lines.push('current_focus_list_id: null');
            lines.push('focus_reason: null');

            return lines;
        }

        if (sprint.sprint_active === true) {
            lines.push('active: true');
            lines.push('focus_list_id: ' + String(sprint.active_focus_list_id != null ? sprint.active_focus_list_id : 'null'));
            lines.push('current_focus_list_id: ' + String(sprint.current_focus_list_id != null ? sprint.current_focus_list_id : 'null'));
            lines.push('focus_reason: ' + String(sprint.focus_reason || 'null'));
            lines.push('expires_in: ' + formatSprintDuration(sprint.seconds_remaining));
            lines.push('sprint_started_at: ' + String(sprint.sprint_started_at != null ? sprint.sprint_started_at : 'null'));
            lines.push('last_executive_action_at: ' + String(sprint.last_executive_action_at != null ? sprint.last_executive_action_at : 'null'));
            lines.push('sprint_expires_at: ' + String(sprint.sprint_expires_at != null ? sprint.sprint_expires_at : 'null'));

            return lines;
        }

        lines.push('active: false');
        lines.push('reason: ' + String(sprint.inactive_reason || 'no_active_sprint'));
        lines.push('current_focus_list_id: ' + String(sprint.current_focus_list_id != null ? sprint.current_focus_list_id : 'null'));
        lines.push('focus_reason: ' + String(sprint.focus_reason || 'first_list_with_eligible_tasks'));

        return lines;
    }

    /**
     * @param {object|null} payload
     */
    function printSprintDebug(payload) {
        var lines = buildSprintDebugLines(payload);
        var logger = globalRoot.console && typeof globalRoot.console.log === 'function'
            ? globalRoot.console.log.bind(globalRoot.console)
            : function () {};

        lines.forEach(function (line) {
            logger(line);
        });
    }

    /**
     * @returns {Promise<object|null>}
     */
    function debugSprint() {
        return loadProposal({ silent: true })
            .then(function (payload) {
                printSprintDebug(payload || lastProposalPayload);

                return payload || lastProposalPayload;
            })
            .catch(function () {
                printSprintDebug(lastProposalPayload);

                return lastProposalPayload;
            });
    }

    /**
     * @param {number} [intervalMs]
     * @returns {function():void}
     */
    function debugSprintWatch(intervalMs) {
        var resolvedInterval = parseInt(intervalMs, 10);

        if (!resolvedInterval || resolvedInterval < 1000) {
            resolvedInterval = 15000;
        }

        stopDebugSprintWatch();

        function tick() {
            debugSprint().then(function (payload) {
                var sprint = payload
                    && payload.meta
                    && payload.meta.sprint;

                if (!sprint || sprint.sprint_active !== true) {
                    stopDebugSprintWatch();
                }
            });
        }

        tick();
        sprintWatchTimer = globalRoot.setInterval(tick, resolvedInterval);

        return stopDebugSprintWatch;
    }

    function stopDebugSprintWatch() {
        if (sprintWatchTimer !== null) {
            globalRoot.clearInterval(sprintWatchTimer);
            sprintWatchTimer = null;
        }
    }

    /**
     * @returns {Promise<object|null>}
     */
    function debugExpireSprint() {
        var service = getService();
        var renderer = getRenderer();

        if (!service || typeof service.postFocusAction !== 'function') {
            return Promise.resolve(null);
        }

        return service.postFocusAction('expire_sprint_debug')
            .then(function (response) {
                if (renderer && typeof renderer.renderProposal === 'function' && response.proposal) {
                    lastProposalPayload = response.proposal;
                    renderProposalPayload(response.proposal);
                }

                return response.proposal || lastProposalPayload;
            })
            .catch(function () {
                return lastProposalPayload;
            });
    }

    function initExecutiveProposalModule() {
        bindExecutiveDelegation();
        loadProposal();
    }

    globalRoot.AAExecutiveProposal = {
        reload: loadProposal,
        setWorkZone: setWorkZone,
        setChoosingMode: setChoosingMode,
        debugSprint: debugSprint,
        debugSprintWatch: debugSprintWatch,
        stopDebugSprintWatch: stopDebugSprintWatch,
        debugExpireSprint: debugExpireSprint
    };

    var moduleExports = {
        loadProposal: loadProposal,
        reloadExecutiveProposalBestEffort: reloadExecutiveProposalBestEffort,
        handleExecutiveActionClick: handleExecutiveActionClick,
        handleFocusActionClick: handleFocusActionClick,
        runClientAction: runClientAction,
        syncListsAfterExecutiveAction: syncListsAfterExecutiveAction,
        setWorkZone: setWorkZone,
        setChoosingMode: setChoosingMode,
        resolveUiMode: resolveUiMode,
        renderProposalPayload: renderProposalPayload,
        afterExecutiveActionSuccess: afterExecutiveActionSuccess,
        bindExecutiveDelegation: bindExecutiveDelegation,
        flushPendingWorkZoneRender: flushPendingWorkZoneRender,
        debugSprint: debugSprint,
        debugSprintWatch: debugSprintWatch,
        stopDebugSprintWatch: stopDebugSprintWatch,
        debugExpireSprint: debugExpireSprint,
        buildSprintDebugLines: buildSprintDebugLines,
        getLastProposalPayload: function () {
            return lastProposalPayload;
        }
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
