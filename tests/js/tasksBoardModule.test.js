'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/tasks-board-module.js');
const hooks = require(modulePath);

describe('tasks-board-module MC13B hooks', () => {
    let originalFeedApi;
    let reloadCalls;

    beforeEach(() => {
        originalFeedApi = globalThis.AAExecutableUserListsVisibleFeed;
        reloadCalls = 0;
    });

    afterEach(() => {
        if (originalFeedApi === undefined) {
            delete globalThis.AAExecutableUserListsVisibleFeed;
        } else {
            globalThis.AAExecutableUserListsVisibleFeed = originalFeedApi;
        }
    });

    it('reloadExecutableUserFeedBestEffort no hace nada sin API', async () => {
        delete globalThis.AAExecutableUserListsVisibleFeed;

        await hooks.reloadExecutableUserFeedBestEffort();
    });

    it('reloadExecutableUserFeedBestEffort llama reload si feed visible está activo', async () => {
        globalThis.AAExecutableUserListsVisibleFeed = {
            isEnabled: function () {
                return true;
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        };

        await hooks.reloadExecutableUserFeedBestEffort();

        assert.equal(reloadCalls, 1);
    });

    it('reloadExecutableUserFeedBestEffort ignora si feed visible está off', async () => {
        globalThis.AAExecutableUserListsVisibleFeed = {
            isEnabled: function () {
                return false;
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        };

        await hooks.reloadExecutableUserFeedBestEffort();

        assert.equal(reloadCalls, 0);
    });

    it('reloadExecutableUserFeedBestEffort ignora errores del feed executable', async () => {
        globalThis.AAExecutableUserListsVisibleFeed = {
            isEnabled: function () {
                return true;
            },
            reload: function () {
                return Promise.reject(new Error('feed failed'));
            }
        };

        await hooks.reloadExecutableUserFeedBestEffort();
    });
});

describe('tasks-board-module wiring MC13B', () => {
    it('expone window.AATasksBoard.reload en el módulo', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /globalRoot\.AATasksBoard = \{/);
        assert.match(moduleSrc, /reload: function \(options\)/);
    });

    it('post-mutación usa reloadBoardAfterMutation', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /function reloadBoardAfterMutation/);
        assert.match(moduleSrc, /reloadExecutableUserFeedBestEffort/);
        assert.match(moduleSrc, /return reloadBoardAfterMutation\(\{ silent: true \}\)/);
    });
});
