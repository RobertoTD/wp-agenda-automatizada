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
        listeners: {},
        inert: false,
        addEventListener: function (type, handler) {
            this.listeners[type] = this.listeners[type] || [];
            this.listeners[type].push(handler);
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

function buildDom(options) {
    var opts = options || {};
    var bodyClasses = opts.bodyCollapsed !== false
        ? ['aa-lists-body', 'is-collapsed']
        : ['aa-lists-body'];
    var bodyAttrs = opts.bodyCollapsed !== false
        ? { 'aria-hidden': 'true', inert: '' }
        : {};
    var toggleAttrs = {
        'aria-expanded': opts.bodyCollapsed !== false ? 'false' : 'true',
        'aria-controls': 'aa-lists-body'
    };

    var toggle = makeElement('button', {
        id: 'aa-lists-header-toggle',
        attributes: toggleAttrs
    });
    var body = makeElement('div', {
        id: 'aa-lists-body',
        classes: bodyClasses,
        attributes: bodyAttrs
    });

    var documentMock = {
        readyState: 'complete',
        getElementById: function (id) {
            if (id === 'aa-lists-header-toggle') {
                return toggle;
            }
            if (id === 'aa-lists-body') {
                return body;
            }
            return null;
        },
        addEventListener: function () {}
    };

    return {
        document: documentMock,
        toggle: toggle,
        body: body
    };
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
    context.AAExecutiveProposal = undefined;

    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    return {
        exports: context.module.exports,
        context: context
    };
}

function clickToggle(toggle) {
    (toggle.listeners['click'] || []).forEach(function (handler) {
        handler();
    });
}

describe('executive-lists-focus-module — toggle explícito del organizador', () => {
    it('index.php no contiene data-work-zone', () => {
        assert.doesNotMatch(indexSrc, /data-work-zone/);
    });

    it('index.php conserva header persistente y body contraíble', () => {
        assert.match(indexSrc, /Organizador · Listas de tareas/);
        assert.match(indexSrc, /id="aa-lists-header"/);
        assert.match(indexSrc, /id="aa-lists-body"/);
        assert.match(indexSrc, /id="aa-lists-header-toggle"/);
    });

    it('CSS no contiene reglas de opacidad del ejecutor ni is-muted', () => {
        var css = fs.readFileSync(adminSourceCssPath, 'utf8');

        assert.doesNotMatch(css, /#aa-executive-proposal\.is-muted/);
        assert.doesNotMatch(css, /#aa-executive-proposal\s*\{[^}]*transition:\s*opacity/);
        assert.doesNotMatch(css, /#aa-lists-header-toggle\s*\{[^}]*transition:\s*opacity/);
        assert.doesNotMatch(css, /#aa-lists-header-toggle\[aria-expanded="false"\]\s*\{[^}]*opacity/);
    });

    it('conserva el estado inicial definido por el HTML (colapsado)', () => {
        var dom = buildDom({ bodyCollapsed: true });
        loadModule(dom);

        assert.equal(dom.body.classList.contains('is-collapsed'), true);
        assert.equal(dom.body.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.toggle.getAttribute('aria-expanded'), 'false');
    });

    it('conserva el estado inicial expandido si el HTML lo define así', () => {
        var dom = buildDom({ bodyCollapsed: false });
        loadModule(dom);

        assert.equal(dom.body.classList.contains('is-collapsed'), false);
        assert.equal(dom.body.getAttribute('aria-hidden'), null);
        assert.equal(dom.toggle.getAttribute('aria-expanded'), 'true');
    });

    it('clic en toggle expande el cuerpo si está colapsado', () => {
        var dom = buildDom({ bodyCollapsed: true });
        loadModule(dom);

        clickToggle(dom.toggle);

        assert.equal(dom.body.classList.contains('is-collapsed'), false);
        assert.equal(dom.body.getAttribute('aria-hidden'), null);
        assert.equal(dom.toggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.body.inert, false);
    });

    it('segundo clic vuelve a colapsar el cuerpo', () => {
        var dom = buildDom({ bodyCollapsed: true });
        loadModule(dom);

        clickToggle(dom.toggle);
        clickToggle(dom.toggle);

        assert.equal(dom.body.classList.contains('is-collapsed'), true);
        assert.equal(dom.body.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.body.inert, true);
    });

    it('aria-expanded, aria-hidden, inert e is-collapsed permanecen sincronizados', () => {
        var dom = buildDom({ bodyCollapsed: true });
        loadModule(dom);

        clickToggle(dom.toggle);
        assert.equal(dom.toggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.body.getAttribute('aria-hidden'), null);
        assert.equal(dom.body.inert, false);
        assert.equal(dom.body.classList.contains('is-collapsed'), false);

        clickToggle(dom.toggle);
        assert.equal(dom.toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.body.getAttribute('aria-hidden'), 'true');
        assert.equal(dom.body.inert, true);
        assert.equal(dom.body.classList.contains('is-collapsed'), true);
    });

    it('clic dentro de la sección fuera del botón no cambia el estado', () => {
        var dom = buildDom({ bodyCollapsed: true });
        loadModule(dom);

        assert.equal(dom.body.classList.contains('is-collapsed'), true);
    });

    it('focusin dentro de una sección no cambia el estado', () => {
        var dom = buildDom({ bodyCollapsed: true });
        loadModule(dom);

        assert.equal(dom.body.listeners['focusin'], undefined);
        assert.equal(dom.toggle.listeners['focusin'], undefined);
        assert.equal(dom.body.classList.contains('is-collapsed'), true);
    });

    it('disparar wheel no cambia el estado', () => {
        var dom = buildDom({ bodyCollapsed: true });
        loadModule(dom);

        assert.equal(dom.body.listeners['wheel'], undefined);
        assert.equal(dom.toggle.listeners['wheel'], undefined);
        assert.equal(dom.body.classList.contains('is-collapsed'), true);
    });

    it('disparar scroll en window no cambia el estado', () => {
        var dom = buildDom({ bodyCollapsed: true });
        var loaded = loadModule(dom);

        assert.equal(loaded.context.listeners, undefined);
        assert.equal(dom.body.classList.contains('is-collapsed'), true);
    });

    it('la inicialización repetida no duplica listeners', () => {
        var dom = buildDom({ bodyCollapsed: true });
        var loaded = loadModule(dom);

        var listenersBefore = (dom.toggle.listeners['click'] || []).length;

        loaded.exports.bind();
        loaded.exports.bind();

        var listenersAfter = (dom.toggle.listeners['click'] || []).length;

        assert.equal(listenersAfter, listenersBefore);
    });

    it('no se aplica is-muted a ninguna sección', () => {
        var dom = buildDom({ bodyCollapsed: true });
        loadModule(dom);

        clickToggle(dom.toggle);

        assert.equal(dom.body.classList.contains('is-muted'), false);
        assert.doesNotMatch(moduleSrc, /is-muted/);
    });

    it('no se llama a AAExecutiveProposal.setWorkZone', () => {
        assert.doesNotMatch(moduleSrc, /setWorkZone/);
        assert.doesNotMatch(moduleSrc, /AAExecutiveProposal/);
    });

    it('la ausencia de nodos no produce una excepción fatal', () => {
        var emptyDoc = {
            readyState: 'complete',
            getElementById: function () { return null; },
            addEventListener: function () {}
        };

        var context = {
            window: {},
            console: console,
            document: emptyDoc,
            module: { exports: {} }
        };
        context.window = context;
        context.globalThis = context;

        assert.doesNotThrow(function () {
            vm.runInNewContext(moduleSrc, context, { filename: modulePath });
        });

        assert.doesNotThrow(function () {
            context.module.exports.handleToggleClick();
        });
    });

    it('módulo no registra listeners en window', () => {
        var windowListeners = {};
        var dom = buildDom({ bodyCollapsed: true });

        var context = {
            window: {},
            console: console,
            document: dom.document,
            module: { exports: {} },
            addEventListener: function (type) {
                windowListeners[type] = true;
            }
        };
        context.window = context;
        context.globalThis = context;

        vm.runInNewContext(moduleSrc, context, { filename: modulePath });

        assert.equal(windowListeners['scroll'], undefined);
        assert.equal(windowListeners['wheel'], undefined);
        assert.equal(windowListeners['resize'], undefined);
    });
});
