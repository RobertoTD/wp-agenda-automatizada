'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');
const vm = require('node:vm');

const rendererPath = path.join(__dirname, '../../assets/js/ui/taskBoardRenderer.js');

function loadRenderer() {
    var context = {
        window: {}
    };

    context.window = context;

    vm.runInNewContext(fs.readFileSync(rendererPath, 'utf8'), context, {
        filename: rendererPath
    });

    return context.window.AATaskBoardRenderer;
}

describe('AATaskBoardRenderer execution_available_at', () => {
    it('renderBoard muestra Realizar a partir de diferenciado de Vence', () => {
        var renderer = loadRenderer();
        var html = renderer.renderBoard({
            lists: [{
                id: 7,
                title: 'Mi lista',
                source_category: 'user',
                managed_by: 'user'
            }],
            tasks: [{
                id: 42,
                list_id: 7,
                title: 'Tarea con fechas',
                status: 'pending',
                execution_available_at: '2026-06-18 14:30:00',
                due_at: '2026-06-20 08:37:00'
            }],
            organization: {
                task_bucket_order_by_list: {
                    7: { primary: [42], secondary: [] }
                }
            }
        });

        assert.match(html, /text-slate-600[\s\S]*Realizar a partir de: 2026-06-18 14:30:00/);
        assert.match(html, /text-gray-500[\s\S]*Vence: 2026-06-20 08:37:00/);

        var realizarPos = html.indexOf('Realizar a partir de:');
        var vencePos = html.indexOf('Vence:');

        assert.notEqual(realizarPos, -1);
        assert.ok(realizarPos < vencePos);
    });

    it('renderBoard omite Realizar a partir de cuando execution_available_at es null', () => {
        var renderer = loadRenderer();
        var html = renderer.renderBoard({
            lists: [{
                id: 7,
                title: 'Mi lista',
                source_category: 'user',
                managed_by: 'user'
            }],
            tasks: [{
                id: 42,
                list_id: 7,
                title: 'Solo vencimiento',
                status: 'pending',
                due_at: '2026-06-20 08:37:00'
            }],
            organization: {
                task_bucket_order_by_list: {
                    7: { primary: [42], secondary: [] }
                }
            }
        });

        assert.doesNotMatch(html, /Realizar a partir de:/);
        assert.match(html, /Vence: 2026-06-20 08:37:00/);
    });
});
