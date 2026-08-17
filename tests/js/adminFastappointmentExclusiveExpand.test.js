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

function makeClassList(initial) {
    var classes = {};
    (initial || []).forEach(function(name) {
        classes[name] = true;
    });

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
    select.insertBefore = function() {};

    return select;
}

function makeStepElement(stepId, bodyStartsHidden) {
    var attrs = {};
    var headerAttrs = {};
    var body = {
        classList: makeClassList(bodyStartsHidden ? ['hidden'] : []),
        appendChild: function() {},
        insertBefore: function() {},
        parentNode: null
    };
    var check = { classList: makeClassList(['hidden']) };
    var summary = { classList: makeClassList(['hidden']), textContent: '' };
    var header = {
        disabled: false,
        classList: makeClassList(),
        setAttribute: function(name, value) {
            headerAttrs[name] = String(value);
        },
        getAttribute: function(name) {
            return Object.prototype.hasOwnProperty.call(headerAttrs, name) ? headerAttrs[name] : null;
        },
        closest: function(selector) {
            if (selector === '[data-aa-fastappointment-step-header]') {
                return header;
            }
            if (selector === '[data-aa-fastappointment-step]') {
                return step;
            }
            return null;
        }
    };
    var step = {
        dataset: {
            aaFastappointmentStep: stepId
        },
        classList: makeClassList(),
        setAttribute: function(name, value) {
            attrs[name] = String(value);
        },
        getAttribute: function(name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
        },
        querySelector: function(selector) {
            if (selector === '[data-aa-fastappointment-step-header]') {
                return header;
            }
            if (selector === '[data-aa-fastappointment-step-check]') {
                return check;
            }
            if (selector === '[data-aa-fastappointment-step-body]') {
                return body;
            }
            if (selector === '[data-aa-fastappointment-step-summary]') {
                return summary;
            }
            return null;
        },
        querySelectorAll: function() {
            return [];
        },
        appendChild: function() {},
        insertBefore: function() {},
        contains: function() {
            return true;
        },
        _header: header,
        _body: body,
        _check: check,
        _summary: summary
    };

    return step;
}

function twoServicePrerequisites() {
    return {
        hasServices: true,
        hasUsableStaff: true,
        hasAreas: true,
        canStart: true,
        messages: [],
        activeServices: [
            { id: '2', name: 'Servicio de prueba', duration_minutes: 60 },
            { id: '22', name: 'Otro servicio', duration_minutes: 30 }
        ],
        usableStaff: [{
            id: '3',
            name: 'Personal de prueba',
            services: [
                { id: '2', name: 'Servicio de prueba' },
                { id: '22', name: 'Otro servicio' }
            ]
        }],
        activeServiceAreas: [{ id: '4', name: 'Zona de atención de prueba' }]
    };
}

function oneServicePrerequisites() {
    var base = twoServicePrerequisites();
    base.activeServices = [base.activeServices[0]];
    base.usableStaff[0].services = [base.usableStaff[0].services[0]];
    return base;
}

function loadHarness(opts) {
    var config = opts || {};
    var state = {
        selectedClientId: null,
        selectedClient: null,
        isClientStepReady: false
    };

    var formHandlers = {};
    var formEl = {
        id: 'aa-fastappointment-form',
        addEventListener: function(type, handler) {
            formHandlers[type] = handler;
        },
        removeEventListener: function() {},
        contains: function() {
            return true;
        },
        _handlers: formHandlers
    };

    var stepClient = makeStepElement('client', false);
    var stepService = makeStepElement('service', true);
    var stepDate = makeStepElement('date', true);
    var stepTime = makeStepElement('time', true);
    var stepStaff = makeStepElement('staff', true);
    var stepArea = makeStepElement('area', true);

    var clientSelect = makeSelect('aa-fastappointment-client', '', [
        { value: '', textContent: 'Selecciona un cliente', dataset: {} },
        { value: '1', textContent: 'Cliente Uno', dataset: { nombre: 'Cliente Uno' } }
    ]);
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

    var prerequisitesResult = config.prerequisites || twoServicePrerequisites();
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
            return prerequisitesPromise;
        }
    };
    context.window.FastAppointmentTimeAvailabilityService = {
        getAvailabilityByDate: function() {
            return Promise.resolve({ slots: [{ value: '10:00' }, { value: '11:00' }] });
        },
        getAllStaffWithAvailability: function() {
            return Promise.resolve({
                staff: [
                    { id: '3', name: 'Personal A', available: true },
                    { id: '8', name: 'Personal B', available: true }
                ]
            });
        },
        getAreaAvailabilityBySelection: function() {
            return Promise.resolve({
                areas: [
                    { id: '4', name: 'Zona A', occupied: false },
                    { id: '5', name: 'Zona B', occupied: false }
                ]
            });
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

    var steps = {
        client: stepClient,
        service: stepService,
        date: stepDate,
        time: stepTime,
        staff: stepStaff,
        area: stepArea
    };

    return {
        controller: controller,
        state: function() {
            return state;
        },
        steps: steps,
        selects: {
            client: clientSelect,
            service: serviceSelect,
            time: timeSelect,
            staff: staffSelect,
            area: areaSelect
        },
        submit: elements['aa-fastappointment-submit'],
        dateInput: dateInput,
        ready: controller && typeof controller.validatePrerequisites === 'function'
            ? controller.validatePrerequisites()
            : prerequisitesPromise,
        clickStep: function(stepId) {
            var step = steps[stepId];
            assert.ok(step, 'step exists: ' + stepId);
            assert.ok(formHandlers.click, 'header click handler bound');
            formHandlers.click({
                target: step._header
            });
        },
        collectExpansion: function() {
            var visibleBodies = [];
            var expandedHeaders = [];

            Object.keys(steps).forEach(function(stepId) {
                var step = steps[stepId];
                if (!step._body.classList.contains('hidden')) {
                    visibleBodies.push(stepId);
                }
                if (step._header.getAttribute('aria-expanded') === 'true') {
                    expandedHeaders.push(stepId);
                }
            });

            return {
                visibleBodies: visibleBodies,
                expandedHeaders: expandedHeaders
            };
        },
        assertExclusiveExpand: function(expectedStepId) {
            var expansion = this.collectExpansion();

            if (expectedStepId === null) {
                assert.deepEqual(expansion.visibleBodies, [], 'cero bodies visibles');
                assert.deepEqual(expansion.expandedHeaders, [], 'cero aria-expanded=true');
                return;
            }

            assert.deepEqual(expansion.visibleBodies, [expectedStepId], 'un solo body visible');
            assert.deepEqual(expansion.expandedHeaders, [expectedStepId], 'un solo aria-expanded=true');
        },
        assertBlueMatchesExpansion: function() {
            Object.keys(steps).forEach(function(stepId) {
                var step = steps[stepId];
                var expanded = step._header.getAttribute('aria-expanded') === 'true';

                assert.equal(
                    step.classList.contains('bg-indigo-50'),
                    expanded,
                    stepId + ' azul solo si está expandida'
                );
                assert.equal(
                    step.classList.contains('border-indigo-100'),
                    expanded,
                    stepId + ' borde activo solo si está expandida'
                );

                if (expanded) {
                    assert.equal(step.classList.contains('bg-white'), false, stepId + ' expandida no usa fondo cerrado');
                }
            });
        },
        assertCompletedChrome: function(stepId, visible) {
            var step = steps[stepId];
            assert.equal(step._check.classList.contains('hidden'), !visible, stepId + ' paloma');
            if (visible) {
                assert.equal(step._summary.classList.contains('hidden'), false, stepId + ' resumen visible');
                assert.ok(String(step._summary.textContent || '').length > 0, stepId + ' resumen con texto');
            } else {
                assert.equal(step._summary.classList.contains('hidden'), true, stepId + ' resumen oculto');
            }
        },
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

async function advanceToDateActive(harness) {
    harness.selects.client.value = '1';
    harness.selects.client.selectedIndex = 1;
    await harness.selects.client._handlers.change.call(harness.selects.client);
    await flushAsync();

    harness.selects.service.value = '2';
    harness.selects.service.selectedIndex = 1;
    await harness.selects.service._handlers.change.call(harness.selects.service);
    await flushAsync();

    assert.equal(harness.steps.client.dataset.visualState, 'completed');
    assert.equal(harness.steps.service.dataset.visualState, 'completed');
    assert.equal(harness.steps.date.dataset.visualState, 'active');
}

async function advanceToAreaActive(harness) {
    await advanceToDateActive(harness);

    harness.dateInput.value = '2026-08-15';
    await harness.dateInput._handlers.change.call(harness.dateInput);
    await flushAsync();
    await flushAsync();

    harness.selects.time.value = '10:00';
    harness.selects.time.selectedIndex = 1;
    await harness.selects.time._handlers.change.call(harness.selects.time);
    await flushAsync();
    await flushAsync();

    harness.selects.staff.value = '3';
    harness.selects.staff.selectedIndex = 1;
    await harness.selects.staff._handlers.change.call(harness.selects.staff);
    await flushAsync();
    await flushAsync();

    assert.equal(harness.steps.area.dataset.visualState, 'active');
}

async function completeLastStep(harness) {
    harness.selects.area.value = '4';
    harness.selects.area.selectedIndex = 1;
    await harness.selects.area._handlers.change.call(harness.selects.area);
    await flushAsync();
}

describe('adminFastappointment exclusive expand', () => {
    it('inicia en el requerido y permite toggle 0/1', async () => {
        var harness = loadHarness();

        try {
            await harness.ready;
            harness.assertExclusiveExpand('client');
            harness.assertBlueMatchesExpansion();
            assert.equal(harness.steps.client.dataset.visualState, 'active');

            harness.clickStep('client');
            harness.assertExclusiveExpand(null);
            harness.assertBlueMatchesExpansion();
            assert.equal(harness.steps.client.dataset.visualState, 'active');
            assert.equal(harness.steps.client.classList.contains('bg-white'), true);

            harness.clickStep('client');
            harness.assertExclusiveExpand('client');
            harness.assertBlueMatchesExpansion();
        } finally {
            harness.destroy();
        }
    });

    it('abrir completed pone azul ahí, conserva paloma y deja el requerido cerrado sin azul', async () => {
        var harness = loadHarness();

        try {
            await harness.ready;
            await advanceToDateActive(harness);
            harness.assertExclusiveExpand('date');
            harness.assertBlueMatchesExpansion();
            harness.assertCompletedChrome('client', true);
            harness.assertCompletedChrome('service', true);
            harness.assertCompletedChrome('date', false);

            harness.clickStep('client');
            harness.assertExclusiveExpand('client');
            harness.assertBlueMatchesExpansion();
            assert.equal(harness.steps.client.dataset.visualState, 'completed');
            assert.equal(harness.steps.date.dataset.visualState, 'active');
            assert.equal(harness.steps.date.classList.contains('bg-white'), true);
            assert.equal(harness.steps.date.classList.contains('bg-indigo-50'), false);
            harness.assertCompletedChrome('client', true);

            harness.clickStep('service');
            harness.assertExclusiveExpand('service');
            harness.assertBlueMatchesExpansion();
            harness.assertCompletedChrome('service', true);
            harness.assertCompletedChrome('client', true);
        } finally {
            harness.destroy();
        }
    });

    it('re-tocar la abierta la cierra y tocar el requerido lo vuelve a abrir', async () => {
        var harness = loadHarness();

        try {
            await harness.ready;
            await advanceToDateActive(harness);

            harness.clickStep('client');
            harness.assertExclusiveExpand('client');

            harness.clickStep('client');
            harness.assertExclusiveExpand(null);
            harness.assertBlueMatchesExpansion();

            harness.clickStep('date');
            harness.assertExclusiveExpand('date');
            harness.assertBlueMatchesExpansion();
            assert.equal(harness.steps.date.dataset.visualState, 'active');
        } finally {
            harness.destroy();
        }
    });

    it('disabled u auto-oculto no abren', async () => {
        var harnessTwo = loadHarness();
        var harnessOne = null;

        try {
            await harnessTwo.ready;
            await advanceToDateActive(harnessTwo);
            harnessTwo.assertExclusiveExpand('date');

            harnessTwo.clickStep('time');
            harnessTwo.assertExclusiveExpand('date');
            harnessTwo.assertBlueMatchesExpansion();
            assert.equal(harnessTwo.steps.time.dataset.visualState, 'disabled');

            harnessOne = loadHarness({ prerequisites: oneServicePrerequisites() });
            await harnessOne.ready;
            harnessOne.selects.client.value = '1';
            harnessOne.selects.client.selectedIndex = 1;
            await harnessOne.selects.client._handlers.change.call(harnessOne.selects.client);
            await flushAsync();
            await flushAsync();

            assert.equal(harnessOne.steps.service.classList.contains('hidden'), true);
            assert.equal(harnessOne.steps.service.dataset.visualState, 'completed');
            harnessOne.assertExclusiveExpand('date');

            harnessOne.clickStep('service');
            harnessOne.assertExclusiveExpand('date');
            harnessOne.assertBlueMatchesExpansion();
        } finally {
            harnessTwo.destroy();
            if (harnessOne) {
                harnessOne.destroy();
            }
        }
    });

    it('id → id abre el nuevo requerido aunque hubiera otra card abierta', async () => {
        var harness = loadHarness();

        try {
            await harness.ready;
            await advanceToDateActive(harness);

            harness.clickStep('client');
            harness.assertExclusiveExpand('client');

            harness.dateInput.value = '2026-08-15';
            await harness.dateInput._handlers.change.call(harness.dateInput);
            await flushAsync();
            await flushAsync();

            assert.equal(harness.steps.date.dataset.visualState, 'completed');
            assert.equal(harness.steps.time.dataset.visualState, 'active');
            harness.assertExclusiveExpand('time');
            harness.assertBlueMatchesExpansion();
            harness.assertCompletedChrome('date', true);
        } finally {
            harness.destroy();
        }
    });

    it('completar el último paso con su card abierta la deja abierta, azul y con paloma', async () => {
        var harness = loadHarness();

        try {
            await harness.ready;
            await advanceToAreaActive(harness);
            harness.assertExclusiveExpand('area');

            await completeLastStep(harness);

            assert.equal(harness.steps.area.dataset.visualState, 'completed');
            assert.equal(harness.steps.client.dataset.visualState, 'completed');
            harness.assertExclusiveExpand('area');
            harness.assertBlueMatchesExpansion();
            harness.assertCompletedChrome('area', true);
            assert.equal(harness.submit.disabled, false);
        } finally {
            harness.destroy();
        }
    });

    it('completar el último paso con todas cerradas las deja cerradas', async () => {
        var harness = loadHarness();

        try {
            await harness.ready;
            await advanceToAreaActive(harness);
            harness.clickStep('area');
            harness.assertExclusiveExpand(null);

            await completeLastStep(harness);

            assert.equal(harness.steps.area.dataset.visualState, 'completed');
            harness.assertExclusiveExpand(null);
            harness.assertBlueMatchesExpansion();
            harness.assertCompletedChrome('area', true);
            assert.equal(harness.submit.disabled, false);
        } finally {
            harness.destroy();
        }
    });

    it('invalidar desde estado terminal abre automáticamente el nuevo requerido', async () => {
        var harness = loadHarness();

        try {
            await harness.ready;
            await advanceToAreaActive(harness);
            await completeLastStep(harness);
            harness.assertExclusiveExpand('area');
            assert.equal(harness.submit.disabled, false);

            harness.selects.client.value = '';
            harness.selects.client.selectedIndex = 0;
            await harness.selects.client._handlers.change.call(harness.selects.client);
            await flushAsync();

            assert.equal(harness.steps.client.dataset.visualState, 'active');
            harness.assertExclusiveExpand('client');
            harness.assertBlueMatchesExpansion();
            harness.assertCompletedChrome('client', false);
            assert.equal(harness.submit.disabled, true);
        } finally {
            harness.destroy();
        }
    });
});
