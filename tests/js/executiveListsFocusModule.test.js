'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/executive-lists-focus-module.js');
const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
const adminSourceCssPath = path.join(__dirname, '../../includes/admin/ui/assets/css/admin.source.css');
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const indexSrc = fs.readFileSync(indexPath, 'utf8');

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
        toggle: function (cls, force) {
            var has = classes.indexOf(cls) !== -1;
            var next = typeof force === 'boolean' ? force : !has;

            if (next && !has) {
                classes.push(cls);
            }

            if (!next && has) {
                classes = classes.filter(function (item) {
                    return item !== cls;
                });
            }

            this.classes = classes;
            return next;
        },
        contains: function (cls) {
            return classes.indexOf(cls) !== -1;
        }
    };
}

function makeElement(tag, options) {
    var opts = options || {};

    return {
        tagName: tag,
        id: opts.id || '',
        classList: opts.classList || makeClassList(opts.classes || []),
        attributes: Object.assign({}, opts.attributes || {}),
        parent: opts.parent || null,
        children: opts.children || [],
        listeners: {},
        contains: function (node) {
            if (!node) {
                return false;
            }

            if (node === this) {
                return true;
            }

            return this.children.some(function (child) {
                return child.contains && child.contains(node);
            });
        },
        appendChild: function (child) {
            child.parent = this;
            this.children.push(child);
        },
        addEventListener: function (type, handler, useCapture) {
            var key = type + (useCapture ? ':capture' : ':bubble');

            this.listeners[key] = this.listeners[key] || [];
            this.listeners[key].push(handler);
        }
    };
}

function buildFocusDom() {
    var proposalButton = makeElement('button', { id: 'proposal-button' });
    var listsButton = makeElement('button', { id: 'lists-button' });
    var proposal = makeElement('section', {
        id: 'aa-executive-proposal',
        children: [proposalButton]
    });
    var listsSection = makeElement('section', {
        id: 'aa-lists-section',
        classes: ['pb-24', 'is-muted'],
        children: [listsButton]
    });
    var root = makeElement('div', {
        id: 'aa-tasks-module-root',
        children: [proposal, listsSection]
    });
    var outside = makeElement('button', { id: 'outside-button' });

    proposalButton.parent = proposal;
    listsButton.parent = listsSection;
    proposal.parent = root;
    listsSection.parent = root;

    var documentMock = {
        readyState: 'complete',
        getElementById: function (id) {
            if (id === 'aa-tasks-module-root') {
                return root;
            }

            if (id === 'aa-executive-proposal') {
                return proposal;
            }

            if (id === 'aa-lists-section') {
                return listsSection;
            }

            return null;
        },
        addEventListener: function () {}
    };

    return {
        document: documentMock,
        root: root,
        proposal: proposal,
        listsSection: listsSection,
        proposalButton: proposalButton,
        listsButton: listsButton,
        outside: outside
    };
}

function dispatchInteraction(target, type, root) {
    var event = {
        target: target,
        preventDefault: function () {
            event.preventDefaultCalled = true;
        },
        stopPropagation: function () {
            event.stopPropagationCalled = true;
        },
        preventDefaultCalled: false,
        stopPropagationCalled: false
    };

    (root.listeners[type + ':capture'] || []).forEach(function (handler) {
        handler(event);
    });

    return event;
}

function loadModule(dom, options) {
    var opts = options || {};
    var workZoneCalls = [];
    var context = {
        window: {},
        console: console,
        document: dom.document,
        module: { exports: {} }
    };

    context.window = context;
    context.globalThis = context;
    context.AAExecutiveProposal = {
        setWorkZone: function (zone) {
            workZoneCalls.push(zone);

            if (typeof opts.onSetWorkZone === 'function') {
                opts.onSetWorkZone(zone);
            }
        }
    };

    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    return {
        exports: context.module.exports,
        workZoneCalls: workZoneCalls
    };
}

describe('executive-lists-focus-module MC6', () => {
    it('index.php expone header MC6 sin copy estático', () => {
        assert.match(indexSrc, />Listas de tareas</);
        assert.doesNotMatch(indexSrc, />Todas las listas de tareas.</);
        assert.doesNotMatch(indexSrc, /Acciones recomendadas ahora/);
        assert.match(indexSrc, /id="aa-executive-status"/);
        assert.match(indexSrc, /id="aa-executive-header-actions"/);
        assert.match(indexSrc, /aa-executive-lists-divider/);
        assert.match(indexSrc, /id="aa-executive-proposal"/);
        assert.match(indexSrc, /id="aa-lists-section" class="pb-24 is-muted"/);
        assert.match(indexSrc, /executive-lists-focus-module\.js/);
    });

    it('CSS define transición simétrica y sin pointer-events none', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /#aa-executive-proposal[\s\S]*#aa-lists-section[\s\S]*transition:\s*opacity 0\.18s ease-in-out/);
        assert.match(css, /#aa-executive-proposal\.is-muted[\s\S]*opacity:\s*0\.50/);
        assert.match(css, /#aa-lists-section\.is-muted[\s\S]*opacity:\s*0\.50/);
        assert.match(css, /\.aa-executive-status-dot/);
        assert.doesNotMatch(css, /#aa-lists-section[\s\S]*pointer-events:\s*none/);
        assert.doesNotMatch(css, /#aa-executive-proposal[\s\S]*pointer-events:\s*none/);
    });

    it('estado inicial mantiene listas atenuadas y propuesta activa', () => {
        var dom = buildFocusDom();

        loadModule(dom);

        assert.equal(dom.listsSection.classList.contains('is-muted'), true);
        assert.equal(dom.proposal.classList.contains('is-muted'), false);
    });

    it('click en listas activa listas, atenúa propuesta y notifica organizing', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        dispatchInteraction(dom.listsButton, 'click', dom.root);

        assert.equal(dom.listsSection.classList.contains('is-muted'), false);
        assert.equal(dom.proposal.classList.contains('is-muted'), true);
        assert.deepEqual(loaded.workZoneCalls, ['organizing']);
    });

    it('focusin en listas atenúa propuesta', () => {
        var dom = buildFocusDom();

        loadModule(dom);
        dispatchInteraction(dom.listsButton, 'focusin', dom.root);

        assert.equal(dom.proposal.classList.contains('is-muted'), true);
    });

    it('click en propuesta atenúa listas y restaura propuesta activa', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        dispatchInteraction(dom.listsButton, 'click', dom.root);
        dispatchInteraction(dom.proposalButton, 'click', dom.root);

        assert.equal(dom.listsSection.classList.contains('is-muted'), true);
        assert.equal(dom.proposal.classList.contains('is-muted'), false);
        assert.deepEqual(loaded.workZoneCalls, ['organizing', 'executive']);
    });

    it('click repetido en propuesta no notifica work zone de nuevo', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        dispatchInteraction(dom.listsButton, 'click', dom.root);
        dispatchInteraction(dom.proposalButton, 'click', dom.root);
        dispatchInteraction(dom.proposalButton, 'click', dom.root);

        assert.deepEqual(loaded.workZoneCalls, ['organizing', 'executive']);
        assert.equal(loaded.exports.getActiveWorkZone(), 'executive');
    });

    it('click en propuesta no usa preventDefault ni stopPropagation', () => {
        var dom = buildFocusDom();

        loadModule(dom);

        var event = dispatchInteraction(dom.proposalButton, 'click', dom.root);

        assert.equal(event.preventDefaultCalled, false);
        assert.equal(event.stopPropagationCalled, false);
    });

    it('click en listas notifica organizing sin bloquear bubbling', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        var event = dispatchInteraction(dom.listsButton, 'click', dom.root);

        assert.deepEqual(loaded.workZoneCalls, ['organizing']);
        assert.equal(event.preventDefaultCalled, false);
        assert.equal(event.stopPropagationCalled, false);
        assert.equal(loaded.exports.getActiveWorkZone(), 'organizing');
    });

    it('focusin en propuesta atenúa listas', () => {
        var dom = buildFocusDom();

        loadModule(dom);
        dom.listsSection.classList.remove('is-muted');
        dispatchInteraction(dom.proposalButton, 'focusin', dom.root);

        assert.equal(dom.listsSection.classList.contains('is-muted'), true);
        assert.equal(dom.proposal.classList.contains('is-muted'), false);
    });

    it('click fuera del root no cambia estado', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        dom.listsSection.classList.remove('is-muted');

        var event = {
            target: dom.outside,
            preventDefault: function () {},
            stopPropagation: function () {}
        };

        (dom.root.listeners['click:capture'] || []).forEach(function (handler) {
            handler(event);
        });

        assert.equal(dom.listsSection.classList.contains('is-muted'), false);
        assert.equal(loaded.workZoneCalls.length, 0);
    });

    it('setMuted alterna clase is-muted', () => {
        var dom = buildFocusDom();
        var api = loadModule(dom).exports;

        api.setMuted(dom.proposal, true);
        assert.equal(dom.proposal.classList.contains('is-muted'), true);

        api.setMuted(dom.proposal, false);
        assert.equal(dom.proposal.classList.contains('is-muted'), false);
    });
});
