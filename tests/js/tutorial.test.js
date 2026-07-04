'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const tutorialPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorial.js'
);

function makeClassList(element) {
    return {
        add: function () {
            Array.prototype.forEach.call(arguments, function (cls) {
                var parts = String(element.className || '').split(/\s+/).filter(Boolean);
                if (parts.indexOf(cls) === -1) {
                    parts.push(cls);
                }
                element.className = parts.join(' ');
            });
        },
        remove: function () {
            Array.prototype.forEach.call(arguments, function (cls) {
                var parts = String(element.className || '').split(/\s+/).filter(function (item) {
                    return item !== cls;
                });
                element.className = parts.join(' ');
            });
        },
        contains: function (cls) {
            return String(element.className || '').split(/\s+/).indexOf(cls) !== -1;
        }
    };
}

function makeElement(tagName, rect) {
    var listeners = {};
    var element = {
        tagName: tagName.toUpperCase(),
        id: '',
        className: '',
        textContent: '',
        type: '',
        style: {},
        dataset: {},
        parentNode: null,
        children: [],
        attributes: {},
        classList: null,
        appendChild: function (child) {
            child.parentNode = element;
            element.children.push(child);
            return child;
        },
        removeChild: function (child) {
            element.children = element.children.filter(function (candidate) {
                return candidate !== child;
            });
            child.parentNode = null;
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
        removeEventListener: function (type, handler) {
            listeners[type] = (listeners[type] || []).filter(function (candidate) {
                return candidate !== handler;
            });
        },
        dispatchEvent: function (event) {
            (listeners[event.type] || []).slice().forEach(function (handler) {
                handler(event);
            });
            return true;
        },
        getBoundingClientRect: function () {
            return rect || { top: 100, left: 100, right: 180, bottom: 140, width: 80, height: 40 };
        },
        __listeners: listeners
    };

    element.classList = makeClassList(element);

    return element;
}

function findByClass(element, className) {
    if (!element) {
        return null;
    }

    if (element.classList && element.classList.contains(className)) {
        return element;
    }

    for (var i = 0; i < element.children.length; i++) {
        var found = findByClass(element.children[i], className);
        if (found) {
            return found;
        }
    }

    return null;
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

function installDom(selectorMap) {
    var docListeners = {};
    var body = makeElement('body');
    var map = selectorMap || {};

    globalThis.window = globalThis;
    globalThis.innerWidth = 1024;
    globalThis.innerHeight = 768;
    globalThis.AA_ADMIN_CONTEXT = { blogId: 44 };
    globalThis.sessionStorage = makeSessionStorage();
    globalThis.CustomEvent = function CustomEvent(type, init) {
        this.type = type;
        this.detail = init && init.detail ? init.detail : {};
    };
    globalThis.document = {
        body: body,
        documentElement: { clientWidth: 1024, clientHeight: 768 },
        createElement: function (tagName) {
            return makeElement(tagName, { top: 0, left: 0, right: 320, bottom: 160, width: 320, height: 160 });
        },
        querySelector: function (selector) {
            return map[selector] || null;
        },
        addEventListener: function (type, handler) {
            docListeners[type] = docListeners[type] || [];
            docListeners[type].push(handler);
        },
        removeEventListener: function (type, handler) {
            docListeners[type] = (docListeners[type] || []).filter(function (candidate) {
                return candidate !== handler;
            });
        },
        dispatchEvent: function (event) {
            (docListeners[event.type] || []).slice().forEach(function (handler) {
                handler(event);
            });
            return true;
        },
        __listeners: docListeners
    };

    globalThis.addEventListener = function () {};
    globalThis.removeEventListener = function () {};

    return { body: body, selectorMap: map, docListeners: docListeners };
}

function loadTutorial() {
    delete require.cache[tutorialPath];
    return require(tutorialPath);
}

function flushMicrotasks() {
    return new Promise(function (resolve) {
        setImmediate(resolve);
    });
}

function baseConfig(overrides) {
    return Object.assign({
        flowId: 'test_flow',
        initialStepId: 'one',
        steps: [
            { id: 'one', title: 'One', text: 'First', advance: { mode: 'button' } },
            { id: 'two', title: 'Two', text: 'Second', advance: { mode: 'button' } }
        ]
    }, overrides || {});
}

describe('AATutorial MC3B', () => {
    let originalWindow;
    let originalDocument;
    let originalStorage;
    let originalContext;
    let originalCustomEvent;

    beforeEach(() => {
        originalWindow = globalThis.window;
        originalDocument = globalThis.document;
        originalStorage = globalThis.sessionStorage;
        originalContext = globalThis.AA_ADMIN_CONTEXT;
        originalCustomEvent = globalThis.CustomEvent;
    });

    afterEach(() => {
        if (globalThis.AATutorial) {
            globalThis.AATutorial.destroy();
        }

        delete globalThis.AATutorial;
        delete globalThis.AATutorialActions;
        delete globalThis.AATutorialSession;
        delete require.cache[tutorialPath];

        globalThis.window = originalWindow;
        globalThis.document = originalDocument;
        globalThis.sessionStorage = originalStorage;
        globalThis.AA_ADMIN_CONTEXT = originalContext;
        globalThis.CustomEvent = originalCustomEvent;
    });

    it('session key usa blogId + flowId y rechaza blogId ausente', () => {
        var api = loadTutorial();

        assert.equal(
            api.AATutorialSession.buildKey('44', 'test_flow'),
            'aa_tutorial_session_v1:44:test_flow'
        );
        assert.equal(api.AATutorialSession.buildKey('', 'test_flow'), null);
    });

    it('session sanitize rechaza JSON corrupto y step invalido', () => {
        var api = loadTutorial();

        assert.equal(api.AATutorialSession.sanitize('{bad-json', 'flow', ['one']), null);
        assert.equal(
            api.AATutorialSession.sanitize({
                version: 1,
                flowId: 'flow',
                currentStepId: 'missing',
                status: 'active',
                updatedAt: 1
            }, 'flow', ['one']),
            null
        );
    });

    it('start retoma currentStepId desde sessionStorage valido', () => {
        installDom();
        var api = loadTutorial();
        var key = api.AATutorialSession.buildKey('44', 'test_flow');

        globalThis.sessionStorage.setItem(key, JSON.stringify({
            version: 1,
            flowId: 'test_flow',
            currentStepId: 'two',
            status: 'active',
            updatedAt: 1
        }));

        assert.equal(api.AATutorial.start(baseConfig()), true);
        assert.equal(api.AATutorial.getState().currentStepId, 'two');
    });

    it('button avanza y persiste el siguiente paso', async () => {
        var dom = installDom();
        var api = loadTutorial();

        api.AATutorial.start(baseConfig());

        var button = findByClass(dom.body, 'aa-tutorial-button');
        assert.ok(button);

        button.dispatchEvent({
            type: 'click',
            preventDefault: function () {}
        });

        await flushMicrotasks();

        assert.equal(api.AATutorial.getState().currentStepId, 'two');

        var stored = JSON.parse(globalThis.sessionStorage.getItem(
            api.AATutorialSession.buildKey('44', 'test_flow')
        ));
        assert.equal(stored.currentStepId, 'two');
    });

    it('target_click con navigation none no cancela el click real', async () => {
        var target = makeElement('a');
        var api;
        var preventCalls = 0;
        var stopCalls = 0;

        installDom({ '#create': target });
        api = loadTutorial();

        api.AATutorial.start(baseConfig({
            steps: [
                { id: 'one', title: 'One', target: '#create', advance: { mode: 'target_click' } },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        target.dispatchEvent({
            type: 'click',
            preventDefault: function () { preventCalls++; },
            stopPropagation: function () { stopCalls++; }
        });

        await flushMicrotasks();

        assert.equal(api.AATutorial.getState().currentStepId, 'two');
        assert.equal(preventCalls, 0);
        assert.equal(stopCalls, 0);

        var stored = JSON.parse(globalThis.sessionStorage.getItem(
            api.AATutorialSession.buildKey('44', 'test_flow')
        ));
        assert.equal(stored.currentStepId, 'two');
    });

    it('dismiss consume un solo click y evita doble avance', async () => {
        var dom = installDom();
        var api = loadTutorial();

        api.AATutorial.start(baseConfig({
            steps: [
                { id: 'one', title: 'One', advance: { mode: 'dismiss' } },
                { id: 'two', title: 'Two', advance: { mode: 'button' } },
                { id: 'three', title: 'Three', advance: { mode: 'button' } }
            ]
        }));

        var backdrop = findByClass(dom.body, 'aa-tutorial-backdrop');
        var preventCalls = 0;
        var stopCalls = 0;

        backdrop.dispatchEvent({
            type: 'click',
            preventDefault: function () { preventCalls++; },
            stopPropagation: function () { stopCalls++; }
        });
        backdrop.dispatchEvent({ type: 'touchend' });

        await flushMicrotasks();

        assert.equal(api.AATutorial.getState().currentStepId, 'two');
        assert.equal(preventCalls, 1);
        assert.equal(stopCalls, 1);
    });

    it('event avanza y limpia listener al avanzar', async () => {
        var dom = installDom();
        var api = loadTutorial();

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    advance: { mode: 'event', eventName: 'aa:test', eventDetail: { source: 'ok' } }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        assert.equal((dom.docListeners['aa:test'] || []).length, 1);
        globalThis.document.dispatchEvent({ type: 'aa:test', detail: { source: 'ignored' } });
        assert.equal(api.AATutorial.getState().currentStepId, 'one');

        globalThis.document.dispatchEvent({ type: 'aa:test', detail: { source: 'ok' } });
        await flushMicrotasks();

        assert.equal(api.AATutorial.getState().currentStepId, 'two');
        assert.equal((dom.docListeners['aa:test'] || []).length, 0);
    });

    it('target inexistente pausa como paused_missing_target y conserva session', async () => {
        installDom();
        var api = loadTutorial();

        api.AATutorial.start(baseConfig({
            steps: [{
                id: 'one',
                title: 'One',
                target: '#missing',
                waitFor: { selector: '#missing', timeoutMs: 0, intervalMs: 10 },
                advance: { mode: 'target_click' }
            }]
        }));

        await new Promise(function (resolve) { setTimeout(resolve, 5); });

        assert.equal(api.AATutorial.getState().status, 'paused_missing_target');

        var stored = JSON.parse(globalThis.sessionStorage.getItem(
            api.AATutorialSession.buildKey('44', 'test_flow')
        ));
        assert.equal(stored.currentStepId, 'one');
        assert.equal(stored.status, 'paused_missing_target');
    });

    it('blogId ausente no persiste bajo key compartida', () => {
        installDom();
        delete globalThis.AA_ADMIN_CONTEXT;
        var api = loadTutorial();

        api.AATutorial.start(baseConfig());

        assert.deepEqual(globalThis.sessionStorage.dump(), {});
        assert.equal(api.AATutorial.getState().currentStepId, 'one');
    });

    it('complete limpia DOM y sessionStorage; destroy no completa ni borra session', () => {
        var dom = installDom();
        var api = loadTutorial();
        var completedEvents = 0;

        globalThis.document.addEventListener('aa:tutorial:completed', function () {
            completedEvents++;
        });

        api.AATutorial.start(baseConfig());
        var key = api.AATutorialSession.buildKey('44', 'test_flow');
        assert.ok(globalThis.sessionStorage.getItem(key));

        api.AATutorial.destroy();
        assert.equal(dom.body.children.length, 0);
        assert.ok(globalThis.sessionStorage.getItem(key));
        assert.equal(completedEvents, 0);

        api.AATutorial.start(baseConfig());
        api.AATutorial.complete();
        assert.equal(dom.body.children.length, 0);
        assert.equal(globalThis.sessionStorage.getItem(key), null);
        assert.equal(completedEvents, 1);
    });

    it('action registry ejecuta acciones por nombre', () => {
        installDom();
        var api = loadTutorial();
        var calls = 0;

        api.AATutorialActions.register('test_action', function (ctx) {
            calls++;
            assert.equal(ctx.step.id, 'one');
        });

        api.AATutorial.start(baseConfig({
            steps: [
                { id: 'one', title: 'One', beforeAction: 'test_action', advance: { mode: 'button' } }
            ]
        }));

        assert.equal(calls, 1);
    });
});

describe('AATutorial MC3D0 async advance gate', () => {
    let originalWindow;
    let originalDocument;
    let originalStorage;
    let originalContext;
    let originalCustomEvent;
    let originalLocation;

    beforeEach(() => {
        originalWindow = globalThis.window;
        originalDocument = globalThis.document;
        originalStorage = globalThis.sessionStorage;
        originalContext = globalThis.AA_ADMIN_CONTEXT;
        originalCustomEvent = globalThis.CustomEvent;
        originalLocation = globalThis.location;
    });

    afterEach(() => {
        if (globalThis.AATutorial) {
            globalThis.AATutorial.destroy();
        }

        delete globalThis.AATutorial;
        delete globalThis.AATutorialActions;
        delete globalThis.AATutorialSession;
        delete require.cache[tutorialPath];

        globalThis.window = originalWindow;
        globalThis.document = originalDocument;
        globalThis.sessionStorage = originalStorage;
        globalThis.AA_ADMIN_CONTEXT = originalContext;
        globalThis.CustomEvent = originalCustomEvent;
        globalThis.location = originalLocation;
    });

    it('beforeAdvanceAction async permite avanzar tras resolver', async () => {
        installDom();
        var api = loadTutorial();
        var gateCtx = null;

        api.AATutorialActions.register('persist_gate', function (ctx) {
            gateCtx = ctx;
            return new Promise(function (resolve) {
                setTimeout(function () { resolve(true); }, 5);
            });
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    beforeAdvanceAction: 'persist_gate',
                    advance: { mode: 'button' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        var button = findByClass(globalThis.document.body, 'aa-tutorial-button');
        button.dispatchEvent({ type: 'click', preventDefault: function () {} });

        assert.equal(api.AATutorial.getState().currentStepId, 'one');
        await new Promise(function (resolve) { setTimeout(resolve, 10); });

        assert.equal(api.AATutorial.getState().currentStepId, 'two');
        assert.equal(gateCtx.trigger, 'button');
        assert.equal(gateCtx.step.id, 'one');
        assert.ok(gateCtx.tutorial);
        assert.ok(gateCtx.state);
    });

    it('beforeAdvanceAction false mantiene el step y emite advance-blocked', async () => {
        installDom();
        var api = loadTutorial();
        var blockedEvents = 0;

        globalThis.document.addEventListener('aa:tutorial:advance-blocked', function () {
            blockedEvents++;
        });

        api.AATutorialActions.register('block_gate', function () {
            return false;
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    beforeAdvanceAction: 'block_gate',
                    advance: { mode: 'button' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        var button = findByClass(globalThis.document.body, 'aa-tutorial-button');
        button.dispatchEvent({ type: 'click', preventDefault: function () {} });
        await flushMicrotasks();

        assert.equal(api.AATutorial.getState().currentStepId, 'one');
        assert.equal(blockedEvents, 1);

        button.dispatchEvent({ type: 'click', preventDefault: function () {} });
        await flushMicrotasks();
        assert.equal(blockedEvents, 2);
    });

    it('beforeAdvanceAction reject mantiene el step reintentable', async () => {
        installDom();
        var api = loadTutorial();

        api.AATutorialActions.register('reject_gate', function () {
            return Promise.reject(new Error('network'));
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    beforeAdvanceAction: 'reject_gate',
                    advance: { mode: 'button' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        var button = findByClass(globalThis.document.body, 'aa-tutorial-button');
        button.dispatchEvent({ type: 'click', preventDefault: function () {} });
        await flushMicrotasks();
        assert.equal(api.AATutorial.getState().currentStepId, 'one');

        api.AATutorialActions.register('reject_gate', function () {
            return Promise.resolve();
        });

        button.dispatchEvent({ type: 'click', preventDefault: function () {} });
        await flushMicrotasks();
        assert.equal(api.AATutorial.getState().currentStepId, 'two');
    });

    it('advanceInFlight bloquea doble click concurrente', async () => {
        installDom();
        var api = loadTutorial();
        var resolveGate;
        var gateCalls = 0;

        api.AATutorialActions.register('slow_gate', function () {
            gateCalls++;
            return new Promise(function (resolve) {
                resolveGate = resolve;
            });
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    beforeAdvanceAction: 'slow_gate',
                    advance: { mode: 'button' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        var button = findByClass(globalThis.document.body, 'aa-tutorial-button');
        var first = api.AATutorial.next();
        var second = api.AATutorial.next();

        await flushMicrotasks();
        assert.equal(gateCalls, 1);
        assert.equal(api.AATutorial.getState().currentStepId, 'one');

        resolveGate(true);
        await first;
        await second;
        await flushMicrotasks();

        assert.equal(api.AATutorial.getState().currentStepId, 'two');
        assert.equal(gateCalls, 1);
    });

    it('next() usa tryAdvance y no una ruta independiente', async () => {
        installDom();
        var api = loadTutorial();
        var gateCalls = 0;

        api.AATutorialActions.register('count_gate', function () {
            gateCalls++;
            return Promise.resolve();
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    beforeAdvanceAction: 'count_gate',
                    advance: { mode: 'button' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        await api.AATutorial.next();
        assert.equal(gateCalls, 1);
        assert.equal(api.AATutorial.getState().currentStepId, 'two');
    });

    it('afterAction legacy actua como alias de beforeAdvanceAction', async () => {
        installDom();
        var api = loadTutorial();
        var gateCalls = 0;

        api.AATutorialActions.register('legacy_gate', function () {
            gateCalls++;
            return false;
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    afterAction: 'legacy_gate',
                    advance: { mode: 'button' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        await api.AATutorial.next();
        assert.equal(gateCalls, 1);
        assert.equal(api.AATutorial.getState().currentStepId, 'one');
    });

    it('target_click follow_target previene navegacion hasta gate exitoso', async () => {
        var target = makeElement('a');
        target.setAttribute('href', '/calendar');
        target.href = '/calendar';

        installDom({ '#nav-calendar': target });
        var api = loadTutorial();
        var assigned = null;
        var preventCalls = 0;
        var resolveGate;

        globalThis.location = { assign: function (href) { assigned = href; } };

        api.AATutorialActions.register('nav_gate', function () {
            return new Promise(function (resolve) {
                resolveGate = resolve;
            });
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    target: '#nav-calendar',
                    beforeAdvanceAction: 'nav_gate',
                    advance: { mode: 'target_click', navigation: 'follow_target' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        target.dispatchEvent({
            type: 'click',
            preventDefault: function () { preventCalls++; },
            stopPropagation: function () {}
        });

        await flushMicrotasks();
        assert.equal(preventCalls, 1);
        assert.equal(assigned, null);
        assert.equal(api.AATutorial.getState().currentStepId, 'one');

        resolveGate(true);
        await flushMicrotasks();
        assert.equal(assigned, '/calendar');
    });

    it('target_click follow_target no navega si el gate falla', async () => {
        var target = makeElement('a');
        target.setAttribute('href', '/calendar');
        target.href = '/calendar';

        installDom({ '#nav-calendar': target });
        var api = loadTutorial();
        var assigned = null;

        globalThis.location = { assign: function (href) { assigned = href; } };

        api.AATutorialActions.register('fail_gate', function () {
            return false;
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    target: '#nav-calendar',
                    beforeAdvanceAction: 'fail_gate',
                    advance: { mode: 'target_click', navigation: 'follow_target' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        target.dispatchEvent({
            type: 'click',
            preventDefault: function () {},
            stopPropagation: function () {}
        });

        await flushMicrotasks();
        assert.equal(assigned, null);
        assert.equal(api.AATutorial.getState().currentStepId, 'one');
    });

    it('target_click navigation none ejecuta gate sin bloquear click del producto', async () => {
        var target = makeElement('button');
        var productCalls = 0;

        installDom({ '#sidebar-btn': target });
        var api = loadTutorial();
        var gateCalls = 0;
        var preventCalls = 0;
        var resolveGate;

        target.addEventListener('click', function () {
            productCalls++;
        });

        api.AATutorialActions.register('sidebar_gate', function (ctx) {
            gateCalls++;
            assert.equal(ctx.trigger, 'target_click');
            assert.equal(ctx.target, target);
            return new Promise(function (resolve) {
                resolveGate = resolve;
            });
        });

        api.AATutorial.start(baseConfig({
            steps: [
                {
                    id: 'one',
                    title: 'One',
                    target: '#sidebar-btn',
                    beforeAdvanceAction: 'sidebar_gate',
                    advance: { mode: 'target_click', navigation: 'none' }
                },
                { id: 'two', title: 'Two', advance: { mode: 'button' } }
            ]
        }));

        target.dispatchEvent({
            type: 'click',
            preventDefault: function () { preventCalls++; },
            stopPropagation: function () {}
        });

        assert.equal(productCalls, 1);
        assert.equal(preventCalls, 0);
        assert.equal(gateCalls, 1);
        assert.equal(api.AATutorial.getState().currentStepId, 'one');

        resolveGate(true);
        await flushMicrotasks();
        assert.equal(api.AATutorial.getState().currentStepId, 'two');
    });
});
