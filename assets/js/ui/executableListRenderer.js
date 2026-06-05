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
     * @param {object|null|undefined} action
     * @param {object} item
     * @returns {string}
     */
    function renderPrimaryAction(action, item) {
        if (!action || typeof action !== 'object') {
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
     * @returns {string}
     */
    function renderSecondaryActions(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }

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

        if (capabilities.can_reactivate && recommendationKey !== '') {
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
     * @returns {string}
     */
    function renderItemActions(item) {
        var primary = renderPrimaryAction(item.primary_action, item);
        var secondary = renderSecondaryActions(item);
        var combined = primary + secondary;

        if (combined === '') {
            return '';
        }

        return '<div class="flex flex-wrap gap-2 mt-3 aa-executable-item-actions">' + combined + '</div>';
    }

    /**
     * @param {object} item
     * @returns {string}
     */
    function renderItem(item) {
        if (!item || typeof item !== 'object') {
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
        var actionsHtml = renderItemActions(item);

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
     * @returns {string}
     */
    function renderBucket(bucket) {
        if (!bucket || typeof bucket !== 'object') {
            return '';
        }

        var key = asString(bucket.key).trim().toLowerCase();
        var label = asString(bucket.label).trim();
        var items = Array.isArray(bucket.items) ? bucket.items : [];
        var itemsHtml = items.length > 0
            ? items.map(renderItem).join('')
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
     * @returns {string}
     */
    function renderList(list) {
        if (!list || typeof list !== 'object') {
            return '';
        }

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
        var bucketsHtml = buckets.map(renderBucket).join('');
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
     * @returns {string}
     */
    function renderFeed(lists) {
        if (!Array.isArray(lists) || lists.length === 0) {
            return '';
        }

        return lists.map(renderList).join('');
    }

    var api = {
        escapeHtml: escapeHtml,
        renderFeed: renderFeed,
        renderList: renderList,
        renderBucket: renderBucket,
        renderItem: renderItem,
        renderItemActions: renderItemActions,
        resolveRecommendationKey: resolveRecommendationKey
    };

    if (typeof window !== 'undefined') {
        window.AAExecutableListRenderer = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
