/**
 * Medición del extensor (geometría simulada, sin layout de navegador).
 * Ejecutar: scripts/safe-node-test.sh tests/js/canonical-shell-records-compact-measure.test.js
 */
'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

function yContent(el, scrollport) {
    const er = el.getBoundingClientRect();
    const sr = scrollport.getBoundingClientRect();
    return (er.top - sr.top) + scrollport.scrollTop;
}

function computeExtra(lastLi, panel, scrollport) {
    const naturalBottom = yContent(lastLi, scrollport) + lastLi.offsetHeight;
    const overlayBottom = yContent(panel, scrollport) + panel.offsetHeight;
    return Math.max(0, Math.round(overlayBottom - naturalBottom));
}

function mockEl(topInViewport, height, scrollTopOfPort) {
    return {
        offsetHeight: height,
        getBoundingClientRect() {
            return { top: topInViewport, height };
        },
        // unused; scrollport carries scrollTop
        _scrollTop: scrollTopOfPort
    };
}

describe('canonical-shell records compact measure', () => {
    it('extra is zero when overlay ends above last row bottom', () => {
        const scrollport = {
            scrollTop: 40,
            getBoundingClientRect() {
                return { top: 100 };
            }
        };
        // content y = (viewportTop - 100) + 40
        const lastLi = mockEl(200, 50); // yContent = 140, bottom = 190
        const panel = mockEl(150, 30); // yContent = 90, bottom = 120
        assert.equal(computeExtra(lastLi, panel, scrollport), 0);
    });

    it('extra equals overflow past natural bottom', () => {
        const scrollport = {
            scrollTop: 0,
            getBoundingClientRect() {
                return { top: 0 };
            }
        };
        const lastLi = mockEl(100, 40); // bottom 140
        const panel = mockEl(80, 100); // bottom 180
        assert.equal(computeExtra(lastLi, panel, scrollport), 40);
    });

    it('scrollTop cancels out of comparable coordinates', () => {
        const scrollportA = {
            scrollTop: 0,
            getBoundingClientRect() {
                return { top: 50 };
            }
        };
        const scrollportB = {
            scrollTop: 80,
            getBoundingClientRect() {
                return { top: 50 };
            }
        };
        // Same content positions relative to content: last at y=200 h=40, panel at y=180 h=120
        const lastA = mockEl(250, 40); // y = 200
        const panelA = mockEl(230, 120); // y = 180, bottom 300 → extra 60
        const lastB = mockEl(250 - 80, 40); // viewport shifted by scroll
        const panelB = mockEl(230 - 80, 120);
        assert.equal(computeExtra(lastA, panelA, scrollportA), 60);
        assert.equal(computeExtra(lastB, panelB, scrollportB), 60);
    });

    it('double compute is stable (no accumulation)', () => {
        const scrollport = {
            scrollTop: 10,
            getBoundingClientRect() {
                return { top: 20 };
            }
        };
        const lastLi = mockEl(100, 50);
        const panel = mockEl(90, 200);
        const a = computeExtra(lastLi, panel, scrollport);
        const b = computeExtra(lastLi, panel, scrollport);
        assert.equal(a, b);
        assert.ok(a > 0);
    });
});
