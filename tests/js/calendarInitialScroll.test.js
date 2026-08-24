'use strict';

/**
 * Ciclo 3 — posicionamiento inicial inteligente.
 *
 * Ejecutar: node --test tests/js/calendarInitialScroll.test.js
 */

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const initialScrollPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/calendar/calendar-section/calendar-initial-scroll.js'
);
const appointmentsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/calendar/calendar-section/calendar-appointments.js'
);
const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/calendar/calendar-module.js'
);
const indexPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/calendar/index.php'
);

const initialScrollSrc = fs.readFileSync(initialScrollPath, 'utf8');
const appointmentsSrc = fs.readFileSync(appointmentsPath, 'utf8');
const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const indexSrc = fs.readFileSync(indexPath, 'utf8');

function loadInitialScroll() {
    const sandbox = {
        window: {
            DateUtils: {
                ymd(d) {
                    const year = d.getFullYear();
                    const month = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                },
                parseMysqlDateTime(value) {
                    if (!value) return null;
                    if (value instanceof Date) {
                        return isNaN(value.getTime()) ? null : value;
                    }
                    if (typeof value === 'string') {
                        const d = new Date(value.replace(' ', 'T'));
                        return isNaN(d.getTime()) ? null : d;
                    }
                    return null;
                }
            }
        }
    };
    vm.createContext(sandbox);
    vm.runInContext(initialScrollSrc, sandbox, { filename: 'calendar-initial-scroll.js' });
    return sandbox.window.CalendarInitialScroll;
}

function item(id, fecha, fechaFin, estado, startRow, bloques) {
    return {
        id: id,
        startRow: startRow == null ? 1 : startRow,
        bloquesOcupados: bloques == null ? 2 : bloques,
        slotInicio: ((startRow == null ? 1 : startRow) - 1) * 30,
        cita: {
            id: id,
            fecha: fecha,
            fecha_fin: fechaFin,
            estado: estado,
            duracion: 60
        }
    };
}

describe('calendar initial scroll (Ciclo 3)', () => {
    it('index encola calendar-initial-scroll.js antes de appointments', () => {
        assert.match(indexSrc, /calendar-initial-scroll\.js/);
        assert.ok(
            indexSrc.indexOf('calendar-initial-scroll.js')
                < indexSrc.indexOf('calendar-appointments.js')
        );
    });

    it('generation se incrementa en cada renderTimelineForDate', () => {
        assert.match(moduleSrc, /timelineLoadGeneration\s*\+=\s*1/);
        assert.match(moduleSrc, /autoPosition:\s*autoPosition/);
        assert.match(moduleSrc, /isStale:\s*isStale/);
        const reloadSlice = moduleSrc.slice(
            moduleSrc.indexOf('function recargarTimelineDelDiaActual'),
            moduleSrc.indexOf('function recargarTimelineDelDiaActual') + 500
        );
        assert.match(reloadSlice, /renderTimelineForDate\(fecha\)/);
        assert.doesNotMatch(reloadSlice, /resetScroll:\s*true/);
    });

    it('appointments protege stale antes de render y antes de scroll', () => {
        assert.match(appointmentsSrc, /if \(isStale\(\)\) \{\s*return;\s*\}/);
        assert.match(appointmentsSrc, /scheduleInitialScroll/);
        assert.match(appointmentsSrc, /citasRenderizadasConPosicion/);
        assert.match(appointmentsSrc, /return citaConPos;/);
        assert.match(appointmentsSrc, /return null;/);
        assert.match(
            appointmentsSrc,
            /gridRect\.top\s*-\s*viewportRect\.top\s*\+\s*viewport\.scrollTop/
        );
        assert.match(appointmentsSrc, /aa-standalone/);
    });

    it('hoy: en curso operativa gana sobre futuras', () => {
        const api = loadInitialScroll();
        const now = new Date('2026-08-23T12:00:00');
        const rendered = [
            item(1, '2026-08-23 11:00:00', '2026-08-23 13:00:00', 'confirmed', 23, 4),
            item(2, '2026-08-23 14:00:00', '2026-08-23 15:00:00', 'pending', 29, 2)
        ];
        const selected = api.selectTarget(rendered, '2026-08-23', now);
        assert.equal(selected.item.id, 1);
        assert.equal(selected.alignment, 'start');
    });

    it('hoy: cancelada futura no gana frente a confirmada posterior', () => {
        const api = loadInitialScroll();
        const now = new Date('2026-08-23T10:00:00');
        const rendered = [
            item(1, '2026-08-23 11:00:00', '2026-08-23 12:00:00', 'cancelled', 23, 2),
            item(2, '2026-08-23 13:00:00', '2026-08-23 14:00:00', 'confirmed', 27, 2)
        ];
        const selected = api.selectTarget(rendered, '2026-08-23', now);
        assert.equal(selected.item.id, 2);
        assert.equal(selected.alignment, 'start');
    });

    it('hoy: última concluida cancelada es target', () => {
        const api = loadInitialScroll();
        const now = new Date('2026-08-23T20:00:00');
        const rendered = [
            item(1, '2026-08-23 09:00:00', '2026-08-23 10:00:00', 'confirmed', 19, 2),
            item(2, '2026-08-23 11:00:00', '2026-08-23 12:00:00', 'cancelled', 23, 2)
        ];
        const selected = api.selectTarget(rendered, '2026-08-23', now);
        assert.equal(selected.item.id, 2);
        assert.equal(selected.alignment, 'end');
    });

    it('futuro: solo canceladas → sin target', () => {
        const api = loadInitialScroll();
        const now = new Date('2026-08-23T10:00:00');
        const rendered = [
            item(1, '2026-08-24 09:00:00', '2026-08-24 10:00:00', 'cancelled', 19, 2)
        ];
        const selected = api.selectTarget(rendered, '2026-08-24', now);
        assert.equal(selected, null);
    });

    it('pasado: última card cancelled es target; estados diversos elegibles', () => {
        const api = loadInitialScroll();
        const now = new Date('2026-08-23T10:00:00');
        const rendered = [
            item(1, '2026-08-20 09:00:00', '2026-08-20 10:00:00', 'pending', 19, 2),
            item(2, '2026-08-20 11:00:00', '2026-08-20 12:00:00', 'asistió', 23, 2),
            item(3, '2026-08-20 14:00:00', '2026-08-20 15:00:00', 'cancelled', 29, 2),
            item(4, '2026-08-20 08:00:00', '2026-08-20 08:30:00', null, 17, 1),
            item(5, '2026-08-20 10:00:00', '2026-08-20 10:30:00', 'no asistió', 21, 1)
        ];
        const selected = api.selectTarget(rendered, '2026-08-20', now);
        assert.equal(selected.item.id, 3);
        assert.equal(selected.alignment, 'end');
    });

    it('solo citas renderizadas: preferente ausente no puede ganar', () => {
        const api = loadInitialScroll();
        const now = new Date('2026-08-23T08:00:00');
        // Nearest future would be 09:00 but it was not rendered; only 11:00 is present
        const rendered = [
            item(2, '2026-08-23 11:00:00', '2026-08-23 12:00:00', 'confirmed', 23, 2)
        ];
        const selected = api.selectTarget(rendered, '2026-08-23', now);
        assert.equal(selected.item.id, 2);
    });

    it('computeScrollTop alignStart/end y clamp con gridOffsetTop', () => {
        const api = loadInitialScroll();

        const startScroll = api.computeScrollTop({
            gridOffsetTop: 15,
            rowHeight: 40,
            startRow: 10,
            bloquesOcupados: 2,
            viewportHeight: 400,
            maxScroll: 1500,
            alignment: 'start'
        });
        // startY = 15 + 9*40 = 375; desired = 375 - 40 = 335
        assert.equal(startScroll, 335);

        const endScroll = api.computeScrollTop({
            gridOffsetTop: 15,
            rowHeight: 40,
            startRow: 10,
            bloquesOcupados: 2,
            viewportHeight: 400,
            maxScroll: 1500,
            alignment: 'end'
        });
        // endY = 15 + 11*40 = 455; desired = 455 - 400 = 55
        assert.equal(endScroll, 55);

        const clamped = api.computeScrollTop({
            gridOffsetTop: 0,
            rowHeight: 40,
            startRow: 48,
            bloquesOcupados: 1,
            viewportHeight: 400,
            maxScroll: 100,
            alignment: 'start'
        });
        assert.equal(clamped, 100);

        const floored = api.computeScrollTop({
            gridOffsetTop: 0,
            rowHeight: 40,
            startRow: 1,
            bloquesOcupados: 1,
            viewportHeight: 400,
            maxScroll: 100,
            alignment: 'start'
        });
        assert.equal(floored, 0);
    });

    it('isOperationalTarget solo excluye cancelled (case/trim)', () => {
        const api = loadInitialScroll();
        assert.equal(api.isOperationalTarget({ estado: 'confirmed' }), true);
        assert.equal(api.isOperationalTarget({ estado: null }), true);
        assert.equal(api.isOperationalTarget({ estado: '  ' }), true);
        assert.equal(api.isOperationalTarget({ estado: 'cancelled' }), false);
        assert.equal(api.isOperationalTarget({ estado: ' Cancelled ' }), false);
    });
});
