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
        if (status.billing_state === 'active' && status.effective_access_tier === 'pro') {
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
                return { mode: 'visible', label: BILLING_LABELS.PAYMENT_PENDING };
            case VIEW.CANCEL_SCHEDULED:
                return { mode: 'visible', label: BILLING_LABELS.CANCEL_SCHEDULED };
            case VIEW.ACTIVE:
                return { mode: 'visible', label: BILLING_LABELS.ACTIVE };
            default:
                return { mode: 'hidden' };
        }
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

    /**
     * @param {object} status
     */
    function renderAccountStatus(status) {
        var view = resolveViewState(status);
        var badgeEl = getEl('aa-account-status-badge');
        var planEl = getEl('aa-account-plan');
        var accessEl = getEl('aa-account-access');
        var noticeEl = getEl('aa-account-notice');
        var messagesEl = getEl('aa-account-messages');

        var planTier = humanizeTier(status.plan_tier);
        var accessTier = humanizeTier(status.effective_access_tier);
        var primaryNotice = '';
        var noticeClass = 'border-amber-200 bg-amber-50 text-amber-800';

        switch (view) {
            case VIEW.SYNC_PENDING:
                setBadge(badgeEl, 'Sincronizando', 'bg-gray-100 text-gray-700 border-gray-200');
                planEl.textContent = planTier;
                accessEl.textContent = accessTier;
                primaryNotice = 'Estamos sincronizando tu suscripción. Intenta de nuevo en unos minutos.';
                noticeClass = 'border-gray-200 bg-gray-50 text-gray-700';
                break;

            case VIEW.PAYMENT_PENDING:
                setBadge(badgeEl, 'Pago pendiente', 'bg-amber-50 text-amber-800 border-amber-200');
                planEl.textContent = planTier !== '—' ? planTier : 'Pro';
                accessEl.textContent = 'Freemium';
                primaryNotice = 'Tu último pago no pudo completarse. Actualiza tu método de pago para recuperar el acceso Pro.';
                break;

            case VIEW.CANCEL_SCHEDULED: {
                setBadge(badgeEl, 'Activa', 'bg-emerald-50 text-emerald-700 border-emerald-200');
                planEl.textContent = planTier !== '—' ? planTier : 'Pro';
                accessEl.textContent = 'Pro';
                var cancelDate = formatDate(status.cancel_at);
                if (cancelDate) {
                    primaryNotice = 'Has solicitado cancelar tu suscripción. Tus beneficios Pro seguirán activos hasta el '
                        + cancelDate
                        + '. Después de esa fecha tu agenda volverá al plan Freemium y no se realizarán nuevos cobros.';
                } else {
                    primaryNotice = 'Has solicitado cancelar tu suscripción. Tus beneficios Pro seguirán activos hasta la fecha programada. Después de esa fecha tu agenda volverá al plan Freemium y no se realizarán nuevos cobros.';
                }
                noticeClass = 'border-blue-200 bg-blue-50 text-blue-800';
                break;
            }

            case VIEW.ACTIVE:
                setBadge(badgeEl, 'Activa', 'bg-emerald-50 text-emerald-700 border-emerald-200');
                planEl.textContent = 'Pro';
                accessEl.textContent = 'Pro';
                break;

            case VIEW.MISSING:
                setBadge(badgeEl, 'Sin suscripción', 'bg-gray-100 text-gray-600 border-gray-200');
                planEl.textContent = '—';
                accessEl.textContent = 'Freemium';
                break;

            case VIEW.INACTIVE:
                setBadge(badgeEl, 'Inactiva', 'bg-gray-100 text-gray-700 border-gray-200');
                planEl.textContent = planTier !== '—' ? planTier : 'Pro';
                accessEl.textContent = accessTier !== '—' ? accessTier : 'Freemium';
                break;

            default:
                setBadge(badgeEl, 'Estado no disponible', 'bg-gray-100 text-gray-600 border-gray-200');
                planEl.textContent = planTier;
                accessEl.textContent = accessTier;
                break;
        }

        setNotice(noticeEl, primaryNotice, noticeClass);
        renderMessages(messagesEl, status.messages);
        renderBillingAction(status);
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

    function showError() {
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
                if (data && data.success && data.data && data.data.account_status) {
                    return data.data.account_status;
                }

                if (data && !data.success && data.data) {
                    console.warn('[AccountModule] Account status error:', data.data.code || '', data.data.message || '');
                }

                return null;
            })
            .catch(function (err) {
                console.error('[AccountModule] Fetch failed:', err);
                return null;
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

        fetchAccountStatus().then(function (status) {
            if (!status) {
                showError();
                return;
            }

            renderAccountStatus(status);
            showContent();
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
