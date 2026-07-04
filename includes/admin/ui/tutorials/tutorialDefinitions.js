/**
 * Tutorial Definitions — declarative step configs for AATutorial (MC3D).
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
                'open_sidebar',
                'open_calendar',
                'calendar_overview'
            ],
            durableStepIds: [
                'open_sidebar',
                'open_calendar',
                'calendar_overview'
            ],
            initialStepId: 'intro',
            terminalImplementedStepId: 'calendar_overview',
            actions: {
                accept: 'aa_tutorial_accept_create_test_appointment',
                persistOpenCalendar: 'aa_tutorial_persist_open_calendar',
                persistCalendarOverview: 'aa_tutorial_persist_calendar_overview',
                pauseAtMc3dBoundary: 'aa_tutorial_pause_mc3d_boundary'
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
                    nextStepId: 'open_sidebar'
                },
                {
                    id: 'open_sidebar',
                    title: 'Abre el menú',
                    text: 'Haz clic en el botón de menú para ir a la Agenda.',
                    target: '#aa-btn-sidebar',
                    placement: 'bottom',
                    beforeAdvanceAction: 'aa_tutorial_persist_open_calendar',
                    advance: {
                        mode: 'target_click',
                        navigation: 'none'
                    },
                    nextStepId: 'open_calendar'
                },
                {
                    id: 'open_calendar',
                    title: 'Entra a la Agenda',
                    text: 'Selecciona Agenda en el menú.',
                    target: '[data-aa-nav-module="calendar"]',
                    placement: 'right',
                    waitFor: {
                        selector: '[data-aa-nav-module="calendar"]',
                        timeoutMs: 3000,
                        intervalMs: 100
                    },
                    beforeAdvanceAction: 'aa_tutorial_persist_calendar_overview',
                    advance: {
                        mode: 'target_click',
                        navigation: 'follow_target'
                    },
                    nextStepId: 'calendar_overview'
                },
                {
                    id: 'calendar_overview',
                    title: 'Esta es tu Agenda',
                    text: 'Aquí verás tus citas y, en el siguiente ciclo, crearemos una cita de prueba.',
                    placement: 'center',
                    beforeAdvanceAction: 'aa_tutorial_pause_mc3d_boundary',
                    advance: {
                        mode: 'button',
                        label: 'Continuar después'
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
                beforeAdvanceAction: step.beforeAdvanceAction,
                advance: Object.assign({}, step.advance)
            };

            if (step.target) {
                clone.target = step.target;
            }

            if (step.nextStepId) {
                clone.nextStepId = step.nextStepId;
            }

            if (step.waitFor) {
                clone.waitFor = Object.assign({}, step.waitFor);
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
            steps: cloneSteps(definition.steps)
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
