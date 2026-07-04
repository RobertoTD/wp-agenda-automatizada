'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const flowSrc = fs.readFileSync(path.join(
    __dirname,
    '../../assets/js/controllers/adminFastappointmentFlowController.js'
), 'utf8');

function makeClassList() {
    var classes = [];

    return {
        remove: function() {
            classes = [];
        },
        add: function() {
            for (var i = 0; i < arguments.length; i++) {
                if (classes.indexOf(arguments[i]) === -1) {
                    classes.push(arguments[i]);
                }
            }
        },
        toggle: function() {}
    };
}

function makeStepElement() {
    var headerClassList = makeClassList();
    var checkClassList = makeClassList();
    var bodyClassList = makeClassList();

    return {
        dataset: {},
        classList: makeClassList(),
        querySelector: function(selector) {
            if (selector === '[data-aa-fastappointment-step-header]') {
                return {
                    disabled: false,
                    setAttribute: function() {},
                    classList: headerClassList
                };
            }

            if (selector === '[data-aa-fastappointment-step-check]') {
                return { classList: checkClassList };
            }

            return null;
        },
        querySelectorAll: function() {
            return [];
        },
        appendChild: function() {},
        insertBefore: function() {},
        contains: function() {
            return false;
        }
    };
}

function loadSubmitHarness(options) {
    var opts = options || {};
    var reservationEvents = [];
    var actionLog = [];
    var saveImpl = opts.saveImpl;
    var assignmentResult = opts.assignmentResult || {
        mode: 'existing',
        assignment: { id: 10 }
    };

    var state = Object.assign({
        selectedClientId: '1',
        selectedServiceId: '2',
        selectedDate: '2026-07-05',
        selectedTime: '10:00',
        selectedStaffId: '3',
        isSelectedStaffAvailable: true,
        selectedAreaId: '4',
        isSelectedAreaAvailable: true,
        selectedClient: {
            nombre: 'Cliente',
            telefono: '5551234',
            correo: ''
        },
        fastAppointmentPrerequisites: {
            activeServices: [{ id: '2', duration_minutes: 60 }]
        }
    }, opts.state || {});

    var clientSelect = {
        id: 'aa-fastappointment-client',
        value: '1',
        selectedIndex: 1,
        options: [
            { value: '', textContent: '' },
            {
                value: '1',
                textContent: 'Cliente',
                dataset: { nombre: 'Cliente', telefono: '5551234', correo: '' }
            }
        ],
        addEventListener: function() {},
        removeEventListener: function() {},
        insertBefore: function() {},
        observe: function() {}
    };

    var formEl = {
        id: 'aa-fastappointment-form',
        addEventListener: function(type, handler) {
            if (type === 'submit') {
                formEl._submitHandler = handler;
            }
        },
        removeEventListener: function() {},
        contains: function() {
            return true;
        },
        dispatchEvent: function() {
            return true;
        }
    };

    var submitBtn = {
        id: 'aa-fastappointment-submit',
        disabled: false,
        textContent: 'Agendar cita',
        classList: makeClassList()
    };

    var stepClient = makeStepElement();
    var stepDate = makeStepElement();
    var stepTime = makeStepElement();
    var stepService = makeStepElement();
    var stepStaff = makeStepElement();
    var stepArea = makeStepElement();

    var dateInput = {
        id: 'aa-fastappointment-date',
        value: '',
        type: 'text',
        setAttribute: function() {},
        addEventListener: function() {}
    };

    var elements = {
        'aa-fastappointment-form': formEl,
        'aa-fastappointment-submit': submitBtn,
        'aa-fastappointment-step-service': stepService,
        'aa-fastappointment-step-staff': stepStaff,
        'aa-fastappointment-step-area': stepArea,
        'aa-fastappointment-client': clientSelect,
        'aa-fastappointment-date': dateInput,
        'aa-fastappointment-time': {
            id: 'aa-fastappointment-time',
            value: '10:00',
            options: [{ value: '' }, { value: '10:00' }],
            selectedIndex: 1,
            classList: makeClassList(),
            addEventListener: function() {},
            removeEventListener: function() {}
        },
        'aa-fastappointment-service': {
            id: 'aa-fastappointment-service',
            value: '2',
            selectedIndex: 1,
            options: [{ value: '' }, { value: '2', textContent: 'Servicio' }],
            addEventListener: function() {},
            removeEventListener: function() {}
        },
        'aa-fastappointment-staff': {
            id: 'aa-fastappointment-staff',
            value: '3',
            selectedIndex: 1,
            options: [{ value: '' }, { value: '3', textContent: 'Staff', dataset: { available: '1' } }],
            addEventListener: function() {},
            removeEventListener: function() {}
        },
        'aa-fastappointment-area': {
            id: 'aa-fastappointment-area',
            value: '4',
            selectedIndex: 1,
            options: [{ value: '' }, { value: '4', textContent: 'Zona', dataset: { available: '1' } }],
            addEventListener: function() {},
            removeEventListener: function() {}
        },
        'aa-fastappointment-staff-message': {
            id: 'aa-fastappointment-staff-message',
            textContent: '',
            classList: makeClassList()
        },
        'aa-fastappointment-area-message': {
            id: 'aa-fastappointment-area-message',
            textContent: '',
            classList: makeClassList()
        },
        'aa-fastappointment-confirm': {
            id: 'aa-fastappointment-confirm',
            checked: false,
            value: 'confirmed'
        },
        'aa-fastappointment-client-search': { id: 'aa-fastappointment-client-search', value: '' },
        'aa-fastappointment-client-create': { id: 'aa-fastappointment-client-create' },
        'aa-fastappointment-client-inline': { id: 'aa-fastappointment-client-inline' }
    };

    var context = {
        window: {},
        document: {
            getElementById: function(id) {
                return elements[id] || null;
            },
            querySelector: function(selector) {
                if (selector === '#aa-fastappointment-step-client') {
                    return stepClient;
                }
                if (selector === '#aa-fastappointment-step-date') {
                    return stepDate;
                }
                if (selector === '#aa-fastappointment-step-time') {
                    return stepTime;
                }
                if (selector === '#aa-fastappointment-summary') {
                    return null;
                }
                return null;
            },
            createElement: function(tag) {
                return {
                    tagName: tag.toUpperCase(),
                    value: '',
                    textContent: '',
                    className: '',
                    setAttribute: function() {},
                    classList: makeClassList(),
                    parentNode: dateInput,
                    insertBefore: function() {},
                    appendChild: function() {}
                };
            },
            addEventListener: function() {},
            removeEventListener: function() {},
            dispatchEvent: function(event) {
                if (event && event.type === 'aa:reservation:created') {
                    reservationEvents.push({
                        type: event.type,
                        detail: event.detail ? Object.assign({}, event.detail) : null
                    });
                    actionLog.push('reservation:created');
                }

                if (event && event.type === 'aa:notifications:refresh') {
                    actionLog.push('notifications:refresh');
                }

                return true;
            }
        },
        console: {
            log: function() {},
            warn: function() {},
            error: function() {},
            group: function() {},
            groupEnd: function() {}
        },
        MutationObserver: function() {
            this.observe = function() {};
            this.disconnect = function() {};
        },
        flatpickr: undefined,
        Date: Date,
        Event: Event,
        CustomEvent: CustomEvent,
        alert: function() {},
        setTimeout: function(fn) {
            fn();
            return 1;
        },
        clearTimeout: function() {},
        parseInt: parseInt,
        Number: Number,
        String: String,
        Object: Object,
        Array: Array,
        Promise: Promise
    };

    context.window = context;
    context.window.aa_asistant_vars = { nonce_crear_cita: 'test-nonce' };
    context.window.aa_slot_duration = 60;
    context.window.DateUtils = {
        extractYmd: function(value) {
            return value;
        }
    };
    context.window.ReservationClientController = {
        init: function() {
            return { destroy: function() {} };
        }
    };
    context.window.FastAppointmentPrerequisitesService = {
        evaluate: function() {
            return Promise.resolve({
                hasServices: true,
                hasUsableStaff: true,
                hasAreas: true,
                canStart: false,
                messages: []
            });
        }
    };
    context.window.FastAppointmentTimeAvailabilityService = {
        findCompatibleAssignment: function() {
            return Promise.resolve(assignmentResult);
        },
        getAvailabilityByDate: function() {
            return Promise.resolve({ slots: [] });
        }
    };
    context.window.ReservationService = {
        saveReservation: saveImpl || function() {
            return Promise.resolve({ data: { id: 42 } });
        }
    };
    context.window.AdminCalendarController = {
        recargar: function() {
            actionLog.push('recargar');
        }
    };
    context.window.AAAdmin = {
        closeModal: function() {
            actionLog.push('closeModal');
        }
    };
    context.window.AdminConfirmController = {
        showLocalActionSuccessNotification: function() {}
    };

    vm.runInNewContext(flowSrc, context, { filename: 'adminFastappointmentFlowController.js' });

    var controller = context.window.AdminFastappointmentFlowController.init({
        getState: function() {
            return state;
        },
        setState: function(nextState) {
            state = nextState;
        }
    });

    assert.ok(controller, 'controller inicializado');

    return {
        formEl: formEl,
        reservationEvents: reservationEvents,
        actionLog: actionLog,
        controller: controller,
        triggerSubmit: async function() {
            var event = {
                preventDefault: function() {},
                type: 'submit'
            };

            await formEl._submitHandler(event);
        },
        destroy: function() {
            if (controller && typeof controller.destroy === 'function') {
                controller.destroy();
            }
        }
    };
}

describe('adminFastappointmentReservationCreatedEvent C1a', () => {
    it('save OK + id emite un evento con payload exacto', async () => {
        var harness = loadSubmitHarness({
            saveImpl: function() {
                return Promise.resolve({ data: { id: 99 } });
            }
        });

        try {
            await harness.triggerSubmit();

            assert.equal(harness.reservationEvents.length, 1);
            assert.deepEqual(harness.reservationEvents[0].detail, {
                source: 'fastappointment',
                id: 99
            });
        } finally {
            harness.destroy();
        }
    });

    it('save OK sin id no emite evento', async () => {
        var harness = loadSubmitHarness({
            saveImpl: function() {
                return Promise.resolve({ data: { message: 'ok' } });
            }
        });

        try {
            await harness.triggerSubmit();

            assert.equal(harness.reservationEvents.length, 0);
        } finally {
            harness.destroy();
        }
    });

    it('fallo de save no emite evento', async () => {
        var harness = loadSubmitHarness({
            saveImpl: function() {
                return Promise.reject(new Error('save failed'));
            }
        });

        try {
            await harness.triggerSubmit();

            assert.equal(harness.reservationEvents.length, 0);
        } finally {
            harness.destroy();
        }
    });

    it('abort/rechazo previo a save no emite evento', async () => {
        var harness = loadSubmitHarness({
            assignmentResult: {
                mode: 'reject_service_not_offered',
                rejection: { message: 'Servicio no ofrecido' }
            },
            saveImpl: function() {
                throw new Error('saveReservation no debe llamarse');
            }
        });

        try {
            await harness.triggerSubmit();

            assert.equal(harness.reservationEvents.length, 0);
        } finally {
            harness.destroy();
        }
    });

    it('evento ocurre antes de recargar, refresh y closeModal', async () => {
        var harness = loadSubmitHarness({
            saveImpl: function() {
                return Promise.resolve({ data: { id: 55 } });
            }
        });

        try {
            await harness.triggerSubmit();

            assert.deepEqual(harness.actionLog, [
                'reservation:created',
                'recargar',
                'notifications:refresh',
                'closeModal'
            ]);
        } finally {
            harness.destroy();
        }
    });
});
