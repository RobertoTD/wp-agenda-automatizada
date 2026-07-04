/**
 * Admin Fast Appointment Controller
 *
 * Inicializa el flujo del modal Fast Appointment y delega la lógica de pasos
 * al flow controller correspondiente.
 *
 * @package AgendaAutomatizada
 * @since 2.0.0
 */
(function() {
    'use strict';

    function createController(opts) {
        const initOpts = opts || {};
        const state = {
            selectedClientId: null,
            selectedClient: null,
            isClientStepReady: false
        };

        let flowController = null;

        function getState() {
            return state;
        }

        function setState(nextState) {
            Object.assign(state, nextState || {});
        }

        if (window.AdminFastappointmentFlowController && typeof window.AdminFastappointmentFlowController.init === 'function') {
            flowController = window.AdminFastappointmentFlowController.init({
                getState: getState,
                setState: setState,
                tutorialContext: initOpts.tutorialContext || null,
                selectors: {
                    stepClientSelector: '#aa-fastappointment-step-client',
                    searchInputId: 'aa-fastappointment-client-search',
                    createButtonId: 'aa-fastappointment-client-create',
                    inlineContainerId: 'aa-fastappointment-client-inline',
                    clientSelectId: 'aa-fastappointment-client'
                }
            });
        } else {
            console.warn('[AdminFastappointmentController] Flow controller no disponible');
        }

        console.log('[AdminFastappointmentController] Inicializado');

        return {
            getState: function() {
                return Object.assign({}, state);
            },
            destroy: function() {
                if (flowController && typeof flowController.destroy === 'function') {
                    flowController.destroy();
                    flowController = null;
                }

                console.log('[AdminFastappointmentController] Destruido');
            }
        };
    }

    window.AdminFastappointmentController = {
        init: createController
    };
})();
