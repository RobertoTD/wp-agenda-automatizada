'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/learning/restore-archived-tasks-module.js');
const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
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

    return {
        tagName: tag,
        classList: opts.classList || makeClassList(!!opts.hidden),
        attributes: Object.assign({}, opts.attributes || {}),
        disabled: !!opts.disabled,
        parent: opts.parent || null,
        children: opts.children || [],
        value: opts.value !== undefined ? opts.value : '',
        textContent: opts.textContent || '',
        innerHTML: opts.innerHTML || '',
        dataset: Object.assign({}, opts.dataset || {}),
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
        querySelectorAll: function (selector) {
            var matches = [];

            this.walk(function (node) {
                if (selector === '[data-aa-tasks-modal-close="aa-restore-archived-tasks-modal"]'
                    && node.getAttribute
                    && node.getAttribute('data-aa-tasks-modal-close') === 'aa-restore-archived-tasks-modal') {
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
        closest: function (selector) {
            var node = this;

            while (node) {
                if (selector === '[data-aa-list-restore-archived-tasks]'
                    && node.getAttribute
                    && node.getAttribute('data-aa-list-restore-archived-tasks') === '1') {
                    return node;
                }

                node = node.parent;
            }

            return null;
        }
    };
}

function buildRestoreModalDom() {
    var serviceCalls = [];
    var reloadCalls = { board: 0, feed: 0 };
    var tasksResponse = [];
    var restoreShouldFail = false;
    var listShouldFail = false;

    var listMenu = makeElement('div', {
        hidden: false,
        attributes: { 'data-list-id': '7', role: 'menu' }
    });
    listMenu.classList.classes.push('aa-executable-list-options-menu');

    var listTrigger = makeElement('button', {
        attributes: {
            'data-aa-list-options-trigger': '1',
            'data-list-id': '7',
            'aria-expanded': 'true'
        }
    });

    var restoreButton = makeElement('button', {
        attributes: {
            'data-aa-list-restore-archived-tasks': '1',
            'data-list-id': '7'
        },
        textContent: 'Desarchivar tareas'
    });

    var listIdInput = makeElement('input', {
        attributes: { id: 'aa-restore-archived-tasks-form-list-id', type: 'hidden' }
    });

    var loading = makeElement('p', {
        hidden: true,
        attributes: { id: 'aa-restore-archived-tasks-loading' }
    });

    var empty = makeElement('p', {
        hidden: true,
        attributes: { id: 'aa-restore-archived-tasks-empty' }
    });

    var select = makeElement('select', {
        hidden: false,
        attributes: { id: 'aa-restore-archived-tasks-select' },
        disabled: true,
        innerHTML: '<option value="">Selecciona una tarea</option>'
    });
    select.appendChild = function (child) {
        child.parent = this;
        this.children.push(child);
    };
    Object.defineProperty(select, 'innerHTML', {
        configurable: true,
        enumerable: true,
        get: function () {
            return this._innerHTML || '';
        },
        set: function (value) {
            this._innerHTML = String(value);
            this.children = [];
            var placeholder = makeElement('option', { attributes: { value: '' } });
            placeholder.textContent = 'Selecciona una tarea';
            this.appendChild(placeholder);
        }
    });

    var selectWrap = makeElement('div', {
        attributes: { id: 'aa-restore-archived-tasks-select-wrap' },
        children: [select]
    });

    var error = makeElement('p', {
        hidden: true,
        attributes: { id: 'aa-restore-archived-tasks-error' }
    });

    var submit = makeElement('button', {
        attributes: { id: 'aa-restore-archived-tasks-submit', type: 'button' },
        disabled: true
    });

    var cancel = makeElement('button', {
        attributes: { 'data-aa-tasks-modal-close': 'aa-restore-archived-tasks-modal', type: 'button' }
    });

    var overlay = makeElement('div', {
        attributes: { 'data-aa-tasks-modal-close': 'aa-restore-archived-tasks-modal' }
    });

    var modalBody = makeElement('div', {
        children: [listIdInput, loading, empty, selectWrap, error, submit, cancel]
    });

    var modal = makeElement('div', {
        hidden: true,
        attributes: { id: 'aa-restore-archived-tasks-modal', 'aria-hidden': 'true' },
        children: [overlay, modalBody]
    });

    var moduleRoot = makeElement('div', {
        attributes: { id: 'aa-tasks-module-root' },
        children: [listMenu, listTrigger, restoreButton, modal]
    });

    var byId = {
        'aa-tasks-module-root': moduleRoot,
        'aa-restore-archived-tasks-modal': modal,
        'aa-restore-archived-tasks-form-list-id': listIdInput,
        'aa-restore-archived-tasks-loading': loading,
        'aa-restore-archived-tasks-empty': empty,
        'aa-restore-archived-tasks-select': select,
        'aa-restore-archived-tasks-select-wrap': selectWrap,
        'aa-restore-archived-tasks-error': error,
        'aa-restore-archived-tasks-submit': submit
    };

    var documentMock = {
        readyState: 'complete',
        addEventListener: function () {},
        createElement: function (tag) {
            return makeElement(String(tag).toLowerCase(), {});
        },
        getElementById: function (id) {
            return byId[id] || null;
        },
        querySelectorAll: function (selector) {
            return moduleRoot.querySelectorAll(selector);
        }
    };

    var dom = {
        document: documentMock,
        moduleRoot: moduleRoot,
        restoreButton: restoreButton,
        modal: modal,
        listMenu: listMenu,
        listTrigger: listTrigger,
        listIdInput: listIdInput,
        loading: loading,
        empty: empty,
        select: select,
        selectWrap: selectWrap,
        error: error,
        submit: submit,
        cancel: cancel,
        get serviceCalls() {
            return serviceCalls;
        },
        get reloadCalls() {
            return reloadCalls;
        },
        get tasksResponse() {
            return tasksResponse;
        },
        set tasksResponse(value) {
            tasksResponse = value;
        },
        get restoreShouldFail() {
            return restoreShouldFail;
        },
        set restoreShouldFail(value) {
            restoreShouldFail = value;
        },
        get listShouldFail() {
            return listShouldFail;
        },
        set listShouldFail(value) {
            listShouldFail = value;
        }
    };

    return dom;
}

function dispatchClick(target, root) {
    var event = {
        target: target,
        preventDefault: function () {},
        stopPropagation: function () {}
    };

    (root.listeners['click:capture'] || []).forEach(function (handler) {
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
    context.window.TasksService = {
        listArchivedTasksInList: function (listId) {
            dom.serviceCalls.push({ method: 'listArchivedTasksInList', listId: listId });

            if (dom.listShouldFail) {
                return Promise.reject(new Error('No se pudieron cargar las tareas archivadas.'));
            }

            return Promise.resolve({ tasks: dom.tasksResponse || [] });
        },
        restoreTask: function (taskId) {
            dom.serviceCalls.push({ method: 'restoreTask', taskId: taskId });

            if (dom.restoreShouldFail) {
                return Promise.reject(new Error('No se pudo desarchivar la tarea.'));
            }

            return Promise.resolve({ task: { id: taskId } });
        }
    };
    context.window.AATasksBoard = {
        reload: function () {
            dom.reloadCalls.board += 1;
            return Promise.resolve();
        }
    };
    context.window.AAExecutableUserListsVisibleFeed = {
        isEnabled: function () {
            return true;
        },
        reload: function () {
            dom.reloadCalls.feed += 1;
            return Promise.resolve();
        }
    };

    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    return context.module.exports;
}

describe('restore-archived-tasks-module MC4b', () => {
    it('index.php encola restore-archived-tasks-module.js después de list-options', () => {
        const indexSrc = fs.readFileSync(indexPath, 'utf8');
        var listOptionsPos = indexSrc.indexOf('esc_url($list_options_js');
        var restorePos = indexSrc.indexOf('esc_url($restore_archived_tasks_js');

        assert.notEqual(restorePos, -1);
        assert.ok(listOptionsPos < restorePos);
        assert.match(indexSrc, /id="aa-restore-archived-tasks-modal"/);
        assert.match(indexSrc, />Desarchivar tareas</);
        assert.match(indexSrc, /No hay tareas archivadas en esta lista\./);
        assert.doesNotMatch(indexSrc, /Desarchivar todas/);
    });

    it('click en Desarchivar tareas abre modal y llama listArchivedTasksInList', async () => {
        var dom = buildRestoreModalDom();
        dom.tasksResponse = [
            { id: 10, title: 'Tarea A', archived_at: '2026-06-11 10:00:00', status: 'pending' }
        ];

        var api = loadModule(dom);
        await api.openRestoreModalFromButton(dom.restoreButton);

        assert.equal(dom.modal.classList.contains('hidden'), false);
        assert.equal(dom.modal.attributes['aria-hidden'], 'false');
        assert.equal(dom.listIdInput.value, '7');
        assert.equal(dom.serviceCalls.length, 1);
        assert.deepEqual(dom.serviceCalls[0], {
            method: 'listArchivedTasksInList',
            listId: '7'
        });
        assert.equal(dom.loading.classList.contains('hidden'), true);
        assert.equal(dom.select.disabled, false);
        assert.equal(dom.select.children.length, 2);
    });

    it('muestra loading mientras carga', () => {
        var dom = buildRestoreModalDom();
        var api = loadModule(dom);

        dom.tasksResponse = [];
        api.loadArchivedTasksIntoModal('7');

        assert.equal(dom.loading.classList.contains('hidden'), false);
    });

    it('empty state si no hay tareas archivadas', async () => {
        var dom = buildRestoreModalDom();
        dom.tasksResponse = [];

        var api = loadModule(dom);
        await api.openRestoreModalFromButton(dom.restoreButton);

        assert.equal(dom.empty.classList.contains('hidden'), false);
        assert.equal(dom.selectWrap.classList.contains('hidden'), true);
        assert.equal(dom.submit.disabled, true);
    });

    it('submit deshabilitado sin selección y habilitado con tarea elegida', async () => {
        var dom = buildRestoreModalDom();
        dom.tasksResponse = [
            { id: 11, title: 'Tarea B', archived_at: '2026-06-12 12:00:00', status: 'pending' }
        ];

        var api = loadModule(dom);
        await api.openRestoreModalFromButton(dom.restoreButton);

        assert.equal(dom.submit.disabled, true);

        dom.select.value = '11';
        dom.select.listeners['change:bubble'].forEach(function (handler) {
            handler();
        });

        assert.equal(dom.submit.disabled, false);
    });

    it('submit llama restoreTask, cierra modal y recarga feed', async () => {
        var dom = buildRestoreModalDom();
        dom.tasksResponse = [
            { id: 12, title: 'Tarea C', archived_at: '2026-06-13 08:00:00', status: 'done' }
        ];

        var api = loadModule(dom);
        await api.openRestoreModalFromButton(dom.restoreButton);

        dom.select.value = '12';
        dom.select.listeners['change:bubble'].forEach(function (handler) {
            handler();
        });

        await api.handleRestoreSubmit();

        assert.deepEqual(dom.serviceCalls[1], { method: 'restoreTask', taskId: '12' });
        assert.equal(dom.modal.classList.contains('hidden'), true);
        assert.equal(dom.reloadCalls.board, 1);
        assert.equal(dom.reloadCalls.feed, 1);
    });

    it('muestra error si falla listado', async () => {
        var dom = buildRestoreModalDom();
        dom.listShouldFail = true;

        var api = loadModule(dom);
        await api.loadArchivedTasksIntoModal('7');

        assert.equal(dom.error.classList.contains('hidden'), false);
        assert.match(dom.error.textContent, /No se pudieron cargar las tareas archivadas/);
    });

    it('muestra error si falla restore', async () => {
        var dom = buildRestoreModalDom();
        dom.tasksResponse = [
            { id: 13, title: 'Tarea D', archived_at: '2026-06-14 09:00:00', status: 'pending' }
        ];
        dom.restoreShouldFail = true;

        var api = loadModule(dom);
        await api.openRestoreModalFromButton(dom.restoreButton);

        dom.select.value = '13';
        await api.handleRestoreSubmit();

        assert.equal(dom.error.classList.contains('hidden'), false);
        assert.match(dom.error.textContent, /No se pudo desarchivar la tarea/);
        assert.equal(dom.modal.classList.contains('hidden'), false);
    });

    it('cancelar resetea modal', async () => {
        var dom = buildRestoreModalDom();
        dom.tasksResponse = [
            { id: 14, title: 'Tarea E', archived_at: '2026-06-15 11:00:00', status: 'pending' }
        ];

        var api = loadModule(dom);
        await api.openRestoreModalFromButton(dom.restoreButton);

        api.closeModal('aa-restore-archived-tasks-modal');

        assert.equal(dom.modal.classList.contains('hidden'), true);
        assert.equal(dom.listIdInput.value, '');
        assert.equal(dom.select.disabled, true);
    });

    it('click en item cierra menú de lista y no expande summary', () => {
        var dom = buildRestoreModalDom();
        dom.tasksResponse = [];
        var summaryToggle = 0;

        var summary = makeElement('summary', { children: [dom.restoreButton] });
        summary.addEventListener('click', function () {
            summaryToggle += 1;
        }, false);
        dom.moduleRoot.appendChild(summary);

        loadModule(dom);
        dispatchClick(dom.restoreButton, dom.moduleRoot);

        assert.equal(summaryToggle, 0);
        assert.equal(dom.listMenu.classList.contains('hidden'), true);
        assert.equal(dom.listTrigger.getAttribute('aria-expanded'), 'false');
    });

    it('formatTaskOptionLabel incluye completada y archived_at', () => {
        var dom = buildRestoreModalDom();
        var api = loadModule(dom);

        assert.equal(
            api.formatTaskOptionLabel({ title: 'Mi tarea', status: 'done', archived_at: '2026-06-10 10:00:00' }),
            'Mi tarea — completada — 2026-06-10 10:00:00'
        );
        assert.equal(
            api.formatTaskOptionLabel({ title: '', archived_at: '2026-06-10 10:00:00' }),
            'Tarea sin título — 2026-06-10 10:00:00'
        );
    });
});
