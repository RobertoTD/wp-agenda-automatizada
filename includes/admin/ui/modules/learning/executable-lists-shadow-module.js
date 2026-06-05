/**
 * Executable Lists Shadow Module — carga MC10A sin render visible.
 *
 * Valida nonce/config/payload real en navegador; no modifica DOM ni pipelines legacy.
 */
(function () {
    'use strict';

    var shadowState = {
        payload: null,
        error: null,
        loadedAt: null
    };

    function isDebugEnabled() {
        if (window.AA_EXECUTABLE_LISTS_DEBUG === true) {
            return true;
        }

        var cfg = window.AA_EXECUTABLE_LISTS_DATA;

        return !!(cfg && cfg.debug === true);
    }

    function debugLog(message, detail) {
        if (!isDebugEnabled()) {
            return;
        }

        if (detail !== undefined) {
            console.info('[Executable Lists Shadow]', message, detail);
            return;
        }

        console.info('[Executable Lists Shadow]', message);
    }

    function publishShadowState() {
        window.AA_EXECUTABLE_LISTS_SHADOW = {
            payload: shadowState.payload,
            error: shadowState.error,
            loadedAt: shadowState.loadedAt
        };
    }

    /**
     * @returns {Promise<void>}
     */
    function loadShadowFeed() {
        var service = window.ExecutableListsService;

        if (!service || typeof service.getFeed !== 'function') {
            shadowState.error = {
                message: 'ExecutableListsService no disponible.',
                code: 'service_unavailable'
            };
            shadowState.loadedAt = new Date().toISOString();
            publishShadowState();
            debugLog('service unavailable');
            return Promise.resolve();
        }

        return service.getFeed()
            .then(function (payload) {
                shadowState.payload = payload;
                shadowState.error = null;
                shadowState.loadedAt = new Date().toISOString();
                publishShadowState();
                debugLog('feed loaded', {
                    listCount: Array.isArray(payload.lists) ? payload.lists.length : 0,
                    metaVersion: payload.meta && payload.meta.version
                });
            })
            .catch(function (err) {
                shadowState.payload = null;
                shadowState.error = {
                    message: (err && err.message) ? err.message : 'No se pudo cargar el feed.',
                    code: (err && err.code) ? err.code : 'unknown_error',
                    meta: (err && err.meta) ? err.meta : null
                };
                shadowState.loadedAt = new Date().toISOString();
                publishShadowState();
                debugLog('feed error', shadowState.error);
            });
    }

    function initShadowModule() {
        if (!document.getElementById('aa-tasks-module-root')) {
            return;
        }

        publishShadowState();
        loadShadowFeed();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initShadowModule);
    } else {
        initShadowModule();
    }
})();
