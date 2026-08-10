'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const toolsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/learning/lists-area-tools.js'
);
const toolsSrc = fs.readFileSync(toolsPath, 'utf8');
const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php');
const indexSrc = fs.readFileSync(indexPath, 'utf8');
const headerPath = path.join(__dirname, '../../includes/admin/ui/shared/header.php');
const headerSrc = fs.readFileSync(headerPath, 'utf8');

function makeEl(id, options) {
    var opts = options || {};

    return {
        id: id,
        classList: {
            classes: opts.hidden ? ['hidden'] : [],
            add: function (cls) {
                if (this.classes.indexOf(cls) === -1) {
                    this.classes.push(cls);
                }
            },
            remove: function (cls) {
                this.classes = this.classes.filter(function (item) {
                    return item !== cls;
                });
            },
            toggle: function () {}
        },
        textContent: opts.textContent || '',
        innerHTML: opts.innerHTML || '',
        value: opts.value || '',
        disabled: !!opts.disabled,
        dataset: opts.dataset || {},
        setAttribute: function () {},
        getAttribute: function () {
            return null;
        },
        appendChild: function (child) {
            this.children = this.children || [];
            this.children.push(child);
        },
        addEventListener: function (type, handler) {
            this.listeners = this.listeners || {};
            this.listeners[type] = this.listeners[type] || [];
            this.listeners[type].push(handler);
        },
        querySelectorAll: function () {
            return [];
        },
        children: opts.children || []
    };
}

function createDocumentMock(dom) {
    var doc = dom.document || dom;

    if (!doc.createElement) {
        doc.createElement = function (tag) {
            return {
                tagName: tag,
                value: '',
                textContent: '',
                appendChild: function () {}
            };
        };
    }

    return doc;
}

function loadListsAreaTools(dom, services) {
    var documentMock = createDocumentMock(dom);
    var context = {
        document: documentMock,
        module: { exports: {} }
    };

    context.window = context;
    context.globalThis = context;

    if (services) {
        Object.keys(services).forEach(function (key) {
            context[key] = services[key];
        });
    }

    vm.runInNewContext(toolsSrc, context, { filename: toolsPath });

    return context.AAListsAreaTools;
}

function makeButtonEl(options) {
    var opts = options || {};
    var el = makeEl(opts.id || '', opts);

    el.tagName = 'BUTTON';
    el.disabled = !!opts.disabled;
    el.closest = function (selector) {
        if (selector === '[data-lists-tool="options-menu"]' && opts.isOptionsTrigger) {
            return el;
        }

        if (selector === '[data-lists-tool="restore-archived"]' && opts.isRestoreItem) {
            return el;
        }

        if (selector === '[data-lists-tool="return-ignored-tasks"]' && opts.isReturnIgnoredItem) {
            return el;
        }

        if (selector === '[data-lists-tool="create-list"]' && opts.isCreateListItem) {
            return el;
        }

        if (selector === '#aa-lists-options-menu' && opts.insideMenu) {
            return { id: 'aa-lists-options-menu' };
        }

        if (selector === '#aa-lists-area-tools' && opts.insideTools) {
            return { id: 'aa-lists-area-tools' };
        }

        return null;
    };

    return el;
}

describe('lists-area-tools MC13I', () => {
    it('shell header incluye menú de opciones; index conserva modal de desarchivar', () => {
        assert.match(headerSrc, /id="aa-lists-area-tools"/);
        assert.match(headerSrc, /id="aa-lists-options-trigger"/);
        assert.match(headerSrc, /title="Opciones de listas"/);
        assert.match(headerSrc, /id="aa-lists-options-menu"/);
        assert.match(headerSrc, /data-lists-tool="create-list"/);
        assert.match(headerSrc, /data-lists-tool="restore-archived"/);
        assert.match(headerSrc, /data-lists-tool="return-ignored-tasks"/);
        assert.match(headerSrc, />Nueva lista</);
        assert.match(headerSrc, />Desarchivar listas</);
        assert.match(headerSrc, />Regresar tareas ignoradas</);
        assert.match(headerSrc, /\$active_module === 'learning'/);
        assert.doesNotMatch(indexSrc, /id="aa-lists-area-tools"/);
        assert.doesNotMatch(indexSrc, /id="aa-lists-header"/);
        assert.match(indexSrc, /id="aa-restore-archived-lists-modal"/);
        assert.match(indexSrc, />Desarchivar listas</);
        assert.match(indexSrc, /lists-area-tools\.js/);
    });

    it('click en herramienta abre modal y carga archivadas', async () => {
        var modal = makeEl('aa-restore-archived-lists-modal', { hidden: true });
        var select = makeEl('aa-restore-archived-lists-select', { disabled: true });
        var submit = makeEl('aa-restore-archived-lists-submit', { disabled: true });
        var loading = makeEl('aa-restore-archived-lists-loading', { hidden: true });
        var empty = makeEl('aa-restore-archived-lists-empty', { hidden: true });
        var error = makeEl('aa-restore-archived-lists-error', { hidden: true });
        var selectWrap = makeEl('aa-restore-archived-lists-select-wrap');
        var areaTools = makeEl('aa-lists-area-tools');
        var moduleRoot = makeEl('aa-tasks-module-root');
        var getArchivedCalls = 0;

        var dom = {
            readyState: 'complete',
            getElementById: function (id) {
                var map = {
                    'aa-restore-archived-lists-modal': modal,
                    'aa-restore-archived-lists-select': select,
                    'aa-restore-archived-lists-submit': submit,
                    'aa-restore-archived-lists-loading': loading,
                    'aa-restore-archived-lists-empty': empty,
                    'aa-restore-archived-lists-error': error,
                    'aa-restore-archived-lists-select-wrap': selectWrap,
                    'aa-lists-area-tools': areaTools,
                    'aa-tasks-module-root': moduleRoot
                };

                return map[id] || null;
            },
            querySelector: function () {
                return null;
            },
            querySelectorAll: function () {
                return [];
            },
            addEventListener: function () {}
        };

        var api = loadListsAreaTools({ document: dom }, {
            TasksService: {
                getArchivedTaskLists: function () {
                    getArchivedCalls += 1;
                    return Promise.resolve({
                        lists: [
                            { id: 7, title: 'Lista archivada', status: 'archived', updated_at: '2026-06-08 10:00:00' }
                        ]
                    });
                }
            }
        });

        api.initListsAreaTools();
        await api.handleRestoreToolClick({ preventDefault: function () {} });

        assert.equal(getArchivedCalls, 1);
        assert.equal(modal.classList.classes.includes('hidden'), false);
        assert.equal(select.disabled, false);
        assert.equal(select.children.length, 1);
        assert.equal(submit.disabled, true);
    });

    it('sin archivadas muestra empty state y deshabilita restaurar', async () => {
        var modal = makeEl('aa-restore-archived-lists-modal', { hidden: true });
        var select = makeEl('aa-restore-archived-lists-select', { disabled: true });
        var submit = makeEl('aa-restore-archived-lists-submit', { disabled: true });
        var loading = makeEl('aa-restore-archived-lists-loading', { hidden: true });
        var empty = makeEl('aa-restore-archived-lists-empty', { hidden: true });
        var error = makeEl('aa-restore-archived-lists-error', { hidden: true });
        var selectWrap = makeEl('aa-restore-archived-lists-select-wrap');
        var moduleRoot = makeEl('aa-tasks-module-root');

        var dom = {
            readyState: 'complete',
            getElementById: function (id) {
                var map = {
                    'aa-restore-archived-lists-modal': modal,
                    'aa-restore-archived-lists-select': select,
                    'aa-restore-archived-lists-submit': submit,
                    'aa-restore-archived-lists-loading': loading,
                    'aa-restore-archived-lists-empty': empty,
                    'aa-restore-archived-lists-error': error,
                    'aa-restore-archived-lists-select-wrap': selectWrap,
                    'aa-tasks-module-root': moduleRoot
                };

                return map[id] || null;
            },
            querySelector: function () {
                return null;
            },
            querySelectorAll: function () {
                return [];
            },
            addEventListener: function () {}
        };

        var api = loadListsAreaTools({ document: dom }, {
            TasksService: {
                getArchivedTaskLists: function () {
                    return Promise.resolve({ lists: [] });
                }
            }
        });

        await api.loadArchivedListsIntoModal();

        assert.equal(empty.classList.classes.includes('hidden'), false);
        assert.equal(selectWrap.classList.classes.includes('hidden'), true);
        assert.equal(submit.disabled, true);
    });

    it('restaurar llama servicio, cierra modal y refresca feed', async () => {
        var modal = makeEl('aa-restore-archived-lists-modal');
        var select = makeEl('aa-restore-archived-lists-select', { value: '9' });
        var submit = makeEl('aa-restore-archived-lists-submit');
        var loading = makeEl('aa-restore-archived-lists-loading', { hidden: true });
        var empty = makeEl('aa-restore-archived-lists-empty', { hidden: true });
        var error = makeEl('aa-restore-archived-lists-error', { hidden: true });
        var selectWrap = makeEl('aa-restore-archived-lists-select-wrap');
        var restoreCalls = [];
        var reloadCalls = 0;
        var boardReloadCalls = 0;

        var dom = {
            getElementById: function (id) {
                var map = {
                    'aa-restore-archived-lists-modal': modal,
                    'aa-restore-archived-lists-select': select,
                    'aa-restore-archived-lists-submit': submit,
                    'aa-restore-archived-lists-loading': loading,
                    'aa-restore-archived-lists-empty': empty,
                    'aa-restore-archived-lists-error': error,
                    'aa-restore-archived-lists-select-wrap': selectWrap
                };

                return map[id] || null;
            },
            querySelector: function () {
                return null;
            },
            querySelectorAll: function () {
                return [];
            }
        };

        var context = {
            document: createDocumentMock(dom),
            module: { exports: {} }
        };

        context.window = context;
        context.globalThis = context;
        context.TasksService = {
            restoreTaskList: function (listId) {
                restoreCalls.push(listId);
                return Promise.resolve({ list: { id: listId, status: 'active' } });
            }
        };
        context.AAExecutableUserListsVisibleFeed = {
            reload: function () {
                reloadCalls += 1;
                return Promise.resolve();
            }
        };
        context.AATasksBoard = {
            reload: function () {
                boardReloadCalls += 1;
                return Promise.resolve();
            }
        };

        vm.runInNewContext(toolsSrc, context, { filename: toolsPath });

        await context.AAListsAreaTools.handleRestoreSubmit();

        assert.deepEqual(restoreCalls, ['9']);
        assert.equal(modal.classList.classes.includes('hidden'), true);
        assert.equal(reloadCalls, 1);
        assert.equal(boardReloadCalls, 1);
    });

    it('change en select solo habilita botón, no restaura', () => {
        var select = makeEl('aa-restore-archived-lists-select', { value: '4', disabled: false });
        var submit = makeEl('aa-restore-archived-lists-submit', { disabled: true });
        var restoreCalls = 0;

        var dom = {
            getElementById: function (id) {
                var map = {
                    'aa-restore-archived-lists-select': select,
                    'aa-restore-archived-lists-submit': submit
                };

                return map[id] || null;
            },
            querySelector: function () {
                return null;
            },
            querySelectorAll: function () {
                return [];
            }
        };

        var context = {
            document: createDocumentMock(dom),
            module: { exports: {} },
            TasksService: {
                restoreTaskList: function () {
                    restoreCalls += 1;
                    return Promise.resolve();
                }
            }
        };

        context.window = context;
        context.globalThis = context;

        vm.runInNewContext(toolsSrc, context, { filename: toolsPath });

        context.AAListsAreaTools.updateSubmitEnabled();

        assert.equal(submit.disabled, false);
        assert.equal(restoreCalls, 0);
    });

    it('error de carga muestra mensaje en modal', async () => {
        var error = makeEl('aa-restore-archived-lists-error', { hidden: true });
        var loading = makeEl('aa-restore-archived-lists-loading', { hidden: true });
        var select = makeEl('aa-restore-archived-lists-select', { disabled: true });
        var submit = makeEl('aa-restore-archived-lists-submit', { disabled: true });
        var empty = makeEl('aa-restore-archived-lists-empty', { hidden: true });
        var selectWrap = makeEl('aa-restore-archived-lists-select-wrap');

        var dom = {
            getElementById: function (id) {
                var map = {
                    'aa-restore-archived-lists-error': error,
                    'aa-restore-archived-lists-loading': loading,
                    'aa-restore-archived-lists-select': select,
                    'aa-restore-archived-lists-submit': submit,
                    'aa-restore-archived-lists-empty': empty,
                    'aa-restore-archived-lists-select-wrap': selectWrap
                };

                return map[id] || null;
            },
            querySelector: function () {
                return null;
            },
            querySelectorAll: function () {
                return [];
            }
        };

        var api = loadListsAreaTools({ document: dom }, {
            TasksService: {
                getArchivedTaskLists: function () {
                    return Promise.reject(new Error('Fallo de red'));
                }
            }
        });

        await api.loadArchivedListsIntoModal();

        assert.equal(error.classList.classes.includes('hidden'), false);
        assert.match(error.textContent, /Fallo de red/);
    });
});

describe('lists-area-tools MC13N-2', () => {
    it('toggle del menú actualiza aria-expanded', () => {
        var trigger = makeButtonEl({
            id: 'aa-lists-options-trigger',
            isOptionsTrigger: true
        });
        var menu = makeEl('aa-lists-options-menu', { hidden: true });
        trigger.setAttribute = function (name, value) {
            trigger.attrs = trigger.attrs || {};
            trigger.attrs[name] = value;
        };

        var dom = {
            getElementById: function (id) {
                return id === 'aa-lists-options-menu' ? menu : null;
            },
            querySelector: function (selector) {
                return selector === '#aa-lists-options-trigger' ? trigger : null;
            }
        };

        var api = loadListsAreaTools({ document: createDocumentMock(dom) });

        api.openOptionsMenu();
        assert.equal(menu.classList.classes.includes('hidden'), false);
        assert.equal(trigger.attrs['aria-expanded'], 'true');

        api.closeOptionsMenu();
        assert.equal(menu.classList.classes.includes('hidden'), true);
        assert.equal(trigger.attrs['aria-expanded'], 'false');
    });

    it('click en trigger abre y cierra menú', () => {
        var trigger = makeButtonEl({
            id: 'aa-lists-options-trigger',
            isOptionsTrigger: true
        });
        var menu = makeEl('aa-lists-options-menu', { hidden: true });
        var areaTools = makeEl('aa-lists-area-tools');
        var moduleRoot = makeEl('aa-tasks-module-root');
        var clickHandlers = [];

        var dom = {
            readyState: 'complete',
            getElementById: function (id) {
                var map = {
                    'aa-lists-options-menu': menu,
                    'aa-lists-area-tools': areaTools,
                    'aa-tasks-module-root': moduleRoot
                };

                return map[id] || null;
            },
            querySelector: function (selector) {
                return selector === '#aa-lists-options-trigger' ? trigger : null;
            },
            querySelectorAll: function () {
                return [];
            },
            addEventListener: function (type, handler) {
                if (type === 'click') {
                    clickHandlers.push(handler);
                }
            }
        };

        var api = loadListsAreaTools({ document: dom });
        api.initListsAreaTools();

        clickHandlers[0]({ target: trigger, preventDefault: function () {} });
        assert.equal(menu.classList.classes.includes('hidden'), false);

        clickHandlers[0]({ target: trigger, preventDefault: function () {} });
        assert.equal(menu.classList.classes.includes('hidden'), true);
    });

    it('Escape cierra menú abierto', () => {
        var trigger = makeButtonEl({ id: 'aa-lists-options-trigger' });
        var menu = makeEl('aa-lists-options-menu');
        var areaTools = makeEl('aa-lists-area-tools');
        var moduleRoot = makeEl('aa-tasks-module-root');
        var keydownHandlers = [];

        trigger.setAttribute = function () {};

        var dom = {
            readyState: 'complete',
            getElementById: function (id) {
                var map = {
                    'aa-lists-options-menu': menu,
                    'aa-lists-area-tools': areaTools,
                    'aa-tasks-module-root': moduleRoot
                };

                return map[id] || null;
            },
            querySelector: function (selector) {
                return selector === '#aa-lists-options-trigger' ? trigger : null;
            },
            querySelectorAll: function () {
                return [];
            },
            addEventListener: function (type, handler) {
                if (type === 'keydown') {
                    keydownHandlers.push(handler);
                }
            }
        };

        var api = loadListsAreaTools({ document: dom });
        api.initListsAreaTools();
        api.openOptionsMenu();
        keydownHandlers[0]({ key: 'Escape' });

        assert.equal(menu.classList.classes.includes('hidden'), true);
    });

    it('click fuera cierra menú abierto', () => {
        var menu = makeEl('aa-lists-options-menu');
        var trigger = makeButtonEl({ id: 'aa-lists-options-trigger' });
        var outside = makeButtonEl({ id: 'outside' });
        var areaTools = makeEl('aa-lists-area-tools');
        var moduleRoot = makeEl('aa-tasks-module-root');
        var clickHandlers = [];

        trigger.setAttribute = function () {};

        var dom = {
            readyState: 'complete',
            getElementById: function (id) {
                var map = {
                    'aa-lists-options-menu': menu,
                    'aa-lists-area-tools': areaTools,
                    'aa-tasks-module-root': moduleRoot
                };

                return map[id] || null;
            },
            querySelector: function (selector) {
                return selector === '#aa-lists-options-trigger' ? trigger : null;
            },
            querySelectorAll: function () {
                return [];
            },
            addEventListener: function (type, handler) {
                if (type === 'click') {
                    clickHandlers.push(handler);
                }
            }
        };

        var api = loadListsAreaTools({ document: dom });
        api.initListsAreaTools();
        api.openOptionsMenu();
        clickHandlers[0]({ target: outside });

        assert.equal(menu.classList.classes.includes('hidden'), true);
    });

    it('item Lista cierra menú y abre modal de creación vía AATasksBoard', () => {
        var menuItem = makeButtonEl({ isCreateListItem: true });
        var menu = makeEl('aa-lists-options-menu');
        var trigger = makeButtonEl({ id: 'aa-lists-options-trigger' });
        var openNewListCalls = 0;

        trigger.setAttribute = function () {};

        var dom = {
            getElementById: function (id) {
                return id === 'aa-lists-options-menu' ? menu : null;
            },
            querySelector: function (selector) {
                return selector === '#aa-lists-options-trigger' ? trigger : null;
            },
            querySelectorAll: function () {
                return [];
            }
        };

        var api = loadListsAreaTools({ document: createDocumentMock(dom) }, {
            AATasksBoard: {
                openNewList: function () {
                    openNewListCalls += 1;
                }
            }
        });

        api.openOptionsMenu();
        api.handleCreateListToolClick({ preventDefault: function () {}, target: menuItem });

        assert.equal(openNewListCalls, 1);
        assert.equal(menu.classList.classes.includes('hidden'), true);
        assert.equal(api.CREATE_LIST_TOOL_SELECTOR, '[data-lists-tool="create-list"]');
    });

    it('item Desarchivar listas abre modal existente', async () => {
        var modal = makeEl('aa-restore-archived-lists-modal', { hidden: true });
        var menuItem = makeButtonEl({ isRestoreItem: true });
        var menu = makeEl('aa-lists-options-menu');
        var trigger = makeButtonEl({ id: 'aa-lists-options-trigger' });
        var select = makeEl('aa-restore-archived-lists-select', { disabled: true });
        var submit = makeEl('aa-restore-archived-lists-submit', { disabled: true });
        var loading = makeEl('aa-restore-archived-lists-loading', { hidden: true });
        var empty = makeEl('aa-restore-archived-lists-empty', { hidden: true });
        var error = makeEl('aa-restore-archived-lists-error', { hidden: true });
        var selectWrap = makeEl('aa-restore-archived-lists-select-wrap');
        var getArchivedCalls = 0;

        trigger.setAttribute = function () {};

        var dom = {
            getElementById: function (id) {
                var map = {
                    'aa-restore-archived-lists-modal': modal,
                    'aa-lists-options-menu': menu,
                    'aa-restore-archived-lists-select': select,
                    'aa-restore-archived-lists-submit': submit,
                    'aa-restore-archived-lists-loading': loading,
                    'aa-restore-archived-lists-empty': empty,
                    'aa-restore-archived-lists-error': error,
                    'aa-restore-archived-lists-select-wrap': selectWrap
                };

                return map[id] || null;
            },
            querySelector: function (selector) {
                return selector === '#aa-lists-options-trigger' ? trigger : null;
            },
            querySelectorAll: function () {
                return [];
            }
        };

        var api = loadListsAreaTools({ document: createDocumentMock(dom) }, {
            TasksService: {
                getArchivedTaskLists: function () {
                    getArchivedCalls += 1;
                    return Promise.resolve({ lists: [] });
                }
            }
        });

        api.openOptionsMenu();
        await api.handleRestoreToolClick({ preventDefault: function () {}, target: menuItem });

        assert.equal(getArchivedCalls, 1);
        assert.equal(menu.classList.classes.includes('hidden'), true);
        assert.equal(modal.classList.classes.includes('hidden'), false);
    });

    it('Regresar tareas ignoradas cancela sin llamar servicio', async () => {
        var areaError = makeEl('aa-lists-area-tools-error', { hidden: true });
        var menu = makeEl('aa-lists-options-menu');
        var trigger = makeButtonEl({ id: 'aa-lists-options-trigger' });
        var returnCalls = 0;
        var confirmCalls = 0;

        trigger.setAttribute = function () {};

        var dom = {
            getElementById: function (id) {
                var map = {
                    'aa-lists-area-tools-error': areaError,
                    'aa-lists-options-menu': menu
                };

                return map[id] || null;
            },
            querySelector: function (selector) {
                return selector === '#aa-lists-options-trigger' ? trigger : null;
            },
            querySelectorAll: function () {
                return [];
            }
        };

        var api = loadListsAreaTools({ document: createDocumentMock(dom) }, {
            confirm: function (message) {
                confirmCalls += 1;
                assert.equal(
                    message,
                    'Todas las tareas ignoradas de tus listas activas regresarán a sus listas. ¿Quieres continuar?'
                );
                return false;
            },
            TasksService: {
                returnIgnoredUserTasks: function () {
                    returnCalls += 1;
                    return Promise.resolve({ returned_count: 1, task_ids: [1] });
                }
            }
        });

        await api.handleReturnIgnoredTasksClick({ preventDefault: function () {} });

        assert.equal(confirmCalls, 1);
        assert.equal(returnCalls, 0);
    });

    it('Regresar tareas ignoradas confirma, llama servicio y refresca feed', async () => {
        var areaError = makeEl('aa-lists-area-tools-error', { hidden: true });
        var menu = makeEl('aa-lists-options-menu');
        var trigger = makeButtonEl({ id: 'aa-lists-options-trigger' });
        var returnCalls = 0;
        var reloadCalls = 0;
        var boardReloadCalls = 0;

        trigger.setAttribute = function () {};

        var dom = {
            getElementById: function (id) {
                var map = {
                    'aa-lists-area-tools-error': areaError,
                    'aa-lists-options-menu': menu
                };

                return map[id] || null;
            },
            querySelector: function (selector) {
                return selector === '#aa-lists-options-trigger' ? trigger : null;
            },
            querySelectorAll: function () {
                return [];
            }
        };

        var api = loadListsAreaTools({ document: createDocumentMock(dom) }, {
            confirm: function () {
                return true;
            },
            TasksService: {
                returnIgnoredUserTasks: function () {
                    returnCalls += 1;
                    return Promise.resolve({ returned_count: 2, task_ids: [1, 2] });
                }
            },
            AAExecutableUserListsVisibleFeed: {
                reload: function () {
                    reloadCalls += 1;
                    return Promise.resolve();
                }
            },
            AATasksBoard: {
                reload: function () {
                    boardReloadCalls += 1;
                    return Promise.resolve();
                }
            }
        });

        await api.handleReturnIgnoredTasksClick({ preventDefault: function () {} });

        assert.equal(returnCalls, 1);
        assert.equal(reloadCalls, 1);
        assert.equal(boardReloadCalls, 1);
        assert.equal(areaError.classList.classes.includes('hidden'), true);
    });
});

