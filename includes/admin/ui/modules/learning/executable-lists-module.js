/**
 * Executable Lists Module — feed unified oficial + sandbox experimental DEBUG.
 *
 * MC12: coordinator en sandbox debug (flags DEBUG + ACTIONS_DEBUG).
 * MC13H: feed executable system + user en un solo root.
 * MC13J: unified es el modo operativo default; flags históricos mapean a unified.
 * MC13J-2B: DOM Learning legacy retirado de Listas.
 * MC13J-2C: modos user/user-swap y DOM user-only retirados; unified única vista.
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var lastPayload = null;
    var lastUnifiedPayload = null;
    var isAvailabilityBound = false;
    var isInteractionGuardBound = false;
    var unifiedCoordinatorInitialized = false;

    var SESSION_STORAGE_DEBUG_KEY = 'AA_EXECUTABLE_LISTS_DEBUG';
    var SESSION_STORAGE_ACTIONS_DEBUG_KEY = 'AA_EXECUTABLE_LISTS_ACTIONS_DEBUG';
    var SESSION_STORAGE_VISIBLE_FEED_KEY = 'AA_EXECUTABLE_VISIBLE_FEED';
    var VISIBLE_FEED_USER = 'user';
    var VISIBLE_FEED_USER_SWAP = 'user-swap';
    var VISIBLE_FEED_UNIFIED = 'unified';
    var VISIBLE_FEED_LEGACY = 'legacy';
    var VISIBLE_FEED_OFF = 'off';
    var UNIFIED_EMPTY_MESSAGE = 'No hay listas activas.';
    var UNIFIED_LOADING_MESSAGE = 'Cargando listas…';

    /**
     * @param {unknown} value
     * @returns {string|null}
     */
    function normalizeVisibleFeedFlag(value) {
        var normalized = asString(value).trim().toLowerCase();

        if (
            normalized === VISIBLE_FEED_OFF
            || normalized === VISIBLE_FEED_LEGACY
            || normalized === VISIBLE_FEED_USER
            || normalized === VISIBLE_FEED_USER_SWAP
        ) {
            return VISIBLE_FEED_UNIFIED;
        }

        if (normalized === VISIBLE_FEED_UNIFIED) {
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
     * MC13J: sin flag explícito → unified (producción).
     *
     * @returns {string}
     */
    function resolveEffectiveFeedMode() {
        var flag = readVisibleFeedFlag();

        if (flag === null) {
            return VISIBLE_FEED_UNIFIED;
        }

        return flag;
    }

    /**
     * @returns {boolean}
     */
    function isLegacyListsViewEnabled() {
        return resolveEffectiveFeedMode() === VISIBLE_FEED_LEGACY;
    }

    /**
     * @returns {boolean}
     */
    function isUnifiedFeedEnabled() {
        return resolveEffectiveFeedMode() === VISIBLE_FEED_UNIFIED;
    }

    /**
     * @returns {boolean}
     */
    function isExecutableVisibleFeedEnabled() {
        return isUnifiedFeedEnabled();
    }

    /**
     * MC13J-2C: stub — modos user-swap retirados.
     *
     * @returns {boolean}
     */
    function isUserSwapEnabled() {
        return false;
    }

    function getLegacyBoardRoot() {
        return document.getElementById('aa-tasks-board-root');
    }

    function setLegacyBoardVisible(visible) {
        setSectionVisible(getLegacyBoardRoot(), visible);
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

    function getUnifiedSection() {
        return document.getElementById('aa-executable-lists-active');
    }

    function getUnifiedRoot() {
        return document.getElementById('aa-executable-lists-active-root');
    }

    function getUnifiedErrorEl() {
        return document.getElementById('aa-executable-lists-active-error');
    }

    function getUnifiedLoadingEl() {
        return document.getElementById('aa-executable-lists-active-loading');
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

    function setUnifiedSectionVisible(visible) {
        setSectionVisible(getUnifiedSection(), visible);
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

    /**
     * @param {string} message
     */
    function setUnifiedLoading(visible) {
        var loadingEl = getUnifiedLoadingEl();
        var root = getUnifiedRoot();

        if (loadingEl) {
            loadingEl.textContent = UNIFIED_LOADING_MESSAGE;

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

    function clearUnifiedError() {
        var errorEl = getUnifiedErrorEl();

        if (!errorEl) {
            return;
        }

        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }

    /**
     * @param {string} message
     */
    function showUnifiedError(message) {
        var errorEl = getUnifiedErrorEl();

        if (!errorEl) {
            return;
        }

        errorEl.textContent = String(message || 'No se pudo completar la acción.');
        errorEl.classList.remove('hidden');
    }

    /**
     * @param {string} message
     */
    function showUnifiedLoadError(message) {
        var root = getUnifiedRoot();

        if (root) {
            root.innerHTML = '';
        }

        showUnifiedError(message || 'No se pudo cargar el feed executable.');
    }

    function applyUnifiedLayout() {
        setLegacyBoardVisible(false);
        setUnifiedSectionVisible(true);
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
     * @param {object|null|undefined} list
     * @returns {boolean}
     */
    function listHasBucketItems(list) {
        if (!list || typeof list !== 'object') {
            return false;
        }

        var buckets = Array.isArray(list.buckets) ? list.buckets : [];
        var bucketIndex = 0;

        for (bucketIndex = 0; bucketIndex < buckets.length; bucketIndex += 1) {
            var bucket = buckets[bucketIndex];

            if (!bucket || typeof bucket !== 'object') {
                continue;
            }

            var items = Array.isArray(bucket.items) ? bucket.items : [];

            if (items.length > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * MC13H: omite listas system sin items; conserva user lists (empty intra-lista).
     *
     * @param {Array|null|undefined} lists
     * @returns {Array}
     */
    function filterListsForUnifiedRender(lists) {
        if (!Array.isArray(lists)) {
            return [];
        }

        return lists.filter(function (list) {
            if (!list || typeof list !== 'object') {
                return false;
            }

            var source = asString(list.source).trim().toLowerCase();

            if (source !== 'system') {
                return true;
            }

            return listHasBucketItems(list);
        });
    }

    /**
     * @param {object|null|undefined} payload
     * @param {string} key
     * @returns {object|null}
     */
    function findLearningItemInPayload(payload, key) {
        var normalizedKey = asString(key).trim();

        if (normalizedKey === '' || !payload) {
            return null;
        }

        var lists = Array.isArray(payload.lists) ? payload.lists : [];
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
     * @param {string} key
     * @returns {object|null}
     */
    function findLearningItem(key) {
        return findLearningItemInPayload(lastPayload, key);
    }

    /**
     * @param {string} key
     * @returns {object|null}
     */
    function findUnifiedLearningItem(key) {
        return findLearningItemInPayload(lastUnifiedPayload, key);
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
     * @param {{useUnifiedErrorChannel?: boolean}} [renderOptions]
     */
    function renderListsIntoRoot(root, lists, emptyMessage, renderOptions) {
        var opts = renderOptions || {};
        var renderer = globalRoot.AAExecutableListRenderer;

        if (!root) {
            return;
        }

        if (!renderer || typeof renderer.renderFeed !== 'function') {
            var rendererError = 'No se pudo inicializar el renderer executable.';

            if (opts.useUnifiedErrorChannel) {
                root.innerHTML = '';
                showUnifiedError(rendererError);
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

    function renderUnifiedPayload(payload) {
        var root = getUnifiedRoot();
        var lists = filterListsForUnifiedRender(payload && payload.lists);

        renderListsIntoRoot(
            root,
            lists,
            UNIFIED_EMPTY_MESSAGE,
            { useUnifiedErrorChannel: true }
        );
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
     * MC13H: tras mutación en feed unified, refresca también executive/selector.
     *
     * @returns {Promise<void>}
     */
    function reloadUnifiedFeedWithBoardSync() {
        return loadUnifiedFeed().then(function () {
            if (!isUnifiedFeedEnabled()) {
                return;
            }

            return reloadLegacyBoardBestEffort();
        });
    }

    /**
     * @returns {Promise<void>}
     */
    function reloadExecutableVisibleFeed() {
        return reloadUnifiedFeedWithBoardSync();
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

    /**
     * @returns {Promise<void>}
     */
    function loadUnifiedFeed() {
        var service = globalRoot.ExecutableListsService;

        setUnifiedLoading(true);

        if (!service || typeof service.getFeed !== 'function') {
            setUnifiedLoading(false);
            showUnifiedLoadError('ExecutableListsService no disponible.');
            return Promise.resolve();
        }

        return service.getFeed()
            .then(function (payload) {
                setUnifiedLoading(false);
                clearUnifiedError();
                lastUnifiedPayload = payload;
                renderUnifiedPayload(payload);
            })
            .catch(function (err) {
                setUnifiedLoading(false);
                lastUnifiedPayload = null;
                showUnifiedLoadError((err && err.message) ? err.message : 'No se pudo cargar el feed executable.');
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

            if (lastUnifiedPayload) {
                renderUnifiedPayload(lastUnifiedPayload);
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

    function initUnifiedCoordinator(root) {
        if (unifiedCoordinatorInitialized) {
            return;
        }

        initActionsCoordinator(root, {
            reload: reloadUnifiedFeedWithBoardSync,
            showError: showUnifiedError,
            clearError: clearUnifiedError,
            findLearningItem: findUnifiedLearningItem,
            onMissingCoordinator: showUnifiedError.bind(null, 'ExecutableActionsCoordinator no disponible.')
        });

        unifiedCoordinatorInitialized = true;
    }

    function initUnifiedFeedModule() {
        if (!isUnifiedFeedEnabled()) {
            setUnifiedSectionVisible(false);
            return;
        }

        var root = getUnifiedRoot();

        if (!root) {
            return;
        }

        applyUnifiedLayout();
        clearUnifiedError();
        enableInteractiveRoot(root);
        bindAvailabilityRerender();
        initUnifiedCoordinator(root);
        loadUnifiedFeed();
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

    function initExecutableListsModule() {
        if (!document.getElementById('aa-tasks-module-root')) {
            return;
        }

        initUnifiedFeedModule();
        initExperimentalModule();
    }

    var visibleUserFeedApi = {
        reload: reloadExecutableVisibleFeed,
        isEnabled: isExecutableVisibleFeedEnabled,
        isSwapEnabled: isUserSwapEnabled,
        isUnifiedEnabled: isUnifiedFeedEnabled,
        filterListsForUnifiedRender: filterListsForUnifiedRender,
        renderUnifiedPayload: renderUnifiedPayload
    };

    globalRoot.AAExecutableUserListsVisibleFeed = visibleUserFeedApi;

    var moduleExports = {
        isDebugEnabled: isDebugEnabled,
        isSessionStorageDebugEnabled: isSessionStorageDebugEnabled,
        isActionsEnabled: isActionsEnabled,
        isSessionStorageActionsDebugEnabled: isSessionStorageActionsDebugEnabled,
        isUnifiedFeedEnabled: isUnifiedFeedEnabled,
        isExecutableVisibleFeedEnabled: isExecutableVisibleFeedEnabled,
        isUserSwapEnabled: isUserSwapEnabled,
        readVisibleFeedFlag: readVisibleFeedFlag,
        resolveEffectiveFeedMode: resolveEffectiveFeedMode,
        isLegacyListsViewEnabled: isLegacyListsViewEnabled,
        normalizeVisibleFeedFlag: normalizeVisibleFeedFlag,
        VISIBLE_FEED_LEGACY: VISIBLE_FEED_LEGACY,
        VISIBLE_FEED_UNIFIED: VISIBLE_FEED_UNIFIED,
        VISIBLE_FEED_USER: VISIBLE_FEED_USER,
        VISIBLE_FEED_USER_SWAP: VISIBLE_FEED_USER_SWAP,
        applyUnifiedLayout: applyUnifiedLayout,
        reloadUnifiedFeedWithBoardSync: reloadUnifiedFeedWithBoardSync,
        reloadExecutableVisibleFeed: reloadExecutableVisibleFeed,
        reloadLegacyBoardBestEffort: reloadLegacyBoardBestEffort,
        buildRenderOptions: buildRenderOptions,
        enableInteractiveRoot: enableInteractiveRoot,
        enablePreviewRoot: enablePreviewRoot,
        loadExperimentalFeed: loadExperimentalFeed,
        loadUnifiedFeed: loadUnifiedFeed,
        renderPayload: renderPayload,
        renderUnifiedPayload: renderUnifiedPayload,
        filterListsForUnifiedRender: filterListsForUnifiedRender,
        listHasBucketItems: listHasBucketItems,
        showUnifiedError: showUnifiedError,
        clearUnifiedError: clearUnifiedError,
        showUnifiedLoadError: showUnifiedLoadError,
        setUnifiedLoading: setUnifiedLoading,
        UNIFIED_EMPTY_MESSAGE: UNIFIED_EMPTY_MESSAGE,
        UNIFIED_LOADING_MESSAGE: UNIFIED_LOADING_MESSAGE,
        showExperimentalError: showExperimentalError,
        clearExperimentalError: clearExperimentalError,
        updateExperimentalModeCopy: updateExperimentalModeCopy,
        findLearningItem: findLearningItem,
        findUnifiedLearningItem: findUnifiedLearningItem,
        findLearningItemInPayload: findLearningItemInPayload,
        resolveExecutableItemKey: resolveExecutableItemKey,
        initUnifiedCoordinator: initUnifiedCoordinator,
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
