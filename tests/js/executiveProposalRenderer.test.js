'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const renderer = require(path.join(__dirname, '../../assets/js/ui/executiveProposalRenderer.js'));

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
                continuation: false
            },
            {
                slot: 'next',
                task_id: 2,
                title: 'Tarea siguiente',
                description: null,
                is_overdue: true,
                actionable: false,
                continuation: true
            },
            {
                slot: 'third',
                task_id: 3,
                title: 'Tercera tarea',
                description: null,
                is_overdue: false,
                actionable: false,
                continuation: true
            }
        ],
        meta: {
            version: 1,
            eligible_count_in_focus_list: 3
        }
    }, overrides || {});
}

describe('executiveProposalRenderer MC2', () => {
    it('current aparece expandida con label Ahora y descripción', () => {
        const html = renderer.renderCurrentTask(basePayload().tasks[0]);

        assert.match(html, /aa-executive-slot-current/);
        assert.match(html, />Ahora</);
        assert.match(html, />Tarea actual</);
        assert.match(html, />Descripción actual</);
    });

    it('next y third aparecen compactas con labels Siguiente y Después', () => {
        const payload = basePayload();
        const nextHtml = renderer.renderContinuationTask(payload.tasks[1], 'next');
        const thirdHtml = renderer.renderContinuationTask(payload.tasks[2], 'third');

        assert.match(nextHtml, /aa-executive-slot-next/);
        assert.match(nextHtml, />Siguiente</);
        assert.match(nextHtml, />Tarea siguiente</);
        assert.match(thirdHtml, /aa-executive-slot-third/);
        assert.match(thirdHtml, />Después</);
        assert.match(thirdHtml, />Tercera tarea</);
    });

    it('muestra label Vencida cuando is_overdue es true', () => {
        const html = renderer.renderContinuationTask({
            slot: 'next',
            task_id: 2,
            title: 'Vencida demo',
            is_overdue: true
        }, 'next');

        assert.match(html, />Vencida</);
    });

    it('buildProposalParts marca empty cuando status es empty', () => {
        const parts = renderer.buildProposalParts({ status: 'empty', tasks: [] });

        assert.equal(parts.isEmpty, true);
        assert.equal(parts.listHtml, '');
    });

    it('buildProposalParts incluye foco y top-3 cuando hay propuesta', () => {
        const parts = renderer.buildProposalParts(basePayload());

        assert.equal(parts.isEmpty, false);
        assert.match(parts.focusHtml, />Foco:</);
        assert.match(parts.focusHtml, />Lista foco</);
        assert.match(parts.listHtml, /aa-executive-slot-current/);
        assert.match(parts.listHtml, /aa-executive-slot-next/);
        assert.match(parts.listHtml, /aa-executive-slot-third/);
    });

    it('muestra badge Agenda app cuando source_category es agenda_app', () => {
        const html = renderer.renderFocusContext({
            title: 'Activación',
            source_category: 'agenda_app'
        });

        assert.match(html, />Agenda app</);
    });

    it('no emite data-tasks-action ni data-learning-action ni botones mutativos', () => {
        const parts = renderer.buildProposalParts(basePayload());
        const html = parts.focusHtml + parts.listHtml;

        assert.equal(html.includes('data-tasks-action'), false);
        assert.equal(html.includes('data-learning-action'), false);
        assert.equal(html.includes('<button'), false);
        assert.equal(html.includes('Completar'), false);
        assert.equal(html.includes('Ignorar'), false);
        assert.equal(html.includes('Archivar'), false);
        assert.equal(html.includes('Eliminar'), false);
    });

    it('payload vacío o inválido no rompe buildProposalParts', () => {
        assert.doesNotThrow(function () {
            renderer.buildProposalParts(null);
            renderer.buildProposalParts(undefined);
            renderer.buildProposalParts({});
            renderer.buildProposalParts({ status: 'ready', tasks: [{ slot: 'invalid' }] });
        });
    });
});
