'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const tutorPath = path.join(
    __dirname,
    '../../includes/admin/ui/modals/onboarding/onboardingTutor.js'
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

function loadTutor() {
    delete require.cache[tutorPath];
    return require(tutorPath);
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

describe('OnboardingTutor MC2C', () => {
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
        if (globalThis.OnboardingTutor) {
            globalThis.OnboardingTutor.destroy();
        }

        delete globalThis.OnboardingTutor;
        delete globalThis.AAOnboardingTutorActions;
        delete globalThis.AAOnboardingTutorSession;
        delete require.cache[tutorPath];

        globalThis.window = originalWindow;
        globalThis.document = originalDocument;
        globalThis.sessionStorage = originalStorage;
        globalThis.AA_ADMIN_CONTEXT = originalContext;
        globalThis.CustomEvent = originalCustomEvent;
    });

    it('session key usa blogId + flowId y rechaza blogId ausente', () => {
        var api = loadTutor();

        assert.equal(
            api.AAOnboardingTutorSession.buildKey('44', 'test_flow'),
            'aa_onboarding_tutor_session_v1:44:test_flow'
        );
        assert.equal(api.AAOnboardingTutorSession.buildKey('', 'test_flow'), null);
    });

    it('session sanitize rechaza JSON corrupto y step invalido', () => {
        var api = loadTutor();

        assert.equal(api.AAOnboardingTutorSession.sanitize('{bad-json', 'flow', ['one']), null);
        assert.equal(
            api.AAOnboardingTutorSession.sanitize({
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
        var api = loadTutor();
        var key = api.AAOnboardingTutorSession.buildKey('44', 'test_flow');

        globalThis.sessionStorage.setItem(key, JSON.stringify({
            version: 1,
            flowId: 'test_flow',
            currentStepId: 'two',
            status: 'active',
            updatedAt: 1
        }));

        assert.equal(api.OnboardingTutor.start(baseConfig()), true);
        assert.equal(api.OnboardingTutor.getState().currentStepId, 'two');
    });

    it('button avanza y persiste el siguiente paso', () => {
        var dom = installDom();
        var api = loadTutor();

        api.OnboardingTutor.start(baseConfig());

        var button = findByClass(dom.body, 'aa-onboarding-tutor-button');
        assert.ok(button);

        button.dispatchEvent({
            type: 'click',
            preventDefault: function () {}
        });

        assert.equal(api.OnboardingTutor.getState().currentStepId, 'two');

        var stored = JSON.parse(globalThis.sessionStorage.getItem(
            api.AAOnboardingTutorSession.buildKey('44', 'test_flow')
        ));
        assert.equal(stored.currentStepId, 'two');
    });

    it('target_click transiciona sincronicamente sin cancelar el click real', () => {
        var target = makeElement('a');
        var api;
        var preventCalls = 0;
        var stopCalls = 0;

        installDom({ '#create': target });
        api = loadTutor();

        api.OnboardingTutor.start(baseConfig({
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

        assert.equal(api.OnboardingTutor.getState().currentStepId, 'two');
        assert.equal(preventCalls, 0);
        assert.equal(stopCalls, 0);

        var stored = JSON.parse(globalThis.sessionStorage.getItem(
            api.AAOnboardingTutorSession.buildKey('44', 'test_flow')
        ));
        assert.equal(stored.currentStepId, 'two');
    });

    it('dismiss consume un solo click y evita doble avance', () => {
        var dom = installDom();
        var api = loadTutor();

        api.OnboardingTutor.start(baseConfig({
            steps: [
                { id: 'one', title: 'One', advance: { mode: 'dismiss' } },
                { id: 'two', title: 'Two', advance: { mode: 'button' } },
                { id: 'three', title: 'Three', advance: { mode: 'button' } }
            ]
        }));

        var backdrop = findByClass(dom.body, 'aa-onboarding-tutor-backdrop');
        var preventCalls = 0;
        var stopCalls = 0;

        backdrop.dispatchEvent({
            type: 'click',
            preventDefault: function () { preventCalls++; },
            stopPropagation: function () { stopCalls++; }
        });
        backdrop.dispatchEvent({ type: 'touchend' });

        assert.equal(api.OnboardingTutor.getState().currentStepId, 'two');
        assert.equal(preventCalls, 1);
        assert.equal(stopCalls, 1);
    });

    it('event avanza y limpia listener al avanzar', () => {
        var dom = installDom();
        var api = loadTutor();

        api.OnboardingTutor.start(baseConfig({
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
        assert.equal(api.OnboardingTutor.getState().currentStepId, 'one');

        globalThis.document.dispatchEvent({ type: 'aa:test', detail: { source: 'ok' } });
        assert.equal(api.OnboardingTutor.getState().currentStepId, 'two');
        assert.equal((dom.docListeners['aa:test'] || []).length, 0);
    });

    it('target inexistente pausa como paused_missing_target y conserva session', async () => {
        installDom();
        var api = loadTutor();

        api.OnboardingTutor.start(baseConfig({
            steps: [{
                id: 'one',
                title: 'One',
                target: '#missing',
                waitFor: { selector: '#missing', timeoutMs: 0, intervalMs: 10 },
                advance: { mode: 'target_click' }
            }]
        }));

        await new Promise(function (resolve) { setTimeout(resolve, 5); });

        assert.equal(api.OnboardingTutor.getState().status, 'paused_missing_target');

        var stored = JSON.parse(globalThis.sessionStorage.getItem(
            api.AAOnboardingTutorSession.buildKey('44', 'test_flow')
        ));
        assert.equal(stored.currentStepId, 'one');
        assert.equal(stored.status, 'paused_missing_target');
    });

    it('blogId ausente no persiste bajo key compartida', () => {
        installDom();
        delete globalThis.AA_ADMIN_CONTEXT;
        var api = loadTutor();

        api.OnboardingTutor.start(baseConfig());

        assert.deepEqual(globalThis.sessionStorage.dump(), {});
        assert.equal(api.OnboardingTutor.getState().currentStepId, 'one');
    });

    it('complete limpia DOM y sessionStorage; destroy no completa ni borra session', () => {
        var dom = installDom();
        var api = loadTutor();
        var completedEvents = 0;

        globalThis.document.addEventListener('aa:onboarding:tutor:completed', function () {
            completedEvents++;
        });

        api.OnboardingTutor.start(baseConfig());
        var key = api.AAOnboardingTutorSession.buildKey('44', 'test_flow');
        assert.ok(globalThis.sessionStorage.getItem(key));

        api.OnboardingTutor.destroy();
        assert.equal(dom.body.children.length, 0);
        assert.ok(globalThis.sessionStorage.getItem(key));
        assert.equal(completedEvents, 0);

        api.OnboardingTutor.start(baseConfig());
        api.OnboardingTutor.complete();
        assert.equal(dom.body.children.length, 0);
        assert.equal(globalThis.sessionStorage.getItem(key), null);
        assert.equal(completedEvents, 1);
    });

    it('action registry ejecuta acciones por nombre', () => {
        installDom();
        var api = loadTutor();
        var calls = 0;

        api.AAOnboardingTutorActions.register('test_action', function (ctx) {
            calls++;
            assert.equal(ctx.step.id, 'one');
        });

        api.OnboardingTutor.start(baseConfig({
            steps: [
                { id: 'one', title: 'One', beforeAction: 'test_action', advance: { mode: 'button' } }
            ]
        }));

        assert.equal(calls, 1);
    });
});
