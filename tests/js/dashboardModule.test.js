'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const runnerPath = path.join(__dirname, '../../assets/js/services/executiveClientActionRunner.js');
const rendererPath = path.join(__dirname, '../../assets/js/ui/executiveProposalRenderer.js');
const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/dashboard/dashboard-module.js');

globalThis.window = globalThis;

const runner = require(runnerPath);
require(rendererPath);
const hooks = require(modulePath);

function makeClassList(initialClasses) {
    var classes = Array.isArray(initialClasses) ? initialClasses.slice() : [];

    return {
        classes: classes,
        add: function () {
            Array.prototype.forEach.call(arguments, function (cls) {
                if (classes.indexOf(cls) === -1) {
                    classes.push(cls);
                }
            });
        },
        remove: function () {
            var toRemove = Array.prototype.slice.call(arguments);
            classes = classes.filter(function (item) {
                return toRemove.indexOf(item) === -1;
            });
        },
        toggle: function (cls, force) {
            var has = classes.indexOf(cls) !== -1;
            var next = typeof force === 'boolean' ? force : !has;

            if (next && !has) {
                classes.push(cls);
            } else if (!next && has) {
                classes = classes.filter(function (item) {
                    return item !== cls;
                });
            }
        }
    };
}

function makeElement(id, options) {
    var opts = options || {};

    return {
        id: id,
        classList: makeClassList(opts.classes || []),
        innerHTML: opts.innerHTML || '',
        textContent: opts.textContent || '',
        style: opts.style || {},
        disabled: !!opts.disabled
    };
}

function makeButton(attrs) {
    var attributes = Object.assign({
        'data-executive-action': '1',
        'data-executive-task-id': '1',
        'data-executive-action-key': 'complete'
    }, attrs || {});

    return {
        disabled: false,
        classList: makeClassList([]),
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attributes, name) ? attributes[name] : null;
        }
    };
}

describe('dashboard-module executive actions', () => {
    let originalService;
    let originalRunner;
    let postCalls;
    let runnerCalls;
    let getCalls;
    let dom;

    beforeEach(() => {
        originalService = globalThis.AAExecutiveProposalService;
        originalRunner = globalThis.AAExecutiveClientActionRunner;
        postCalls = 0;
        runnerCalls = 0;
        getCalls = 0;

        dom = {
            loading: makeElement('aa-dash-current-task-loading', { classes: [] }),
            empty: makeElement('aa-dash-current-task-empty', { classes: ['hidden'] }),
            error: makeElement('aa-dash-current-task-error', { classes: ['hidden'] }),
            content: makeElement('aa-dash-current-task-content', { classes: ['hidden'] })
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-dash-current-task-loading') {
                    return dom.loading;
                }

                if (id === 'aa-dash-current-task-empty') {
                    return dom.empty;
                }

                if (id === 'aa-dash-current-task-error') {
                    return dom.error;
                }

                if (id === 'aa-dash-current-task-content') {
                    return dom.content;
                }

                return null;
            }
        };

        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                getCalls += 1;
                return Promise.resolve({
                    status: 'ready',
                    focus_list: { title: 'Lista foco' },
                    tasks: [{ slot: 'current', task_id: 9, title: 'Tarea GET' }]
                });
            },
            postExecutiveAction: function (payload) {
                postCalls += 1;

                return Promise.resolve({
                    action: {
                        key: payload.actionKey,
                        mutated: payload.actionKey === 'complete' || payload.actionKey === 'dismiss'
                    },
                    proposal: {
                        status: 'ready',
                        focus_list: { title: 'Lista foco' },
                        tasks: [{ slot: 'current', task_id: 2, title: 'Siguiente', executive_actions: [] }]
                    },
                    client_action: payload.actionKey.indexOf('navigate.') === 0
                        ? { type: 'navigate', url: 'https://example.test/settings' }
                        : payload.actionKey === 'pwa.install'
                            ? {
                                type: 'handler',
                                handler: 'pwa.install',
                                origin_key: 'install_pwa',
                                task_id: payload.taskId,
                                source: 'system',
                                label: 'Instalar'
                            }
                            : null
                });
            }
        };

        globalThis.AAExecutiveClientActionRunner = {
            run: function (clientAction) {
                runnerCalls += 1;
                return runner.run(clientAction);
            }
        };

        globalThis.location = { href: '' };
    });

    afterEach(() => {
        if (originalService === undefined) {
            delete globalThis.AAExecutiveProposalService;
        } else {
            globalThis.AAExecutiveProposalService = originalService;
        }

        if (originalRunner === undefined) {
            delete globalThis.AAExecutiveClientActionRunner;
        } else {
            globalThis.AAExecutiveClientActionRunner = originalRunner;
        }

        delete globalThis.location;
        delete globalThis.document;
    });

    it('navigate invoca runner tras POST', async () => {
        await hooks.handleDashboardExecutiveAction(makeButton({
            'data-executive-action-key': 'navigate.settings'
        }));

        assert.equal(postCalls, 1);
        assert.equal(runnerCalls, 1);
        assert.equal(globalThis.location.href, 'https://example.test/settings');
        assert.equal(getCalls, 0);
        assert.match(dom.content.innerHTML, /Siguiente/);
    });

    it('pwa.install invoca runner tras POST', async () => {
        globalThis.LearningActionHandlers = {
            isAvailable: function () {
                return true;
            },
            run: function () {
                return Promise.resolve();
            }
        };

        await hooks.handleDashboardExecutiveAction(makeButton({
            'data-executive-task-id': '7',
            'data-executive-action-key': 'pwa.install'
        }));

        assert.equal(postCalls, 1);
        assert.equal(runnerCalls, 1);
        assert.equal(getCalls, 0);
    });

    it('complete actualiza tarjeta desde response.proposal sin GET extra', async () => {
        await hooks.handleDashboardExecutiveAction(makeButton({
            'data-executive-action-key': 'complete'
        }));

        assert.equal(postCalls, 1);
        assert.equal(getCalls, 0);
        assert.equal(runnerCalls, 1);
        assert.match(dom.content.innerHTML, /Siguiente/);
    });

    it('dismiss actualiza tarjeta desde response.proposal sin GET extra', async () => {
        await hooks.handleDashboardExecutiveAction(makeButton({
            'data-executive-action-key': 'dismiss'
        }));

        assert.equal(postCalls, 1);
        assert.equal(getCalls, 0);
        assert.equal(runnerCalls, 1);
        assert.match(dom.content.innerHTML, /Siguiente/);
    });
});
