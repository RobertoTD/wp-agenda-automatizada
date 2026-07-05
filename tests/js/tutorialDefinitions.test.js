'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const definitionsPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialDefinitions.js'
);
const definitionsSrc = fs.readFileSync(definitionsPath, 'utf8');

function loadDefinitions() {
    var context = { window: {} };
    context.window = context;
    vm.runInNewContext(definitionsSrc, context, { filename: definitionsPath });
    return context.window.TutorialDefinitions;
}

describe('TutorialDefinitions MC3D', () => {
    let api;

    beforeEach(() => {
        api = loadDefinitions();
    });

    it('expone create_test_appointment_v1', () => {
        assert.equal(api.TUTORIAL_ID, 'create_test_appointment_v1');
        assert.deepEqual([].concat(api.list()), ['create_test_appointment_v1']);

        var def = api.get('create_test_appointment_v1');
        assert.ok(def);
        assert.equal(def.tutorialId, 'create_test_appointment_v1');
        assert.equal(def.flowId, 'create_test_appointment_v1');
    });

    it('getConfig devuelve flowId e initialStepId', () => {
        var config = api.getConfig('create_test_appointment_v1');

        assert.ok(config);
        assert.equal(config.flowId, 'create_test_appointment_v1');
        assert.equal(config.initialStepId, 'intro');
        assert.equal(config.steps.length, 7);
    });

    it('getConfig respeta initialStepId override', () => {
        var config = api.getConfig('create_test_appointment_v1', {
            initialStepId: 'calendar_overview'
        });

        assert.equal(config.initialStepId, 'calendar_overview');
    });

    it('steps implementados en orden MC3D+A', () => {
        var config = api.getConfig('create_test_appointment_v1');
        var ids = config.steps.map(function (step) { return step.id; });

        assert.deepEqual([].concat(ids), [
            'intro',
            'open_sidebar',
            'open_calendar',
            'calendar_overview',
            'resume_open_sidebar',
            'resume_navigate_calendar',
            'resume_create_test_appointment_fab'
        ]);
    });

    it('open_sidebar usa target_click con navigation none', () => {
        var config = api.getConfig('create_test_appointment_v1');
        var step = config.steps.find(function (s) { return s.id === 'open_sidebar'; });

        assert.equal(step.target, '#aa-btn-sidebar');
        assert.equal(step.advance.mode, 'target_click');
        assert.equal(step.advance.navigation, 'none');
        assert.equal(step.beforeAdvanceAction, 'aa_tutorial_persist_open_calendar');
    });

    it('open_calendar usa target_click con navigation follow_target', () => {
        var config = api.getConfig('create_test_appointment_v1');
        var step = config.steps.find(function (s) { return s.id === 'open_calendar'; });

        assert.equal(step.target, '[data-aa-nav-module="calendar"]');
        assert.equal(step.advance.mode, 'target_click');
        assert.equal(step.advance.navigation, 'follow_target');
        assert.equal(step.beforeAdvanceAction, 'aa_tutorial_persist_calendar_overview');
        assert.ok(step.waitFor);
    });

    it('resume steps son visual-only y no están en durableStepIds', () => {
        var def = api.get('create_test_appointment_v1');
        var config = api.getConfig('create_test_appointment_v1');
        var resumeIds = [
            'resume_open_sidebar',
            'resume_navigate_calendar',
            'resume_create_test_appointment_fab'
        ];

        resumeIds.forEach(function (id) {
            assert.equal(def.durableStepIds.indexOf(id), -1, id);
            assert.equal(def.implementedStepIds.indexOf(id), -1, id);
            assert.ok(config.steps.find(function (step) { return step.id === id; }), id);
        });
    });

    it('resume_open_sidebar encadena resume_navigate_calendar sin gate durable', () => {
        var config = api.getConfig('create_test_appointment_v1');
        var step = config.steps.find(function (s) { return s.id === 'resume_open_sidebar'; });

        assert.equal(step.target, '#aa-btn-sidebar');
        assert.equal(step.advance.mode, 'target_click');
        assert.equal(step.advance.navigation, 'none');
        assert.equal(step.nextStepId, 'resume_navigate_calendar');
        assert.equal(step.beforeAdvanceAction, undefined);
    });

    it('resume_navigate_calendar usa follow_target sin gate durable', () => {
        var config = api.getConfig('create_test_appointment_v1');
        var step = config.steps.find(function (s) { return s.id === 'resume_navigate_calendar'; });

        assert.equal(step.target, '[data-aa-nav-module="calendar"]');
        assert.equal(step.advance.mode, 'target_click');
        assert.equal(step.advance.navigation, 'follow_target');
        assert.equal(step.beforeAdvanceAction, undefined);
        assert.equal(step.beforeAction, 'aa_tutorial_ensure_sidebar_interactable');
        assert.ok(step.waitFor);
    });

    it('resume_create_test_appointment_fab usa dismiss visual-only', () => {
        var def = api.get('create_test_appointment_v1');
        var config = api.getConfig('create_test_appointment_v1');
        var step = config.steps.find(function (s) { return s.id === 'resume_create_test_appointment_fab'; });

        assert.equal(step.target, '#aa-btn-open-fastappointment-modal');
        assert.equal(step.beforeAdvanceAction, def.actions.dismissVisualOnly);
        assert.equal(step.advance.navigation, 'none');
        assert.equal(step.nextStepId, undefined);
    });

    it('calendar_overview es step terminal con coach mark en FAB', () => {
        var def = api.get('create_test_appointment_v1');
        var config = api.getConfig('create_test_appointment_v1');
        var step = config.steps.find(function (s) { return s.id === 'calendar_overview'; });

        assert.equal(step.title, 'Esta es tu Agenda');
        assert.equal(step.target, '#aa-btn-open-fastappointment-modal');
        assert.equal(step.placement, 'left');
        assert.equal(step.advance.mode, 'target_click');
        assert.equal(step.advance.navigation, 'none');
        assert.equal(step.beforeAdvanceAction, 'aa_tutorial_persist_create_test_appointment');
        assert.ok(step.waitFor);
        assert.equal(step.nextStepId, undefined);
        assert.equal(def.terminalImplementedStepId, 'calendar_overview');
        assert.equal(def.implementedStepIds.indexOf('open_fastappointment'), -1);
        assert.notEqual(def.durableStepIds.indexOf('create_test_appointment'), -1);
    });

    it('getConfig clona steps sin mutar la definición base', () => {
        var first = api.getConfig('create_test_appointment_v1');
        first.steps[0].title = 'mutado';

        var second = api.getConfig('create_test_appointment_v1');
        assert.notEqual(first.steps[0], second.steps[0]);
        assert.equal(second.steps[0].title, 'Crea tu primera cita de prueba');
    });
});
