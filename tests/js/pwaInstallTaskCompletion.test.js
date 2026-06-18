'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const handlersRealPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/learning/learning-action-handlers.js'
);

function pendingInstallPayload(taskId) {
    return {
        lists: [{
            buckets: [{
                items: [{
                    id: String(taskId),
                    source: 'system',
                    source_category: 'agenda_app',
                    origin_key: 'install_pwa',
                    status: 'pending',
                    state: { completed: false }
                }]
            }]
        }]
    };
}

function createBeforeInstallPromptEvent(outcome) {
    var evt = new Event('beforeinstallprompt');

    evt.preventDefault = function () {};
    evt.prompt = function () {
        return Promise.resolve();
    };
    evt.userChoice = Promise.resolve({ outcome: outcome });

    return evt;
}

function loadHandlers() {
    delete require.cache[handlersRealPath];
    require(handlersRealPath);
}

describe('pwa.install task completion', () => {
    let originalMatchMedia;
    let originalAddEventListener;
    let originalDispatchEvent;
    let statusCalls;
    let reloadCalls;
    let eventListeners;

    beforeEach(() => {
        originalMatchMedia = globalThis.matchMedia;
        originalAddEventListener = globalThis.addEventListener;
        originalDispatchEvent = globalThis.dispatchEvent;
        statusCalls = [];
        reloadCalls = [];
        eventListeners = {};

        globalThis.matchMedia = function () {
            return { matches: false };
        };

        globalThis.addEventListener = function (type, listener) {
            if (!eventListeners[type]) {
                eventListeners[type] = [];
            }

            eventListeners[type].push(listener);
        };

        globalThis.dispatchEvent = function (event) {
            var listeners = eventListeners[event.type] || [];

            listeners.slice().forEach(function (listener) {
                listener(event);
            });

            return true;
        };

        globalThis.TasksService = {
            changeTaskStatus: function (taskId, status) {
                statusCalls.push({ taskId: taskId, status: status });
                return Promise.resolve({ success: true });
            }
        };

        globalThis.AAExecutableUserListsVisibleFeed = {
            reload: function () {
                reloadCalls.push(1);
                return Promise.resolve();
            }
        };

        loadHandlers();
    });

    afterEach(() => {
        globalThis.matchMedia = originalMatchMedia;

        if (originalAddEventListener) {
            globalThis.addEventListener = originalAddEventListener;
        } else {
            delete globalThis.addEventListener;
        }

        if (originalDispatchEvent) {
            globalThis.dispatchEvent = originalDispatchEvent;
        } else {
            delete globalThis.dispatchEvent;
        }

        delete globalThis.TasksService;
        delete globalThis.AAExecutableUserListsVisibleFeed;
        delete globalThis.LearningActionHandlers;
        delete require.cache[handlersRealPath];
    });

    it('refreshPendingInstallTaskFromPayload conserva id numerico pendiente', () => {
        globalThis.LearningActionHandlers.refreshPendingInstallTaskFromPayload(pendingInstallPayload(501));
        globalThis.dispatchEvent(new Event('appinstalled'));

        return Promise.resolve().then(function () {
            assert.equal(statusCalls.length, 1);
            assert.deepEqual(statusCalls[0], { taskId: '501', status: 'done' });
            assert.equal(reloadCalls.length, 1);
        });
    });

    it('appinstalled sin id pendiente no muta', () => {
        globalThis.LearningActionHandlers.refreshPendingInstallTaskFromPayload({ lists: [] });
        globalThis.dispatchEvent(new Event('appinstalled'));

        return Promise.resolve().then(function () {
            assert.equal(statusCalls.length, 0);
            assert.equal(reloadCalls.length, 0);
        });
    });

    it('accepted no completa la tarea', async () => {
        globalThis.LearningActionHandlers.refreshPendingInstallTaskFromPayload(pendingInstallPayload(501));
        globalThis.dispatchEvent(createBeforeInstallPromptEvent('accepted'));

        var result = await globalThis.LearningActionHandlers.run(
            { type: 'handler', handler: 'pwa.install' },
            {
                id: '501',
                source: 'system',
                source_category: 'agenda_app',
                origin_key: 'install_pwa',
                status: 'pending',
                state: { completed: false }
            },
            {}
        );

        assert.deepEqual(result, { completed: false, outcome: 'accepted' });
        assert.equal(statusCalls.length, 0);
    });

    it('dismissed no completa la tarea', async () => {
        globalThis.LearningActionHandlers.refreshPendingInstallTaskFromPayload(pendingInstallPayload(501));
        globalThis.dispatchEvent(createBeforeInstallPromptEvent('dismissed'));

        var result = await globalThis.LearningActionHandlers.run(
            { type: 'handler', handler: 'pwa.install' },
            {
                id: '501',
                source: 'system',
                source_category: 'agenda_app',
                origin_key: 'install_pwa',
                status: 'pending',
                state: { completed: false }
            },
            {}
        );

        assert.deepEqual(result, { completed: false, outcome: 'dismissed' });
        assert.equal(statusCalls.length, 0);
    });

    it('standalone con tarea pendiente completa una vez por ciclo de carga', () => {
        globalThis.matchMedia = function (query) {
            return { matches: query === '(display-mode: standalone)' };
        };

        globalThis.LearningActionHandlers.beginInstallTaskFeedLoadCycle();
        globalThis.LearningActionHandlers.refreshPendingInstallTaskFromPayload(pendingInstallPayload(502));

        return globalThis.LearningActionHandlers.reconcileStandaloneInstallTaskIfNeeded()
            .then(function () {
                assert.equal(statusCalls.length, 1);
                assert.deepEqual(statusCalls[0], { taskId: '502', status: 'done' });
                assert.equal(reloadCalls.length, 1);

                return globalThis.LearningActionHandlers.reconcileStandaloneInstallTaskIfNeeded();
            })
            .then(function () {
                assert.equal(statusCalls.length, 1);
            });
    });

    it('carga normal no completa en reconcile standalone', () => {
        globalThis.LearningActionHandlers.beginInstallTaskFeedLoadCycle();
        globalThis.LearningActionHandlers.refreshPendingInstallTaskFromPayload(pendingInstallPayload(503));

        return globalThis.LearningActionHandlers.reconcileStandaloneInstallTaskIfNeeded()
            .then(function () {
                assert.equal(statusCalls.length, 0);
                assert.equal(reloadCalls.length, 0);
            });
    });

    it('completionInFlight evita mutaciones automaticas duplicadas', () => {
        var resolveFirst;

        globalThis.TasksService.changeTaskStatus = function (taskId, status) {
            statusCalls.push({ taskId: taskId, status: status });

            return new Promise(function (resolve) {
                resolveFirst = resolve;
            });
        };

        loadHandlers();
        globalThis.LearningActionHandlers.refreshPendingInstallTaskFromPayload(pendingInstallPayload(504));
        globalThis.dispatchEvent(new Event('appinstalled'));
        globalThis.dispatchEvent(new Event('appinstalled'));

        assert.equal(statusCalls.length, 1);

        resolveFirst({ success: true });

        return Promise.resolve().then(function () {
            assert.equal(reloadCalls.length, 1);
        });
    });

    it('run captura task_id desde ctx sin completar', async () => {
        globalThis.LearningActionHandlers.refreshPendingInstallTaskFromPayload({ lists: [] });

        await globalThis.LearningActionHandlers.run(
            { type: 'handler', handler: 'pwa.install' },
            null,
            {
                item: {
                    id: '505',
                    source: 'system',
                    source_category: 'agenda_app',
                    origin_key: 'install_pwa',
                    status: 'pending',
                    state: { completed: false }
                }
            }
        );

        assert.equal(statusCalls.length, 0);

        globalThis.dispatchEvent(new Event('appinstalled'));

        return Promise.resolve().then(function () {
            assert.equal(statusCalls.length, 1);
            assert.deepEqual(statusCalls[0], { taskId: '505', status: 'done' });
        });
    });

    it('shouldShowRecommendation muestra tarea pendiente en standalone sin ocultar', () => {
        globalThis.matchMedia = function (query) {
            return { matches: query === '(display-mode: standalone)' };
        };

        loadHandlers();

        var handler = globalThis.LearningActionHandlers.get('pwa.install');
        var action = { type: 'handler', handler: 'pwa.install' };
        var item = { origin_key: 'install_pwa', status: 'pending', state: { completed: false } };

        assert.equal(handler.shouldHideRecommendation, undefined);
        assert.equal(globalThis.LearningActionHandlers.shouldShowRecommendation(action, item), true);
        assert.equal(globalThis.LearningActionHandlers.isAvailable(action, item), false);
    });

    it('isAvailable sigue dependiendo de canInstallNow sin ocultar la card', () => {
        globalThis.dispatchEvent(createBeforeInstallPromptEvent('accepted'));

        var handler = globalThis.LearningActionHandlers.get('pwa.install');
        var action = { type: 'handler', handler: 'pwa.install' };
        var item = { origin_key: 'install_pwa' };

        assert.equal(globalThis.LearningActionHandlers.isAvailable(action, item), true);
        assert.equal(globalThis.LearningActionHandlers.shouldShowRecommendation(action, item), true);
    });

    it('shouldShowRecommendation sigue delegando hide en handlers que lo implementen', () => {
        globalThis.LearningActionHandlers.register('test.hide.handler', {
            shouldHideRecommendation: function () {
                return true;
            },
            isAvailable: function () {
                return true;
            },
            run: function () {
                return Promise.resolve({});
            }
        });

        assert.equal(
            globalThis.LearningActionHandlers.shouldShowRecommendation(
                { type: 'handler', handler: 'test.hide.handler' },
                {}
            ),
            false
        );
    });
});
