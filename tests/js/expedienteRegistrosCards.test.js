'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

function createEl(tag) {
    const el = {
        tagName: String(tag).toUpperCase(),
        className: '',
        id: '',
        type: '',
        textContent: '',
        open: false,
        disabled: false,
        children: [],
        attributes: Object.create(null),
        style: {},
        parentNode: null,
        classList: {
            _set: new Set(),
            add: function (c) { this._set.add(c); },
            remove: function (c) { this._set.delete(c); },
            contains: function (c) { return this._set.has(c); }
        },
        setAttribute: function (name, value) {
            this.attributes[name] = String(value);
        },
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(this.attributes, name)
                ? this.attributes[name]
                : null;
        },
        appendChild: function (child) {
            child.parentNode = this;
            this.children.push(child);
            return child;
        },
        removeChild: function (child) {
            this.children = this.children.filter(function (c) { return c !== child; });
            child.parentNode = null;
            return child;
        },
        addEventListener: function (type, handler) {
            this._listeners = this._listeners || {};
            this._listeners[type] = this._listeners[type] || [];
            this._listeners[type].push(handler);
        },
        focus: function () {
            this._focused = true;
        },
        querySelector: function (selector) {
            return findMatch(this, selector);
        },
        querySelectorAll: function (selector) {
            const out = [];
            collectMatches(this, selector, out);
            return out;
        }
    };
    return el;
}

function classMatches(el, className) {
    if (!el || !el.className) {
        return false;
    }
    return String(el.className).split(/\s+/).indexOf(className) !== -1;
}

function matchesSelector(el, selector) {
    if (!el || el.nodeType === 3) {
        return false;
    }
    if (selector === 'time' && el.tagName === 'TIME') {
        return true;
    }
    if (selector === 'details' && el.tagName === 'DETAILS') {
        return true;
    }
    if (selector === 'button' && el.tagName === 'BUTTON') {
        return true;
    }

    // Compound: .class[attr="value"] or .class
    const compound = selector.match(/^(\.[a-zA-Z0-9_-]+)(?:\[([a-zA-Z0-9_-]+)="([^"]*)"\])?$/);
    if (compound) {
        if (!classMatches(el, compound[1].slice(1))) {
            return false;
        }
        if (compound[2]) {
            return el.getAttribute(compound[2]) === compound[3];
        }
        return true;
    }

    if (selector.charAt(0) === '.' && classMatches(el, selector.slice(1))) {
        return true;
    }
    return false;
}

function findMatch(root, selector) {
    const out = [];
    collectMatches(root, selector, out);
    return out[0] || null;
}

function collectMatches(node, selector, out) {
    if (!node) {
        return;
    }
    function visit(el) {
        if (!el || !el.children) {
            return;
        }
        el.children.forEach(function (child) {
            if (child.nodeType === 3) {
                return;
            }
            if (matchesSelector(child, selector)) {
                out.push(child);
            }
            visit(child);
        });
    }
    visit(node);
}

function loadModule() {
    const sandboxWindow = { AAAdmin: {}, AA_CLIENTS_DATA: {}, AA_CLIENTS_NONCES: {} };
    const document = {
        createElement: createEl,
        createTextNode: function (text) {
            return { nodeType: 3, textContent: String(text), children: [] };
        },
        getElementById: function () { return null; },
        contains: function () { return true; }
    };

    sandboxWindow.window = sandboxWindow;
    sandboxWindow.document = document;

    const context = {
        window: sandboxWindow,
        document: document,
        console: console,
        setTimeout: setTimeout,
        MutationObserver: undefined
    };

    vm.runInNewContext(moduleSrc, context, { filename: modulePath });

    return {
        api: sandboxWindow.AAAdmin.ExpedienteRegistros,
        document: document
    };
}

describe('expediente-registros cards (MC2b/MC3)', () => {
    let api;
    let document;

    beforeEach(() => {
        const loaded = loadModule();
        api = loaded.api;
        document = loaded.document;
    });

    it('expone hooks de prueba y helpers de fecha', () => {
        assert.ok(api);
        assert.ok(api.__test__);
        assert.equal(api.__test__.toDatetimeAttr('2026-07-30 15:04:05'), '2026-07-30T15:04:05');
        assert.equal(api.__test__.toDatetimeAttr('2026-07-30 15:04'), '2026-07-30T15:04:00');
        assert.equal(api.__test__.formatRecordedAt('2026-07-30 15:04:05'), '30/07/2026 15:04');
    });

    it('createRecordDetails usa DOM/textContent con título, folio, time y Editar', () => {
        const details = api.__test__.createRecordDetails({
            id: 12,
            title: 'Consulta',
            body: 'Línea 1\nLínea 2',
            recorded_at: '2026-07-30 09:30:00'
        });

        assert.equal(details.tagName, 'DETAILS');
        assert.equal(details.className, 'aa-expediente-registro');
        assert.equal(details.getAttribute('data-registro-id'), '12');
        assert.equal(details.open, false);

        const title = details.querySelector('.aa-expediente-registro-title');
        const folio = details.querySelector('.aa-expediente-registro-folio');
        const timeEl = details.querySelector('time');
        const body = details.querySelector('.aa-expediente-registro-body');
        const actions = details.querySelector('.aa-expediente-registro-actions');
        const editBtn = details.querySelector('.aa-expediente-btn-editar');

        assert.equal(title.textContent, 'Consulta');
        assert.equal(folio.textContent, 'Folio #12');
        assert.equal(timeEl.tagName, 'TIME');
        assert.equal(timeEl.getAttribute('datetime'), '2026-07-30T09:30:00');
        assert.equal(timeEl.textContent, '30/07/2026 09:30');
        assert.equal(body.textContent, 'Línea 1\nLínea 2');
        assert.ok(actions);
        assert.equal(actions.children.length, 2);
        assert.ok(editBtn);
        assert.equal(editBtn.textContent, 'Editar');
        assert.equal(editBtn.getAttribute('data-registro-id'), '12');
        assert.equal(editBtn.type, 'button');
        const deleteBtn = details.querySelector('.aa-expediente-btn-eliminar');
        assert.ok(deleteBtn);
        assert.equal(deleteBtn.textContent, 'Eliminar');
        assert.equal(deleteBtn.getAttribute('data-registro-id'), '12');
        assert.equal(deleteBtn.type, 'button');
        assert.equal(details.open, false);
    });

    it('carga/lista sin expandId deja todos colapsados', () => {
        const root = createEl('div');
        api.__test__.setState({
            clientId: 1,
            recordsRoot: root,
            records: [
                { id: 2, title: 'B', body: 'b', recorded_at: '2026-07-30 12:00:00' },
                { id: 1, title: 'A', body: 'a', recorded_at: '2026-07-29 12:00:00' }
            ]
        });

        api.__test__.renderRecordsList();

        const cards = root.querySelectorAll('details');
        assert.equal(cards.length, 2);
        assert.equal(cards[0].open, false);
        assert.equal(cards[1].open, false);
        assert.equal(cards[0].getAttribute('data-registro-id'), '2');
    });

    it('prependRecord inserta primero y abre solo el nuevo (expandId)', () => {
        const root = createEl('div');
        api.__test__.setState({
            clientId: 1,
            recordsRoot: root,
            records: [
                { id: 1, title: 'Viejo', body: 'v', recorded_at: '2026-07-29 10:00:00' }
            ]
        });

        api.__test__.prependRecord({
            id: 9,
            title: 'Nuevo',
            body: 'n',
            recorded_at: '2026-07-30 18:00:00'
        });

        const cards = root.querySelectorAll('details');
        assert.equal(cards.length, 2);
        assert.equal(cards[0].getAttribute('data-registro-id'), '9');
        assert.equal(cards[0].open, true);
        assert.equal(cards[1].getAttribute('data-registro-id'), '1');
        assert.equal(cards[1].open, false);

        const title = cards[0].querySelector('.aa-expediente-registro-title');
        assert.equal(title.textContent, 'Nuevo');
    });

    it('replaceRecord actualiza por id sin duplicar ni reordenar por updated_at', () => {
        const root = createEl('div');
        api.__test__.setState({
            clientId: 1,
            recordsRoot: root,
            records: [
                { id: 2, title: 'B', body: 'b', recorded_at: '2026-07-30 12:00:00', updated_at: null },
                { id: 1, title: 'A', body: 'a', recorded_at: '2026-07-29 10:00:00', updated_at: null }
            ]
        });

        api.__test__.replaceRecord({
            id: 1,
            title: 'A editado',
            body: 'a2',
            recorded_at: '2026-07-29 10:00:00',
            updated_at: '2026-07-30 20:00:00'
        });

        const state = api.__test__.getState();
        assert.equal(state.records.length, 2);
        assert.equal(state.records[0].id, 2);
        assert.equal(state.records[1].id, 1);
        assert.equal(state.records[1].title, 'A editado');
        assert.equal(state.records[1].body, 'a2');

        const cards = root.querySelectorAll('details');
        assert.equal(cards.length, 2);
        assert.equal(cards[0].getAttribute('data-registro-id'), '2');
        assert.equal(cards[0].open, false);
        assert.equal(cards[1].getAttribute('data-registro-id'), '1');
        assert.equal(cards[1].open, true);
        assert.equal(cards[1].querySelector('.aa-expediente-registro-title').textContent, 'A editado');
        assert.equal(cards[1].querySelector('.aa-expediente-registro-body').textContent, 'a2');
        // Header sigue mostrando recorded_at, no updated_at
        assert.equal(cards[1].querySelector('time').textContent, '29/07/2026 10:00');
    });

    it('estado vacío conserva mensaje sin botón Nuevo registro en actions', () => {
        const root = createEl('div');
        const actions = createEl('div');
        api.__test__.setState({
            clientId: 1,
            recordsRoot: root,
            actionsRoot: actions,
            records: []
        });
        api.__test__.renderRecordsList();

        assert.equal(actions.querySelector('.aa-expediente-nuevo-registro-btn'), null);
        assert.doesNotMatch(moduleSrc, /aa-expediente-nuevo-registro-btn/);
        assert.equal(root.querySelector('.aa-expediente-registros-toolbar'), null);
        const empty = root.querySelector('.aa-expediente-registros-empty');
        assert.ok(empty);
        assert.match(empty.textContent, /Aún no hay registros/);
        assert.equal(root.querySelectorAll('details').length, 0);
    });

    it('fuente no usa data-aa-card ni innerHTML para datos de registro', () => {
        assert.equal(moduleSrc.includes('data-aa-card'), false);
        assert.equal(moduleSrc.includes('aa-card-overlay'), false);
        assert.equal(moduleSrc.includes('aa-appointment-'), false);
        assert.match(moduleSrc, /expandId/);
        assert.match(moduleSrc, /Folio #/);
        assert.match(moduleSrc, /createElement\('time'\)/);
        assert.match(moduleSrc, /aa-expediente-registro-actions/);
        assert.match(moduleSrc, /aa-expediente-btn-editar/);
        assert.match(moduleSrc, /function replaceRecord/);
        assert.match(moduleSrc, /updateRegistro/);
        assert.match(moduleSrc, /titleSpan\.textContent/);
        assert.match(moduleSrc, /body\.textContent/);
        assert.doesNotMatch(moduleSrc, /titleSpan\.innerHTML|folioSpan\.innerHTML|body\.innerHTML\s*=/);
        assert.doesNotMatch(moduleSrc, /Editado el/);
    });
});
