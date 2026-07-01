/**
 * Account Module - subscription status UI
 *
 * Fetches aa_get_account_status via admin-ajax and renders account summary.
 * Billing portal opens via aa_create_billing_portal_session (server-side only).
 */

(function () {
    'use strict';

    var BADGE_BASE = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border';

    var BILLING_ERROR_MESSAGE = 'No pudimos abrir la gestión de suscripción en este momento.';

    var BILLING_LABELS = {
        PAYMENT_PENDING: 'Actualizar pago',
        CANCEL_SCHEDULED: 'Revisar suscripción',
        ACTIVE: 'Gestionar suscripción',
        SYNC_DISABLED: 'Gestionar suscripción'
    };

    var FREEMIUM_UPGRADE_LEGEND =
        'Puedes aumentar tu cuota mensual haciendo upgrade en tu suscripción.';

    var PRO_PAYMENT_FAILED_NOTICE =
        'El pago de tu suscripción no pudo realizarse. Actualiza tus datos con Gestionar suscripción para seguir disfrutando de tus beneficios Pro.';

    var VIEW = {
        SYNC_PENDING: 'sync_pending',
        PAYMENT_PENDING: 'payment_pending',
        CANCEL_SCHEDULED: 'cancel_scheduled',
        ACTIVE: 'active',
        MISSING: 'missing',
        INACTIVE: 'inactive',
        FALLBACK: 'fallback'
    };

    var billingClickBound = false;

    function getConfig() {
        return window.AA_ACCOUNT_DATA || {};
    }

    function getEl(id) {
        return document.getElementById(id);
    }

    function humanizeTier(tier) {
        if (tier === 'pro') {
            return 'Pro';
        }
        if (tier === 'freemium') {
            return 'Freemium';
        }
        return '—';
    }

    function formatDate(isoString) {
        if (!isoString || typeof isoString !== 'string') {
            return null;
        }
        var date = new Date(isoString);
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        try {
            return new Intl.DateTimeFormat('es-MX', { dateStyle: 'long' }).format(date);
        } catch (_) {
            return isoString;
        }
    }

    /**
     * @param {object} status
     * @returns {boolean}
     */
    function isFreemiumActiveAccount(status) {
        if (!status || typeof status !== 'object') {
            return false;
        }

        return status.billing_state === 'active'
            && status.plan_tier === 'freemium'
            && status.effective_access_tier === 'freemium';
    }

    /**
     * @param {object} status
     * @returns {string}
     */
    function resolveViewState(status) {
        if (!status || typeof status !== 'object') {
            return VIEW.FALLBACK;
        }

        if (status.sync_pending === true) {
            return VIEW.SYNC_PENDING;
        }
        if (status.payment_action_required === true) {
            return VIEW.PAYMENT_PENDING;
        }
        if (status.is_cancel_scheduled === true) {
            return VIEW.CANCEL_SCHEDULED;
        }
        if (status.billing_state === 'active') {
            return VIEW.ACTIVE;
        }
        if (status.billing_state === 'missing') {
            return VIEW.MISSING;
        }
        if (status.billing_state === 'inactive') {
            return VIEW.INACTIVE;
        }

        return VIEW.FALLBACK;
    }

    /**
     * @param {object} status
     * @returns {{ mode: string, label?: string, hint?: string }}
     */
    function resolveBillingAction(status) {
        var view = resolveViewState(status);

        switch (view) {
            case VIEW.SYNC_PENDING:
                return {
                    mode: 'disabled',
                    label: BILLING_LABELS.SYNC_DISABLED,
                    hint: 'Estamos sincronizando tu suscripción.'
                };
            case VIEW.PAYMENT_PENDING:
                return { mode: 'visible', label: BILLING_LABELS.ACTIVE };
            case VIEW.CANCEL_SCHEDULED:
                return { mode: 'visible', label: BILLING_LABELS.CANCEL_SCHEDULED };
            case VIEW.ACTIVE:
                if (isFreemiumActiveAccount(status)) {
                    return { mode: 'hidden' };
                }
                return { mode: 'visible', label: BILLING_LABELS.ACTIVE };
            default:
                return { mode: 'hidden' };
        }
    }

    /**
     * Pure presentation mapping for account status (testable, no DOM).
     *
     * @param {object} status
     * @returns {{
     *   view: string,
     *   badgeLabel: string,
     *   plan: string,
     *   access: string,
     *   primaryNotice: string
     * }}
     */
    function buildAccountPresentation(status) {
        var view = resolveViewState(status);
        var planTier = humanizeTier(status && status.plan_tier);
        var accessTier = humanizeTier(status && status.effective_access_tier);
        var primaryNotice = '';
        var badgeLabel = 'Estado no disponible';
        var plan = planTier;
        var access = accessTier;

        switch (view) {
            case VIEW.SYNC_PENDING:
                badgeLabel = 'Sincronizando';
                plan = planTier;
                access = accessTier;
                primaryNotice = 'Estamos sincronizando tu suscripción. Intenta de nuevo en unos minutos.';
                break;

            case VIEW.PAYMENT_PENDING:
                badgeLabel = 'Pago pendiente';
                plan = planTier !== '—' ? planTier : 'Pro';
                access = 'Freemium';
                primaryNotice = PRO_PAYMENT_FAILED_NOTICE;
                break;

            case VIEW.CANCEL_SCHEDULED: {
                badgeLabel = 'Activa';
                plan = planTier !== '—' ? planTier : 'Pro';
                access = 'Pro';
                var cancelDate = formatDate(status.cancel_at);
                if (cancelDate) {
                    primaryNotice = 'Has solicitado cancelar tu suscripción. Tus beneficios Pro seguirán activos hasta el '
                        + cancelDate
                        + '. Después de esa fecha tu agenda volverá al plan Freemium y no se realizarán nuevos cobros.';
                } else {
                    primaryNotice = 'Has solicitado cancelar tu suscripción. Tus beneficios Pro seguirán activos hasta la fecha programada. Después de esa fecha tu agenda volverá al plan Freemium y no se realizarán nuevos cobros.';
                }
                break;
            }

            case VIEW.ACTIVE:
                badgeLabel = 'Activa';
                if (isFreemiumActiveAccount(status)) {
                    plan = 'Freemium';
                    access = 'Freemium';
                    primaryNotice = FREEMIUM_UPGRADE_LEGEND;
                } else {
                    plan = planTier !== '—' ? planTier : 'Pro';
                    access = accessTier !== '—' ? accessTier : 'Pro';
                }
                break;

            case VIEW.MISSING:
                badgeLabel = 'Sin suscripción';
                plan = '—';
                access = 'Freemium';
                break;

            case VIEW.INACTIVE:
                badgeLabel = 'Inactiva';
                plan = planTier !== '—' ? planTier : 'Pro';
                access = accessTier !== '—' ? accessTier : 'Freemium';
                break;

            default:
                badgeLabel = 'Estado no disponible';
                plan = planTier;
                access = accessTier;
                break;
        }

        return {
            view: view,
            badgeLabel: badgeLabel,
            plan: plan,
            access: access,
            primaryNotice: primaryNotice
        };
    }

    /**
     * @param {string} url
     * @returns {boolean}
     */
    function isSafeStripeBillingPortalUrl(url) {
        if (typeof url !== 'string' || url.trim() === '') {
            return false;
        }

        try {
            var parsed = new URL(url);
            return parsed.protocol === 'https:' && parsed.hostname === 'billing.stripe.com';
        } catch (_) {
            return false;
        }
    }

    function setHidden(el, hidden) {
        if (!el) {
            return;
        }
        if (hidden) {
            el.classList.add('hidden');
        } else {
            el.classList.remove('hidden');
        }
    }

    function clearBillingError() {
        var errorEl = getEl('aa-account-billing-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = '';
        setHidden(errorEl, true);
    }

    function showBillingError(message) {
        var errorEl = getEl('aa-account-billing-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message;
        setHidden(errorEl, false);
    }

    function setBillingLoading(isLoading) {
        var buttonEl = getEl('aa-account-billing-button');
        var loadingEl = getEl('aa-account-billing-loading');

        setHidden(loadingEl, !isLoading);

        if (buttonEl) {
            buttonEl.disabled = isLoading;
        }
    }

    /**
     * @param {object} status
     */
    function renderBillingAction(status) {
        var actionEl = getEl('aa-account-billing-action');
        var hintEl = getEl('aa-account-billing-hint');
        var buttonEl = getEl('aa-account-billing-button');
        var loadingEl = getEl('aa-account-billing-loading');
        var billingAction = resolveBillingAction(status);
        var config = getConfig();

        clearBillingError();
        setHidden(loadingEl, true);

        if (!actionEl || !buttonEl) {
            return;
        }

        if (billingAction.mode === 'hidden') {
            setHidden(actionEl, true);
            buttonEl.disabled = false;
            buttonEl.textContent = '';
            if (hintEl) {
                hintEl.textContent = '';
                setHidden(hintEl, true);
            }
            return;
        }

        if (!config.billingNonce) {
            console.warn('[AccountModule] Missing billingNonce for aa_create_billing_portal_session');
            setHidden(actionEl, true);
            return;
        }

        setHidden(actionEl, false);
        buttonEl.textContent = billingAction.label || '';

        if (hintEl) {
            if (billingAction.hint) {
                hintEl.textContent = billingAction.hint;
                setHidden(hintEl, false);
            } else {
                hintEl.textContent = '';
                setHidden(hintEl, true);
            }
        }

        buttonEl.disabled = billingAction.mode === 'disabled';
    }

    function setBadge(el, label, className) {
        if (!el) {
            return;
        }
        el.textContent = label;
        el.className = BADGE_BASE + ' ' + className;
    }

    function setNotice(el, text, className) {
        if (!el) {
            return;
        }
        if (!text) {
            el.textContent = '';
            el.className = 'hidden';
            return;
        }
        el.textContent = text;
        el.className = 'rounded-lg border p-4 text-sm ' + className;
    }

    function renderMessages(listEl, messages) {
        if (!listEl) {
            return;
        }

        while (listEl.firstChild) {
            listEl.removeChild(listEl.firstChild);
        }

        if (!Array.isArray(messages) || messages.length === 0) {
            listEl.className = 'hidden';
            return;
        }

        messages.forEach(function (msg) {
            if (typeof msg !== 'string' || msg.trim() === '') {
                return;
            }
            var li = document.createElement('li');
            li.textContent = msg.trim();
            listEl.appendChild(li);
        });

        if (listEl.childNodes.length > 0) {
            listEl.className = 'list-disc list-inside space-y-1 text-sm text-gray-600';
        } else {
            listEl.className = 'hidden';
        }
    }

    function renderPublicSiteSection(publicSite) {
        var sectionEl = getEl('aa-account-public-site-section');
        var statusEl = getEl('aa-account-public-site-status');
        var actionEl = getEl('aa-account-public-site-action');
        var buttonEl = getEl('aa-account-public-site-activate-button');
        var previewLinkEl = getEl('aa-account-public-site-preview-link');

        if (!sectionEl || !statusEl) {
            return;
        }

        if (!publicSite || publicSite.show_section !== true) {
            setHidden(sectionEl, true);
            setHidden(actionEl, true);
            setHidden(previewLinkEl, true);
            return;
        }

        setHidden(sectionEl, false);

        if (publicSite.status === 'maintenance') {
            statusEl.textContent = 'En mantenimiento';
            setHidden(actionEl, false);
            if (buttonEl) {
                buttonEl.disabled = true;
            }
        } else {
            statusEl.textContent = 'Activo';
            setHidden(actionEl, true);
        }

        if (previewLinkEl) {
            if (typeof publicSite.public_url === 'string' && publicSite.public_url.trim() !== '') {
                previewLinkEl.href = publicSite.public_url;
                setHidden(previewLinkEl, false);
            } else {
                previewLinkEl.removeAttribute('href');
                setHidden(previewLinkEl, true);
            }
        }
    }

    function setBillingSummaryVisible(visible) {
        var badgeEl = getEl('aa-account-status-badge');
        var summaryEl = badgeEl ? badgeEl.closest('.flex.flex-wrap') : null;
        var planGridEl = getEl('aa-account-plan') ? getEl('aa-account-plan').closest('dl') : null;
        var billingActionEl = getEl('aa-account-billing-action');
        var noticeEl = getEl('aa-account-notice');
        var messagesEl = getEl('aa-account-messages');

        setHidden(summaryEl, !visible);
        setHidden(planGridEl, !visible);
        setHidden(billingActionEl, !visible);

        if (!visible) {
            setNotice(noticeEl, '', '');
            renderMessages(messagesEl, []);
            renderAccountStatusActions(getEl('aa-account-notice-actions'), []);
        }
    }

    /**
     * @param {object} status
     */
    function renderAccountStatus(status) {
        var presentation = buildAccountPresentation(status);
        var badgeEl = getEl('aa-account-status-badge');
        var planEl = getEl('aa-account-plan');
        var accessEl = getEl('aa-account-access');
        var noticeEl = getEl('aa-account-notice');
        var messagesEl = getEl('aa-account-messages');
        var noticeClass = 'border-amber-200 bg-amber-50 text-amber-800';

        switch (presentation.view) {
            case VIEW.SYNC_PENDING:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-gray-100 text-gray-700 border-gray-200');
                noticeClass = 'border-gray-200 bg-gray-50 text-gray-700';
                break;

            case VIEW.PAYMENT_PENDING:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-amber-50 text-amber-800 border-amber-200');
                break;

            case VIEW.CANCEL_SCHEDULED:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-emerald-50 text-emerald-700 border-emerald-200');
                noticeClass = 'border-blue-200 bg-blue-50 text-blue-800';
                break;

            case VIEW.ACTIVE:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-emerald-50 text-emerald-700 border-emerald-200');
                if (isFreemiumActiveAccount(status)) {
                    noticeClass = 'border-gray-200 bg-gray-50 text-gray-700';
                }
                break;

            case VIEW.MISSING:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-gray-100 text-gray-600 border-gray-200');
                break;

            case VIEW.INACTIVE:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-gray-100 text-gray-700 border-gray-200');
                break;

            default:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-gray-100 text-gray-600 border-gray-200');
                break;
        }

        planEl.textContent = presentation.plan;
        accessEl.textContent = presentation.access;
        setNotice(noticeEl, presentation.primaryNotice, noticeClass);
        renderMessages(messagesEl, status.messages);
        renderBillingAction(status);
    }

    function mapAccountStatusErrorToUi(data) {
        if (window.AccountStatusErrorUx && typeof window.AccountStatusErrorUx.mapAccountStatusErrorToUi === 'function') {
            return window.AccountStatusErrorUx.mapAccountStatusErrorToUi(data);
        }

        var code = data && data.code ? String(data.code) : null;
        var serverMsg = (data && data.message) ? String(data.message) : '';

        return {
            text: serverMsg || 'No pudimos consultar el estado de cuenta en este momento. Intenta más tarde.',
            code: code,
            actions: []
        };
    }

    function renderAccountStatusActions(actionsEl, actions) {
        if (!actionsEl) {
            return;
        }

        while (actionsEl.firstChild) {
            actionsEl.removeChild(actionsEl.firstChild);
        }

        if (!Array.isArray(actions) || actions.length === 0) {
            setHidden(actionsEl, true);
            return;
        }

        var linkCls =
            'inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 transition-colors';

        actions.forEach(function (action) {
            if (!action || !action.url || !action.label) {
                return;
            }
            var link = document.createElement('a');
            link.href = action.url;
            link.textContent = action.label;
            link.className = linkCls;
            actionsEl.appendChild(link);
        });

        setHidden(actionsEl, actionsEl.childNodes.length === 0);
    }

    function renderAccountStatusError(ui, options) {
        options = options || {};
        var messageEl = options.messageEl || getEl('aa-account-status-error-message');
        var actionsEl = options.actionsEl || getEl('aa-account-status-error-actions');

        if (messageEl) {
            messageEl.textContent = ui && ui.text ? ui.text : '';
        }

        renderAccountStatusActions(actionsEl, ui && ui.actions ? ui.actions : []);
    }

    function showLoading() {
        var loading = getEl('aa-account-status-loading');
        var content = getEl('aa-account-status-content');
        var error = getEl('aa-account-status-error');

        if (loading) {
            loading.classList.remove('hidden');
        }
        if (content) {
            content.classList.add('hidden');
        }
        if (error) {
            error.classList.add('hidden');
        }
    }

    function showContent() {
        var loading = getEl('aa-account-status-loading');
        var content = getEl('aa-account-status-content');
        var error = getEl('aa-account-status-error');

        if (loading) {
            loading.classList.add('hidden');
        }
        if (content) {
            content.classList.remove('hidden');
        }
        if (error) {
            error.classList.add('hidden');
        }
    }

    function showError(ui) {
        var loading = getEl('aa-account-status-loading');
        var content = getEl('aa-account-status-content');
        var error = getEl('aa-account-status-error');

        if (loading) {
            loading.classList.add('hidden');
        }
        if (content) {
            content.classList.add('hidden');
        }
        if (error) {
            error.classList.remove('hidden');
        }

        if (ui) {
            renderAccountStatusError(ui);
        }
    }

    function fetchAccountStatus() {
        var config = getConfig();
        var ajaxUrl = config.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        var nonce = config.nonce || '';

        if (!nonce) {
            console.warn('[AccountModule] Missing nonce for aa_get_account_status');
            return Promise.resolve(null);
        }

        var url = ajaxUrl
            + '?action=aa_get_account_status'
            + '&_wpnonce='
            + encodeURIComponent(nonce);

        return fetch(url)
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success && data.data) {
                    return {
                        accountStatus: data.data.account_status || null,
                        publicSite: data.data.public_site || null
                    };
                }

                if (data && !data.success && data.data) {
                    console.warn('[AccountModule] Account status error:', data.data.code || '', data.data.message || '');
                    return {
                        accountStatus: null,
                        publicSite: data.data.public_site || null,
                        statusError: mapAccountStatusErrorToUi(data.data)
                    };
                }

                return {
                    accountStatus: null,
                    publicSite: null,
                    statusError: mapAccountStatusErrorToUi({ code: 'account_backend_unreachable' })
                };
            })
            .catch(function (err) {
                console.error('[AccountModule] Fetch failed:', err);
                return {
                    accountStatus: null,
                    publicSite: null,
                    statusError: mapAccountStatusErrorToUi({ code: 'account_backend_unreachable' })
                };
            });
    }

    function createBillingPortalSession() {
        var config = getConfig();
        var ajaxUrl = config.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        var billingNonce = config.billingNonce || '';

        if (!billingNonce) {
            return Promise.resolve({ ok: false });
        }

        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'aa_create_billing_portal_session',
                _wpnonce: billingNonce
            })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success && data.data && typeof data.data.url === 'string') {
                    return { ok: true, url: data.data.url };
                }

                if (data && !data.success && data.data) {
                    console.warn('[AccountModule] Billing portal error:', data.data.code || '');
                }

                return { ok: false };
            })
            .catch(function (err) {
                console.error('[AccountModule] Billing portal fetch failed:', err);
                return { ok: false };
            });
    }

    function handleBillingButtonClick() {
        var buttonEl = getEl('aa-account-billing-button');

        if (!buttonEl || buttonEl.disabled) {
            return;
        }

        clearBillingError();
        setBillingLoading(true);

        createBillingPortalSession().then(function (result) {
            if (result.ok && isSafeStripeBillingPortalUrl(result.url)) {
                window.location.href = result.url;
                return;
            }

            setBillingLoading(false);
            buttonEl.disabled = false;
            showBillingError(BILLING_ERROR_MESSAGE);
        });
    }

    function bindBillingClickHandler() {
        if (billingClickBound) {
            return;
        }

        var buttonEl = getEl('aa-account-billing-button');
        if (!buttonEl) {
            return;
        }

        buttonEl.addEventListener('click', handleBillingButtonClick);
        billingClickBound = true;
    }

    function init() {
        var root = getEl('aa-account-status-root');
        if (!root) {
            return;
        }

        bindBillingClickHandler();
        showLoading();

        fetchAccountStatus().then(function (payload) {
            if (!payload) {
                showError(mapAccountStatusErrorToUi({ code: 'account_backend_unreachable' }));
                return;
            }

            var hasBilling = !!(payload.accountStatus && typeof payload.accountStatus === 'object');
            var hasPublicSite = !!(payload.publicSite && payload.publicSite.show_section === true);
            var errorUi = payload.statusError || mapAccountStatusErrorToUi({ code: 'account_backend_unreachable' });

            if (!hasBilling && !hasPublicSite) {
                showError(errorUi);
                return;
            }

            if (hasBilling) {
                setBillingSummaryVisible(true);
                renderAccountStatusActions(getEl('aa-account-notice-actions'), []);
                renderAccountStatus(payload.accountStatus);
            } else {
                setBillingSummaryVisible(false);
                setNotice(
                    getEl('aa-account-notice'),
                    errorUi.text,
                    'border-gray-200 bg-gray-50 text-gray-700'
                );
                renderAccountStatusActions(getEl('aa-account-notice-actions'), errorUi.actions);
            }

            renderPublicSiteSection(payload.publicSite);
            showContent();
        });
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', init);
    }

    var presentationApi = {
        resolveViewState: resolveViewState,
        resolveBillingAction: resolveBillingAction,
        isFreemiumActiveAccount: isFreemiumActiveAccount,
        buildAccountPresentation: buildAccountPresentation,
        VIEW: VIEW
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = presentationApi;
    }
})();
