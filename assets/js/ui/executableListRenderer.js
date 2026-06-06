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

        if (capabilities.can_dismiss && recommendationKey !== '') {
            actions.push(
                '<button type="button" data-learning-action="dismiss"'
                + ' data-recommendation-key="' + escapeHtml(recommendationKey) + '"'
                + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                + 'Ignorar'
                + '</button>'
            );
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

        return '<div class="flex flex-wrap gap-2 mt-3 aa-executable-item-actions">' + combined + '</div>';
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
        var description = item.description
            ? '<p class="text-sm text-gray-600 mt-1">' + escapeHtml(item.description) + '</p>'
            : '';
        var isDone = asString(item.status).toLowerCase() === 'done';
        var titleClass = isDone
            ? 'text-sm text-gray-400 line-through'
            : 'text-sm font-semibold text-gray-900';
        var dueHtml = item.due_at
            ? '<span class="text-xs text-gray-500 ml-2">Vence: ' + escapeHtml(item.due_at) + '</span>'
            : '';
        var actionsHtml = renderItemActions(item, opts, itemContext);

        return ''
            + '<li class="aa-executable-item rounded-lg border border-gray-200 bg-gray-50/80 p-4"'
            + ' data-item-id="' + itemId + '"'
            + ' data-item-source="' + source + '">'
            + '<div class="flex flex-wrap items-center gap-1">'
            + '<p class="' + titleClass + '">' + title + '</p>'
            + dueHtml
            + '</div>'
            + description
            + actionsHtml
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
            listIndex: typeof listIndex === 'number' ? listIndex : undefined
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
        var archiveHtml = capabilities.can_archive
            ? '<button type="button" data-tasks-action="archive-list" data-list-id="' + listId + '"'
                + ' class="text-xs font-medium text-gray-500 hover:text-red-600 px-2 py-1 rounded border border-gray-200 whitespace-nowrap">'
                + 'Archivar'
                + '</button>'
            : '';
        var headerGradient = source === 'system'
            ? 'from-amber-50 to-white'
            : 'from-gray-50 to-white';

        return ''
            + '<article class="aa-executable-list-card aa-task-list-card bg-white rounded-xl shadow border border-gray-200 overflow-hidden"'
            + ' data-list-id="' + listId + '"'
            + ' data-list-source="' + source + '">'
            + '<div class="px-4 py-4 border-b border-gray-100 bg-gradient-to-r ' + headerGradient + '">'
            + '<div class="flex items-start justify-between gap-3">'
            + '<div class="min-w-0">'
            + '<h4 class="text-base font-semibold text-gray-900">' + title + '</h4>'
            + description
            + '</div>'
            + archiveHtml
            + '</div>'
            + '</div>'
            + '<div class="px-4 py-4">' + bucketsHtml + '</div>'
            + '</article>';
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
        renderFeed: renderFeed,
        renderList: renderList,
        renderBucket: renderBucket,
        renderItem: renderItem,
        renderItemActions: renderItemActions,
        resolveRecommendationKey: resolveRecommendationKey,
        hasVisibleActions: hasVisibleActions
    };

    if (typeof window !== 'undefined') {
        window.AAExecutableListRenderer = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
