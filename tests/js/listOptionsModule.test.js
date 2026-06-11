'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/list-options-module.js');
const rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const rendererSrc = fs.readFileSync(rendererPath, 'utf8');

function makeClassList(initialHidden) {
    var classes = initialHidden ? ['hidden'] : [];

    return {
        classes: classes,
        add: function (cls) {
            if (classes.indexOf(cls) === -1) {
                classes.push(cls);
            }
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

function makeElement(tag, options) {
    var opts = options || {};

    return {
        tagName: tag,
        classList: opts.classList || makeClassList(!!opts.hidden),
        attributes: Object.assign({}, opts.attributes || {}),
        disabled: !!opts.disabled,
        parent: opts.parent || null,
        children: opts.children || [],
        listeners: {},
        setAttribute: function (name, value) {
            this.attributes[name] = String(value);
        },
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
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
        querySelector: function (selector) {
            if (selector.indexOf('.aa-executable-list-options-menu[data-list-id="') === 0) {
                var listId = selector.match(/data-list-id="([^"]+)"/);

                return this.findByPredicate(function (node) {
                    return node.classList
                        && node.classList.contains('aa-executable-list-options-menu')
                        && node.getAttribute('data-list-id') === (listId ? listId[1] : '');
                });
            }

            if (selector.indexOf('.aa-executable-list-options-trigger[data-list-id="') === 0) {
                var triggerId = selector.match(/data-list-id="([^"]+)"/);

                return this.findByPredicate(function (node) {
                    return node.getAttribute
                        && node.getAttribute('data-aa-list-options-trigger') === '1'
                        && node.getAttribute('data-list-id') === (triggerId ? triggerId[1] : '');
                });
            }

            return null;
        },
        querySelectorAll: function (selector) {
            var matches = [];

            this.walk(function (node) {
                if (selector === '.aa-executable-list-options-menu'
                    && node.classList
                    && node.classList.contains('aa-executable-list-options-menu')) {
                    matches.push(node);
                }

                if (selector === '.aa-executable-list-options-trigger'
                    && node.getAttribute
                    && node.getAttribute('data-aa-list-options-trigger') === '1') {
                    matches.push(node);
                }
            });

            return matches;
        },
        walk: function (visitor) {
            visitor(this);
            this.children.forEach(function (child) {
                if (child.walk) {
                    child.walk(visitor);
                }
            });
        },
        findByPredicate: function (predicate) {
            var found = null;

            this.walk(function (node) {
                if (!found && predicate(node)) {
                    found = node;
                }
            });

            return found;
        },
        closest: function (selector) {
            var node = this;

            while (node) {
                if (selector === '[data-aa-list-options-trigger]'
                    && node.getAttribute
                    && node.getAttribute('data-aa-list-options-trigger') === '1') {
                    return node;
                }

                if (selector === '.aa-executable-list-options'
                    && node.classList
                    && node.classList.contains('aa-executable-list-options')) {
                    return node;
                }

                if (selector === '.aa-executable-list-options-menu [role="menuitem"]'
                    && node.getAttribute
                    && node.getAttribute('role') === 'menuitem'
                    && node.parent
                    && node.parent.classList
                    && node.parent.classList.contains('aa-executable-list-options-menu')) {
                    return node;
                }

                node = node.parent;
            }

            return null;
        }
    };
}

function buildListCardDom(listId) {
    var menu = makeElement('div', {
        hidden: true,
        classList: makeClassList(true),
        attributes: {
            'data-list-id': listId,
            role: 'menu'
        }
    });
    menu.classList.classes.push('aa-executable-list-options-menu');

    var archiveItem = makeElement('button', {
        attributes: {
            role: 'menuitem',
            'data-tasks-action': 'archive-list',
            'data-list-id': listId
        }
    });

    menu.appendChild(archiveItem);

    var trigger = makeElement('button', {
        attributes: {
            'data-aa-list-options-trigger': '1',
            'data-list-id': listId,
            'aria-expanded': 'false'
        }
    });

    var optionsWrap = makeElement('div', {
        children: [trigger, menu]
    });
    optionsWrap.classList.classes.push('aa-executable-list-options');

    var details = makeElement('details', {
        attributes: {
            'data-list-id': listId,
            open: 'true'
        },
        children: [optionsWrap]
    });
    details.classList.classes.push('aa-executable-list-card');
    details.open = true;

    var moduleRoot = makeElement('div', {
        attributes: { id: 'aa-tasks-module-root' },
        children: [details]
    });

    var documentMock = {
        readyState: 'complete',
        addEventListener: function (type, handler, useCapture) {
            var key = type + (useCapture ? ':capture' : ':bubble');
            documentMock.listeners[key] = documentMock.listeners[key] || [];
            documentMock.listeners[key].push(handler);
        },
        listeners: {},
        getElementById: function (id) {
            return id === 'aa-tasks-module-root' ? moduleRoot : null;
        },
        querySelector: function (selector) {
            return moduleRoot.querySelector(selector);
        },
        querySelectorAll: function (selector) {
            return moduleRoot.querySelectorAll(selector);
        }
    };

    return {
        document: documentMock,
        moduleRoot: moduleRoot,
        details: details,
        trigger: trigger,
        menu: menu,
        archiveItem: archiveItem
    };
}

function dispatchClick(target, documentMock) {
    var event = {
        target: target,
        preventDefault: function () {},
        stopPropagation: function () {}
    };

    (documentMock.listeners['click:capture'] || []).forEach(function (handler) {
        handler(event);
    });
    (documentMock.listeners['click:bubble'] || []).forEach(function (handler) {
        handler(event);
    });
}

function dispatchKeydown(key, documentMock) {
    var event = { key: key };

    (documentMock.listeners['keydown:bubble'] || []).forEach(function (handler) {
        handler(event);
    });
}

function dispatchToggle(details, documentMock) {
    var event = { target: details };

    (documentMock.listeners['toggle:capture'] || []).forEach(function (handler) {
        handler(event);
    });
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

describe('list-options-module MC13L-B', () => {
    it('index.php encola list-options-module.js', () => {
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /list-options-module\.js/);
    });

    it('renderer expone menú ⋮ y archivar dentro del menú', () => {
        assert.match(rendererSrc, /data-aa-list-options-trigger/);
        assert.match(rendererSrc, /aa-executable-list-options-menu/);
        assert.match(rendererSrc, /Archivar lista/);
        assert.match(rendererSrc, /Editar lista/);
        assert.match(rendererSrc, /data-aa-list-edit/);
        assert.doesNotMatch(rendererSrc, /class="[^"]*text-xs[^"]*"[^>]*>Archivar</);
    });

    it('click en ⋮ abre y cierra el menú de la lista', () => {
        var dom = buildListCardDom('7');
        var api = loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), false);
        assert.equal(api.getOpenListId(), '7');

        dispatchClick(dom.trigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), true);
        assert.equal(api.getOpenListId(), '');
    });

    it('click fuera cierra el menú abierto', () => {
        var dom = buildListCardDom('8');
        var outside = makeElement('div', { attributes: { id: 'outside' } });
        dom.moduleRoot.appendChild(outside);
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dispatchClick(outside, dom.document);

        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('Escape cierra el menú abierto', () => {
        var dom = buildListCardDom('9');
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dispatchKeydown('Escape', dom.document);

        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('abrir un menú cierra otro previamente abierto', () => {
        var dom = buildListCardDom('10');
        var second = buildListCardDom('11');
        dom.moduleRoot.appendChild(second.details);
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dispatchClick(second.trigger, dom.document);

        assert.equal(dom.menu.classList.contains('hidden'), true);
        assert.equal(second.menu.classList.contains('hidden'), false);
    });

    it('colapsar la lista cierra el menú', () => {
        var dom = buildListCardDom('12');
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dom.details.open = false;
        dispatchToggle(dom.details, dom.document);

        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('click en Archivar conserva data-tasks-action para coordinator', () => {
        var dom = buildListCardDom('13');
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dispatchClick(dom.archiveItem, dom.document);

        assert.equal(dom.archiveItem.getAttribute('data-tasks-action'), 'archive-list');
        assert.equal(dom.menu.classList.contains('hidden'), true);
    });
});
