'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const availability = require(path.join(
    __dirname,
    '../../assets/js/services/availability/fastAppointmentTimeAvailabilityService.js'
));

const flowHelpers = require(path.join(
    __dirname,
    '../../assets/js/controllers/adminFastappointmentFlowController.js'
));

function slotAt(time) {
    return { value: time, label: time, available: true };
}

function staffBusyForSingleStaff(ranges) {
    return {
        1: ranges.map(function(range) {
            return { start: range.start, end: range.end };
        })
    };
}

describe('buildBaseSlots — grilla fija de 30 minutos', () => {
    it('genera inicios cada 30 minutos independientemente de la duración de la cita', () => {
        var slots = availability.buildBaseSlots(availability.GRID_STEP_MINUTES);
        var morning = slots
            .map(function(slot) { return slot.value; })
            .filter(function(value) {
                return value >= '09:00' && value <= '10:00';
            });

        assert.deepEqual(morning, ['09:00', '09:30', '10:00']);
        assert.equal(availability.GRID_STEP_MINUTES, 30);
    });
});

describe('evaluateSlots — duración efectiva de la cita', () => {
    var usableStaff = [{ id: 1, name: 'Staff A' }];
    var slots = [slotAt('13:30')];
    var staffBusy = staffBusyForSingleStaff([
        { start: '13:00', end: '13:30' },
        { start: '14:00', end: '14:30' }
    ]);

    it('mantiene 13:30 visible para citas de 30 minutos', () => {
        var result = availability.evaluateSlots(slots, 30, usableStaff, staffBusy);

        assert.deepEqual(
            result.availableSlots.map(function(slot) { return slot.value; }),
            ['13:30']
        );
    });

    it('oculta 13:30 para citas de 60 minutos cuando el siguiente bloque está ocupado', () => {
        var result = availability.evaluateSlots(slots, 60, usableStaff, staffBusy);

        assert.deepEqual(result.availableSlots, []);
        assert.deepEqual(result.removedSlots, ['13:30']);
    });

    it('oculta 13:30 para citas de 90 minutos cuando el siguiente bloque está ocupado', () => {
        var result = availability.evaluateSlots(slots, 90, usableStaff, staffBusy);

        assert.deepEqual(result.availableSlots, []);
        assert.deepEqual(result.removedSlots, ['13:30']);
    });
});

describe('evaluateSlots — al menos un staff libre en el rango completo', () => {
    it('mantiene el horario si solo uno de varios staff está ocupado', () => {
        var slots = [slotAt('13:30')];
        var usableStaff = [
            { id: 1, name: 'Staff A' },
            { id: 2, name: 'Staff B' }
        ];
        var staffBusy = {
            1: [{ start: '13:30', end: '14:30' }],
            2: []
        };

        var result = availability.evaluateSlots(slots, 60, usableStaff, staffBusy);

        assert.deepEqual(
            result.availableSlots.map(function(slot) { return slot.value; }),
            ['13:30']
        );
    });
});

describe('getEffectiveAppointmentDurationMinutes — cascada única', () => {
    it('usa duration_minutes del servicio seleccionado', () => {
        assert.equal(flowHelpers.getEffectiveAppointmentDurationMinutes({
            selectedServiceId: '9',
            fastAppointmentPrerequisites: {
                activeServices: [{ id: '9', duration_minutes: 90 }]
            }
        }), 90);
    });

    it('cae a aa_slot_duration cuando el servicio no define duración', () => {
        global.window = { aa_slot_duration: 60 };

        assert.equal(flowHelpers.getEffectiveAppointmentDurationMinutes({
            selectedServiceId: '9',
            fastAppointmentPrerequisites: {
                activeServices: [{ id: '9', duration_minutes: null }]
            }
        }), 60);

        delete global.window;
    });
});
