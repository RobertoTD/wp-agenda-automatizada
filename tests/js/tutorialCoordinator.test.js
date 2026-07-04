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
const coordinatorPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialCoordinator.js'
);

const definitionsSrc = fs.readFileSync(definitionsPath, 'utf8');
const coordinatorSrc = fs.readFileSync(coordinatorPath, 'utf8');

function flushMicrotasks() {
    return new Promise(function (resolve) {
        setImmediate(resolve);
    });
}

function makeSessionStorage() {
    var store = {};

    return {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null;
        },
        setItem: function (key, value) {
            store[key] = String(value);
        },
        removeItem: function (key) {
            delete store[key];
        },
        dump: function () {
            return Object.assign({}, store);
        }
    };
}

function loadCoordinator(options) {
    var opts = options || {};
    var actionHandlers = {};
    var actionRegisterCalls = 0;
    var clearCalls = 0;
    var destroyCalls = 0;
    var startCalls = [];
    var transitionCalls = [];
    var fetchCalls = 0;
    var pauseCalls = 0;

    var tutorialRuntime = {
        status: 'idle',
        currentStepId: null,
        destroy: function () {
            destroyCalls++;
            this.status = 'idle';
            this.currentStepId = null;
        },
        start: function (config) {
            startCalls.push(config);
            this.status = 'active';
            this.currentStepId = config.initialStepId;
            return true;
        },
        pause: function () {
            pauseCalls++;
            this.status = 'paused';
        }
    };

    var context = {
        window: {},
        console: {
            warn: function () {},
            error: function () {}
        }
    };

    context.window = context;
    context.window.AA_ADMIN_CONTEXT = { blogId: opts.blogId || 44 };
    context.window.sessionStorage = opts.sessionStorage || makeSessionStorage();
    context.window.AATutorialSession = {
        resolveBlogId: function () {
            return String(context.window.AA_ADMIN_CONTEXT.blogId);
        },
        buildKey: function (blogId, flowId) {
            return 'aa_tutorial_session_v1:' + blogId + ':' + flowId;
        },
        clear: function (blogId, flowId) {
            clearCalls++;
            var key = 'aa_tutorial_session_v1:' + blogId + ':' + flowId;
            context.window.sessionStorage.removeItem(key);
        }
    };
    context.window.AATutorial = tutorialRuntime;
    context.window.AATutorialActions = {
        register: function (name, handler) {
            actionRegisterCalls++;
            actionHandlers[name] = handler;
        },
        has: function (name) {
            return Object.prototype.hasOwnProperty.call(actionHandlers, name);
        },
        run: function (name, ctx) {
            return actionHandlers[name] ? actionHandlers[name](ctx) : undefined;
        }
    };
    context.window.TutorialStateService = {
        fetchState: function () {
            fetchCalls++;
            if (typeof opts.fetchState === 'function') {
                return opts.fetchState();
            }
            return Promise.resolve(opts.state || { version: 1, tutorials: {} });
        },
        transition: opts.transition || function (input) {
            transitionCalls.push(input);
            if (opts.transitionImpl) {
                return opts.transitionImpl(input);
            }
            return Promise.resolve(opts.stateAfterTransition || { version: 1, tutorials: {} });
        }
    };

    vm.runInNewContext(definitionsSrc, context, { filename: definitionsPath });
    vm.runInNewContext(coordinatorSrc, context, { filename: coordinatorPath });

    return {
        TutorialCoordinator: context.window.TutorialCoordinator,
        TutorialDefinitions: context.window.TutorialDefinitions,
        AATutorial: tutorialRuntime,
        actionHandlers: actionHandlers,
        metrics: {
            get actionRegisterCalls() { return actionRegisterCalls; },
            get clearCalls() { return clearCalls; },
            get destroyCalls() { return destroyCalls; },
            get startCalls() { return startCalls; },
            get transitionCalls() { return transitionCalls; },
            get fetchCalls() { return fetchCalls; },
            get pauseCalls() { return pauseCalls; }
        },
        sessionStorage: context.window.sessionStorage
    };
}

describe('TutorialCoordinator MC3D', () => {
    it('available inicia en intro', async () => {
        var env = loadCoordinator({
            state: { version: 1, tutorials: {} }
        });

        var started = await env.TutorialCoordinator.init();
        assert.equal(started, true);
        assert.equal(env.metrics.startCalls.length, 1);
        assert.equal(env.metrics.startCalls[0].initialStepId, 'intro');
        assert.equal(env.metrics.clearCalls, 1);
    });

    it('accept persiste open_sidebar antes de avanzar', async () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        var result = await env.actionHandlers.aa_tutorial_accept_create_test_appointment({});
        assert.ok(result);
        assert.equal(env.metrics.transitionCalls.length, 1);
        assert.equal(env.metrics.transitionCalls[0].tutorialId, 'create_test_appointment_v1');
        assert.equal(env.metrics.transitionCalls[0].status, 'in_progress');
        assert.equal(env.metrics.transitionCalls[0].currentStepId, 'open_sidebar');
    });

    it('fallo al aceptar no avanza via motor', async () => {
        var env = loadCoordinator({
            transitionImpl: function () {
                return Promise.reject(new Error('network'));
            }
        });
        env.TutorialCoordinator.registerActions();

        await assert.rejects(
            function () {
                return env.actionHandlers.aa_tutorial_accept_create_test_appointment({});
            },
            /network/
        );
    });

    it('open_sidebar persiste open_calendar', async () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        await env.actionHandlers.aa_tutorial_persist_open_calendar({});
        assert.equal(env.metrics.transitionCalls[0].currentStepId, 'open_calendar');
    });

    it('open_calendar persiste calendar_overview', async () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        await env.actionHandlers.aa_tutorial_persist_calendar_overview({});
        assert.equal(env.metrics.transitionCalls[0].currentStepId, 'calendar_overview');
    });

    it('reanuda desde in_progress/current_step_id calendar_overview', async () => {
        var env = loadCoordinator({
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        current_step_id: 'calendar_overview'
                    }
                }
            }
        });

        await env.TutorialCoordinator.init();
        assert.equal(env.metrics.startCalls[0].initialStepId, 'calendar_overview');
    });

    it('reanuda paused en current_step_id', async () => {
        var env = loadCoordinator({
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'paused',
                        current_step_id: 'open_sidebar'
                    }
                }
            }
        });

        await env.TutorialCoordinator.init();
        assert.equal(env.metrics.startCalls[0].initialStepId, 'open_sidebar');
    });

    it('completed no inicia', async () => {
        var env = loadCoordinator({
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'completed',
                        current_step_id: null
                    }
                }
            }
        });

        var started = await env.TutorialCoordinator.init();
        assert.equal(started, false);
        assert.equal(env.metrics.startCalls.length, 0);
    });

    it('error backend no bloquea la app', async () => {
        var env = loadCoordinator({
            fetchState: function () {
                return Promise.reject(new Error('backend down'));
            }
        });

        var started = await env.TutorialCoordinator.init();
        assert.equal(started, false);
        assert.equal(env.metrics.startCalls.length, 0);
    });

    it('sessionStorage nunca gana al backend', async () => {
        var sessionStorage = makeSessionStorage();
        var key = 'aa_tutorial_session_v1:44:create_test_appointment_v1';

        sessionStorage.setItem(key, JSON.stringify({
            version: 1,
            flowId: 'create_test_appointment_v1',
            currentStepId: 'open_calendar',
            status: 'active',
            updatedAt: 1
        }));

        var env = loadCoordinator({
            sessionStorage: sessionStorage,
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        current_step_id: 'calendar_overview'
                    }
                }
            }
        });

        await env.TutorialCoordinator.init();

        assert.equal(env.metrics.clearCalls, 1);
        assert.equal(sessionStorage.getItem(key), null);
        assert.equal(env.metrics.startCalls[0].initialStepId, 'calendar_overview');
    });

    it('calendar_overview pausa durablemente y bloquea completion', async () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        var result = await env.actionHandlers.aa_tutorial_pause_mc3d_boundary({
            tutorial: env.AATutorial
        });

        assert.equal(result, false);
        assert.equal(env.metrics.pauseCalls, 1);
        assert.equal(env.metrics.transitionCalls[0].status, 'paused');
        assert.equal(env.metrics.transitionCalls[0].currentStepId, 'calendar_overview');
    });

    it('registerActions es idempotente', () => {
        var env = loadCoordinator();

        env.TutorialCoordinator.registerActions();
        var firstCount = Object.keys(env.actionHandlers).length;
        var firstRegisterCalls = env.metrics.actionRegisterCalls;

        env.TutorialCoordinator.registerActions();
        assert.equal(Object.keys(env.actionHandlers).length, firstCount);
        assert.equal(env.metrics.actionRegisterCalls, firstRegisterCalls);
    });

    it('init es idempotente en vuelo', async () => {
        var resolveFetch;
        var env = loadCoordinator({
            fetchState: function () {
                return new Promise(function (resolve) {
                    resolveFetch = resolve;
                });
            }
        });

        var first = env.TutorialCoordinator.init();
        var second = env.TutorialCoordinator.init();

        assert.strictEqual(first, second);

        await flushMicrotasks();
        assert.equal(typeof resolveFetch, 'function');

        resolveFetch({ version: 1, tutorials: {} });
        await first;
        await second;

        assert.equal(env.metrics.fetchCalls, 1);
    });

    it('init repetido destruye runtime previo sin duplicar fetch concurrente', async () => {
        var env = loadCoordinator({
            state: { version: 1, tutorials: {} }
        });

        await env.TutorialCoordinator.init();
        var destroyAfterFirst = env.metrics.destroyCalls;

        await env.TutorialCoordinator.init();
        assert.equal(env.metrics.destroyCalls, destroyAfterFirst + 1);
        assert.equal(env.metrics.startCalls.length, 2);
        assert.equal(env.metrics.fetchCalls, 2);
    });

    it('no usa currentStepId camelCase del backend', async () => {
        var env = loadCoordinator({
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        currentStepId: 'open_calendar',
                        current_step_id: 'calendar_overview'
                    }
                }
            }
        });

        await env.TutorialCoordinator.init();
        assert.equal(env.metrics.startCalls[0].initialStepId, 'calendar_overview');
    });

    it('paso durable no implementado no inicia', async () => {
        var env = loadCoordinator({
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        current_step_id: 'create_test_appointment'
                    }
                }
            }
        });

        var started = await env.TutorialCoordinator.init();
        assert.equal(started, false);
        assert.equal(env.metrics.startCalls.length, 0);
    });
});

describe('TutorialCoordinator wiring guardrails', () => {
    it('coordinator source no auto-inicia en DOMContentLoaded', () => {
        assert.equal(coordinatorSrc.includes('DOMContentLoaded'), false);
        assert.equal(coordinatorSrc.includes("addEventListener('DOMContentLoaded'"), false);
        assert.equal(coordinatorSrc.includes('fetchState'), true);
    });

    it('layout carga definitions/coordinator y mantiene onboarding legacy', () => {
        var layoutSrc = fs.readFileSync(
            path.join(__dirname, '../../includes/admin/ui/shared/layout.php'),
            'utf8'
        );

        var stateServicePos = layoutSrc.indexOf('tutorialStateService.js');
        var definitionsPos = layoutSrc.indexOf('tutorialDefinitions.js');
        var coordinatorPos = layoutSrc.indexOf('tutorialCoordinator.js');
        var welcomePos = layoutSrc.indexOf('onboardingWelcome.js');
        var activationPos = layoutSrc.indexOf('onboardingActivationCoordinator.js');

        assert.ok(stateServicePos !== -1);
        assert.ok(definitionsPos > stateServicePos);
        assert.ok(coordinatorPos > definitionsPos);
        assert.ok(welcomePos !== -1);
        assert.ok(activationPos !== -1);
    });
});
