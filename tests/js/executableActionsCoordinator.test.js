'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach } = require('node:test');
const path = require('node:path');

const coordinatorPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/learning/executable-actions-coordinator.js'
);
const coordinatorApi = require(coordinatorPath);

function createButton(attrs) {
    var values = Object.assign({
        'data-tasks-action': 'complete',
        'data-task-id': '10'
    }, attrs || {});

    return {
        disabled: false,
        classList: {
            add: function () {},
            remove: function () {}
        },
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(values, name) ? values[name] : null;
        },
        closest: function () {
            return this;
        }
    };
}

function createRoot(button) {
    return {
        contains: function (node) {
            return node === button;
        },
        querySelectorAll: function () {
            return [button];
        }
    };
}

function createEvent(button) {
    var prevented = false;
    var stopped = false;

    return {
        target: button,
        prevented: function () {
            return prevented;
        },
        stopped: function () {
            return stopped;
        },
        preventDefault: function () {
            prevented = true;
        },
        stopPropagation: function () {
            stopped = true;
        }
    };
}

describe('ExecutableActionsCoordinator', () => {
    /** @type {ReturnType<typeof coordinatorApi.createCoordinator>} */
    let coordinator;
    let tasksCalls;
    let reloadCalls;
    let errorMessage;
    let confirmResult;

    beforeEach(() => {
        tasksCalls = [];
        reloadCalls = 0;
        errorMessage = null;
        confirmResult = true;

        coordinator = coordinatorApi.createCoordinator({
            getTasksService: function () {
                return {
                    changeTaskStatus: function (taskId, status) {
                        tasksCalls.push({ method: 'changeTaskStatus', taskId: taskId, status: status });
                        return Promise.resolve({});
                    },
                    archiveTaskList: function (listId) {
                        tasksCalls.push({ method: 'archiveTaskList', listId: listId });
                        return Promise.resolve({});
                    }
                };
            },
            confirm: function () {
                return confirmResult;
            }
        });

        coordinator.resetPending();
    });

    it('complete llama TasksService.changeTaskStatus(taskId, done)', async () => {
        var button = createButton({
            'data-tasks-action': 'complete',
            'data-task-id': '42'
        });
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            },
            showError: function (message) {
                errorMessage = message;
            }
        });

        assert.equal(tasksCalls.length, 1);
        assert.deepEqual(tasksCalls[0], {
            method: 'changeTaskStatus',
            taskId: '42',
            status: 'done'
        });
        assert.equal(reloadCalls, 1);
        assert.equal(event.stopped(), true);
        assert.equal(event.prevented(), true);
    });

    it('pending llama TasksService.changeTaskStatus(taskId, pending)', async () => {
        var button = createButton({
            'data-tasks-action': 'pending',
            'data-task-id': '42'
        });
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.deepEqual(tasksCalls[0], {
            method: 'changeTaskStatus',
            taskId: '42',
            status: 'pending'
        });
    });

    it('archive-list llama TasksService.archiveTaskList(listId) si confirm es true', async () => {
        confirmResult = true;
        var button = createButton({
            'data-tasks-action': 'archive-list',
            'data-task-id': null,
            'data-list-id': '7'
        });
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.deepEqual(tasksCalls[0], {
            method: 'archiveTaskList',
            listId: '7'
        });
        assert.equal(reloadCalls, 1);
    });

    it('archive-list no llama servicio si confirm es false', async () => {
        confirmResult = false;
        var button = createButton({
            'data-tasks-action': 'archive-list',
            'data-list-id': '7'
        });
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(tasksCalls.length, 0);
        assert.equal(reloadCalls, 0);
    });

    it('tras éxito llama callback reload', async () => {
        var button = createButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(reloadCalls, 1);
    });

    it('error llama callback showError', async () => {
        coordinator = coordinatorApi.createCoordinator({
            getTasksService: function () {
                return {
                    changeTaskStatus: function () {
                        return Promise.reject(new Error('fallo de prueba'));
                    }
                };
            }
        });
        coordinator.resetPending();

        var button = createButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            },
            showError: function (message) {
                errorMessage = message;
            }
        });

        assert.equal(errorMessage, 'fallo de prueba');
        assert.equal(reloadCalls, 0);
    });

    it('pending guard evita doble ejecución', async () => {
        var resolveFirst;
        var serviceCalls = 0;
        var button = createButton();
        var root = createRoot(button);
        var event = createEvent(button);
        var ctx = {
            root: root,
            reload: function () {
                return Promise.resolve();
            }
        };

        coordinator = coordinatorApi.createCoordinator({
            getTasksService: function () {
                return {
                    changeTaskStatus: function () {
                        serviceCalls += 1;
                        return new Promise(function (resolve) {
                            resolveFirst = resolve;
                        });
                    }
                };
            }
        });
        coordinator.resetPending();

        var first = coordinator.handleClick(event, ctx);
        var second = coordinator.handleClick(event, ctx);

        resolveFirst({});
        await first;
        await second;

        assert.equal(serviceCalls, 1);
    });

    it('stopPropagation se invoca al manejar acción tasks', async () => {
        var button = createButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            reload: function () {
                return Promise.resolve();
            }
        });

        assert.equal(event.stopped(), true);
        assert.equal(event.prevented(), true);
    });

    it('ignora data-learning-action en MC12A', async () => {
        var button = {
            disabled: false,
            getAttribute: function (name) {
                if (name === 'data-tasks-action') {
                    return null;
                }

                return null;
            },
            closest: function () {
                return null;
            }
        };
        var root = createRoot(button);
        var event = createEvent(button);

        var handled = await coordinator.handleClick(event, {
            root: root,
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(handled, false);
        assert.equal(reloadCalls, 0);
    });
});
