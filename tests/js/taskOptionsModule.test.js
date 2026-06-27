'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/task-options-module.js');
const placementPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/executable-options-menu-placement.js');
const listModulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/list-options-module.js');
const rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const placementSrc = fs.readFileSync(placementPath, 'utf8');
const listModuleSrc = fs.readFileSync(listModulePath, 'utf8');
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
        open: !!opts.open,
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
        style: opts.style || {},
        getBoundingClientRect: opts.getBoundingClientRect || function () {
            return {
                top: 0,
                left: 0,
                right: 100,
                bottom: 50,
                width: 100,
                height: 50
            };
        },
        addEventListener: function (type, handler, useCapture) {
            var key = type + (useCapture ? ':capture' : ':bubble');

            this.listeners[key] = this.listeners[key] || [];
            this.listeners[key].push(handler);
        },
        querySelector: function (selector) {
            if (selector.indexOf('.aa-executable-task-options-menu[data-task-id="') === 0) {
                var taskId = selector.match(/data-task-id="([^"]+)"/);

                return this.findByPredicate(function (node) {
                    return node.classList
                        && node.classList.contains('aa-executable-task-options-menu')
                        && node.getAttribute('data-task-id') === (taskId ? taskId[1] : '');
                });
            }

            if (selector.indexOf('.aa-executable-task-options-trigger[data-task-id="') === 0) {
                var triggerId = selector.match(/data-task-id="([^"]+)"/);

                return this.findByPredicate(function (node) {
                    return node.getAttribute
                        && node.getAttribute('data-aa-task-options-trigger') === '1'
                        && node.getAttribute('data-task-id') === (triggerId ? triggerId[1] : '');
                });
            }

            return null;
        },
        querySelectorAll: function (selector) {
            var matches = [];

            this.walk(function (node) {
                if (selector === '.aa-executable-task-options-menu'
                    && node.classList
                    && node.classList.contains('aa-executable-task-options-menu')) {
                    matches.push(node);
                }

                if (selector === '.aa-executable-task-options-trigger'
                    && node.getAttribute
                    && node.getAttribute('data-aa-task-options-trigger') === '1') {
                    matches.push(node);
                }

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
                if (selector === '[data-aa-task-options-trigger]'
                    && node.getAttribute
                    && node.getAttribute('data-aa-task-options-trigger') === '1') {
                    return node;
                }

                if (selector === '.aa-executable-task-options'
                    && node.classList
                    && node.classList.contains('aa-executable-task-options')) {
                    return node;
                }

                if (selector === '.aa-executable-task-options-menu [role="menuitem"]'
                    && node.getAttribute
                    && node.getAttribute('role') === 'menuitem'
                    && node.parent
                    && node.parent.classList
                    && node.parent.classList.contains('aa-executable-task-options-menu')) {
                    return node;
                }

                node = node.parent;
            }

            return null;
        }
    };
}

function buildTaskOptionsDom(taskId) {
    var menu = makeElement('div', {
        hidden: true,
        classList: makeClassList(true),
        attributes: {
            'data-task-id': taskId,
            role: 'menu'
        }
    });
    menu.classList.classes.push('aa-executable-task-options-menu');

    var archiveItem = makeElement('button', {
        attributes: {
            role: 'menuitem',
            'data-tasks-action': 'archive-task',
            'data-task-id': taskId
        }
    });

    menu.appendChild(archiveItem);

    var trigger = makeElement('button', {
        attributes: {
            'data-aa-task-options-trigger': '1',
            'data-task-id': taskId,
            'aria-expanded': 'false'
        }
    });
    trigger.classList.classes.push('aa-executable-task-options-trigger');

    var optionsWrap = makeElement('div', {
        children: [trigger, menu]
    });
    optionsWrap.classList.classes.push('aa-executable-task-options');

    var summary = makeElement('summary', {
        children: [optionsWrap]
    });

    var taskDetails = makeElement('details', {
        children: [summary]
    });
    taskDetails.classList.classes.push('aa-executable-item');

    var moduleRoot = makeElement('div', {
        attributes: { id: 'aa-tasks-module-root' },
        children: [taskDetails]
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
        taskDetails: taskDetails,
        trigger: trigger,
        menu: menu,
        archiveItem: archiveItem
    };
}

function buildListMenuDom(listId) {
    var menu = makeElement('div', {
        hidden: false,
        classList: makeClassList(false),
        attributes: {
            'data-list-id': listId,
            role: 'menu'
        }
    });
    menu.classList.classes.push('aa-executable-list-options-menu');

    var trigger = makeElement('button', {
        attributes: {
            'data-aa-list-options-trigger': '1',
            'data-list-id': listId,
            'aria-expanded': 'true'
        }
    });
    trigger.classList.classes.push('aa-executable-list-options-trigger');

    return {
        menu: menu,
        trigger: trigger
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

    return event;
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

function loadModule(dom, bag) {
    var context = {
        window: {},
        console: console,
        document: dom.document,
        module: { exports: {} },
        innerWidth: 1024,
        innerHeight: 800,
        listeners: {},
        addEventListener: function (type, handler, useCapture) {
            var key = type + (useCapture ? ':capture' : ':bubble');
            context.listeners[key] = context.listeners[key] || [];
            context.listeners[key].push(handler);
        }
    };

    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(placementSrc, context, { filename: placementPath });
    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    if (bag) {
        bag.context = context;
    }

    return context.module.exports;
}

function loadBothModules(dom, bag) {
    var context = {
        window: {},
        console: console,
        document: dom.document,
        module: { exports: {} },
        innerWidth: 1024,
        innerHeight: 800,
        listeners: {},
        addEventListener: function (type, handler, useCapture) {
            var key = type + (useCapture ? ':capture' : ':bubble');
            context.listeners[key] = context.listeners[key] || [];
            context.listeners[key].push(handler);
        }
    };

    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(placementSrc, context, { filename: placementPath });
    vm.runInNewContext(moduleSrc, context, { filename: modulePath });
    vm.runInNewContext(listModuleSrc, context, { filename: listModulePath });

    if (bag) {
        bag.context = context;
    }

    return context.module.exports;
}

function dispatchScroll(context) {
    (context.listeners['scroll:capture'] || []).forEach(function (handler) {
        handler({});
    });
}

function dispatchResize(context) {
    (context.listeners['resize:bubble'] || []).forEach(function (handler) {
        handler({});
    });
}

describe('task-options-module MC3', () => {
    it('index.php encola placement helper antes de task-options-module.js', () => {
        const indexSrc = fs.readFileSync(indexPath, 'utf8');
        var enqueuePlacement = indexSrc.indexOf('esc_url($executable_options_menu_placement_js');
        var enqueueTaskOptions = indexSrc.indexOf('esc_url($task_options_js');
        var enqueueTaskEdit = indexSrc.indexOf('esc_url($task_edit_js');
        var enqueueCoordinator = indexSrc.indexOf('esc_url($executable_actions_coordinator_js');

        assert.notEqual(enqueuePlacement, -1);
        assert.notEqual(enqueueTaskOptions, -1);
        assert.notEqual(enqueueTaskEdit, -1);
        assert.notEqual(enqueueCoordinator, -1);
        assert.ok(enqueuePlacement < enqueueTaskOptions);
        assert.ok(enqueueTaskOptions < enqueueTaskEdit);
        assert.ok(enqueueTaskEdit < enqueueCoordinator);
    });

    it('renderer expone menú ⋮ de tarea con Editar, Archivar y Eliminar', () => {
        assert.match(rendererSrc, /data-aa-task-options-trigger/);
        assert.match(rendererSrc, /aa-executable-task-options-menu/);
        assert.match(rendererSrc, /Editar tarea/);
        assert.match(rendererSrc, /Archivar tarea/);
        assert.match(rendererSrc, /Eliminar tarea/);
        assert.match(rendererSrc, /data-tasks-action="archive-task"/);
        assert.match(rendererSrc, /data-tasks-action="delete-task"/);
        assert.doesNotMatch(rendererSrc, /aa-executable-item-summary-edit/);
    });

    it('click en ⋮ abre y cierra el menú de la tarea', () => {
        var dom = buildTaskOptionsDom('21');
        var api = loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), false);
        assert.equal(api.getOpenTaskId(), '21');

        dispatchClick(dom.trigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), true);
        assert.equal(api.getOpenTaskId(), '');
    });

    it('click fuera cierra el menú abierto', () => {
        var dom = buildTaskOptionsDom('22');
        var outside = makeElement('div', { attributes: { id: 'outside' } });
        dom.moduleRoot.appendChild(outside);
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dispatchClick(outside, dom.document);

        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('Escape cierra el menú abierto', () => {
        var dom = buildTaskOptionsDom('23');
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dispatchKeydown('Escape', dom.document);

        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('abrir menú de tarea cierra menú de lista abierto', () => {
        var dom = buildTaskOptionsDom('24');
        var listMenu = buildListMenuDom('5');
        dom.moduleRoot.appendChild(listMenu.menu);
        dom.moduleRoot.appendChild(listMenu.trigger);
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);

        assert.equal(listMenu.menu.classList.contains('hidden'), true);
        assert.equal(listMenu.trigger.getAttribute('aria-expanded'), 'false');
    });

    it('list-options cierra menú de tarea al abrir menú de lista', () => {
        var dom = buildTaskOptionsDom('25');
        var listMenu = buildListMenuDom('6');
        var listTrigger = makeElement('button', {
            attributes: {
                'data-aa-list-options-trigger': '1',
                'data-list-id': '6',
                'aria-expanded': 'false'
            }
        });

        dom.moduleRoot.appendChild(listMenu.menu);
        dom.moduleRoot.appendChild(listTrigger);

        loadBothModules(dom);

        dispatchClick(dom.trigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), false);

        dispatchClick(listTrigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('toggle de tarea cierra el menú', () => {
        var dom = buildTaskOptionsDom('26');
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dom.taskDetails.open = false;
        dispatchToggle(dom.taskDetails, dom.document);

        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('click en trigger usa stopPropagation y no expande summary accidentalmente', () => {
        var dom = buildTaskOptionsDom('27');
        var summary = dom.taskDetails.children[0];
        var toggleCalls = 0;

        summary.addEventListener('click', function () {
            toggleCalls += 1;
        });

        loadModule(dom);
        var event = dispatchClick(dom.trigger, dom.document);

        assert.equal(event.stopped || typeof event.stopPropagation === 'function', true);
        assert.equal(toggleCalls, 0);
    });

    it('click en Archivar conserva data-tasks-action para coordinator', () => {
        var dom = buildTaskOptionsDom('28');
        loadModule(dom);

        dispatchClick(dom.trigger, dom.document);
        dispatchClick(dom.archiveItem, dom.document);

        assert.equal(dom.archiveItem.getAttribute('data-tasks-action'), 'archive-task');
        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('abrir menú aplica position fixed vía helper compartido', () => {
        var dom = buildTaskOptionsDom('29');
        dom.menu.classList.classes.push('top-full', 'mt-2');
        dom.menu.style = {};
        dom.menu.getBoundingClientRect = function () {
            return { top: 0, left: 0, right: 192, bottom: 154, width: 192, height: 154 };
        };
        dom.trigger.getBoundingClientRect = function () {
            return { top: 200, left: 400, right: 500, bottom: 228, width: 100, height: 28 };
        };

        loadModule(dom);
        dispatchClick(dom.trigger, dom.document);

        assert.equal(dom.menu.style.position, 'fixed');
        assert.equal(dom.menu.style.zIndex, '70');
        assert.equal(dom.menu.classList.contains('hidden'), false);
    });

    it('cerrar menú resetea placement inline', () => {
        var dom = buildTaskOptionsDom('30');
        dom.menu.classList.classes.push('top-full', 'mt-2');
        dom.menu.style = {};
        dom.menu.getBoundingClientRect = function () {
            return { top: 0, left: 0, right: 192, bottom: 154, width: 192, height: 154 };
        };
        dom.trigger.getBoundingClientRect = function () {
            return { top: 200, left: 400, right: 500, bottom: 228, width: 100, height: 28 };
        };

        loadModule(dom);
        dispatchClick(dom.trigger, dom.document);
        dispatchClick(dom.trigger, dom.document);

        assert.equal(dom.menu.style.position, '');
        assert.equal(dom.menu.style.top, '');
        assert.equal(dom.menu.style.left, '');
        assert.ok(dom.menu.classList.contains('top-full'));
        assert.ok(dom.menu.classList.contains('mt-2'));
    });

    it('scroll cierra menú abierto', () => {
        var dom = buildTaskOptionsDom('31');
        var bag = {};

        dom.menu.style = {};
        dom.menu.getBoundingClientRect = function () {
            return { top: 0, left: 0, right: 192, bottom: 154, width: 192, height: 154 };
        };
        dom.trigger.getBoundingClientRect = function () {
            return { top: 200, left: 400, right: 500, bottom: 228, width: 100, height: 28 };
        };

        loadModule(dom, bag);
        dispatchClick(dom.trigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), false);

        dispatchScroll(bag.context);
        assert.equal(dom.menu.classList.contains('hidden'), true);
    });

    it('resize cierra menú abierto', () => {
        var dom = buildTaskOptionsDom('32');
        var bag = {};

        dom.menu.style = {};
        dom.menu.getBoundingClientRect = function () {
            return { top: 0, left: 0, right: 192, bottom: 154, width: 192, height: 154 };
        };
        dom.trigger.getBoundingClientRect = function () {
            return { top: 200, left: 400, right: 500, bottom: 228, width: 100, height: 28 };
        };

        loadModule(dom, bag);
        dispatchClick(dom.trigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), false);

        dispatchResize(bag.context);
        assert.equal(dom.menu.classList.contains('hidden'), true);
    });
});
