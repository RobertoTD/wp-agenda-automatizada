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

    var AUTOMATIC_TITLE = 'Instala la app "DEOIA Citas"';
    var AUTOMATIC_BODY = ''
        + 'Ten tu agenda disponible fácilmente desde tu dispositivo. Pulsa Continuar y después '
        + 'confirma la instalación en la ventana que aparecerá.';
    var DISMISS_LABEL = 'Ahora no';
    var INSTALL_LABEL = 'Continuar';
    var CLOSE_LABEL = 'Cerrar solicitud de instalación';

    // Logo DEOIA Citas reutilizado inline (mismo path que assets/img/deoia-citas-logo.svg).
    var LOGO_SVG = ''
        + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 261.33334 261.33301" '
        + 'width="38" height="38" aria-hidden="true" focusable="false">'
        + '<path fill="#8b5cf6" d="M43.2754 238.56813c-6.67841-1.50448-14.20397-7.55082-17.16525-13.79124'
        + '-3.51017-7.39715-3.93082-166.376054-.45429-171.694076C32.2034 43.067099 40.1448 38.735125 '
        + '51.95814 38.735125h8.04282l.008 7.666667c.0166 17.139723 5.80599 25.000001 18.41342 25.000001 '
        + '13.07937 0 18.91246-7.417597 18.91246-24.049877v-8.66258l33.66663.356228 33.66666.356228.3946 '
        + '10.621138c.59722 16.074673 8.67682 24.017319 21.90671 21.535376 9.71368-1.822299 14.03992-8.752066 '
        + '14.66466-23.489847l.36737-8.666667 6.66667-.431741c11.57258-.749456 20.2725 3.803455 27.03493 '
        + '14.148127 3.45557 5.286074 3.01328 164.302552-.47745 171.658712-3.13346 6.60326-10.52935 12.25492'
        + '-18.1859 13.89694-8.1186 1.74114-165.99202 1.6451-173.76382-.10573Zm174.79952-17.78576c3.28608'
        + '-3.28607 3.28608-3.28607 2.9394-64.99999l-.34668-61.71392h-90.00001-90l-.34668 61.71392c-.34668 '
        + '61.71392-.34668 61.71392 2.93939 64.99999 5.18726 5.18726 169.62732 5.18726 174.81458 0ZM97.00097 '
        + '181.28305C76.12958 159.83071 76.77275 161.28567 84.49524 152.993c7.5025-8.05643 6.96527-8.18982 '
        + '20.44119 5.07546 14.25071 14.02796 10.02322 16.33501 49.49871-27.01279 14.30579-15.70908 13.57673'
        + '-15.4461 21.89916-7.89924 7.35081 6.66579 3.166 15.87243-13.74779 30.24536-.43149.36667-6.96534 '
        + '7.56667-14.51969 15.99999-33.0617 36.90861-27.88754 35.70476-51.06585 11.88127ZM72.84744 '
        + '61.305019c-9.02379-7.098116-4.10332-46.569894 5.8053-46.569894 7.78879 0 14.42046 35.675811 '
        + '8.32781 44.800395-3.16797 4.744474-9.36471 5.52032-14.13311 1.769499Zm103.15353.09677c-7.9501'
        + '-7.950085-2.27388-46.666664 6.84177-46.666664 8.20737 0 14.18537 25.101148 9.97407 41.880333'
        + '-1.87851 7.484601-11.4058 10.196376-16.81584 4.786334Z"/></svg>';

    // Indicador de descarga/instalación (SVG inline, estilo stroke coherente con el header).
    var INSTALL_ARROW_SVG = ''
        + '<span style="position:absolute;right:-2px;bottom:-2px;display:inline-flex;align-items:center;'
        + 'justify-content:center;width:24px;height:24px;border-radius:9999px;background:#7c3aed;'
        + 'color:#ffffff;border:2px solid #ffffff;box-sizing:border-box;">'
        + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" '
        + 'stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">'
        + '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v10m0 0l-4-4m4 4l4-4M5 21h14"/>'
        + '</svg></span>';

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

        var graphic = doc.createElement('div');
        graphic.className = 'aa-pwa-install-first-opportunity-graphic';
        graphic.setAttribute('aria-hidden', 'true');
        applySurfaceStyles(graphic, {
            display: 'flex',
            justifyContent: 'center',
            margin: '0 0 16px 0'
        });

        var logoBadge = doc.createElement('div');
        logoBadge.className = 'aa-pwa-install-first-opportunity-logo';
        applySurfaceStyles(logoBadge, {
            position: 'relative',
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: '64px',
            height: '64px',
            borderRadius: '9999px',
            backgroundColor: '#f5f3ff',
            boxSizing: 'border-box'
        });

        try {
            logoBadge.innerHTML = LOGO_SVG + INSTALL_ARROW_SVG;
        } catch (err) {
            // Fake/limited DOM (p. ej. pruebas): el bloque es decorativo, se puede omitir.
        }

        graphic.appendChild(logoBadge);

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

        var closeButton = doc.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'aa-pwa-install-first-opportunity-close';
        closeButton.setAttribute('aria-label', CLOSE_LABEL);
        closeButton.textContent = '\u00d7';
        applySurfaceStyles(closeButton, {
            position: 'fixed',
            top: '1rem',
            right: '1rem',
            zIndex: '2',
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: '2.5rem',
            height: '2.5rem',
            padding: '0',
            border: '1px solid rgb(229, 231, 235)',
            borderRadius: '9999px',
            backgroundColor: '#ffffff',
            color: 'rgb(55, 65, 81)',
            fontSize: '1.5rem',
            lineHeight: '1',
            boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1)',
            cursor: 'pointer',
            boxSizing: 'border-box'
        });
        closeButton.addEventListener('click', onDismissAutomaticInstall);

        actions.appendChild(dismissButton);
        actions.appendChild(installButton);
        card.appendChild(graphic);
        card.appendChild(title);
        card.appendChild(body);
        card.appendChild(actions);
        root.appendChild(backdrop);
        root.appendChild(card);
        root.appendChild(closeButton);
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
