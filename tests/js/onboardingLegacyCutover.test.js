'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const welcomePath = path.join(
    __dirname,
    '../../includes/admin/ui/modals/onboarding/onboardingWelcome.js'
);
const activationCoordinatorPath = path.join(
    __dirname,
    '../../includes/admin/ui/modals/onboarding/onboardingActivationCoordinator.js'
);

const welcomeSrc = fs.readFileSync(welcomePath, 'utf8');
const activationCoordinatorSrc = fs.readFileSync(activationCoordinatorPath, 'utf8');

function loadWelcomeEnv() {
    var openCalls = 0;
    var documentEvents = {};

    var context = {
        window: {},
        document: {
            addEventListener: function (type, handler) {
                documentEvents[type] = documentEvents[type] || [];
                documentEvents[type].push(handler);
            },
            removeEventListener: function () {},
            getElementById: function () {
                return {
                    content: {
                        cloneNode: function () {
                            return {
                                appendChild: function () {}
                            };
                        }
                    }
                };
            },
            createElement: function () {
                return {
                    appendChild: function () {},
                    innerHTML: '<p>welcome</p>'
                };
            },
            dispatchEvent: function (event) {
                (documentEvents[event.type] || []).forEach(function (handler) {
                    handler(event);
                });
                return true;
            }
        },
        localStorage: {
            getItem: function () { return null; },
            setItem: function () {}
        },
        console: { warn: function () {}, error: function () {} },
        setTimeout: function (fn) { fn(); return 0; }
    };

    context.window = context;
    context.window.AAAdmin = {
        openModal: function () {
            openCalls++;
        }
    };
    context.document.createElement = function () {
        return {
            appendChild: function () {},
            innerHTML: '<p>welcome</p>'
        };
    };

    vm.runInNewContext(welcomeSrc, context, { filename: welcomePath });

    return {
        OnboardingWelcomeModal: context.window.OnboardingWelcomeModal,
        dispatchDomContentLoaded: function () {
            context.document.dispatchEvent({ type: 'DOMContentLoaded' });
        },
        metrics: {
            get openCalls() { return openCalls; }
        }
    };
}

function loadActivationEnv() {
    var fetchStatusCalls = 0;
    var guideOpenCalls = 0;
    var documentEvents = {};

    var context = {
        window: {},
        document: {
            addEventListener: function (type, handler) {
                documentEvents[type] = documentEvents[type] || [];
                documentEvents[type].push(handler);
            },
            removeEventListener: function () {},
            getElementById: function () {
                return { classList: { contains: function () { return true; } } };
            },
            dispatchEvent: function (event) {
                (documentEvents[event.type] || []).forEach(function (handler) {
                    handler(event);
                });
                return true;
            }
        },
        localStorage: {
            getItem: function () { return '1'; },
            setItem: function () {}
        },
        sessionStorage: {
            getItem: function () { return null; },
            setItem: function () {}
        },
        console: { warn: function () {}, error: function () {} },
        setTimeout: function (fn) { fn(); return 0; },
        clearTimeout: function () {}
    };

    context.window = context;
    context.window.OnboardingStatusService = {
        fetchStatus: function () {
            fetchStatusCalls++;
            return Promise.resolve({
                show_activation_guide: true,
                activation_complete: false,
                setup_complete: false,
                next_step: 'calendar'
            });
        }
    };
    context.window.OnboardingActivationGuide = {
        open: function () {
            guideOpenCalls++;
            return Promise.resolve({
                show_activation_guide: true,
                activation_complete: false
            });
        }
    };

    vm.runInNewContext(activationCoordinatorSrc, context, { filename: activationCoordinatorPath });

    return {
        OnboardingActivationCoordinator: context.window.OnboardingActivationCoordinator,
        dispatchDomContentLoaded: function () {
            context.document.dispatchEvent({ type: 'DOMContentLoaded' });
        },
        dispatchDocumentEvent: function (type, detail) {
            context.document.dispatchEvent({ type: type, detail: detail || {} });
        },
        metrics: {
            get fetchStatusCalls() { return fetchStatusCalls; },
            get guideOpenCalls() { return guideOpenCalls; }
        }
    };
}

describe('Onboarding legacy cutover E3b', () => {
    it('welcome source no registra auto-init en DOMContentLoaded', () => {
        assert.equal(welcomeSrc.includes('OnboardingWelcomeModal.init()'), true);
        assert.equal(
            welcomeSrc.includes("document.addEventListener('DOMContentLoaded', function () {\n        OnboardingWelcomeModal.init();"),
            false
        );
    });

    it('welcome no autoabre al cargar DOMContentLoaded', () => {
        var env = loadWelcomeEnv();

        env.dispatchDomContentLoaded();
        assert.equal(env.metrics.openCalls, 0);
    });

    it('welcome mantiene apertura manual', () => {
        var env = loadWelcomeEnv();

        assert.equal(typeof env.OnboardingWelcomeModal.open, 'function');
        assert.equal(typeof env.OnboardingWelcomeModal.init, 'function');
        assert.equal(typeof env.OnboardingWelcomeModal.tryAutoShow, 'function');
    });

    it('activation coordinator desactiva auto-open inicial', () => {
        assert.equal(activationCoordinatorSrc.includes('LEGACY_AUTO_OPEN_ENABLED = false'), true);
    });

    it('activation guide no autoabre al cargar', async () => {
        var env = loadActivationEnv();

        env.dispatchDomContentLoaded();
        await new Promise(function (resolve) { setImmediate(resolve); });

        assert.equal(env.metrics.fetchStatusCalls, 0);
        assert.equal(env.metrics.guideOpenCalls, 0);
    });

    it('eventos de mutación no reabren activation guide automáticamente', async () => {
        var env = loadActivationEnv();

        env.dispatchDomContentLoaded();
        env.dispatchDocumentEvent('aa:reservation:created', { source: 'fastappointment' });
        env.dispatchDocumentEvent('aa:notifications:refresh', { source: 'fastappointment-created' });
        env.dispatchDocumentEvent('aa:client:saved', { isEdit: false });
        env.dispatchDocumentEvent('aa:onboarding:setup-mutated', { source: 'calendar' });

        await new Promise(function (resolve) { setImmediate(resolve); });

        assert.equal(env.metrics.guideOpenCalls, 0);
    });

    it('activation guide mantiene apertura manual via tryAutoOpen', async () => {
        var env = loadActivationEnv();

        await env.OnboardingActivationCoordinator.tryAutoOpen();

        assert.equal(env.metrics.fetchStatusCalls, 1);
        assert.equal(env.metrics.guideOpenCalls, 1);
    });
});
