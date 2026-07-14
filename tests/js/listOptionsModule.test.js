'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/list-options-module.js');
const placementPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/executable-options-menu-placement.js');
const rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const placementSrc = fs.readFileSync(placementPath, 'utf8');
const rendererSrc = fs.readFileSync(rendererPath, 'utf8');

function nodeMatchesSelector(node, selector) {
    if (!node || !node.classList) {
        return false;
    }

    if (selector === 'details.aa-executable-list-card[open]') {
        return node.tagName === 'details'
            && node.classList.contains('aa-executable-list-card')
            && node.open === true;
    }

    if (selector === 'details.aa-executable-list-card') {
        return node.tagName === 'details'
            && node.classList.contains('aa-executable-list-card');
    }

    if (selector === 'details.aa-executable-item') {
        return node.tagName === 'details' && node.classList.contains('aa-executable-item');
    }

    if (selector === 'details.aa-executable-item[open]') {
        return node.tagName === 'details'
            && node.classList.contains('aa-executable-item')
            && node.open === true;
    }

    if (selector === 'details.aa-executable-following-tasks') {
        return node.tagName === 'details'
            && node.classList.contains('aa-executable-following-tasks');
    }

    if (selector === 'details.aa-executable-following-tasks[open]') {
        return node.tagName === 'details'
            && node.classList.contains('aa-executable-following-tasks')
            && node.open === true;
    }

    return false;
}

function queryDescendants(root, selector, firstOnly) {
    var matches = [];

    root.walk(function (node) {
        if (node === root) {
            return;
        }

        if (nodeMatchesSelector(node, selector)) {
            matches.push(node);

            if (firstOnly) {
                return false;
            }
        }
    });

    return firstOnly ? (matches[0] || null) : matches;
}

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
        _textContent: opts.textContent || '',
        get textContent() {
            return this._textContent;
        },
        set textContent(value) {
            this._textContent = value === null || value === undefined ? '' : String(value);
        },
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

            if (selector.indexOf('.aa-executable-list-details[data-list-id="') === 0) {
                var detailsPanelId = selector.match(/data-list-id="([^"]+)"/);

                return this.findByPredicate(function (node) {
                    return node.classList
                        && node.classList.contains('aa-executable-list-details')
                        && node.getAttribute('data-list-id') === (detailsPanelId ? detailsPanelId[1] : '');
                });
            }

            if (selector.indexOf('.aa-executable-list-details-toggle[data-list-id="') === 0) {
                var detailsToggleId = selector.match(/data-list-id="([^"]+)"/);

                return this.findByPredicate(function (node) {
                    return node.getAttribute
                        && node.getAttribute('data-aa-list-details-toggle') === '1'
                        && node.getAttribute('data-list-id') === (detailsToggleId ? detailsToggleId[1] : '');
                });
            }

            if (selector.indexOf('details.aa-executable') === 0) {
                return queryDescendants(this, selector, true);
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

                if (selector === '.aa-executable-list-details'
                    && node.classList
                    && node.classList.contains('aa-executable-list-details')) {
                    matches.push(node);
                }

                if (selector === '.aa-executable-list-details-toggle'
                    && node.getAttribute
                    && node.getAttribute('data-aa-list-details-toggle') === '1') {
                    matches.push(node);
                }

                if (node !== this && nodeMatchesSelector(node, selector)) {
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
                if (selector === '[data-aa-list-add-task]'
                    && node.getAttribute
                    && node.getAttribute('data-aa-list-add-task') === '1') {
                    return node;
                }

                if (selector === '[data-aa-list-options-trigger]'
                    && node.getAttribute
                    && node.getAttribute('data-aa-list-options-trigger') === '1') {
                    return node;
                }

                if (selector === '[data-aa-list-details-toggle]'
                    && node.getAttribute
                    && node.getAttribute('data-aa-list-details-toggle') === '1') {
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

                if (selector === 'details.aa-executable-list-card'
                    && node.tagName === 'details'
                    && node.classList
                    && node.classList.contains('aa-executable-list-card')) {
                    return node;
                }

                node = node.parent;
            }

            return null;
        }
    };
}

function buildTaskItem(taskId, open) {
    var task = makeElement('details', {
        attributes: {
            'data-task-id': taskId
        }
    });

    task.classList.classes.push('aa-executable-item');
    task.open = !!open;

    return task;
}

function buildListWithFollowingTasks(listId, topTaskId, followingTaskIds, open) {
    var dom = buildListCardDom(listId);
    var body = makeElement('div');
    var topTask = buildTaskItem(topTaskId, false);
    var followingBlock = makeElement('details');
    followingBlock.classList.classes.push('aa-executable-following-tasks');
    followingBlock.open = false;

    var followingBody = makeElement('div');
    var followingTasks = followingTaskIds.map(function (taskId) {
        var task = buildTaskItem(taskId, false);

        followingBody.appendChild(task);

        return task;
    });

    followingBlock.appendChild(followingBody);
    body.appendChild(topTask);
    body.appendChild(followingBlock);
    dom.details.appendChild(body);
    dom.details.open = !!open;
    dom.topTask = topTask;
    dom.followingBlock = followingBlock;
    dom.followingTasks = followingTasks;
    dom.body = body;

    return dom;
}

function buildListWithTasks(listId, taskIds, open) {
    var dom = buildListCardDom(listId);
    var body = makeElement('div');
    var tasks = taskIds.map(function (taskId) {
        var task = buildTaskItem(taskId, false);

        body.appendChild(task);

        return task;
    });

    dom.details.appendChild(body);
    dom.details.open = !!open;
    dom.tasks = tasks;
    dom.body = body;

    return dom;
}

function buildListWithDetailsDom(listId, open) {
    var dom = buildListCardDom(listId);
    var panel = makeElement('div', {
        attributes: {
            'data-list-id': listId,
            id: 'aa-list-details-' + listId
        }
    });

    panel.classList.classes.push('aa-executable-list-details');

    var toggle = makeElement('button', {
        attributes: {
            'data-aa-list-details-toggle': '1',
            'data-list-id': listId,
            'aria-expanded': 'false'
        },
        textContent: 'Ver más'
    });

    toggle.classList.classes.push('aa-executable-list-details-toggle');
    dom.details.appendChild(toggle);
    dom.details.appendChild(panel);
    dom.details.open = !!open;
    dom.detailsToggle = toggle;
    dom.detailsPanel = panel;

    return dom;
}

function buildFeedDom() {
    var moduleRoot = makeElement('div', {
        attributes: { id: 'aa-tasks-module-root' }
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
        moduleRoot: moduleRoot
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

describe('list-options-module MC13L-B', () => {
    it('index.php encola placement helper antes de list-options-module.js', () => {
        const indexSrc = fs.readFileSync(indexPath, 'utf8');
        var enqueuePlacement = indexSrc.indexOf('esc_url($executable_options_menu_placement_js');
        var enqueueListOptions = indexSrc.indexOf('esc_url($list_options_js');

        assert.notEqual(enqueuePlacement, -1);
        assert.notEqual(enqueueListOptions, -1);
        assert.ok(enqueuePlacement < enqueueListOptions);
    });

    it('renderer expone menú ⋮ con Editar, Archivar y Eliminar lista', () => {
        assert.match(rendererSrc, /data-aa-list-options-trigger/);
        assert.match(rendererSrc, /aa-executable-list-options-menu/);
        assert.match(rendererSrc, /Archivar lista/);
        assert.match(rendererSrc, /Eliminar lista/);
        assert.match(rendererSrc, /Editar lista/);
        assert.match(rendererSrc, /data-aa-list-edit/);
        assert.match(rendererSrc, /data-tasks-action="delete-list"/);
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

    it('abrir menú aplica position fixed vía helper compartido', () => {
        var dom = buildListCardDom('14');
        dom.menu.classList.classes.push('top-full', 'mt-2');
        dom.menu.style = {};
        dom.menu.getBoundingClientRect = function () {
            return {
                top: 0,
                left: 0,
                right: 192,
                bottom: 154,
                width: 192,
                height: 154
            };
        };
        dom.trigger.getBoundingClientRect = function () {
            return {
                top: 200,
                left: 400,
                right: 500,
                bottom: 228,
                width: 100,
                height: 28
            };
        };

        loadModule(dom);
        dispatchClick(dom.trigger, dom.document);

        assert.equal(dom.menu.style.position, 'fixed');
        assert.equal(dom.menu.style.zIndex, '70');
        assert.equal(dom.menu.classList.contains('hidden'), false);
    });

    it('cerrar menú resetea placement inline', () => {
        var dom = buildListCardDom('15');
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
        var dom = buildListCardDom('16');
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
        var dom = buildListCardDom('17');
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

describe('list-options-module list expansion coordination', () => {
    it('abrir una lista cierra otras listas abiertas', () => {
        var feed = buildFeedDom();
        var listA = buildListWithTasks('1', ['a1'], true);
        var listB = buildListWithTasks('2', ['b1'], false);

        feed.moduleRoot.appendChild(listA.details);
        feed.moduleRoot.appendChild(listB.details);
        loadModule(feed);

        listB.details.open = true;
        dispatchToggle(listB.details, feed.document);

        assert.equal(listA.details.open, false);
        assert.equal(listB.details.open, true);
    });

    it('abrir una lista abre su primera tarea visible', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('3', ['t1', 't2'], false);

        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.details.open = true;
        dispatchToggle(list.details, feed.document);

        assert.equal(list.tasks[0].open, true);
        assert.equal(list.tasks[1].open, false);
    });

    it('abrir una lista deja solo la primera tarea abierta aunque otras estuvieran abiertas', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('3b', ['t1', 't2', 't3'], false);

        list.tasks[1].open = true;
        list.tasks[2].open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.details.open = true;
        dispatchToggle(list.details, feed.document);

        assert.equal(list.tasks[0].open, true);
        assert.equal(list.tasks[1].open, false);
        assert.equal(list.tasks[2].open, false);
    });

    it('cerrar una lista cierra todas sus tareas', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('4', ['t1', 't2'], true);

        list.tasks[0].open = true;
        list.tasks[1].open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.details.open = false;
        dispatchToggle(list.details, feed.document);

        assert.equal(list.tasks[0].open, false);
        assert.equal(list.tasks[1].open, false);
    });

    it('lista sin tareas no genera error al expandirse', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('5', [], false);

        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.details.open = true;

        assert.doesNotThrow(function () {
            dispatchToggle(list.details, feed.document);
        });
        assert.equal(list.details.open, true);
    });

    it('colapsar lista sigue cerrando el menú ⋮', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('6', ['t1'], true);

        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        dispatchClick(list.trigger, feed.document);
        assert.equal(list.menu.classList.contains('hidden'), false);

        list.details.open = false;
        dispatchToggle(list.details, feed.document);

        assert.equal(list.menu.classList.contains('hidden'), true);
    });

    it('coordinación vive solo en JS del módulo de listas', () => {
        assert.match(moduleSrc, /details\.aa-executable-list-card\[open\]/);
        assert.match(moduleSrc, /openFirstTaskInList/);
        assert.match(moduleSrc, /coordinatingListToggle/);
        assert.match(moduleSrc, /coordinatingTaskToggle/);
        assert.match(moduleSrc, /handleTaskToggle/);
        assert.match(moduleSrc, /closeOtherTasksInList/);
        assert.doesNotMatch(rendererSrc, /coordinatingListToggle/);
    });
});

describe('list-options-module MC-UX-B task accordion', () => {
    it('abrir tarea 2 cierra tarea 1 dentro de la misma lista', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('acc-1', ['t1', 't2'], true);

        list.tasks[0].open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.tasks[1].open = true;
        dispatchToggle(list.tasks[1], feed.document);

        assert.equal(list.tasks[0].open, false);
        assert.equal(list.tasks[1].open, true);
    });

    it('abrir tarea 3 cierra tarea 1 y tarea 2 si estaban abiertas', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('acc-2', ['t1', 't2', 't3'], true);

        list.tasks[0].open = true;
        list.tasks[1].open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.tasks[2].open = true;
        dispatchToggle(list.tasks[2], feed.document);

        assert.equal(list.tasks[0].open, false);
        assert.equal(list.tasks[1].open, false);
        assert.equal(list.tasks[2].open, true);
    });

    it('abrir una tarea no afecta tareas de otra lista', () => {
        var feed = buildFeedDom();
        var listA = buildListWithTasks('acc-a', ['a1', 'a2'], true);
        var listB = buildListWithTasks('acc-b', ['b1', 'b2'], true);

        listA.tasks[0].open = true;
        listB.tasks[1].open = true;
        feed.moduleRoot.appendChild(listA.details);
        feed.moduleRoot.appendChild(listB.details);
        loadModule(feed);

        listA.tasks[1].open = true;
        dispatchToggle(listA.tasks[1], feed.document);

        assert.equal(listA.tasks[0].open, false);
        assert.equal(listA.tasks[1].open, true);
        assert.equal(listB.tasks[1].open, true);
    });

    it('cerrar manualmente la única tarea abierta no abre otra automáticamente', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('acc-3', ['t1', 't2'], true);

        list.tasks[0].open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.tasks[0].open = false;
        dispatchToggle(list.tasks[0], feed.document);

        assert.equal(list.tasks[0].open, false);
        assert.equal(list.tasks[1].open, false);
        assert.equal(list.details.open, true);
    });

    it('cierres programáticos usan guard anti-loop (coordinatingTaskToggle)', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('acc-4', ['t1', 't2', 't3'], true);

        list.tasks[0].open = true;
        list.tasks[1].open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.tasks[2].open = true;
        dispatchToggle(list.tasks[2], feed.document);

        var openTasks = list.tasks.filter(function (task) {
            return task.open === true;
        });

        assert.equal(openTasks.length, 1);
        assert.equal(list.tasks[2].open, true);
    });
});

describe('list-options-module MC-UX-D list details toggle', () => {
    it('click Ver más muestra detalles y cambia aria-expanded', () => {
        var dom = buildListWithDetailsDom('uxd-1', true);

        loadModule(dom);
        dispatchClick(dom.detailsToggle, dom.document);

        assert.equal(dom.detailsPanel.classList.contains('is-visible'), true);
        assert.equal(dom.detailsToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(dom.detailsToggle.textContent, 'Ver menos');
    });

    it('click Ver menos oculta detalles y restaura aria-expanded', () => {
        var dom = buildListWithDetailsDom('uxd-2', true);

        loadModule(dom);
        dispatchClick(dom.detailsToggle, dom.document);
        dispatchClick(dom.detailsToggle, dom.document);

        assert.equal(dom.detailsPanel.classList.contains('is-visible'), false);
        assert.equal(dom.detailsToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.detailsToggle.textContent, 'Ver más');
    });

    it('click en toggle no cambia details.open de la lista', () => {
        var dom = buildListWithDetailsDom('uxd-3', true);

        loadModule(dom);
        dispatchClick(dom.detailsToggle, dom.document);

        assert.equal(dom.details.open, true);
    });

    it('colapsar lista resetea detalles y texto Ver más', () => {
        var dom = buildListWithDetailsDom('uxd-4', true);

        loadModule(dom);
        dispatchClick(dom.detailsToggle, dom.document);
        dom.details.open = false;
        dispatchToggle(dom.details, dom.document);

        assert.equal(dom.detailsPanel.classList.contains('is-visible'), false);
        assert.equal(dom.detailsToggle.getAttribute('aria-expanded'), 'false');
        assert.equal(dom.detailsToggle.textContent, 'Ver más');
    });
});

describe('list-options-module MC-UX-E following tasks block', () => {
    it('colapsar Siguientes tareas cierra tareas internas abiertas', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('uxe-1', 'top', ['f1', 'f2'], true);

        list.followingBlock.open = true;
        list.followingTasks[0].open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.followingBlock.open = false;
        dispatchToggle(list.followingBlock, feed.document);

        assert.equal(list.followingTasks[0].open, false);
        assert.equal(list.topTask.open, false);
    });

    it('expandir Siguientes tareas no abre tareas automáticamente', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('uxe-2', 'top', ['f1', 'f2'], true);

        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.followingBlock.open = true;
        dispatchToggle(list.followingBlock, feed.document);

        assert.equal(list.followingTasks[0].open, false);
        assert.equal(list.followingTasks[1].open, false);
    });

    it('abrir lista resetea Siguientes tareas a cerrado', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('uxe-3', 'top', ['f1'], false);

        list.followingBlock.open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.details.open = true;
        dispatchToggle(list.details, feed.document);

        assert.equal(list.followingBlock.open, false);
        assert.equal(list.topTask.open, true);
    });

    it('cerrar lista resetea Siguientes tareas y cierra tareas internas', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('uxe-4', 'top', ['f1'], true);

        list.followingBlock.open = true;
        list.followingTasks[0].open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.details.open = false;
        dispatchToggle(list.details, feed.document);

        assert.equal(list.followingBlock.open, false);
        assert.equal(list.followingTasks[0].open, false);
        assert.equal(list.topTask.open, false);
    });

    it('toggle de Siguientes tareas no cambia open de la lista principal', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('uxe-5', 'top', ['f1'], true);

        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.followingBlock.open = true;
        dispatchToggle(list.followingBlock, feed.document);

        assert.equal(list.details.open, true);
    });

    it('acordeón de tareas sigue funcionando con tareas dentro de Siguientes tareas', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('uxe-6', 'top', ['f1', 'f2'], true);

        list.topTask.open = true;
        list.followingBlock.open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.followingTasks[1].open = true;
        dispatchToggle(list.followingTasks[1], feed.document);

        assert.equal(list.topTask.open, false);
        assert.equal(list.followingTasks[0].open, false);
        assert.equal(list.followingTasks[1].open, true);
        assert.equal(list.details.open, true);
    });

    it('colapsar Siguientes tareas cierra tareas secundarias abiertas dentro del bloque', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('uxe-7', 'top', ['f1'], true);
        var secondaryTask = buildTaskItem('s1', true);
        var secondaryWrap = makeElement('div');

        secondaryWrap.appendChild(secondaryTask);
        list.followingBlock.appendChild(secondaryWrap);
        list.followingBlock.open = true;
        feed.moduleRoot.appendChild(list.details);
        loadModule(feed);

        list.followingBlock.open = false;
        dispatchToggle(list.followingBlock, feed.document);

        assert.equal(list.followingTasks[0].open, false);
        assert.equal(secondaryTask.open, false);
    });
});

describe('list-options-module restore open list card', () => {
    it('getOpenListCardId devuelve el ID de la lista abierta', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('keep-1', ['t1'], true);

        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);

        assert.equal(api.getOpenListCardId(feed.moduleRoot), 'keep-1');
    });

    it('getOpenListCardId devuelve vacío sin lista abierta', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('keep-2', ['t1'], false);

        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);

        assert.equal(api.getOpenListCardId(feed.moduleRoot), '');
    });

    it('reopenListCardById abre lista existente y primera tarea tras toggle', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('keep-3', ['t1', 't2'], false);

        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);

        api.reopenListCardById('keep-3', feed.moduleRoot);
        dispatchToggle(list.details, feed.document);

        assert.equal(list.details.open, true);
        assert.equal(list.tasks[0].open, true);
        assert.equal(list.tasks[1].open, false);
    });

    it('reopenListCardById no-op si la lista desapareció', () => {
        var feed = buildFeedDom();
        var api = loadModule(feed);

        assert.doesNotThrow(function () {
            api.reopenListCardById('missing', feed.moduleRoot);
        });
    });

    it('reopenListCardById no-op sin listId ni root', () => {
        var feed = buildFeedDom();
        var api = loadModule(feed);

        assert.doesNotThrow(function () {
            api.reopenListCardById('', null);
        });
    });
});

describe('list-options-module restore following tasks block', () => {
    it('getListRestoreSnapshot devuelve followingTasks abierto', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('snap-1', 'top', ['f1'], true);

        list.followingBlock.open = true;
        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);
        var snapshot = api.getListRestoreSnapshot(feed.moduleRoot);

        assert.equal(snapshot.restoreOpenListId, 'snap-1');
        assert.equal(snapshot.restoreFollowingTasksOpen, true);
    });

    it('getListRestoreSnapshot devuelve followingTasks cerrado', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('snap-2', 'top', ['f1'], true);

        list.followingBlock.open = false;
        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);
        var snapshot = api.getListRestoreSnapshot(feed.moduleRoot);

        assert.equal(snapshot.restoreOpenListId, 'snap-2');
        assert.equal(snapshot.restoreFollowingTasksOpen, false);
    });

    it('getListRestoreSnapshot devuelve null sin lista abierta', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('snap-3', 'top', ['f1'], false);

        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);

        assert.equal(api.getListRestoreSnapshot(feed.moduleRoot), null);
    });

    it('reopenFollowingTasksInList no-op sin listId ni root', () => {
        var feed = buildFeedDom();
        var api = loadModule(feed);

        assert.doesNotThrow(function () {
            api.reopenFollowingTasksInList('', null);
        });
    });
});

describe('list-options-module restoreListAfterReload', () => {
    it('mantiene following abierto tras toggle tardío de lista', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('fix-1', 'top', ['f1', 'f2'], false);

        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);

        api.restoreListAfterReload('fix-1', feed.moduleRoot, { followingTasksOpen: true });
        dispatchToggle(list.details, feed.document);

        assert.equal(list.details.open, true);
        assert.equal(list.followingBlock.open, true);
    });

    it('con followingTasksOpen false deja following cerrado tras toggle tardío', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('fix-2', 'top', ['f1'], false);

        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);

        api.restoreListAfterReload('fix-2', feed.moduleRoot, { followingTasksOpen: false });
        dispatchToggle(list.details, feed.document);

        assert.equal(list.details.open, true);
        assert.equal(list.followingBlock.open, false);
    });

    it('no falla si la lista no tiene bloque following', () => {
        var feed = buildFeedDom();
        var list = buildListWithTasks('fix-3', ['t1'], false);

        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);

        assert.doesNotThrow(function () {
            api.restoreListAfterReload('fix-3', feed.moduleRoot, { followingTasksOpen: true });
        });
    });

    it('cierra otra lista abierta y abre la primera tarea', () => {
        var feed = buildFeedDom();
        var listA = buildListWithFollowingTasks('fix-a', 'top', ['a1'], true);
        var listB = buildListWithFollowingTasks('fix-b', 'top', ['b1', 'b2'], false);

        feed.moduleRoot.appendChild(listA.details);
        feed.moduleRoot.appendChild(listB.details);
        var api = loadModule(feed);

        api.restoreListAfterReload('fix-b', feed.moduleRoot, { followingTasksOpen: false });

        assert.equal(listA.details.open, false);
        assert.equal(listB.details.open, true);
        assert.equal(listB.topTask.open, true);
    });

    it('con lista ya abierta restaura following si followingTasksOpen es true', () => {
        var feed = buildFeedDom();
        var list = buildListWithFollowingTasks('fix-open', 'top', ['f1'], true);

        list.followingBlock.open = false;
        feed.moduleRoot.appendChild(list.details);
        var api = loadModule(feed);

        api.restoreListAfterReload('fix-open', feed.moduleRoot, { followingTasksOpen: true });

        assert.equal(list.details.open, true);
        assert.equal(list.followingBlock.open, true);
    });

    it('no-op si la lista no existe', () => {
        var feed = buildFeedDom();
        var api = loadModule(feed);

        assert.doesNotThrow(function () {
            api.restoreListAfterReload('missing', feed.moduleRoot, { followingTasksOpen: true });
        });
    });
});

describe('listOptionsModule add-task click delegation', () => {
    function buildAddTaskDom(listId) {
        var addBtn = makeElement('button', {
            attributes: {
                'data-aa-list-add-task': '1',
                'data-list-id': listId
            }
        });

        var details = makeElement('details', {
            attributes: {
                'data-list-id': listId
            },
            children: [addBtn]
        });
        details.classList.classes.push('aa-executable-list-card');
        details.open = false;

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
            addBtn: addBtn
        };
    }

    it('click en + tarea llama preventDefault y stopPropagation', () => {
        var dom = buildAddTaskDom('42');
        var bag = {};
        loadModule(dom, bag);

        var prevented = false;
        var stopped = false;
        var event = {
            target: dom.addBtn,
            preventDefault: function () { prevented = true; },
            stopPropagation: function () { stopped = true; }
        };

        (dom.document.listeners['click:capture'] || []).forEach(function (handler) {
            handler(event);
        });

        assert.equal(prevented, true);
        assert.equal(stopped, true);
    });

    it('click en + tarea invoca AATasksBoard.openNewTaskForList con listId', () => {
        var dom = buildAddTaskDom('77');
        var bag = {};
        loadModule(dom, bag);

        var calledWith = null;
        bag.context.window.AATasksBoard = {
            openNewTaskForList: function (id) {
                calledWith = id;
            }
        };

        dispatchClick(dom.addBtn, dom.document);

        assert.equal(calledWith, '77');
    });

    it('click en + tarea no alterna el details', () => {
        var dom = buildAddTaskDom('88');
        var bag = {};
        loadModule(dom, bag);
        dom.details.open = false;

        bag.context.window.AATasksBoard = {
            openNewTaskForList: function () {}
        };

        dispatchClick(dom.addBtn, dom.document);

        assert.equal(dom.details.open, false);
    });

    it('sin AATasksBoard no lanza error', () => {
        var dom = buildAddTaskDom('99');
        var bag = {};
        loadModule(dom, bag);
        bag.context.window.AATasksBoard = undefined;

        assert.doesNotThrow(function () {
            dispatchClick(dom.addBtn, dom.document);
        });
    });

    it('click en + tarea cierra el menú de opciones abierto', () => {
        var dom = buildListCardDom('mc-close');
        var bag = {};
        loadModule(dom, bag);

        bag.context.window.AATasksBoard = {
            openNewTaskForList: function () {}
        };

        dispatchClick(dom.trigger, dom.document);
        assert.equal(dom.menu.classList.contains('hidden'), false, 'menú abierto tras click en trigger');

        var addBtn = makeElement('button', {
            attributes: {
                'data-aa-list-add-task': '1',
                'data-list-id': 'mc-close'
            }
        });
        dom.menu.appendChild(addBtn);
        addBtn.parent = dom.menu;

        dispatchClick(addBtn, dom.document);

        assert.equal(dom.menu.classList.contains('hidden'), true, 'menú cerrado tras click en + tarea');
    });
});
