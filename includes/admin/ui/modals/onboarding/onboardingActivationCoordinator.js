/**
 * Onboarding Activation Coordinator — MC5C1 auto-open, MC5C3B re-open on setup progress.
 *
 * Coordinates welcome modal vs activation guide. Does not render or change business rules.
 * MC1: fast appointment open is never intercepted; prerequisites are handled by the modal flow.
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
    var SETUP_MUTATION_DEBOUNCE_MS = 300;
    var GUIDE_REOPEN_AFTER_MODAL_CLOSE_MS = 350;

    var welcomeCloseObserver = null;
    var welcomeCloseHandled = false;

    /** @type {object|null|undefined} */
    var lastStatus = null;
    var setupMutationDebounceTimer = null;
    var setupMutationRefreshInFlight = false;
    var setupMutationRefreshQueued = false;
    var lastOpenedTransitionKey = null;
    var guideReopenObserver = null;

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

    function fetchOnboardingStatus() {
        if (!window.OnboardingStatusService || typeof window.OnboardingStatusService.fetchStatus !== 'function') {
            return Promise.reject(new Error('OnboardingStatusService.fetchStatus no disponible'));
        }

        return window.OnboardingStatusService.fetchStatus();
    }

    /**
     * @param {string|null|undefined} step
     * @returns {string}
     */
    function normalizeNextStep(step) {
        if (step === null || step === undefined) {
            return '';
        }

        return String(step);
    }

    /**
     * @param {object} oldStatus
     * @param {object} newStatus
     * @returns {string}
     */
    function buildTransitionKey(oldStatus, newStatus) {
        return normalizeNextStep(oldStatus.next_step) + '>' + normalizeNextStep(newStatus.next_step) +
            '|' + String(!!newStatus.setup_complete) + '|' + String(!!newStatus.activation_complete);
    }

    /**
     * @param {object|null|undefined} oldStatus
     * @param {object|null|undefined} newStatus
     * @returns {boolean}
     */
    function hasSignificantTransition(oldStatus, newStatus) {
        if (!oldStatus || !newStatus || typeof oldStatus !== 'object' || typeof newStatus !== 'object') {
            return false;
        }

        return normalizeNextStep(oldStatus.next_step) !== normalizeNextStep(newStatus.next_step) ||
            oldStatus.setup_complete !== newStatus.setup_complete ||
            oldStatus.activation_complete !== newStatus.activation_complete;
    }

    /**
     * @param {object|null|undefined} status
     * @returns {boolean}
     */
    function shouldReopenGuideAfterTransition(status) {
        if (!status || typeof status !== 'object') {
            return false;
        }

        if (status.activation_complete === true) {
            return false;
        }

        return status.show_activation_guide === true;
    }

    function primeLastStatus() {
        return fetchOnboardingStatus()
            .then(function (status) {
                lastStatus = status;
                return status;
            })
            .catch(function (err) {
                console.warn('[OnboardingActivationCoordinator] primeLastStatus failed:', err);
            });
    }

    function disconnectGuideReopenObserver() {
        if (guideReopenObserver) {
            guideReopenObserver.disconnect();
            guideReopenObserver = null;
        }
    }

    /**
     * @param {() => void} callback
     */
    function whenModalClosedThen(callback) {
        var root = document.getElementById('aa-modal-root');

        if (!root || !isModalOpen()) {
            window.setTimeout(callback, GUIDE_REOPEN_AFTER_MODAL_CLOSE_MS);
            return;
        }

        disconnectGuideReopenObserver();

        guideReopenObserver = new MutationObserver(function () {
            if (root.classList.contains('hidden')) {
                disconnectGuideReopenObserver();
                window.setTimeout(callback, GUIDE_REOPEN_AFTER_MODAL_CLOSE_MS);
            }
        });

        guideReopenObserver.observe(root, {
            attributes: true,
            attributeFilter: ['class']
        });
    }

    /**
     * @param {string} transitionKey
     * @returns {Promise<*>}
     */
    function tryReopenActivationGuide(transitionKey) {
        if (!window.OnboardingActivationGuide || typeof window.OnboardingActivationGuide.open !== 'function') {
            console.warn('[OnboardingActivationCoordinator] OnboardingActivationGuide.open no disponible');
            return Promise.resolve();
        }

        if (!shouldReopenGuideAfterTransition(lastStatus)) {
            return Promise.resolve();
        }

        function openGuideNow() {
            if (!shouldReopenGuideAfterTransition(lastStatus)) {
                return Promise.resolve();
            }

            if (isModalOpen()) {
                return new Promise(function (resolve) {
                    whenModalClosedThen(function () {
                        resolve(openGuideNow());
                    });
                });
            }

            return window.OnboardingActivationGuide.open().then(function () {
                lastOpenedTransitionKey = transitionKey;
            });
        }

        return openGuideNow();
    }

    function processSetupMutationRefresh() {
        if (!window.OnboardingStatusService || typeof window.OnboardingStatusService.fetchStatus !== 'function') {
            return Promise.resolve();
        }

        if (!lastStatus) {
            return fetchOnboardingStatus()
                .then(function (status) {
                    lastStatus = status;
                })
                .catch(function (err) {
                    console.warn('[OnboardingActivationCoordinator] setup mutation baseline fetch failed:', err);
                });
        }

        var oldStatus = lastStatus;

        return fetchOnboardingStatus()
            .then(function (newStatus) {
                lastStatus = newStatus;

                if (!hasSignificantTransition(oldStatus, newStatus)) {
                    return;
                }

                if (!shouldReopenGuideAfterTransition(newStatus)) {
                    return;
                }

                var transitionKey = buildTransitionKey(oldStatus, newStatus);

                if (transitionKey === lastOpenedTransitionKey) {
                    return;
                }

                return tryReopenActivationGuide(transitionKey);
            })
            .catch(function (err) {
                console.warn('[OnboardingActivationCoordinator] setup mutation refresh failed:', err);
            });
    }

    function runSetupMutationRefresh() {
        if (setupMutationRefreshInFlight) {
            setupMutationRefreshQueued = true;
            return;
        }

        setupMutationRefreshInFlight = true;

        processSetupMutationRefresh()
            .finally(function () {
                setupMutationRefreshInFlight = false;

                if (setupMutationRefreshQueued) {
                    setupMutationRefreshQueued = false;
                    runSetupMutationRefresh();
                }
            });
    }

    function scheduleSetupMutationRefresh() {
        if (setupMutationDebounceTimer) {
            window.clearTimeout(setupMutationDebounceTimer);
        }

        setupMutationDebounceTimer = window.setTimeout(function () {
            setupMutationDebounceTimer = null;
            runSetupMutationRefresh();
        }, SETUP_MUTATION_DEBOUNCE_MS);
    }

    /**
     * @param {Event} event
     * @returns {string|null}
     */
    function resolveSetupMutationSource(event) {
        if (!event || !event.type) {
            return null;
        }

        if (event.type === 'aa:client:saved') {
            if (event.detail && event.detail.isEdit === true) {
                return null;
            }

            return 'client';
        }

        if (event.type === 'aa:onboarding:setup-mutated') {
            return event.detail && event.detail.source ? event.detail.source : null;
        }

        if (event.type === 'aa:reservation:created') {
            return 'reservation';
        }

        if (event.type === 'aa:notifications:refresh') {
            if (event.detail && event.detail.source === 'fastappointment-created') {
                return 'fastappointment';
            }

            return null;
        }

        return null;
    }

    function onSetupMutationEvent(event) {
        if (!resolveSetupMutationSource(event)) {
            return;
        }

        scheduleSetupMutationRefresh();
    }

    function installSetupMutationListeners() {
        document.addEventListener('aa:client:saved', onSetupMutationEvent);
        document.addEventListener('aa:onboarding:setup-mutated', onSetupMutationEvent);
        document.addEventListener('aa:reservation:created', onSetupMutationEvent);
        document.addEventListener('aa:notifications:refresh', onSetupMutationEvent);
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

        return fetchOnboardingStatus()
            .then(function (status) {
                lastStatus = status;

                if (!shouldAutoOpenFromStatus(status)) {
                    return status;
                }

                if (isModalOpen()) {
                    return status;
                }

                return window.OnboardingActivationGuide.open().then(function (openedStatus) {
                    if (openedStatus && typeof openedStatus === 'object') {
                        lastStatus = openedStatus;
                    }

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

    function initInitialAutoOpen() {
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

    function init() {
        installSetupMutationListeners();

        primeLastStatus().finally(function () {
            initInitialAutoOpen();
        });
    }

    if (typeof window !== 'undefined') {
        window.OnboardingActivationCoordinator = {
            tryAutoOpen: tryAutoOpen,
            getLastStatus: function () {
                return lastStatus;
            },
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
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            isFastAppointmentOpenIntercepted: function () {
                return false;
            }
        };
    }
})();
