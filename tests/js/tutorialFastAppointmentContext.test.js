'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const contextPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialFastAppointmentContext.js'
);
const contextSrc = fs.readFileSync(contextPath, 'utf8');

const VALID_PAYLOAD = {
    tutorialId: 'create_test_appointment_v1',
    stepId: 'create_test_appointment',
    source: 'tutorial'
};

function loadContext() {
    var context = { window: {} };
    context.window = context;

    vm.runInNewContext(contextSrc, context, { filename: contextPath });

    return context.window.TutorialFastAppointmentContext;
}

describe('TutorialFastAppointmentContext B1', () => {
    /** @type {ReturnType<typeof loadContext>} */
    var api;

    beforeEach(function () {
        api = loadContext();
    });

    it('inicia inactivo', () => {
        assert.equal(api.isActive(), false);
        assert.equal(api.get(), null);
    });

    it('activate con payload mínimo válido', () => {
        assert.equal(api.activate(VALID_PAYLOAD), true);
        assert.equal(api.isActive(), true);
        assert.deepEqual(Object.assign({}, api.get()), VALID_PAYLOAD);
    });

    it('activate es idempotente con mismo payload', () => {
        api.activate(VALID_PAYLOAD);
        assert.equal(api.activate(VALID_PAYLOAD), true);
        assert.deepEqual(Object.assign({}, api.get()), VALID_PAYLOAD);
    });

    it('activate reemplaza payload distinto', () => {
        api.activate(VALID_PAYLOAD);
        api.activate({
            tutorialId: 'other_tutorial',
            stepId: 'other_step',
            source: 'tutorial'
        });
        assert.deepEqual(Object.assign({}, api.get()), {
            tutorialId: 'other_tutorial',
            stepId: 'other_step',
            source: 'tutorial'
        });
    });

    it('rechaza payload inválido', () => {
        assert.equal(api.activate(null), false);
        assert.equal(api.activate({}), false);
        assert.equal(api.activate({ tutorialId: '', stepId: 'x', source: 'tutorial' }), false);
        assert.equal(api.isActive(), false);
    });

    it('get devuelve copia congelada', () => {
        api.activate(VALID_PAYLOAD);
        var snapshot = api.get();
        assert.equal(Object.isFrozen(snapshot), true);

        assert.throws(function () {
            snapshot.stepId = 'mutated';
        }, TypeError);
    });

    it('clear deja inactivo', () => {
        api.activate(VALID_PAYLOAD);
        api.clear();
        assert.equal(api.isActive(), false);
        assert.equal(api.get(), null);
    });

    it('clear es idempotente', () => {
        api.clear();
        api.clear();
        assert.equal(api.isActive(), false);
    });
});
