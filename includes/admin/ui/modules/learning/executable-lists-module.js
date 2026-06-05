/**
 * Executable Lists Module — feed experimental visible solo en debug.
 *
 * Renderiza el feed MC7/MC9 sin sustituir pipelines legacy ni activar acciones reales.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var lastPayload = null;
    var isAvailabilityBound = false;
    var isInteractionGuardBound = false;

    var SESSION_STORAGE_DEBUG_KEY = 'AA_EXECUTABLE_LISTS_DEBUG';

    /**
     * @returns {boolean}
     */
    function isSessionStorageDebugEnabled() {
        try {
            var storage = globalRoot.sessionStorage;

            if (!storage || typeof storage.getItem !== 'function') {
                return false;
            }

            return storage.getItem(SESSION_STORAGE_DEBUG_KEY) === '1';
        } catch (err) {
            return false;
        }
    }

    function isDebugEnabled() {
        if (globalRoot.AA_EXECUTABLE_LISTS_DEBUG === true) {
            return true;
        }

        var cfg = globalRoot.AA_EXECUTABLE_LISTS_DATA;

        if (cfg && cfg.debug === true) {
            return true;
        }

        return isSessionStorageDebugEnabled();
    }

    function getExperimentalSection() {
        return document.getElementById('aa-executable-lists-experimental');
    }

    function getExperimentalRoot() {
        return document.getElementById('aa-executable-lists-root');
    }

    function setSectionVisible(visible) {
        var section = getExperimentalSection();

        if (!section) {
            return;
        }

        if (visible) {
            section.classList.remove('hidden');
        } else {
            section.classList.add('hidden');
        }
    }

    function getLearningRegistry() {
        return globalRoot.LearningActionHandlers || null;
    }

    /**
     * @returns {object}
     */
    function buildRenderOptions() {
        var registry = getLearningRegistry();

        return {
            shouldRenderItem: function (item) {
                var action = item && item.primary_action;

                if (!action || typeof action !== 'object' || action.type !== 'handler') {
                    return true;
                }

                if (!registry || typeof registry.shouldShowRecommendation !== 'function') {
                    return true;
                }

                return registry.shouldShowRecommendation(action, item) === true;
            },
            shouldRenderPrimaryAction: function (action, item) {
                if (!action || typeof action !== 'object') {
                    return true;
                }

                if (action.type !== 'handler') {
                    return true;
                }

                if (!registry || typeof registry.isAvailable !== 'function') {
                    return false;
                }

                return registry.isAvailable(action, item) === true;
            }
        };
    }

    function bindInteractionGuard(root) {
        if (!root || isInteractionGuardBound) {
            return;
        }

        isInteractionGuardBound = true;
        root.setAttribute('inert', '');
        root.classList.add('pointer-events-none');

        ['click', 'submit', 'keydown'].forEach(function (eventName) {
            root.addEventListener(eventName, function (event) {
                event.preventDefault();
                event.stopPropagation();
            }, true);
        });
    }

    function renderPayload(payload) {
        var root = getExperimentalRoot();
        var renderer = globalRoot.AAExecutableListRenderer;
        var lists = payload && Array.isArray(payload.lists) ? payload.lists : [];

        if (!root) {
            return;
        }

        if (!renderer || typeof renderer.renderFeed !== 'function') {
            root.innerHTML = '<p class="text-sm text-red-600">No se pudo inicializar el renderer executable.</p>';
            return;
        }

        root.innerHTML = lists.length > 0
            ? renderer.renderFeed(lists, buildRenderOptions())
            : '<p class="text-sm text-gray-500">No hay listas en el feed executable.</p>';
    }

    function showLoadError(message) {
        var root = getExperimentalRoot();

        if (root) {
            root.innerHTML = '<p class="text-sm text-red-600">' + String(message || 'No se pudo cargar el feed executable.') + '</p>';
        }
    }

    /**
     * @returns {Promise<void>}
     */
    function loadExperimentalFeed() {
        var service = globalRoot.ExecutableListsService;

        if (!service || typeof service.getFeed !== 'function') {
            showLoadError('ExecutableListsService no disponible.');
            return Promise.resolve();
        }

        return service.getFeed()
            .then(function (payload) {
                lastPayload = payload;
                renderPayload(payload);
            })
            .catch(function (err) {
                lastPayload = null;
                showLoadError((err && err.message) ? err.message : 'No se pudo cargar el feed executable.');
            });
    }

    function bindAvailabilityRerender() {
        var registry = getLearningRegistry();

        if (isAvailabilityBound || !registry || typeof registry.onAvailabilityChange !== 'function') {
            return;
        }

        isAvailabilityBound = true;

        registry.onAvailabilityChange(function () {
            if (lastPayload) {
                renderPayload(lastPayload);
            }
        });
    }

    function initExperimentalModule() {
        if (!document.getElementById('aa-tasks-module-root')) {
            return;
        }

        if (!isDebugEnabled()) {
            setSectionVisible(false);
            return;
        }

        var root = getExperimentalRoot();

        if (!root) {
            return;
        }

        setSectionVisible(true);
        bindInteractionGuard(root);
        bindAvailabilityRerender();
        loadExperimentalFeed();
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            isDebugEnabled: isDebugEnabled,
            isSessionStorageDebugEnabled: isSessionStorageDebugEnabled,
            buildRenderOptions: buildRenderOptions
        };
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExperimentalModule);
    } else {
        initExperimentalModule();
    }
})();
