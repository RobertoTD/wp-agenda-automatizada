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

describe('tasks-board-module create task classification', () => {
    const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
    const servicePath = path.join(__dirname, '../../assets/js/services/tasksService.js');

    it('modal incluye Opciones, Clasificación y default_bucket', () => {
        const fs = require('node:fs');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /id="aa-task-form-options"[\s\S]*?Opciones[\s\S]*?Clasificación/);
        assert.match(indexSrc, /id="aa-task-form-default-bucket"/);
        assert.match(indexSrc, /name="default_bucket"/);
        assert.match(indexSrc, /value="primary"[^>]*>Principal</);
        assert.match(indexSrc, /value="secondary"[^>]*>Secundaria</);
    });

    it('board module propaga default_bucket secundario al crear', () => {
        const fs = require('node:fs');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /aa-task-form-default-bucket/);
        assert.match(moduleSrc, /createPayload\.default_bucket = 'secondary'/);
        assert.match(moduleSrc, /function collapseTaskFormOptions/);
    });

    it('TasksService propaga default_bucket en createTask', () => {
        const fs = require('node:fs');
        const serviceSrc = fs.readFileSync(servicePath, 'utf8');

        assert.match(serviceSrc, /default_bucket/);
        assert.match(serviceSrc, /fields\.default_bucket = payload\.default_bucket/);
    });
});

describe('tasks-board-module MC1 appointment_actions', () => {
    it('expone helpers de filtrado para tests', () => {
        assert.equal(typeof hooks.isUserManualTaskListDestination, 'function');
        assert.equal(typeof hooks.filterUserManualTaskListDestinations, 'function');
        assert.equal(typeof hooks.isAppointmentActionsList, 'function');
        assert.equal(typeof hooks.filterListsForBoardRender, 'function');
    });

    it('isUserManualTaskListDestination solo acepta user/user', () => {
        assert.equal(hooks.isUserManualTaskListDestination({
            source_category: 'user',
            managed_by: 'user'
        }), true);
        assert.equal(hooks.isUserManualTaskListDestination({
            source_category: 'agenda_app',
            managed_by: 'developer',
            origin_key: 'appointment_actions'
        }), false);
    });

    it('filterListsForBoardRender omite appointment_actions sin buckets activos', () => {
        var payload = hooks.filterListsForBoardRender({
            lists: [
                {
                    id: 88,
                    source_category: 'agenda_app',
                    origin_key: 'appointment_actions',
                    title: 'Acciones de citas'
                },
                {
                    id: 7,
                    source_category: 'user',
                    managed_by: 'user',
                    title: 'Mi lista'
                }
            ],
            tasks: [],
            organization: {
                task_bucket_order_by_list: {
                    88: { primary: [], secondary: [] },
                    7: { primary: [], secondary: [] }
                }
            }
        });

        assert.equal(payload.lists.length, 1);
        assert.equal(payload.lists[0].id, 7);
    });

    it('filterListsForBoardRender incluye appointment_actions con tareas en buckets activos', () => {
        var payload = hooks.filterListsForBoardRender({
            lists: [
                {
                    id: 88,
                    source_category: 'agenda_app',
                    origin_key: 'appointment_actions',
                    title: 'Acciones de citas'
                }
            ],
            tasks: [{ id: 42, list_id: 88, status: 'pending' }],
            organization: {
                task_bucket_order_by_list: {
                    88: { primary: [42], secondary: [] }
                }
            }
        });

        assert.equal(payload.lists.length, 1);
        assert.equal(payload.lists[0].origin_key, 'appointment_actions');
    });

    it('filterUserManualTaskListDestinations excluye appointment_actions del selector', () => {
        var selectable = hooks.filterUserManualTaskListDestinations([
            {
                id: 88,
                source_category: 'agenda_app',
                managed_by: 'developer',
                origin_key: 'appointment_actions'
            },
            {
                id: 7,
                source_category: 'user',
                managed_by: 'user'
            }
        ]);

        assert.equal(selectable.length, 1);
        assert.equal(selectable[0].id, 7);
    });
});

describe('tasks-board-module MC5-lite due_at picker min', () => {
    const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');

    it('form create tiene novalidate para permitir due_at pasado manual', () => {
        const fs = require('node:fs');
        const indexSrc = fs.readFileSync(indexPath, 'utf8');
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(indexSrc, /id="aa-task-form"[^>]*novalidate/);
        assert.match(moduleSrc, /function todayMinForDatetimeLocal/);
        assert.match(moduleSrc, /T00:00/);
        assert.match(moduleSrc, /applyTaskDueAtInputMin\('aa-task-form-due-at'\)/);
        assert.match(moduleSrc, /applyTaskDueAtInputMin\('aa-task-form-execution-available-at'\)/);
        assert.match(moduleSrc, /modalId === 'aa-task-modal'/);
        assert.match(moduleSrc, /if \(!listId\)/);
        assert.match(moduleSrc, /if \(!title\)/);
    });
});

describe('tasks-board-module execution_available_at UI', () => {
    const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
    const fs = require('node:fs');

    it('modal create incluye Realizar a partir de antes de Vencimiento', () => {
        const indexSrc = fs.readFileSync(indexPath, 'utf8');
        const executionPos = indexSrc.indexOf('aa-task-form-execution-available-at');
        const duePos = indexSrc.indexOf('aa-task-form-due-at');

        assert.notEqual(executionPos, -1);
        assert.ok(executionPos < duePos);
        assert.match(indexSrc, />Realizar a partir de \(opcional\)</);
        assert.match(indexSrc, /La tarea se volverá pertinente para realizarse desde este momento\./);
        assert.match(indexSrc, /id="aa-task-form-execution-available-at"/);
        assert.match(indexSrc, /aa-task-form-execution-available-at[\s\S]*?type="datetime-local"/);
    });

    it('submitTaskForm propaga execution_available_at al crear', () => {
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /aa-task-form-execution-available-at/);
        assert.match(moduleSrc, /execution_available_at: normalizeDueAtInput\(executionAvailableInput/);
        assert.match(moduleSrc, /service\.createTask\(createPayload\)/);
    });
});
