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
    var body = {
        classList: makeClassList(),
        appendChild: function() {},
        insertBefore: function() {}
    };

    return {
        dataset: {},
        classList: makeClassList(),
        querySelector: function(selector) {
            if (selector === '[data-aa-fastappointment-step-header]') {
                return {
                    disabled: false,
                    setAttribute: function() {},
                    classList: makeClassList()
                };
            }

            if (selector === '[data-aa-fastappointment-step-check]') {
                return { classList: makeClassList() };
            }

            if (selector === '[data-aa-fastappointment-step-body]') {
                return body;
            }

            if (selector === '[data-aa-fastappointment-step-summary]') {
                return { classList: makeClassList(), textContent: '' };
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

function makeSelect(id, value, options) {
    var handlers = {};

    return {
        id: id,
        value: value,
        selectedIndex: value ? 1 : 0,
        options: options,
        disabled: false,
        classList: makeClassList(),
        innerHTML: '',
        addEventListener: function(type, handler) {
            handlers[type] = handler;
        },
        removeEventListener: function() {},
        dispatchEvent: function(event) {
            if (event && event.type && handlers[event.type]) {
                return handlers[event.type].call(this);
            }
            return true;
        },
        _handlers: handlers
    };
}

function loadConfirmPersistenceHarness() {
    var state = {
        selectedClientId: '1',
        selectedServiceId: '2',
        selectedDate: '2026-07-05',
        selectedTime: '10:00',
        selectedStaffId: '3',
        isSelectedStaffAvailable: true,
        selectedAreaId: '4',
        isSelectedAreaAvailable: true,
        isClientStepReady: true,
        canStartFastAppointment: true,
        selectedClient: {
            nombre: 'Cliente',
            telefono: '5551234',
            correo: ''
        },
        fastAppointmentPrerequisites: {
            activeServices: [{ id: '2', duration_minutes: 60 }]
        }
    };

    var confirmCheckbox = {
        id: 'aa-fastappointment-confirm',
        checked: true,
        value: 'confirmed'
    };

    var clientSelect = makeSelect('aa-fastappointment-client', '1', [
        { value: '', textContent: '' },
        {
            value: '1',
            textContent: 'Cliente',
            dataset: { nombre: 'Cliente', telefono: '5551234', correo: '' }
        }
    ]);
    clientSelect.insertBefore = function() {};

    var serviceSelect = makeSelect('aa-fastappointment-service', '2', [
        { value: '', textContent: '' },
        { value: '2', textContent: 'Servicio' }
    ]);

    var timeSelect = makeSelect('aa-fastappointment-time', '10:00', [
        { value: '' },
        { value: '10:00' }
    ]);

    var staffSelect = makeSelect('aa-fastappointment-staff', '3', [
        { value: '' },
        { value: '3', textContent: 'Staff', dataset: { available: '1' } }
    ]);

    var areaSelect = makeSelect('aa-fastappointment-area', '4', [
        { value: '' },
        { value: '4', textContent: 'Zona', dataset: { available: '1' } }
    ]);

    var dateInput = {
        id: 'aa-fastappointment-date',
        value: '2026-07-05',
        type: 'text',
        setAttribute: function() {},
        addEventListener: function(type, handler) {
            dateInput._handlers = dateInput._handlers || {};
            dateInput._handlers[type] = handler;
        },
        removeEventListener: function() {},
        _handlers: {}
    };

    var formEl = {
        id: 'aa-fastappointment-form',
        addEventListener: function() {},
        removeEventListener: function() {},
        contains: function() {
            return true;
        }
    };

    var stepClient = makeStepElement();
    var stepDate = makeStepElement();
    var stepTime = makeStepElement();
    var stepService = makeStepElement();
    var stepStaff = makeStepElement();
    var stepArea = makeStepElement();

    var elements = {
        'aa-fastappointment-form': formEl,
        'aa-fastappointment-submit': {
            id: 'aa-fastappointment-submit',
            disabled: false,
            textContent: 'Agendar cita',
            classList: makeClassList()
        },
        'aa-fastappointment-step-service': stepService,
        'aa-fastappointment-step-staff': stepStaff,
        'aa-fastappointment-step-area': stepArea,
        'aa-fastappointment-client': clientSelect,
        'aa-fastappointment-date': dateInput,
        'aa-fastappointment-time': timeSelect,
        'aa-fastappointment-service': serviceSelect,
        'aa-fastappointment-staff': staffSelect,
        'aa-fastappointment-area': areaSelect,
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
        'aa-fastappointment-confirm': confirmCheckbox,
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
            dispatchEvent: function() {
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
    var prerequisitesResult = {
        hasServices: true,
        hasUsableStaff: true,
        hasAreas: true,
        canStart: false,
        messages: [],
        activeServices: [{ id: '2', duration_minutes: 60 }]
    };
    var prerequisitesPromise = Promise.resolve(prerequisitesResult);

    context.window.FastAppointmentPrerequisitesService = {
        evaluate: function() {
            return prerequisitesPromise;
        }
    };
    context.window.FastAppointmentTimeAvailabilityService = {
        getAvailabilityByDate: function() {
            return Promise.resolve({ slots: [] });
        },
        getAllStaffWithAvailability: function() {
            return Promise.resolve({ staff: [] });
        },
        getAreaAvailabilityBySelection: function() {
            return Promise.resolve({ areas: [] });
        }
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
    assert.ok(serviceSelect._handlers.change, 'handler de servicio');
    assert.ok(timeSelect._handlers.change, 'handler de hora');
    assert.ok(staffSelect._handlers.change, 'handler de personal');
    assert.ok(dateInput._handlers.change, 'handler de fecha');

    return {
        confirmCheckbox: confirmCheckbox,
        ready: prerequisitesPromise.then(function() {
            return null;
        }),
        runPartialResets: async function() {
            serviceSelect.value = '2';
            serviceSelect._handlers.change.call(serviceSelect);

            dateInput.value = '2026-07-06';
            await dateInput._handlers.change.call(dateInput);

            timeSelect.value = '11:00';
            timeSelect.selectedIndex = 1;
            await timeSelect._handlers.change.call(timeSelect);

            staffSelect.value = '3';
            staffSelect.selectedIndex = 1;
            staffSelect.options = [
                { value: '' },
                { value: '3', textContent: 'Staff', dataset: { available: '1' } }
            ];
            await staffSelect._handlers.change.call(staffSelect);
        },
        destroy: function() {
            if (controller && typeof controller.destroy === 'function') {
                controller.destroy();
            }
        }
    };
}

describe('adminFastappointmentConfirmCheckboxPersistence', () => {
    it('resets parciales no desmarcan checkbox marcado', async () => {
        var harness = loadConfirmPersistenceHarness();

        try {
            await harness.ready;
            harness.confirmCheckbox.checked = true;
            await harness.runPartialResets();
            assert.equal(harness.confirmCheckbox.checked, true);
        } finally {
            harness.destroy();
        }
    });

    it('resets parciales conservan checkbox desmarcado por el usuario', async () => {
        var harness = loadConfirmPersistenceHarness();

        try {
            await harness.ready;
            harness.confirmCheckbox.checked = false;
            await harness.runPartialResets();
            assert.equal(harness.confirmCheckbox.checked, false);
        } finally {
            harness.destroy();
        }
    });
});
