/**
 * Onboarding Welcome Modal — first-open orientation (MC4).
 *
 * Uses AAAdmin.openModal and localStorage; no activation guide or backend status.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'aa_onboarding_welcome_seen_v1';
    var STORAGE_VALUE = '1';

    var config = {
        title: 'Bienvenido',
        bodyTemplateId: 'aa-onboarding-welcome-body-template',
        footerTemplateId: 'aa-onboarding-welcome-footer-template'
    };

    var welcomeOpen = false;
    var closeObserver = null;

    function hasSeenWelcome() {
        try {
            return localStorage.getItem(STORAGE_KEY) === STORAGE_VALUE;
        } catch (err) {
            console.warn('[OnboardingWelcomeModal] localStorage read failed:', err);
            return false;
        }
    }

    function markWelcomeSeen() {
        try {
            localStorage.setItem(STORAGE_KEY, STORAGE_VALUE);
        } catch (err) {
            console.warn('[OnboardingWelcomeModal] localStorage write failed:', err);
        }
    }

    function resetWelcomeSeen() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (err) {
            console.warn('[OnboardingWelcomeModal] localStorage remove failed:', err);
        }
    }

    function disconnectCloseObserver() {
        if (closeObserver) {
            closeObserver.disconnect();
            closeObserver = null;
        }
    }

    function getTemplateHtml(templateId) {
        var template = document.getElementById(templateId);

        if (!template || !template.content) {
            console.error('[OnboardingWelcomeModal] Template no encontrado:', templateId);
            return '';
        }

        var clone = template.content.cloneNode(true);
        var container = document.createElement('div');
        container.appendChild(clone);
        return container.innerHTML;
    }

    function watchModalClose() {
        disconnectCloseObserver();

        var root = document.getElementById('aa-modal-root');
        if (!root) {
            return;
        }

        closeObserver = new MutationObserver(function () {
            if (!welcomeOpen) {
                return;
            }

            if (root.classList.contains('hidden')) {
                welcomeOpen = false;
                markWelcomeSeen();
                disconnectCloseObserver();
            }
        });

        closeObserver.observe(root, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    function bindDismissButton() {
        var button = document.getElementById('aa-onboarding-welcome-dismiss');

        if (!button || button.dataset.onboardingWelcomeBound === '1') {
            return;
        }

        button.dataset.onboardingWelcomeBound = '1';
        button.addEventListener('click', function () {
            markWelcomeSeen();
            welcomeOpen = false;
            disconnectCloseObserver();

            if (window.AAAdmin && typeof window.AAAdmin.closeModal === 'function') {
                window.AAAdmin.closeModal();
            }
        });
    }

    function open() {
        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('[OnboardingWelcomeModal] AAAdmin.openModal no disponible');
            return;
        }

        var body = getTemplateHtml(config.bodyTemplateId);
        var footer = getTemplateHtml(config.footerTemplateId);

        if (!body) {
            return;
        }

        welcomeOpen = true;

        window.AAAdmin.openModal({
            title: config.title,
            body: body,
            footer: footer
        });

        bindDismissButton();
        watchModalClose();
    }

    function tryAutoShow() {
        if (hasSeenWelcome()) {
            return;
        }

        open();
    }

    function init() {
        if (hasSeenWelcome()) {
            return;
        }

        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.warn('[OnboardingWelcomeModal] AAAdmin.openModal no disponible');
            return;
        }

        if (!document.getElementById(config.bodyTemplateId)) {
            console.warn('[OnboardingWelcomeModal] Templates no disponibles');
            return;
        }

        setTimeout(function () {
            tryAutoShow();
        }, 0);
    }

    window.OnboardingWelcomeModal = {
        hasSeenWelcome: hasSeenWelcome,
        markWelcomeSeen: markWelcomeSeen,
        resetWelcomeSeen: resetWelcomeSeen,
        open: open,
        tryAutoShow: tryAutoShow,
        init: init
    };

    document.addEventListener('DOMContentLoaded', function () {
        OnboardingWelcomeModal.init();
    });
})();
