'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/task-edit-module.js');
const servicePath = path.join(__dirname, '../../assets/js/services/tasksService.js');
const rendererPath = path.join(__dirname, '../../assets/js/ui/executableListRenderer.js');
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
        open: !!opts.open,
        onclick: opts.onclick || null,
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
            if (selector === '[data-aa-task-edit]') {
                return this.getAttribute('data-aa-task-edit') === '1';
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

    var bubbleHandlers = root.listeners.bubble.click || [];

    bubbleHandlers.forEach(function (handler) {
        if (!stopped) {
            handler(event);
        }
    });
}

function loadTaskEditModule(dom) {
    var context = {
        window: {},
        console: console,
        document: dom.document,
        setTimeout: setTimeout,
        clearTimeout: clearTimeout
    };

    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(moduleSrc, context, {
        filename: modulePath
    });

    return context;
}

function buildEditDom() {
    var modal = makeElement('div', {
        id: 'aa-task-edit-modal',
        hidden: true,
        attributes: {
            'aria-hidden': 'true'
        }
    });
    var taskIdInput = makeElement('input', { id: 'aa-task-edit-form-task-id' });
    var titleInput = makeElement('input', { id: 'aa-task-edit-form-title' });
    var notesInput = makeElement('textarea', { id: 'aa-task-edit-form-notes' });
    var dueInput = makeElement('input', { id: 'aa-task-edit-form-due-at' });
    var importanceInput = makeElement('input', { id: 'aa-task-edit-form-importance' });
    var bucketSelect = makeElement('select', { id: 'aa-task-edit-form-default-bucket' });
    var optionsDetails = makeElement('details', { id: 'aa-task-edit-form-options', open: true });
    var formError = makeElement('p', { id: 'aa-task-edit-form-error', hidden: true });
    var form = makeElement('form', {
        id: 'aa-task-edit-form',
        children: [
            taskIdInput,
            titleInput,
            notesInput,
            optionsDetails,
            formError
        ]
    });

    modal.appendChild(form);

    var iconPath = makeElement('path');
    var editButton = makeElement('button', {
        attributes: {
            'data-aa-task-edit': '1',
            'data-task-id': '28',
            'data-task-title': 'terea nueva',
            'data-task-notes': 'notas de prueba',
            'data-task-due-at': '2026-06-20 08:37:00',
            'data-task-importance': '7',
            'data-task-default-bucket': 'primary'
        },
        onclick: function (event) {
            event.stopPropagation();
        }
    });

    editButton.appendChild(makeElement('svg', { children: [iconPath] }));

    var moduleRoot = makeElement('div', {
        id: 'aa-tasks-module-root',
        children: [editButton]
    });

    var byId = {
        'aa-tasks-module-root': moduleRoot,
        'aa-task-edit-modal': modal,
        'aa-task-edit-form': form,
        'aa-task-edit-form-task-id': taskIdInput,
        'aa-task-edit-form-title': titleInput,
        'aa-task-edit-form-notes': notesInput,
        'aa-task-edit-form-due-at': dueInput,
        'aa-task-edit-form-importance': importanceInput,
        'aa-task-edit-form-default-bucket': bucketSelect,
        'aa-task-edit-form-options': optionsDetails,
        'aa-task-edit-form-error': formError
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
        iconPath: iconPath,
        modal: modal,
        titleInput: titleInput
    };
}

describe('task-edit-module MC13C', () => {
    it('modal Editar tarea incluye campos requeridos sin selector de lista ni delete', () => {
        const indexSrc = fs.readFileSync(indexPath, 'utf8');

        assert.match(indexSrc, /id="aa-task-edit-modal"/);
        assert.match(indexSrc, />Editar tarea</);
        assert.match(indexSrc, /id="aa-task-edit-form"/);
        assert.match(indexSrc, /id="aa-task-edit-form-task-id"/);
        assert.match(indexSrc, /id="aa-task-edit-form-title"/);
        assert.match(indexSrc, /id="aa-task-edit-form-notes"/);
        assert.match(indexSrc, /id="aa-task-edit-form-due-at"/);
        assert.match(indexSrc, /id="aa-task-edit-form-importance"/);
        assert.match(indexSrc, /id="aa-task-edit-form-default-bucket"/);
        assert.match(indexSrc, /id="aa-task-edit-form-options"[\s\S]*?Opciones/);
        assert.doesNotMatch(indexSrc, /aa-task-edit-form-list-id/);
        assert.doesNotMatch(indexSrc, /Eliminar tarea/);
    });

    it('módulo JS delega clic en data-aa-task-edit en capture y envía updateTask', () => {
        assert.match(moduleSrc, /data-aa-task-edit/);
        assert.match(moduleSrc, /openEditModalFromButton/);
        assert.match(moduleSrc, /service\.updateTask\(/);
        assert.match(moduleSrc, /default_bucket/);
        assert.match(moduleSrc, /showFormError/);
        assert.match(moduleSrc, /closeModal\('aa-task-edit-modal'\)/);
        assert.match(moduleSrc, /reloadAfterMutation/);
        assert.match(moduleSrc, /addEventListener\('click', function \(event\) \{[\s\S]*?\}, true\)/);
    });

    it('click real en lápiz con stopPropagation inline abre el modal', () => {
        var dom = buildEditDom();

        loadTaskEditModule(dom);

        dispatchClick(dom.iconPath);

        assert.equal(dom.modal.classList.contains('hidden'), false);
        assert.equal(dom.modal.attributes['aria-hidden'], 'false');
        assert.equal(dom.titleInput.value, 'terea nueva');
    });

    it('click en botón editar abre el modal aunque bubble quede bloqueado', () => {
        var dom = buildEditDom();
        var bubbleRan = false;

        dom.moduleRoot.addEventListener('click', function () {
            bubbleRan = true;
        }, false);

        loadTaskEditModule(dom);
        dispatchClick(dom.editButton);

        assert.equal(bubbleRan, false);
        assert.equal(dom.modal.classList.contains('hidden'), false);
    });

    it('TasksService.updateTask propaga default_bucket primary y secondary', () => {
        const serviceSrc = fs.readFileSync(servicePath, 'utf8');

        assert.match(serviceSrc, /function updateTask\(payload\)/);
        assert.match(serviceSrc, /postAction\('aa_update_task'/);
        assert.match(serviceSrc, /payload\.default_bucket === 'primary'/);
        assert.match(serviceSrc, /payload\.default_bucket === 'secondary'/);
    });

    it('renderer coloca botón editar en summary con stopPropagation', () => {
        const rendererSrc = fs.readFileSync(rendererPath, 'utf8');

        assert.match(rendererSrc, /function renderItemEditButton/);
        assert.match(rendererSrc, /capabilities\.can_edit/);
        assert.match(rendererSrc, /aria-label="Editar tarea"/);
        assert.match(rendererSrc, /onclick="event\.stopPropagation\(\)"/);
        assert.match(rendererSrc, /aa-executable-item-summary-actions/);
        assert.match(rendererSrc, /aa-executable-item-summary-edit/);
    });
});
