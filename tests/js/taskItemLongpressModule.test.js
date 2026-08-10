'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/learning/task-item-longpress-module.js'
);
const indexPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/learning/index.php'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const indexSrc = fs.readFileSync(indexPath, 'utf8');

function loadModule() {
    var context = {
        window: {},
        console: console,
        document: undefined,
        module: { exports: {} },
        setTimeout: setTimeout,
        clearTimeout: clearTimeout
    };

    context.window = context;
    context.globalThis = context;

    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    return context.module.exports;
}

describe('task-item-longpress-module', () => {
    it('index.php encola task-item-longpress-module.js después de task-edit', () => {
        var taskEditPos = indexSrc.indexOf('task-edit-module.js');
        var longpressPos = indexSrc.indexOf('task-item-longpress-module.js');

        assert.ok(taskEditPos !== -1, 'task-edit-module.js debe encolarse');
        assert.ok(longpressPos !== -1, 'task-item-longpress-module.js debe encolarse');
        assert.ok(taskEditPos < longpressPos, 'longpress debe ir después de task-edit');
    });

    it('expone constantes y resolvers para summary de tarea editable', () => {
        var exports = loadModule();

        assert.equal(exports.LONG_PRESS_MS, 500);
        assert.equal(exports.MOVE_TOLERANCE_PX, 10);
        assert.equal(typeof exports.resolveTaskItemSummary, 'function');
        assert.equal(typeof exports.resolveEditButton, 'function');
        assert.equal(typeof exports.isInteractiveTarget, 'function');
    });

    it('resolveTaskItemSummary solo acepta summary de aa-executable-item', () => {
        var exports = loadModule();

        var itemDetails = {
            classList: { contains: function (cls) { return cls === 'aa-executable-item'; } }
        };
        var listDetails = {
            classList: { contains: function (cls) { return cls === 'aa-executable-list-card'; } }
        };
        var itemSummary = {
            classList: { contains: function () { return true; } },
            parentElement: itemDetails
        };
        var listSummary = {
            classList: { contains: function () { return true; } },
            parentElement: listDetails
        };

        var itemTarget = {
            closest: function (sel) {
                return sel === 'summary.aa-executable-item-summary' ? itemSummary : null;
            }
        };
        var listTarget = {
            closest: function (sel) {
                return sel === 'summary.aa-executable-item-summary' ? null : listSummary;
            }
        };

        assert.equal(exports.resolveTaskItemSummary(itemTarget), itemSummary);
        assert.equal(exports.resolveTaskItemSummary(listTarget), null);
    });

    it('resolveEditButton busca data-aa-task-edit solo en listas de usuario', () => {
        var exports = loadModule();
        var editButton = { id: 'edit-btn' };
        var userListCard = {
            querySelector: function (sel) {
                return sel === '[data-aa-list-add-task="1"]' ? {} : null;
            }
        };
        var systemListCard = {
            querySelector: function () {
                return null;
            }
        };
        var userDetails = {
            querySelector: function (sel) {
                return sel === '[data-aa-task-edit="1"]' ? editButton : null;
            },
            closest: function (sel) {
                return sel === '.aa-executable-list-card' ? userListCard : null;
            }
        };
        var systemDetails = {
            querySelector: function (sel) {
                return sel === '[data-aa-task-edit="1"]' ? editButton : null;
            },
            closest: function (sel) {
                return sel === '.aa-executable-list-card' ? systemListCard : null;
            }
        };

        assert.equal(exports.resolveEditButton({ parentElement: userDetails }), editButton);
        assert.equal(exports.resolveEditButton({ parentElement: systemDetails }), null);
        assert.equal(exports.resolveEditButton({ parentElement: null }), null);
    });

    it('isInteractiveTarget ignora botones y menú de opciones de tarea', () => {
        var exports = loadModule();

        assert.equal(exports.isInteractiveTarget({
            closest: function (sel) {
                return String(sel).indexOf('button') !== -1 ? {} : null;
            }
        }), true);

        assert.equal(exports.isInteractiveTarget({
            closest: function (sel) {
                return String(sel).indexOf('data-aa-task-options-trigger') !== -1 ? {} : null;
            }
        }), true);

        assert.equal(exports.isInteractiveTarget({
            closest: function () { return null; }
        }), false);
    });

    it('longpress abre AATaskEdit.openEditModalFromButton y no el modal de nueva tarea', () => {
        assert.match(moduleSrc, /AATaskEdit\.openEditModalFromButton/);
        assert.match(moduleSrc, /data-aa-task-edit="1"/);
        assert.doesNotMatch(moduleSrc, /openNewTaskForList/);
        assert.doesNotMatch(moduleSrc, /aa-task-create-modal|aa-tasks-new-task/);
    });
});
