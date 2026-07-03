'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const coordinator = require(path.join(
    __dirname,
    '../../includes/admin/ui/modals/onboarding/onboardingActivationCoordinator.js'
));

describe('onboardingActivationCoordinator fast appointment guard (MC1 fix)', () => {
    it('no intercepta FastAppointmentModal.open por setup_complete', () => {
        assert.equal(coordinator.isFastAppointmentOpenIntercepted(), false);
    });

    it('FastAppointmentModal.open sin guard llama al open original aunque setup_complete sea false', async function () {
        var modalOpenCalls = 0;
        var guideOpenCalls = 0;

        var originalOpen = function () {
            modalOpenCalls++;
        };

        var modal = {
            open: originalOpen
        };

        assert.notEqual(modal.open.__aaOnboardingGuarded, true);

        modal.open();

        assert.equal(modalOpenCalls, 1);
        assert.equal(guideOpenCalls, 0);
        assert.equal(modal.open, originalOpen);
    });
});
