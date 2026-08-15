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
    var classes = {};

    return {
        add: function() {
            for (var i = 0; i < arguments.length; i++) {
                classes[arguments[i]] = true;
            }
        },
        remove: function() {
            for (var i = 0; i < arguments.length; i++) {
                delete classes[arguments[i]];
            }
        },
        toggle: function(name, force) {
            if (typeof force === 'boolean') {
                if (force) {
                    classes[name] = true;
                } else {
                    delete classes[name];
                }
                return force;
            }

            if (classes[name]) {
                delete classes[name];
                return false;
            }

            classes[name] = true;
            return true;
        },
        contains: function(name) {
            return !!classes[name];
        }
    };
}

function makeStepElement() {
    var attrs = {};
    var body = {
        classList: makeClassList(),
        appendChild: function() {},
        insertBefore: function() {}
    };

    return {
        dataset: {},
        classList: makeClassList(),
        setAttribute: function(name, value) {
            attrs[name] = String(value);
        },
        getAttribute: function(name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
        },
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

function parseSelectOptions(html) {
    var options = [];
    var regex = /<option value="([^"]*)"([^>]*)>/g;
    var match;

    while ((match = regex.exec(String(html || ''))) !== null) {
        var attrs = match[2] || '';
        options.push({
            value: match[1],
            textContent: match[1],
            dataset: {
                available: attrs.indexOf('data-available="0"') !== -1 ? '0' : '1',
                occupied: attrs.indexOf('data-occupied="1"') !== -1 ? '1' : '0',
                reason: ''
            }
        });
    }

    return options;
}

function makeSelect(id, value, options) {
    var handlers = {};
    var currentOptions = (options || []).slice();
    var currentValue = value || '';
    var html = '';

    var select = {
        id: id,
        disabled: false,
        classList: makeClassList(),
        addEventListener: function(type, handler) {
            handlers[type] = handler;
        },
        removeEventListener: function() {},
        dispatchEvent: function(event) {
            if (event && event.type && handlers[event.type]) {
                return handlers[event.type].call(select);
            }
            return true;
        },
        _handlers: handlers
    };

    Object.defineProperty(select, 'options', {
        get: function() {
            return currentOptions;
        },
        set: function(next) {
            currentOptions = next || [];
        }
    });

    Object.defineProperty(select, 'value', {
        get: function() {
            return currentValue;
        },
        set: function(next) {
            currentValue = next === null || typeof next === 'undefined' ? '' : String(next);
            var index = 0;
            currentOptions.forEach(function(option, optionIndex) {
                if (String(option.value) === currentValue) {
                    index = optionIndex;
                }
            });
            select.selectedIndex = currentValue ? index : 0;
        }
    });

    Object.defineProperty(select, 'innerHTML', {
        get: function() {
            return html;
        },
        set: function(nextHtml) {
            html = String(nextHtml || '');
            currentOptions = parseSelectOptions(html);
            select.selectedIndex = 0;
            currentValue = currentOptions[0] ? currentOptions[0].value : '';
        }
    });

    select.selectedIndex = currentValue ? 1 : 0;

    return select;
}

function oneServicePrerequisites() {
    return {
        hasServices: true,
        hasUsableStaff: true,
        hasAreas: true,
        canStart: true,
        messages: [],
        activeServices: [{ id: '2', name: 'Servicio de prueba', duration_minutes: 60 }],
        usableStaff: [{
            id: '3',
            name: 'Personal de prueba',
            services: [{ id: '2', name: 'Servicio de prueba' }]
        }],
        activeServiceAreas: [{ id: '4', name: 'Zona de atención de prueba' }]
    };
}

function twoServicePrerequisites() {
    var base = oneServicePrerequisites();
    base.activeServices.push({ id: '22', name: 'Otro servicio', duration_minutes: 30 });
    base.usableStaff[0].services.push({ id: '22', name: 'Otro servicio' });
    return base;
}

function loadHarness(opts) {
    var config = opts || {};
    var state = {
        selectedClientId: null,
        selectedClient: null,
        isClientStepReady: false
    };

    var clientSelect = makeSelect('aa-fastappointment-client', '', [
        { value: '', textContent: 'Selecciona un cliente', dataset: {} }
    ]);
    clientSelect.insertBefore = function() {};

    var serviceSelect = makeSelect('aa-fastappointment-service', '', [
        { value: '', textContent: 'Selecciona un servicio', dataset: {} }
    ]);
    var timeSelect = makeSelect('aa-fastappointment-time', '', [
        { value: '', textContent: 'Horario de la cita', dataset: {} }
    ]);
    var staffSelect = makeSelect('aa-fastappointment-staff', '', [
        { value: '', textContent: 'Personal que atenderá la cita', dataset: {} }
    ]);
    var areaSelect = makeSelect('aa-fastappointment-area', '', [
        { value: '', textContent: 'Zona donde se realizará la cita', dataset: {} }
    ]);

    var dateInput = {
        id: 'aa-fastappointment-date',
        value: '',
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
            disabled: true,
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
        'aa-fastappointment-confirm': {
            id: 'aa-fastappointment-confirm',
            checked: false,
            value: 'confirmed'
        },
        'aa-fastappointment-client-search': { id: 'aa-fastappointment-client-search', value: '' },
        'aa-fastappointment-client-create': { id: 'aa-fastappointment-client-create' },
        'aa-fastappointment-client-inline': { id: 'aa-fastappointment-client-inline' }
    };

    var staffResult = typeof config.staffResult === 'function'
        ? config.staffResult
        : function() {
            return { staff: [] };
        };
    var areaResult = typeof config.areaResult === 'function'
        ? config.areaResult
        : function() {
            return { areas: [] };
        };

    var prerequisitesResult = config.prerequisites || oneServicePrerequisites();
    var prerequisitesPromise = Promise.resolve(prerequisitesResult);

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
        Map: Map,
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
    context.window.ReservationClientController = {
        init: function() {
            return { destroy: function() {} };
        }
    };
    context.window.FastAppointmentPrerequisitesService = {
        evaluate: function() {
            return prerequisitesPromise;
        }
    };
    context.window.FastAppointmentTimeAvailabilityService = {
        getAvailabilityByDate: function() {
            return Promise.resolve({ slots: [{ value: '10:00' }] });
        },
        getAllStaffWithAvailability: function() {
            return Promise.resolve(staffResult());
        },
        getAreaAvailabilityBySelection: function() {
            return Promise.resolve(areaResult());
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

    return {
        controller: controller,
        state: function() {
            return state;
        },
        steps: {
            client: stepClient,
            service: stepService,
            staff: stepStaff,
            area: stepArea
        },
        selects: {
            service: serviceSelect,
            time: timeSelect,
            staff: staffSelect,
            area: areaSelect
        },
        dateInput: dateInput,
        ready: controller && typeof controller.validatePrerequisites === 'function'
            ? controller.validatePrerequisites()
            : prerequisitesPromise,
        destroy: function() {
            if (controller && typeof controller.destroy === 'function') {
                controller.destroy();
            }
        }
    };
}

function flushAsync() {
    return new Promise(function(resolve) {
        setImmediate(resolve);
    });
}

function assertCollapsed(step, collapsed) {
    assert.equal(step.classList.contains('hidden'), collapsed);
    assert.equal(step.getAttribute('aria-hidden'), collapsed ? 'true' : 'false');
}

describe('adminFastappointment auto-resolved step collapse', () => {
    it('oculta Servicio cuando hay una sola opción y la autoselección queda en estado, y no toca Cliente', async () => {
        var harness = loadHarness({ prerequisites: oneServicePrerequisites() });

        try {
            await harness.ready;
            assert.equal(harness.state().selectedServiceId, '2');
            assertCollapsed(harness.steps.service, true);
            assert.equal(harness.steps.client.classList.contains('hidden'), false);
        } finally {
            harness.destroy();
        }
    });

    it('muestra Servicio cuando hay más de una opción válida', async () => {
        var harness = loadHarness({ prerequisites: twoServicePrerequisites() });

        try {
            await harness.ready;
            assert.equal(harness.state().selectedServiceId || null, null);
            assertCollapsed(harness.steps.service, false);
        } finally {
            harness.destroy();
        }
    });

    it('oculta Personal y Zona con una opción válida, y los reaparece al resetear la hora', async () => {
        var staffCalls = 0;
        var harness = loadHarness({
            prerequisites: oneServicePrerequisites(),
            staffResult: function() {
                staffCalls += 1;
                if (staffCalls === 1) {
                    return {
                        staff: [
                            { id: '3', name: 'Personal de prueba', available: true },
                            { id: '9', name: 'Ocupado', available: false }
                        ]
                    };
                }

                return {
                    staff: [
                        { id: '3', name: 'Personal de prueba', available: true },
                        { id: '8', name: 'Otro personal', available: true }
                    ]
                };
            },
            areaResult: function() {
                return {
                    areas: [
                        { id: '4', name: 'Zona de atención de prueba', occupied: false },
                        { id: '5', name: 'Ocupada', occupied: true }
                    ]
                };
            }
        });

        try {
            await harness.ready;

            harness.dateInput.value = '2026-08-15';
            await harness.dateInput._handlers.change.call(harness.dateInput);

            harness.selects.time.value = '10:00';
            harness.selects.time.selectedIndex = 1;
            await harness.selects.time._handlers.change.call(harness.selects.time);
            await flushAsync();
            await flushAsync();

            assert.equal(harness.state().selectedStaffId, '3');
            assert.equal(harness.state().selectedAreaId, '4');
            assertCollapsed(harness.steps.staff, true);
            assertCollapsed(harness.steps.area, true);

            harness.selects.time.value = '11:00';
            harness.selects.time.selectedIndex = 1;
            var resetPromise = harness.selects.time._handlers.change.call(harness.selects.time);
            assertCollapsed(harness.steps.staff, false);
            assertCollapsed(harness.steps.area, false);
            await resetPromise;
            await flushAsync();
            await flushAsync();

            assert.equal(harness.state().selectedStaffId || null, null);
            assertCollapsed(harness.steps.staff, false);
            assert.equal(harness.steps.client.classList.contains('hidden'), false);
        } finally {
            harness.destroy();
        }
    });

    it('no oculta Personal cuando no hay opciones válidas', async () => {
        var harness = loadHarness({
            prerequisites: oneServicePrerequisites(),
            staffResult: function() {
                return {
                    staff: [
                        { id: '3', name: 'Personal de prueba', available: false }
                    ]
                };
            }
        });

        try {
            await harness.ready;
            harness.dateInput.value = '2026-08-15';
            await harness.dateInput._handlers.change.call(harness.dateInput);
            harness.selects.time.value = '10:00';
            await harness.selects.time._handlers.change.call(harness.selects.time);

            assert.equal(harness.state().selectedStaffId || null, null);
            assertCollapsed(harness.steps.staff, false);
        } finally {
            harness.destroy();
        }
    });
});
