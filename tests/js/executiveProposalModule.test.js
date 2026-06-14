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

function makeElement(id, options) {
    var opts = options || {};

    return {
        id: id,
        innerHTML: opts.innerHTML || '',
        textContent: opts.textContent || '',
        classList: makeClassList(opts.classes || []),
        setAttribute: function () {},
        getAttribute: function () {
            return null;
        }
    };
}

describe('executive-proposal-module MC2', () => {
    let originalService;
    let originalRenderer;
    let originalProposalApi;
    let serviceCalls;
    let renderCalls;
    let dom;

    beforeEach(() => {
        originalService = globalThis.AAExecutiveProposalService;
        originalRenderer = globalThis.AAExecutiveProposalRenderer;
        originalProposalApi = globalThis.AAExecutiveProposal;
        serviceCalls = 0;
        renderCalls = 0;

        dom = {
            proposal: makeElement('aa-executive-proposal'),
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
            }
        };

        globalThis.AAExecutiveProposalRenderer = {
            renderProposal: function (payload) {
                renderCalls += 1;
                dom.list.innerHTML = payload && payload.tasks ? 'rendered' : '';
            }
        };
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

        delete globalThis.document;
    });

    it('loadProposal llama service y renderiza respuesta', async () => {
        await hooks.loadProposal({ silent: true });

        assert.equal(serviceCalls, 1);
        assert.equal(renderCalls, 1);
        assert.equal(dom.list.innerHTML, 'rendered');
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

    it('reload({ silent: true }) no llama AATasksBoard.reload', async () => {
        let boardReloadCalls = 0;
        globalThis.AATasksBoard = {
            reload: function () {
                boardReloadCalls += 1;
                return Promise.resolve();
            }
        };

        await hooks.loadProposal({ silent: true });

        assert.equal(boardReloadCalls, 0);
        delete globalThis.AATasksBoard;
    });
});
