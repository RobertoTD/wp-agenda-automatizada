/**
 * Calendar Initial Scroll - Pure selection + scroll math for Ciclo 3
 *
 * Design System: See /docs/DESIGN_BRIEF.md
 * - No DOM side effects in select/compute helpers
 * - Operates only on already-rendered appointment rows
 */

(function () {
    'use strict';

    const ROW_HEIGHT = 40;
    const CONTEXT_ROWS = 1;

    /**
     * @param {Object} cita
     * @returns {string}
     */
    function normalizeEstado(cita) {
        if (!cita || cita.estado == null) {
            return 'pending';
        }
        const trimmed = String(cita.estado).trim();
        return trimmed === '' ? 'pending' : trimmed;
    }

    /**
     * Operational target filter (in-progress / upcoming / future days only).
     * @param {Object} cita
     * @returns {boolean}
     */
    function isOperationalTarget(cita) {
        return normalizeEstado(cita).toLowerCase() !== 'cancelled';
    }

    /**
     * @param {Object} cita
     * @returns {{ start: Date, end: Date }|null}
     */
    function getAppointmentInterval(cita) {
        if (!cita || !cita.fecha || !window.DateUtils || typeof window.DateUtils.parseMysqlDateTime !== 'function') {
            return null;
        }

        const start = window.DateUtils.parseMysqlDateTime(cita.fecha);
        if (!start) {
            return null;
        }

        let end = null;
        if (cita.fecha_fin) {
            end = window.DateUtils.parseMysqlDateTime(cita.fecha_fin);
        }
        if (!end) {
            const dur = parseInt(cita.duracion, 10) || 60;
            end = new Date(start.getTime() + dur * 60000);
        }

        return { start: start, end: end };
    }

    /**
     * @param {Object} a
     * @param {Object} b
     * @returns {number}
     */
    function compareIdAsc(a, b) {
        const idA = Number(a.id);
        const idB = Number(b.id);
        if (!isNaN(idA) && !isNaN(idB) && idA !== idB) {
            return idA - idB;
        }
        return String(a.id).localeCompare(String(b.id));
    }

    /**
     * Select scroll target from rendered appointments only.
     *
     * @param {Array} citasRenderizadasConPosicion
     * @param {string} fechaStr YYYY-MM-DD
     * @param {Date} [now]
     * @returns {{ item: Object, alignment: 'start'|'end' }|null}
     */
    function selectTarget(citasRenderizadasConPosicion, fechaStr, now) {
        const rendered = Array.isArray(citasRenderizadasConPosicion)
            ? citasRenderizadasConPosicion
            : [];
        if (!fechaStr || !/^\d{4}-\d{2}-\d{2}$/.test(fechaStr)) {
            return null;
        }

        const referenceNow = (now && typeof now.getTime === 'function' && !isNaN(now.getTime()))
            ? now
            : new Date();
        const todayYmd = window.DateUtils && typeof window.DateUtils.ymd === 'function'
            ? window.DateUtils.ymd(referenceNow)
            : null;
        if (!todayYmd) {
            return null;
        }

        const withIntervals = [];
        for (let i = 0; i < rendered.length; i++) {
            const item = rendered[i];
            const interval = getAppointmentInterval(item.cita);
            if (!interval) {
                continue;
            }
            withIntervals.push({
                item: item,
                start: interval.start,
                end: interval.end
            });
        }

        if (withIntervals.length === 0) {
            return null;
        }

        function pickEarliestStart(list) {
            list.sort(function (a, b) {
                const t = a.start.getTime() - b.start.getTime();
                if (t !== 0) {
                    return t;
                }
                return compareIdAsc(a.item, b.item);
            });
            return list[0];
        }

        function pickLatestEnd(list) {
            list.sort(function (a, b) {
                const t = b.end.getTime() - a.end.getTime();
                if (t !== 0) {
                    return t;
                }
                const startCmp = b.start.getTime() - a.start.getTime();
                if (startCmp !== 0) {
                    return startCmp;
                }
                return compareIdAsc(a.item, b.item);
            });
            return list[0];
        }

        if (fechaStr === todayYmd) {
            const inProgress = withIntervals.filter(function (row) {
                return row.start.getTime() <= referenceNow.getTime()
                    && referenceNow.getTime() < row.end.getTime()
                    && isOperationalTarget(row.item.cita);
            });
            if (inProgress.length > 0) {
                return { item: pickEarliestStart(inProgress).item, alignment: 'start' };
            }

            const upcoming = withIntervals.filter(function (row) {
                return row.start.getTime() > referenceNow.getTime()
                    && isOperationalTarget(row.item.cita);
            });
            if (upcoming.length > 0) {
                return { item: pickEarliestStart(upcoming).item, alignment: 'start' };
            }

            const past = withIntervals.filter(function (row) {
                return row.end.getTime() <= referenceNow.getTime();
            });
            if (past.length > 0) {
                return { item: pickLatestEnd(past).item, alignment: 'end' };
            }

            return null;
        }

        if (fechaStr > todayYmd) {
            const operational = withIntervals.filter(function (row) {
                return isOperationalTarget(row.item.cita);
            });
            if (operational.length === 0) {
                return null;
            }
            return { item: pickEarliestStart(operational).item, alignment: 'start' };
        }

        // Past day: all rendered cards; no now comparison; no estado filter
        return { item: pickLatestEnd(withIntervals).item, alignment: 'end' };
    }

    /**
     * Pure scrollTop computation in viewport scroll-content coordinates.
     *
     * @param {Object} params
     * @param {number} params.gridOffsetTop
     * @param {number} params.rowHeight
     * @param {number} params.startRow
     * @param {number} params.bloquesOcupados
     * @param {number} params.viewportHeight
     * @param {number} params.maxScroll
     * @param {'start'|'end'} params.alignment
     * @returns {number}
     */
    function computeScrollTop(params) {
        const gridOffsetTop = Number(params.gridOffsetTop) || 0;
        const rowHeight = Number(params.rowHeight) > 0 ? Number(params.rowHeight) : ROW_HEIGHT;
        const startRow = Number(params.startRow) || 1;
        const bloques = Math.max(1, Number(params.bloquesOcupados) || 1);
        const viewportHeight = Number(params.viewportHeight) || 0;
        const maxScroll = Math.max(0, Number(params.maxScroll) || 0);
        const alignment = params.alignment === 'end' ? 'end' : 'start';

        const startY = gridOffsetTop + (startRow - 1) * rowHeight;
        const endY = gridOffsetTop + (startRow - 1 + bloques) * rowHeight;

        let desired;
        if (alignment === 'end') {
            desired = endY - viewportHeight;
        } else {
            desired = startY - CONTEXT_ROWS * rowHeight;
        }

        return Math.min(maxScroll, Math.max(0, desired));
    }

    window.CalendarInitialScroll = {
        ROW_HEIGHT: ROW_HEIGHT,
        normalizeEstado: normalizeEstado,
        isOperationalTarget: isOperationalTarget,
        getAppointmentInterval: getAppointmentInterval,
        selectTarget: selectTarget,
        computeScrollTop: computeScrollTop
    };
})();
