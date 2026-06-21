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
    var attributes = Object.assign({}, opts.attributes || {});

    var element = {
        tagName: tag,
        id: opts.id || '',
        classList: opts.classList || makeClassList(opts.classes || []),
        attributes: attributes,
        parent: opts.parent || null,
        children: opts.children || [],
        listeners: {},
        inert: false,
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
        },
        setAttribute: function (name, value) {
            this.attributes[name] = String(value);
        },
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
        },
        removeAttribute: function (name) {
            delete this.attributes[name];
        }
    };

    Object.keys(attributes).forEach(function (name) {
        element.setAttribute(name, attributes[name]);
    });

    return element;
}

function buildFocusDom() {
    var proposalButton = makeElement('button', { id: 'proposal-button' });
    var listsButton = makeElement('button', { id: 'lists-button' });
    var headerToggle = makeElement('button', {
        id: 'aa-lists-header-toggle',
        attributes: { 'aria-expanded': 'false', 'aria-controls': 'aa-lists-body' }
    });
    var listsHeader = makeElement('header', {
        id: 'aa-lists-header',
        children: [headerToggle]
    });
    var listsBody = makeElement('div', {
        id: 'aa-lists-body',
        classes: ['aa-lists-body', 'is-collapsed'],
        attributes: { 'aria-hidden': 'true', inert: '' },
        children: [listsButton]
    });
    var proposal = makeElement('section', {
        id: 'aa-executive-proposal',
        children: [proposalButton]
    });
    var listsSection = makeElement('section', {
        id: 'aa-lists-section',
        classes: ['pb-24'],
        children: [listsHeader, listsBody]
    });
    var root = makeElement('div', {
        id: 'aa-tasks-module-root',
        attributes: { 'data-work-zone': 'executive' },
        children: [proposal, listsSection]
    });
    var outside = makeElement('button', { id: 'outside-button' });

    proposalButton.parent = proposal;
    headerToggle.parent = listsHeader;
    listsButton.parent = listsBody;
    listsHeader.parent = listsSection;
    listsBody.parent = listsSection;
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

            if (id === 'aa-lists-body') {
                return listsBody;
            }

            if (id === 'aa-lists-header-toggle') {
                return headerToggle;
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
        listsHeader: listsHeader,
        listsBody: listsBody,
        headerToggle: headerToggle,
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

describe('executive-lists-focus-module MC6 / MC-UX-G MC1', () => {
    it('index.php expone header persistente y body contraíble', () => {
        assert.match(indexSrc, /Organizador · Listas de tareas/);
        assert.match(indexSrc, /id="aa-lists-header"/);
        assert.match(indexSrc, /id="aa-lists-body"/);
        assert.match(indexSrc, /id="aa-lists-header-toggle"/);
        assert.match(indexSrc, /id="aa-lists-section" class="pb-24"/);
        assert.doesNotMatch(indexSrc, /id="aa-lists-section" class="pb-24 is-muted"/);
        assert.match(indexSrc, /data-work-zone="executive"/);
        assert.doesNotMatch(indexSrc, />Todas las listas de tareas.</);
        assert.doesNotMatch(indexSrc, /Acciones recomendadas ahora/);
        assert.match(indexSrc, /id="aa-executive-status"/);
        assert.match(indexSrc, /id="aa-executive-header-actions"/);
        assert.match(indexSrc, /aa-executive-lists-divider/);
        assert.match(indexSrc, /id="aa-executive-proposal"/);
        assert.match(indexSrc, /executive-lists-focus-module\.js/);
    });

    it('CSS define mute del ejecutor y collapse del body sin pointer-events none', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /#aa-executive-proposal[\s\S]*transition:\s*opacity 0\.18s ease-in-out/);
        assert.match(css, /#aa-executive-proposal\.is-muted[\s\S]*opacity:\s*0\.50/);
        assert.match(css, /#aa-lists-body\.is-collapsed[\s\S]*max-height:\s*0/);
        assert.match(css, /\[data-work-zone="organizing"\][\s\S]*\.aa-lists-header-chevron/);
        assert.match(css, /\.aa-executive-status-dot/);
        assert.doesNotMatch(css, /#aa-lists-section\.is-muted/);
        assert.doesNotMatch(css, /#aa-lists-section[\s\S]*pointer-events:\s*none/);
        assert.doesNotMatch(css, /#aa-executive-proposal[\s\S]*pointer-events:\s*none/);
    });

    it('estado inicial mantiene body colapsado y propuesta activa', () => {
        var dom = buildFocusDom();

        loadModule(dom);

        assert.equal(dom.root.getAttribute('data-work-zone'), 'executive');
        assert.equal(dom.listsBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.listsBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.headerToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.proposal.classList.contains('is-muted'), false);
        assert.equal(dom.listsSection.classList.contains('is-muted'), false);
    });

    it('applyWorkZone organizing despliega listas y opaca ejecutor', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        var changed = loaded.exports.applyWorkZone('organizing');

        assert.equal(changed, true);
        assert.equal(dom.root.getAttribute('data-work-zone'), 'organizing');
        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.listsBody.getAttribute('aria-hidden'), null);
        assert.equal(dom.headerToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.proposal.classList.contains('is-muted'), true);
        assert.deepEqual(loaded.workZoneCalls, ['organizing']);
    });

    it('applyWorkZone executive colapsa listas y activa ejecutor', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        loaded.exports.applyWorkZone('organizing');
        loaded.workZoneCalls.length = 0;

        var changed = loaded.exports.applyWorkZone('executive');

        assert.equal(changed, true);
        assert.equal(dom.root.getAttribute('data-work-zone'), 'executive');
        assert.equal(dom.listsBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.listsBody.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.headerToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.proposal.classList.contains('is-muted'), false);
        assert.deepEqual(loaded.workZoneCalls, ['executive']);
    });

    it('applyWorkZone idempotente no llama setWorkZone de nuevo', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        loaded.exports.applyWorkZone('executive');
        loaded.workZoneCalls.length = 0;

        var changed = loaded.exports.applyWorkZone('executive');

        assert.equal(changed, false);
        assert.deepEqual(loaded.workZoneCalls, []);
    });

    it('click en listas activa organizing, despliega body y opaca propuesta', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        dispatchInteraction(dom.listsButton, 'click', dom.root);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
        assert.equal(dom.proposal.classList.contains('is-muted'), true);
        assert.deepEqual(loaded.workZoneCalls, ['organizing']);
    });

    it('click en header toggle activa organizing', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        dispatchInteraction(dom.headerToggle, 'click', dom.root);

        assert.equal(dom.root.getAttribute('data-work-zone'), 'organizing');
        assert.equal(dom.listsBody.classList.contains('is-collapsed'), false);
        assert.deepEqual(loaded.workZoneCalls, ['organizing']);
    });

    it('focusin en listas atenúa propuesta', () => {
        var dom = buildFocusDom();

        loadModule(dom);
        dispatchInteraction(dom.listsButton, 'focusin', dom.root);

        assert.equal(dom.proposal.classList.contains('is-muted'), true);
    });

    it('click en propuesta colapsa listas y restaura propuesta activa', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        dispatchInteraction(dom.listsButton, 'click', dom.root);
        dispatchInteraction(dom.proposalButton, 'click', dom.root);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), true);
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

    it('focusin en propuesta colapsa listas', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        loaded.exports.applyWorkZone('organizing');
        dispatchInteraction(dom.proposalButton, 'focusin', dom.root);

        assert.equal(dom.listsBody.classList.contains('is-collapsed'), true);
        assert.equal(dom.proposal.classList.contains('is-muted'), false);
    });

    it('click fuera del root no cambia estado', () => {
        var dom = buildFocusDom();
        var loaded = loadModule(dom);

        loaded.exports.applyWorkZone('organizing');

        var event = {
            target: dom.outside,
            preventDefault: function () {},
            stopPropagation: function () {}
        };

        (dom.root.listeners['click:capture'] || []).forEach(function (handler) {
            handler(event);
        });

        assert.equal(dom.root.getAttribute('data-work-zone'), 'organizing');
        assert.equal(loaded.workZoneCalls.length, 1);
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
