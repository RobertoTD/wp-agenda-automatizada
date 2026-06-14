/**
 * Executive Proposal Renderer — render read-only top-3 (MC2).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    var SLOT_LABELS = {
        current: 'Ahora',
        next: 'Siguiente',
        third: 'Después'
    };

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
     * @param {object|null|undefined} focusList
     * @returns {string}
     */
    function renderFocusContext(focusList) {
        if (!focusList || typeof focusList !== 'object') {
            return '';
        }

        var title = escapeHtml(focusList.title || 'Lista sin título');
        var badge = String(focusList.source_category || '') === 'agenda_app'
            ? '<span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Agenda app</span>'
            : '';

        return ''
            + '<div class="aa-executive-focus-context rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">'
            + '<p class="text-sm text-gray-700 flex flex-wrap items-center gap-2">'
            + '<span class="font-medium text-gray-900">Foco:</span>'
            + '<span>' + title + '</span>'
            + badge
            + '</p>'
            + '</div>';
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
     * @param {number|string} taskId
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
     * @returns {string}
     */
    function renderCurrentTask(task) {
        var title = escapeHtml(task.title || 'Tarea sin título');
        var description = task.description
            ? '<p class="text-sm text-gray-600 mt-2">' + escapeHtml(task.description) + '</p>'
            : '';
        var slotLabel = escapeHtml(SLOT_LABELS.current);
        var actionsHtml = renderExecutiveActions(task.executive_actions, task);

        return ''
            + '<li class="aa-executive-slot aa-executive-slot-current rounded-lg border border-blue-200 bg-blue-50/60 p-4" data-executive-slot="current">'
            + '<div class="flex flex-wrap items-center gap-2">'
            + '<span class="text-xs font-semibold uppercase tracking-wide text-blue-700">' + slotLabel + '</span>'
            + renderOverdueBadge(task)
            + '</div>'
            + '<p class="text-base font-semibold text-gray-900 mt-2">' + title + '</p>'
            + description
            + actionsHtml
            + '</li>';
    }

    /**
     * @param {object} task
     * @param {string} slot
     * @returns {string}
     */
    function renderContinuationTask(task, slot) {
        var title = escapeHtml(task.title || 'Tarea sin título');
        var slotLabel = escapeHtml(SLOT_LABELS[slot] || slot);

        return ''
            + '<li class="aa-executive-slot aa-executive-slot-' + escapeHtml(slot) + ' rounded-lg border border-gray-200 bg-white px-3 py-2" data-executive-slot="' + escapeHtml(slot) + '">'
            + '<div class="flex flex-wrap items-center gap-2">'
            + '<span class="text-xs font-medium text-gray-500">' + slotLabel + '</span>'
            + renderOverdueBadge(task)
            + '</div>'
            + '<p class="text-sm font-medium text-gray-900 mt-1">' + title + '</p>'
            + '</li>';
    }

    /**
     * @param {object|null|undefined} payload
     * @returns {{focusHtml:string,listHtml:string,isEmpty:boolean}}
     */
    function buildProposalParts(payload) {
        var data = payload && typeof payload === 'object' ? payload : {};
        var status = String(data.status || '');
        var tasks = Array.isArray(data.tasks) ? data.tasks : [];
        var isEmpty = status === 'empty' || tasks.length === 0;

        if (isEmpty) {
            return {
                focusHtml: '',
                listHtml: '',
                isEmpty: true
            };
        }

        var focusHtml = renderFocusContext(data.focus_list);
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
            listHtml += renderCurrentTask(currentTask);
        }

        if (nextTask) {
            listHtml += renderContinuationTask(nextTask, 'next');
        }

        if (thirdTask) {
            listHtml += renderContinuationTask(thirdTask, 'third');
        }

        if (listHtml === '') {
            return {
                focusHtml: '',
                listHtml: '',
                isEmpty: true
            };
        }

        return {
            focusHtml: focusHtml,
            listHtml: listHtml,
            isEmpty: false
        };
    }

    /**
     * @param {object|null|undefined} payload
     */
    function renderProposal(payload) {
        var focusEl = document.getElementById('aa-executive-focus');
        var emptyEl = document.getElementById('aa-executive-empty');
        var listEl = document.getElementById('aa-executive-list');
        var parts = buildProposalParts(payload);

        if (focusEl) {
            if (parts.isEmpty || parts.focusHtml === '') {
                focusEl.innerHTML = '';
                focusEl.classList.add('hidden');
            } else {
                focusEl.innerHTML = parts.focusHtml;
                focusEl.classList.remove('hidden');
            }
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
        escapeHtml: escapeHtml,
        renderProposal: renderProposal,
        buildProposalParts: buildProposalParts,
        renderFocusContext: renderFocusContext,
        renderCurrentTask: renderCurrentTask,
        renderContinuationTask: renderContinuationTask,
        renderExecutiveActions: renderExecutiveActions,
        resolveActionLabel: resolveActionLabel
    };

    globalRoot.AAExecutiveProposalRenderer = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
