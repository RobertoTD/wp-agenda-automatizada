'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const runnerPath = path.join(__dirname, '../../assets/js/services/executiveClientActionRunner.js');
const runner = require(runnerPath);

describe('executiveClientActionRunner', () => {
    let originalHandlers;
    let originalLocation;
    let handlerRuns;
    let reloadCalls;
    let errorMessages;

    beforeEach(() => {
        originalHandlers = globalThis.LearningActionHandlers;
        originalLocation = globalThis.location;
        handlerRuns = 0;
        reloadCalls = 0;
        errorMessages = [];

        globalThis.location = { href: '' };

        globalThis.LearningActionHandlers = {
            isAvailable: function () {
                return true;
            },
            run: function () {
                handlerRuns += 1;
                return Promise.resolve();
            }
        };
    });

    afterEach(() => {
        if (originalHandlers === undefined) {
            delete globalThis.LearningActionHandlers;
        } else {
            globalThis.LearningActionHandlers = originalHandlers;
        }

        if (originalLocation === undefined) {
            delete globalThis.location;
        } else {
            globalThis.location = originalLocation;
        }
    });

    it('navigate asigna location.href con URL válida', async () => {
        await runner.run({ type: 'navigate', url: 'https://example.test/settings' });

        assert.equal(globalThis.location.href, 'https://example.test/settings');
    });

    it('navigate ignora URL vacía sin error', async () => {
        await assert.doesNotReject(async function () {
            await runner.run({ type: 'navigate', url: '   ' });
        });

        assert.equal(globalThis.location.href, '');
    });

    it('handler invoca LearningActionHandlers.run', async () => {
        await runner.run({
            type: 'handler',
            handler: 'pwa.install',
            origin_key: 'install_pwa',
            task_id: '7',
            source: 'system',
            label: 'Instalar'
        });

        assert.equal(handlerRuns, 1);
    });

    it('handler no disponible no rompe', async () => {
        globalThis.LearningActionHandlers = {
            isAvailable: function () {
                return false;
            },
            run: function () {
                handlerRuns += 1;
                return Promise.resolve();
            }
        };

        await assert.doesNotReject(async function () {
            await runner.run({
                type: 'handler',
                handler: 'pwa.install',
                origin_key: 'install_pwa',
                task_id: '7'
            });
        });

        assert.equal(handlerRuns, 0);
    });

    it('handler con reload:true ejecuta onReload', async () => {
        globalThis.LearningActionHandlers = {
            isAvailable: function () {
                return true;
            },
            run: function () {
                handlerRuns += 1;
                return Promise.resolve({ reload: true });
            }
        };

        await runner.run({
            type: 'handler',
            handler: 'pwa.install',
            origin_key: 'install_pwa',
            task_id: '7'
        }, {
            onReload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(handlerRuns, 1);
        assert.equal(reloadCalls, 1);
    });

    it('handler rechazado llama showError', async () => {
        globalThis.LearningActionHandlers = {
            isAvailable: function () {
                return true;
            },
            run: function () {
                return Promise.reject(new Error('falló handler'));
            }
        };

        await runner.run({
            type: 'handler',
            handler: 'pwa.install',
            origin_key: 'install_pwa',
            task_id: '7'
        }, {
            showError: function (message) {
                errorMessages.push(message);
            }
        });

        assert.deepEqual(errorMessages, ['falló handler']);
    });

    it('clientAction nulo es no-op', async () => {
        await assert.doesNotReject(async function () {
            await runner.run(null);
            await runner.run(undefined);
        });

        assert.equal(handlerRuns, 0);
    });
});
