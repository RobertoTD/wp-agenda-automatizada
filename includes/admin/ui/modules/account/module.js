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
        'Puedes aumentar tu cuota mensual con Pro.';

    var UPGRADE_CHECKOUT_ERROR_MESSAGE = 'No pudimos abrir el checkout de Pro. Intenta de nuevo.';

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
    var upgradeClickBound = false;
    var upgradeCardOpen = false;

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
        el.className = 'text-sm ' + className;
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
        var summaryEl = getEl('aa-account-billing-summary');
        var billingActionEl = getEl('aa-account-billing-action');
        var upgradeSectionEl = getEl('aa-account-upgrade-section');
        var noticeEl = getEl('aa-account-notice');
        var messagesEl = getEl('aa-account-messages');

        setHidden(summaryEl, !visible);
        setHidden(billingActionEl, !visible);
        setHidden(upgradeSectionEl, !visible);

        if (!visible) {
            upgradeCardOpen = false;
            setNotice(noticeEl, '', '');
            renderMessages(messagesEl, []);
            renderAccountStatusActions(getEl('aa-account-notice-actions'), []);
            renderBenefitQuotas({});
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
        var noticeClass = 'rounded-lg border p-4 border-amber-200 bg-amber-50 text-amber-800';

        switch (presentation.view) {
            case VIEW.SYNC_PENDING:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-gray-100 text-gray-700 border-gray-200');
                noticeClass = 'rounded-lg border p-4 border-gray-200 bg-gray-50 text-gray-700';
                break;

            case VIEW.PAYMENT_PENDING:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-amber-50 text-amber-800 border-amber-200');
                break;

            case VIEW.CANCEL_SCHEDULED:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-emerald-50 text-emerald-700 border-emerald-200');
                noticeClass = 'rounded-lg border p-4 border-blue-200 bg-blue-50 text-blue-800';
                break;

            case VIEW.ACTIVE:
                setBadge(badgeEl, presentation.badgeLabel, 'bg-emerald-50 text-emerald-700 border-emerald-200');
                if (isFreemiumActiveAccount(status)) {
                    noticeClass = 'text-gray-600';
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
        renderUpgradeSection(status);
        renderBenefitQuotas(status);
    }

    function resolveAccountUpgradeUx() {
        if (typeof window === 'undefined') {
            return null;
        }
        if (window.AccountUpgradeUx && typeof window.AccountUpgradeUx.shouldShowUpgradeCta === 'function') {
            return window.AccountUpgradeUx;
        }
        return null;
    }

    /**
     * @param {object} status
     * @returns {boolean}
     */
    function shouldShowUpgradeCta(status) {
        var ux = resolveAccountUpgradeUx();
        if (ux) {
            return ux.shouldShowUpgradeCta(status);
        }
        return !!(status && status.upgrade_to_pro_available === true);
    }

    /**
     * @param {string} url
     * @returns {boolean}
     */
    function isSafeStripeCheckoutUrl(url) {
        var ux = resolveAccountUpgradeUx();
        if (ux && typeof ux.isSafeStripeCheckoutUrl === 'function') {
            return ux.isSafeStripeCheckoutUrl(url);
        }

        if (typeof url !== 'string' || url.trim() === '') {
            return false;
        }

        try {
            var parsed = new URL(url);
            return parsed.protocol === 'https:' && parsed.hostname === 'checkout.stripe.com';
        } catch (_) {
            return false;
        }
    }

    function clearUpgradeError() {
        var errorEl = getEl('aa-account-upgrade-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = '';
        setHidden(errorEl, true);
    }

    function showUpgradeError(message) {
        var errorEl = getEl('aa-account-upgrade-error');
        if (!errorEl) {
            return;
        }
        errorEl.textContent = message;
        setHidden(errorEl, false);
    }

    function setUpgradeLoading(isLoading) {
        var continueEl = getEl('aa-account-upgrade-continue');
        var loadingEl = getEl('aa-account-upgrade-loading');

        setHidden(loadingEl, !isLoading);

        if (continueEl) {
            continueEl.disabled = isLoading;
        }
    }

    /**
     * @param {object} status
     */
    function renderUpgradeSection(status) {
        var sectionEl = getEl('aa-account-upgrade-section');
        var ctaWrapEl = getEl('aa-account-upgrade-cta-wrap');
        var cardEl = getEl('aa-account-upgrade-card');
        var showUpgrade = shouldShowUpgradeCta(status);
        var ux = resolveAccountUpgradeUx();
        var uiState = ux
            ? ux.buildUpgradeUiState(showUpgrade, upgradeCardOpen)
            : {
                sectionVisible: showUpgrade,
                ctaVisible: showUpgrade && !upgradeCardOpen,
                cardVisible: showUpgrade && upgradeCardOpen
            };

        clearUpgradeError();
        setHidden(getEl('aa-account-upgrade-loading'), true);

        if (!sectionEl) {
            return;
        }

        if (!uiState.sectionVisible) {
            upgradeCardOpen = false;
            setHidden(sectionEl, true);
            setHidden(ctaWrapEl, true);
            setHidden(cardEl, true);
            return;
        }

        setHidden(sectionEl, false);
        setHidden(ctaWrapEl, !uiState.ctaVisible);
        setHidden(cardEl, !uiState.cardVisible);

        if (!uiState.cardVisible) {
            setUpgradeLoading(false);
            var continueEl = getEl('aa-account-upgrade-continue');
            if (continueEl) {
                continueEl.disabled = false;
            }
        }
    }

    function showUpgradeReturnNotice() {
        var ux = resolveAccountUpgradeUx();
        var parsed = ux
            ? ux.parseUpgradeReturnNotice(window.location.search)
            : { notice: null, className: null };
        var noticeEl = getEl('aa-account-upgrade-return-notice');

        if (!noticeEl || !parsed.notice) {
            if (noticeEl) {
                noticeEl.textContent = '';
                setHidden(noticeEl, true);
            }
            return;
        }

        noticeEl.textContent = parsed.notice;
        noticeEl.className = 'rounded-lg border p-4 text-sm ' + (parsed.className || 'border-gray-200 bg-gray-50 text-gray-700');
        setHidden(noticeEl, false);
    }

    function createUpgradeCheckoutSession() {
        var config = getConfig();
        var ajaxUrl = config.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        var upgradeNonce = config.upgradeCheckoutNonce || '';

        if (!upgradeNonce) {
            return Promise.resolve({ ok: false, code: 'missing_nonce' });
        }

        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'aa_create_upgrade_checkout_session',
                _wpnonce: upgradeNonce
            })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success && data.data && typeof data.data.checkout_url === 'string') {
                    return { ok: true, checkout_url: data.data.checkout_url };
                }

                var ux = resolveAccountUpgradeUx();
                var message = ux
                    ? ux.mapUpgradeCheckoutErrorToUi(data && data.data ? data.data : null)
                    : UPGRADE_CHECKOUT_ERROR_MESSAGE;
                var code = data && data.data && data.data.code ? String(data.data.code) : '';

                return { ok: false, message: message, code: code };
            })
            .catch(function (err) {
                console.error('[AccountModule] Upgrade checkout fetch failed:', err);
                return { ok: false, message: UPGRADE_CHECKOUT_ERROR_MESSAGE };
            });
    }

    function handleUpgradeButtonClick() {
        upgradeCardOpen = true;
        clearUpgradeError();
        renderUpgradeSection({ upgrade_to_pro_available: true });
    }

    function handleUpgradeBackClick() {
        upgradeCardOpen = false;
        clearUpgradeError();
        setUpgradeLoading(false);
        renderUpgradeSection({ upgrade_to_pro_available: true });
    }

    function handleUpgradeContinueClick() {
        var continueEl = getEl('aa-account-upgrade-continue');

        if (!continueEl || continueEl.disabled) {
            return;
        }

        clearUpgradeError();
        setUpgradeLoading(true);

        createUpgradeCheckoutSession().then(function (result) {
            if (result.ok && isSafeStripeCheckoutUrl(result.checkout_url)) {
                window.location.href = result.checkout_url;
                return;
            }

            setUpgradeLoading(false);
            continueEl.disabled = false;
            showUpgradeError(result.message || UPGRADE_CHECKOUT_ERROR_MESSAGE);
        });
    }

    function bindUpgradeClickHandlers() {
        if (upgradeClickBound) {
            return;
        }

        var acquireEl = getEl('aa-account-upgrade-button');
        var backEl = getEl('aa-account-upgrade-back');
        var continueEl = getEl('aa-account-upgrade-continue');

        if (acquireEl) {
            acquireEl.addEventListener('click', handleUpgradeButtonClick);
        }
        if (backEl) {
            backEl.addEventListener('click', handleUpgradeBackClick);
        }
        if (continueEl) {
            continueEl.addEventListener('click', handleUpgradeContinueClick);
        }

        upgradeClickBound = true;
    }

    function resolveBenefitQuotasUx() {
        if (window.AccountBenefitQuotasUx && typeof window.AccountBenefitQuotasUx.buildBenefitQuotasRenderPlan === 'function') {
            return window.AccountBenefitQuotasUx;
        }
        return null;
    }

    /**
     * @param {object} status
     */
    function renderBenefitQuotas(status) {
        var sectionEl = getEl('aa-account-benefit-quotas');
        var listEl = getEl('aa-account-benefit-quotas-list');
        var unavailableEl = getEl('aa-account-benefit-quotas-unavailable');
        if (!sectionEl || !listEl || !unavailableEl) {
            return;
        }

        var ux = resolveBenefitQuotasUx();
        var plan = ux
            ? ux.buildBenefitQuotasRenderPlan(status && status.benefit_quotas)
            : { visible: false, unavailableMessage: null, items: [] };

        while (listEl.firstChild) {
            listEl.removeChild(listEl.firstChild);
        }

        if (!plan.visible) {
            unavailableEl.textContent = '';
            setHidden(unavailableEl, true);
            setHidden(sectionEl, true);
            return;
        }

        setHidden(sectionEl, false);

        if (plan.unavailableMessage) {
            unavailableEl.textContent = plan.unavailableMessage;
            setHidden(unavailableEl, false);
            setHidden(listEl, true);
            return;
        }

        unavailableEl.textContent = '';
        setHidden(unavailableEl, true);
        setHidden(listEl, false);

        plan.items.forEach(function (item) {
            var li = document.createElement('li');

            var title = document.createElement('p');
            title.className = 'font-medium text-gray-900';
            title.textContent = item.title;
            li.appendChild(title);

            if (item.remainingLine) {
                var remaining = document.createElement('p');
                remaining.className = 'text-gray-600 mt-0.5';
                remaining.textContent = item.remainingLine;
                li.appendChild(remaining);
            }

            if (item.detailLine) {
                var detail = document.createElement('p');
                detail.className = 'text-gray-500 mt-0.5 text-sm';
                detail.textContent = item.detailLine;
                li.appendChild(detail);
            }

            listEl.appendChild(li);
        });
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
        var service = window.AccountStatusService;

        if (!service || typeof service.fetchStatus !== 'function') {
            return Promise.resolve(null);
        }

        return service.fetchStatus()
            .then(function (data) {
                return {
                    accountStatus: data.account_status || null,
                    publicSite: data.public_site || null
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
        bindUpgradeClickHandlers();
        showUpgradeReturnNotice();
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
                    'rounded-lg border p-4 border-gray-200 bg-gray-50 text-gray-700'
                );
                renderAccountStatusActions(getEl('aa-account-notice-actions'), errorUi.actions);
            }

            renderPublicSiteSection(payload.publicSite);
            showContent();
        });
    }

    // ─── Training card (C8A2) — independent of account status ───────────────

    var trainingStatusRequestId = 0;
    var trainingConsentRequestId = 0;
    var trainingMutationBusy = false;
    var trainingCardBound = false;

    function resolveTrainingUx() {
        if (window.TrainingAccountUx && typeof window.TrainingAccountUx.buildEnrollmentPresentation === 'function') {
            return window.TrainingAccountUx;
        }
        return null;
    }

    function resolveTrainingService() {
        if (window.TrainingService && typeof window.TrainingService.getStatus === 'function') {
            return window.TrainingService;
        }
        return null;
    }

    function getTrainingConfig() {
        return window.AA_TRAINING_DATA || {};
    }

    function setTrainingPanel(mode) {
        var loadingEl = getEl('aa-training-card-loading');
        var contentEl = getEl('aa-training-card-content');
        var errorEl = getEl('aa-training-card-error');

        if (loadingEl) {
            loadingEl.classList.toggle('hidden', mode !== 'loading');
        }
        if (contentEl) {
            contentEl.classList.toggle('hidden', mode !== 'content');
        }
        if (errorEl) {
            errorEl.classList.toggle('hidden', mode !== 'error');
        }
    }

    function clearTrainingActionHosts() {
        var actionsEl = getEl('aa-training-card-actions');
        var errorActionsEl = getEl('aa-training-card-error-actions');
        var consentActionsEl = getEl('aa-training-consent-actions');
        if (actionsEl) {
            actionsEl.innerHTML = '';
        }
        if (errorActionsEl) {
            errorActionsEl.innerHTML = '';
        }
        if (consentActionsEl) {
            consentActionsEl.innerHTML = '';
        }
    }

    /**
     * @param {HTMLElement|null} host
     * @param {{id: string, label: string, kind?: string}|null} action
     * @param {string} className
     * @param {boolean} [disabled]
     */
    function appendTrainingAction(host, action, className, disabled) {
        if (!host || !action) {
            return;
        }

        var cfg = getTrainingConfig();
        var el;

        if (action.kind === 'link' && action.id === 'open') {
            el = document.createElement('a');
            el.href = typeof cfg.trainingModuleUrl === 'string' ? cfg.trainingModuleUrl : '#';
            el.className = className;
            el.textContent = action.label;
            el.setAttribute('data-aa-training-action', action.id);
        } else {
            el = document.createElement('button');
            el.type = 'button';
            el.className = className;
            el.textContent = action.label;
            el.setAttribute('data-aa-training-action', action.id);
            if (disabled) {
                el.disabled = true;
            }
        }

        host.appendChild(el);
    }

    var PRIMARY_BTN =
        'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors';
    var SECONDARY_BTN =
        'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-60 disabled:cursor-not-allowed transition-colors';

    /**
     * @param {object} presentation from TrainingAccountUx.buildEnrollmentPresentation
     * @param {boolean} [controlsDisabled]
     */
    function renderTrainingEnrollment(presentation, controlsDisabled) {
        var copyEl = getEl('aa-training-card-copy');
        var actionsEl = getEl('aa-training-card-actions');
        var errorMsgEl = getEl('aa-training-card-error-message');
        var errorActionsEl = getEl('aa-training-card-error-actions');
        var consentSection = getEl('aa-training-consent-section');

        clearTrainingActionHosts();

        if (presentation.accessState === 'error') {
            if (errorMsgEl) {
                errorMsgEl.textContent = presentation.copy;
            }
            appendTrainingAction(errorActionsEl, presentation.primaryAction, PRIMARY_BTN, !!controlsDisabled);
            setTrainingPanel('error');
            if (consentSection) {
                consentSection.classList.add('hidden');
            }
            return;
        }

        if (copyEl) {
            copyEl.textContent = presentation.copy;
        }
        appendTrainingAction(actionsEl, presentation.primaryAction, PRIMARY_BTN, !!controlsDisabled);
        appendTrainingAction(actionsEl, presentation.secondaryAction, SECONDARY_BTN, !!controlsDisabled);

        if (consentSection) {
            consentSection.classList.toggle('hidden', !presentation.showConsent);
        }

        setTrainingPanel('content');
    }

    /**
     * @param {object} consentPresentation
     * @param {boolean} [controlsDisabled]
     */
    function renderTrainingConsent(consentPresentation, controlsDisabled) {
        var section = getEl('aa-training-consent-section');
        if (!section || section.classList.contains('hidden')) {
            return;
        }

        var introEl = getEl('aa-training-consent-intro');
        var statusEl = getEl('aa-training-consent-status');
        var actionsEl = getEl('aa-training-consent-actions');

        if (introEl) {
            introEl.textContent = consentPresentation.intro;
        }
        if (statusEl) {
            statusEl.textContent = consentPresentation.statusCopy || '';
            statusEl.classList.toggle('hidden', !consentPresentation.statusCopy);
        }
        if (actionsEl) {
            actionsEl.innerHTML = '';
            appendTrainingAction(actionsEl, consentPresentation.primaryAction, PRIMARY_BTN, !!controlsDisabled);
            appendTrainingAction(actionsEl, consentPresentation.secondaryAction, SECONDARY_BTN, !!controlsDisabled);
        }
    }

    function hideTrainingConsent() {
        var section = getEl('aa-training-consent-section');
        if (section) {
            section.classList.add('hidden');
        }
    }

    function setTrainingControlsDisabled(disabled) {
        var root = getEl('aa-training-card-root');
        if (!root) {
            return;
        }
        var buttons = root.querySelectorAll('button[data-aa-training-action]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = !!disabled;
        }
    }

    function loadTrainingConsent(options) {
        options = options || {};
        var ux = resolveTrainingUx();
        var service = resolveTrainingService();
        if (!ux || !service) {
            return Promise.resolve();
        }

        var requestId = ++trainingConsentRequestId;
        renderTrainingConsent(ux.buildConsentPresentation(ux.CONSENT.LOADING), true);

        return service.getConsentStatus()
            .then(function (result) {
                if (requestId !== trainingConsentRequestId) {
                    return;
                }
                var consent = result && result.data && result.data.consent
                    ? result.data.consent
                    : null;
                var uiState = ux.resolveConsentUiState(consent);
                renderTrainingConsent(ux.buildConsentPresentation(uiState), !!options.controlsDisabled);
            })
            .catch(function () {
                if (requestId !== trainingConsentRequestId) {
                    return;
                }
                renderTrainingConsent(ux.buildConsentPresentation(ux.CONSENT.ERROR), false);
            });
    }

    function loadTrainingStatus(options) {
        options = options || {};
        var ux = resolveTrainingUx();
        var service = resolveTrainingService();
        var root = getEl('aa-training-card-root');

        if (!root) {
            return Promise.resolve();
        }

        if (!ux || !service) {
            if (ux) {
                renderTrainingEnrollment(ux.buildEnrollmentPresentation(ux.ACCESS.ERROR), false);
            }
            return Promise.resolve();
        }

        var requestId = ++trainingStatusRequestId;
        if (!options.silent) {
            setTrainingPanel('loading');
            hideTrainingConsent();
        }

        return service.getStatus()
            .then(function (result) {
                if (requestId !== trainingStatusRequestId) {
                    return;
                }
                var accessState = ux.resolveAccessState(result && result.data ? result.data : null);
                var presentation = ux.buildEnrollmentPresentation(accessState);
                renderTrainingEnrollment(presentation, !!trainingMutationBusy);
                if (presentation.showConsent) {
                    return loadTrainingConsent({ controlsDisabled: !!trainingMutationBusy });
                }
                hideTrainingConsent();
            })
            .catch(function () {
                if (requestId !== trainingStatusRequestId) {
                    return;
                }
                renderTrainingEnrollment(ux.buildEnrollmentPresentation(ux.ACCESS.ERROR), false);
                hideTrainingConsent();
            });
    }

    function runTrainingEnrollmentMutation(serviceMethod) {
        var service = resolveTrainingService();
        var ux = resolveTrainingUx();
        if (!service || !ux || trainingMutationBusy) {
            return Promise.resolve();
        }

        trainingMutationBusy = true;
        setTrainingControlsDisabled(true);

        var mutationPromise;
        if (serviceMethod === 'enroll') {
            mutationPromise = service.enroll();
        } else if (serviceMethod === 'unsubscribe') {
            mutationPromise = service.unsubscribe();
        } else {
            trainingMutationBusy = false;
            setTrainingControlsDisabled(false);
            return Promise.resolve();
        }

        return mutationPromise
            .catch(function () {
                // Always refresh from server; do not assume local state.
            })
            .then(function () {
                return loadTrainingStatus({ silent: true });
            })
            .then(function () {
                trainingMutationBusy = false;
                setTrainingControlsDisabled(false);
            })
            .catch(function () {
                trainingMutationBusy = false;
                setTrainingControlsDisabled(false);
            });
    }

    function runTrainingConsentMutation(serviceMethod) {
        var service = resolveTrainingService();
        var ux = resolveTrainingUx();
        if (!service || !ux || trainingMutationBusy) {
            return Promise.resolve();
        }

        trainingMutationBusy = true;
        setTrainingControlsDisabled(true);

        var mutationPromise;
        if (serviceMethod === 'accept') {
            mutationPromise = service.acceptConsent();
        } else if (serviceMethod === 'revoke') {
            mutationPromise = service.revokeConsent();
        } else {
            trainingMutationBusy = false;
            setTrainingControlsDisabled(false);
            return Promise.resolve();
        }

        return mutationPromise
            .catch(function () {
                // Keep enrollment UI; refresh consent only.
            })
            .then(function () {
                return loadTrainingConsent({ controlsDisabled: false });
            })
            .then(function () {
                trainingMutationBusy = false;
                setTrainingControlsDisabled(false);
            })
            .catch(function () {
                trainingMutationBusy = false;
                setTrainingControlsDisabled(false);
            });
    }

    function handleTrainingCardClick(event) {
        var target = event.target;
        if (!target || !target.closest) {
            return;
        }
        var actionEl = target.closest('[data-aa-training-action]');
        if (!actionEl) {
            return;
        }

        var actionId = actionEl.getAttribute('data-aa-training-action');
        var ux = resolveTrainingUx();
        if (!ux || !actionId) {
            return;
        }

        if (actionId === 'open') {
            return;
        }

        event.preventDefault();

        if (actionEl.disabled || trainingMutationBusy) {
            return;
        }

        var mapped = ux.mapActionToService(actionId);

        if (mapped === 'retry') {
            loadTrainingStatus();
            return;
        }
        if (mapped === 'consent_retry') {
            loadTrainingConsent();
            return;
        }
        if (mapped === 'enroll') {
            runTrainingEnrollmentMutation('enroll');
            return;
        }
        if (mapped === 'unsubscribe') {
            var confirmFn = typeof window.confirm === 'function' ? window.confirm.bind(window) : null;
            var message = ux.UNSUBSCRIBE_CONFIRM_MESSAGE;
            if (confirmFn && !confirmFn(message)) {
                return;
            }
            runTrainingEnrollmentMutation('unsubscribe');
            return;
        }
        if (mapped === 'accept') {
            runTrainingConsentMutation('accept');
            return;
        }
        if (mapped === 'revoke') {
            runTrainingConsentMutation('revoke');
        }
    }

    function bindTrainingCardHandlers() {
        if (trainingCardBound) {
            return;
        }
        var root = getEl('aa-training-card-root');
        if (!root) {
            return;
        }
        root.addEventListener('click', handleTrainingCardClick);
        trainingCardBound = true;
    }

    function initTrainingCard() {
        var root = getEl('aa-training-card-root');
        if (!root) {
            return;
        }

        try {
            bindTrainingCardHandlers();
            loadTrainingStatus();
        } catch (err) {
            console.error('[AccountModule] Training card failed to init:', err);
        }
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', function () {
            init();
            initTrainingCard();
        });
    }

    var presentationApi = {
        resolveViewState: resolveViewState,
        resolveBillingAction: resolveBillingAction,
        isFreemiumActiveAccount: isFreemiumActiveAccount,
        buildAccountPresentation: buildAccountPresentation,
        renderBenefitQuotas: renderBenefitQuotas,
        shouldShowUpgradeCta: shouldShowUpgradeCta,
        isSafeStripeCheckoutUrl: isSafeStripeCheckoutUrl,
        createUpgradeCheckoutSession: createUpgradeCheckoutSession,
        initTrainingCard: initTrainingCard,
        loadTrainingStatus: loadTrainingStatus,
        runTrainingEnrollmentMutation: runTrainingEnrollmentMutation,
        runTrainingConsentMutation: runTrainingConsentMutation,
        handleTrainingCardClick: handleTrainingCardClick,
        VIEW: VIEW
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = presentationApi;
    }
})();
