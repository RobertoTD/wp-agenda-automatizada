/**
 * PWA Install First Opportunity — dashboard-only automatic/manual install prompt (MC1).
 *
 * Reuses LearningActionHandlers / pwa.install; does not duplicate deferredPrompt.
 */
(function () {
    'use strict';

    var STORAGE_KEY_PREFIX = 'aa_pwa_install_first_opportunity_v1';
    var STORAGE_VALUE = '1';
    var AUTOMATIC_SURFACE_ROOT_ID = 'aa-pwa-install-first-opportunity-root';

    var AUTOMATIC_TITLE = 'Instala DEOIA Citas';
    var AUTOMATIC_BODY = 'Ten tu agenda disponible fácilmente desde tu dispositivo.';
    var DISMISS_LABEL = 'Ahora no';
    var INSTALL_LABEL = 'Instalar ahora';

    var MANUAL_ALERT_MESSAGE = ''
        + 'Recomendamos instalar DEOIA Citas en tu dispositivo para acceder fácilmente a tu agenda.\n\n'
        + 'Tu sistema no permite que DEOIA inicie la instalación automáticamente. '
        + 'Puedes instalarla manualmente desde las opciones del navegador que estás usando.';

    var PWA_INSTALL_ACTION = {
        type: 'handler',
        handler: 'pwa.install',
        label: 'Instalar'
    };

    var promptInFlight = false;
    var initialized = false;
    var manualBranchScheduled = false;
    var automaticSurfaceVisible = false;

    function getGlobalRoot() {
        if (typeof window !== 'undefined') {
            return window;
        }

        if (typeof globalThis !== 'undefined') {
            return globalThis;
        }

        return {};
    }

    function warn(message, err) {
        var root = getGlobalRoot();

        if (root.console && typeof root.console.warn === 'function') {
            root.console.warn('[PwaInstallFirstOpportunity] ' + message, err || '');
        }
    }

    function resolveBlogId() {
        var root = getGlobalRoot();
        var ctx = root.AA_ADMIN_CONTEXT;

        if (!ctx || ctx.blogId === null || ctx.blogId === undefined) {
            return '';
        }

        return String(ctx.blogId);
    }

    function buildStorageKey(blogId) {
        var bid = typeof blogId === 'string' ? blogId.trim() : '';

        if (!bid) {
            bid = resolveBlogId();
        }

        return STORAGE_KEY_PREFIX + ':' + bid;
    }

    function hasConsumedFirstOpportunity(blogId) {
        var key = buildStorageKey(blogId);

        if (!key || key === STORAGE_KEY_PREFIX + ':') {
            return false;
        }

        try {
            return getGlobalRoot().localStorage.getItem(key) === STORAGE_VALUE;
        } catch (err) {
            return false;
        }
    }

    function markConsumedFirstOpportunity(blogId) {
        var key = buildStorageKey(blogId);

        if (!key || key === STORAGE_KEY_PREFIX + ':') {
            return false;
        }

        try {
            getGlobalRoot().localStorage.setItem(key, STORAGE_VALUE);
            return true;
        } catch (err) {
            return false;
        }
    }

    function resetConsumedFirstOpportunity(blogId) {
        var key = buildStorageKey(blogId);

        if (!key || key === STORAGE_KEY_PREFIX + ':') {
            return false;
        }

        try {
            getGlobalRoot().localStorage.removeItem(key);
            return true;
        } catch (err) {
            return false;
        }
    }

    function isStandalone() {
        var root = getGlobalRoot();

        try {
            if (root.matchMedia && root.matchMedia('(display-mode: standalone)').matches) {
                return true;
            }
        } catch (err) {
            // matchMedia puede no existir en entornos muy antiguos.
        }

        return !!(root.navigator && root.navigator.standalone === true);
    }

    function isIosLike() {
        var root = getGlobalRoot();
        var ua = root.navigator && root.navigator.userAgent ? root.navigator.userAgent : '';

        if (/iPhone|iPad|iPod/i.test(ua)) {
            return true;
        }

        return root.navigator
            && root.navigator.platform === 'MacIntel'
            && root.navigator.maxTouchPoints > 1;
    }

    function getHandlers() {
        return getGlobalRoot().LearningActionHandlers || null;
    }

    function isPwaInstallAvailable() {
        var handlers = getHandlers();

        if (!handlers || typeof handlers.isAvailable !== 'function') {
            return false;
        }

        return handlers.isAvailable(PWA_INSTALL_ACTION, null) === true;
    }

    function getDocument() {
        var doc = getGlobalRoot().document;

        if (!doc || !doc.body || typeof doc.createElement !== 'function') {
            return null;
        }

        return doc;
    }

    function removeAutomaticInstallSurface() {
        var doc = getGlobalRoot().document;

        if (doc && typeof doc.getElementById === 'function') {
            var root = doc.getElementById(AUTOMATIC_SURFACE_ROOT_ID);

            if (root && root.parentNode && typeof root.parentNode.removeChild === 'function') {
                root.parentNode.removeChild(root);
            }
        }

        automaticSurfaceVisible = false;
    }

    /**
     * @param {{outcome?: string}|null|undefined} result
     */
    function handleInstallRunResult(result) {
        var outcome = result && result.outcome ? String(result.outcome) : '';

        if (outcome === 'accepted' || outcome === 'dismissed') {
            markConsumedFirstOpportunity();
        }
    }

    function onDismissAutomaticInstall(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        removeAutomaticInstallSurface();
        markConsumedFirstOpportunity();
    }

    function onInstallAutomaticClick(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        var handlers = getHandlers();

        if (!handlers || typeof handlers.run !== 'function') {
            removeAutomaticInstallSurface();
            return;
        }

        var resultPromise;

        try {
            resultPromise = handlers.run(PWA_INSTALL_ACTION, null, {});
        } catch (err) {
            removeAutomaticInstallSurface();
            warn('install run failed:', err);
            return;
        }

        removeAutomaticInstallSurface();

        Promise.resolve(resultPromise)
            .then(function (result) {
                handleInstallRunResult(result);
            })
            .catch(function (err) {
                warn('install run rejected:', err);
            });
    }

    function applySurfaceStyles(element, styles) {
        if (!element || !element.style || !styles) {
            return;
        }

        Object.keys(styles).forEach(function (key) {
            element.style[key] = styles[key];
        });
    }

    /**
     * @returns {boolean}
     */
    function renderAutomaticInstallSurface() {
        if (automaticSurfaceVisible || hasConsumedFirstOpportunity() || isStandalone()) {
            return false;
        }

        if (!isPwaInstallAvailable()) {
            return false;
        }

        var doc = getDocument();

        if (!doc) {
            return false;
        }

        if (doc.getElementById(AUTOMATIC_SURFACE_ROOT_ID)) {
            automaticSurfaceVisible = true;
            return true;
        }

        var root = doc.createElement('div');
        root.setAttribute('id', AUTOMATIC_SURFACE_ROOT_ID);
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'aa-pwa-install-first-opportunity-title');
        applySurfaceStyles(root, {
            position: 'fixed',
            inset: '0',
            zIndex: '10000',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '16px',
            boxSizing: 'border-box'
        });

        var backdrop = doc.createElement('div');
        backdrop.className = 'aa-pwa-install-first-opportunity-backdrop';
        applySurfaceStyles(backdrop, {
            position: 'absolute',
            inset: '0',
            backgroundColor: 'rgba(17, 24, 39, 0.45)'
        });

        var card = doc.createElement('div');
        card.className = 'aa-pwa-install-first-opportunity-card';
        applySurfaceStyles(card, {
            position: 'relative',
            zIndex: '1',
            width: '100%',
            maxWidth: '360px',
            backgroundColor: '#ffffff',
            borderRadius: '12px',
            padding: '20px',
            boxShadow: '0 10px 25px rgba(0, 0, 0, 0.15)',
            boxSizing: 'border-box'
        });

        var title = doc.createElement('h2');
        title.setAttribute('id', 'aa-pwa-install-first-opportunity-title');
        title.className = 'aa-pwa-install-first-opportunity-title';
        title.textContent = AUTOMATIC_TITLE;
        applySurfaceStyles(title, {
            margin: '0 0 8px 0',
            fontSize: '18px',
            fontWeight: '600',
            color: '#111827'
        });

        var body = doc.createElement('p');
        body.className = 'aa-pwa-install-first-opportunity-body';
        body.textContent = AUTOMATIC_BODY;
        applySurfaceStyles(body, {
            margin: '0 0 16px 0',
            fontSize: '14px',
            lineHeight: '1.5',
            color: '#4b5563'
        });

        var actions = doc.createElement('div');
        actions.className = 'aa-pwa-install-first-opportunity-actions';
        applySurfaceStyles(actions, {
            display: 'flex',
            flexWrap: 'wrap',
            gap: '8px',
            justifyContent: 'flex-end'
        });

        var dismissButton = doc.createElement('button');
        dismissButton.type = 'button';
        dismissButton.className = 'aa-pwa-install-first-opportunity-dismiss';
        dismissButton.textContent = DISMISS_LABEL;
        applySurfaceStyles(dismissButton, {
            padding: '8px 12px',
            borderRadius: '8px',
            border: '1px solid #d1d5db',
            backgroundColor: '#ffffff',
            color: '#374151',
            fontSize: '14px',
            cursor: 'pointer'
        });
        dismissButton.addEventListener('click', onDismissAutomaticInstall);

        var installButton = doc.createElement('button');
        installButton.type = 'button';
        installButton.className = 'aa-pwa-install-first-opportunity-install';
        installButton.textContent = INSTALL_LABEL;
        applySurfaceStyles(installButton, {
            padding: '8px 12px',
            borderRadius: '8px',
            border: '1px solid #bfdbfe',
            backgroundColor: '#eff6ff',
            color: '#1d4ed8',
            fontSize: '14px',
            fontWeight: '500',
            cursor: 'pointer'
        });
        installButton.addEventListener('click', onInstallAutomaticClick);

        actions.appendChild(dismissButton);
        actions.appendChild(installButton);
        card.appendChild(title);
        card.appendChild(body);
        card.appendChild(actions);
        root.appendChild(backdrop);
        root.appendChild(card);
        doc.body.appendChild(root);
        automaticSurfaceVisible = true;

        return true;
    }

    /**
     * @returns {Promise<void>}
     */
    function tryOfferAutomaticInstall() {
        if (hasConsumedFirstOpportunity() || isStandalone()) {
            return Promise.resolve();
        }

        if (!isPwaInstallAvailable()) {
            return Promise.resolve();
        }

        renderAutomaticInstallSurface();
        return Promise.resolve();
    }

    function tryOfferManualInstall() {
        if (promptInFlight || hasConsumedFirstOpportunity() || isStandalone()) {
            return;
        }

        if (isPwaInstallAvailable()) {
            return;
        }

        if (!isIosLike()) {
            return;
        }

        var root = getGlobalRoot();

        if (typeof root.alert !== 'function') {
            return;
        }

        promptInFlight = true;

        try {
            root.alert(MANUAL_ALERT_MESSAGE);
        } finally {
            markConsumedFirstOpportunity();
            promptInFlight = false;
        }
    }

    function bindAvailabilityListener() {
        var handlers = getHandlers();

        if (!handlers || typeof handlers.onAvailabilityChange !== 'function') {
            return;
        }

        handlers.onAvailabilityChange(function () {
            tryOfferAutomaticInstall();
        });
    }

    function scheduleManualBranch() {
        if (manualBranchScheduled) {
            return;
        }

        manualBranchScheduled = true;

        var root = getGlobalRoot();
        var doc = root.document;

        if (!doc || typeof doc.addEventListener !== 'function') {
            tryOfferManualInstall();
            return;
        }

        if (doc.readyState === 'loading') {
            doc.addEventListener('DOMContentLoaded', tryOfferManualInstall);
            return;
        }

        tryOfferManualInstall();
    }

    function init() {
        if (initialized) {
            return;
        }

        initialized = true;

        if (hasConsumedFirstOpportunity() || isStandalone()) {
            return;
        }

        bindAvailabilityListener();
        tryOfferAutomaticInstall();
        scheduleManualBranch();
    }

    var api = {
        init: init,
        tryOfferAutomaticInstall: tryOfferAutomaticInstall,
        tryOfferManualInstall: tryOfferManualInstall,
        renderAutomaticInstallSurface: renderAutomaticInstallSurface,
        removeAutomaticInstallSurface: removeAutomaticInstallSurface,
        onDismissAutomaticInstall: onDismissAutomaticInstall,
        onInstallAutomaticClick: onInstallAutomaticClick,
        handleInstallRunResult: handleInstallRunResult,
        isAutomaticSurfaceVisible: function () {
            return automaticSurfaceVisible;
        },
        hasConsumedFirstOpportunity: hasConsumedFirstOpportunity,
        markConsumedFirstOpportunity: markConsumedFirstOpportunity,
        resetConsumedFirstOpportunity: resetConsumedFirstOpportunity,
        buildStorageKey: buildStorageKey,
        isStandalone: isStandalone,
        isIosLike: isIosLike,
        isPwaInstallAvailable: isPwaInstallAvailable,
        constants: {
            STORAGE_KEY_PREFIX: STORAGE_KEY_PREFIX,
            STORAGE_VALUE: STORAGE_VALUE,
            AUTOMATIC_SURFACE_ROOT_ID: AUTOMATIC_SURFACE_ROOT_ID,
            AUTOMATIC_TITLE: AUTOMATIC_TITLE,
            AUTOMATIC_BODY: AUTOMATIC_BODY,
            DISMISS_LABEL: DISMISS_LABEL,
            INSTALL_LABEL: INSTALL_LABEL,
            MANUAL_ALERT_MESSAGE: MANUAL_ALERT_MESSAGE,
            PWA_INSTALL_ACTION: PWA_INSTALL_ACTION
        }
    };

    getGlobalRoot().PwaInstallFirstOpportunity = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }

    init();
})();
