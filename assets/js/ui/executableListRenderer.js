/**
 * Executable List Renderer — markup puro para ExecutableList[] (contrato MC7).
 *
 * Experimental MC8A: no conectado a producción. Emite data-* legacy-compatible
 * para integración futura con learning-module.js y tasks-board-module.js.
 */
(function () {
    'use strict';

    var BTN_BASE = 'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors';

    /**
     * @param {unknown} value
     * @returns {string}
     */
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
     * @param {boolean} disabled
     * @returns {string}
     */
    function btnClass(disabled) {
        if (disabled) {
            return BTN_BASE + ' text-gray-400 bg-gray-100 border-gray-200 cursor-not-allowed opacity-60';
        }

        return BTN_BASE;
    }

    /**
     * @param {unknown} value
     * @returns {string}
     */
    function asString(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    /**
     * @param {object|undefined|null} options
     * @returns {object}
     */
    function normalizeOptions(options) {
        return options && typeof options === 'object' ? options : {};
    }

    /**
     * @param {Function|undefined} callback
     * @param {Array} args
     * @returns {boolean}
     */
    function callbackAllows(callback, args) {
        if (typeof callback !== 'function') {
            return true;
        }

        try {
            return callback.apply(null, args) !== false;
        } catch (err) {
            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn('[AAExecutableListRenderer] runtime callback failed:', err);
            }

            return true;
        }
    }

    /**
     * @param {object} context
     * @param {object} additions
     * @returns {object}
     */
    function extendContext(context, additions) {
        var next = {};
        var base = context && typeof context === 'object' ? context : {};
        var extra = additions && typeof additions === 'object' ? additions : {};

        Object.keys(base).forEach(function (key) {
            next[key] = base[key];
        });

        Object.keys(extra).forEach(function (key) {
            next[key] = extra[key];
        });

        return next;
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function resolveItemSource(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        return asString(item.source).trim().toLowerCase();
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function resolveSourceCategory(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        return asString(item.source_category).trim().toLowerCase();
    }

    /**
     * Task id para acciones del canal Tasks común (aa_*_task), si aplica.
     *
     * User tasks siempre; agenda_app seeded desde DB común usa id numérico de aa_tasks.
     * Legacy Learning conserva ids slug (= origin_key) y sigue en canal Learning.
     *
     * @param {object} item
     * @returns {string}
     */
    function resolveTasksChannelTaskId(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        var taskId = asString(item.id).trim();

        if (taskId === '') {
            return '';
        }

        if (resolveItemSource(item) === 'user') {
            return taskId;
        }

        if (resolveSourceCategory(item) === 'agenda_app' && /^\d+$/.test(taskId)) {
            return taskId;
        }

        return '';
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function resolveRecommendationKey(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        var originKey = asString(item.origin_key).trim();

        if (originKey !== '') {
            return originKey;
        }

        return asString(item.id).trim();
    }

    /**
     * @param {object} item
     * @returns {boolean}
     */
    function hasVisibleActions(item) {
        return !!item
            && typeof item === 'object'
            && Array.isArray(item.visible_actions)
            && item.visible_actions.length > 0;
    }

    /**
     * @param {object} action
     * @returns {object}
     */
    function mapVisibleActionForCallback(action) {
        if (!action || typeof action !== 'object') {
            return action;
        }

        var type = asString(action.type).trim();

        if (type === 'status') {
            return {
                type: 'status',
                label: action.label,
                to: action.target_status
            };
        }

        return action;
    }

    /**
     * @param {object} action
     * @param {object} item
     * @param {object} [options]
     * @param {object} [context]
     * @returns {boolean}
     */
    function actionRenderAllowed(action, item, options, context) {
        var opts = normalizeOptions(options);
        var ctx = context || {};

        if (typeof opts.shouldRenderAction === 'function') {
            return callbackAllows(opts.shouldRenderAction, [action, item, ctx]);
        }

        return callbackAllows(
            opts.shouldRenderPrimaryAction,
            [mapVisibleActionForCallback(action), item, ctx]
        );
    }

    /**
     * @param {object|null|undefined} action
     * @param {object} item
     * @param {object} [options]
     * @param {object} [context]
     * @returns {string}
     */
    function renderVisibleAction(action, item, options, context) {
        if (!action || typeof action !== 'object') {
            return '';
        }

        if (!actionRenderAllowed(action, item, options, context)) {
            return '';
        }

        var type = asString(action.type).trim();
        var label = asString(action.label).trim();
        var recommendationKey = resolveRecommendationKey(item);

        if (type === 'navigate') {
            var url = asString(action.url).trim();

            if (url === '') {
                return '';
            }

            return ''
                + '<a href="' + escapeHtml(url) + '"'
                + ' class="' + btnClass(false) + ' text-blue-700 bg-white hover:bg-gray-50 border-blue-200">'
                + escapeHtml(label || 'Ir')
                + '</a>';
        }

        if (type === 'handler') {
            var handler = asString(action.handler).trim();

            if (handler === '' || label === '' || recommendationKey === '') {
                return '';
            }

            return ''
                + '<button type="button" data-learning-action="primary-handler"'
                + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                + ' data-learning-handler="' + escapeHtml(handler) + '"'
                + ' class="' + btnClass(false) + ' text-blue-700 bg-white hover:bg-gray-50 border-blue-200">'
                + escapeHtml(label)
                + '</button>';
        }

        if (type === 'status') {
            var itemSource = resolveItemSource(item);
            var taskId = asString(item.id).trim();
            var targetStatus = asString(action.target_status).trim().toLowerCase();

            if (targetStatus === 'done') {
                var completeTasksTaskId = resolveTasksChannelTaskId(item);

                if (completeTasksTaskId !== '') {
                    return ''
                        + '<button type="button" data-tasks-action="complete" data-task-id="' + escapeHtml(completeTasksTaskId) + '"'
                        + ' class="' + btnClass(false) + ' text-green-700 hover:text-green-800 border-green-200 bg-white">'
                        + escapeHtml(label || 'Completar')
                        + '</button>';
                }

                if (itemSource === 'system') {
                    if (recommendationKey === '') {
                        return '';
                    }

                    return ''
                        + '<button type="button" data-learning-action="complete"'
                        + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                        + ' class="' + btnClass(false) + ' text-green-700 hover:text-green-800 border-green-200 bg-white">'
                        + escapeHtml(label || 'Completar')
                        + '</button>';
                }

                if (itemSource === 'user') {
                    if (taskId === '') {
                        return '';
                    }

                    return ''
                        + '<button type="button" data-tasks-action="complete" data-task-id="' + escapeHtml(taskId) + '"'
                        + ' class="' + btnClass(false) + ' text-green-700 hover:text-green-800 border-green-200 bg-white">'
                        + escapeHtml(label || 'Completar')
                        + '</button>';
                }

                return '';
            }

            if (targetStatus === 'pending') {
                if (itemSource !== 'user' || taskId === '') {
                    return '';
                }

                return ''
                    + '<button type="button" data-tasks-action="pending" data-task-id="' + escapeHtml(taskId) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 hover:text-gray-800 border-gray-200">'
                    + escapeHtml(label || 'Reabrir')
                    + '</button>';
            }

            if (targetStatus === 'missed') {
                var missedTasksTaskId = resolveTasksChannelTaskId(item);

                if (missedTasksTaskId === '') {
                    return '';
                }

                return ''
                    + '<button type="button" data-tasks-action="missed" data-task-id="' + escapeHtml(missedTasksTaskId) + '"'
                    + ' class="' + btnClass(false) + ' text-amber-700 hover:text-amber-800 border-amber-200 bg-amber-50">'
                    + escapeHtml(label || 'No realizada')
                    + '</button>';
            }

            return '';
        }

        if (type === 'intent') {
            var intentKey = asString(action.key).trim().toLowerCase();
            var intentItemSource = resolveItemSource(item);

            if (intentItemSource === 'user') {
                var intentTaskId = asString(item.id).trim();

                if (intentTaskId === '') {
                    return '';
                }

                if (intentKey === 'defer') {
                    return ''
                        + '<button type="button" data-tasks-action="defer" data-task-id="' + escapeHtml(intentTaskId) + '"'
                        + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                        + escapeHtml(label || 'Ahora no')
                        + '</button>';
                }

                if (intentKey === 'dismiss') {
                    return ''
                        + '<button type="button" data-tasks-action="dismiss" data-task-id="' + escapeHtml(intentTaskId) + '"'
                        + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                        + escapeHtml(label || 'Ahora no')
                        + '</button>';
                }

                return '';
            }

            if (recommendationKey === '') {
                return '';
            }

            if (intentKey === 'defer') {
                return ''
                    + '<button type="button" data-learning-action="defer"'
                    + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                    + escapeHtml(label || 'Ahora no')
                    + '</button>';
            }

            if (intentKey === 'dismiss') {
                var dismissTasksTaskId = resolveTasksChannelTaskId(item);

                if (dismissTasksTaskId !== '') {
                    return ''
                        + '<button type="button" data-tasks-action="dismiss" data-task-id="' + escapeHtml(dismissTasksTaskId) + '"'
                        + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                        + escapeHtml(label || 'Ahora no')
                        + '</button>';
                }

                if (recommendationKey === '') {
                    return '';
                }

                return ''
                    + '<button type="button" data-learning-action="dismiss"'
                    + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                    + escapeHtml(label || 'Ahora no')
                    + '</button>';
            }

            if (intentKey === 'reactivate') {
                return ''
                    + '<button type="button" data-learning-action="reactivate"'
                    + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                    + escapeHtml(label || 'Reactivar')
                    + '</button>';
            }
        }

        return '';
    }

    /**
     * @param {object} item
     * @param {object} [options]
     * @param {object} [context]
     * @returns {string}
     */
    function renderVisibleActions(item, options, context) {
        if (!hasVisibleActions(item)) {
            return '';
        }

        return item.visible_actions.map(function (action) {
            return renderVisibleAction(action, item, options, context);
        }).join('');
    }

    /**
     * @param {object|null|undefined} action
     * @param {object} item
     * @param {object} [options]
     * @param {object} [context]
     * @returns {string}
     */
    function renderPrimaryAction(action, item, options, context) {
        var opts = normalizeOptions(options);

        if (!action || typeof action !== 'object') {
            return '';
        }

        if (!callbackAllows(opts.shouldRenderPrimaryAction, [action, item, context || {}])) {
            return '';
        }

        var type = asString(action.type).trim();

        if (type === 'navigate') {
            var url = asString(action.url).trim();

            if (url === '') {
                return '';
            }

            var navigateLabel = asString(action.label).trim() || 'Ir';

            return ''
                + '<a href="' + escapeHtml(url) + '"'
                + ' class="' + btnClass(false) + ' text-blue-700 bg-white hover:bg-gray-50 border-blue-200">'
                + escapeHtml(navigateLabel)
                + '</a>';
        }

        if (type === 'handler') {
            var handler = asString(action.handler).trim();
            var handlerLabel = asString(action.label).trim();
            var recommendationKey = resolveRecommendationKey(item);

            if (handler === '' || handlerLabel === '' || recommendationKey === '') {
                return '';
            }

            return ''
                + '<button type="button" data-learning-action="primary-handler"'
                + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                + ' data-learning-handler="' + escapeHtml(handler) + '"'
                + ' class="' + btnClass(false) + ' text-blue-700 bg-white hover:bg-gray-50 border-blue-200">'
                + escapeHtml(handlerLabel)
                + '</button>';
        }

        if (type === 'status') {
            var taskId = asString(item.id).trim();
            var to = asString(action.to).trim().toLowerCase();
            var statusLabel = asString(action.label).trim();

            if (taskId === '') {
                return '';
            }

            if (to === 'done') {
                statusLabel = statusLabel || 'Completar';

                return ''
                    + '<button type="button" data-tasks-action="complete" data-task-id="' + escapeHtml(taskId) + '"'
                    + ' class="' + btnClass(false) + ' text-green-700 hover:text-green-800 border-green-200 bg-white">'
                    + escapeHtml(statusLabel)
                    + '</button>';
            }

            if (to === 'pending') {
                statusLabel = statusLabel || 'Reabrir';

                return ''
                    + '<button type="button" data-tasks-action="pending" data-task-id="' + escapeHtml(taskId) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 hover:text-gray-800 border-gray-200">'
                    + escapeHtml(statusLabel)
                    + '</button>';
            }
        }

        return '';
    }

    /**
     * @param {object} item
     * @param {object} [options]
     * @returns {string}
     */
    function renderSecondaryActions(item, options) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        var opts = normalizeOptions(options);
        var capabilities = item.capabilities && typeof item.capabilities === 'object'
            ? item.capabilities
            : {};
        var recommendationKey = resolveRecommendationKey(item);
        var actions = [];

        if (capabilities.can_defer && recommendationKey !== '') {
            actions.push(
                '<button type="button" data-learning-action="defer"'
                + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                + 'Ahora no'
                + '</button>'
            );
        }

        if (capabilities.can_dismiss) {
            var fallbackDismissTasksTaskId = resolveTasksChannelTaskId(item);

            if (fallbackDismissTasksTaskId !== '') {
                actions.push(
                    '<button type="button" data-tasks-action="dismiss" data-task-id="' + escapeHtml(fallbackDismissTasksTaskId) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                    + 'Ahora no'
                    + '</button>'
                );
            } else if (recommendationKey !== '') {
                actions.push(
                    '<button type="button" data-learning-action="dismiss"'
                    + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                    + 'Ahora no'
                    + '</button>'
                );
            }
        }

        if (opts.showReactivate === true && capabilities.can_reactivate && recommendationKey !== '') {
            actions.push(
                '<button type="button" data-learning-action="reactivate"'
                + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                + 'Reactivar'
                + '</button>'
            );
        }

        return actions.join('');
    }

    /**
     * @param {object} item
     * @param {object} [options]
     * @param {object} [context]
     * @returns {string}
     */
    function renderItemActions(item, options, context) {
        var combined = hasVisibleActions(item)
            ? renderVisibleActions(item, options, context)
            : renderPrimaryAction(item.primary_action, item, options, context)
                + renderSecondaryActions(item, options);

        if (combined === '') {
            return '';
        }

        return ''
            + '<div class="flex flex-wrap gap-2 aa-executable-item-actions"'
            + ' onclick="event.stopPropagation()">'
            + combined
            + '</div>';
    }

    /**
     * @param {object} item
     * @returns {boolean}
     */
    function hasItemDescription(item) {
        return asString(item && item.description).trim() !== '';
    }

    /**
     * @param {string} description
     * @returns {string}
     */
    function renderItemDescriptionPreview(description) {
        return ''
            + '<p class="aa-executable-item-desc-preview text-sm text-gray-500 mt-1">'
            + escapeHtml(description)
            + '</p>';
    }

    /**
     * @param {string} description
     * @returns {string}
     */
    function renderItemDescriptionFull(description) {
        return ''
            + '<div class="aa-executable-item-desc-full text-sm text-gray-500">'
            + escapeHtml(description)
            + '</div>';
    }

    /**
     * @param {unknown} value
     * @returns {Date|null}
     */
    function parseDueAtToDate(value) {
        var raw = value ? String(value).trim() : '';

        if (!raw) {
            return null;
        }

        var normalized = raw.indexOf('T') !== -1 ? raw : raw.replace(' ', 'T');

        if (normalized.length === 16) {
            normalized += ':00';
        }

        var dueDate = new Date(normalized);

        if (Number.isNaN(dueDate.getTime())) {
            return null;
        }

        return dueDate;
    }

    var DUE_SOON_WINDOW_MS = 24 * 60 * 60 * 1000;

    /**
     * @param {unknown} dueAt
     * @param {Date} [nowDate]
     * @returns {boolean}
     */
    function isDueSoonFromDueAt(dueAt, nowDate) {
        var dueDate = parseDueAtToDate(dueAt);

        if (!dueDate) {
            return false;
        }

        var now = nowDate instanceof Date ? nowDate : new Date();
        var nowMs = now.getTime();
        var dueMs = dueDate.getTime();

        return dueMs > nowMs && dueMs <= (nowMs + DUE_SOON_WINDOW_MS);
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function renderDueStatusBadge(item) {
        if (!item) {
            return '';
        }

        if (asString(item.status).toLowerCase() === 'done') {
            return '';
        }

        if (item.is_overdue === true) {
            return '<span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Vencida</span>';
        }

        if (isDueSoonFromDueAt(item.due_at)) {
            return '<span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Vence pronto</span>';
        }

        if (item.is_pertinent === true) {
            return '<span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Pertinente</span>';
        }

        return '';
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function renderOverdueBadge(item) {
        return renderDueStatusBadge(item);
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function renderItemExpandedMeta(item) {
        var metaHtml = '';

        if (item.execution_available_at) {
            metaHtml += ''
                + '<p class="text-xs text-slate-600 mt-2">Realizar a partir de: '
                + escapeHtml(item.execution_available_at)
                + '</p>';
        }

        if (item.due_at) {
            metaHtml += ''
                + '<p class="text-xs text-gray-500 mt-2">Vence: '
                + escapeHtml(item.due_at)
                + '</p>';
        }

        var importance = Number(item.importance);

        if (!Number.isNaN(importance) && importance !== 0) {
            metaHtml += ''
                + '<p class="text-xs text-gray-500 mt-1">Importancia: '
                + escapeHtml(String(importance))
                + '</p>';
        }

        return metaHtml;
    }

    /**
     * @param {object} capabilities
     * @returns {boolean}
     */
    function itemHasOptionsMenu(capabilities) {
        return !!capabilities.can_edit || !!capabilities.can_archive || !!capabilities.can_delete;
    }

    /**
     * @param {object} item
     * @param {object} capabilities
     * @returns {string}
     */
    function renderItemOptionsMenuItems(item, capabilities) {
        var items = '';
        var taskId = escapeHtml(asString(item.id));
        var defaultBucket = asString(item.default_bucket).trim().toLowerCase();

        if (defaultBucket !== 'secondary') {
            defaultBucket = 'primary';
        }

        if (capabilities.can_edit) {
            items += ''
                + '<button type="button" role="menuitem"'
                + ' data-aa-task-edit="1"'
                + ' data-task-id="' + taskId + '"'
                + ' data-task-title="' + escapeHtml(asString(item.title)) + '"'
                + ' data-task-notes="' + escapeHtml(asString(item.description)) + '"'
                + ' data-task-due-at="' + escapeHtml(asString(item.due_at)) + '"'
                + ' data-task-execution-available-at="' + escapeHtml(asString(item.execution_available_at)) + '"'
                + ' data-task-importance="' + escapeHtml(String(item.importance !== undefined && item.importance !== null ? item.importance : 0)) + '"'
                + ' data-task-default-bucket="' + escapeHtml(defaultBucket) + '"'
                + ' onclick="event.stopPropagation()"'
                + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">'
                + 'Editar tarea'
                + '</button>';
        }

        if (capabilities.can_archive) {
            items += ''
                + '<button type="button" role="menuitem"'
                + ' data-tasks-action="archive-task"'
                + ' data-task-id="' + taskId + '"'
                + ' onclick="event.stopPropagation()"'
                + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">'
                + 'Archivar tarea'
                + '</button>';
        }

        if (capabilities.can_delete) {
            items += ''
                + '<button type="button" role="menuitem"'
                + ' data-tasks-action="delete-task"'
                + ' data-task-id="' + taskId + '"'
                + ' onclick="event.stopPropagation()"'
                + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-gray-50">'
                + 'Eliminar tarea'
                + '</button>';
        }

        return items;
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function renderItemOptionsMenu(item) {
        var capabilities = item.capabilities && typeof item.capabilities === 'object'
            ? item.capabilities
            : {};

        if (!itemHasOptionsMenu(capabilities)) {
            return '';
        }

        var menuItems = renderItemOptionsMenuItems(item, capabilities);

        if (menuItems === '') {
            return '';
        }

        var taskId = escapeHtml(asString(item.id));

        return ''
            + '<div class="relative aa-executable-task-options shrink-0">'
            + '<button type="button"'
            + ' data-aa-task-options-trigger="1"'
            + ' data-task-id="' + taskId + '"'
            + ' onclick="event.stopPropagation()"'
            + ' title="Opciones de tarea"'
            + ' aria-label="Opciones de tarea"'
            + ' aria-haspopup="menu"'
            + ' aria-expanded="false"'
            + ' class="aa-executable-task-options-trigger aa-options-trigger-flat">'
            + '<svg class="w-6 h-6 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            + '<circle cx="5" cy="12" r="1.75"/>'
            + '<circle cx="12" cy="12" r="1.75"/>'
            + '<circle cx="19" cy="12" r="1.75"/>'
            + '</svg>'
            + '</button>'
            + '<div class="hidden aa-executable-task-options-menu absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"'
            + ' role="menu"'
            + ' data-task-id="' + taskId + '">'
            + menuItems
            + '</div>'
            + '</div>';
    }

    /**
     * @returns {string}
     */
    function renderItemSummaryChevron() {
        return ''
            + '<span class="aa-executable-item-chevron aa-chevron inline-flex shrink-0 text-gray-400 transition-transform duration-200"'
            + ' aria-hidden="true">'
            + '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>'
            + '</svg>'
            + '</span>';
    }

    /**
     * Icono representativo de tarea (mismo patrón que clientes / cabecera de lista).
     *
     * @returns {string}
     */
    function renderItemTitleIcon() {
        return ''
            + '<span class="aa-executable-item-icon flex items-center justify-center w-8 h-8 text-gray-600 shrink-0" aria-hidden="true">'
            + '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'
            + '</svg>'
            + '</span>';
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function renderItemSummaryActions(item) {
        return ''
            + '<div class="aa-executable-item-summary-actions flex items-center gap-1 shrink-0">'
            + renderItemOptionsMenu(item)
            + '</div>';
    }

    /**
     * @param {object} item
     * @param {object} [options]
     * @param {object} [context]
     * @param {number} [itemIndex]
     * @returns {string}
     */
    function renderItem(item, options, context, itemIndex) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        var opts = normalizeOptions(options);
        var itemContext = extendContext(context, {
            item: item,
            source: item.source || (context && context.source) || '',
            itemIndex: typeof itemIndex === 'number' ? itemIndex : undefined
        });

        if (!opts.assumeRenderable && !callbackAllows(opts.shouldRenderItem, [item, itemContext])) {
            return '';
        }

        var itemId = escapeHtml(item.id);
        var source = escapeHtml(item.source || '');
        var title = escapeHtml(item.title || '');
        var descriptionText = asString(item.description).trim();
        var isDone = asString(item.status).toLowerCase() === 'done';
        var titleClass = isDone
            ? 'aa-executable-item-title text-base text-gray-400 line-through'
            : 'aa-executable-item-title text-base font-semibold text-gray-600';
        var actionsHtml = renderItemActions(item, opts, itemContext);
        var previewHtml = descriptionText !== ''
            ? renderItemDescriptionPreview(descriptionText)
            : '';
        var expandedDescriptionHtml = descriptionText !== ''
            ? renderItemDescriptionFull(descriptionText)
            : '';
        var metaHtml = renderItemExpandedMeta(item);
        var needsExpandedPanel = descriptionText !== ''
            || metaHtml !== ''
            || actionsHtml !== '';
        var expandedActionsHtml = actionsHtml !== ''
            ? '<div class="aa-executable-item-expanded-actions mt-3">' + actionsHtml + '</div>'
            : '';
        var expandedPanelHtml = needsExpandedPanel
            ? ''
                + '<div class="aa-executable-item-expanded px-4 pb-4 pt-0">'
                + expandedDescriptionHtml
                + metaHtml
                + expandedActionsHtml
                + '</div>'
            : '';
        var firstItemClass = '';

        if (context && context.firstItemState && !context.firstItemState.marked) {
            context.firstItemState.marked = true;
            firstItemClass = ' aa-executable-item-first';
        }

        return ''
            + '<li class="aa-executable-item-entry">'
            + '<details class="aa-executable-item group rounded-lg border border-gray-200 bg-gray-50/80' + firstItemClass + '"'
            + ' data-item-id="' + itemId + '"'
            + ' data-item-source="' + source + '">'
            + '<summary class="aa-executable-item-summary cursor-pointer list-none p-4">'
            + '<div class="flex items-start justify-between gap-2">'
            + '<div class="min-w-0 flex-1">'
            + '<div class="flex flex-wrap items-center gap-2">'
            + '<div class="flex items-center min-w-0 flex-1">'
            + renderItemTitleIcon()
            + '<p class="' + titleClass + '">' + title + '</p>'
            + '</div>'
            + renderOverdueBadge(item)
            + '</div>'
            + previewHtml
            + '</div>'
            + renderItemSummaryActions(item)
            + '</div>'
            + '</summary>'
            + expandedPanelHtml
            + '</details>'
            + '</li>';
    }

    /**
     * @param {Array} items
     * @param {object} [options]
     * @param {object} [bucketContext]
     * @param {number} [startIndex]
     * @returns {string}
     */
    function renderBucketItemsHtml(items, options, bucketContext, startIndex) {
        var offset = typeof startIndex === 'number' ? startIndex : 0;

        return items.map(function (item, itemIndex) {
            return renderItem(item, options, bucketContext, offset + itemIndex);
        }).join('');
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function normalizeStableItemId(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }

        return asString(item.id).trim();
    }

    /**
     * Filtra, deduplica y indexa los items efectivamente renderizables de una lista.
     * shouldRenderItem se ejecuta exactamente una vez por aparición candidata.
     *
     * @param {Array} buckets
     * @param {object} [options]
     * @param {object} [listContext]
     * @returns {{
     *   items: Array<{item: object, bucket: object, bucketIndex: number, sourceItemIndex: number}>,
     *   total: number,
     *   pertinentCount: number,
     *   overdueCount: number
     * }}
     */
    function prepareRenderableListCollection(buckets, options, listContext) {
        var opts = normalizeOptions(options);
        var seenIds = Object.create(null);
        var collection = [];
        var pertinentCount = 0;
        var overdueCount = 0;

        if (!Array.isArray(buckets)) {
            return {
                items: [],
                total: 0,
                pertinentCount: 0,
                overdueCount: 0
            };
        }

        buckets.forEach(function (bucket, bucketIndex) {
            if (!bucket || typeof bucket !== 'object') {
                return;
            }

            var items = Array.isArray(bucket.items) ? bucket.items : [];

            items.forEach(function (item, itemIndex) {
                if (!item || typeof item !== 'object') {
                    return;
                }

                var stableId = normalizeStableItemId(item);

                if (stableId === '') {
                    return;
                }

                var bucketContext = extendContext(listContext, {
                    bucket: bucket,
                    bucketIndex: bucketIndex
                });
                var itemContext = extendContext(bucketContext, {
                    item: item,
                    source: item.source || (listContext && listContext.source) || '',
                    itemIndex: itemIndex
                });

                if (!callbackAllows(opts.shouldRenderItem, [item, itemContext])) {
                    return;
                }

                if (seenIds[stableId]) {
                    return;
                }

                seenIds[stableId] = true;
                collection.push({
                    item: item,
                    bucket: bucket,
                    bucketIndex: bucketIndex,
                    sourceItemIndex: itemIndex
                });

                if (item.is_overdue === true) {
                    overdueCount += 1;
                } else if (item.is_pertinent === true) {
                    pertinentCount += 1;
                }
            });
        });

        return {
            items: collection,
            total: collection.length,
            pertinentCount: pertinentCount,
            overdueCount: overdueCount
        };
    }

    /**
     * @param {string} label
     * @returns {string}
     */
    function renderSecondaryBucketLabel(label) {
        if (asString(label).trim() === '') {
            return '';
        }

        return ''
            + '<div class="aa-executable-bucket-label-wrap mb-3">'
            + '<p class="text-xs font-semibold uppercase tracking-wide text-gray-500">' + escapeHtml(label) + '</p>'
            + '</div>';
    }

    /**
     * @param {string} key
     * @param {string} innerHtml
     * @param {string} [extraClass]
     * @returns {string}
     */
    function renderBucketWrapper(key, innerHtml, extraClass) {
        if (innerHtml === '') {
            return '';
        }

        var bucketClass = 'aa-executable-bucket';

        if (extraClass) {
            bucketClass += ' ' + extraClass;
        }

        return ''
            + '<div class="' + bucketClass + '" data-bucket-key="' + escapeHtml(key || 'default') + '">'
            + innerHtml
            + '</div>';
    }

    /**
     * @param {Array} items
     * @param {object} [options]
     * @param {object} [bucketContext]
     * @param {number} [startIndex]
     * @param {string} ulClasses
     * @returns {string}
     */
    function renderItemsUl(items, options, bucketContext, startIndex, ulClasses) {
        if (!Array.isArray(items) || items.length === 0) {
            return '';
        }

        var itemsHtml = renderBucketItemsHtml(items, options, bucketContext, startIndex);

        if (itemsHtml === '') {
            return '';
        }

        return '<ul class="' + ulClasses + '">' + itemsHtml + '</ul>';
    }

    /**
     * @param {string} label
     * @param {Array} items
     * @param {object} [options]
     * @param {object} [bucketContext]
     * @param {number} [startIndex]
     * @returns {string}
     */
    function renderSecondaryFollowingSection(label, items, options, bucketContext, startIndex) {
        var labelHtml = renderSecondaryBucketLabel(label);
        var itemsHtml = renderItemsUl(
            items,
            options,
            bucketContext,
            startIndex,
            'aa-executable-bucket-items space-y-2'
        );

        if (labelHtml === '' && itemsHtml === '') {
            return '';
        }

        return renderBucketWrapper('secondary', labelHtml + itemsHtml, 'mt-5');
    }

    /**
     * @param {string} followingContentHtml
     * @param {number} followingCount
     * @returns {string}
     */
    function renderFollowingTasksBlock(followingContentHtml, followingCount) {
        return ''
            + '<div class="aa-executable-following-tasks mt-2">'
            + '<div class="aa-executable-following-tasks-content space-y-2">'
            + followingContentHtml
            + '</div>'
            + '</div>';
    }

    /**
     * @param {object} [options]
     * @returns {object}
     */
    function optionsAssumingRenderable(options) {
        var base = normalizeOptions(options);
        var next = {};

        Object.keys(base).forEach(function (key) {
            next[key] = base[key];
        });
        next.assumeRenderable = true;

        return next;
    }

    /**
     * @param {Array<{item: object, bucket: object, bucketIndex: number}>} entries
     * @param {object} [options]
     * @param {object} listContext
     * @param {number} startIndex
     * @returns {string}
     */
    function renderFollowingEntriesHtml(entries, options, listContext, startIndex) {
        if (!Array.isArray(entries) || entries.length === 0) {
            return '';
        }

        var sections = [];
        var group = [];
        var groupBucket = null;
        var groupStartIndex = typeof startIndex === 'number' ? startIndex : 0;
        var trustedOptions = optionsAssumingRenderable(options);

        function flushGroup() {
            if (!groupBucket || group.length === 0) {
                return;
            }

            var key = asString(groupBucket.key).trim().toLowerCase();
            var label = asString(groupBucket.label).trim();
            var bucketContext = extendContext(listContext, {
                bucket: groupBucket,
                bucketIndex: groupBucket.__bucketIndex
            });
            var items = group.map(function (entry) {
                return entry.item;
            });

            if (key === 'secondary') {
                sections.push(renderSecondaryFollowingSection(
                    label,
                    items,
                    trustedOptions,
                    bucketContext,
                    groupStartIndex
                ));
            } else {
                sections.push(renderItemsUl(
                    items,
                    trustedOptions,
                    bucketContext,
                    groupStartIndex,
                    'aa-executable-bucket-items aa-executable-bucket-items-following space-y-2'
                ));
            }

            groupStartIndex += group.length;
            group = [];
            groupBucket = null;
        }

        entries.forEach(function (entry) {
            var bucket = entry && entry.bucket && typeof entry.bucket === 'object'
                ? entry.bucket
                : { key: 'default', label: '' };
            var bucketIdentity = String(entry.bucketIndex) + ':' + asString(bucket.key).trim().toLowerCase();
            var currentIdentity = groupBucket
                ? String(groupBucket.__bucketIndex) + ':' + asString(groupBucket.key).trim().toLowerCase()
                : '';

            if (groupBucket && bucketIdentity !== currentIdentity) {
                flushGroup();
            }

            if (!groupBucket) {
                groupBucket = {
                    key: bucket.key,
                    label: bucket.label,
                    __bucketIndex: entry.bucketIndex
                };
            }

            group.push(entry);
        });

        flushGroup();

        return sections.join('');
    }

    /**
     * @param {{items: Array}} collection
     * @param {object} [options]
     * @param {object} listContext
     * @returns {string}
     */
    function renderListBucketsBodyFromCollection(collection, options, listContext) {
        var entries = collection && Array.isArray(collection.items) ? collection.items : [];

        if (entries.length === 0) {
            return '';
        }

        var trustedOptions = optionsAssumingRenderable(options);
        var topEntry = entries[0];
        var followingEntries = entries.slice(1);
        var topBucket = topEntry.bucket && typeof topEntry.bucket === 'object'
            ? topEntry.bucket
            : { key: 'default', label: '' };
        var topKey = asString(topBucket.key).trim().toLowerCase();
        var topBucketContext = extendContext(listContext, {
            bucket: topBucket,
            bucketIndex: topEntry.bucketIndex
        });
        var topHtml = renderBucketWrapper(
            topKey,
            renderItemsUl(
                [topEntry.item],
                trustedOptions,
                topBucketContext,
                0,
                'aa-executable-bucket-items aa-executable-bucket-items-top space-y-2'
            )
        );
        var followingHtml = renderFollowingEntriesHtml(
            followingEntries,
            trustedOptions,
            listContext,
            1
        );
        var bodyHtml = topHtml;

        if (followingEntries.length > 0 && followingHtml !== '') {
            bodyHtml += renderFollowingTasksBlock(followingHtml, followingEntries.length);
        }

        return bodyHtml;
    }

    /**
     * @param {Array} buckets
     * @param {object} [options]
     * @param {object} listContext
     * @returns {string}
     */
    function renderListBucketsBody(buckets, options, listContext) {
        return renderListBucketsBodyFromCollection(
            prepareRenderableListCollection(buckets, options, listContext),
            options,
            listContext
        );
    }

    /**
     * @param {object} bucket
     * @param {object} [options]
     * @param {object} [context]
     * @param {number} [bucketIndex]
     * @returns {string}
     */
    function renderBucket(bucket, options, context, bucketIndex) {
        if (!bucket || typeof bucket !== 'object') {
            return '';
        }

        var bucketContext = extendContext(context, {
            bucket: bucket,
            bucketIndex: typeof bucketIndex === 'number' ? bucketIndex : undefined
        });
        var key = asString(bucket.key).trim().toLowerCase();
        var label = asString(bucket.label).trim();
        var items = Array.isArray(bucket.items) ? bucket.items : [];
        var labelHtml = key === 'secondary' ? renderSecondaryBucketLabel(label) : '';
        var itemsHtml = items.length > 0
            ? renderBucketItemsHtml(items, options, bucketContext, 0)
            : '';

        if (itemsHtml === '' && labelHtml === '') {
            return '';
        }

        var extraClass = key === 'secondary' && labelHtml !== '' ? 'mt-5' : '';

        return renderBucketWrapper(
            key,
            labelHtml + '<ul class="aa-executable-bucket-items space-y-2">' + itemsHtml + '</ul>',
            extraClass
        );
    }

    /**
     * @param {unknown} sourceOrCategory
     * @returns {string}
     */
    function defaultSourceLabel(sourceOrCategory) {
        var normalized = asString(sourceOrCategory).trim().toLowerCase();

        if (normalized === 'system' || normalized === 'agenda_app') {
            return 'Agenda app';
        }

        if (normalized === 'user') {
            return 'Mis listas';
        }

        if (normalized === 'ai') {
            return 'IA';
        }

        if (normalized === '') {
            return '';
        }

        return normalized.replace(/_/g, ' ').replace(/^\w/, function (char) {
            return char.toUpperCase();
        });
    }

    /**
     * @param {object} list
     * @returns {string}
     */
    function resolveSourceLabel(list) {
        if (!list || typeof list !== 'object') {
            return '';
        }

        var explicitLabel = asString(list.source_label).trim();

        if (explicitLabel !== '') {
            return explicitLabel;
        }

        var category = asString(list.source_category).trim().toLowerCase();
        var fromCategory = defaultSourceLabel(category);

        if (fromCategory !== '') {
            return fromCategory;
        }

        return defaultSourceLabel(list.source);
    }

    /**
     * @param {object} list
     * @returns {string}
     */
    function resolveSourceLabelModifier(list) {
        if (!list || typeof list !== 'object') {
            return '';
        }

        var category = asString(list.source_category).trim().toLowerCase();
        var source = asString(list.source).trim().toLowerCase();

        if (category === 'user' || source === 'user') {
            return ' aa-executable-list-source-label--user text-emerald-700';
        }

        if (category === 'agenda_app' || source === 'system') {
            return ' aa-executable-list-source-label--agenda-app text-blue-700';
        }

        return '';
    }

    /**
     * @param {object} list
     * @returns {string}
     */
    function renderSourceLabel(list) {
        var label = resolveSourceLabel(list);

        if (label === '') {
            return '';
        }

        var modifier = resolveSourceLabelModifier(list);
        var colorClass = modifier !== '' ? modifier : ' text-gray-500';

        return ''
            + '<span class="aa-executable-list-source-label text-xs truncate min-w-0' + colorClass + '">'
            + escapeHtml(label)
            + '</span>';
    }

    /**
     * @param {object} list
     * @returns {boolean}
     */
    function listHasExpandableDetails(list) {
        if (!list || typeof list !== 'object') {
            return false;
        }

        var descriptionText = asString(list.description).trim();
        var importance = Number(list.importance);

        return descriptionText !== ''
            || (!Number.isNaN(importance) && importance !== 0);
    }

    /**
     * @param {string} listId
     * @returns {string}
     */
    function renderListDetailsToggle(listId) {
        return ''
            + '<button type="button"'
            + ' data-aa-list-details-toggle="1"'
            + ' data-list-id="' + listId + '"'
            + ' onclick="event.stopPropagation()"'
            + ' aria-expanded="false"'
            + ' aria-controls="aa-list-details-' + listId + '"'
            + ' class="aa-executable-list-details-toggle shrink-0 text-xs text-gray-500 underline hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300/60 rounded">'
            + 'Ver más'
            + '</button>';
    }

    /**
     * @param {object} list
     * @param {string} listId
     * @returns {string}
     */
    function renderListDetailsBlock(list, listId) {
        var parts = '';
        var descriptionText = asString(list.description).trim();
        var importance = Number(list.importance);
        var sourceLabelHtml = renderSourceLabel(list);

        if (sourceLabelHtml !== '') {
            parts += ''
                + '<p class="aa-executable-list-details-source mb-1">'
                + sourceLabelHtml
                + '</p>';
        }

        if (descriptionText !== '') {
            parts += ''
                + '<p class="aa-executable-list-details-description text-sm text-gray-600">'
                + escapeHtml(descriptionText)
                + '</p>';
        }

        if (!Number.isNaN(importance) && importance !== 0) {
            parts += ''
                + '<p class="aa-executable-list-details-importance text-xs text-gray-500 mt-1">'
                + 'Importancia: '
                + escapeHtml(String(importance))
                + '</p>';
        }

        if (parts === '') {
            return '';
        }

        return ''
            + '<div class="aa-executable-list-details mt-2"'
            + ' id="aa-list-details-' + listId + '"'
            + ' data-list-id="' + listId + '">'
            + parts
            + '</div>';
    }

    /**
     * @param {number} total
     * @returns {string}
     */
    function formatListTotalLabel(total) {
        var count = Math.max(0, total | 0);

        if (count === 0) {
            return 'Sin tareas';
        }

        if (count === 1) {
            return '1 tarea';
        }

        return String(count) + ' tareas';
    }

    /**
     * @param {number} count
     * @returns {string}
     */
    function formatListPertinentLabel(count) {
        var value = Math.max(0, count | 0);

        if (value <= 0) {
            return '';
        }

        if (value === 1) {
            return '1 pertinente';
        }

        return String(value) + ' pertinentes';
    }

    /**
     * @param {number} count
     * @returns {string}
     */
    function formatListOverdueLabel(count) {
        var value = Math.max(0, count | 0);

        if (value <= 0) {
            return '';
        }

        if (value === 1) {
            return '1 vencida';
        }

        return String(value) + ' vencidas';
    }

    /**
     * @param {string} text
     * @param {string} className
     * @returns {string}
     */
    function renderListSummaryPart(text, className) {
        if (asString(text).trim() === '') {
            return '';
        }

        return ''
            + '<span class="' + className + '">'
            + escapeHtml(text)
            + '</span>';
    }

    /**
     * @param {{total?: number, pertinentCount?: number, overdueCount?: number}} collection
     * @param {{leadingSeparator?: boolean}} [options]
     * @returns {string}
     */
    function renderListTemporalSummary(collection, options) {
        var safe = collection && typeof collection === 'object'
            ? collection
            : { total: 0, pertinentCount: 0, overdueCount: 0 };
        var opts = options && typeof options === 'object' ? options : {};
        var parts = [];
        var totalPart = renderListSummaryPart(
            formatListTotalLabel(safe.total || 0),
            'aa-executable-list-summary-total text-xs text-gray-500 shrink-0'
        );
        var pertinentPart = renderListSummaryPart(
            formatListPertinentLabel(safe.pertinentCount || 0),
            'aa-executable-list-summary-pertinent text-xs text-emerald-700/80 shrink-0'
        );
        var overduePart = renderListSummaryPart(
            formatListOverdueLabel(safe.overdueCount || 0),
            'aa-executable-list-summary-overdue text-xs text-red-600/80 shrink-0'
        );
        var separator = '<span class="aa-executable-list-summary-sep text-xs text-gray-400 shrink-0" aria-hidden="true"> · </span>';

        if (totalPart !== '') {
            parts.push(totalPart);
        }

        if (pertinentPart !== '') {
            parts.push(pertinentPart);
        }

        if (overduePart !== '') {
            parts.push(overduePart);
        }

        if (parts.length === 0) {
            return '';
        }

        var leading = opts.leadingSeparator ? separator : '';

        return ''
            + '<span class="aa-executable-list-summary inline-flex flex-wrap items-center">'
            + leading
            + parts.join(separator)
            + '</span>';
    }

    /**
     * @param {object} list
     * @param {{total?: number, pertinentCount?: number, overdueCount?: number}} [collection]
     * @returns {string}
     */
    function renderListHeaderMeta(list, collection) {
        var listId = escapeHtml(asString(list.id));
        var toggleHtml = listHasExpandableDetails(list)
            ? renderListDetailsToggle(listId)
            : '';
        var metaContent = toggleHtml;

        if (metaContent === '') {
            return '';
        }

        return ''
            + '<div class="aa-executable-list-header-meta flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5 min-w-0">'
            + metaContent
            + '</div>';
    }

    /**
     * @param {object} capabilities
     * @param {object} [list]
     * @returns {boolean}
     */
    function listHasOptionsMenu(capabilities, list) {
        return !!capabilities.can_archive
            || !!capabilities.can_edit
            || !!capabilities.can_restore_archived_tasks
            || !!capabilities.can_delete
            || isUserManualList(list);
    }

    /**
     * @param {object} capabilities
     * @param {object} list
     * @returns {string}
     */
    function renderListOptionsMenuItems(capabilities, list) {
        var items = '';
        var listId = escapeHtml(asString(list.id));
        var listTitle = escapeHtml(asString(list.title));
        var listDescription = escapeHtml(asString(list.description || ''));
        var listImportance = escapeHtml(asString(
            list.importance !== undefined && list.importance !== null ? list.importance : 0
        ));

        if (isUserManualList(list)) {
            items += ''
                + '<button type="button" role="menuitem"'
                + ' data-aa-list-add-task="1"'
                + ' data-list-id="' + listId + '"'
                + ' onclick="event.stopPropagation()"'
                + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">'
                + '+ Tarea'
                + '</button>';
        }

        if (capabilities.can_edit) {
            items += ''
                + '<button type="button" role="menuitem"'
                + ' data-aa-list-edit="1"'
                + ' data-list-id="' + listId + '"'
                + ' data-list-title="' + listTitle + '"'
                + ' data-list-description="' + listDescription + '"'
                + ' data-list-importance="' + listImportance + '"'
                + ' onclick="event.stopPropagation()"'
                + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">'
                + 'Editar lista'
                + '</button>';
        }

        if (capabilities.can_restore_archived_tasks) {
            items += ''
                + '<button type="button" role="menuitem"'
                + ' data-aa-list-restore-archived-tasks="1"'
                + ' data-list-id="' + listId + '"'
                + ' onclick="event.stopPropagation()"'
                + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">'
                + 'Desarchivar tareas'
                + '</button>';
        }

        if (capabilities.can_archive) {
            items += ''
                + '<button type="button" role="menuitem"'
                + ' data-tasks-action="archive-list"'
                + ' data-list-id="' + listId + '"'
                + ' onclick="event.stopPropagation()"'
                + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">'
                + 'Archivar lista'
                + '</button>';
        }

        if (capabilities.can_delete) {
            items += ''
                + '<button type="button" role="menuitem"'
                + ' data-tasks-action="delete-list"'
                + ' data-list-id="' + listId + '"'
                + ' onclick="event.stopPropagation()"'
                + ' class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-gray-50">'
                + 'Eliminar lista'
                + '</button>';
        }

        return items;
    }

    /**
     * @param {object} capabilities
     * @param {object} list
     * @returns {string}
     */
    function renderListOptionsMenu(capabilities, list) {
        if (!listHasOptionsMenu(capabilities, list)) {
            return '';
        }

        var menuItems = renderListOptionsMenuItems(capabilities, list);

        if (menuItems === '') {
            return '';
        }

        var listId = escapeHtml(asString(list.id));

        return ''
            + '<div class="relative aa-executable-list-options shrink-0">'
            + '<button type="button"'
            + ' data-aa-list-options-trigger="1"'
            + ' data-list-id="' + listId + '"'
            + ' onclick="event.stopPropagation()"'
            + ' title="Opciones de lista"'
            + ' aria-label="Opciones de lista"'
            + ' aria-haspopup="menu"'
            + ' aria-expanded="false"'
            + ' class="aa-executable-list-options-trigger aa-options-trigger-flat">'
            + '<svg class="w-6 h-6 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            + '<circle cx="5" cy="12" r="1.75"/>'
            + '<circle cx="12" cy="12" r="1.75"/>'
            + '<circle cx="19" cy="12" r="1.75"/>'
            + '</svg>'
            + '</button>'
            + '<div class="hidden aa-executable-list-options-menu absolute right-0 top-full z-20 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"'
            + ' role="menu"'
            + ' data-list-id="' + listId + '">'
            + menuItems
            + '</div>'
            + '</div>';
    }

    /**
     * @param {object} list
     * @returns {boolean}
     */
    function isUserManualList(list) {
        if (!list || typeof list !== 'object') {
            return false;
        }

        return asString(list.source_category).trim().toLowerCase() === 'user'
            && asString(list.managed_by || 'user').trim().toLowerCase() === 'user';
    }

    /**
     * @param {object} list
     * @param {object} [options]
     * @param {number} [listIndex]
     * @returns {string}
     */
    function renderList(list, options, listIndex) {
        if (!list || typeof list !== 'object') {
            return '';
        }

        var listContext = {
            list: list,
            source: list.source || '',
            listIndex: typeof listIndex === 'number' ? listIndex : undefined,
            firstItemState: { marked: false }
        };
        var listId = escapeHtml(list.id);
        var source = escapeHtml(list.source || '');
        var title = escapeHtml(list.title || 'Lista sin título');
        var listIdAttr = escapeHtml(asString(list.id));
        var capabilities = list.capabilities && typeof list.capabilities === 'object'
            ? list.capabilities
            : {};
        var buckets = Array.isArray(list.buckets) ? list.buckets : [];
        var renderableCollection = prepareRenderableListCollection(buckets, options, listContext);
        var bodyHtml = renderListBucketsBodyFromCollection(renderableCollection, options, listContext);

        if (bodyHtml === '' && asString(list.source).trim().toLowerCase() === 'user') {
            bodyHtml = '<p class="text-sm text-gray-500 aa-executable-list-empty-pending">No hay tareas pendientes en esta lista.</p>';
        }

        var headerMetaHtml = renderListHeaderMeta(list, renderableCollection);
        var detailsHtml = listHasExpandableDetails(list)
            ? renderListDetailsBlock(list, listIdAttr)
            : '';
        var optionsMenuHtml = renderListOptionsMenu(capabilities, list);
        var headerActionsHtml = ''
            + '<div class="flex items-center gap-1 shrink-0">'
            + optionsMenuHtml
            + '</div>';
        return ''
            + '<details class="aa-executable-list-card aa-task-list-card group bg-white rounded-xl shadow border border-gray-200"'
            + ' data-list-id="' + listId + '"'
            + ' data-list-source="' + source + '">'
            + '<summary class="px-4 py-5 bg-white cursor-pointer list-none">'
            + '<div class="flex items-start justify-between gap-3">'
            + '<div class="min-w-0 flex-1">'
            + '<div class="flex items-center gap-1.5 min-w-0">'
            + '<span class="aa-executable-list-icon flex items-center justify-center w-8 h-8 text-gray-600 shrink-0" aria-hidden="true">'
            + '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h.01M8 6h12M4 12h.01M8 12h12M4 18h.01M8 18h12"/>'
            + '</svg>'
            + '</span>'
            + '<h3 class="text-lg font-semibold text-gray-600 min-w-0">' + title + '</h3>'
            + '</div>'
            + headerMetaHtml
            + detailsHtml
            + '</div>'
            + headerActionsHtml
            + '</div>'
            + '</summary>'
            + '<div class="aa-executable-list-body px-4 py-4">' + bodyHtml + '</div>'
            + '</details>';
    }

    /**
     * @param {Array} lists
     * @param {object} [options]
     * @returns {string}
     */
    function renderFeed(lists, options) {
        if (!Array.isArray(lists) || lists.length === 0) {
            return '';
        }

        return lists.map(function (list, listIndex) {
            return renderList(list, options, listIndex);
        }).join('');
    }

    var api = {
        escapeHtml: escapeHtml,
        resolveSourceLabel: resolveSourceLabel,
        resolveSourceLabelModifier: resolveSourceLabelModifier,
        renderSourceLabel: renderSourceLabel,
        defaultSourceLabel: defaultSourceLabel,
        prepareRenderableListCollection: prepareRenderableListCollection,
        renderFeed: renderFeed,
        renderList: renderList,
        renderBucket: renderBucket,
        renderItem: renderItem,
        renderItemActions: renderItemActions,
        resolveRecommendationKey: resolveRecommendationKey,
        resolveTasksChannelTaskId: resolveTasksChannelTaskId,
        hasVisibleActions: hasVisibleActions,
        parseDueAtToDate: parseDueAtToDate,
        isDueSoonFromDueAt: isDueSoonFromDueAt,
        isUserManualList: isUserManualList
    };

    if (typeof window !== 'undefined') {
        window.AAExecutableListRenderer = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
