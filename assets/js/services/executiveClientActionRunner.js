/**
 * Executive Client Action Runner — ejecuta client_action post aa_executive_action.
 *
 * Compartido entre Dashboard y Ejecutor/Listas.
 * Depends on window.LearningActionHandlers para type handler.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    /**
     * @param {object|null|undefined} clientAction
     * @param {{showError?:function(string):void, onReload?:function():Promise<void>}} [options]
     * @returns {Promise<void>}
     */
    function run(clientAction, options) {
        var opts = options || {};
        var showError = typeof opts.showError === 'function' ? opts.showError : function () {};
        var onReload = typeof opts.onReload === 'function'
            ? opts.onReload
            : function () {
                return Promise.resolve();
            };

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
            var handlers = globalRoot.LearningActionHandlers;
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
                showError: showError
            }))
                .then(function (result) {
                    if (result && result.reload === true) {
                        return onReload();
                    }

                    return undefined;
                })
                .catch(function (err) {
                    showError((err && err.message) ? err.message : 'No se pudo ejecutar la acción.');
                });
        }

        return Promise.resolve();
    }

    var api = {
        run: run
    };

    globalRoot.AAExecutiveClientActionRunner = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
