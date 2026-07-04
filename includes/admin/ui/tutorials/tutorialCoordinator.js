/**
 * Tutorial Coordinator — orchestrates durable tutorial state + AATutorial (MC3D+).
 *
 * Backend FSM is authoritative. No auto-start; manual init via TutorialCoordinator.init().
 */
(function () {
    'use strict';

    var DEFAULT_TUTORIAL_ID = 'create_test_appointment_v1';
    var CALENDAR_OVERVIEW_STEP_ID = 'calendar_overview';
    var CREATE_TEST_APPOINTMENT_STEP_ID = 'create_test_appointment';
    var OPEN_FASTAPPOINTMENT_STEP_ID = 'open_fastappointment';

    var FAST_APPOINTMENT_TUTORIAL_CONTEXT = {
        tutorialId: DEFAULT_TUTORIAL_ID,
        stepId: CREATE_TEST_APPOINTMENT_STEP_ID,
        source: 'tutorial'
    };

    var actionsRegistered = false;
    var lifecycleListenersRegistered = false;
    var initPromise = null;
    var activeTutorialId = null;
    var tutorialCompletionInFlight = false;
    var tutorialCompletionDone = false;

    function normalizeString(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function warn(message) {
        if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('[TutorialCoordinator] ' + message);
        }
    }

    function errorLog(message, err) {
        if (window.console && typeof window.console.error === 'function') {
            window.console.error('[TutorialCoordinator] ' + message, err || '');
        }
    }

    function getBlogId() {
        if (window.AATutorialSession && typeof window.AATutorialSession.resolveBlogId === 'function') {
            return window.AATutorialSession.resolveBlogId();
        }

        var ctx = window.AA_ADMIN_CONTEXT;
        return ctx && ctx.blogId != null ? String(ctx.blogId) : '';
    }

    function getTutorialRecord(state, tutorialId) {
        if (!state || typeof state.tutorials !== 'object' || state.tutorials === null) {
            return null;
        }

        var record = state.tutorials[tutorialId];
        return record && typeof record === 'object' ? record : null;
    }

    function resolveStatus(record) {
        if (!record || !record.status) {
            return 'available';
        }

        return normalizeString(record.status) || 'available';
    }

    /**
     * Backend durable document uses snake_case current_step_id.
     *
     * @param {object|null} record
     * @returns {string|null}
     */
    function resolveDurableStepId(record) {
        if (!record) {
            return null;
        }

        var stepId = record.current_step_id;

        if (stepId === null || stepId === undefined || stepId === '') {
            return null;
        }

        return normalizeString(String(stepId)) || null;
    }

    /**
     * @param {object} definition
     * @param {object|null} record
     * @returns {string|null}
     */
    function resolveInitialStepId(definition, record) {
        var status = resolveStatus(record);

        if (status === 'completed') {
            return null;
        }

        if (status === 'available') {
            return definition.initialStepId;
        }

        if (status === 'in_progress' || status === 'paused') {
            var durableStep = resolveDurableStepId(record);

            if (durableStep === CREATE_TEST_APPOINTMENT_STEP_ID) {
                return null;
            }

            if (durableStep && definition.implementedStepIds.indexOf(durableStep) !== -1) {
                return durableStep;
            }

            warn('Paso durable no implementado: ' + (durableStep || '(vacío)'));
            return null;
        }

        warn('Estado durable desconocido: ' + status);
        return null;
    }

    function activateFastAppointmentTutorialContext() {
        if (!window.TutorialFastAppointmentContext
            || typeof window.TutorialFastAppointmentContext.activate !== 'function') {
            warn('TutorialFastAppointmentContext no disponible.');
            return false;
        }

        return window.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_TUTORIAL_CONTEXT);
    }

    function clearFastAppointmentTutorialContext() {
        if (window.TutorialFastAppointmentContext
            && typeof window.TutorialFastAppointmentContext.clear === 'function') {
            window.TutorialFastAppointmentContext.clear();
        }
    }

    function showTutorialCompletionCard() {
        if (!window.TutorialCompletionCard
            || typeof window.TutorialCompletionCard.show !== 'function') {
            warn('TutorialCompletionCard no disponible.');
            return;
        }

        window.TutorialCompletionCard.show();
    }

    function isTutorialOnOpenFastAppointmentStep() {
        if (!window.AATutorial || typeof window.AATutorial.getState !== 'function') {
            return false;
        }

        var state = window.AATutorial.getState();
        return !!(state
            && state.status === 'active'
            && state.currentStepId === OPEN_FASTAPPOINTMENT_STEP_ID);
    }

    function onTutorialStepShown(event) {
        var detail = event && event.detail;
        var stepId = detail ? normalizeString(detail.stepId) : '';

        if (stepId === OPEN_FASTAPPOINTMENT_STEP_ID) {
            activateFastAppointmentTutorialContext();
        }
    }

    function onFastAppointmentModalClosed() {
        if (isTutorialOnOpenFastAppointmentStep()) {
            activateFastAppointmentTutorialContext();
        }
    }

    function isActiveFastAppointmentTutorialContext() {
        if (!window.TutorialFastAppointmentContext
            || typeof window.TutorialFastAppointmentContext.isActive !== 'function'
            || !window.TutorialFastAppointmentContext.isActive()) {
            return false;
        }

        if (typeof window.TutorialFastAppointmentContext.get !== 'function') {
            return false;
        }

        var ctx = window.TutorialFastAppointmentContext.get();

        if (!ctx) {
            return false;
        }

        return ctx.tutorialId === FAST_APPOINTMENT_TUTORIAL_CONTEXT.tutorialId
            && ctx.stepId === FAST_APPOINTMENT_TUTORIAL_CONTEXT.stepId
            && ctx.source === FAST_APPOINTMENT_TUTORIAL_CONTEXT.source;
    }

    function onReservationCreatedForTutorial(event) {
        if (tutorialCompletionDone || tutorialCompletionInFlight) {
            return;
        }

        var detail = event && event.detail ? event.detail : {};

        if (detail.source !== 'fastappointment') {
            return;
        }

        if (!isActiveFastAppointmentTutorialContext()) {
            return;
        }

        tutorialCompletionInFlight = true;

        if (!window.TutorialStateService || typeof window.TutorialStateService.fetchState !== 'function') {
            warn('TutorialStateService no disponible para completar tutorial.');
            tutorialCompletionInFlight = false;
            return;
        }

        window.TutorialStateService.fetchState()
            .then(function (state) {
                var record = getTutorialRecord(state, DEFAULT_TUTORIAL_ID);
                var status = resolveStatus(record);
                var durableStep = resolveDurableStepId(record);

                if (status === 'completed') {
                    tutorialCompletionDone = true;
                    clearFastAppointmentTutorialContext();
                    return;
                }

                if (status !== 'in_progress' || durableStep !== CREATE_TEST_APPOINTMENT_STEP_ID) {
                    return;
                }

                return transitionPersist(DEFAULT_TUTORIAL_ID, 'completed', null)
                    .then(function () {
                        tutorialCompletionDone = true;
                        clearFastAppointmentTutorialContext();
                        showTutorialCompletionCard();
                    });
            })
            .catch(function (err) {
                warn('No se pudo completar tutorial tras reserva: ' + (err && err.message ? err.message : String(err)));
            })
            .finally(function () {
                tutorialCompletionInFlight = false;
            });
    }

    function registerLifecycleListeners() {
        if (lifecycleListenersRegistered) {
            return;
        }

        if (typeof document === 'undefined' || !document || typeof document.addEventListener !== 'function') {
            return;
        }

        document.addEventListener('aa:tutorial:step-shown', onTutorialStepShown);
        document.addEventListener('aa:fastappointment:modal-closed', onFastAppointmentModalClosed);
        document.addEventListener('aa:reservation:created', onReservationCreatedForTutorial);
        lifecycleListenersRegistered = true;
    }

    /**
     * Microciclo A: resume automático solo para paused/calendar_overview.
     *
     * @param {string} tutorialId
     * @param {object|null} record
     * @returns {Promise<object|null>}
     */
    function ensureCalendarOverviewResume(tutorialId, record) {
        if (!record) {
            return Promise.resolve(record);
        }

        if (resolveStatus(record) !== 'paused') {
            return Promise.resolve(record);
        }

        if (resolveDurableStepId(record) !== CALENDAR_OVERVIEW_STEP_ID) {
            return Promise.resolve(record);
        }

        return transitionPersist(tutorialId, 'in_progress', CALENDAR_OVERVIEW_STEP_ID)
            .then(function (nextState) {
                return getTutorialRecord(nextState, tutorialId) || record;
            });
    }

    function clearTransientSession(flowId) {
        var blogId = getBlogId();

        if (window.AATutorialSession && typeof window.AATutorialSession.clear === 'function') {
            window.AATutorialSession.clear(blogId, flowId);
        }
    }

    function transitionPersist(tutorialId, status, currentStepId) {
        return window.TutorialStateService.transition({
            tutorialId: tutorialId,
            status: status,
            currentStepId: currentStepId
        });
    }

    function makeAcceptAction(tutorialId) {
        return function () {
            return transitionPersist(tutorialId, 'in_progress', 'open_sidebar');
        };
    }

    function makePersistStepAction(tutorialId, stepId) {
        return function () {
            return transitionPersist(tutorialId, 'in_progress', stepId);
        };
    }

    function makePersistCreateTestAppointmentAction(tutorialId) {
        return function (ctx) {
            return transitionPersist(tutorialId, 'in_progress', CREATE_TEST_APPOINTMENT_STEP_ID)
                .then(function () {
                    if (ctx && ctx.tutorial && typeof ctx.tutorial.destroy === 'function') {
                        ctx.tutorial.destroy();
                    }

                    return false;
                });
        };
    }

    function registerActions() {
        if (actionsRegistered) {
            registerLifecycleListeners();
            return;
        }

        if (!window.TutorialDefinitions || !window.AATutorialActions) {
            warn('Dependencias no disponibles para registrar acciones.');
            return;
        }

        var definition = window.TutorialDefinitions.get(DEFAULT_TUTORIAL_ID);
        if (!definition) {
            warn('Definición no encontrada: ' + DEFAULT_TUTORIAL_ID);
            return;
        }

        var registry = window.AATutorialActions;
        var names = definition.actions;
        var tutorialId = definition.tutorialId;

        function safeRegister(name, handler) {
            if (typeof registry.has === 'function' && registry.has(name)) {
                return;
            }

            registry.register(name, handler);
        }

        safeRegister(names.accept, makeAcceptAction(tutorialId));
        safeRegister(names.persistOpenCalendar, makePersistStepAction(tutorialId, 'open_calendar'));
        safeRegister(names.persistCalendarOverview, makePersistStepAction(tutorialId, 'calendar_overview'));
        safeRegister(names.persistCreateTestAppointment, makePersistCreateTestAppointmentAction(tutorialId));

        actionsRegistered = true;
        registerLifecycleListeners();
    }

    function destroyRuntime() {
        if (window.AATutorial && typeof window.AATutorial.destroy === 'function') {
            window.AATutorial.destroy();
        }

        clearFastAppointmentTutorialContext();
        activeTutorialId = null;
    }

    /**
     * @param {string} tutorialId
     * @param {object} [options]
     * @returns {Promise<boolean>}
     */
    function start(tutorialId, options) {
        var id = normalizeString(tutorialId) || DEFAULT_TUTORIAL_ID;

        if (initPromise) {
            return initPromise;
        }

        initPromise = Promise.resolve()
            .then(function () {
                registerActions();

                if (!window.TutorialDefinitions || !window.TutorialStateService || !window.AATutorial) {
                    warn('Dependencias no disponibles.');
                    return false;
                }

                var definition = window.TutorialDefinitions.get(id);
                if (!definition) {
                    warn('Tutorial no encontrado: ' + id);
                    return false;
                }

                return window.TutorialStateService.fetchState()
                    .then(function (state) {
                        var record = getTutorialRecord(state, id);

                        return ensureCalendarOverviewResume(id, record)
                            .then(function (activeRecord) {
                                var status = resolveStatus(activeRecord);
                                var durableStep = resolveDurableStepId(activeRecord);
                                var initialStepId = resolveInitialStepId(definition, activeRecord);

                                if (status === 'completed') {
                                    return false;
                                }

                                if (status === 'in_progress'
                                    && durableStep === CREATE_TEST_APPOINTMENT_STEP_ID) {
                                    activateFastAppointmentTutorialContext();
                                    return false;
                                }

                                if (!initialStepId) {
                                    return false;
                                }

                                destroyRuntime();
                                clearTransientSession(definition.flowId);

                                var config = window.TutorialDefinitions.getConfig(id, {
                                    initialStepId: initialStepId
                                });

                                if (!config) {
                                    return false;
                                }

                                var started = window.AATutorial.start(config);

                                if (started) {
                                    activeTutorialId = id;
                                }

                                return !!started;
                            });
                    })
                    .catch(function (err) {
                        errorLog('No se pudo iniciar tutorial.', err);
                        return false;
                    });
            })
            .finally(function () {
                initPromise = null;
            });

        return initPromise;
    }

    /**
     * @param {object} [options]
     * @returns {Promise<boolean>}
     */
    function init(options) {
        return start(DEFAULT_TUTORIAL_ID, options);
    }

    window.TutorialCoordinator = {
        init: init,
        start: start,
        registerActions: registerActions
    };
})();
