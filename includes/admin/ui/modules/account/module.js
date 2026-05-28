/**
 * Account Module - subscription status UI
 *
 * Fetches aa_get_account_status via admin-ajax and renders a read-only summary.
 * Pure UI: no billing rules, no backend Node calls from the browser.
 */

(function () {
    'use strict';

    var BADGE_BASE = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border';

    var VIEW = {
        SYNC_PENDING: 'sync_pending',
        PAYMENT_PENDING: 'payment_pending',
        CANCEL_SCHEDULED: 'cancel_scheduled',
        ACTIVE: 'active',
        MISSING: 'missing',
        INACTIVE: 'inactive',
        FALLBACK: 'fallback'
    };

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

    function init() {
        var root = getEl('aa-account-status-root');
        if (!root) {
            return;
        }

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
