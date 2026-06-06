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
        'data-learning-action': 'defer',
        'data-recommendation-key': 'configure_services'
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
        confirm: options.confirm || function () {
            return options.confirmResult !== false;
        }
    });
}

describe('ExecutableActionsCoordinator', () => {
    /** @type {ReturnType<typeof coordinatorApi.createCoordinator>} */
    let coordinator;
    let tasksCalls;
    let learningCalls;
    let reloadCalls;
    let errorMessage;
    let confirmResult;

    beforeEach(() => {
        tasksCalls = [];
        learningCalls = [];
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

    it('defer llama LearningService.ignoreRecommendation(key)', async () => {
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
                    ignoreRecommendation: function () {
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

    it('primary-handler no llama LearningService', async () => {
        var button = createLearningButton({
            'data-learning-action': 'primary-handler',
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
                    ignoreRecommendation: function () {
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
