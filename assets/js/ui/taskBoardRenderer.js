/**
 * Task Board Renderer — markup puro para listas/tareas manuales.
 *
 * Ordena según organization recibida del backend; sin reglas de prioridad locales.
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * @param {Array} lists
     * @returns {Object<number, object>}
     */
    function indexLists(lists) {
        var map = {};

        (lists || []).forEach(function (list) {
            if (!list || list.id === undefined || list.id === null) {
                return;
            }

            map[Number(list.id)] = list;
        });

        return map;
    }

    /**
     * @param {Array} tasks
     * @returns {Object<number, object>}
     */
    function indexTasks(tasks) {
        var map = {};

        (tasks || []).forEach(function (task) {
            if (!task || task.id === undefined || task.id === null) {
                return;
            }

            map[Number(task.id)] = task;
        });

        return map;
    }

    /**
     * @param {Array} lists
     * @param {Object} organization
     * @returns {Array}
     */
    function resolveListOrder(lists, organization) {
        var listMap = indexLists(lists);
        var order = organization && Array.isArray(organization.list_order)
            ? organization.list_order
            : [];
        var resolved = [];
        var seen = {};

        order.forEach(function (listId) {
            var id = Number(listId);
            var list = listMap[id];

            if (list && !seen[id]) {
                resolved.push(list);
                seen[id] = true;
            }
        });

        (lists || []).forEach(function (list) {
            var id = Number(list.id);

            if (!seen[id]) {
                resolved.push(list);
            }
        });

        return resolved;
    }

    /**
     * @param {number} listId
     * @param {Array} tasks
     * @param {Object} organization
     * @returns {Array}
     */
    function resolveTasksForList(listId, tasks, organization) {
        var taskMap = indexTasks(tasks);
        var order = organization
            && organization.task_order_by_list
            && Array.isArray(organization.task_order_by_list[listId])
            ? organization.task_order_by_list[listId]
            : [];
        var resolved = [];
        var seen = {};

        order.forEach(function (taskId) {
            var id = Number(taskId);
            var task = taskMap[id];

            if (task && Number(task.list_id) === listId && !seen[id]) {
                resolved.push(task);
                seen[id] = true;
            }
        });

        (tasks || []).forEach(function (task) {
            var id = Number(task.id);

            if (Number(task.list_id) === listId && !seen[id]) {
                resolved.push(task);
            }
        });

        return resolved;
    }

    /**
     * @param {object} task
     * @returns {string}
     */
    function renderTaskRow(task) {
        var taskId = escapeHtml(task.id);
        var title = escapeHtml(task.title || '');
        var isDone = String(task.status || '') === 'done';
        var titleClass = isDone
            ? 'text-sm text-gray-400 line-through'
            : 'text-sm font-medium text-gray-900';
        var dueHtml = task.due_at
            ? '<span class="text-xs text-gray-500 ml-2">Vence: ' + escapeHtml(task.due_at) + '</span>'
            : '';
        var notesHtml = task.notes
            ? '<p class="text-xs text-gray-500 mt-1">' + escapeHtml(task.notes) + '</p>'
            : '';
        var statusAction = isDone
            ? '<button type="button" data-tasks-action="pending" data-task-id="' + taskId + '"'
                + ' class="text-xs font-medium text-gray-600 hover:text-gray-800 px-2 py-1 rounded border border-gray-200">Marcar pendiente</button>'
            : '<button type="button" data-tasks-action="complete" data-task-id="' + taskId + '"'
                + ' class="text-xs font-medium text-green-700 hover:text-green-800 px-2 py-1 rounded border border-green-200 bg-green-50">Completar</button>';

        return ''
            + '<li class="aa-task-row flex items-start justify-between gap-3 py-2 border-b border-gray-100 last:border-b-0" data-task-id="' + taskId + '">'
            + '<div class="min-w-0 flex-1">'
            + '<div class="flex flex-wrap items-center gap-1">'
            + '<span class="' + titleClass + '">' + title + '</span>'
            + dueHtml
            + '</div>'
            + notesHtml
            + '</div>'
            + '<div class="flex-shrink-0">' + statusAction + '</div>'
            + '</li>';
    }

    /**
     * @param {object} list
     * @param {Array} tasks
     * @returns {string}
     */
    function renderListCard(list, tasks) {
        var listId = escapeHtml(list.id);
        var title = escapeHtml(list.title || 'Lista sin título');
        var description = list.description
            ? '<p class="text-sm text-gray-600 mt-1">' + escapeHtml(list.description) + '</p>'
            : '';
        var tasksHtml = tasks.length > 0
            ? tasks.map(renderTaskRow).join('')
            : '<li class="text-sm text-gray-500 py-2">No hay tareas en esta lista.</li>';

        return ''
            + '<article class="aa-task-list-card bg-white rounded-xl shadow border border-gray-200 overflow-hidden" data-list-id="' + listId + '">'
            + '<div class="px-4 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">'
            + '<div class="flex items-start justify-between gap-3">'
            + '<div class="min-w-0">'
            + '<h4 class="text-base font-semibold text-gray-900">' + title + '</h4>'
            + description
            + '</div>'
            + '<button type="button" data-tasks-action="archive-list" data-list-id="' + listId + '"'
            + ' class="text-xs font-medium text-gray-500 hover:text-red-600 px-2 py-1 rounded border border-gray-200 whitespace-nowrap">Archivar</button>'
            + '</div>'
            + '</div>'
            + '<ul class="px-4 py-2 space-y-0">' + tasksHtml + '</ul>'
            + '</article>';
    }

    /**
     * @param {{lists:Array,tasks:Array,organization:Object}} payload
     * @returns {string}
     */
    function renderBoard(payload) {
        var lists = resolveListOrder(payload.lists || [], payload.organization || {});
        var tasks = payload.tasks || [];
        var organization = payload.organization || {};

        if (lists.length === 0) {
            return '';
        }

        return lists.map(function (list) {
            var listId = Number(list.id);
            var listTasks = resolveTasksForList(listId, tasks, organization);

            return renderListCard(list, listTasks);
        }).join('');
    }

    window.AATaskBoardRenderer = {
        escapeHtml: escapeHtml,
        renderBoard: renderBoard,
        resolveListOrder: resolveListOrder,
        resolveTasksForList: resolveTasksForList
    };
})();
