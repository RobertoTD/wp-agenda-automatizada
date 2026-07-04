'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const flowHelpers = require(path.join(
    __dirname,
    '../../assets/js/controllers/adminFastappointmentFlowController.js'
));

const TUTORIAL_CONTEXT = {
    tutorialId: 'create_test_appointment_v1',
    stepId: 'create_test_appointment',
    source: 'tutorial'
};

const READY_STATE = {
    selectedClientId: '42',
    selectedServiceId: '7',
    selectedDate: '2026-07-05',
    selectedTime: '09:30',
    selectedStaffId: '3',
    isSelectedStaffAvailable: true,
    selectedAreaId: '9',
    isSelectedAreaAvailable: true
};

describe('isFormReadyFromState B2d', () => {
    it('formulario completo está listo', () => {
        assert.equal(flowHelpers.isFormReadyFromState(READY_STATE), true);
    });

    it('formulario incompleto no está listo', () => {
        assert.equal(flowHelpers.isFormReadyFromState({
            selectedClientId: '42',
            selectedServiceId: '7'
        }), false);
    });
});

describe('canStartTutorialConfirmPrefill B2d', () => {
    it('tutorial con formulario listo y checkbox desmarcado puede arrancar', () => {
        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialConfirmPrefillDone: false,
            state: READY_STATE,
            hasConfirmCheckbox: true,
            confirmChecked: false
        }), true);
    });

    it('modal normal no arranca', () => {
        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: null,
            tutorialConfirmPrefillDone: false,
            state: READY_STATE,
            hasConfirmCheckbox: true,
            confirmChecked: false
        }), false);
    });

    it('no arranca si B2d ya terminó', () => {
        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialConfirmPrefillDone: true,
            state: READY_STATE,
            hasConfirmCheckbox: true,
            confirmChecked: false
        }), false);
    });

    it('no arranca si el checkbox ya está marcado', () => {
        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialConfirmPrefillDone: false,
            state: READY_STATE,
            hasConfirmCheckbox: true,
            confirmChecked: true
        }), false);
    });

    it('no arranca si falta zona disponible', () => {
        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialConfirmPrefillDone: false,
            state: Object.assign({}, READY_STATE, {
                selectedAreaId: null,
                isSelectedAreaAvailable: false
            }),
            hasConfirmCheckbox: true,
            confirmChecked: false
        }), false);
    });
});

describe('tryAutoCheckConfirmCheckbox B2d', () => {
    it('marca checkbox y dispara change como interacción manual', () => {
        var events = [];
        var checkbox = {
            checked: false,
            dispatchEvent: function(event) {
                events.push(event.type);
            }
        };

        assert.equal(flowHelpers.tryAutoCheckConfirmCheckbox(checkbox), true);
        assert.equal(checkbox.checked, true);
        assert.deepEqual(events, ['change']);
    });

    it('tutorial + formulario listo simulado marca una sola vez', () => {
        var events = [];
        var checkbox = {
            checked: false,
            dispatchEvent: function(event) {
                events.push(event.type);
            }
        };

        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialConfirmPrefillDone: false,
            state: READY_STATE,
            hasConfirmCheckbox: true,
            confirmChecked: false
        }), true);

        assert.equal(flowHelpers.tryAutoCheckConfirmCheckbox(checkbox), true);
        assert.equal(flowHelpers.tryAutoCheckConfirmCheckbox(checkbox), false);
        assert.deepEqual(events, ['change']);
    });

    it('checkbox ya marcado no se pisa', () => {
        var checkbox = {
            checked: true,
            dispatchEvent: function() {
                throw new Error('no debe disparar change');
            }
        };

        assert.equal(flowHelpers.tryAutoCheckConfirmCheckbox(checkbox), false);
    });

    it('usuario desmarca después: segunda pasada no vuelve a marcar si done=true', () => {
        var checkbox = {
            checked: false,
            dispatchEvent: function() {}
        };

        assert.equal(flowHelpers.tryAutoCheckConfirmCheckbox(checkbox), true);
        checkbox.checked = false;

        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialConfirmPrefillDone: true,
            state: READY_STATE,
            hasConfirmCheckbox: true,
            confirmChecked: false
        }), false);
    });

    it('modal normal: canStart false deja checkbox intacto', () => {
        var checkbox = {
            checked: false,
            dispatchEvent: function() {
                throw new Error('modal normal no debe marcar');
            }
        };

        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: null,
            tutorialConfirmPrefillDone: false,
            state: READY_STATE,
            hasConfirmCheckbox: true,
            confirmChecked: false
        }), false);
        assert.equal(checkbox.checked, false);
    });

    it('nueva apertura: done=false permite otra pasada', () => {
        var checkbox = {
            checked: false,
            dispatchEvent: function() {}
        };

        assert.equal(flowHelpers.canStartTutorialConfirmPrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialConfirmPrefillDone: false,
            state: READY_STATE,
            hasConfirmCheckbox: true,
            confirmChecked: false
        }), true);

        assert.equal(flowHelpers.tryAutoCheckConfirmCheckbox(checkbox), true);
    });
});
