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
