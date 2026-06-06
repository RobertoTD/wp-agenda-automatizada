/**
 * Executable Lists Module — feed experimental visible solo en debug.
 *
 * Renderiza el feed MC7/MC9 sin sustituir pipelines legacy.
 * MC12A: modo interactivo debug opcional vía ExecutableActionsCoordinator (user tasks).
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
    var SESSION_STORAGE_ACTIONS_DEBUG_KEY = 'AA_EXECUTABLE_LISTS_ACTIONS_DEBUG';

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

    /**
     * @returns {boolean}
     */
    function isSessionStorageActionsDebugEnabled() {
        try {
            var storage = globalRoot.sessionStorage;

            if (!storage || typeof storage.getItem !== 'function') {
                return false;
            }

            return storage.getItem(SESSION_STORAGE_ACTIONS_DEBUG_KEY) === '1';
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

    /**
     * @returns {boolean}
     */
    function isActionsEnabled() {
        if (!isDebugEnabled()) {
            return false;
        }

        if (globalRoot.AA_EXECUTABLE_LISTS_ACTIONS_DEBUG === true) {
            return true;
        }

        return isSessionStorageActionsDebugEnabled();
    }

    function getExperimentalSection() {
        return document.getElementById('aa-executable-lists-experimental');
    }

    function getExperimentalRoot() {
        return document.getElementById('aa-executable-lists-root');
    }

    function getExperimentalErrorEl() {
        return document.getElementById('aa-executable-lists-error');
    }

    function getExperimentalModeEl() {
        return document.getElementById('aa-executable-lists-mode');
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

    function updateExperimentalModeCopy() {
        var modeEl = getExperimentalModeEl();

        if (!modeEl) {
            return;
        }

        if (isActionsEnabled()) {
            modeEl.textContent = 'Modo debug interactivo: acciones de tareas de usuario habilitadas.';
            return;
        }

        modeEl.textContent = 'Modo preview: acciones desactivadas.';
    }

    /**
     * @param {HTMLElement|null} root
     */
    function enableInteractiveRoot(root) {
        if (!root) {
            return;
        }

        root.removeAttribute('inert');
        root.classList.remove('pointer-events-none');
    }

    /**
     * @param {HTMLElement|null} root
     */
    function enablePreviewRoot(root) {
        if (!root) {
            return;
        }

        root.setAttribute('inert', '');
        root.classList.add('pointer-events-none');
    }

    function clearExperimentalError() {
        var errorEl = getExperimentalErrorEl();

        if (!errorEl) {
            return;
        }

        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }

    /**
     * @param {string} message
     */
    function showExperimentalError(message) {
        var errorEl = getExperimentalErrorEl();

        if (!errorEl) {
            return;
        }

        errorEl.textContent = String(message || 'No se pudo completar la acción.');
        errorEl.classList.remove('hidden');
    }

    function getLearningRegistry() {
        return globalRoot.LearningActionHandlers || null;
    }

    /**
     * @param {object|null|undefined} action
     * @returns {object|null}
     */
    function asHandlerAction(action) {
        if (!action || typeof action !== 'object' || action.type !== 'handler') {
            return null;
        }

        return action;
    }

    /**
     * @param {object|null|undefined} item
     * @returns {object|null}
     */
    function resolveItemHandlerAction(item) {
        if (!item || typeof item !== 'object') {
            return null;
        }

        var visibleActions = Array.isArray(item.visible_actions) ? item.visible_actions : [];
        var index = 0;

        for (index = 0; index < visibleActions.length; index += 1) {
            var visibleHandler = asHandlerAction(visibleActions[index]);

            if (visibleHandler) {
                return visibleHandler;
            }
        }

        return asHandlerAction(item.primary_action);
    }

    /**
     * @param {object|null|undefined} action
     * @returns {object|null}
     */
    function mapHandlerForRegistry(action) {
        var handlerAction = asHandlerAction(action);

        if (!handlerAction) {
            return null;
        }

        return {
            type: 'handler',
            label: handlerAction.label || '',
            handler: handlerAction.handler || ''
        };
    }

    /**
     * @returns {object}
     */
    function buildRenderOptions() {
        var registry = getLearningRegistry();

        return {
            shouldRenderItem: function (item) {
                var handlerAction = resolveItemHandlerAction(item);

                if (!handlerAction) {
                    return true;
                }

                if (!registry || typeof registry.shouldShowRecommendation !== 'function') {
                    return true;
                }

                return registry.shouldShowRecommendation(mapHandlerForRegistry(handlerAction), item) === true;
            },
            shouldRenderPrimaryAction: function (action, item) {
                var handlerAction = mapHandlerForRegistry(action);

                if (!handlerAction) {
                    return true;
                }

                if (!registry || typeof registry.isAvailable !== 'function') {
                    return false;
                }

                return registry.isAvailable(handlerAction, item) === true;
            }
        };
    }

    function bindInteractionGuard(root) {
        if (!root || isInteractionGuardBound) {
            return;
        }

        isInteractionGuardBound = true;
        enablePreviewRoot(root);

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

    /**
     * @param {HTMLElement} root
     */
    function initActionsCoordinator(root) {
        var coordinator = globalRoot.ExecutableActionsCoordinator;

        if (!coordinator || typeof coordinator.init !== 'function') {
            showExperimentalError('ExecutableActionsCoordinator no disponible.');
            return;
        }

        coordinator.init({
            root: root,
            reload: loadExperimentalFeed,
            showError: showExperimentalError,
            clearError: clearExperimentalError
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
        updateExperimentalModeCopy();
        clearExperimentalError();
        bindAvailabilityRerender();

        if (isActionsEnabled()) {
            enableInteractiveRoot(root);
            initActionsCoordinator(root);
        } else {
            bindInteractionGuard(root);
        }

        loadExperimentalFeed();
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            isDebugEnabled: isDebugEnabled,
            isSessionStorageDebugEnabled: isSessionStorageDebugEnabled,
            isActionsEnabled: isActionsEnabled,
            isSessionStorageActionsDebugEnabled: isSessionStorageActionsDebugEnabled,
            buildRenderOptions: buildRenderOptions,
            enableInteractiveRoot: enableInteractiveRoot,
            enablePreviewRoot: enablePreviewRoot,
            loadExperimentalFeed: loadExperimentalFeed,
            renderPayload: renderPayload,
            showExperimentalError: showExperimentalError,
            clearExperimentalError: clearExperimentalError,
            updateExperimentalModeCopy: updateExperimentalModeCopy
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
