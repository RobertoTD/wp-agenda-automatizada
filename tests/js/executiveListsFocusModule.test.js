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

function loadModule(dom) {
    var context = {
        window: {},
        console: console,
        document: dom.document,
        module: { exports: {} }
    };

    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    return context.module.exports;
}

describe('executive-lists-focus-module MC-UX-F', () => {
    it('index.php expone header, divider, is-muted y enqueue del módulo', () => {
        assert.match(indexSrc, />Listas de tareas</);
        assert.doesNotMatch(indexSrc, />Todas las listas de tareas.</);
        assert.match(indexSrc, /aa-executive-lists-divider/);
        assert.match(indexSrc, /id="aa-executive-proposal"/);
        assert.match(indexSrc, /id="aa-lists-section" class="pb-24 is-muted"/);
        assert.match(indexSrc, /executive-lists-focus-module\.js/);
    });

    it('CSS define transición, opacidad atenuada y sin pointer-events none en section', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.match(css, /#aa-lists-section[\s\S]*transition:\s*opacity 0\.18s ease-in-out/);
        assert.match(css, /#aa-lists-section\.is-muted[\s\S]*opacity:\s*0\.50/);
        assert.match(css, /\.aa-executive-lists-divider hr/);
        assert.doesNotMatch(css, /#aa-lists-section[\s\S]*pointer-events:\s*none/);
    });

    it('estado inicial mantiene is-muted en listas', () => {
        var dom = buildFocusDom();

        loadModule(dom);

        assert.equal(dom.listsSection.classList.contains('is-muted'), true);
    });

    it('click dentro de listas quita is-muted sin interceptar evento', () => {
        var dom = buildFocusDom();
        var api = loadModule(dom);

        dom.listsSection.classList.add('is-muted');
        var event = dispatchInteraction(dom.listsButton, 'click', dom.root);

        assert.equal(dom.listsSection.classList.contains('is-muted'), false);
        assert.equal(event.preventDefaultCalled, false);
        assert.equal(event.stopPropagationCalled, false);
        assert.equal(typeof api.handleRootInteraction, 'function');
    });

    it('focusin dentro de listas quita is-muted', () => {
        var dom = buildFocusDom();

        loadModule(dom);
        dom.listsSection.classList.add('is-muted');
        dispatchInteraction(dom.listsButton, 'focusin', dom.root);

        assert.equal(dom.listsSection.classList.contains('is-muted'), false);
    });

    it('click dentro de propuesta agrega is-muted', () => {
        var dom = buildFocusDom();

        loadModule(dom);
        dom.listsSection.classList.remove('is-muted');
        dispatchInteraction(dom.proposalButton, 'click', dom.root);

        assert.equal(dom.listsSection.classList.contains('is-muted'), true);
    });

    it('focusin dentro de propuesta agrega is-muted', () => {
        var dom = buildFocusDom();

        loadModule(dom);
        dom.listsSection.classList.remove('is-muted');
        dispatchInteraction(dom.proposalButton, 'focusin', dom.root);

        assert.equal(dom.listsSection.classList.contains('is-muted'), true);
    });

    it('click fuera del root no cambia estado', () => {
        var dom = buildFocusDom();

        loadModule(dom);
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
    });
});
