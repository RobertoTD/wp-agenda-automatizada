'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const autoSelect = require(path.join(
    __dirname,
    '../../assets/js/controllers/adminFastappointmentFlowController.js'
));

describe('adminFastappointmentFlowController auto-select helpers', () => {
    it('getSingleEligibleItem devuelve null con 0 o más de 1 elegibles', () => {
        assert.equal(autoSelect.getSingleEligibleItem([]), null);
        assert.equal(autoSelect.getSingleEligibleItem([{ id: 1 }, { id: 2 }]), null);
        assert.equal(
            autoSelect.getSingleEligibleItem(
                [{ available: true }, { available: true }],
                function(item) { return item.available === true; }
            ),
            null
        );
    });

    it('getSingleEligibleItem devuelve el único elegible', () => {
        var item = { id: 7, available: true };
        assert.deepEqual(
            autoSelect.getSingleEligibleItem(
                [item, { id: 2, available: false }],
                function(candidate) { return candidate.available === true; }
            ),
            item
        );
    });

    it('tryAutoSelectSelectValue no pisa selección manual', () => {
        var select = {
            value: '2',
            options: [
                { value: '' },
                { value: '1' },
                { value: '2' }
            ],
            dispatchEvent: function() {
                throw new Error('no debe disparar change');
            }
        };

        assert.equal(autoSelect.tryAutoSelectSelectValue(select, '1'), false);
    });

    it('tryAutoSelectSelectValue asigna valor y dispara change', () => {
        var events = [];
        var select = {
            value: '',
            options: [
                { value: '' },
                { value: '9' }
            ],
            dispatchEvent: function(event) {
                events.push(event.type);
            }
        };

        assert.equal(autoSelect.tryAutoSelectSelectValue(select, 9), true);
        assert.equal(select.value, '9');
        assert.deepEqual(events, ['change']);
    });
});

describe('adminFastappointmentFlowController auto-resolved step collapse helpers', () => {
    it('recordAutoResolution marca ready y usa las mismas opciones elegibles que getSingleEligibleItem', () => {
        var staff = [
            { id: 1, available: false },
            { id: 7, available: true }
        ];
        var isEligible = function(item) { return item.available === true; };

        assert.deepEqual(autoSelect.recordAutoResolution(staff, isEligible), {
            status: 'ready',
            eligibleCount: 1,
            eligibleId: 7
        });
        assert.equal(autoSelect.getSingleEligibleItem(staff, isEligible).id, 7);

        assert.deepEqual(
            autoSelect.recordAutoResolution(
                [{ id: 1, available: true }, { id: 2, available: true }],
                isEligible
            ),
            { status: 'ready', eligibleCount: 2, eligibleId: null }
        );
        assert.deepEqual(
            autoSelect.recordAutoResolution([], isEligible),
            { status: 'ready', eligibleCount: 0, eligibleId: null }
        );
        assert.deepEqual(
            autoSelect.recordAutoResolution([{ available: true }], isEligible),
            { status: 'ready', eligibleCount: 1, eligibleId: null }
        );
    });

    it('createEmptyAutoResolution queda pending', () => {
        assert.deepEqual(autoSelect.createEmptyAutoResolution(), {
            status: 'pending',
            eligibleCount: 0,
            eligibleId: null
        });
    });

    it('shouldCollapseAutoResolvedStep no colapsa con 0 o más de 1 elegibles', () => {
        assert.equal(autoSelect.shouldCollapseAutoResolvedStep({
            eligibleCount: 0,
            eligibleId: null,
            selectedId: '1'
        }), false);
        assert.equal(autoSelect.shouldCollapseAutoResolvedStep({
            eligibleCount: 2,
            eligibleId: null,
            selectedId: '1'
        }), false);
    });

    it('shouldCollapseAutoResolvedStep no colapsa con una opción si falta el id seleccionado', () => {
        assert.equal(autoSelect.shouldCollapseAutoResolvedStep({
            eligibleCount: 1,
            eligibleId: '9',
            selectedId: null
        }), false);
        assert.equal(autoSelect.shouldCollapseAutoResolvedStep({
            eligibleCount: 1,
            eligibleId: '9',
            selectedId: ''
        }), false);
    });

    it('shouldCollapseAutoResolvedStep no colapsa si el id guardado no es el único elegible', () => {
        assert.equal(autoSelect.shouldCollapseAutoResolvedStep({
            eligibleCount: 1,
            eligibleId: '9',
            selectedId: '8'
        }), false);
    });

    it('shouldCollapseAutoResolvedStep colapsa solo con una opción y el mismo id', () => {
        assert.equal(autoSelect.shouldCollapseAutoResolvedStep({
            eligibleCount: 1,
            eligibleId: 9,
            selectedId: '9'
        }), true);
    });

    it('shouldCollapseAutoResolvedStep ignora status pending (Servicio sigue visible)', () => {
        assert.equal(autoSelect.shouldCollapseAutoResolvedStep({
            status: 'pending',
            eligibleCount: 0,
            eligibleId: null,
            selectedId: null
        }), false);
    });

    it('shouldHideDeferredStep oculta pending', () => {
        assert.equal(autoSelect.shouldHideDeferredStep({
            status: 'pending',
            eligibleCount: 0,
            eligibleId: null,
            selectedId: null
        }), true);
        assert.equal(autoSelect.shouldHideDeferredStep({
            eligibleCount: 2,
            eligibleId: null,
            selectedId: '1'
        }), true);
    });

    it('shouldHideDeferredStep muestra ready/0 y ready/≥2', () => {
        assert.equal(autoSelect.shouldHideDeferredStep({
            status: 'ready',
            eligibleCount: 0,
            eligibleId: null,
            selectedId: null
        }), false);
        assert.equal(autoSelect.shouldHideDeferredStep({
            status: 'ready',
            eligibleCount: 2,
            eligibleId: null,
            selectedId: '3'
        }), false);
    });

    it('shouldHideDeferredStep oculta ready/1 solo con coincidencia confirmada', () => {
        assert.equal(autoSelect.shouldHideDeferredStep({
            status: 'ready',
            eligibleCount: 1,
            eligibleId: 9,
            selectedId: '9'
        }), true);
    });

    it('shouldHideDeferredStep muestra ready/1 sin coincidencia de selectedId', () => {
        assert.equal(autoSelect.shouldHideDeferredStep({
            status: 'ready',
            eligibleCount: 1,
            eligibleId: '9',
            selectedId: null
        }), false);
        assert.equal(autoSelect.shouldHideDeferredStep({
            status: 'ready',
            eligibleCount: 1,
            eligibleId: '9',
            selectedId: '8'
        }), false);
    });

    it('isFormReadyFromState no depende de la visibilidad de los pasos', () => {
        assert.equal(autoSelect.isFormReadyFromState({
            selectedClientId: '1',
            selectedServiceId: '2',
            selectedDate: '2026-08-15',
            selectedTime: '10:00',
            selectedStaffId: '3',
            isSelectedStaffAvailable: true,
            selectedAreaId: '4',
            isSelectedAreaAvailable: true
        }), true);
    });
});
