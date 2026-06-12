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
                + ' class="' + btnClass(false) + ' text-blue-700 bg-blue-50 hover:bg-blue-100 border-blue-200">'
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
                + ' class="' + btnClass(false) + ' text-blue-700 bg-blue-50 hover:bg-blue-100 border-blue-200">'
                + escapeHtml(label)
                + '</button>';
        }

        if (type === 'status') {
            var itemSource = resolveItemSource(item);
            var taskId = asString(item.id).trim();
            var targetStatus = asString(action.target_status).trim().toLowerCase();

            if (targetStatus === 'done') {
                if (itemSource === 'system') {
                    if (recommendationKey === '') {
                        return '';
                    }

                    return ''
                        + '<button type="button" data-learning-action="complete"'
                        + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                        + ' class="' + btnClass(false) + ' text-green-700 hover:text-green-800 border-green-200 bg-green-50">'
                        + escapeHtml(label || 'Completar')
                        + '</button>';
                }

                if (itemSource === 'user') {
                    if (taskId === '') {
                        return '';
                    }

                    return ''
                        + '<button type="button" data-tasks-action="complete" data-task-id="' + escapeHtml(taskId) + '"'
                        + ' class="' + btnClass(false) + ' text-green-700 hover:text-green-800 border-green-200 bg-green-50">'
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
                        + escapeHtml(label || 'Ignorar')
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
                        + escapeHtml(label || 'Ignorar')
                        + '</button>';
                }

                if (recommendationKey === '') {
                    return '';
                }

                return ''
                    + '<button type="button" data-learning-action="dismiss"'
                    + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                    + escapeHtml(label || 'Ignorar')
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
                + ' class="' + btnClass(false) + ' text-blue-700 bg-blue-50 hover:bg-blue-100 border-blue-200">'
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
                + ' class="' + btnClass(false) + ' text-blue-700 bg-blue-50 hover:bg-blue-100 border-blue-200">'
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
                    + ' class="' + btnClass(false) + ' text-green-700 hover:text-green-800 border-green-200 bg-green-50">'
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
                    + 'Ignorar'
                    + '</button>'
                );
            } else if (recommendationKey !== '') {
                actions.push(
                    '<button type="button" data-learning-action="dismiss"'
                    + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                    + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                    + 'Ignorar'
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
            + '<p class="aa-executable-item-desc-preview text-sm text-gray-600 mt-1">'
            + escapeHtml(description)
            + '</p>';
    }

    /**
     * @param {string} description
     * @returns {string}
     */
    function renderItemDescriptionFull(description) {
        return ''
            + '<div class="aa-executable-item-desc-full text-sm text-gray-600">'
            + escapeHtml(description)
            + '</div>';
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function renderItemExpandedMeta(item) {
        var metaHtml = '';

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
            + ' class="aa-executable-task-options-trigger inline-flex items-center justify-center w-8 h-8 text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-300/60 transition-colors">'
            + '<svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
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
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>'
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
            + renderItemSummaryChevron()
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

        if (!callbackAllows(opts.shouldRenderItem, [item, itemContext])) {
            return '';
        }

        var itemId = escapeHtml(item.id);
        var source = escapeHtml(item.source || '');
        var title = escapeHtml(item.title || '');
        var descriptionText = asString(item.description).trim();
        var isDone = asString(item.status).toLowerCase() === 'done';
        var titleClass = isDone
            ? 'aa-executable-item-title text-sm text-gray-400 line-through'
            : 'aa-executable-item-title text-sm font-semibold text-gray-900';
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
            + '<p class="' + titleClass + '">' + title + '</p>'
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
        var itemsHtml = items.length > 0
            ? items.map(function (item, itemIndex) {
                return renderItem(item, options, bucketContext, itemIndex);
            }).join('')
            : '';
        var labelHtml = '';

        if (label !== '' && (key === 'primary' || key === 'secondary')) {
            labelHtml = ''
                + '<div class="aa-executable-bucket-label-wrap mb-3">'
                + '<p class="text-xs font-semibold uppercase tracking-wide text-gray-500">' + escapeHtml(label) + '</p>'
                + '</div>';
        }

        if (itemsHtml === '' && labelHtml === '') {
            return '';
        }

        var bucketClass = key === 'secondary' && labelHtml !== ''
            ? 'aa-executable-bucket mt-5'
            : 'aa-executable-bucket';

        return ''
            + '<div class="' + bucketClass + '" data-bucket-key="' + escapeHtml(key || 'default') + '">'
            + labelHtml
            + '<ul class="aa-executable-bucket-items space-y-3">' + itemsHtml + '</ul>'
            + '</div>';
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
            + '<span class="aa-executable-list-source-label block text-xs truncate' + colorClass + '">'
            + escapeHtml(label)
            + '</span>';
    }

    /**
     * @param {object} capabilities
     * @returns {boolean}
     */
    function listHasOptionsMenu(capabilities) {
        return !!capabilities.can_archive
            || !!capabilities.can_edit
            || !!capabilities.can_restore_archived_tasks
            || !!capabilities.can_delete;
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
        if (!listHasOptionsMenu(capabilities)) {
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
            + ' class="aa-executable-list-options-trigger inline-flex items-center justify-center w-8 h-8 text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-300/60 transition-colors">'
            + '<svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
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
        var description = list.description
            ? '<p class="text-sm text-gray-600 mt-1">' + escapeHtml(list.description) + '</p>'
            : '';
        var capabilities = list.capabilities && typeof list.capabilities === 'object'
            ? list.capabilities
            : {};
        var buckets = Array.isArray(list.buckets) ? list.buckets : [];
        var bucketsHtml = buckets.map(function (bucket, bucketIndex) {
            return renderBucket(bucket, options, listContext, bucketIndex);
        }).join('');
        var bodyHtml = bucketsHtml;

        if (bodyHtml === '' && asString(list.source).trim().toLowerCase() === 'user') {
            bodyHtml = '<p class="text-sm text-gray-500 aa-executable-list-empty-pending">No hay tareas pendientes en esta lista.</p>';
        }

        var sourceLabelHtml = renderSourceLabel(list);
        var optionsMenuHtml = renderListOptionsMenu(capabilities, list);
        var chevronHtml = ''
            + '<svg class="aa-chevron w-5 h-5 text-gray-400 transition-transform duration-200 flex-shrink-0"'
            + ' fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>'
            + '</svg>';
        var headerActionsHtml = ''
            + '<div class="flex items-center gap-1 shrink-0">'
            + optionsMenuHtml
            + chevronHtml
            + '</div>';
        var headerGradient = 'from-gray-50 to-white';

        return ''
            + '<details class="aa-executable-list-card aa-task-list-card group bg-white rounded-xl shadow border border-gray-200"'
            + ' data-list-id="' + listId + '"'
            + ' data-list-source="' + source + '">'
            + '<summary class="px-4 py-4 border-b border-gray-100 bg-gradient-to-r ' + headerGradient + ' cursor-pointer list-none">'
            + '<div class="flex items-start justify-between gap-3">'
            + '<div class="min-w-0 flex-1">'
            + '<h4 class="text-base font-semibold text-gray-900">' + title + '</h4>'
            + sourceLabelHtml
            + description
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
        renderFeed: renderFeed,
        renderList: renderList,
        renderBucket: renderBucket,
        renderItem: renderItem,
        renderItemActions: renderItemActions,
        resolveRecommendationKey: resolveRecommendationKey,
        resolveTasksChannelTaskId: resolveTasksChannelTaskId,
        hasVisibleActions: hasVisibleActions
    };

    if (typeof window !== 'undefined') {
        window.AAExecutableListRenderer = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
