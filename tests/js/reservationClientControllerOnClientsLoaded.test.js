'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const reservationClientPath = path.join(
    __dirname,
    '../../assets/js/controllers/reservationClientController.js'
);
const reservationClientSrc = fs.readFileSync(reservationClientPath, 'utf8');

function makeSelectMock() {
    var select = {
        _innerHTML: '',
        disabled: false,
        value: '',
        selectedIndex: 0,
        options: [],
        appendChild: function(option) {
            this.options.push(option);
        },
        insertBefore: function(newOpt, refOpt) {
            var idx = this.options.indexOf(refOpt);
            if (idx === -1) {
                this.options.push(newOpt);
            } else {
                this.options.splice(idx, 0, newOpt);
            }
        },
        addEventListener: function() {},
        removeEventListener: function() {}
    };

    Object.defineProperty(select, 'innerHTML', {
        get: function() { return this._innerHTML; },
        set: function(v) { this._innerHTML = v; this.options = []; }
    });

    Object.defineProperty(select, 'firstChild', {
        get: function() { return this.options[0] || null; }
    });

    return select;
}

function loadReservationClientController(options) {
    var opts = options || {};
    var loadedMeta = [];
    var selectMock = makeSelectMock();

    var context = {
        window: {},
        document: {
            getElementById: function(id) {
                if (id === 'aa-fastappointment-client-search') {
                    return { value: opts.searchValue || '', addEventListener: function() {}, removeEventListener: function() {} };
                }
                if (id === 'aa-fastappointment-client') {
                    return selectMock;
                }
                return null;
            },
            createElement: function(tag) {
                return {
                    tagName: tag.toUpperCase(),
                    value: '',
                    textContent: '',
                    disabled: false,
                    dataset: {}
                };
            },
            addEventListener: function() {},
            removeEventListener: function() {}
        },
        console: { log: function() {}, warn: function() {}, error: function() {} },
        fetch: opts.fetchImpl || function() {
            return Promise.resolve({
                json: function() {
                    return Promise.resolve({
                        success: true,
                        data: {
                            clients: opts.clients || [],
                            total: typeof opts.total === 'number' ? opts.total : (opts.clients || []).length
                        }
                    });
                }
            });
        },
        FormData: function() {
            this._fields = {};
            this.append = function(key, value) {
                this._fields[key] = value;
            };
        },
        wpaa_vars: { nonce_search_clientes: 'test-nonce' }
    };

    context.window = context;
    context.window.ajaxurl = '/wp-admin/admin-ajax.php';

    vm.runInNewContext(reservationClientSrc, context, { filename: reservationClientPath });

    context.window.ReservationClientController.init({
        searchInputId: 'aa-fastappointment-client-search',
        selectId: 'aa-fastappointment-client',
        onClientsLoaded: function(meta) {
            loadedMeta.push(meta);
        }
    });

    return {
        loadedMeta: loadedMeta,
        selectMock: selectMock
    };
}

describe('ReservationClientController onClientsLoaded B2a', () => {
    it('expone metadata { clients, total, query } sin reglas de tutorial', async () => {
        var env = loadReservationClientController({
            clients: [{ id: 1, nombre: 'Uno', telefono: '1', correo: '' }],
            total: 1
        });

        await new Promise(function(resolve) {
            setImmediate(resolve);
        });

        assert.equal(env.loadedMeta.length, 1);
        assert.equal(env.loadedMeta[0].total, 1);
        assert.equal(env.loadedMeta[0].query, '');
        assert.equal(env.loadedMeta[0].clients.length, 1);
        assert.deepEqual(Object.assign({}, env.loadedMeta[0].clients[0]), {
            id: 1,
            nombre: 'Uno',
            telefono: '1',
            correo: ''
        });
    });
});

describe('ReservationClientController placeholder text', () => {
    it('query vac\u00edo muestra "-- Selecciona un cliente --" y selectedIndex === 0', async () => {
        var env = loadReservationClientController({
            clients: [
                { id: 1, nombre: 'A', telefono: '1', correo: '' },
                { id: 2, nombre: 'B', telefono: '2', correo: '' }
            ]
        });

        await new Promise(function(r) { setImmediate(r); });

        var first = env.selectMock.options[0];
        assert.equal(first.value, '');
        assert.equal(first.textContent, '-- Selecciona un cliente --');
        assert.equal(env.selectMock.selectedIndex, 0);
        assert.equal(env.selectMock.options.length, 3);
    });

    it('varios resultados con query muestra "N resultados \u2014 selecciona uno"', async () => {
        var env = loadReservationClientController({
            fetchImpl: function() {
                return Promise.resolve({
                    json: function() {
                        return Promise.resolve({
                            success: true,
                            data: {
                                clients: [
                                    { id: 1, nombre: 'Rob A', telefono: '1', correo: '' },
                                    { id: 2, nombre: 'Rob B', telefono: '2', correo: '' },
                                    { id: 3, nombre: 'Rob C', telefono: '3', correo: '' }
                                ],
                                total: 3
                            }
                        });
                    }
                });
            }
        });

        await new Promise(function(r) { setImmediate(r); });

        var searchInput = { value: 'Rob', _listeners: {} };
        searchInput.addEventListener = function(evt, fn) { this._listeners[evt] = fn; };
        searchInput.removeEventListener = function() {};

        var selectMockForSearch = makeSelectMock();

        var context2 = {
            window: {},
            document: {
                getElementById: function(id) {
                    if (id === 'aa-fastappointment-client-search') return searchInput;
                    if (id === 'aa-fastappointment-client') return selectMockForSearch;
                    return null;
                },
                createElement: function(tag) {
                    return { tagName: tag.toUpperCase(), value: '', textContent: '', disabled: false, dataset: {} };
                },
                addEventListener: function() {},
                removeEventListener: function() {}
            },
            console: { log: function() {}, warn: function() {}, error: function() {} },
            fetch: function() {
                return Promise.resolve({
                    json: function() {
                        return Promise.resolve({
                            success: true,
                            data: {
                                clients: [
                                    { id: 1, nombre: 'Rob A', telefono: '1', correo: '' },
                                    { id: 2, nombre: 'Rob B', telefono: '2', correo: '' },
                                    { id: 3, nombre: 'Rob C', telefono: '3', correo: '' }
                                ],
                                total: 3
                            }
                        });
                    }
                });
            },
            FormData: function() { this._fields = {}; this.append = function(k, v) { this._fields[k] = v; }; },
            wpaa_vars: { nonce_search_clientes: 'n' },
            setTimeout: function(fn) { fn(); return 1; },
            clearTimeout: function() {}
        };
        context2.window = context2;
        context2.window.ajaxurl = '/test';

        vm.runInNewContext(reservationClientSrc, context2, { filename: reservationClientPath });

        context2.window.ReservationClientController.init({
            searchInputId: 'aa-fastappointment-client-search',
            selectId: 'aa-fastappointment-client'
        });

        await new Promise(function(r) { setImmediate(r); });

        searchInput._listeners.input.call(searchInput);

        await new Promise(function(r) { setImmediate(r); });

        var first = selectMockForSearch.options[0];
        assert.equal(first.value, '');
        assert.equal(first.textContent, '3 resultados \u2014 selecciona uno');
    });

    it('un resultado con query muestra "1 resultado \u2014 selecci\u00f3nalo"', async () => {
        var selectMock = makeSelectMock();

        var context2 = {
            window: {},
            document: {
                getElementById: function(id) {
                    if (id === 'search') return { value: 'Mar', addEventListener: function() {}, removeEventListener: function() {}, _listeners: {} };
                    if (id === 'sel') return selectMock;
                    return null;
                },
                createElement: function(tag) {
                    return { tagName: tag.toUpperCase(), value: '', textContent: '', disabled: false, dataset: {} };
                },
                addEventListener: function() {},
                removeEventListener: function() {}
            },
            console: { log: function() {}, warn: function() {}, error: function() {} },
            fetch: function() {
                return Promise.resolve({
                    json: function() {
                        return Promise.resolve({
                            success: true,
                            data: {
                                clients: [{ id: 5, nombre: 'Mar\u00eda', telefono: '55', correo: '' }],
                                total: 1
                            }
                        });
                    }
                });
            },
            FormData: function() { this._fields = {}; this.append = function(k, v) { this._fields[k] = v; }; },
            wpaa_vars: { nonce_search_clientes: 'n' }
        };
        context2.window = context2;
        context2.window.ajaxurl = '/test';

        vm.runInNewContext(reservationClientSrc, context2, { filename: reservationClientPath });

        context2.window.ReservationClientController.init({
            searchInputId: 'search',
            selectId: 'sel'
        });

        await new Promise(function(r) { setImmediate(r); });

        var first = selectMock.options[0];
        assert.equal(first.value, '');
        assert.equal(first.textContent, '-- Selecciona un cliente --');
    });

    it('cero resultados con query muestra "Sin clientes encontrados"', async () => {
        var selectMock = makeSelectMock();

        var searchInput = { value: 'zzz', addEventListener: function() {}, removeEventListener: function() {}, _listeners: {} };
        searchInput.addEventListener = function(evt, fn) { this._listeners[evt] = fn; };

        var context2 = {
            window: {},
            document: {
                getElementById: function(id) {
                    if (id === 's') return searchInput;
                    if (id === 'c') return selectMock;
                    return null;
                },
                createElement: function(tag) {
                    return { tagName: tag.toUpperCase(), value: '', textContent: '', disabled: false, dataset: {} };
                },
                addEventListener: function() {},
                removeEventListener: function() {}
            },
            console: { log: function() {}, warn: function() {}, error: function() {} },
            fetch: function() {
                return Promise.resolve({
                    json: function() {
                        return Promise.resolve({ success: true, data: { clients: [], total: 0 } });
                    }
                });
            },
            FormData: function() { this._fields = {}; this.append = function(k, v) { this._fields[k] = v; }; },
            wpaa_vars: { nonce_search_clientes: 'n' },
            setTimeout: function(fn) { fn(); return 1; },
            clearTimeout: function() {}
        };
        context2.window = context2;
        context2.window.ajaxurl = '/test';

        vm.runInNewContext(reservationClientSrc, context2, { filename: reservationClientPath });

        context2.window.ReservationClientController.init({
            searchInputId: 's',
            selectId: 'c'
        });

        await new Promise(function(r) { setImmediate(r); });

        searchInput._listeners.input.call(searchInput);

        await new Promise(function(r) { setImmediate(r); });

        var first = selectMock.options[0];
        assert.equal(first.value, '');
        assert.equal(first.textContent, 'Sin clientes encontrados');
    });

    it('"Buscando clientes\u2026" no fuerza selectedIndex al placeholder', async () => {
        var selectMock = makeSelectMock();
        var fetchResolve;

        var context2 = {
            window: {},
            document: {
                getElementById: function(id) {
                    if (id === 's') return { value: '', addEventListener: function() {}, removeEventListener: function() {} };
                    if (id === 'c') return selectMock;
                    return null;
                },
                createElement: function(tag) {
                    return { tagName: tag.toUpperCase(), value: '', textContent: '', disabled: false, dataset: {} };
                },
                addEventListener: function() {},
                removeEventListener: function() {}
            },
            console: { log: function() {}, warn: function() {}, error: function() {} },
            fetch: function() {
                return new Promise(function(resolve) { fetchResolve = resolve; });
            },
            FormData: function() { this._fields = {}; this.append = function(k, v) { this._fields[k] = v; }; },
            wpaa_vars: { nonce_search_clientes: 'n' }
        };
        context2.window = context2;
        context2.window.ajaxurl = '/test';

        vm.runInNewContext(reservationClientSrc, context2, { filename: reservationClientPath });

        selectMock.options = [
            { value: '', textContent: '-- Selecciona un cliente --' },
            { value: '7', textContent: 'Test (55)' }
        ];
        selectMock.value = '7';
        selectMock.selectedIndex = 1;

        context2.window.ReservationClientController.init({
            searchInputId: 's',
            selectId: 'c'
        });

        assert.equal(selectMock.options[0].textContent, 'Buscando clientes\u2026');
        assert.equal(selectMock.selectedIndex, 1, 'selectedIndex must not be forced to 0 during loading');
    });

    it('sin selecci\u00f3n previa el placeholder queda seleccionado, no el primer cliente', async () => {
        var env = loadReservationClientController({
            clients: [
                { id: 10, nombre: 'Carlos', telefono: '100', correo: '' },
                { id: 20, nombre: 'Diana', telefono: '200', correo: '' }
            ]
        });

        await new Promise(function(r) { setImmediate(r); });

        assert.equal(env.selectMock.selectedIndex, 0);
        assert.equal(env.selectMock.options[0].value, '');
        assert.equal(env.selectMock.options[1].value, '10');
    });

    it('preserveSelection conserva cliente v\u00e1lido tras b\u00fasqueda', async () => {
        var selectMock = makeSelectMock();
        var searchInput = { value: 'Car', _listeners: {} };
        searchInput.addEventListener = function(evt, fn) { this._listeners[evt] = fn; };
        searchInput.removeEventListener = function() {};

        var callCount = 0;
        var context2 = {
            window: {},
            document: {
                getElementById: function(id) {
                    if (id === 'si') return searchInput;
                    if (id === 'cs') return selectMock;
                    return null;
                },
                createElement: function(tag) {
                    return { tagName: tag.toUpperCase(), value: '', textContent: '', disabled: false, dataset: {} };
                },
                addEventListener: function() {},
                removeEventListener: function() {}
            },
            console: { log: function() {}, warn: function() {}, error: function() {} },
            fetch: function() {
                callCount++;
                var clients = callCount === 1
                    ? [
                        { id: 10, nombre: 'Carlos', telefono: '100', correo: '' },
                        { id: 20, nombre: 'Diana', telefono: '200', correo: '' }
                      ]
                    : [{ id: 10, nombre: 'Carlos', telefono: '100', correo: '' }];
                return Promise.resolve({
                    json: function() {
                        return Promise.resolve({ success: true, data: { clients: clients, total: clients.length } });
                    }
                });
            },
            FormData: function() { this._fields = {}; this.append = function(k, v) { this._fields[k] = v; }; },
            wpaa_vars: { nonce_search_clientes: 'n' },
            setTimeout: function(fn) { fn(); return 1; },
            clearTimeout: function() {}
        };
        context2.window = context2;
        context2.window.ajaxurl = '/test';

        vm.runInNewContext(reservationClientSrc, context2, { filename: reservationClientPath });

        context2.window.ReservationClientController.init({
            searchInputId: 'si',
            selectId: 'cs'
        });

        await new Promise(function(r) { setImmediate(r); });

        selectMock.value = '10';
        selectMock.selectedIndex = 1;

        searchInput._listeners.input.call(searchInput);

        await new Promise(function(r) { setImmediate(r); });

        assert.equal(selectMock.value, '10', 'preserved client must stay selected');
    });

    it('un resultado no autoselecciona el cliente', async () => {
        var selectMock = makeSelectMock();
        var searchInput = { value: 'Uni', _listeners: {} };
        searchInput.addEventListener = function(evt, fn) { this._listeners[evt] = fn; };
        searchInput.removeEventListener = function() {};

        var context2 = {
            window: {},
            document: {
                getElementById: function(id) {
                    if (id === 'si2') return searchInput;
                    if (id === 'cs2') return selectMock;
                    return null;
                },
                createElement: function(tag) {
                    return { tagName: tag.toUpperCase(), value: '', textContent: '', disabled: false, dataset: {} };
                },
                addEventListener: function() {},
                removeEventListener: function() {}
            },
            console: { log: function() {}, warn: function() {}, error: function() {} },
            fetch: function() {
                return Promise.resolve({
                    json: function() {
                        return Promise.resolve({
                            success: true,
                            data: {
                                clients: [{ id: 99, nombre: 'Unico', telefono: '999', correo: '' }],
                                total: 1
                            }
                        });
                    }
                });
            },
            FormData: function() { this._fields = {}; this.append = function(k, v) { this._fields[k] = v; }; },
            wpaa_vars: { nonce_search_clientes: 'n' },
            setTimeout: function(fn) { fn(); return 1; },
            clearTimeout: function() {}
        };
        context2.window = context2;
        context2.window.ajaxurl = '/test';

        vm.runInNewContext(reservationClientSrc, context2, { filename: reservationClientPath });

        context2.window.ReservationClientController.init({
            searchInputId: 'si2',
            selectId: 'cs2'
        });

        await new Promise(function(r) { setImmediate(r); });

        searchInput._listeners.input.call(searchInput);

        await new Promise(function(r) { setImmediate(r); });

        assert.equal(selectMock.selectedIndex, 0, 'must not auto-select the single client');
        assert.equal(selectMock.options[0].value, '', 'placeholder must be first');
        assert.equal(selectMock.options[1].value, '99', 'client must be second');
    });
});
