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
const contextPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialFastAppointmentContext.js'
);
const coordinatorPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialCoordinator.js'
);
const tutorialPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorial.js'
);

const definitionsSrc = fs.readFileSync(definitionsPath, 'utf8');
const contextSrc = fs.readFileSync(contextPath, 'utf8');
const coordinatorSrc = fs.readFileSync(coordinatorPath, 'utf8');
const tutorialSrc = fs.readFileSync(tutorialPath, 'utf8');

const FAST_APPOINTMENT_CONTEXT = {
    tutorialId: 'create_test_appointment_v1',
    stepId: 'create_test_appointment',
    source: 'tutorial'
};

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
    var reconcileCalls = 0;
    var warnMessages = [];
    var pauseCalls = 0;
    var completionCardShowCalls = 0;
    var completionCardDismissCalls = 0;
    var contextClearedBeforeShow = null;

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
        },
        getState: function () {
            return {
                flowId: 'create_test_appointment_v1',
                currentStepId: this.currentStepId,
                status: this.status,
                hasRoot: false
            };
        }
    };

    var documentEvents = {};

    var context = {
        window: {},
        document: {
            addEventListener: function (type, handler) {
                if (!documentEvents[type]) {
                    documentEvents[type] = [];
                }
                documentEvents[type].push(handler);
            },
            removeEventListener: function () {},
            dispatchEvent: function (event) {
                var handlers = documentEvents[event.type] || [];
                handlers.forEach(function (handler) {
                    handler(event);
                });
                return true;
            }
        },
        console: {
            warn: function (message) {
                warnMessages.push(String(message));
            },
            error: function () {}
        }
    };

    context.window = context;
    context.window.AA_ADMIN_CONTEXT = {
        blogId: opts.blogId || 44,
        currentModule: opts.currentModule
    };
    if (opts.sidebarOpen === true) {
        context.window.AAAdmin = { Sidebar: { isOpen: true } };
    } else if (opts.sidebarOpen === false) {
        context.window.AAAdmin = { Sidebar: { isOpen: false } };
    }
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
        reconcileState: function () {
            reconcileCalls++;
            if (typeof opts.reconcileState === 'function') {
                return opts.reconcileState();
            }
            if (opts.reconcileStateImpl) {
                return opts.reconcileStateImpl();
            }
            if (opts.reconcileStateReject) {
                return Promise.reject(opts.reconcileStateReject);
            }
            return Promise.resolve(opts.reconcileStateResult || opts.state || { version: 1, tutorials: {} });
        },
        transition: opts.transition || function (input) {
            transitionCalls.push(input);
            if (opts.transitionImpl) {
                return opts.transitionImpl(input);
            }
            return Promise.resolve(opts.stateAfterTransition || { version: 1, tutorials: {} });
        }
    };
    context.window.TutorialCompletionCard = {
        show: function (options) {
            completionCardShowCalls++;
            contextClearedBeforeShow = !context.window.TutorialFastAppointmentContext.isActive();
        },
        dismiss: function () {
            completionCardDismissCalls++;
        }
    };

    vm.runInNewContext(definitionsSrc, context, { filename: definitionsPath });
    vm.runInNewContext(contextSrc, context, { filename: contextPath });
    vm.runInNewContext(coordinatorSrc, context, { filename: coordinatorPath });

    return {
        TutorialCoordinator: context.window.TutorialCoordinator,
        TutorialDefinitions: context.window.TutorialDefinitions,
        TutorialFastAppointmentContext: context.window.TutorialFastAppointmentContext,
        AATutorial: tutorialRuntime,
        actionHandlers: actionHandlers,
        dispatchDocumentEvent: function (type, detail) {
            context.document.dispatchEvent({ type: type, detail: detail || {} });
        },
        dispatchDomContentLoaded: function () {
            context.document.dispatchEvent({ type: 'DOMContentLoaded' });
        },
        metrics: {
            get actionRegisterCalls() { return actionRegisterCalls; },
            get clearCalls() { return clearCalls; },
            get destroyCalls() { return destroyCalls; },
            get startCalls() { return startCalls; },
            get transitionCalls() { return transitionCalls; },
            get fetchCalls() { return fetchCalls; },
            get reconcileCalls() { return reconcileCalls; },
            get warnMessages() { return warnMessages.slice(); },
            get pauseCalls() { return pauseCalls; },
            get completionCardShowCalls() { return completionCardShowCalls; },
            get completionCardDismissCalls() { return completionCardDismissCalls; },
            get contextClearedBeforeShow() { return contextClearedBeforeShow; }
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
            currentModule: 'calendar',
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

    it('paused/calendar_overview hace resume antes de iniciar', async () => {
        var env = loadCoordinator({
            currentModule: 'calendar',
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'paused',
                        current_step_id: 'calendar_overview'
                    }
                }
            },
            stateAfterTransition: {
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
        assert.equal(env.metrics.transitionCalls.length, 1);
        assert.equal(env.metrics.transitionCalls[0].status, 'in_progress');
        assert.equal(env.metrics.transitionCalls[0].currentStepId, 'calendar_overview');
        assert.equal(env.metrics.startCalls[0].initialStepId, 'calendar_overview');
    });

    it('paused/open_sidebar hace resume durable antes de iniciar', async () => {
        var env = loadCoordinator({
            currentModule: 'calendar',
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'paused',
                        current_step_id: 'open_sidebar'
                    }
                }
            },
            stateAfterTransition: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        current_step_id: 'open_sidebar'
                    }
                }
            }
        });

        await env.TutorialCoordinator.init();
        assert.equal(env.metrics.transitionCalls.length, 1);
        assert.equal(env.metrics.transitionCalls[0].status, 'in_progress');
        assert.equal(env.metrics.transitionCalls[0].currentStepId, 'open_sidebar');
        assert.equal(env.metrics.startCalls[0].initialStepId, 'open_sidebar');
    });

    it('in_progress/calendar_overview no llama resume', async () => {
        var env = loadCoordinator({
            currentModule: 'calendar',
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
        assert.equal(env.metrics.transitionCalls.length, 0);
        assert.equal(env.metrics.startCalls[0].initialStepId, 'calendar_overview');
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
            currentModule: 'calendar',
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

    it('persist create_test_appointment destruye motor y bloquea completion', async () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        var result = await env.actionHandlers.aa_tutorial_persist_create_test_appointment({
            tutorial: env.AATutorial
        });

        assert.equal(result, false);
        assert.equal(env.metrics.destroyCalls, 1);
        assert.equal(env.metrics.pauseCalls, 0);
        assert.equal(env.metrics.transitionCalls[0].status, 'in_progress');
        assert.equal(env.metrics.transitionCalls[0].currentStepId, 'create_test_appointment');
    });

    it('fallo al persistir create_test_appointment no destruye motor', async () => {
        var env = loadCoordinator({
            transitionImpl: function () {
                return Promise.reject(new Error('network'));
            }
        });
        env.TutorialCoordinator.registerActions();

        await assert.rejects(
            function () {
                return env.actionHandlers.aa_tutorial_persist_create_test_appointment({
                    tutorial: env.AATutorial
                });
            },
            /network/
        );

        assert.equal(env.metrics.destroyCalls, 0);
    });

    it('in_progress/create_test_appointment en calendar inicia resume FAB', async () => {
        var env = loadCoordinator({
            currentModule: 'calendar',
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
        assert.equal(started, true);
        assert.equal(env.metrics.startCalls.length, 1);
        assert.equal(env.metrics.startCalls[0].initialStepId, 'resume_create_test_appointment_fab');
        assert.equal(env.metrics.transitionCalls.length, 0);
    });

    it('step-shown resume_create_test_appointment_fab activa contexto antes del click', () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);

        env.dispatchDocumentEvent('aa:tutorial:step-shown', {
            stepId: 'resume_create_test_appointment_fab'
        });

        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
        assert.deepEqual(Object.assign({}, env.TutorialFastAppointmentContext.get()), FAST_APPOINTMENT_CONTEXT);
    });

    it('dismiss visual-only destruye motor sin transition ni clear context', async () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        var result = await env.actionHandlers.aa_tutorial_dismiss_visual_only({
            tutorial: env.AATutorial
        });

        assert.equal(result, false);
        assert.equal(env.metrics.destroyCalls, 1);
        assert.equal(env.metrics.transitionCalls.length, 0);
        assert.equal(env.metrics.pauseCalls, 0);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
    });

    it('step-shown calendar_overview activa contexto antes del click', () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);

        env.dispatchDocumentEvent('aa:tutorial:step-shown', { stepId: 'calendar_overview' });

        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
        assert.deepEqual(Object.assign({}, env.TutorialFastAppointmentContext.get()), FAST_APPOINTMENT_CONTEXT);
    });

    it('persist create_test_appointment mantiene contexto activo tras destroy', async () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        var result = await env.actionHandlers.aa_tutorial_persist_create_test_appointment({
            tutorial: env.AATutorial
        });

        assert.equal(result, false);
        assert.equal(env.metrics.destroyCalls, 1);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
    });

    it('fallo al persistir create_test_appointment mantiene contexto activo', async () => {
        var env = loadCoordinator({
            transitionImpl: function () {
                return Promise.reject(new Error('network'));
            }
        });
        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        await assert.rejects(
            function () {
                return env.actionHandlers.aa_tutorial_persist_create_test_appointment({
                    tutorial: env.AATutorial
                });
            },
            /network/
        );

        assert.equal(env.metrics.destroyCalls, 0);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
    });

    it('modal-closed reactiva contexto solo si motor sigue en calendar_overview', () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        env.AATutorial.status = 'active';
        env.AATutorial.currentStepId = 'calendar_overview';

        env.dispatchDocumentEvent('aa:fastappointment:modal-closed');

        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
        assert.deepEqual(Object.assign({}, env.TutorialFastAppointmentContext.get()), FAST_APPOINTMENT_CONTEXT);
    });

    it('modal-closed no reactiva contexto si motor ya no está en calendar_overview', () => {
        var env = loadCoordinator();
        env.TutorialCoordinator.registerActions();

        env.AATutorial.status = 'idle';
        env.AATutorial.currentStepId = null;

        env.dispatchDocumentEvent('aa:fastappointment:modal-closed');

        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);
    });

    it('init de sesión tutorial nueva limpia contexto previo', async () => {
        var env = loadCoordinator({
            state: { version: 1, tutorials: {} }
        });

        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);
        await env.TutorialCoordinator.init();

        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);
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
            currentModule: 'calendar',
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

    it('paso durable desconocido no inicia', async () => {
        var env = loadCoordinator({
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        current_step_id: 'unknown_future_step'
                    }
                }
            }
        });

        var started = await env.TutorialCoordinator.init();
        assert.equal(started, false);
        assert.equal(env.metrics.startCalls.length, 0);
    });
});

describe('TutorialCoordinator E3a resume infrastructure', () => {
    function makeRecord(status, durableStep) {
        return {
            status: status,
            current_step_id: durableStep
        };
    }

    function resolvePlan(record, overrides) {
        var env = loadCoordinator();
        var opts = overrides || {};

        return env.TutorialCoordinator.resolveResumePlan({
            record: record,
            currentModule: opts.currentModule,
            sidebarOpen: opts.sidebarOpen
        });
    }

    it('matriz calendar_overview + calendar → calendar_overview', () => {
        var result = resolvePlan(makeRecord('in_progress', 'calendar_overview'), {
            currentModule: 'calendar'
        });

        assert.equal(result.visualStepId, 'calendar_overview');
    });

    it('matriz calendar_overview + fuera calendar + sidebar abierto → resume_navigate_calendar', () => {
        var result = resolvePlan(makeRecord('in_progress', 'calendar_overview'), {
            currentModule: 'dashboard',
            sidebarOpen: true
        });

        assert.equal(result.visualStepId, 'resume_navigate_calendar');
    });

    it('matriz calendar_overview + fuera calendar + sidebar cerrado → resume_open_sidebar', () => {
        var result = resolvePlan(makeRecord('in_progress', 'calendar_overview'), {
            currentModule: 'dashboard',
            sidebarOpen: false
        });

        assert.equal(result.visualStepId, 'resume_open_sidebar');
    });

    it('matriz create_test_appointment + calendar → resume_create_test_appointment_fab', () => {
        var result = resolvePlan(makeRecord('in_progress', 'create_test_appointment'), {
            currentModule: 'calendar'
        });

        assert.equal(result.visualStepId, 'resume_create_test_appointment_fab');
    });

    it('matriz create_test_appointment + fuera calendar + sidebar abierto → resume_navigate_calendar', () => {
        var result = resolvePlan(makeRecord('in_progress', 'create_test_appointment'), {
            currentModule: 'settings',
            sidebarOpen: true
        });

        assert.equal(result.visualStepId, 'resume_navigate_calendar');
    });

    it('matriz create_test_appointment + fuera calendar + sidebar cerrado → resume_open_sidebar', () => {
        var result = resolvePlan(makeRecord('paused', 'create_test_appointment'), {
            currentModule: 'learning',
            sidebarOpen: false
        });

        assert.equal(result.visualStepId, 'resume_open_sidebar');
    });

    it('init off-calendar calendar_overview usa resume_navigate_calendar con sidebar abierto', async () => {
        var env = loadCoordinator({
            currentModule: 'dashboard',
            sidebarOpen: true,
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
        assert.equal(env.metrics.transitionCalls.length, 0);
        assert.equal(env.metrics.startCalls[0].initialStepId, 'resume_navigate_calendar');
    });

    it('init off-calendar calendar_overview usa resume_open_sidebar con sidebar cerrado', async () => {
        var env = loadCoordinator({
            currentModule: 'dashboard',
            sidebarOpen: false,
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
        assert.equal(env.metrics.startCalls[0].initialStepId, 'resume_open_sidebar');
    });

    it('paused/create_test_appointment + calendar hace una transition y resume FAB', async () => {
        var env = loadCoordinator({
            currentModule: 'calendar',
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'paused',
                        current_step_id: 'create_test_appointment'
                    }
                }
            },
            stateAfterTransition: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        current_step_id: 'create_test_appointment'
                    }
                }
            }
        });

        await env.TutorialCoordinator.init();
        assert.equal(env.metrics.transitionCalls.length, 1);
        assert.equal(env.metrics.transitionCalls[0].status, 'in_progress');
        assert.equal(env.metrics.transitionCalls[0].currentStepId, 'create_test_appointment');
        assert.equal(env.metrics.startCalls[0].initialStepId, 'resume_create_test_appointment_fab');
    });

    it('paused/calendar_overview + fuera calendar hace una transition y resume navegación', async () => {
        var env = loadCoordinator({
            currentModule: 'dashboard',
            sidebarOpen: true,
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'paused',
                        current_step_id: 'calendar_overview'
                    }
                }
            },
            stateAfterTransition: {
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
        assert.equal(env.metrics.transitionCalls.length, 1);
        assert.equal(env.metrics.startCalls[0].initialStepId, 'resume_navigate_calendar');
    });

    it('isSidebarOpen usa AAAdmin.Sidebar.isOpen', () => {
        var env = loadCoordinator({ sidebarOpen: true });
        assert.equal(env.TutorialCoordinator.isSidebarOpen(), true);
    });

    it('ensure sidebar interactable pausa si sidebar cerrado', () => {
        var env = loadCoordinator({ sidebarOpen: false });
        env.TutorialCoordinator.registerActions();

        env.actionHandlers.aa_tutorial_ensure_sidebar_interactable({
            tutorial: env.AATutorial
        });

        assert.equal(env.metrics.pauseCalls, 1);
    });
});

describe('TutorialCoordinator E3a motor integration', () => {
    function makeMotorElement(tagName, rect) {
        var listeners = {};
        var element = {
            tagName: tagName.toUpperCase(),
            id: '',
            className: '',
            href: '',
            textContent: '',
            style: {},
            attributes: {},
            children: [],
            parentNode: null,
            appendChild: function (child) {
                child.parentNode = element;
                element.children.push(child);
                return child;
            },
            setAttribute: function (name, value) {
                element.attributes[name] = String(value);
            },
            getAttribute: function (name) {
                return Object.prototype.hasOwnProperty.call(element.attributes, name)
                    ? element.attributes[name]
                    : null;
            },
            addEventListener: function (type, handler) {
                listeners[type] = listeners[type] || [];
                listeners[type].push(handler);
            },
            dispatchEvent: function (event) {
                (listeners[event.type] || []).slice().forEach(function (handler) {
                    handler(event);
                });
                return true;
            },
            getBoundingClientRect: function () {
                return rect || { top: 10, left: 10, right: 90, bottom: 50, width: 80, height: 40 };
            },
            classList: {
                contains: function () { return false; },
                add: function () {},
                remove: function () {}
            },
            __listeners: listeners
        };

        return element;
    }

    function loadMotorEnv() {
        var bodyChildren = [];
        var body = {
            children: bodyChildren,
            appendChild: function (child) {
                bodyChildren.push(child);
                return child;
            },
            classList: {
                contains: function () { return false; },
                add: function () {},
                remove: function () {}
            }
        };

        var sidebarOpened = false;
        var assignedHref = null;
        var completedEvents = 0;
        var productFabOpens = 0;
        var documentEvents = {};

        var sidebarBtn = makeMotorElement('button');
        sidebarBtn.id = 'aa-btn-sidebar';

        var calendarLink = makeMotorElement('a');
        calendarLink.setAttribute('data-aa-nav-module', 'calendar');
        calendarLink.href = '/admin?module=calendar';

        var fabBtn = makeMotorElement('button');
        fabBtn.id = 'aa-btn-open-fastappointment-modal';

        var sidebar = makeMotorElement('aside');
        sidebar.id = 'aa-sidebar';

        var context = {
            window: {},
            document: {
                body: body,
                documentElement: { clientWidth: 1024, clientHeight: 768 },
                addEventListener: function (type, handler) {
                    documentEvents[type] = documentEvents[type] || [];
                    documentEvents[type].push(handler);
                },
                removeEventListener: function () {},
                querySelector: function (selector) {
                    if (selector === '#aa-btn-sidebar') {
                        return sidebarBtn;
                    }
                    if (selector === '[data-aa-nav-module="calendar"]') {
                        return calendarLink;
                    }
                    if (selector === '#aa-btn-open-fastappointment-modal') {
                        return fabBtn;
                    }
                    if (selector === '#aa-sidebar') {
                        return sidebar;
                    }
                    return null;
                },
                getElementById: function (id) {
                    if (id === 'aa-sidebar') {
                        return sidebar;
                    }
                    return null;
                },
                createElement: function (tag) {
                    return makeMotorElement(tag);
                },
                dispatchEvent: function (event) {
                    if (event.type === 'aa:tutorial:completed') {
                        completedEvents++;
                    }

                    (documentEvents[event.type] || []).forEach(function (handler) {
                        handler(event);
                    });
                    return true;
                }
            },
            CustomEvent: function (type, init) {
                this.type = type;
                this.detail = init && init.detail ? init.detail : {};
            },
            console: { warn: function () {}, error: function () {} },
            setTimeout: function (fn) { fn(); return 0; },
            clearTimeout: function () {},
            Event: function (type) { this.type = type; }
        };

        context.window = context;
        context.window.AA_ADMIN_CONTEXT = { blogId: 44, currentModule: 'calendar' };
        context.window.AAAdmin = {
            Sidebar: {
                isOpen: false,
                open: function () {
                    sidebarOpened = true;
                    this.isOpen = true;
                }
            }
        };
        context.window.location = {
            assign: function (href) {
                assignedHref = href;
            }
        };
        context.window.sessionStorage = makeSessionStorage();

        sidebarBtn.addEventListener('click', function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            context.window.AAAdmin.Sidebar.open();
        });

        fabBtn.addEventListener('click', function () {
            productFabOpens++;
        });

        vm.runInNewContext(tutorialSrc, context, { filename: tutorialPath });
        vm.runInNewContext(definitionsSrc, context, { filename: definitionsPath });
        vm.runInNewContext(contextSrc, context, { filename: contextPath });
        vm.runInNewContext(coordinatorSrc, context, { filename: coordinatorPath });

        context.window.TutorialCoordinator.registerActions();

        return {
            api: context.window,
            sidebarBtn: sidebarBtn,
            calendarLink: calendarLink,
            fabBtn: fabBtn,
            metrics: {
                get sidebarOpened() { return sidebarOpened; },
                get assignedHref() { return assignedHref; },
                get completedEvents() { return completedEvents; },
                get productFabOpens() { return productFabOpens; }
            }
        };
    }

    it('resume_open_sidebar click real avanza a resume_navigate_calendar sin transition', async () => {
        var env = loadMotorEnv();
        var config = env.api.TutorialDefinitions.getConfig('create_test_appointment_v1', {
            initialStepId: 'resume_open_sidebar'
        });

        env.api.AATutorial.start(config);
        assert.equal(env.api.AATutorial.getState().currentStepId, 'resume_open_sidebar');

        env.sidebarBtn.dispatchEvent({
            type: 'click',
            preventDefault: function () {},
            stopPropagation: function () {}
        });

        await flushMicrotasks();
        assert.equal(env.metrics.sidebarOpened, true);
        assert.equal(env.api.AATutorial.getState().currentStepId, 'resume_navigate_calendar');
        assert.equal(env.metrics.completedEvents, 0);
    });

    it('resume_navigate_calendar click navega sin complete ni transition', async () => {
        var env = loadMotorEnv();
        env.api.AAAdmin.Sidebar.isOpen = true;

        var config = env.api.TutorialDefinitions.getConfig('create_test_appointment_v1', {
            initialStepId: 'resume_navigate_calendar'
        });

        env.api.AATutorial.start(config);

        env.calendarLink.dispatchEvent({
            type: 'click',
            preventDefault: function () {},
            stopPropagation: function () {}
        });

        await flushMicrotasks();
        assert.equal(env.metrics.assignedHref, '/admin?module=calendar');
        assert.equal(env.metrics.completedEvents, 0);
    });

    it('resume FAB click abre producto, destruye visual y no complete', async () => {
        var env = loadMotorEnv();
        var destroyCalls = 0;
        var originalDestroy = env.api.AATutorial.destroy.bind(env.api.AATutorial);

        env.api.AATutorial.destroy = function () {
            destroyCalls++;
            return originalDestroy();
        };

        env.api.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        var config = env.api.TutorialDefinitions.getConfig('create_test_appointment_v1', {
            initialStepId: 'resume_create_test_appointment_fab'
        });

        env.api.AATutorial.start(config);
        assert.equal(env.api.TutorialFastAppointmentContext.isActive(), true);

        var destroyCallsBeforeClick = destroyCalls;

        env.fabBtn.dispatchEvent({
            type: 'click',
            preventDefault: function () {},
            stopPropagation: function () {}
        });

        await flushMicrotasks();
        assert.equal(env.metrics.productFabOpens, 1);
        assert.equal(destroyCalls - destroyCallsBeforeClick, 1);
        assert.equal(env.api.AATutorial.getState().status, 'idle');
        assert.equal(env.metrics.completedEvents, 0);
        assert.equal(env.api.TutorialFastAppointmentContext.isActive(), true);
    });
});

describe('TutorialCoordinator C1b reservation completion', () => {
    var IN_PROGRESS_CREATE_TEST_STATE = {
        version: 1,
        tutorials: {
            create_test_appointment_v1: {
                status: 'in_progress',
                current_step_id: 'create_test_appointment'
            }
        }
    };

    function emitFastAppointmentReservationCreated(env, id) {
        env.dispatchDocumentEvent('aa:reservation:created', {
            source: 'fastappointment',
            id: id || 42
        });
    }

    it('evento válido + durable correcto completa tutorial una vez', async () => {
        var env = loadCoordinator({
            state: IN_PROGRESS_CREATE_TEST_STATE
        });

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env, 77);
        await flushMicrotasks();

        assert.equal(env.metrics.fetchCalls, 1);
        assert.equal(env.metrics.transitionCalls.length, 1);
        assert.equal(env.metrics.transitionCalls[0].tutorialId, 'create_test_appointment_v1');
        assert.equal(env.metrics.transitionCalls[0].status, 'completed');
        assert.equal(env.metrics.transitionCalls[0].currentStepId, null);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);
        assert.equal(env.metrics.completionCardShowCalls, 1);
        assert.equal(env.metrics.contextClearedBeforeShow, true);
    });

    it('source distinto de fastappointment no completa', async () => {
        var env = loadCoordinator({
            state: IN_PROGRESS_CREATE_TEST_STATE
        });

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        env.dispatchDocumentEvent('aa:reservation:created', { id: 42 });
        await flushMicrotasks();

        assert.equal(env.metrics.fetchCalls, 0);
        assert.equal(env.metrics.transitionCalls.length, 0);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
    });

    it('contexto tutorial inactivo no completa', async () => {
        var env = loadCoordinator({
            state: IN_PROGRESS_CREATE_TEST_STATE
        });

        env.TutorialCoordinator.registerActions();

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.fetchCalls, 0);
        assert.equal(env.metrics.transitionCalls.length, 0);
    });

    it('dos eventos rápidos producen una sola operación async', async () => {
        var resolveFetch;
        var env = loadCoordinator({
            fetchState: function () {
                return new Promise(function (resolve) {
                    resolveFetch = resolve;
                });
            }
        });

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        emitFastAppointmentReservationCreated(env);

        assert.equal(env.metrics.fetchCalls, 1);

        resolveFetch(IN_PROGRESS_CREATE_TEST_STATE);
        await flushMicrotasks();

        assert.equal(env.metrics.transitionCalls.length, 1);
    });

    it('transition fallida deja done false y permite reintento', async () => {
        var transitionAttempts = 0;
        var recordedTransitions = [];
        var env = loadCoordinator({
            state: IN_PROGRESS_CREATE_TEST_STATE,
            transitionImpl: function (input) {
                transitionAttempts++;
                recordedTransitions.push(input);

                if (transitionAttempts === 1) {
                    return Promise.reject(new Error('network'));
                }

                return Promise.resolve({
                    version: 1,
                    tutorials: {
                        create_test_appointment_v1: {
                            status: 'completed',
                            current_step_id: null
                        }
                    }
                });
            }
        });

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(transitionAttempts, 1);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(transitionAttempts, 2);
        assert.equal(recordedTransitions.length, 2);
        assert.equal(recordedTransitions[1].tutorialId, 'create_test_appointment_v1');
        assert.equal(recordedTransitions[1].status, 'completed');
        assert.equal(recordedTransitions[1].currentStepId, null);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);
    });

    it('durable completed no transition y limpia contexto', async () => {
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

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.transitionCalls.length, 0);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), false);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.fetchCalls, 1);
    });

    it('durable step distinto no completa', async () => {
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

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.transitionCalls.length, 0);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
    });

    it('status paused no completa', async () => {
        var env = loadCoordinator({
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'paused',
                        current_step_id: 'create_test_appointment'
                    }
                }
            }
        });

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.transitionCalls.length, 0);
        assert.equal(env.TutorialFastAppointmentContext.isActive(), true);
    });

    it('segundo evento tras completion no vuelve a transicionar', async () => {
        var env = loadCoordinator({
            state: IN_PROGRESS_CREATE_TEST_STATE
        });

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.fetchCalls, 1);
        assert.equal(env.metrics.transitionCalls.length, 1);
    });

    it('transition fallida no muestra tarjeta final', async () => {
        var env = loadCoordinator({
            state: IN_PROGRESS_CREATE_TEST_STATE,
            transitionImpl: function () {
                return Promise.reject(new Error('network'));
            }
        });

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.completionCardShowCalls, 0);
    });

    it('durable ya completed no muestra tarjeta final', async () => {
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

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.transitionCalls.length, 0);
        assert.equal(env.metrics.completionCardShowCalls, 0);
    });

    it('segundo evento tras completion no muestra segunda tarjeta', async () => {
        var env = loadCoordinator({
            state: IN_PROGRESS_CREATE_TEST_STATE
        });

        env.TutorialCoordinator.registerActions();
        env.TutorialFastAppointmentContext.activate(FAST_APPOINTMENT_CONTEXT);

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        emitFastAppointmentReservationCreated(env);
        await flushMicrotasks();

        assert.equal(env.metrics.completionCardShowCalls, 1);
    });
});

describe('TutorialCoordinator E3b bootstrap', () => {
    it('bootstrap llama reconcile antes de init', async () => {
        var callOrder = [];
        var env = loadCoordinator({
            currentModule: 'calendar',
            reconcileStateImpl: function () {
                callOrder.push('reconcile');
                return Promise.resolve({
                    version: 1,
                    tutorials: {
                        create_test_appointment_v1: {
                            status: 'available'
                        }
                    }
                });
            },
            fetchState: function () {
                callOrder.push('fetch');
                return Promise.resolve({
                    version: 1,
                    tutorials: {
                        create_test_appointment_v1: {
                            status: 'available'
                        }
                    }
                });
            }
        });

        await env.TutorialCoordinator.bootstrapTutorial();
        assert.deepEqual(callOrder, ['reconcile', 'fetch']);
        assert.equal(env.metrics.reconcileCalls, 1);
        assert.equal(env.metrics.startCalls.length, 1);
    });

    it('completed tras reconcile no inicia', async () => {
        var env = loadCoordinator({
            reconcileStateResult: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'completed',
                        current_step_id: null
                    }
                }
            }
        });

        var started = await env.TutorialCoordinator.bootstrapTutorial();
        assert.equal(started, false);
        assert.equal(env.metrics.reconcileCalls, 1);
        assert.equal(env.metrics.fetchCalls, 0);
        assert.equal(env.metrics.startCalls.length, 0);
    });

    it('skipped tras reconcile no inicia', async () => {
        var env = loadCoordinator({
            reconcileStateResult: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'skipped',
                        current_step_id: null,
                        skipped_at: '2026-07-04 08:00:00'
                    }
                }
            }
        });

        var started = await env.TutorialCoordinator.bootstrapTutorial();
        assert.equal(started, false);
        assert.equal(env.metrics.reconcileCalls, 1);
        assert.equal(env.metrics.fetchCalls, 0);
        assert.equal(env.metrics.startCalls.length, 0);
    });

    it('available tras reconcile inicia tutorial', async () => {
        var env = loadCoordinator({
            reconcileStateResult: {
                version: 1,
                tutorials: {}
            }
        });

        var started = await env.TutorialCoordinator.bootstrapTutorial();
        assert.equal(started, true);
        assert.equal(env.metrics.startCalls[0].initialStepId, 'intro');
    });

    it('in_progress tras reconcile inicia tutorial', async () => {
        var env = loadCoordinator({
            currentModule: 'calendar',
            reconcileStateResult: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        current_step_id: 'calendar_overview'
                    }
                }
            },
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

        await env.TutorialCoordinator.bootstrapTutorial();
        assert.equal(env.metrics.startCalls[0].initialStepId, 'calendar_overview');
    });

    it('paused tras reconcile inicia tutorial', async () => {
        var env = loadCoordinator({
            currentModule: 'calendar',
            reconcileStateResult: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'paused',
                        current_step_id: 'calendar_overview'
                    }
                }
            },
            state: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'paused',
                        current_step_id: 'calendar_overview'
                    }
                }
            },
            stateAfterTransition: {
                version: 1,
                tutorials: {
                    create_test_appointment_v1: {
                        status: 'in_progress',
                        current_step_id: 'calendar_overview'
                    }
                }
            }
        });

        await env.TutorialCoordinator.bootstrapTutorial();
        assert.equal(env.metrics.reconcileCalls, 1);
        assert.equal(env.metrics.startCalls[0].initialStepId, 'calendar_overview');
    });

    it('error reconcile no inicia ni hace fallback a fetchState', async () => {
        var env = loadCoordinator({
            reconcileStateReject: new Error('probe down')
        });

        var started = await env.TutorialCoordinator.bootstrapTutorial();
        assert.equal(started, false);
        assert.equal(env.metrics.reconcileCalls, 1);
        assert.equal(env.metrics.fetchCalls, 0);
        assert.equal(env.metrics.startCalls.length, 0);
        assert.ok(env.metrics.warnMessages.some(function (msg) {
            return msg.indexOf('reconcile falló') !== -1;
        }));
    });

    it('segunda llamada bootstrap no repite reconcile', async () => {
        var env = loadCoordinator({
            reconcileStateResult: {
                version: 1,
                tutorials: {}
            }
        });

        await env.TutorialCoordinator.bootstrapTutorial();
        await env.TutorialCoordinator.bootstrapTutorial();
        assert.equal(env.metrics.reconcileCalls, 1);
    });

    it('llamadas concurrentes bootstrap comparten una sola cadena', async () => {
        var resolveReconcile;
        var env = loadCoordinator({
            reconcileStateImpl: function () {
                return new Promise(function (resolve) {
                    resolveReconcile = resolve;
                });
            },
            state: {
                version: 1,
                tutorials: {}
            }
        });

        var first = env.TutorialCoordinator.bootstrapTutorial();
        var second = env.TutorialCoordinator.bootstrapTutorial();

        await flushMicrotasks();
        assert.strictEqual(first, second);
        assert.equal(env.metrics.reconcileCalls, 1);

        resolveReconcile({ version: 1, tutorials: {} });
        await first;
        await second;
    });

    it('DOMContentLoaded dispara bootstrap una sola vez', async () => {
        var env = loadCoordinator({
            reconcileStateResult: {
                version: 1,
                tutorials: {}
            }
        });

        env.dispatchDomContentLoaded();
        env.dispatchDomContentLoaded();
        await flushMicrotasks();

        assert.equal(env.metrics.reconcileCalls, 1);
    });

    it('init manual sigue disponible sin bootstrap', async () => {
        var env = loadCoordinator({
            state: {
                version: 1,
                tutorials: {}
            }
        });

        var started = await env.TutorialCoordinator.init();
        assert.equal(started, true);
        assert.equal(env.metrics.reconcileCalls, 0);
        assert.equal(env.metrics.fetchCalls, 1);
    });
});

describe('TutorialCoordinator wiring guardrails', () => {
    it('coordinator registra auto-bootstrap en DOMContentLoaded', () => {
        assert.equal(coordinatorSrc.includes('DOMContentLoaded'), true);
        assert.equal(coordinatorSrc.includes('bootstrapTutorial'), true);
        assert.equal(coordinatorSrc.includes('reconcileState'), true);
    });

    it('layout carga definitions/coordinator y mantiene onboarding legacy', () => {
        var layoutSrc = fs.readFileSync(
            path.join(__dirname, '../../includes/admin/ui/shared/layout.php'),
            'utf8'
        );

        var stateServicePos = layoutSrc.indexOf('tutorialStateService.js');
        var contextPos = layoutSrc.indexOf('tutorialFastAppointmentContext.js');
        var definitionsPos = layoutSrc.indexOf('tutorialDefinitions.js');
        var completionCardPos = layoutSrc.indexOf('tutorialCompletionCard.js');
        var coordinatorPos = layoutSrc.indexOf('tutorialCoordinator.js');
        var welcomePos = layoutSrc.indexOf('onboardingWelcome.js');
        var activationPos = layoutSrc.indexOf('onboardingActivationCoordinator.js');

        assert.ok(stateServicePos !== -1);
        assert.ok(contextPos > stateServicePos);
        assert.ok(completionCardPos !== -1);
        assert.ok(definitionsPos > completionCardPos);
        assert.ok(coordinatorPos > definitionsPos);
        assert.ok(welcomePos !== -1);
        assert.ok(activationPos !== -1);
    });
});
