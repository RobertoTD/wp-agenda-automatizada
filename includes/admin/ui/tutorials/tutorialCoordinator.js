/**
 * Tutorial Coordinator — orchestrates durable tutorial state + AATutorial (MC3D).
 *
 * Backend FSM is authoritative. No auto-start; manual init via TutorialCoordinator.init().
 */
(function () {
    'use strict';

    var DEFAULT_TUTORIAL_ID = 'create_test_appointment_v1';

    var actionsRegistered = false;
    var initPromise = null;
    var activeTutorialId = null;

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

            if (durableStep && definition.implementedStepIds.indexOf(durableStep) !== -1) {
                return durableStep;
            }

            warn('Paso durable no implementado en MC3D: ' + (durableStep || '(vacío)'));
            return null;
        }

        warn('Estado durable desconocido: ' + status);
        return null;
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

    function makePauseBoundaryAction(tutorialId) {
        return function (ctx) {
            return transitionPersist(tutorialId, 'paused', 'calendar_overview')
                .then(function () {
                    if (ctx && ctx.tutorial && typeof ctx.tutorial.pause === 'function') {
                        ctx.tutorial.pause('mc3d_boundary', { status: 'paused' });
                    }

                    return false;
                });
        };
    }

    function registerActions() {
        if (actionsRegistered) {
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
        safeRegister(names.pauseAtMc3dBoundary, makePauseBoundaryAction(tutorialId));

        actionsRegistered = true;
    }

    function destroyRuntime() {
        if (window.AATutorial && typeof window.AATutorial.destroy === 'function') {
            window.AATutorial.destroy();
        }

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
                        var status = resolveStatus(record);
                        var initialStepId = resolveInitialStepId(definition, record);

                        if (status === 'completed') {
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
