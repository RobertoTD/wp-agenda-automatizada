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

const ONE_CLIENT = [{ id: 42, nombre: 'Ana', telefono: '555', correo: '' }];

describe('resolveTutorialClientAutoSelectId B2a', () => {
    it('tutorial + total 1 + un cliente devuelve id', () => {
        assert.equal(
            flowHelpers.resolveTutorialClientAutoSelectId(TUTORIAL_CONTEXT, {
                clients: ONE_CLIENT,
                total: 1,
                query: ''
            }),
            '42'
        );
    });

    it('tutorial + 0 clientes no autoselecciona', () => {
        assert.equal(
            flowHelpers.resolveTutorialClientAutoSelectId(TUTORIAL_CONTEXT, {
                clients: [],
                total: 0,
                query: ''
            }),
            null
        );
    });

    it('tutorial + 2 clientes no autoselecciona', () => {
        assert.equal(
            flowHelpers.resolveTutorialClientAutoSelectId(TUTORIAL_CONTEXT, {
                clients: [{ id: 1 }, { id: 2 }],
                total: 2,
                query: ''
            }),
            null
        );
    });

    it('tutorial + total distinto de clients.length no autoselecciona', () => {
        assert.equal(
            flowHelpers.resolveTutorialClientAutoSelectId(TUTORIAL_CONTEXT, {
                clients: ONE_CLIENT,
                total: 2,
                query: ''
            }),
            null
        );
    });

    it('tutorial + búsqueda manual con un resultado no autoselecciona', () => {
        assert.equal(
            flowHelpers.resolveTutorialClientAutoSelectId(TUTORIAL_CONTEXT, {
                clients: ONE_CLIENT,
                total: 1,
                query: 'ana'
            }),
            null
        );
    });

    it('modal normal + 1 cliente no autoselecciona', () => {
        assert.equal(
            flowHelpers.resolveTutorialClientAutoSelectId(null, {
                clients: ONE_CLIENT,
                total: 1,
                query: ''
            }),
            null
        );
    });

    it('contexto tutorial inválido no autoselecciona', () => {
        assert.equal(
            flowHelpers.resolveTutorialClientAutoSelectId({
                tutorialId: 'other',
                stepId: 'create_test_appointment',
                source: 'tutorial'
            }, {
                clients: ONE_CLIENT,
                total: 1,
                query: ''
            }),
            null
        );
    });
});

describe('tutorial client autoselect canonical path B2a', () => {
    it('usa tryAutoSelectSelectValue y dispara change como selección manual', () => {
        var changeCount = 0;
        var select = {
            value: '',
            options: [
                { value: '' },
                { value: '42', dataset: { nombre: 'Ana', telefono: '555', correo: '' } }
            ],
            dispatchEvent: function(event) {
                if (event.type === 'change') {
                    changeCount++;
                }
            }
        };

        var clientId = flowHelpers.resolveTutorialClientAutoSelectId(TUTORIAL_CONTEXT, {
            clients: ONE_CLIENT,
            total: 1,
            query: ''
        });

        assert.equal(clientId, '42');
        assert.equal(flowHelpers.tryAutoSelectSelectValue(select, clientId), true);
        assert.equal(select.value, '42');
        assert.equal(changeCount, 1);
    });

    it('tryAutoSelectSelectValue no pisa selección existente', () => {
        var select = {
            value: '99',
            options: [{ value: '99' }, { value: '42' }],
            dispatchEvent: function() {
                throw new Error('no debe disparar change');
            }
        };

        assert.equal(flowHelpers.tryAutoSelectSelectValue(select, '42'), false);
    });
});

describe('isCreateTestAppointmentTutorialContext B2a', () => {
    it('acepta snapshot exacto del tutorial', () => {
        assert.equal(flowHelpers.isCreateTestAppointmentTutorialContext(TUTORIAL_CONTEXT), true);
    });

    it('rechaza snapshot incompleto', () => {
        assert.equal(flowHelpers.isCreateTestAppointmentTutorialContext({
            tutorialId: 'create_test_appointment_v1',
            stepId: 'create_test_appointment'
        }), false);
    });
});
