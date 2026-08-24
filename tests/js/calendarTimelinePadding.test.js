'use strict';

/**
 * Ciclo 1 — padding inferior del lienzo (contrato idempotente).
 *
 * Ejecutar: node --test tests/js/calendarTimelinePadding.test.js
 */

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

const appointmentsPath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/calendar/calendar-section/calendar-appointments.js'
);
const appointmentsSrc = fs.readFileSync(appointmentsPath, 'utf8');

/**
 * Réplica del contrato de applyTimelinePaddingForExpandedCard (sin DOM).
 * @param {number} sectionRectBottom
 * @param {number} currentPaddingBottom
 * @param {number} contentBottom
 * @returns {number}
 */
function computeTimelinePaddingBottom(sectionRectBottom, currentPaddingBottom, contentBottom) {
    const naturalBottomViewport = sectionRectBottom - currentPaddingBottom;
    const overflow = contentBottom - naturalBottomViewport;

    if (overflow > 0) {
        return Math.ceil(overflow) + 2;
    }

    return 0;
}

describe('calendar timeline lienzo padding (Ciclo 1)', () => {
    it('implementación descuenta padding vigente sin reset previo al medir', () => {
        assert.match(appointmentsSrc, /naturalBottomViewport\s*=\s*sectionRect\.bottom\s*-\s*currentPaddingBottom/);
        assert.doesNotMatch(
            appointmentsSrc,
            /applyTimelinePaddingForExpandedCard[\s\S]*?paddingBottom\s*=\s*'0px'[\s\S]*?naturalBottom/
        );
    });

    it('card mediodía dentro del lienzo natural no requiere padding', () => {
        const naturalBottom = 800;
        const contentBottom = 750;
        const padding = computeTimelinePaddingBottom(naturalBottom, 0, contentBottom);
        assert.equal(padding, 0);
    });

    it('card en última franja idempotente: segunda invocación conserva el mismo padding', () => {
        const naturalGridBottom = 400;
        const contentBottom = 450;

        const firstPadding = computeTimelinePaddingBottom(naturalGridBottom, 0, contentBottom);
        assert.equal(firstPadding, 52);

        const sectionBottomWithPadding = naturalGridBottom + firstPadding;
        const secondPadding = computeTimelinePaddingBottom(
            sectionBottomWithPadding,
            firstPadding,
            contentBottom
        );
        assert.equal(secondPadding, firstPadding);
    });

    it('padding acumulativo no crece en invocaciones repetidas', () => {
        const naturalGridBottom = 400;
        const contentBottom = 430;
        let padding = 0;
        let sectionBottom = naturalGridBottom;

        for (let i = 0; i < 5; i++) {
            padding = computeTimelinePaddingBottom(sectionBottom, padding, contentBottom);
            sectionBottom = naturalGridBottom + padding;
        }

        assert.equal(padding, 32);
    });
});
