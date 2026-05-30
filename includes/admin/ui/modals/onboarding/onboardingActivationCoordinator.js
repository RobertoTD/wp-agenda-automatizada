/**
 * Onboarding Activation Coordinator — MC5C1 auto-open, MC5C2 fast appointment guard.
 *
 * Coordinates welcome modal vs activation guide. Does not render or change business rules.
 */
(function () {
    'use strict';

    var WELCOME_SEEN_KEY = 'aa_onboarding_welcome_seen_v1';
    var WELCOME_SEEN_VALUE = '1';
    var SESSION_AUTO_SEEN_KEY = 'aa_onboarding_activation_guide_seen_session_v1';
    var SESSION_AUTO_SEEN_VALUE = '1';

    var WELCOME_CLOSE_DELAY_MS = 350;
    var WELCOME_SEEN_POLL_MS = 100;
    var WELCOME_SEEN_POLL_MAX_MS = 5000;
    var FAST_APPOINTMENT_GUARD_RETRY_MS = 50;
    var FAST_APPOINTMENT_GUARD_MAX_ATTEMPTS = 40;

    var welcomeCloseObserver = null;
    var welcomeCloseHandled = false;

    function hasSeenWelcome() {
        try {
            return localStorage.getItem(WELCOME_SEEN_KEY) === WELCOME_SEEN_VALUE;
        } catch (err) {
            console.warn('[OnboardingActivationCoordinator] localStorage read failed:', err);
            return false;
        }
    }

    function hasSeenAutoGuideInSession() {
        try {
            return sessionStorage.getItem(SESSION_AUTO_SEEN_KEY) === SESSION_AUTO_SEEN_VALUE;
        } catch (err) {
            console.warn('[OnboardingActivationCoordinator] sessionStorage read failed:', err);
            return false;
        }
    }

    function markAutoGuideSeenInSession() {
        try {
            sessionStorage.setItem(SESSION_AUTO_SEEN_KEY, SESSION_AUTO_SEEN_VALUE);
        } catch (err) {
            console.warn('[OnboardingActivationCoordinator] sessionStorage write failed:', err);
        }
    }

    function isModalOpen() {
        var root = document.getElementById('aa-modal-root');
        return !!(root && !root.classList.contains('hidden'));
    }

    /**
     * @param {object|null|undefined} status
     * @returns {boolean}
     */
    function shouldAutoOpenFromStatus(status) {
        if (!status || typeof status !== 'object') {
            return false;
        }

        if (status.activation_complete === true) {
            return false;
        }

        return status.show_activation_guide === true;
    }

    function disconnectWelcomeCloseObserver() {
        if (welcomeCloseObserver) {
            welcomeCloseObserver.disconnect();
            welcomeCloseObserver = null;
        }
    }

    /**
     * After welcome modal closes, wait until localStorage reflects welcome seen.
     *
     * @param {() => void} callback
     */
    function whenWelcomeSeenThen(callback) {
        var startedAt = Date.now();

        function tick() {
            if (hasSeenWelcome()) {
                callback();
                return;
            }

            if (Date.now() - startedAt >= WELCOME_SEEN_POLL_MAX_MS) {
                console.warn('[OnboardingActivationCoordinator] welcome seen key not set after modal close');
                return;
            }

            window.setTimeout(tick, WELCOME_SEEN_POLL_MS);
        }

        tick();
    }

    function scheduleAutoOpenAfterWelcomeClose() {
        if (welcomeCloseHandled) {
            return;
        }

        welcomeCloseHandled = true;
        disconnectWelcomeCloseObserver();

        window.setTimeout(function () {
            whenWelcomeSeenThen(function () {
                tryAutoOpen();
            });
        }, WELCOME_CLOSE_DELAY_MS);
    }

    function watchWelcomeModalClose() {
        var root = document.getElementById('aa-modal-root');

        if (!root) {
            return;
        }

        disconnectWelcomeCloseObserver();

        welcomeCloseObserver = new MutationObserver(function () {
            if (root.classList.contains('hidden')) {
                scheduleAutoOpenAfterWelcomeClose();
            }
        });

        welcomeCloseObserver.observe(root, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    /**
     * Opens activation guide once per session when backend status allows it.
     */
    function tryAutoOpen() {
        if (hasSeenAutoGuideInSession()) {
            return Promise.resolve();
        }

        if (!window.OnboardingStatusService || typeof window.OnboardingStatusService.fetchStatus !== 'function') {
            console.warn('[OnboardingActivationCoordinator] OnboardingStatusService.fetchStatus no disponible');
            return Promise.resolve();
        }

        if (!window.OnboardingActivationGuide || typeof window.OnboardingActivationGuide.open !== 'function') {
            console.warn('[OnboardingActivationCoordinator] OnboardingActivationGuide.open no disponible');
            return Promise.resolve();
        }

        if (isModalOpen()) {
            return Promise.resolve();
        }

        return window.OnboardingStatusService.fetchStatus()
            .then(function (status) {
                if (!shouldAutoOpenFromStatus(status)) {
                    return status;
                }

                if (isModalOpen()) {
                    return status;
                }

                return window.OnboardingActivationGuide.open().then(function (openedStatus) {
                    if (shouldAutoOpenFromStatus(openedStatus) && isModalOpen()) {
                        markAutoGuideSeenInSession();
                    }
                    return openedStatus;
                });
            })
            .catch(function (err) {
                console.warn('[OnboardingActivationCoordinator] fetchStatus failed:', err);
            });
    }

    function callOriginalFastAppointmentOpen(originalOpen, args) {
        return originalOpen.apply(window.FastAppointmentModal, args);
    }

    /**
     * MC5C2: redirect fast appointment to activation guide when setup is incomplete.
     *
     * @returns {boolean} true when guard installed
     */
    function installFastAppointmentGuard() {
        var modal = window.FastAppointmentModal;

        if (!modal || typeof modal.open !== 'function') {
            return false;
        }

        if (modal.open.__aaOnboardingGuarded === true) {
            return true;
        }

        var originalOpen = modal.open;

        modal.open = function guardedFastAppointmentOpen() {
            var callArgs = arguments;

            if (!window.OnboardingStatusService || typeof window.OnboardingStatusService.fetchStatus !== 'function') {
                return callOriginalFastAppointmentOpen(originalOpen, callArgs);
            }

            return window.OnboardingStatusService.fetchStatus()
                .then(function (status) {
                    if (status && status.setup_complete === false) {
                        if (window.OnboardingActivationGuide && typeof window.OnboardingActivationGuide.open === 'function') {
                            return window.OnboardingActivationGuide.open();
                        }

                        console.warn('[OnboardingActivationCoordinator] OnboardingActivationGuide.open no disponible; abriendo cita rápida');
                        return callOriginalFastAppointmentOpen(originalOpen, callArgs);
                    }

                    return callOriginalFastAppointmentOpen(originalOpen, callArgs);
                })
                .catch(function (err) {
                    console.warn('[OnboardingActivationCoordinator] fast appointment guard fetch failed, allowing fast appointment:', err);
                    return callOriginalFastAppointmentOpen(originalOpen, callArgs);
                });
        };

        modal.open.__aaOnboardingGuarded = true;
        return true;
    }

    function tryInstallFastAppointmentGuard(attemptsLeft) {
        if (installFastAppointmentGuard()) {
            return;
        }

        if (attemptsLeft <= 0) {
            console.warn('[OnboardingActivationCoordinator] FastAppointmentModal.open no disponible; guard no instalado');
            return;
        }

        window.setTimeout(function () {
            tryInstallFastAppointmentGuard(attemptsLeft - 1);
        }, FAST_APPOINTMENT_GUARD_RETRY_MS);
    }

    function init() {
        tryInstallFastAppointmentGuard(FAST_APPOINTMENT_GUARD_MAX_ATTEMPTS);

        if (hasSeenAutoGuideInSession()) {
            return;
        }

        if (hasSeenWelcome()) {
            window.setTimeout(function () {
                tryAutoOpen();
            }, 0);
            return;
        }

        watchWelcomeModalClose();
    }

    window.OnboardingActivationCoordinator = {
        tryAutoOpen: tryAutoOpen,
        installFastAppointmentGuard: installFastAppointmentGuard,
        hasSeenAutoGuideInSession: hasSeenAutoGuideInSession,
        resetAutoGuideSessionSeen: function () {
            try {
                sessionStorage.removeItem(SESSION_AUTO_SEEN_KEY);
            } catch (err) {
                console.warn('[OnboardingActivationCoordinator] sessionStorage remove failed:', err);
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        init();
    });
})();
