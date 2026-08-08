/**
 * Tutorial Definitions — declarative step configs for AATutorial (MC3D+).
 *
 * No orchestration, no AJAX, no auto-start. Consumed by TutorialCoordinator.
 */
(function () {
    'use strict';

    var TUTORIAL_ID = 'create_test_appointment_v1';

    var DEFINITIONS = {
        create_test_appointment_v1: {
            tutorialId: TUTORIAL_ID,
            flowId: TUTORIAL_ID,
            implementedStepIds: [
                'intro',
                'calendar_overview'
            ],
            durableStepIds: [
                'calendar_overview',
                'create_test_appointment'
            ],
            initialStepId: 'intro',
            terminalImplementedStepId: 'calendar_overview',
            actions: {
                accept: 'aa_tutorial_accept_create_test_appointment',
                skip: 'aa_tutorial_skip_create_test_appointment',
                persistCreateTestAppointment: 'aa_tutorial_persist_create_test_appointment',
                dismissVisualOnly: 'aa_tutorial_dismiss_visual_only',
                ensureSidebarInteractable: 'aa_tutorial_ensure_sidebar_interactable'
            },
            steps: [
                {
                    id: 'intro',
                    title: 'Crea tu primera cita de prueba',
                    text: 'Te guiaremos por la agenda para validar el flujo básico.',
                    placement: 'center',
                    beforeAdvanceAction: 'aa_tutorial_accept_create_test_appointment',
                    advance: {
                        mode: 'button',
                        label: 'Comenzar tutorial'
                    },
                    secondaryAction: {
                        label: 'Omitir tutorial',
                        action: 'aa_tutorial_skip_create_test_appointment'
                    },
                    nextStepId: 'calendar_overview'
                },
                {
                    id: 'calendar_overview',
                    title: 'Esta es tu Agenda',
                    text: 'Pulsa «+ Crear cita» para crear una cita de prueba.',
                    target: '#aa-btn-open-fastappointment-modal',
                    placement: 'top',
                    waitFor: {
                        selector: '#aa-btn-open-fastappointment-modal',
                        timeoutMs: 3000,
                        intervalMs: 100
                    },
                    beforeAdvanceAction: 'aa_tutorial_persist_create_test_appointment',
                    advance: {
                        mode: 'target_click',
                        navigation: 'none'
                    }
                },
                {
                    id: 'resume_open_sidebar',
                    title: 'Abre el menú',
                    text: 'Haz clic en el botón de menú para ver la Agenda.',
                    target: '#aa-btn-sidebar',
                    placement: 'bottom',
                    advance: {
                        mode: 'target_click',
                        navigation: 'none'
                    },
                    nextStepId: 'resume_navigate_calendar'
                },
                {
                    id: 'resume_navigate_calendar',
                    title: 'Entra a la Agenda',
                    text: 'Selecciona Agenda en el menú.',
                    target: '[data-aa-nav-module="calendar"]',
                    placement: 'bottom',
                    beforeAction: 'aa_tutorial_ensure_sidebar_interactable',
                    waitFor: {
                        selector: '[data-aa-nav-module="calendar"]',
                        timeoutMs: 3000,
                        intervalMs: 100
                    },
                    advance: {
                        mode: 'target_click',
                        navigation: 'follow_target'
                    }
                },
                {
                    id: 'resume_create_test_appointment_fab',
                    title: 'Crea tu cita de prueba',
                    text: 'Pulsa «+ Crear cita» para continuar con tu cita de prueba.',
                    target: '#aa-btn-open-fastappointment-modal',
                    placement: 'top',
                    waitFor: {
                        selector: '#aa-btn-open-fastappointment-modal',
                        timeoutMs: 3000,
                        intervalMs: 100
                    },
                    beforeAdvanceAction: 'aa_tutorial_dismiss_visual_only',
                    advance: {
                        mode: 'target_click',
                        navigation: 'none'
                    }
                }
            ]
        }
    };

    function normalizeString(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function cloneSteps(steps) {
        return steps.map(function (step) {
            var clone = {
                id: step.id,
                title: step.title,
                text: step.text,
                placement: step.placement,
                advance: Object.assign({}, step.advance)
            };

            if (step.beforeAdvanceAction) {
                clone.beforeAdvanceAction = step.beforeAdvanceAction;
            }

            if (step.target) {
                clone.target = step.target;
            }

            if (step.nextStepId) {
                clone.nextStepId = step.nextStepId;
            }

            if (step.waitFor) {
                clone.waitFor = Object.assign({}, step.waitFor);
            }

            if (step.beforeAction) {
                clone.beforeAction = step.beforeAction;
            }

            if (step.secondaryAction) {
                clone.secondaryAction = Object.assign({}, step.secondaryAction);
            }

            return clone;
        });
    }

    function get(tutorialId) {
        var id = normalizeString(tutorialId);
        return DEFINITIONS[id] || null;
    }

    /**
     * @param {string} tutorialId
     * @param {{initialStepId?:string}} [options]
     * @returns {object|null}
     */
    function getConfig(tutorialId, options) {
        var definition = get(tutorialId);
        if (!definition) {
            return null;
        }

        var opts = options || {};
        var initialStepId = normalizeString(opts.initialStepId) || definition.initialStepId;

        return {
            flowId: definition.flowId,
            initialStepId: initialStepId,
            steps: cloneSteps(definition.steps),
            onGlobalClose: typeof opts.onGlobalClose === 'function' ? opts.onGlobalClose : null
        };
    }

    function list() {
        return Object.keys(DEFINITIONS);
    }

    window.TutorialDefinitions = {
        TUTORIAL_ID: TUTORIAL_ID,
        get: get,
        getConfig: getConfig,
        list: list
    };
})();
