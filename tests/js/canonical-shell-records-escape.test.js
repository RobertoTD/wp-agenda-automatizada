/**
 * Escape coordination: form + compact handlers, both orders, one layer per key.
 * Ejecutar: scripts/safe-node-test.sh tests/js/canonical-shell-records-escape.test.js
 */
'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

function createKeyEvent() {
    return {
        key: 'Escape',
        defaultPrevented: false,
        preventDefault() {
            this.defaultPrevented = true;
        }
    };
}

function makeFormHandler(state) {
    return function formEscape(e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (e.defaultPrevented) {
            return;
        }
        if (state.deleteOpen) {
            if (!state.deleteBlocked) {
                state.deleteOpen = false;
                state.closed.push('delete');
            }
            e.preventDefault();
            return;
        }
        if (state.modalOpen) {
            state.modalOpen = false;
            state.closed.push('modal');
            e.preventDefault();
        }
    };
}

function makeCompactHandler(state) {
    return function compactEscape(e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (e.defaultPrevented) {
            return;
        }
        if (state.deleteOpen || state.modalOpen) {
            return;
        }
        if (state.menuOpen) {
            state.menuOpen = false;
            state.closed.push('menu');
            e.preventDefault();
            return;
        }
        if (state.recordOpen) {
            state.recordOpen = false;
            state.closed.push('record');
            e.preventDefault();
        }
    };
}

function runBothOrders(initial) {
    const results = {};

    for (const order of ['form-first', 'compact-first']) {
        const state = {
            deleteOpen: !!initial.deleteOpen,
            deleteBlocked: !!initial.deleteBlocked,
            modalOpen: !!initial.modalOpen,
            menuOpen: !!initial.menuOpen,
            recordOpen: !!initial.recordOpen,
            closed: []
        };
        const form = makeFormHandler(state);
        const compact = makeCompactHandler(state);
        const e = createKeyEvent();
        if (order === 'form-first') {
            form(e);
            compact(e);
        } else {
            compact(e);
            form(e);
        }
        results[order] = {
            closed: state.closed.slice(),
            deleteOpen: state.deleteOpen,
            modalOpen: state.modalOpen,
            menuOpen: state.menuOpen,
            recordOpen: state.recordOpen,
            defaultPrevented: e.defaultPrevented
        };
    }
    return results;
}

describe('canonical-shell records Escape agreement', () => {
    it('modal open: both orders close only modal', () => {
        const r = runBothOrders({ modalOpen: true, recordOpen: true, menuOpen: true });
        for (const order of ['form-first', 'compact-first']) {
            assert.deepEqual(r[order].closed, ['modal']);
            assert.equal(r[order].modalOpen, false);
            assert.equal(r[order].recordOpen, true);
            assert.equal(r[order].menuOpen, true);
            assert.equal(r[order].defaultPrevented, true);
        }
    });

    it('menu only: both orders close only menu', () => {
        const r = runBothOrders({ menuOpen: true, recordOpen: true });
        for (const order of ['form-first', 'compact-first']) {
            assert.deepEqual(r[order].closed, ['menu']);
            assert.equal(r[order].menuOpen, false);
            assert.equal(r[order].recordOpen, true);
            assert.equal(r[order].defaultPrevented, true);
        }
    });

    it('record only: both orders close only record', () => {
        const r = runBothOrders({ recordOpen: true });
        for (const order of ['form-first', 'compact-first']) {
            assert.deepEqual(r[order].closed, ['record']);
            assert.equal(r[order].recordOpen, false);
            assert.equal(r[order].defaultPrevented, true);
        }
    });

    it('nothing open: neither order consumes Escape', () => {
        const r = runBothOrders({});
        for (const order of ['form-first', 'compact-first']) {
            assert.deepEqual(r[order].closed, []);
            assert.equal(r[order].defaultPrevented, false);
        }
    });

    it('delete modal blocked still prevents compact from closing record', () => {
        const r = runBothOrders({
            deleteOpen: true,
            deleteBlocked: true,
            recordOpen: true
        });
        for (const order of ['form-first', 'compact-first']) {
            assert.ok(r[order].closed.length === 0 || r[order].closed[0] !== 'record');
            assert.equal(r[order].recordOpen, true);
            assert.equal(r[order].deleteOpen, true);
            assert.equal(r[order].defaultPrevented, true);
        }
    });
});
