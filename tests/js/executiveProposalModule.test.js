'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/executive-proposal-module.js');
const hooks = require(modulePath);

function makeClassList(initialClasses) {
    var classes = Array.isArray(initialClasses) ? initialClasses.slice() : [];

    return {
        classes: classes,
        add: function (cls) {
            if (classes.indexOf(cls) === -1) {
                classes.push(cls);
            }
            this.classes = classes;
        },
        remove: function (cls) {
            classes = classes.filter(function (item) {
                return item !== cls;
            });
            this.classes = classes;
        },
        contains: function (cls) {
            return classes.indexOf(cls) !== -1;
        }
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
            return attributes[name] || null;
        },
        setAttribute: function (name, value) {
            attributes[name] = value;
        }
    };
}

function makeElement(id, options) {
    var opts = options || {};

    return {
        id: id,
        innerHTML: opts.innerHTML || '',
        textContent: opts.textContent || '',
        classList: makeClassList(opts.classes || []),
        contains: function () {
            return true;
        },
        querySelectorAll: function () {
            return opts.buttons || [];
        },
        addEventListener: function () {},
        setAttribute: function () {},
        getAttribute: function () {
            return null;
        }
    };
}

describe('executive-proposal-module MC2/MC3', () => {
    let originalService;
    let originalRenderer;
    let originalProposalApi;
    let originalBoard;
    let originalFeed;
    let originalHandlers;
    let originalLocation;
    let serviceCalls;
    let postCalls;
    let renderCalls;
    let boardReloadOptions;
    let feedReloadCalls;
    let handlerRuns;
    let dom;

    beforeEach(() => {
        originalService = globalThis.AAExecutiveProposalService;
        originalRenderer = globalThis.AAExecutiveProposalRenderer;
        originalProposalApi = globalThis.AAExecutiveProposal;
        originalBoard = globalThis.AATasksBoard;
        originalFeed = globalThis.AAExecutableUserListsVisibleFeed;
        originalHandlers = globalThis.LearningActionHandlers;
        originalLocation = globalThis.location;
        serviceCalls = 0;
        postCalls = 0;
        renderCalls = 0;
        boardReloadOptions = null;
        feedReloadCalls = 0;
        handlerRuns = 0;

        dom = {
            proposal: makeElement('aa-executive-proposal', { buttons: [] }),
            loading: makeElement('aa-executive-proposal-loading', { classes: ['hidden'] }),
            error: makeElement('aa-executive-proposal-error', { classes: ['hidden'] }),
            focus: makeElement('aa-executive-focus', { classes: ['hidden'] }),
            empty: makeElement('aa-executive-empty', { classes: ['hidden'] }),
            list: makeElement('aa-executive-list')
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executive-proposal') {
                    return dom.proposal;
                }

                if (id === 'aa-executive-proposal-loading') {
                    return dom.loading;
                }

                if (id === 'aa-executive-proposal-error') {
                    return dom.error;
                }

                if (id === 'aa-executive-focus') {
                    return dom.focus;
                }

                if (id === 'aa-executive-empty') {
                    return dom.empty;
                }

                if (id === 'aa-executive-list') {
                    return dom.list;
                }

                return null;
            }
        };

        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                serviceCalls += 1;
                return Promise.resolve({
                    status: 'ready',
                    focus_list: { id: 1, title: 'Foco', source_category: 'user' },
                    tasks: [{ slot: 'current', task_id: 1, title: 'Actual', is_overdue: false }]
                });
            },
            postExecutiveAction: function (payload) {
                postCalls += 1;
                return Promise.resolve({
                    action: { key: payload.actionKey, mutated: payload.actionKey === 'complete' },
                    proposal: {
                        status: 'ready',
                        tasks: [{ slot: 'current', task_id: 2, title: 'Siguiente' }]
                    },
                    client_action: payload.actionKey === 'navigate.settings'
                        ? { type: 'navigate', url: 'https://example.test/go' }
                        : null
                });
            }
        };

        globalThis.AAExecutiveProposalRenderer = {
            renderProposal: function (payload) {
                renderCalls += 1;
                dom.list.innerHTML = payload && payload.tasks ? 'rendered:' + payload.tasks[0].task_id : '';
            }
        };

        globalThis.AATasksBoard = {
            reload: function (options) {
                boardReloadOptions = options || {};
                return Promise.resolve();
            }
        };

        globalThis.AAExecutableUserListsVisibleFeed = {
            isEnabled: function () {
                return true;
            },
            reloadFeedOnly: function () {
                feedReloadCalls += 1;
                return Promise.resolve();
            }
        };

        globalThis.LearningActionHandlers = {
            isAvailable: function () {
                return true;
            },
            run: function () {
                handlerRuns += 1;
                return Promise.resolve();
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

        if (originalRenderer === undefined) {
            delete globalThis.AAExecutiveProposalRenderer;
        } else {
            globalThis.AAExecutiveProposalRenderer = originalRenderer;
        }

        if (originalProposalApi === undefined) {
            delete globalThis.AAExecutiveProposal;
        } else {
            globalThis.AAExecutiveProposal = originalProposalApi;
        }

        if (originalBoard === undefined) {
            delete globalThis.AATasksBoard;
        } else {
            globalThis.AATasksBoard = originalBoard;
        }

        if (originalFeed === undefined) {
            delete globalThis.AAExecutableUserListsVisibleFeed;
        } else {
            globalThis.AAExecutableUserListsVisibleFeed = originalFeed;
        }

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

        delete globalThis.document;
    });

    it('loadProposal llama service y renderiza respuesta', async () => {
        await hooks.loadProposal({ silent: true });

        assert.equal(serviceCalls, 1);
        assert.equal(renderCalls, 1);
        assert.equal(dom.list.innerHTML, 'rendered:1');
    });

    it('handleExecutiveActionClick complete llama POST y renderiza proposal inline', async () => {
        var button = makeButton({ 'data-executive-action-key': 'complete' });

        await hooks.handleExecutiveActionClick(button);

        assert.equal(postCalls, 1);
        assert.equal(renderCalls, 1);
        assert.equal(dom.list.innerHTML, 'rendered:2');
        assert.equal(boardReloadOptions && boardReloadOptions.skipExecutiveProposal, true);
        assert.equal(feedReloadCalls, 1);
    });

    it('handleExecutiveActionClick dismiss llama POST', async () => {
        globalThis.AAExecutiveProposalService.postExecutiveAction = function (payload) {
            postCalls += 1;
            return Promise.resolve({
                action: { key: 'dismiss', mutated: true },
                proposal: { status: 'ready', tasks: [{ slot: 'current', task_id: 3, title: 'Otra' }] },
                client_action: null
            });
        };

        await hooks.handleExecutiveActionClick(makeButton({ 'data-executive-action-key': 'dismiss' }));

        assert.equal(postCalls, 1);
        assert.equal(renderCalls, 1);
    });

    it('maneja error sin romper el módulo', async () => {
        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                return Promise.reject(new Error('falló propuesta'));
            }
        };

        await assert.doesNotReject(async function () {
            await hooks.loadProposal({ silent: true });
        });
    });

    it('POST error muestra mensaje', async () => {
        globalThis.AAExecutiveProposalService.postExecutiveAction = function () {
            return Promise.reject(new Error('acción inválida'));
        };

        await hooks.handleExecutiveActionClick(makeButton());

        assert.match(dom.error.textContent, /acción inválida/);
    });

    it('runClientAction navigate asigna location.href', async () => {
        await hooks.runClientAction({ type: 'navigate', url: 'https://example.test/go' });

        assert.equal(globalThis.location.href, 'https://example.test/go');
    });

    it('runClientAction handler usa LearningActionHandlers', async () => {
        await hooks.runClientAction({
            type: 'handler',
            handler: 'pwa.install',
            origin_key: 'install_pwa',
            task_id: '10',
            source: 'system',
            label: 'Instalar'
        });

        assert.equal(handlerRuns, 1);
    });

    it('expone AAExecutiveProposal.reload', () => {
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /globalRoot\.AAExecutiveProposal = \{/);
        assert.match(moduleSrc, /reload: loadProposal/);
        assert.equal(typeof globalThis.AAExecutiveProposal.reload, 'function');
    });

    it('reloadExecutiveProposalBestEffort usa reload silencioso', async () => {
        let reloadSilent = null;

        globalThis.AAExecutiveProposal = {
            reload: function (options) {
                reloadSilent = options && options.silent;
                return Promise.resolve();
            }
        };

        await hooks.reloadExecutiveProposalBestEffort();

        assert.equal(reloadSilent, true);
    });

    it('loadProposal no llama AATasksBoard.reload', async () => {
        let boardReloadCalls = 0;
        globalThis.AATasksBoard = {
            reload: function () {
                boardReloadCalls += 1;
                return Promise.resolve();
            }
        };

        await hooks.loadProposal({ silent: true });

        assert.equal(boardReloadCalls, 0);
    });
});
