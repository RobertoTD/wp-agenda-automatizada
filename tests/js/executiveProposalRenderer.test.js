'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');

const renderer = require(path.join(__dirname, '../../assets/js/ui/executiveProposalRenderer.js'));
const indexSrc = fs.readFileSync(
    path.join(__dirname, '../../includes/admin/ui/modules/learning/index.php'),
    'utf8'
);

function basePayload(overrides) {
    return Object.assign({
        success: true,
        status: 'ready',
        focus_list: {
            id: 10,
            title: 'Lista foco',
            source_category: 'user',
            importance: 5
        },
        tasks: [
            {
                slot: 'current',
                task_id: 1,
                title: 'Tarea actual',
                description: 'Descripción actual',
                is_overdue: false,
                actionable: true,
                continuation: false,
                executive_actions: [
                    { key: 'complete', type: 'status', label: 'Completar' },
                    { key: 'dismiss', type: 'intent', label: 'Ahora no' },
                    { key: 'navigate.settings', type: 'navigate', label: 'Ir', url: 'https://example.test/settings' }
                ]
            },
            {
                slot: 'next',
                task_id: 2,
                title: 'Tarea siguiente',
                description: null,
                is_overdue: true,
                actionable: false,
                continuation: true,
                executive_actions: []
            },
            {
                slot: 'third',
                task_id: 3,
                title: 'Tercera tarea',
                description: null,
                is_overdue: false,
                actionable: false,
                continuation: true,
                executive_actions: []
            }
        ],
        meta: {
            version: 1,
            eligible_count_in_focus_list: 3,
            sprint: {
                sprint_active: false,
                inactive_reason: 'no_active_sprint'
            },
            focus_controls: {
                can_change_focus: true,
                can_go_previous: true
            }
        }
    }, overrides || {});
}

describe('executiveProposalRenderer MC6', () => {
    it('index.php no incluye Acciones recomendadas ahora', () => {
        assert.doesNotMatch(indexSrc, /Acciones recomendadas ahora/);
        assert.match(indexSrc, /id="aa-executive-status"/);
        assert.match(indexSrc, /id="aa-executive-header-actions"/);
    });

    it('resolveStatusLabel sprint activo → En ejecución', () => {
        const label = renderer.resolveStatusLabel(basePayload({
            meta: { sprint: { sprint_active: true } }
        }), null);

        assert.equal(label, 'En ejecución');
    });

    it('resolveStatusLabel sin sprint → Elige tu siguiente tarea', () => {
        const label = renderer.resolveStatusLabel(basePayload(), null);

        assert.equal(label, 'Elige tu siguiente tarea');
    });

    it('resolveStatusLabel choosing → Buscando tarea para ejecutar', () => {
        const label = renderer.resolveStatusLabel(basePayload(), 'choosing');

        assert.equal(label, 'Buscando tarea para ejecutar');
    });

    it('resolveStatusLabel organizing → Organizando tareas', () => {
        const label = renderer.resolveStatusLabel(basePayload(), 'organizing');

        assert.equal(label, 'Organizando tareas');
    });

    it('renderStatusHtml incluye led verde solo en ejecución', () => {
        const executing = renderer.renderStatusHtml('En ejecución');
        const ready = renderer.renderStatusHtml('Elige tu siguiente tarea');

        assert.match(executing, /aa-executive-status-dot/);
        assert.equal(ready.includes('aa-executive-status-dot'), false);
    });

    it('renderHeaderFocusControls usa data-executive-focus-action', () => {
        const html = renderer.renderHeaderFocusControls({
            can_change_focus: true,
            can_go_previous: true
        });

        assert.match(html, /data-executive-focus-action="previous_focus"/);
        assert.match(html, /data-executive-focus-action="change_focus"/);
        assert.equal(html.includes('data-executive-action'), false);
        assert.equal(html.includes('data-tasks-action'), false);
        assert.equal(html.includes('data-learning-action'), false);
    });

    it('current muestra Lista: y no Ahora', () => {
        const html = renderer.renderCurrentTask(basePayload().tasks[0], 'Lista foco');

        assert.match(html, />Lista: Lista foco</);
        assert.equal(html.includes('>Ahora<'), false);
    });

    it('continuation summary compacto sin cards', () => {
        const payload = basePayload();
        const html = renderer.renderContinuationSummary(payload.tasks[1], payload.tasks[2]);

        assert.match(html, /<p class="aa-executive-continuation/);
        assert.equal((html.match(/<p /g) || []).length, 1);
        assert.match(html, />Siguiente:</);
        assert.match(html, />Después:</);
        assert.match(html, /Siguiente:.*,.*Después:/);
        assert.equal(html.includes('sm:flex-row'), false);
        assert.equal(html.includes('sm:max-w-[48%]'), false);
        assert.equal(html.includes('aa-executive-slot-next'), false);
        assert.equal(html.includes('border border-gray-200'), false);
    });

    it('continuation summary solo nextTask sin coma sobrante', () => {
        const payload = basePayload();
        const html = renderer.renderContinuationSummary(payload.tasks[1], null);

        assert.match(html, /<p class="aa-executive-continuation/);
        assert.match(html, />Siguiente:</);
        assert.equal(html.includes('Después:'), false);
        assert.equal(html.includes(', '), false);
    });

    it('buildProposalParts no incluye bloque de foco separado', () => {
        const parts = renderer.buildProposalParts(basePayload());

        assert.equal(parts.isEmpty, false);
        assert.match(parts.listHtml, /aa-executive-slot-current/);
        assert.match(parts.listHtml, /aa-executive-continuation/);
        assert.equal(parts.listHtml.includes('aa-executive-focus-context'), false);
    });

    it('current muestra botones ejecutivos con data-executive-*', () => {
        const html = renderer.renderCurrentTask(basePayload().tasks[0], 'Lista foco');

        assert.match(html, /data-executive-action="1"/);
        assert.match(html, /data-executive-action-key="dismiss"/);
        assert.match(html, />Ahora no</);
    });

    it('current vencida sin Confirmar en executive_actions no pinta botón Confirmar', () => {
        const task = Object.assign({}, basePayload().tasks[0], {
            is_overdue: true,
            executive_actions: [
                { key: 'dismiss', type: 'intent', label: 'Ahora no' }
            ]
        });
        const html = renderer.renderCurrentTask(task, 'Lista foco');

        assert.match(html, />Vencida</);
        assert.doesNotMatch(html, />Confirmar</);
        assert.match(html, />Ahora no</);
    });

    it('current vencida con missed en executive_actions pinta No realizada y conserva Vencida (MC4)', () => {
        const task = Object.assign({}, basePayload().tasks[0], {
            is_overdue: true,
            executive_actions: [
                { key: 'complete', type: 'status', label: 'Completar', to: 'done' },
                { key: 'missed', type: 'status', label: 'No realizada', to: 'missed' },
                { key: 'dismiss', type: 'intent', label: 'Ahora no' }
            ]
        });
        const html = renderer.renderCurrentTask(task, 'Lista foco');

        assert.match(html, />Vencida</);
        assert.match(html, /data-executive-action-key="missed"/);
        assert.match(html, />No realizada</);
        assert.match(html, />Ahora no</);
        assert.match(html, /data-executive-action-key="missed"[^>]*amber|amber[\s\S]*data-executive-action-key="missed"/);
    });

    it('payload vacío no rompe buildProposalParts', () => {
        assert.doesNotThrow(function () {
            renderer.buildProposalParts(null);
            renderer.buildProposalParts({ status: 'empty', tasks: [] });
        });
    });
});
