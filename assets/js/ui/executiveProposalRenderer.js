/**
 * Executive Proposal Renderer — Propuesta ejecutiva top-3 (MC2/MC6).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var STATUS_LABEL_EXECUTING = 'En ejecución';
    var STATUS_LABEL_READY = 'Elige tu siguiente tarea';
    var STATUS_LABEL_CHOOSING = 'Buscando tarea para ejecutar';
    var STATUS_LABEL_ORGANIZING = 'Organizando tareas';

    var CONTINUATION_TITLE_MAX = 42;

    /**
     * @param {string} value
     * @returns {string}
     */
    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * @param {object|null|undefined} payload
     * @param {string|null|undefined} uiMode organizing|choosing|null
     * @returns {string}
     */
    function resolveStatusLabel(payload, uiMode) {
        if (uiMode === 'organizing') {
            return STATUS_LABEL_ORGANIZING;
        }

        if (uiMode === 'choosing') {
            return STATUS_LABEL_CHOOSING;
        }

        var sprintActive = payload
            && payload.meta
            && payload.meta.sprint
            && payload.meta.sprint.sprint_active === true;

        if (sprintActive) {
            return STATUS_LABEL_EXECUTING;
        }

        return STATUS_LABEL_READY;
    }

    /**
     * @param {string} label
     * @returns {string}
     */
    function renderStatusHtml(label) {
        var safeLabel = escapeHtml(label);
        var dot = label === STATUS_LABEL_EXECUTING
            ? '<span class="aa-executive-status-dot shrink-0" aria-hidden="true"></span>'
            : '';

        return ''
            + '<span class="aa-executive-status inline-flex items-center gap-2 text-sm text-gray-600" data-executive-status-label="' + safeLabel + '">'
            + dot
            + '<span class="aa-executive-status-text">' + safeLabel + '</span>'
            + '</span>';
    }

    /**
     * @param {object|null|undefined} focusControls
     * @returns {string}
     */
    function renderHeaderFocusControls(focusControls) {
        if (!focusControls || typeof focusControls !== 'object') {
            return '';
        }

        var buttons = [];

        if (focusControls.can_go_previous === true) {
            buttons.push(
                '<button type="button"'
                + ' data-executive-focus-action="previous_focus"'
                + ' class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-800">'
                + 'Anterior'
                + '</button>'
            );
        }

        if (focusControls.can_change_focus === true) {
            buttons.push(
                '<button type="button"'
                + ' data-executive-focus-action="change_focus"'
                + ' class="inline-flex items-center rounded-lg border border-violet-200 bg-white px-2.5 py-1.5 text-xs font-medium text-violet-700 hover:text-violet-800 hover:border-violet-300">'
                + 'Cambiar foco'
                + '</button>'
            );
        }

        return buttons.join('');
    }

    /**
     * @param {object|null|undefined} payload
     * @param {{uiMode?:string|null}} [options]
     */
    function updateExecutiveHeader(payload, options) {
        var opts = options || {};
        var statusEl = document.getElementById('aa-executive-status');
        var actionsEl = document.getElementById('aa-executive-header-actions');
        var focusControls = payload && payload.meta && typeof payload.meta === 'object'
            ? payload.meta.focus_controls
            : null;
        var label = resolveStatusLabel(payload, opts.uiMode || null);

        if (statusEl) {
            statusEl.innerHTML = renderStatusHtml(label);
        }

        if (actionsEl) {
            actionsEl.innerHTML = renderHeaderFocusControls(focusControls);
        }
    }

    /**
     * @param {string} title
     * @param {number} [maxLen]
     * @returns {string}
     */
    function truncateTitle(title, maxLen) {
        var safe = String(title || '').trim();
        var limit = maxLen || CONTINUATION_TITLE_MAX;

        if (safe.length <= limit) {
            return safe;
        }

        return safe.slice(0, Math.max(0, limit - 1)) + '…';
    }

    /**
     * @param {object} task
     * @returns {string}
     */
    function renderOverdueBadge(task) {
        if (!task || !task.is_overdue) {
            return '';
        }

        return '<span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Vencida</span>';
    }

    /**
     * @param {object} action
     * @returns {string}
     */
    function resolveActionLabel(action) {
        var key = String(action.key || '').toLowerCase();
        var label = String(action.label || '').trim();

        if (key === 'dismiss') {
            return 'Ahora no';
        }

        if (key === 'complete') {
            return 'Completar';
        }

        if (key === 'missed') {
            return 'No realizada';
        }

        return label !== '' ? label : 'Acción';
    }

    /**
     * @param {object} action
     * @returns {string}
     */
    function resolveActionButtonClass(action) {
        var key = String(action.key || '').toLowerCase();
        var type = String(action.type || '').toLowerCase();

        if (key === 'complete') {
            return 'text-green-700 hover:text-green-800 border-green-200 bg-green-50';
        }

        if (key === 'missed') {
            return 'text-amber-700 hover:text-amber-800 border-amber-200 bg-amber-50';
        }

        if (key === 'dismiss') {
            return 'text-gray-600 hover:text-gray-800 border-gray-200 bg-white';
        }

        if (type === 'navigate' || type === 'handler') {
            return 'text-blue-700 hover:text-blue-800 border-blue-200 bg-blue-50';
        }

        return 'text-gray-700 hover:text-gray-900 border-gray-200 bg-white';
    }

    /**
     * @param {Array} actions
     * @param {object} task
     * @returns {string}
     */
    function renderExecutiveActions(actions, task) {
        if (!Array.isArray(actions) || actions.length === 0) {
            return '';
        }

        var taskId = escapeHtml(task.task_id || '');
        var buttons = actions.map(function (action) {
            if (!action || typeof action !== 'object') {
                return '';
            }

            var actionKey = escapeHtml(action.key || '');
            var label = escapeHtml(resolveActionLabel(action));
            var buttonClass = resolveActionButtonClass(action);

            if (actionKey === '' || taskId === '') {
                return '';
            }

            return ''
                + '<button type="button"'
                + ' data-executive-action="1"'
                + ' data-executive-task-id="' + taskId + '"'
                + ' data-executive-action-key="' + actionKey + '"'
                + ' class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-medium ' + buttonClass + '">'
                + label
                + '</button>';
        }).join('');

        if (buttons === '') {
            return '';
        }

        return '<div class="aa-executive-actions mt-3 flex flex-wrap gap-2">' + buttons + '</div>';
    }

    /**
     * @param {object} task
     * @param {string} focusListTitle
     * @param {{wrapper?:'li'|'div'}} [options]
     * @returns {string}
     */
    function renderCurrentTask(task, focusListTitle, options) {
        var opts = options || {};
        var wrapper = opts.wrapper === 'div' ? 'div' : 'li';
        var title = escapeHtml(task.title || 'Tarea sin título');
        var description = task.description
            ? '<p class="text-sm text-gray-600 mt-2">' + escapeHtml(task.description) + '</p>'
            : '';
        var listLabel = escapeHtml(focusListTitle || 'Lista sin título');
        var actionsHtml = renderExecutiveActions(task.executive_actions, task);

        return ''
            + '<' + wrapper + ' class="aa-executive-slot aa-executive-slot-current rounded-lg border border-blue-200 bg-blue-50/60 p-4" data-executive-slot="current">'
            + '<div class="flex flex-wrap items-center justify-between gap-2">'
            + '<span class="min-w-0 truncate text-xs font-medium text-blue-700">Lista: ' + listLabel + '</span>'
            + renderOverdueBadge(task)
            + '</div>'
            + '<p class="text-base font-semibold text-gray-900 mt-2">' + title + '</p>'
            + description
            + actionsHtml
            + '</' + wrapper + '>';
    }

    /**
     * @param {object|null} nextTask
     * @param {object|null} thirdTask
     * @returns {string}
     */
    function renderContinuationSummary(nextTask, thirdTask) {
        var segments = [];

        if (nextTask && typeof nextTask === 'object') {
            segments.push(
                '<span class="font-medium text-gray-600">Siguiente:</span> '
                + '<span class="text-gray-700">' + escapeHtml(nextTask.title) + '</span>'
            );
        }

        if (thirdTask && typeof thirdTask === 'object') {
            segments.push(
                '<span class="font-medium text-gray-600">Después:</span> '
                + '<span class="text-gray-700">' + escapeHtml(thirdTask.title) + '</span>'
            );
        }

        if (segments.length === 0) {
            return '';
        }

        return ''
            + '<p class="aa-executive-continuation mt-2 block w-full min-w-0 truncate text-xs text-gray-500">'
            + segments.join(', ')
            + '</p>';
    }

    /**
     * @param {object|null|undefined} payload
     * @returns {{listHtml:string,isEmpty:boolean,focusListTitle:string}}
     */
    function buildProposalParts(payload) {
        var data = payload && typeof payload === 'object' ? payload : {};
        var status = String(data.status || '');
        var tasks = Array.isArray(data.tasks) ? data.tasks : [];
        var isEmpty = status === 'empty' || tasks.length === 0;
        var focusListTitle = data.focus_list && typeof data.focus_list === 'object'
            ? String(data.focus_list.title || '')
            : '';

        if (isEmpty) {
            return {
                listHtml: '',
                isEmpty: true,
                focusListTitle: focusListTitle
            };
        }

        var listHtml = '';
        var currentTask = null;
        var nextTask = null;
        var thirdTask = null;

        tasks.forEach(function (task) {
            if (!task || typeof task !== 'object') {
                return;
            }

            var slot = String(task.slot || '');

            if (slot === 'current') {
                currentTask = task;
            } else if (slot === 'next') {
                nextTask = task;
            } else if (slot === 'third') {
                thirdTask = task;
            }
        });

        if (currentTask) {
            listHtml += renderCurrentTask(currentTask, focusListTitle);
            listHtml += renderContinuationSummary(nextTask, thirdTask);
        }

        if (listHtml === '') {
            return {
                listHtml: '',
                isEmpty: true,
                focusListTitle: focusListTitle
            };
        }

        return {
            listHtml: listHtml,
            isEmpty: false,
            focusListTitle: focusListTitle
        };
    }

    /**
     * @param {object|null|undefined} payload
     * @param {{uiMode?:string|null}} [options]
     */
    function renderProposal(payload, options) {
        var emptyEl = document.getElementById('aa-executive-empty');
        var listEl = document.getElementById('aa-executive-list');
        var focusEl = document.getElementById('aa-executive-focus');
        var parts = buildProposalParts(payload);

        updateExecutiveHeader(payload, options);

        if (focusEl) {
            focusEl.innerHTML = '';
            focusEl.classList.add('hidden');
        }

        if (listEl) {
            listEl.innerHTML = parts.isEmpty ? '' : parts.listHtml;
        }

        if (emptyEl) {
            if (parts.isEmpty) {
                emptyEl.classList.remove('hidden');
            } else {
                emptyEl.classList.add('hidden');
            }
        }
    }

    var api = {
        STATUS_LABEL_EXECUTING: STATUS_LABEL_EXECUTING,
        STATUS_LABEL_READY: STATUS_LABEL_READY,
        STATUS_LABEL_CHOOSING: STATUS_LABEL_CHOOSING,
        STATUS_LABEL_ORGANIZING: STATUS_LABEL_ORGANIZING,
        escapeHtml: escapeHtml,
        resolveStatusLabel: resolveStatusLabel,
        renderStatusHtml: renderStatusHtml,
        renderHeaderFocusControls: renderHeaderFocusControls,
        updateExecutiveHeader: updateExecutiveHeader,
        renderProposal: renderProposal,
        buildProposalParts: buildProposalParts,
        renderCurrentTask: renderCurrentTask,
        renderContinuationSummary: renderContinuationSummary,
        renderExecutiveActions: renderExecutiveActions,
        resolveActionLabel: resolveActionLabel,
        truncateTitle: truncateTitle
    };

    globalRoot.AAExecutiveProposalRenderer = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
