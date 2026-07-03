'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const guide = require(path.join(
    __dirname,
    '../../includes/admin/ui/modals/onboarding/onboardingActivationGuide.js'
));

describe('onboardingActivationGuide presentation (MC1)', () => {
    it('solo expone first_appointment en el checklist visible', () => {
        assert.deepEqual(guide.VISIBLE_STEP_KEYS, ['first_appointment']);
    });

    it('renombra first_appointment a Crear cita de prueba', () => {
        assert.equal(
            guide.getDisplayLabel('first_appointment', { label: 'Primera cita' }),
            'Crear cita de prueba'
        );
    });

    it('getPresentationNextStep prioriza first_appointment aunque setup_complete sea false', () => {
        var status = {
            activation_complete: false,
            setup_complete: false,
            steps: {
                first_appointment: { completed: false }
            },
            next_step: 'client'
        };

        assert.equal(guide.getPresentationNextStep(status), 'first_appointment');
    });

    it('isActionableStep permite first_appointment sin depender de setup_complete', () => {
        var status = {
            setup_complete: false,
            activation_complete: false,
            next_step: 'client',
            steps: {
                first_appointment: { completed: false }
            }
        };

        assert.equal(guide.isActionableStep('first_appointment', status), true);
        assert.equal(guide.isFirstAppointmentPending(status), true);
    });

    it('isActionableStep desactiva first_appointment cuando activation está completa', () => {
        var status = {
            setup_complete: true,
            activation_complete: true,
            steps: {
                first_appointment: { completed: true }
            }
        };

        assert.equal(guide.isActionableStep('first_appointment', status), false);
        assert.equal(guide.getPresentationNextStep(status), null);
    });

    it('buildSummaryHtml explica cita de prueba sin mensaje de preparando datos', () => {
        var html = guide.buildSummaryHtml({
            activation_complete: false,
            setup_complete: false
        });

        assert.match(html, /cita de prueba/i);
        assert.match(html, /datos ficticios/i);
        assert.match(html, /clientes reales/i);
        assert.match(html, /servicios/i);
        assert.doesNotMatch(html, /Estamos preparando los datos de prueba/i);
        assert.doesNotMatch(html, /Completa estos pasos para activar/i);
    });
});
