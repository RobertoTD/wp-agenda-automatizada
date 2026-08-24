'use strict';

/**
 * Ciclo 2 — timeline completo 24 h (48 franjas).
 *
 * Ejecutar: node --test tests/js/calendarTimelineFullDay.test.js
 */

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const timelinePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/calendar/calendar-section/calendar-timeline.js'
);
const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/calendar/calendar-module.js'
);
const timelineSrc = fs.readFileSync(timelinePath, 'utf8');
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

const FULL_DAY_START_MIN = 0;
const FULL_DAY_END_MIN = 1440;
const SLOT_STEP_MIN = 30;

function buildFullDaySlots() {
    const timeSlots = [];
    for (let min = FULL_DAY_START_MIN; min < FULL_DAY_END_MIN; min += SLOT_STEP_MIN) {
        timeSlots.push(min);
    }
    return timeSlots;
}

function runTimelineRender(options) {
    const gridChildren = [];
    const grid = {
        style: {},
        appendChild(node) {
            gridChildren.push(node);
        }
    };
    Object.defineProperty(grid, 'innerHTML', {
        get() {
            return '';
        },
        set() {
            gridChildren.length = 0;
        }
    });

    let viewportScrollTop = 250;
    const viewport = {
        get scrollTop() {
            return viewportScrollTop;
        },
        set scrollTop(value) {
            viewportScrollTop = value;
        }
    };

    const sandbox = {
        window: {
            DateUtils: {
                ymd: () => '2026-08-23',
                minutesFromDate: () => 600
            },
            CalendarTimeline: null,
            AA_DEBUG_CALENDAR_OVERFLOW: false,
            getComputedStyle: () => ({ fontSize: '12px' })
        },
        document: {
            createElement(tag) {
                return {
                    tagName: tag.toUpperCase(),
                    className: '',
                    id: '',
                    style: {},
                    textContent: '',
                    appendChild() {},
                    setAttribute() {},
                    querySelector() {
                        return null;
                    },
                    getAttribute() {
                        return null;
                    }
                };
            },
            getElementById(id) {
                return id === 'aa-time-grid' ? grid : null;
            },
            querySelector(sel) {
                return sel === '.aa-day-timeline-viewport' ? viewport : null;
            }
        },
        console
    };

    vm.createContext(sandbox);
    vm.runInContext(timelineSrc, sandbox, { filename: 'calendar-timeline.js' });

    const result = sandbox.window.CalendarTimeline.renderTimelineForDate('2026-08-23', options || {});

    return {
        result,
        gridChildren,
        viewportScrollTop
    };
}

describe('calendar timeline full day (Ciclo 2)', () => {
    it('genera exactamente 48 slots de 00:00 a 23:30', () => {
        const timeSlots = buildFullDaySlots();
        assert.equal(timeSlots.length, 48);
        assert.equal(timeSlots[0], 0);
        assert.equal(timeSlots[47], 1410);

        const slotRowIndex = new Map();
        timeSlots.forEach((minutes, index) => {
            slotRowIndex.set(minutes, { rowIndex: index + 1 });
        });

        assert.equal(slotRowIndex.get(0).rowIndex, 1);
        assert.equal(slotRowIndex.get(1410).rowIndex, 48);
    });

    it('timeline fuente usa día completo desacoplado de visualIntervals vacío', () => {
        assert.match(timelineSrc, /FULL_DAY_END_MIN\s*=\s*1440/);
        assert.match(timelineSrc, /for \(let min = FULL_DAY_START_MIN; min < FULL_DAY_END_MIN; min \+= SLOT_STEP_MIN\)/);
        assert.doesNotMatch(timelineSrc, /mensaje\.textContent = 'Sin citas'/);
        assert.doesNotMatch(timelineSrc, /visualIntervals\.length === 0/);
    });

    it('visualIntervals vacío produce timeline válido sin retorno nulo', () => {
        const { result, gridChildren } = runTimelineRender({ visualIntervals: [] });

        assert.notEqual(result, null);
        assert.equal(result.timeSlots.length, 48);
        assert.equal(result.slotRowIndex.get(0).rowIndex, 1);
        assert.equal(result.slotRowIndex.get(1410).rowIndex, 48);

        const overlay = gridChildren.find((node) => node.id === 'aa-expanded-cards-overlay');
        assert.ok(overlay);
        assert.equal(overlay.style.gridRow, '1 / 49');
    });

    it('resetScroll solo cuando options.resetScroll es true', () => {
        const withoutReset = runTimelineRender({ resetScroll: false });
        assert.equal(withoutReset.viewportScrollTop, 250);

        const withReset = runTimelineRender({ resetScroll: true });
        assert.equal(withReset.viewportScrollTop, 0);
    });

    it('calendar-module pasa resetScroll en carga inicial y cambio de fecha', () => {
        assert.match(moduleSrc, /renderTimelineForDate\(fechaInicial,\s*\{\s*resetScroll:\s*true\s*\}\)/);
        assert.match(moduleSrc, /renderTimelineForDate\(fecha,\s*\{\s*resetScroll:\s*true\s*\}\)/);
    });

    it('recargarTimelineDelDiaActual no fuerza resetScroll', () => {
        const recargarIndex = moduleSrc.indexOf('function recargarTimelineDelDiaActual');
        assert.ok(recargarIndex >= 0);
        const recargarSlice = moduleSrc.slice(recargarIndex, recargarIndex + 600);
        assert.match(recargarSlice, /renderTimelineForDate\(fecha\)/);
        assert.doesNotMatch(recargarSlice, /resetScroll:\s*true/);
    });
});
