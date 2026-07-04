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
    selectedDate: '2026-07-05',
    selectedTime: null
};

describe('resolveTutorialTimeAutoSelectValue B2c', () => {
    it('0 slots devuelve null', () => {
        assert.equal(flowHelpers.resolveTutorialTimeAutoSelectValue([]), null);
        assert.equal(flowHelpers.resolveTutorialTimeAutoSelectValue(null), null);
    });

    it('1 slot selecciona slots[0].value', () => {
        assert.equal(
            flowHelpers.resolveTutorialTimeAutoSelectValue([{ value: '10:00', label: '10:00' }]),
            '10:00'
        );
    });

    it('2 slots selecciona literalmente slots[1].value', () => {
        assert.equal(
            flowHelpers.resolveTutorialTimeAutoSelectValue([
                { value: '09:00', label: '09:00' },
                { value: '09:30', label: '09:30' }
            ]),
            '09:30'
        );
    });

    it('3 o más slots sigue usando slots[1]', () => {
        assert.equal(
            flowHelpers.resolveTutorialTimeAutoSelectValue([
                { value: '08:00', label: '08:00' },
                { value: '08:30', label: '08:30' },
                { value: '09:00', label: '09:00' }
            ]),
            '08:30'
        );
    });

    it('no reordena ni recalcula slots', () => {
        var slots = [
            { value: '11:00', label: '11:00' },
            { value: '10:00', label: '10:00' }
        ];

        assert.equal(flowHelpers.resolveTutorialTimeAutoSelectValue(slots), '10:00');
        assert.equal(slots[0].value, '11:00');
        assert.equal(slots[1].value, '10:00');
    });
});

describe('canStartTutorialTimePrefill B2c', () => {
    it('tutorial con fecha y sin hora puede arrancar', () => {
        assert.equal(flowHelpers.canStartTutorialTimePrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialTimePrefillDone: false,
            state: READY_STATE,
            hasTimeSelect: true,
            timeSelectValue: ''
        }), true);
    });

    it('modal normal no arranca', () => {
        assert.equal(flowHelpers.canStartTutorialTimePrefill({
            tutorialContext: null,
            tutorialTimePrefillDone: false,
            state: READY_STATE,
            hasTimeSelect: true,
            timeSelectValue: ''
        }), false);
    });

    it('no arranca si ya hay hora seleccionada', () => {
        assert.equal(flowHelpers.canStartTutorialTimePrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialTimePrefillDone: false,
            state: Object.assign({}, READY_STATE, { selectedTime: '09:30' }),
            hasTimeSelect: true,
            timeSelectValue: '09:30'
        }), false);
    });

    it('no arranca si B2c ya terminó', () => {
        assert.equal(flowHelpers.canStartTutorialTimePrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialTimePrefillDone: true,
            state: READY_STATE,
            hasTimeSelect: true,
            timeSelectValue: ''
        }), false);
    });
});

describe('tutorial time autoselect canonical path B2c', () => {
    it('tryAutoSelectSelectValue dispara change con el segundo slot', () => {
        var events = [];
        var select = {
            value: '',
            options: [
                { value: '' },
                { value: '09:00' },
                { value: '09:30' }
            ],
            dispatchEvent: function(event) {
                events.push(event.type);
            }
        };

        var slotValue = flowHelpers.resolveTutorialTimeAutoSelectValue([
            { value: '09:00' },
            { value: '09:30' }
        ]);

        assert.equal(slotValue, '09:30');
        assert.equal(flowHelpers.tryAutoSelectSelectValue(select, slotValue), true);
        assert.equal(select.value, '09:30');
        assert.deepEqual(events, ['change']);
    });

    it('tryAutoSelectSelectValue no pisa hora ya elegida', () => {
        var select = {
            value: '10:00',
            options: [
                { value: '' },
                { value: '09:30' }
            ],
            dispatchEvent: function() {
                throw new Error('no debe disparar change');
            }
        };

        assert.equal(flowHelpers.tryAutoSelectSelectValue(select, '09:30'), false);
    });
});
