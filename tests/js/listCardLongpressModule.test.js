'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/learning/list-card-longpress-module.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

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

describe('list-card-longpress-module', () => {
    it('expone resolvers de elegibilidad de lista de usuario', () => {
        var exports = loadModule();

        assert.equal(exports.LONG_PRESS_MS, 500);
        assert.equal(typeof exports.resolveListCardSummary, 'function');
        assert.equal(typeof exports.resolveAddTaskButton, 'function');
        assert.equal(typeof exports.isUserManualListCard, 'function');
    });

    it('isUserManualListCard exige data-aa-list-add-task en la card', () => {
        var exports = loadModule();
        var addTaskBtn = { id: 'add' };

        var userDetails = {
            classList: { contains: function (cls) { return cls === 'aa-executable-list-card'; } },
            querySelector: function (sel) {
                return sel === '[data-aa-list-add-task="1"]' ? addTaskBtn : null;
            }
        };
        var systemDetails = {
            classList: { contains: function (cls) { return cls === 'aa-executable-list-card'; } },
            querySelector: function () { return null; }
        };

        assert.equal(exports.isUserManualListCard({ parentElement: userDetails }), true);
        assert.equal(exports.isUserManualListCard({ parentElement: systemDetails }), false);
        assert.equal(exports.resolveAddTaskButton({ parentElement: userDetails }), addTaskBtn);
        assert.equal(exports.resolveAddTaskButton({ parentElement: systemDetails }), null);
    });

    it('solo abre modal vía openNewTaskForList cuando hay add-task', () => {
        assert.match(moduleSrc, /isUserManualListCard/);
        assert.match(moduleSrc, /data-aa-list-add-task="1"/);
        assert.match(moduleSrc, /openNewTaskForList/);
    });
});
