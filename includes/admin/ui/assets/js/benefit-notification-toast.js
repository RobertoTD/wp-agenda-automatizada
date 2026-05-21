/**
 * Admin iframe — benefit notification toast renderer (UX-3B).
 *
 * Renders notification models from BenefitNotificationMapper (UX-3A).
 * No mapper calls, no AJAX, no business rules.
 */

window.AAAdmin = window.AAAdmin || {};

(function () {
    'use strict';

    var ROOT_ID = 'aa-benefit-toast-root';
    var MAX_VISIBLE = 3;
    var DEFAULT_DURATION_MS = {
        success: 3500,
        info: 4000,
        warning: 5000,
        error: 7000
    };
    var VALID_SEVERITIES = ['success', 'warning', 'error', 'info'];

    /** @type {Map<HTMLElement, number>} */
    var dismissTimers = new Map();

    function escapeHtml(str) {
        if (str == null) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function normalizeSeverity(severity) {
        var s = typeof severity === 'string' ? severity.toLowerCase() : 'info';
        return VALID_SEVERITIES.indexOf(s) >= 0 ? s : 'info';
    }

    function getDurationMs(notification) {
        var ms = notification && notification.durationMs;
        if (typeof ms === 'number' && ms > 0 && isFinite(ms)) {
            return ms;
        }
        var severity = normalizeSeverity(notification && notification.severity);
        return DEFAULT_DURATION_MS[severity];
    }

    function ensureRoot() {
        var existing = document.getElementById(ROOT_ID);
        if (existing) {
            return existing;
        }
        var root = document.createElement('div');
        root.id = ROOT_ID;
        root.className = 'aa-benefit-toast-root';
        root.setAttribute('aria-label', 'Notificaciones de beneficios');
        document.body.appendChild(root);
        return root;
    }

    function clearDismissTimer(el) {
        if (!el || !dismissTimers.has(el)) return;
        clearTimeout(dismissTimers.get(el));
        dismissTimers.delete(el);
    }

    function removeToast(el) {
        if (!el || !el.parentNode) return;
        clearDismissTimer(el);
        el.parentNode.removeChild(el);
    }

    function enforceMaxVisible(root) {
        var toasts = root.querySelectorAll('.aa-benefit-toast');
        while (toasts.length > MAX_VISIBLE) {
            removeToast(toasts[0]);
            toasts = root.querySelectorAll('.aa-benefit-toast');
        }
    }

    function scheduleDismiss(el, durationMs) {
        clearDismissTimer(el);
        var timerId = window.setTimeout(function () {
            removeToast(el);
        }, durationMs);
        dismissTimers.set(el, timerId);
    }

    /**
     * @param {object} notification
     * @returns {HTMLElement|null}
     */
    function buildToastElement(notification) {
        if (!notification || typeof notification !== 'object') {
            console.warn('[AAAdmin.toast] notification must be an object');
            return null;
        }

        var severity = normalizeSeverity(notification.severity);
        var isError = severity === 'error';
        var el = document.createElement('div');
        el.className = 'aa-benefit-toast aa-benefit-toast-' + severity;
        el.setAttribute('role', isError ? 'alert' : 'status');
        el.setAttribute('aria-live', isError ? 'assertive' : 'polite');
        el.setAttribute('aria-atomic', 'true');

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'aa-benefit-toast-close';
        closeBtn.setAttribute('aria-label', 'Cerrar notificación');
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', function () {
            removeToast(el);
        });

        var html = '';

        if (notification.title) {
            html += '<p class="aa-benefit-toast-title">' + escapeHtml(notification.title) + '</p>';
        }
        if (notification.message) {
            html += '<p class="aa-benefit-toast-message">' + escapeHtml(notification.message) + '</p>';
        }

        var details = notification.details;
        if (Array.isArray(details) && details.length > 0) {
            html += '<ul class="aa-benefit-toast-details">';
            for (var i = 0; i < details.length; i++) {
                if (details[i]) {
                    html += '<li>' + escapeHtml(details[i]) + '</li>';
                }
            }
            html += '</ul>';
        }

        if (notification.fallback) {
            html += '<p class="aa-benefit-toast-fallback">' + escapeHtml(notification.fallback) + '</p>';
        }

        el.innerHTML = html;
        el.appendChild(closeBtn);
        return el;
    }

    /**
     * @param {object} notification
     * @param {object} [options]
     * @returns {HTMLElement|null}
     */
    function show(notification, options) {
        options = options || {};
        var el = buildToastElement(notification);
        if (!el) return null;

        var root = ensureRoot();
        root.appendChild(el);
        enforceMaxVisible(root);

        if (options.autoDismiss !== false) {
            scheduleDismiss(el, getDurationMs(notification));
        }

        return el;
    }

    /**
     * @param {object[]} notifications
     * @param {object} [options]
     */
    function showMany(notifications, options) {
        if (!Array.isArray(notifications)) {
            console.warn('[AAAdmin.toast] showMany expects an array');
            return;
        }
        for (var i = 0; i < notifications.length; i++) {
            show(notifications[i], options);
        }
    }

    function clear() {
        var root = document.getElementById(ROOT_ID);
        if (!root) return;
        var toasts = root.querySelectorAll('.aa-benefit-toast');
        for (var i = 0; i < toasts.length; i++) {
            removeToast(toasts[i]);
        }
    }

    window.AAAdmin.toast = {
        show: show,
        showMany: showMany,
        clear: clear
    };

    window.BenefitNotificationToast = window.AAAdmin.toast;
})();
