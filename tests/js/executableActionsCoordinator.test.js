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
        closest: function (selector) {
            if (selector === coordinatorApi.TASK_ACTION_SELECTOR && values['data-tasks-action']) {
                return this;
            }

            return null;
        }
    };
}

function createLearningButton(attrs) {
    var values = Object.assign({
        'data-learning-action': 'dismiss',
        'data-recommendation-key': 'install_pwa'
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
        closest: function (selector) {
            if (selector === coordinatorApi.LEARNING_ACTION_SELECTOR && values['data-learning-action']) {
                return this;
            }

            return null;
        }
    };
}

function createRoot(buttons) {
    var nodes = Array.isArray(buttons) ? buttons : [buttons];

    return {
        contains: function (node) {
            return nodes.indexOf(node) !== -1;
        },
        querySelectorAll: function (selector) {
            if (selector === coordinatorApi.ACTIONABLE_BUTTON_SELECTOR) {
                return nodes;
            }

            return [];
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

function createCoordinatorFactory(options) {
    options = options || {};

    return coordinatorApi.createCoordinator({
        getTasksService: options.getTasksService || function () {
            return {
                changeTaskStatus: function (taskId, status) {
                    options.tasksCalls.push({ method: 'changeTaskStatus', taskId: taskId, status: status });
                    return Promise.resolve({});
                },
                archiveTaskList: function (listId) {
                    options.tasksCalls.push({ method: 'archiveTaskList', listId: listId });
                    return Promise.resolve({});
                },
                archiveTask: function (taskId) {
                    options.tasksCalls.push({ method: 'archiveTask', taskId: taskId });
                    return Promise.resolve({});
                },
                deferTask: function (taskId) {
                    options.tasksCalls.push({ method: 'deferTask', taskId: taskId });
                    return Promise.resolve({});
                },
                dismissTask: function (taskId) {
                    options.tasksCalls.push({ method: 'dismissTask', taskId: taskId });
                    return Promise.resolve({});
                }
            };
        },
        getLearningService: options.getLearningService || function () {
            return {
                ignoreRecommendation: function (key) {
                    options.learningCalls.push({ method: 'ignoreRecommendation', key: key });
                    return Promise.resolve({});
                },
                dismissRecommendation: function (key) {
                    options.learningCalls.push({ method: 'dismissRecommendation', key: key });
                    return Promise.resolve({});
                },
                completeRecommendation: function (key) {
                    options.learningCalls.push({ method: 'completeRecommendation', key: key });
                    return Promise.resolve({});
                }
            };
        },
        getLearningActionHandlers: options.getLearningActionHandlers || function () {
            return options.learningActionHandlers || null;
        },
        confirm: options.confirm || function () {
            return options.confirmResult !== false;
        }
    });
}

function createInstallPwaItem(visibleActionsOverride) {
    return {
        id: 'install_pwa',
        source: 'system',
        origin_key: 'install_pwa',
        visible_actions: visibleActionsOverride !== undefined
            ? visibleActionsOverride
            : [{
                key: 'pwa.install',
                type: 'handler',
                handler: 'pwa.install',
                label: 'Instalar'
            }]
    };
}

function createPrimaryHandlerButton(attrs) {
    return createLearningButton(Object.assign({
        'data-learning-action': 'primary-handler',
        'data-recommendation-key': 'install_pwa',
        'data-learning-handler': 'pwa.install'
    }, attrs || {}));
}

describe('ExecutableActionsCoordinator', () => {
    /** @type {ReturnType<typeof coordinatorApi.createCoordinator>} */
    let coordinator;
    let tasksCalls;
    let learningCalls;
    let handlerCalls;
    let reloadCalls;
    let errorMessage;
    let confirmResult;

    beforeEach(() => {
        tasksCalls = [];
        learningCalls = [];
        handlerCalls = [];
        reloadCalls = 0;
        errorMessage = null;
        confirmResult = true;

        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            confirmResult: confirmResult
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
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            confirmResult: false
        });
        coordinator.resetPending();

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

    it('archive-task llama TasksService.archiveTask(taskId) si confirm es true', async () => {
        var button = createButton({
            'data-tasks-action': 'archive-task',
            'data-task-id': '42',
            'data-list-id': null
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
            method: 'archiveTask',
            taskId: '42'
        });
        assert.equal(reloadCalls, 1);
    });

    it('archive-task no llama servicio si confirm es false', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            confirmResult: false
        });
        coordinator.resetPending();

        var button = createButton({
            'data-tasks-action': 'archive-task',
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

        assert.equal(tasksCalls.length, 0);
        assert.equal(reloadCalls, 0);
    });

    it('[dormido] task defer llama TasksService.deferTask(taskId)', async () => {
        var button = createButton({
            'data-tasks-action': 'defer',
            'data-task-id': '55'
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
            method: 'deferTask',
            taskId: '55'
        });
        assert.equal(learningCalls.length, 0);
        assert.equal(reloadCalls, 1);
        assert.equal(event.stopped(), true);
        assert.equal(event.prevented(), true);
    });

    it('task dismiss llama TasksService.dismissTask(taskId)', async () => {
        var button = createButton({
            'data-tasks-action': 'dismiss',
            'data-task-id': '56'
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
            method: 'dismissTask',
            taskId: '56'
        });
        assert.equal(learningCalls.length, 0);
        assert.equal(reloadCalls, 1);
        assert.equal(event.stopped(), true);
        assert.equal(event.prevented(), true);
    });

    it('[dormido] task defer error llama showError y no reload', async () => {
        coordinator = coordinatorApi.createCoordinator({
            getTasksService: function () {
                return {
                    deferTask: function () {
                        return Promise.reject(new Error('fallo defer task'));
                    }
                };
            }
        });
        coordinator.resetPending();

        var button = createButton({
            'data-tasks-action': 'defer',
            'data-task-id': '55'
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

        assert.equal(errorMessage, 'fallo defer task');
        assert.equal(reloadCalls, 0);
        assert.equal(learningCalls.length, 0);
    });

    it('[dormido] pending guard evita doble ejecución en task defer', async () => {
        var resolveFirst;
        var serviceCalls = 0;
        var button = createButton({
            'data-tasks-action': 'defer',
            'data-task-id': '55'
        });
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
                    deferTask: function () {
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

    it('[dormido] defer llama LearningService.ignoreRecommendation(key)', async () => {
        var button = createLearningButton({
            'data-learning-action': 'defer',
            'data-recommendation-key': 'configure_services'
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

        assert.deepEqual(learningCalls[0], {
            method: 'ignoreRecommendation',
            key: 'configure_services'
        });
        assert.equal(reloadCalls, 1);
        assert.equal(event.stopped(), true);
        assert.equal(event.prevented(), true);
    });

    it('dismiss llama LearningService.dismissRecommendation(key)', async () => {
        var button = createLearningButton({
            'data-learning-action': 'dismiss',
            'data-recommendation-key': 'install_pwa'
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

        assert.deepEqual(learningCalls[0], {
            method: 'dismissRecommendation',
            key: 'install_pwa'
        });
        assert.equal(reloadCalls, 1);
    });

    it('complete llama LearningService.completeRecommendation(key)', async () => {
        var button = createLearningButton({
            'data-learning-action': 'complete',
            'data-recommendation-key': 'install_pwa'
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

        assert.deepEqual(learningCalls[0], {
            method: 'completeRecommendation',
            key: 'install_pwa'
        });
        assert.equal(reloadCalls, 1);
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

    it('error Learning llama callback showError', async () => {
        coordinator = coordinatorApi.createCoordinator({
            getLearningService: function () {
                return {
                    dismissRecommendation: function () {
                        return Promise.reject(new Error('fallo learning'));
                    }
                };
            }
        });
        coordinator.resetPending();

        var button = createLearningButton();
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

        assert.equal(errorMessage, 'fallo learning');
        assert.equal(reloadCalls, 0);
    });

    it('reactivate no llama LearningService', async () => {
        var button = createLearningButton({
            'data-learning-action': 'reactivate',
            'data-recommendation-key': 'install_pwa'
        });
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
        assert.equal(learningCalls.length, 0);
        assert.equal(reloadCalls, 0);
        assert.equal(event.stopped(), false);
    });

    it('primary-handler llama LearningActionHandlers.run(action, item, ctx)', async () => {
        var installItem = createInstallPwaItem();

        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function (action, item, ctx) {
                    handlerCalls.push({ action: action, item: item, ctx: ctx });
                    return Promise.resolve({ completed: false, outcome: 'accepted' });
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function (key) {
                return key === 'install_pwa' ? installItem : null;
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(handlerCalls.length, 1);
        assert.deepEqual(handlerCalls[0].action, {
            type: 'handler',
            label: 'Instalar',
            handler: 'pwa.install'
        });
        assert.equal(handlerCalls[0].item, installItem);
        assert.equal(handlerCalls[0].ctx.key, 'install_pwa');
        assert.equal(learningCalls.length, 0);
        assert.equal(reloadCalls, 0);
        assert.equal(event.stopped(), true);
        assert.equal(event.prevented(), true);
    });

    it('primary-handler no ejecuta si handler no está en visible_actions', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function () {
                    handlerCalls.push({ method: 'run' });
                    return Promise.resolve({});
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem([]);
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(handlerCalls.length, 0);
        assert.equal(reloadCalls, 0);
        assert.equal(learningCalls.length, 0);
    });

    it('primary-handler no ejecuta si item no existe', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function () {
                    handlerCalls.push({ method: 'run' });
                    return Promise.resolve({});
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return null;
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(handlerCalls.length, 0);
        assert.equal(reloadCalls, 0);
    });

    it('primary-handler no ejecuta si registry no existe', async () => {
        coordinator = coordinatorApi.createCoordinator({});
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem();
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(handlerCalls.length, 0);
        assert.equal(reloadCalls, 0);
    });

    it('primary-handler no ejecuta si isAvailable es false', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return false;
                },
                run: function () {
                    handlerCalls.push({ method: 'run' });
                    return Promise.resolve({});
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem();
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(handlerCalls.length, 0);
        assert.equal(reloadCalls, 0);
    });

    it('primary-handler outcome accepted no llama reload', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function () {
                    return Promise.resolve({ completed: false, outcome: 'accepted' });
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem();
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(reloadCalls, 0);
        assert.equal(learningCalls.length, 0);
    });

    it('primary-handler llama reload solo si result.reload es true', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function () {
                    return Promise.resolve({ reload: true });
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem();
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(reloadCalls, 1);
    });

    it('primary-handler reject llama showError', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function () {
                    return Promise.reject(new Error('fallo handler'));
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem();
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            },
            showError: function (message) {
                errorMessage = message;
            }
        });

        assert.equal(errorMessage, 'fallo handler');
        assert.equal(reloadCalls, 0);
    });

    it('primary-handler no llama LearningService.completeRecommendation', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function () {
                    return Promise.resolve({ completed: false, outcome: 'accepted' });
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem();
            },
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        });

        assert.equal(learningCalls.length, 0);
    });

    it('pending guard evita doble ejecución en primary-handler', async () => {
        var resolveFirst;
        var serviceCalls = 0;
        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);
        var ctx = {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem();
            },
            reload: function () {
                return Promise.resolve();
            }
        };

        coordinator = createCoordinatorFactory({
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function () {
                    serviceCalls += 1;
                    return new Promise(function (resolve) {
                        resolveFirst = resolve;
                    });
                }
            }
        });
        coordinator.resetPending();

        var first = coordinator.handleClick(event, ctx);
        var second = coordinator.handleClick(event, ctx);

        resolveFirst({ completed: false, outcome: 'accepted' });
        await first;
        await second;

        assert.equal(serviceCalls, 1);
    });

    it('stopPropagation se invoca al manejar primary-handler', async () => {
        coordinator = createCoordinatorFactory({
            tasksCalls: tasksCalls,
            learningCalls: learningCalls,
            learningActionHandlers: {
                isAvailable: function () {
                    return true;
                },
                run: function () {
                    return Promise.resolve({ completed: false, outcome: 'accepted' });
                }
            }
        });
        coordinator.resetPending();

        var button = createPrimaryHandlerButton();
        var root = createRoot(button);
        var event = createEvent(button);

        await coordinator.handleClick(event, {
            root: root,
            findLearningItem: function () {
                return createInstallPwaItem();
            },
            reload: function () {
                return Promise.resolve();
            }
        });

        assert.equal(event.stopped(), true);
        assert.equal(event.prevented(), true);
    });

    it('pending guard evita doble ejecución en tasks', async () => {
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

    it('pending guard evita doble ejecución en Learning', async () => {
        var resolveFirst;
        var serviceCalls = 0;
        var button = createLearningButton();
        var root = createRoot(button);
        var event = createEvent(button);
        var ctx = {
            root: root,
            reload: function () {
                return Promise.resolve();
            }
        };

        coordinator = coordinatorApi.createCoordinator({
            getLearningService: function () {
                return {
                    dismissRecommendation: function () {
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

    it('stopPropagation se invoca al manejar acción Learning', async () => {
        var button = createLearningButton();
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

    it('setRootButtonsDisabled incluye data-learning-action', () => {
        var taskButton = createButton();
        var learningButton = createLearningButton();
        var root = createRoot([taskButton, learningButton]);

        coordinator.setRootButtonsDisabled(root, true);

        assert.equal(taskButton.disabled, true);
        assert.equal(learningButton.disabled, true);
    });
});

describe('ExecutableActionsCoordinator multi-root init', () => {
    beforeEach(() => {
        coordinatorApi.resetBinding();
    });

    it('init es idempotente por root y permite múltiples roots', () => {
        var listenersA = 0;
        var listenersB = 0;
        var rootA = {
            id: 'aa-executable-lists-root',
            addEventListener: function () {
                listenersA += 1;
            }
        };
        var rootB = {
            id: 'aa-executable-user-lists-root',
            addEventListener: function () {
                listenersB += 1;
            }
        };

        coordinatorApi.init({ root: rootA, reload: function () {} });
        coordinatorApi.init({ root: rootA, reload: function () {} });
        coordinatorApi.init({ root: rootB, reload: function () {} });

        assert.equal(coordinatorApi.isDelegationBound(rootA), true);
        assert.equal(coordinatorApi.isDelegationBound(rootB), true);
        assert.equal(listenersA, 1);
        assert.equal(listenersB, 1);
    });

    it('resetBinding limpia todos los roots', () => {
        var root = {
            id: 'aa-executable-user-lists-root',
            addEventListener: function () {}
        };

        coordinatorApi.init({ root: root, reload: function () {} });
        assert.equal(coordinatorApi.isDelegationBound(root), true);

        coordinatorApi.resetBinding();

        assert.equal(coordinatorApi.isDelegationBound(root), false);
        assert.equal(coordinatorApi.isDelegationBound(), false);
    });
});
