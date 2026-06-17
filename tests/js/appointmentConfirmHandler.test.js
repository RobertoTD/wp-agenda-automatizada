'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const handlersPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/learning-action-handlers.js');

function loadHandlers() {
    delete require.cache[handlersPath];
    require(handlersPath);
    return globalThis.LearningActionHandlers;
}

function appointmentItem(originKey, overrides) {
    return Object.assign({
        id: '10',
        source: 'system',
        origin_key: originKey,
        primary_action: {
            type: 'handler',
            label: 'Confirmar',
            handler: 'appointment.confirm'
        },
        visible_actions: [{
            type: 'handler',
            label: 'Confirmar',
            handler: 'appointment.confirm'
        }]
    }, overrides || {});
}

function appointmentAction() {
    return {
        type: 'handler',
        label: 'Confirmar',
        handler: 'appointment.confirm'
    };
}

function stubConfirmEnvironment(options) {
    var opts = options || {};

    if (opts.withService !== false) {
        globalThis.ConfirmService = {
            confirmar: opts.confirmar || function () {
                return Promise.resolve({ success: true, data: { local_confirmed: true } });
            }
        };
    } else {
        delete globalThis.ConfirmService;
    }

    if (opts.withNonce !== false) {
        globalThis.aa_asistant_vars = {
            nonce_confirmar: opts.nonce || 'test-nonce'
        };
    } else {
        delete globalThis.aa_asistant_vars;
    }
}

describe('appointment.confirm handler (MC5)', () => {
    let originalConfirmService;
    let originalAssistantVars;
    let originalAdminConfirmController;
    let originalDocument;
    let dispatchedEvents;

    beforeEach(() => {
        originalConfirmService = globalThis.ConfirmService;
        originalAssistantVars = globalThis.aa_asistant_vars;
        originalAdminConfirmController = globalThis.AdminConfirmController;
        originalDocument = globalThis.document;
        dispatchedEvents = [];

        globalThis.document = {
            dispatchEvent: function (event) {
                dispatchedEvents.push(event.type);
            }
        };

        globalThis.AdminConfirmController = {
            showLocalActionSuccessNotification: function () {},
            isConfirmAutomationIncomplete: function () {
                return false;
            },
            showConfirmResultNotification: function () {},
            showAutomationConnectionFailedNotification: function () {}
        };

        stubConfirmEnvironment();
        loadHandlers();
    });

    afterEach(() => {
        if (originalConfirmService === undefined) {
            delete globalThis.ConfirmService;
        } else {
            globalThis.ConfirmService = originalConfirmService;
        }

        if (originalAssistantVars === undefined) {
            delete globalThis.aa_asistant_vars;
        } else {
            globalThis.aa_asistant_vars = originalAssistantVars;
        }

        if (originalAdminConfirmController === undefined) {
            delete globalThis.AdminConfirmController;
        } else {
            globalThis.AdminConfirmController = originalAdminConfirmController;
        }

        if (originalDocument === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = originalDocument;
        }

        delete require.cache[handlersPath];
    });

    describe('resolveAppointmentConfirmationReservationId', () => {
        let resolveId;

        beforeEach(() => {
            resolveId = globalThis.LearningActionHandlers.resolveAppointmentConfirmationReservationId;
        });

        it('appointment_confirmation:42 → 42', () => {
            assert.equal(resolveId(appointmentItem('appointment_confirmation:42')), 42);
        });

        it('prefijo incorrecto → null', () => {
            assert.equal(resolveId(appointmentItem('appointment_confirm:42')), null);
            assert.equal(resolveId(appointmentItem('other:42')), null);
        });

        it('ID cero → null', () => {
            assert.equal(resolveId(appointmentItem('appointment_confirmation:0')), null);
        });

        it('ID negativo → null', () => {
            assert.equal(resolveId(appointmentItem('appointment_confirmation:-5')), null);
        });

        it('ID decimal → null', () => {
            assert.equal(resolveId(appointmentItem('appointment_confirmation:42.5')), null);
        });

        it('ID no numérico → null', () => {
            assert.equal(resolveId(appointmentItem('appointment_confirmation:abc')), null);
            assert.equal(resolveId(appointmentItem('appointment_confirmation:')), null);
        });
    });

    describe('isAvailable', () => {
        it('válido con ConfirmService y nonce → true', () => {
            assert.equal(
                globalThis.LearningActionHandlers.isAvailable(
                    appointmentAction(),
                    appointmentItem('appointment_confirmation:42')
                ),
                true
            );
        });

        it('origin inválido → false', () => {
            assert.equal(
                globalThis.LearningActionHandlers.isAvailable(
                    appointmentAction(),
                    appointmentItem('invalid:42')
                ),
                false
            );
        });

        it('sin servicio → false', () => {
            stubConfirmEnvironment({ withService: false });
            loadHandlers();

            assert.equal(
                globalThis.LearningActionHandlers.isAvailable(
                    appointmentAction(),
                    appointmentItem('appointment_confirmation:42')
                ),
                false
            );
        });

        it('sin nonce → false', () => {
            stubConfirmEnvironment({ withNonce: false });
            loadHandlers();

            assert.equal(
                globalThis.LearningActionHandlers.isAvailable(
                    appointmentAction(),
                    appointmentItem('appointment_confirmation:42')
                ),
                false
            );
        });
    });

    describe('run', () => {
        it('llama ConfirmService.confirmar con el ID correcto', async () => {
            var capturedId = null;

            globalThis.ConfirmService.confirmar = function (id) {
                capturedId = id;
                return Promise.resolve({ success: true, data: { local_confirmed: true } });
            };

            await globalThis.LearningActionHandlers.run(
                appointmentAction(),
                appointmentItem('appointment_confirmation:42'),
                {}
            );

            assert.equal(capturedId, 42);
        });

        it('éxito local → { reload: true }', async () => {
            var result = await globalThis.LearningActionHandlers.run(
                appointmentAction(),
                appointmentItem('appointment_confirmation:7'),
                {}
            );

            assert.deepEqual(result, { reload: true });
            assert.equal(dispatchedEvents.indexOf('aa-cita-action-completed') !== -1, true);
        });

        it('fallo de confirmación → reject', async () => {
            globalThis.ConfirmService.confirmar = function () {
                return Promise.resolve({
                    success: false,
                    data: { message: 'Conflicto de personal.' }
                });
            };

            await assert.rejects(
                () => globalThis.LearningActionHandlers.run(
                    appointmentAction(),
                    appointmentItem('appointment_confirmation:7'),
                    {}
                ),
                /Conflicto de personal/
            );
        });

        it('fallo remoto posterior con confirmación local exitosa → éxito', async () => {
            globalThis.ConfirmService.confirmar = function () {
                return Promise.resolve({
                    success: true,
                    data: {
                        success: true,
                        local_confirmed: true,
                        calendar_sync: false,
                        message: 'backend remoto no disponible'
                    }
                });
            };

            globalThis.AdminConfirmController.isConfirmAutomationIncomplete = function () {
                return true;
            };

            var automationCalls = 0;
            globalThis.AdminConfirmController.showAutomationConnectionFailedNotification = function () {
                automationCalls += 1;
            };

            var result = await globalThis.LearningActionHandlers.run(
                appointmentAction(),
                appointmentItem('appointment_confirmation:9'),
                {}
            );

            assert.deepEqual(result, { reload: true });
            assert.equal(automationCalls, 1);
        });

        it('nunca llama mecanismos de completado de tareas', async () => {
            globalThis.ChangeTaskStatusUseCase = {
                execute: function () {
                    throw new Error('no debe invocarse');
                }
            };

            await globalThis.LearningActionHandlers.run(
                appointmentAction(),
                appointmentItem('appointment_confirmation:3'),
                {}
            );

            delete globalThis.ChangeTaskStatusUseCase;
        });
    });

    describe('pwa.install regresión', () => {
        it('sigue registrado sin cambios de contrato', () => {
            var handler = globalThis.LearningActionHandlers.get('pwa.install');

            assert.ok(handler);
            assert.equal(typeof handler.isAvailable, 'function');
            assert.equal(typeof handler.run, 'function');
            assert.equal(typeof handler.shouldHideRecommendation, 'function');
        });
    });
});
