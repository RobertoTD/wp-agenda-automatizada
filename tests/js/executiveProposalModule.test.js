'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/executive-proposal-module.js');
const runnerPath = path.join(__dirname, '../../assets/js/services/executiveClientActionRunner.js');
require(runnerPath);
const hooks = require(modulePath);

function makeClassList(initialClasses) {
    var classes = Array.isArray(initialClasses) ? initialClasses.slice() : [];

    return {
        classes: classes,
        add: function () {
            var self = this;

            Array.prototype.forEach.call(arguments, function (cls) {
                if (classes.indexOf(cls) === -1) {
                    classes.push(cls);
                }
            });
            self.classes = classes;
        },
        remove: function () {
            var self = this;
            var toRemove = Array.prototype.slice.call(arguments);

            classes = classes.filter(function (item) {
                return toRemove.indexOf(item) === -1;
            });
            self.classes = classes;
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
    let originalRunner;
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
        originalRunner = globalThis.AAExecutiveClientActionRunner;
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
            renderProposal: function (payload, options) {
                renderCalls += 1;
                dom.list.innerHTML = payload && payload.tasks ? 'rendered:' + payload.tasks[0].task_id : '';
                dom.lastRenderUiMode = options && options.uiMode ? options.uiMode : null;
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

        if (originalRunner === undefined) {
            delete globalThis.AAExecutiveClientActionRunner;
        } else {
            globalThis.AAExecutiveClientActionRunner = originalRunner;
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

    it('runClientAction handler con reload:true sincroniza listas y propuesta', async () => {
        globalThis.LearningActionHandlers = {
            isAvailable: function () {
                return true;
            },
            run: function () {
                handlerRuns += 1;
                return Promise.resolve({ reload: true });
            }
        };

        await hooks.runClientAction({
            type: 'handler',
            handler: 'appointment.confirm',
            origin_key: 'appointment_confirmation:42',
            task_id: '10',
            source: 'system',
            label: 'Confirmar'
        });

        assert.equal(handlerRuns, 1);
        assert.equal(boardReloadOptions && boardReloadOptions.skipExecutiveProposal, true);
        assert.equal(feedReloadCalls, 1);
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

describe('executive-proposal-module MC4.1 sprint debug', () => {
    let originalService;
    let originalRenderer;
    let originalProposalApi;
    let originalConsole;
    let dom;
    let consoleLines;

    beforeEach(() => {
        originalService = globalThis.AAExecutiveProposalService;
        originalRenderer = globalThis.AAExecutiveProposalRenderer;
        originalProposalApi = globalThis.AAExecutiveProposal;
        originalConsole = globalThis.console;
        consoleLines = [];

        globalThis.console = {
            log: function (line) {
                consoleLines.push(String(line));
            }
        };

        dom = {
            root: makeElement('aa-executive-proposal'),
            loading: makeElement('aa-executive-proposal-loading', { classes: ['hidden'] }),
            error: makeElement('aa-executive-proposal-error', { classes: ['hidden'] })
        };

        globalThis.document = {
            getElementById: function (id) {
                if (id === 'aa-executive-proposal') {
                    return dom.root;
                }
                if (id === 'aa-executive-proposal-loading') {
                    return dom.loading;
                }
                if (id === 'aa-executive-proposal-error') {
                    return dom.error;
                }

                return null;
            },
            readyState: 'complete',
            addEventListener: function () {}
        };

        globalThis.AAExecutiveProposalRenderer = {
            renderProposal: function () {}
        };
    });

    afterEach(() => {
        globalThis.AAExecutiveProposalService = originalService;
        globalThis.AAExecutiveProposalRenderer = originalRenderer;
        globalThis.AAExecutiveProposal = originalProposalApi;
        globalThis.console = originalConsole;
        hooks.stopDebugSprintWatch();
    });

    it('expone AAExecutiveProposal.debugSprint', () => {
        assert.equal(typeof globalThis.AAExecutiveProposal.debugSprint, 'function');
        assert.equal(typeof globalThis.AAExecutiveProposal.debugSprintWatch, 'function');
        assert.equal(typeof globalThis.AAExecutiveProposal.stopDebugSprintWatch, 'function');
    });

    it('debugSprint usa reload silencioso e imprime sin sprint meta', async () => {
        let reloadSilent = null;

        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                return Promise.resolve({
                    status: 'ready',
                    focus_list: { id: 2 },
                    meta: {
                        focus_reason: 'first_list_with_eligible_tasks',
                        sprint: {
                            sprint_active: false,
                            inactive_reason: 'no_active_sprint',
                            current_focus_list_id: 2,
                            focus_reason: 'first_list_with_eligible_tasks'
                        }
                    }
                });
            }
        };

        await hooks.debugSprint();

        assert.equal(reloadSilent, null);
        assert.match(consoleLines.join('\n'), /\[DEOIA Executive Sprint\]/);
        assert.match(consoleLines.join('\n'), /active: false/);
        assert.match(consoleLines.join('\n'), /current_focus_list_id: 2/);
        assert.equal(dom.loading.classList.contains('hidden'), true);
    });

    it('debugSprint imprime sprint activo con expires_in', async () => {
        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                return Promise.resolve({
                    status: 'ready',
                    focus_list: { id: 12 },
                    meta: {
                        focus_reason: 'sprint_active',
                        sprint: {
                            sprint_active: true,
                            active_focus_list_id: 12,
                            current_focus_list_id: 12,
                            focus_reason: 'sprint_active',
                            seconds_remaining: 2832,
                            sprint_started_at: 1000,
                            last_executive_action_at: 1200,
                            sprint_expires_at: 4600
                        }
                    }
                });
            }
        };

        await hooks.debugSprint();

        assert.match(consoleLines.join('\n'), /active: true/);
        assert.match(consoleLines.join('\n'), /focus_list_id: 12/);
        assert.match(consoleLines.join('\n'), /expires_in: 47m 12s/);
    });

    it('buildSprintDebugLines no rompe sin sprint meta', () => {
        var lines = hooks.buildSprintDebugLines({ status: 'empty', meta: {} });

        assert.match(lines.join('\n'), /active: false/);
        assert.match(lines.join('\n'), /reason: no_active_sprint/);
    });

    it('debugSprintWatch se detiene cuando sprint queda inactivo', async () => {
        var calls = 0;

        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                calls += 1;

                return Promise.resolve({
                    status: 'ready',
                    meta: {
                        sprint: {
                            sprint_active: false,
                            inactive_reason: 'no_active_sprint',
                            current_focus_list_id: 1,
                            focus_reason: 'first_list_with_eligible_tasks'
                        }
                    }
                });
            }
        };

        hooks.debugSprintWatch(20);

        await new Promise(function (resolve) {
            setTimeout(resolve, 50);
        });

        assert.equal(calls, 1);
    });
});

describe('executive-proposal-module MC5', () => {
    it('expone debugExpireSprint y delega focus clicks al servicio', () => {
        const moduleSrc = fs.readFileSync(modulePath, 'utf8');

        assert.match(moduleSrc, /debugExpireSprint/);
        assert.match(moduleSrc, /postFocusAction/);
        assert.match(moduleSrc, /data-executive-focus-action/);
        assert.match(moduleSrc, /handleFocusActionClick/);
        assert.equal(moduleSrc.includes('data-tasks-action'), false);
        assert.equal(moduleSrc.includes('data-learning-action'), false);
    });
});

describe('executive-proposal-module MC6', () => {
    let originalService;
    let originalRenderer;
    let dom;
    let renderCalls;
    let postCalls;
    let focusPostCalls;

    beforeEach(() => {
        originalService = globalThis.AAExecutiveProposalService;
        originalRenderer = globalThis.AAExecutiveProposalRenderer;
        renderCalls = 0;
        postCalls = 0;
        focusPostCalls = 0;

        dom = {
            proposal: makeElement('aa-executive-proposal', { buttons: [] }),
            loading: makeElement('aa-executive-proposal-loading', { classes: ['hidden'] }),
            error: makeElement('aa-executive-proposal-error', { classes: ['hidden'] }),
            empty: makeElement('aa-executive-empty', { classes: ['hidden'] }),
            list: makeElement('aa-executive-list'),
            lastRenderUiMode: null
        };

        globalThis.document = {
            getElementById: function (id) {
                return dom[id.replace(/^aa-executive-/, 'aa-executive-')] || dom[id] || null;
            }
        };

        globalThis.document.getElementById = function (id) {
            if (id === 'aa-executive-proposal') {
                return dom.proposal;
            }

            if (id === 'aa-executive-proposal-loading') {
                return dom.loading;
            }

            if (id === 'aa-executive-proposal-error') {
                return dom.error;
            }

            if (id === 'aa-executive-empty') {
                return dom.empty;
            }

            if (id === 'aa-executive-list') {
                return dom.list;
            }

            return null;
        };

        globalThis.AAExecutiveProposalRenderer = {
            renderProposal: function (payload, options) {
                renderCalls += 1;
                dom.lastRenderUiMode = options && options.uiMode ? options.uiMode : null;
            }
        };

        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                return Promise.resolve({
                    status: 'ready',
                    focus_list: { id: 1, title: 'Foco' },
                    tasks: [{ slot: 'current', task_id: 1, title: 'Actual' }],
                    meta: { sprint: { sprint_active: false } }
                });
            },
            postExecutiveAction: function (payload) {
                postCalls += 1;

                if (payload.actionKey === 'dismiss') {
                    return Promise.resolve({
                        action: { key: 'dismiss', mutated: true },
                        proposal: {
                            status: 'ready',
                            tasks: [{ slot: 'current', task_id: 3, title: 'Otra' }],
                            meta: { sprint: { sprint_active: false } }
                        },
                        client_action: null
                    });
                }

                return Promise.resolve({
                    action: { key: payload.actionKey, mutated: true },
                    proposal: {
                        status: 'ready',
                        tasks: [{ slot: 'current', task_id: 2, title: 'Siguiente' }],
                        meta: { sprint: { sprint_active: true } }
                    },
                    client_action: null
                });
            },
            postFocusAction: function () {
                focusPostCalls += 1;

                return Promise.resolve({
                    proposal: {
                        status: 'ready',
                        tasks: [{ slot: 'current', task_id: 4, title: 'Foco nuevo' }],
                        meta: { sprint: { sprint_active: false } }
                    }
                });
            }
        };

        hooks.flushPendingWorkZoneRender();
        hooks.setWorkZone('organizing');
        hooks.flushPendingWorkZoneRender();
        hooks.setWorkZone('executive');
        hooks.flushPendingWorkZoneRender();
    });

    afterEach(() => {
        globalThis.AAExecutiveProposalService = originalService;
        globalThis.AAExecutiveProposalRenderer = originalRenderer;
        hooks.flushPendingWorkZoneRender();
        hooks.setWorkZone('organizing');
        hooks.flushPendingWorkZoneRender();
        hooks.setWorkZone('executive');
        hooks.flushPendingWorkZoneRender();
    });

    it('loadProposal sin sprint usa uiMode null', async () => {
        await hooks.loadProposal({ silent: true });

        assert.equal(dom.lastRenderUiMode, null);
    });

    it('dismiss sin sprint activa choosing', async () => {
        await hooks.handleExecutiveActionClick(makeButton({ 'data-executive-action-key': 'dismiss' }));

        assert.equal(dom.lastRenderUiMode, 'choosing');
    });

    it('complete con sprint activo limpia choosing', async () => {
        hooks.setChoosingMode(true);

        await hooks.handleExecutiveActionClick(makeButton({ 'data-executive-action-key': 'complete' }));

        assert.equal(dom.lastRenderUiMode, null);
    });

    it('focus action sin sprint activa choosing', async () => {
        var button = {
            disabled: false,
            classList: makeClassList([]),
            getAttribute: function (name) {
                return name === 'data-executive-focus-action' ? 'change_focus' : null;
            }
        };

        await hooks.handleFocusActionClick(button);

        assert.equal(focusPostCalls, 1);
        assert.equal(dom.lastRenderUiMode, 'choosing');
    });

    it('setWorkZone organizing usa uiMode organizing sin endpoint extra', async () => {
        await hooks.loadProposal({ silent: true });
        var callsBefore = renderCalls;

        hooks.setWorkZone('organizing');
        hooks.flushPendingWorkZoneRender();

        assert.equal(renderCalls, callsBefore + 1);
        assert.equal(dom.lastRenderUiMode, 'organizing');
        assert.equal(postCalls, 0);
        assert.equal(focusPostCalls, 0);
    });

    it('setWorkZone executive restaura uiMode según sprint', async () => {
        await hooks.loadProposal({ silent: true });
        hooks.setWorkZone('organizing');
        hooks.flushPendingWorkZoneRender();
        hooks.setWorkZone('executive');
        hooks.flushPendingWorkZoneRender();

        assert.equal(dom.lastRenderUiMode, null);
    });

    it('setWorkZone executive repetido no re-renderiza', async () => {
        await hooks.loadProposal({ silent: true });
        var callsBefore = renderCalls;

        hooks.setWorkZone('executive');

        assert.equal(renderCalls, callsBefore);
    });

    it('expone AAExecutiveProposal.setWorkZone', () => {
        assert.equal(typeof globalThis.AAExecutiveProposal.setWorkZone, 'function');
    });
});

describe('executive-proposal-module focus pending visual scope', () => {
    let originalService;
    let originalRenderer;
    let resolveFocusAction;
    let focusPostCalls;
    let dom;

    function makeFocusButton(focusAction) {
        return makeButton({
            'data-executive-action': null,
            'data-executive-task-id': null,
            'data-executive-action-key': null,
            'data-executive-focus-action': focusAction
        });
    }

    function makeProposalRootWithAllButtons(buttons) {
        return {
            id: 'aa-executive-proposal',
            children: buttons,
            classList: makeClassList([]),
            contains: function (node) {
                return buttons.indexOf(node) !== -1;
            },
            querySelectorAll: function (selector) {
                return buttons.filter(function (button) {
                    if (selector === '[data-executive-action]') {
                        return button.getAttribute('data-executive-action') != null;
                    }

                    if (selector === '[data-executive-focus-action]') {
                        return button.getAttribute('data-executive-focus-action') != null;
                    }

                    return false;
                });
            },
            addEventListener: function () {}
        };
    }

    function buttonHasPendingVisual(button) {
        return button.disabled === true
            && button.classList.contains('opacity-60')
            && button.classList.contains('cursor-not-allowed');
    }

    beforeEach(() => {
        originalService = globalThis.AAExecutiveProposalService;
        originalRenderer = globalThis.AAExecutiveProposalRenderer;
        focusPostCalls = 0;
        resolveFocusAction = null;

        dom = {
            loading: makeElement('aa-executive-proposal-loading', { classes: ['hidden'] }),
            error: makeElement('aa-executive-proposal-error', { classes: ['hidden'] }),
            empty: makeElement('aa-executive-empty', { classes: ['hidden'] }),
            list: makeElement('aa-executive-list')
        };

        globalThis.document = {
            contains: function (node) {
                return dom.proposal && dom.proposal.contains(node);
            },
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

                if (id === 'aa-executive-empty') {
                    return dom.empty;
                }

                if (id === 'aa-executive-list') {
                    return dom.list;
                }

                return null;
            }
        };

        globalThis.AAExecutiveProposalRenderer = {
            renderProposal: function () {}
        };

        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                return Promise.resolve({ status: 'ready', tasks: [], meta: {} });
            },
            postExecutiveAction: function () {
                return Promise.resolve({ action: {}, proposal: {}, client_action: null });
            },
            postFocusAction: function () {
                focusPostCalls += 1;

                return new Promise(function (resolve) {
                    resolveFocusAction = resolve;
                });
            }
        };
    });

    afterEach(() => {
        globalThis.AAExecutiveProposalService = originalService;
        globalThis.AAExecutiveProposalRenderer = originalRenderer;
    });

    it('focus click aplica pending visual solo al botón clicado', async () => {
        var clicked = makeFocusButton('change_focus');
        var otherFocus = makeFocusButton('previous_focus');
        var executiveButton = makeButton({ 'data-executive-action-key': 'dismiss' });

        dom.proposal = makeProposalRootWithAllButtons([clicked, otherFocus, executiveButton]);

        var pending = hooks.handleFocusActionClick(clicked);

        assert.equal(focusPostCalls, 1);
        assert.equal(buttonHasPendingVisual(clicked), true);
        assert.equal(buttonHasPendingVisual(otherFocus), false);
        assert.equal(buttonHasPendingVisual(executiveButton), false);

        resolveFocusAction({
            proposal: {
                status: 'ready',
                tasks: [{ slot: 'current', task_id: 9, title: 'Nueva' }],
                meta: { sprint: { sprint_active: false } }
            }
        });

        await pending;

        assert.equal(buttonHasPendingVisual(clicked), false);
        assert.equal(buttonHasPendingVisual(otherFocus), false);
        assert.equal(buttonHasPendingVisual(executiveButton), false);
    });

    it('isActionPending bloquea segundo focus click concurrente', async () => {
        var clicked = makeFocusButton('change_focus');
        var otherFocus = makeFocusButton('previous_focus');

        dom.proposal = makeProposalRootWithAllButtons([clicked, otherFocus]);

        var first = hooks.handleFocusActionClick(clicked);

        assert.equal(focusPostCalls, 1);

        await hooks.handleFocusActionClick(otherFocus);

        assert.equal(focusPostCalls, 1);

        resolveFocusAction({
            proposal: {
                status: 'ready',
                tasks: [{ slot: 'current', task_id: 10, title: 'Otra' }],
                meta: { sprint: { sprint_active: false } }
            }
        });

        await first;
    });

    it('finally no restaura pending sobre botón reemplazado por re-render', async () => {
        var clicked = makeFocusButton('change_focus');

        dom.proposal = makeProposalRootWithAllButtons([clicked]);

        var pending = hooks.handleFocusActionClick(clicked);

        assert.equal(buttonHasPendingVisual(clicked), true);

        resolveFocusAction({
            proposal: {
                status: 'ready',
                tasks: [{ slot: 'current', task_id: 11, title: 'Reemplazada' }],
                meta: { sprint: { sprint_active: false } }
            }
        });

        dom.proposal = makeProposalRootWithAllButtons([]);

        await pending;

        assert.equal(buttonHasPendingVisual(clicked), true);
    });
});

function makeClickableProposalButton(attrs) {
    var button = makeButton(attrs);

    button.closest = function (selector) {
        if (selector === '[data-executive-action]' && button.getAttribute('data-executive-action')) {
            return button;
        }

        if (selector === '[data-executive-focus-action]' && button.getAttribute('data-executive-focus-action')) {
            return button;
        }

        return null;
    };

    return button;
}

function makeProposalRootWithButton(button) {
    var proposal = {
        id: 'aa-executive-proposal',
        children: [button],
        classList: makeClassList([]),
        listeners: {},
        contains: function (node) {
            if (!node) {
                return false;
            }

            if (node === this) {
                return true;
            }

            return this.children.indexOf(node) !== -1;
        },
        querySelectorAll: function () {
            return this.children.slice();
        },
        addEventListener: function (type, handler) {
            this.listeners[type] = handler;
        }
    };

    button.parent = proposal;

    return proposal;
}

function dispatchProposalClick(proposal, button) {
    var handler = proposal.listeners.click;

    if (typeof handler !== 'function') {
        return;
    }

    handler({
        target: button,
        preventDefault: function () {},
        stopPropagation: function () {}
    });
}

describe('executive-proposal-module MC6.1 capture re-render fix', () => {
    let originalService;
    let originalRenderer;
    let originalRaf;
    let originalCaf;
    let rafQueue;
    let postCalls;
    let focusPostCalls;
    let renderCalls;
    let dom;

    beforeEach(() => {
        originalService = globalThis.AAExecutiveProposalService;
        originalRenderer = globalThis.AAExecutiveProposalRenderer;
        originalRaf = globalThis.requestAnimationFrame;
        originalCaf = globalThis.cancelAnimationFrame;
        rafQueue = [];
        postCalls = 0;
        focusPostCalls = 0;
        renderCalls = 0;

        globalThis.requestAnimationFrame = function (callback) {
            rafQueue.push(callback);

            return rafQueue.length;
        };

        globalThis.cancelAnimationFrame = function () {};

        dom = {
            loading: makeElement('aa-executive-proposal-loading', { classes: ['hidden'] }),
            error: makeElement('aa-executive-proposal-error', { classes: ['hidden'] }),
            empty: makeElement('aa-executive-empty', { classes: ['hidden'] }),
            list: makeElement('aa-executive-list'),
            lastRenderUiMode: null
        };

        globalThis.AAExecutiveProposalRenderer = {
            renderProposal: function (payload, options) {
                renderCalls += 1;
                dom.lastRenderUiMode = options && options.uiMode ? options.uiMode : null;
            }
        };

        globalThis.AAExecutiveProposalService = {
            getExecutiveProposal: function () {
                return Promise.resolve({
                    status: 'ready',
                    focus_list: { id: 1, title: 'Foco' },
                    tasks: [{ slot: 'current', task_id: 1, title: 'Actual' }],
                    meta: { sprint: { sprint_active: false } }
                });
            },
            postExecutiveAction: function () {
                postCalls += 1;

                return Promise.resolve({
                    action: { key: 'complete', mutated: true },
                    proposal: {
                        status: 'ready',
                        tasks: [{ slot: 'current', task_id: 2, title: 'Siguiente' }],
                        meta: { sprint: { sprint_active: true } }
                    },
                    client_action: null
                });
            },
            postFocusAction: function () {
                focusPostCalls += 1;

                return Promise.resolve({
                    proposal: {
                        status: 'ready',
                        tasks: [{ slot: 'current', task_id: 4, title: 'Foco nuevo' }],
                        meta: { sprint: { sprint_active: false } }
                    }
                });
            }
        };

        hooks.flushPendingWorkZoneRender();
        rafQueue = [];
    });

    afterEach(() => {
        hooks.flushPendingWorkZoneRender();
        rafQueue = [];
        globalThis.AAExecutiveProposalService = originalService;
        globalThis.AAExecutiveProposalRenderer = originalRenderer;
        globalThis.requestAnimationFrame = originalRaf;
        globalThis.cancelAnimationFrame = originalCaf;
    });

    function flushRafQueue() {
        var queue = rafQueue.slice();
        rafQueue = [];
        queue.forEach(function (callback) {
            callback();
        });
    }

    function bindProposalDom(proposal) {
        dom.proposal = proposal;

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

                if (id === 'aa-executive-empty') {
                    return dom.empty;
                }

                if (id === 'aa-executive-list') {
                    return dom.list;
                }

                return null;
            }
        };

        hooks.bindExecutiveDelegation();
    }

    it('capture setWorkZone executive idempotente no invalida click delegado', async () => {
        rafQueue = [];
        renderCalls = 0;

        var button = makeClickableProposalButton({ 'data-executive-action-key': 'complete' });
        var proposal = makeProposalRootWithButton(button);

        bindProposalDom(proposal);

        globalThis.AAExecutiveProposal.setWorkZone('executive');
        assert.equal(rafQueue.length, 0);

        dispatchProposalClick(proposal, button);

        await Promise.resolve();

        assert.equal(postCalls, 1);
        assert.equal(proposal.contains(button), true);
    });

    it('transición organizing → executive difiere render y conserva target para handler', async () => {
        rafQueue = [];
        renderCalls = 0;

        var button = makeClickableProposalButton({ 'data-executive-action-key': 'complete' });
        var proposal = makeProposalRootWithButton(button);

        bindProposalDom(proposal);

        hooks.setWorkZone('organizing');
        assert.equal(rafQueue.length, 1);
        flushRafQueue();
        assert.equal(renderCalls, 1);

        globalThis.AAExecutiveProposal.setWorkZone('executive');
        assert.equal(rafQueue.length, 1);
        assert.equal(proposal.contains(button), true);

        dispatchProposalClick(proposal, button);

        await Promise.resolve();

        assert.equal(postCalls, 1);

        flushRafQueue();
        assert.equal(renderCalls, 3);
    });

    it('focus action en header sigue llamando postFocusAction tras capture', async () => {
        var button = makeClickableProposalButton({
            'data-executive-action': null,
            'data-executive-task-id': null,
            'data-executive-action-key': null,
            'data-executive-focus-action': 'change_focus'
        });
        button.getAttribute = function (name) {
            if (name === 'data-executive-focus-action') {
                return 'change_focus';
            }

            return null;
        };

        var proposal = makeProposalRootWithButton(button);

        bindProposalDom(proposal);

        globalThis.AAExecutiveProposal.setWorkZone('executive');
        dispatchProposalClick(proposal, button);

        await Promise.resolve();

        assert.equal(focusPostCalls, 1);
        assert.equal(postCalls, 0);
    });
});
