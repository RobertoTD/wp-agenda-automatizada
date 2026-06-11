'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/list-edit-module.js');
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

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
    var el = {
        tagName: tag,
        id: opts.id || '',
        parent: opts.parent || null,
        disabled: !!opts.disabled,
        dataset: opts.dataset || {},
        value: opts.value || '',
        textContent: opts.textContent || '',
        attributes: Object.assign({}, opts.attributes || {}),
        children: [],
        classList: makeClassList(!!opts.hidden),
        listeners: {
            capture: {},
            bubble: {}
        },
        appendChild: function (child) {
            child.parent = this;
            this.children.push(child);
        },
        setAttribute: function (name, value) {
            this.attributes[name] = String(value);
        },
        getAttribute: function (name) {
            if (Object.prototype.hasOwnProperty.call(this.attributes, name)) {
                return this.attributes[name];
            }

            return null;
        },
        addEventListener: function (type, handler, useCapture) {
            var bucket = useCapture ? this.listeners.capture : this.listeners.bubble;

            bucket[type] = bucket[type] || [];
            bucket[type].push(handler);
        },
        matches: function (selector) {
            if (selector === '[data-aa-list-edit]') {
                return this.getAttribute('data-aa-list-edit') === '1';
            }

            return false;
        },
        closest: function (selector) {
            var node = this;

            while (node) {
                if (node.matches && node.matches(selector)) {
                    return node;
                }

                node = node.parent;
            }

            return null;
        },
        querySelectorAll: function () {
            return [];
        },
        reset: function () {
            this.value = '';
        }
    };

    (opts.children || []).forEach(function (child) {
        el.appendChild(child);
    });

    return el;
}

function dispatchClick(target) {
    var stopped = false;
    var event = {
        target: target,
        defaultPrevented: false,
        preventDefault: function () {
            event.defaultPrevented = true;
        },
        stopPropagation: function () {
            stopped = true;
        }
    };

    event.target.closest = function (selector) {
        var node = event.target;

        while (node) {
            if (node.matches && node.matches(selector)) {
                return node;
            }

            node = node.parent;
        }

        return null;
    };

    var root = target;

    while (root.parent) {
        root = root.parent;
    }

    var captureHandlers = root.listeners.capture.click || [];

    captureHandlers.forEach(function (handler) {
        if (!stopped) {
            handler(event);
        }
    });

    if (!stopped && target.onclick) {
        target.onclick(event);
    }
}

function flushPromises() {
    return new Promise(function (resolve) {
        setImmediate(resolve);
    });
}

function dispatchSubmit(form) {
    var event = {
        target: form,
        defaultPrevented: false,
        preventDefault: function () {
            event.defaultPrevented = true;
        }
    };

    var handlers = form.listeners.bubble.submit || [];

    handlers.forEach(function (handler) {
        handler(event);
    });
}

function loadListEditModule(dom, serviceOverrides) {
    var updateCalls = [];
    var reloadCalls = { board: 0, feed: 0 };

    var context = {
        window: {},
        console: console,
        document: dom.document,
        setTimeout: setTimeout,
        clearTimeout: clearTimeout,
        Promise: Promise
    };

    context.window = context;
    context.globalThis = context;
    context.window.TasksService = Object.assign({
        updateTaskList: function (payload) {
            updateCalls.push(payload);

            if (serviceOverrides && serviceOverrides.reject) {
                return Promise.reject(serviceOverrides.reject);
            }

            return Promise.resolve({ list: { id: payload.list_id } });
        }
    }, serviceOverrides && serviceOverrides.TasksService ? serviceOverrides.TasksService : {});
    context.window.AATasksBoard = {
        reload: function () {
            reloadCalls.board += 1;
            return Promise.resolve();
        }
    };
    context.window.AAExecutableUserListsVisibleFeed = {
        isEnabled: function () {
            return true;
        },
        reload: function () {
            reloadCalls.feed += 1;
            return Promise.resolve();
        }
    };

    vm.runInNewContext(moduleSrc, context, {
        filename: modulePath
    });

    return {
        context: context,
        updateCalls: updateCalls,
        reloadCalls: reloadCalls
    };
}

function buildEditDom() {
    var modal = makeElement('div', {
        id: 'aa-task-list-edit-modal',
        hidden: true,
        attributes: {
            'aria-hidden': 'true'
        }
    });
    var listIdInput = makeElement('input', { id: 'aa-task-list-edit-form-list-id' });
    var titleInput = makeElement('input', { id: 'aa-task-list-edit-form-title' });
    var descriptionInput = makeElement('textarea', { id: 'aa-task-list-edit-form-description' });
    var importanceInput = makeElement('input', { id: 'aa-task-list-edit-form-importance', value: '0' });
    var formError = makeElement('p', { id: 'aa-task-list-edit-form-error', hidden: true });
    var form = makeElement('form', {
        id: 'aa-task-list-edit-form',
        children: [
            listIdInput,
            titleInput,
            descriptionInput,
            importanceInput,
            formError
        ]
    });

    form.reset = function () {
        listIdInput.value = '';
        titleInput.value = '';
        descriptionInput.value = '';
        importanceInput.value = '0';
    };

    modal.appendChild(form);

    var editButton = makeElement('button', {
        attributes: {
            'data-aa-list-edit': '1',
            'data-list-id': '15',
            'data-list-title': 'Mi lista',
            'data-list-description': 'Objetivo común',
            'data-list-importance': '4'
        },
        onclick: function (event) {
            event.stopPropagation();
        }
    });

    var moduleRoot = makeElement('div', {
        id: 'aa-tasks-module-root',
        children: [editButton]
    });

    var byId = {
        'aa-tasks-module-root': moduleRoot,
        'aa-task-list-edit-modal': modal,
        'aa-task-list-edit-form': form,
        'aa-task-list-edit-form-list-id': listIdInput,
        'aa-task-list-edit-form-title': titleInput,
        'aa-task-list-edit-form-description': descriptionInput,
        'aa-task-list-edit-form-importance': importanceInput,
        'aa-task-list-edit-form-error': formError
    };

    var documentMock = {
        readyState: 'complete',
        addEventListener: function () {},
        getElementById: function (id) {
            return byId[id] || null;
        },
        querySelectorAll: function () {
            return [];
        }
    };

    return {
        document: documentMock,
        moduleRoot: moduleRoot,
        editButton: editButton,
        modal: modal,
        form: form,
        listIdInput: listIdInput,
        titleInput: titleInput,
        descriptionInput: descriptionInput,
        importanceInput: importanceInput,
        formError: formError
    };
}

describe('list-edit-module MC13L-C', () => {
    it('index.php incluye modal Editar lista separado sin delete ni archive', () => {
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /id="aa-task-list-edit-modal"/);
        assert.match(indexSrc, />Editar lista</);
        assert.match(indexSrc, /id="aa-task-list-edit-form"/);
        assert.match(indexSrc, /id="aa-task-list-edit-form-list-id"/);
        assert.match(indexSrc, /id="aa-task-list-edit-form-title"/);
        assert.match(indexSrc, /id="aa-task-list-edit-form-description"/);
        assert.match(indexSrc, /id="aa-task-list-edit-form-importance"/);
        assert.match(indexSrc, /list-edit-module\.js/);
        assert.doesNotMatch(indexSrc, /aa-task-list-edit-form[\s\S]*archive-list/);
        assert.doesNotMatch(indexSrc, /Eliminar lista/);
    });

    it('módulo JS delega clic en data-aa-list-edit en capture y envía updateTaskList', () => {
        assert.match(moduleSrc, /data-aa-list-edit/);
        assert.match(moduleSrc, /openEditModalFromButton/);
        assert.match(moduleSrc, /service\.updateTaskList\(/);
        assert.match(moduleSrc, /closeModal\('aa-task-list-edit-modal'\)/);
        assert.match(moduleSrc, /reloadAfterMutation/);
        assert.match(moduleSrc, /addEventListener\('click', function \(event\) \{[\s\S]*?\}, true\)/);
    });

    it('click en Editar lista abre modal con prefill y no propaga toggle', async () => {
        var dom = buildEditDom();
        var bubbleRan = false;

        dom.moduleRoot.addEventListener('click', function () {
            bubbleRan = true;
        }, false);

        loadListEditModule(dom);
        dispatchClick(dom.editButton);

        assert.equal(bubbleRan, false);
        assert.equal(dom.modal.classList.contains('hidden'), false);
        assert.equal(dom.listIdInput.value, '15');
        assert.equal(dom.titleInput.value, 'Mi lista');
        assert.equal(dom.descriptionInput.value, 'Objetivo común');
        assert.equal(dom.importanceInput.value, '4');
    });

    it('submit llama updateTaskList, cierra modal y recarga feed/tablero', async () => {
        var dom = buildEditDom();
        var api = loadListEditModule(dom);

        dispatchClick(dom.editButton);
        dom.titleInput.value = 'Lista renombrada';
        dom.descriptionInput.value = 'Nuevo objetivo';
        dom.importanceInput.value = '1';

        dispatchSubmit(dom.form);
        await flushPromises();

        assert.equal(api.updateCalls.length, 1);
        assert.equal(api.updateCalls[0].list_id, '15');
        assert.equal(api.updateCalls[0].title, 'Lista renombrada');
        assert.equal(api.updateCalls[0].description, 'Nuevo objetivo');
        assert.equal(api.updateCalls[0].importance, '1');
        assert.equal(dom.modal.classList.contains('hidden'), true);
        assert.equal(api.reloadCalls.board, 1);
        assert.equal(api.reloadCalls.feed, 1);
    });

    it('error controlado del backend se muestra en el modal', async () => {
        var dom = buildEditDom();
        var api = loadListEditModule(dom, {
            reject: new Error('Esta lista no se puede editar.')
        });

        dispatchClick(dom.editButton);
        dispatchSubmit(dom.form);
        await flushPromises();

        assert.equal(api.updateCalls.length, 1);
        assert.equal(dom.modal.classList.contains('hidden'), false);
        assert.equal(dom.formError.classList.contains('hidden'), false);
        assert.equal(dom.formError.textContent, 'Esta lista no se puede editar.');
    });
});
