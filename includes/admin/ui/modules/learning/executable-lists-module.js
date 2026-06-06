/**
 * Executable Lists Module — feed experimental debug + visible user feed MC13A.
 *
 * Renderiza el feed MC7/MC9 sin sustituir pipelines legacy.
 * MC12: coordinator en sandbox debug (flags DEBUG + ACTIONS_DEBUG).
 * MC13A: feed user visible bajo AA_EXECUTABLE_VISIBLE_FEED=user (comparación, no swap).
 * MC13B: AA_EXECUTABLE_VISIBLE_FEED=user-swap — feed user principal, board legacy oculto.
 * MC13C: hardening UX user-swap — loading, errores unificados, empty intra-lista.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var lastPayload = null;
    var lastVisibleUserPayload = null;
    var isAvailabilityBound = false;
    var isInteractionGuardBound = false;
    var visibleUserCoordinatorInitialized = false;

    var SESSION_STORAGE_DEBUG_KEY = 'AA_EXECUTABLE_LISTS_DEBUG';
    var SESSION_STORAGE_ACTIONS_DEBUG_KEY = 'AA_EXECUTABLE_LISTS_ACTIONS_DEBUG';
    var SESSION_STORAGE_VISIBLE_FEED_KEY = 'AA_EXECUTABLE_VISIBLE_FEED';
    var VISIBLE_FEED_USER = 'user';
    var VISIBLE_FEED_USER_SWAP = 'user-swap';

    /**
     * @param {unknown} value
     * @returns {string|null}
     */
    function normalizeVisibleFeedFlag(value) {
        var normalized = asString(value).trim().toLowerCase();

        if (normalized === VISIBLE_FEED_USER || normalized === VISIBLE_FEED_USER_SWAP) {
            return normalized;
        }

        return null;
    }

    /**
     * @returns {string|null}
     */
    function readVisibleFeedFlag() {
        var windowFlag = normalizeVisibleFeedFlag(globalRoot.AA_EXECUTABLE_VISIBLE_FEED);

        if (windowFlag) {
            return windowFlag;
        }

        var cfg = globalRoot.AA_EXECUTABLE_LISTS_DATA;

        if (cfg) {
            var cfgFlag = normalizeVisibleFeedFlag(cfg.visibleFeed);

            if (cfgFlag) {
                return cfgFlag;
            }
        }

        try {
            var storage = globalRoot.sessionStorage;

            if (!storage || typeof storage.getItem !== 'function') {
                return null;
            }

            return normalizeVisibleFeedFlag(storage.getItem(SESSION_STORAGE_VISIBLE_FEED_KEY));
        } catch (err) {
            return null;
        }
    }

    /**
     * @returns {boolean}
     */
    function isSessionStorageVisibleFeedUserEnabled() {
        try {
            var storage = globalRoot.sessionStorage;

            if (!storage || typeof storage.getItem !== 'function') {
                return false;
            }

            return normalizeVisibleFeedFlag(storage.getItem(SESSION_STORAGE_VISIBLE_FEED_KEY)) === VISIBLE_FEED_USER;
        } catch (err) {
            return false;
        }
    }

    /**
     * @returns {boolean}
     */
    function isVisibleUserFeedEnabled() {
        var flag = readVisibleFeedFlag();

        return flag === VISIBLE_FEED_USER || flag === VISIBLE_FEED_USER_SWAP;
    }

    /**
     * @returns {boolean}
     */
    function isUserSwapEnabled() {
        return readVisibleFeedFlag() === VISIBLE_FEED_USER_SWAP;
    }

    function getLegacyBoardRoot() {
        return document.getElementById('aa-tasks-board-root');
    }

    function getVisibleUserSectionHeader() {
        return document.getElementById('aa-executable-user-lists-visible-header');
    }

    function getVisibleUserSectionTitle() {
        return document.getElementById('aa-executable-user-lists-visible-title');
    }

    function getVisibleUserSectionSubtitle() {
        return document.getElementById('aa-executable-user-lists-visible-subtitle');
    }

    function setLegacyBoardVisible(visible) {
        setSectionVisible(getLegacyBoardRoot(), visible);
    }

    function applyVisibleUserSectionChrome() {
        var section = getVisibleUserSection();
        var header = getVisibleUserSectionHeader();
        var titleEl = getVisibleUserSectionTitle();
        var subtitleEl = getVisibleUserSectionSubtitle();
        var swap = isUserSwapEnabled();

        if (section) {
            section.classList.toggle('border-dashed', !swap);
            section.classList.toggle('border-violet-300', !swap);
            section.classList.toggle('border-gray-200', swap);
        }

        if (header) {
            header.classList.toggle('border-violet-200', !swap);
            header.classList.toggle('bg-violet-50', !swap);
            header.classList.toggle('border-gray-100', swap);
            header.classList.toggle('bg-gradient-to-r', swap);
            header.classList.toggle('from-gray-50', swap);
            header.classList.toggle('to-white', swap);
        }

        if (titleEl) {
            titleEl.textContent = swap ? 'Mis listas' : 'Listas de usuario (executable visible)';
            titleEl.classList.toggle('text-violet-900', !swap);
            titleEl.classList.toggle('text-gray-900', swap);
            titleEl.classList.toggle('text-base', swap);
            titleEl.classList.toggle('font-semibold', swap);
            titleEl.classList.toggle('text-sm', !swap);
        }

        if (subtitleEl) {
            if (swap) {
                subtitleEl.textContent = 'Tareas organizadas por lista.';
                subtitleEl.classList.remove('hidden');
            } else {
                subtitleEl.textContent = 'Comparación MC13A: feed user filtrado; legacy sigue visible abajo.';
                subtitleEl.classList.remove('hidden');
            }

            subtitleEl.classList.toggle('text-violet-800', !swap);
            subtitleEl.classList.toggle('text-gray-500', swap);
            subtitleEl.classList.toggle('text-xs', true);
            subtitleEl.classList.toggle('text-sm', swap);
            subtitleEl.classList.toggle('mt-0.5', !swap);
            subtitleEl.classList.toggle('mt-1', swap);
        }
    }

    function applyUserSwapLayout() {
        if (isUserSwapEnabled()) {
            setLegacyBoardVisible(false);
            return;
        }

        setLegacyBoardVisible(true);
    }

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

    function getVisibleUserSection() {
        return document.getElementById('aa-executable-user-lists-visible');
    }

    function getVisibleUserRoot() {
        return document.getElementById('aa-executable-user-lists-root');
    }

    function getVisibleUserErrorEl() {
        return document.getElementById('aa-executable-user-lists-error');
    }

    function getVisibleUserLoadingEl() {
        return document.getElementById('aa-executable-user-lists-loading');
    }

    var VISIBLE_USER_LOADING_MESSAGE = 'Cargando listas…';

    function setVisibleUserLoading(visible) {
        var loadingEl = getVisibleUserLoadingEl();
        var root = getVisibleUserRoot();

        if (loadingEl) {
            loadingEl.textContent = VISIBLE_USER_LOADING_MESSAGE;

            if (visible) {
                loadingEl.classList.remove('hidden');
            } else {
                loadingEl.classList.add('hidden');
            }
        }

        if (root && visible) {
            root.innerHTML = '';
        }
    }

    function setSectionVisible(section, visible) {
        if (!section) {
            return;
        }

        if (visible) {
            section.classList.remove('hidden');
        } else {
            section.classList.add('hidden');
        }
    }

    function setExperimentalSectionVisible(visible) {
        setSectionVisible(getExperimentalSection(), visible);
    }

    function setVisibleUserSectionVisible(visible) {
        setSectionVisible(getVisibleUserSection(), visible);
    }

    function updateExperimentalModeCopy() {
        var modeEl = getExperimentalModeEl();

        if (!modeEl) {
            return;
        }

        if (isActionsEnabled()) {
            modeEl.textContent = 'Modo debug interactivo: acciones de tareas y handlers Learning habilitadas.';
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

    function clearVisibleUserError() {
        var errorEl = getVisibleUserErrorEl();

        if (!errorEl) {
            return;
        }

        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }

    /**
     * @param {string} message
     */
    function showVisibleUserError(message) {
        var errorEl = getVisibleUserErrorEl();

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
     * @param {object|null|undefined} item
     * @returns {string}
     */
    function resolveExecutableItemKey(item) {
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
     * @param {unknown} value
     * @returns {string}
     */
    function asString(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    /**
     * @param {Array|null|undefined} lists
     * @returns {Array}
     */
    function filterUserLists(lists) {
        if (!Array.isArray(lists)) {
            return [];
        }

        return lists.filter(function (list) {
            return list
                && typeof list === 'object'
                && asString(list.source).trim().toLowerCase() === 'user';
        });
    }

    /**
     * @param {string} key
     * @returns {object|null}
     */
    function findLearningItem(key) {
        var normalizedKey = asString(key).trim();

        if (normalizedKey === '' || !lastPayload) {
            return null;
        }

        var lists = Array.isArray(lastPayload.lists) ? lastPayload.lists : [];
        var preferred = null;
        var fallback = null;
        var listIndex = 0;
        var bucketIndex = 0;
        var itemIndex = 0;

        for (listIndex = 0; listIndex < lists.length; listIndex += 1) {
            var list = lists[listIndex];

            if (!list || typeof list !== 'object') {
                continue;
            }

            var buckets = Array.isArray(list.buckets) ? list.buckets : [];

            for (bucketIndex = 0; bucketIndex < buckets.length; bucketIndex += 1) {
                var bucket = buckets[bucketIndex];

                if (!bucket || typeof bucket !== 'object') {
                    continue;
                }

                var items = Array.isArray(bucket.items) ? bucket.items : [];

                for (itemIndex = 0; itemIndex < items.length; itemIndex += 1) {
                    var item = items[itemIndex];

                    if (!item || resolveExecutableItemKey(item) !== normalizedKey) {
                        continue;
                    }

                    if (asString(item.source).trim().toLowerCase() === 'system') {
                        preferred = item;
                    } else if (!fallback) {
                        fallback = item;
                    }
                }
            }
        }

        return preferred || fallback;
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

    /**
     * @param {HTMLElement|null} root
     * @param {Array} lists
     * @param {string} emptyMessage
     * @param {{useVisibleUserErrorChannel?: boolean}} [renderOptions]
     */
    function renderListsIntoRoot(root, lists, emptyMessage, renderOptions) {
        var opts = renderOptions || {};
        var renderer = globalRoot.AAExecutableListRenderer;

        if (!root) {
            return;
        }

        if (!renderer || typeof renderer.renderFeed !== 'function') {
            var rendererError = 'No se pudo inicializar el renderer executable.';

            if (opts.useVisibleUserErrorChannel) {
                root.innerHTML = '';
                showVisibleUserError(rendererError);
                return;
            }

            root.innerHTML = '<p class="text-sm text-red-600">' + rendererError + '</p>';
            return;
        }

        root.innerHTML = lists.length > 0
            ? renderer.renderFeed(lists, buildRenderOptions())
            : '<p class="text-sm text-gray-500">' + String(emptyMessage || 'No hay listas en el feed executable.') + '</p>';
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
        var lists = payload && Array.isArray(payload.lists) ? payload.lists : [];

        renderListsIntoRoot(
            root,
            lists,
            'No hay listas en el feed executable.'
        );
    }

    function renderVisibleUserPayload(payload) {
        var root = getVisibleUserRoot();
        var lists = filterUserLists(payload && payload.lists);

        renderListsIntoRoot(
            root,
            lists,
            getVisibleUserEmptyMessage(),
            { useVisibleUserErrorChannel: true }
        );
    }

    /**
     * @returns {string}
     */
    function getVisibleUserEmptyMessage() {
        if (isUserSwapEnabled()) {
            return 'Aún no tienes listas propias. Usa el botón flotante + Nueva lista.';
        }

        return 'No hay listas de usuario en el feed executable.';
    }

    /**
     * @returns {Promise<void>}
     */
    function reloadLegacyBoardBestEffort() {
        var board = globalRoot.AATasksBoard;

        if (!board || typeof board.reload !== 'function') {
            return Promise.resolve();
        }

        return board.reload({ silent: true }).catch(function () {});
    }

    /**
     * MC13B: tras mutación en feed user swap, refresca también executive/selector.
     *
     * @returns {Promise<void>}
     */
    function reloadVisibleUserFeedWithBoardSync() {
        return loadVisibleUserFeed().then(function () {
            if (!isUserSwapEnabled()) {
                return;
            }

            return reloadLegacyBoardBestEffort();
        });
    }

    function showLoadError(message) {
        var root = getExperimentalRoot();

        if (root) {
            root.innerHTML = '<p class="text-sm text-red-600">' + String(message || 'No se pudo cargar el feed executable.') + '</p>';
        }
    }

    /**
     * MC13C: errores de carga en el mismo canal que acciones (#aa-executable-user-lists-error).
     *
     * @param {string} message
     */
    function showVisibleUserLoadError(message) {
        var root = getVisibleUserRoot();

        if (root) {
            root.innerHTML = '';
        }

        showVisibleUserError(message || 'No se pudo cargar el feed executable de usuario.');
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

    /**
     * Refresco público MC13A — MC13B cableará post-CRUD legacy aquí.
     *
     * @returns {Promise<void>}
     */
    function loadVisibleUserFeed() {
        var service = globalRoot.ExecutableListsService;

        setVisibleUserLoading(true);

        if (!service || typeof service.getFeed !== 'function') {
            setVisibleUserLoading(false);
            showVisibleUserLoadError('ExecutableListsService no disponible.');
            return Promise.resolve();
        }

        return service.getFeed()
            .then(function (payload) {
                setVisibleUserLoading(false);
                clearVisibleUserError();
                lastVisibleUserPayload = payload;
                renderVisibleUserPayload(payload);
            })
            .catch(function (err) {
                setVisibleUserLoading(false);
                lastVisibleUserPayload = null;
                showVisibleUserLoadError((err && err.message) ? err.message : 'No se pudo cargar el feed executable de usuario.');
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
     * @param {object} options
     */
    function initActionsCoordinator(root, options) {
        var coordinator = globalRoot.ExecutableActionsCoordinator;
        var opts = options || {};

        if (!coordinator || typeof coordinator.init !== 'function') {
            if (typeof opts.onMissingCoordinator === 'function') {
                opts.onMissingCoordinator();
            }

            return;
        }

        coordinator.init({
            root: root,
            reload: opts.reload,
            showError: opts.showError,
            clearError: opts.clearError,
            findLearningItem: opts.findLearningItem
        });
    }

    function initExperimentalCoordinator(root) {
        initActionsCoordinator(root, {
            reload: loadExperimentalFeed,
            showError: showExperimentalError,
            clearError: clearExperimentalError,
            findLearningItem: findLearningItem,
            onMissingCoordinator: showExperimentalError.bind(null, 'ExecutableActionsCoordinator no disponible.')
        });
    }

    function initVisibleUserCoordinator(root) {
        if (visibleUserCoordinatorInitialized) {
            return;
        }

        initActionsCoordinator(root, {
            reload: reloadVisibleUserFeedWithBoardSync,
            showError: showVisibleUserError,
            clearError: clearVisibleUserError,
            findLearningItem: function () {
                return null;
            },
            onMissingCoordinator: showVisibleUserError.bind(null, 'ExecutableActionsCoordinator no disponible.')
        });

        visibleUserCoordinatorInitialized = true;
    }

    function initExperimentalModule() {
        if (!isDebugEnabled()) {
            setExperimentalSectionVisible(false);
            return;
        }

        var root = getExperimentalRoot();

        if (!root) {
            return;
        }

        setExperimentalSectionVisible(true);
        updateExperimentalModeCopy();
        clearExperimentalError();
        bindAvailabilityRerender();

        if (isActionsEnabled()) {
            enableInteractiveRoot(root);
            initExperimentalCoordinator(root);
        } else {
            bindInteractionGuard(root);
        }

        loadExperimentalFeed();
    }

    function initVisibleUserFeedModule() {
        if (!isVisibleUserFeedEnabled()) {
            setVisibleUserSectionVisible(false);
            setLegacyBoardVisible(true);
            return;
        }

        var root = getVisibleUserRoot();

        if (!root) {
            return;
        }

        applyUserSwapLayout();
        applyVisibleUserSectionChrome();
        setVisibleUserSectionVisible(true);
        clearVisibleUserError();
        enableInteractiveRoot(root);
        initVisibleUserCoordinator(root);
        loadVisibleUserFeed();
    }

    function initExecutableListsModule() {
        if (!document.getElementById('aa-tasks-module-root')) {
            return;
        }

        initVisibleUserFeedModule();
        initExperimentalModule();
    }

    var visibleUserFeedApi = {
        reload: loadVisibleUserFeed,
        isEnabled: isVisibleUserFeedEnabled,
        isSwapEnabled: isUserSwapEnabled,
        filterUserLists: filterUserLists,
        renderVisibleUserPayload: renderVisibleUserPayload
    };

    globalRoot.AAExecutableUserListsVisibleFeed = visibleUserFeedApi;

    var moduleExports = {
        isDebugEnabled: isDebugEnabled,
        isSessionStorageDebugEnabled: isSessionStorageDebugEnabled,
        isActionsEnabled: isActionsEnabled,
        isSessionStorageActionsDebugEnabled: isSessionStorageActionsDebugEnabled,
        isVisibleUserFeedEnabled: isVisibleUserFeedEnabled,
        isUserSwapEnabled: isUserSwapEnabled,
        isSessionStorageVisibleFeedUserEnabled: isSessionStorageVisibleFeedUserEnabled,
        readVisibleFeedFlag: readVisibleFeedFlag,
        normalizeVisibleFeedFlag: normalizeVisibleFeedFlag,
        applyUserSwapLayout: applyUserSwapLayout,
        applyVisibleUserSectionChrome: applyVisibleUserSectionChrome,
        getVisibleUserEmptyMessage: getVisibleUserEmptyMessage,
        setVisibleUserLoading: setVisibleUserLoading,
        showVisibleUserError: showVisibleUserError,
        clearVisibleUserError: clearVisibleUserError,
        showVisibleUserLoadError: showVisibleUserLoadError,
        VISIBLE_USER_LOADING_MESSAGE: VISIBLE_USER_LOADING_MESSAGE,
        reloadVisibleUserFeedWithBoardSync: reloadVisibleUserFeedWithBoardSync,
        reloadLegacyBoardBestEffort: reloadLegacyBoardBestEffort,
        buildRenderOptions: buildRenderOptions,
        enableInteractiveRoot: enableInteractiveRoot,
        enablePreviewRoot: enablePreviewRoot,
        loadExperimentalFeed: loadExperimentalFeed,
        loadVisibleUserFeed: loadVisibleUserFeed,
        renderPayload: renderPayload,
        renderVisibleUserPayload: renderVisibleUserPayload,
        filterUserLists: filterUserLists,
        showExperimentalError: showExperimentalError,
        clearExperimentalError: clearExperimentalError,
        updateExperimentalModeCopy: updateExperimentalModeCopy,
        findLearningItem: findLearningItem,
        resolveExecutableItemKey: resolveExecutableItemKey,
        initVisibleUserCoordinator: initVisibleUserCoordinator,
        visibleUserFeedApi: visibleUserFeedApi
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExecutableListsModule);
    } else {
        initExecutableListsModule();
    }
})();
