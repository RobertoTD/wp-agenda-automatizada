/**
 * PWA Notifications First Opportunity — dashboard-only permission prompt (MC2).
 *
 * Standalone only; requests Notification permission via real button click.
 */
(function () {
    'use strict';

    var STORAGE_KEY_PREFIX = 'aa_pwa_notifications_first_opportunity_v1';
    var STORAGE_VALUE = '1';
    var SURFACE_ROOT_ID = 'aa-pwa-notifications-first-opportunity-root';

    var SURFACE_TITLE = 'Activa las notificaciones de DEOIA';
    var SURFACE_BODY = ''
        + 'Recibe avisos importantes aunque la app esté cerrada. Pulsa Continuar y después '
        + 'acepta la solicitud de notificaciones que aparecerá en tu dispositivo.';
    var DISMISS_LABEL = 'Ahora no';
    var ENABLE_LABEL = 'Continuar';

    var initialized = false;
    var surfaceVisible = false;
    var requestInFlight = false;

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
            root.console.warn('[PwaNotificationsFirstOpportunity] ' + message, err || '');
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

    function hasNotificationApi() {
        var root = getGlobalRoot();

        return !!(root.window && root.window.Notification);
    }

    function getNotificationPermission() {
        if (!hasNotificationApi()) {
            return '';
        }

        try {
            return String(getGlobalRoot().Notification.permission || '');
        } catch (err) {
            return '';
        }
    }

    function isEligibleForFirstOpportunity() {
        if (!isStandalone()) {
            return false;
        }

        if (!hasNotificationApi()) {
            return false;
        }

        if (getNotificationPermission() !== 'default') {
            return false;
        }

        if (hasConsumedFirstOpportunity()) {
            return false;
        }

        if (requestInFlight) {
            return false;
        }

        return true;
    }

    function getDocument() {
        var doc = getGlobalRoot().document;

        if (!doc || !doc.body || typeof doc.createElement !== 'function') {
            return null;
        }

        return doc;
    }

    function applySurfaceStyles(element, styles) {
        if (!element || !element.style || !styles) {
            return;
        }

        Object.keys(styles).forEach(function (key) {
            element.style[key] = styles[key];
        });
    }

    function removeSurface() {
        var doc = getGlobalRoot().document;

        if (doc && typeof doc.getElementById === 'function') {
            var root = doc.getElementById(SURFACE_ROOT_ID);

            if (root && root.parentNode && typeof root.parentNode.removeChild === 'function') {
                root.parentNode.removeChild(root);
            }
        }

        surfaceVisible = false;
    }

    /**
     * @param {string} permission
     */
    function handlePermissionResult(permission) {
        requestInFlight = false;

        if (permission === 'granted' || permission === 'denied' || permission === 'default') {
            markConsumedFirstOpportunity();
        }
    }

    function onDismissClick(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        removeSurface();
        markConsumedFirstOpportunity();
    }

    function onEnableNotificationsClick(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        if (requestInFlight) {
            return;
        }

        requestInFlight = true;

        var root = getGlobalRoot();
        var permissionPromise;

        if (!hasNotificationApi() || typeof root.Notification.requestPermission !== 'function') {
            requestInFlight = false;
            removeSurface();
            return;
        }

        try {
            permissionPromise = root.Notification.requestPermission();
        } catch (err) {
            requestInFlight = false;
            removeSurface();
            warn('requestPermission failed:', err);
            return;
        }

        removeSurface();

        Promise.resolve(permissionPromise)
            .then(function (permission) {
                handlePermissionResult(String(permission || ''));
            })
            .catch(function (err) {
                requestInFlight = false;
                warn('requestPermission rejected:', err);
            });
    }

    /**
     * @returns {boolean}
     */
    function renderSurface() {
        if (surfaceVisible || !isEligibleForFirstOpportunity()) {
            return false;
        }

        var doc = getDocument();

        if (!doc) {
            return false;
        }

        if (doc.getElementById(SURFACE_ROOT_ID)) {
            surfaceVisible = true;
            return true;
        }

        var root = doc.createElement('div');
        root.setAttribute('id', SURFACE_ROOT_ID);
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'aa-pwa-notifications-first-opportunity-title');
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
        backdrop.className = 'aa-pwa-notifications-first-opportunity-backdrop';
        applySurfaceStyles(backdrop, {
            position: 'absolute',
            inset: '0',
            backgroundColor: 'rgba(17, 24, 39, 0.45)'
        });

        var card = doc.createElement('div');
        card.className = 'aa-pwa-notifications-first-opportunity-card';
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
        title.setAttribute('id', 'aa-pwa-notifications-first-opportunity-title');
        title.className = 'aa-pwa-notifications-first-opportunity-title';
        title.textContent = SURFACE_TITLE;
        applySurfaceStyles(title, {
            margin: '0 0 8px 0',
            fontSize: '18px',
            fontWeight: '600',
            color: '#111827'
        });

        var body = doc.createElement('p');
        body.className = 'aa-pwa-notifications-first-opportunity-body';
        body.textContent = SURFACE_BODY;
        applySurfaceStyles(body, {
            margin: '0 0 16px 0',
            fontSize: '14px',
            lineHeight: '1.5',
            color: '#4b5563'
        });

        var actions = doc.createElement('div');
        actions.className = 'aa-pwa-notifications-first-opportunity-actions';
        applySurfaceStyles(actions, {
            display: 'flex',
            flexWrap: 'wrap',
            gap: '8px',
            justifyContent: 'flex-end'
        });

        var dismissButton = doc.createElement('button');
        dismissButton.type = 'button';
        dismissButton.className = 'aa-pwa-notifications-first-opportunity-dismiss';
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
        dismissButton.addEventListener('click', onDismissClick);

        var enableButton = doc.createElement('button');
        enableButton.type = 'button';
        enableButton.className = 'aa-pwa-notifications-first-opportunity-enable';
        enableButton.textContent = ENABLE_LABEL;
        applySurfaceStyles(enableButton, {
            padding: '8px 12px',
            borderRadius: '8px',
            border: '1px solid #bfdbfe',
            backgroundColor: '#eff6ff',
            color: '#1d4ed8',
            fontSize: '14px',
            fontWeight: '500',
            cursor: 'pointer'
        });
        enableButton.addEventListener('click', onEnableNotificationsClick);

        actions.appendChild(dismissButton);
        actions.appendChild(enableButton);
        card.appendChild(title);
        card.appendChild(body);
        card.appendChild(actions);
        root.appendChild(backdrop);
        root.appendChild(card);
        doc.body.appendChild(root);
        surfaceVisible = true;

        return true;
    }

    function tryOfferNotificationsFirstOpportunity() {
        if (!isEligibleForFirstOpportunity()) {
            return false;
        }

        return renderSurface();
    }

    function init() {
        if (initialized) {
            return;
        }

        initialized = true;
        tryOfferNotificationsFirstOpportunity();
    }

    var api = {
        init: init,
        tryOfferNotificationsFirstOpportunity: tryOfferNotificationsFirstOpportunity,
        renderSurface: renderSurface,
        removeSurface: removeSurface,
        onDismissClick: onDismissClick,
        onEnableNotificationsClick: onEnableNotificationsClick,
        handlePermissionResult: handlePermissionResult,
        isSurfaceVisible: function () {
            return surfaceVisible;
        },
        isRequestInFlight: function () {
            return requestInFlight;
        },
        hasConsumedFirstOpportunity: hasConsumedFirstOpportunity,
        markConsumedFirstOpportunity: markConsumedFirstOpportunity,
        resetConsumedFirstOpportunity: resetConsumedFirstOpportunity,
        buildStorageKey: buildStorageKey,
        isStandalone: isStandalone,
        hasNotificationApi: hasNotificationApi,
        getNotificationPermission: getNotificationPermission,
        isEligibleForFirstOpportunity: isEligibleForFirstOpportunity,
        constants: {
            STORAGE_KEY_PREFIX: STORAGE_KEY_PREFIX,
            STORAGE_VALUE: STORAGE_VALUE,
            SURFACE_ROOT_ID: SURFACE_ROOT_ID,
            SURFACE_TITLE: SURFACE_TITLE,
            SURFACE_BODY: SURFACE_BODY,
            DISMISS_LABEL: DISMISS_LABEL,
            ENABLE_LABEL: ENABLE_LABEL
        }
    };

    getGlobalRoot().PwaNotificationsFirstOpportunity = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }

    init();
})();
