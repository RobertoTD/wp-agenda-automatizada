/**
 * Learning Recommendation Renderer — funciones puras compartidas para cards de recomendaciones.
 *
 * Depends on window.LearningActionHandlers (optional, for handler actions and visibility).
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

    function btnClass(disabled) {
        var base = 'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors';
        if (disabled) {
            return base + ' text-gray-400 bg-gray-100 border-gray-200 cursor-not-allowed opacity-60';
        }
        return base;
    }

    /**
     * Resuelve la acción primaria renderizable desde item.action o legacy action_url.
     *
     * @param {object} item
     * @returns {{kind: 'navigate', url: string, label: string}|{kind: 'handler', handler: string, label: string}|null}
     */
    function resolvePrimaryAction(item) {
        var action = item.action;

        if (action && typeof action === 'object' && action.type === 'navigate') {
            var navigateUrl = action.url || '';

            if (navigateUrl) {
                return {
                    kind: 'navigate',
                    url: navigateUrl,
                    label: action.label || 'Ir'
                };
            }

            return null;
        }

        if (action && typeof action === 'object' && action.type === 'handler') {
            var registry = window.LearningActionHandlers;
            var handlerKey = action.handler || '';

            if (
                handlerKey
                && registry
                && typeof registry.get === 'function'
                && typeof registry.isAvailable === 'function'
                && registry.get(handlerKey)
                && registry.isAvailable(action, item)
            ) {
                return {
                    kind: 'handler',
                    handler: handlerKey,
                    label: action.label || 'Ir'
                };
            }

            return null;
        }

        var legacyUrl = item.action_url || '';

        if (legacyUrl) {
            return {
                kind: 'navigate',
                url: legacyUrl,
                label: item.action_label || 'Ir'
            };
        }

        return null;
    }

    /**
     * Filtra recomendaciones ocultas en runtime (p. ej. install_pwa en standalone).
     *
     * @param {Array} items
     * @returns {Array}
     */
    function filterRecommendationsForRender(items) {
        if (!items || !items.length) {
            return [];
        }

        var registry = window.LearningActionHandlers;

        return items.filter(function (item) {
            if (!item || typeof item !== 'object') {
                return false;
            }

            var action = item.action;

            if (
                !action
                || typeof action !== 'object'
                || action.type !== 'handler'
                || !registry
                || typeof registry.shouldShowRecommendation !== 'function'
            ) {
                return true;
            }

            return registry.shouldShowRecommendation(action, item) === true;
        });
    }

    /**
     * @param {object} [options]
     * @returns {{showDefer:boolean,showDismiss:boolean,showHandlerPrimary:boolean,wrapper:string}}
     */
    function normalizeRenderOptions(options) {
        var opts = options || {};

        return {
            showDefer: opts.showDefer !== false,
            showDismiss: opts.showDismiss !== false,
            showHandlerPrimary: opts.showHandlerPrimary !== false,
            wrapper: opts.wrapper !== undefined ? opts.wrapper : 'li'
        };
    }

    /**
     * @param {{list_1?:Array,list_2?:Array}} data
     * @returns {object|null}
     */
    function pickFirstVisibleRecommendation(data) {
        var payload = data || {};
        var list1 = filterRecommendationsForRender(payload.list_1 || []);
        var list2 = filterRecommendationsForRender(payload.list_2 || []);

        if (list1.length > 0) {
            return list1[0];
        }

        if (list2.length > 0) {
            return list2[0];
        }

        return null;
    }

    /**
     * @param {object} item
     * @param {object} [options] showDefer, showDismiss, showHandlerPrimary, wrapper ('li'|'div'|false)
     * @returns {string}
     */
    function renderRecommendationCard(item, options) {
        var opts = normalizeRenderOptions(options);
        var key = escapeHtml(item.key || '');
        var title = escapeHtml(item.title || '');
        var description = escapeHtml(item.description || '');
        var primaryAction = resolvePrimaryAction(item);

        var actions = [];

        if (primaryAction && primaryAction.kind === 'navigate') {
            actions.push(
                '<a href="' + escapeHtml(primaryAction.url) + '"'
                + ' class="' + btnClass(false) + ' text-blue-700 bg-blue-50 hover:bg-blue-100 border-blue-200">'
                + escapeHtml(primaryAction.label)
                + '</a>'
            );
        } else if (primaryAction && primaryAction.kind === 'handler' && opts.showHandlerPrimary) {
            actions.push(
                '<button type="button" data-learning-action="primary-handler"'
                + ' data-recommendation-key="' + key + '"'
                + ' data-learning-handler="' + escapeHtml(primaryAction.handler) + '"'
                + ' class="' + btnClass(false) + ' text-blue-700 bg-blue-50 hover:bg-blue-100 border-blue-200">'
                + escapeHtml(primaryAction.label)
                + '</button>'
            );
        }

        if (opts.showDefer && item.can_defer) {
            actions.push(
                '<button type="button" data-learning-action="defer" data-recommendation-key="' + key + '"'
                + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                + 'Ahora no'
                + '</button>'
            );
        }

        if (opts.showDismiss && item.can_dismiss) {
            actions.push(
                '<button type="button" data-learning-action="dismiss" data-recommendation-key="' + key + '"'
                + ' class="' + btnClass(false) + ' text-gray-600 bg-white hover:bg-gray-50 border-gray-300">'
                + 'Ignorar'
                + '</button>'
            );
        }

        var actionsHtml = actions.length > 0
            ? '<div class="flex flex-wrap gap-2 mt-3 aa-learning-card-actions">' + actions.join('') + '</div>'
            : '';

        var innerHtml =
            '<p class="text-sm font-semibold text-gray-900">' + title + '</p>'
            + '<p class="text-sm text-gray-600 mt-1">' + description + '</p>'
            + actionsHtml;

        if (opts.wrapper === false || opts.wrapper === null || opts.wrapper === '') {
            return innerHtml;
        }

        var tag = opts.wrapper === 'div' ? 'div' : 'li';
        var cardClass = tag === 'li'
            ? 'aa-learning-card rounded-lg border border-gray-200 bg-gray-50/80 p-4 transition-opacity duration-150'
            : 'aa-learning-card rounded-lg border border-gray-200 bg-gray-50/80 p-4';

        return '<' + tag + ' class="' + cardClass + '" data-recommendation-key="' + key + '">'
            + innerHtml
            + '</' + tag + '>';
    }

    window.AALearningRecommendationRenderer = {
        escapeHtml: escapeHtml,
        resolvePrimaryAction: resolvePrimaryAction,
        filterRecommendationsForRender: filterRecommendationsForRender,
        pickFirstVisibleRecommendation: pickFirstVisibleRecommendation,
        renderRecommendationCard: renderRecommendationCard
    };
})();
