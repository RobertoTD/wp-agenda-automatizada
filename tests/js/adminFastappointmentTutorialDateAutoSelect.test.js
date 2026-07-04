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
    isClientStepReady: true,
    selectedServiceId: '9',
    canStartFastAppointment: true,
    selectedDate: null
};

describe('normalizeFutureWindowDays B2b', () => {
    it('normaliza aa_future_window válido', () => {
        assert.equal(flowHelpers.normalizeFutureWindowDays(30), 30);
        assert.equal(flowHelpers.normalizeFutureWindowDays('45'), 45);
    });

    it('usa 15 cuando el valor no es finito positivo', () => {
        assert.equal(flowHelpers.normalizeFutureWindowDays(0), 15);
        assert.equal(flowHelpers.normalizeFutureWindowDays(-3), 15);
        assert.equal(flowHelpers.normalizeFutureWindowDays('x'), 15);
        assert.equal(flowHelpers.normalizeFutureWindowDays(undefined), 15);
    });
});

describe('addDaysYmd B2b', () => {
    it('suma días en formato Y-m-d', () => {
        assert.equal(flowHelpers.addDaysYmd('2026-07-04', 0), '2026-07-04');
        assert.equal(flowHelpers.addDaysYmd('2026-07-04', 1), '2026-07-05');
        assert.equal(flowHelpers.addDaysYmd('2026-07-31', 1), '2026-08-01');
    });
});

describe('canStartTutorialDatePrefill B2b', () => {
    it('tutorial con precondiciones listas puede arrancar', () => {
        assert.equal(flowHelpers.canStartTutorialDatePrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialDatePrefillDone: false,
            tutorialDatePrefillInFlight: false,
            state: READY_STATE,
            hasDatePicker: true,
            hasDateInput: true,
            dateInputValue: '',
            hasAvailabilityService: true
        }), true);
    });

    it('modal normal no arranca', () => {
        assert.equal(flowHelpers.canStartTutorialDatePrefill({
            tutorialContext: null,
            tutorialDatePrefillDone: false,
            tutorialDatePrefillInFlight: false,
            state: READY_STATE,
            hasDatePicker: true,
            hasDateInput: true,
            dateInputValue: '',
            hasAvailabilityService: true
        }), false);
    });

    it('no arranca si ya hay fecha seleccionada', () => {
        assert.equal(flowHelpers.canStartTutorialDatePrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialDatePrefillDone: false,
            tutorialDatePrefillInFlight: false,
            state: Object.assign({}, READY_STATE, { selectedDate: '2026-07-10' }),
            hasDatePicker: true,
            hasDateInput: true,
            dateInputValue: '',
            hasAvailabilityService: true
        }), false);
    });

    it('no arranca si B2b ya terminó o está en vuelo', () => {
        assert.equal(flowHelpers.canStartTutorialDatePrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialDatePrefillDone: true,
            tutorialDatePrefillInFlight: false,
            state: READY_STATE,
            hasDatePicker: true,
            hasDateInput: true,
            dateInputValue: '',
            hasAvailabilityService: true
        }), false);

        assert.equal(flowHelpers.canStartTutorialDatePrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialDatePrefillDone: false,
            tutorialDatePrefillInFlight: true,
            state: READY_STATE,
            hasDatePicker: true,
            hasDateInput: true,
            dateInputValue: '',
            hasAvailabilityService: true
        }), false);
    });

    it('no arranca sin cliente o servicio listos', () => {
        assert.equal(flowHelpers.canStartTutorialDatePrefill({
            tutorialContext: TUTORIAL_CONTEXT,
            tutorialDatePrefillDone: false,
            tutorialDatePrefillInFlight: false,
            state: Object.assign({}, READY_STATE, { isClientStepReady: false }),
            hasDatePicker: true,
            hasDateInput: true,
            dateInputValue: '',
            hasAvailabilityService: true
        }), false);
    });
});

describe('findFirstDateWithDaySlots B2b', () => {
    it('elige la primera fecha con slots', async () => {
        var calls = [];

        var dateStr = await flowHelpers.findFirstDateWithDaySlots({
            startYmd: '2026-07-04',
            maxDays: 3,
            usableStaff: [{ id: 1 }],
            getAvailabilityByDate: function(date) {
                calls.push(date);
                return Promise.resolve({
                    slots: date === '2026-07-05' ? [{ value: '10:00' }] : []
                });
            }
        });

        assert.equal(dateStr, '2026-07-05');
        assert.deepEqual(calls, ['2026-07-04', '2026-07-05']);
    });

    it('detiene la búsqueda al primer hit', async () => {
        var calls = [];

        var dateStr = await flowHelpers.findFirstDateWithDaySlots({
            startYmd: '2026-07-04',
            maxDays: 5,
            usableStaff: [],
            getAvailabilityByDate: function(date) {
                calls.push(date);
                return Promise.resolve({
                    slots: date === '2026-07-04' ? [{ value: '09:00' }] : []
                });
            }
        });

        assert.equal(dateStr, '2026-07-04');
        assert.equal(calls.length, 1);
    });

    it('devuelve null si ningún día tiene slots', async () => {
        var dateStr = await flowHelpers.findFirstDateWithDaySlots({
            startYmd: '2026-07-04',
            maxDays: 2,
            usableStaff: [],
            getAvailabilityByDate: function() {
                return Promise.resolve({ slots: [] });
            }
        });

        assert.equal(dateStr, null);
    });

    it('continúa tras error de un día y usa el siguiente con slots', async () => {
        var dateStr = await flowHelpers.findFirstDateWithDaySlots({
            startYmd: '2026-07-04',
            maxDays: 3,
            usableStaff: [],
            getAvailabilityByDate: function(date) {
                if (date === '2026-07-04') {
                    return Promise.reject(new Error('network'));
                }

                return Promise.resolve({
                    slots: date === '2026-07-05' ? [{ value: '11:00' }] : []
                });
            }
        });

        assert.equal(dateStr, '2026-07-05');
    });

    it('respeta shouldAbort antes de aplicar resultado', async () => {
        var aborted = false;

        var dateStr = await flowHelpers.findFirstDateWithDaySlots({
            startYmd: '2026-07-04',
            maxDays: 3,
            usableStaff: [],
            shouldAbort: function() {
                return aborted;
            },
            getAvailabilityByDate: function(date) {
                if (date === '2026-07-05') {
                    aborted = true;
                }

                return Promise.resolve({
                    slots: date === '2026-07-05' ? [{ value: '12:00' }] : []
                });
            }
        });

        assert.equal(dateStr, null);
    });

    it('limita consultas a maxDays normalizado', async () => {
        var calls = 0;

        await flowHelpers.findFirstDateWithDaySlots({
            startYmd: '2026-07-04',
            maxDays: 2,
            usableStaff: [],
            getAvailabilityByDate: function() {
                calls++;
                return Promise.resolve({ slots: [] });
            }
        });

        assert.equal(calls, 2);
    });
});
