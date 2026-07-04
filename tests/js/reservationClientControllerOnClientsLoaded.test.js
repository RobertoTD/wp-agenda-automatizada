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

function loadReservationClientController(options) {
    var opts = options || {};
    var loadedMeta = [];

    var context = {
        window: {},
        document: {
            getElementById: function(id) {
                if (id === 'aa-fastappointment-client-search') {
                    return { value: opts.searchValue || '', addEventListener: function() {}, removeEventListener: function() {} };
                }
                if (id === 'aa-fastappointment-client') {
                    return {
                        innerHTML: '',
                        disabled: false,
                        value: '',
                        selectedIndex: 0,
                        options: [],
                        appendChild: function(option) {
                            this.options.push(option);
                        },
                        addEventListener: function() {},
                        removeEventListener: function() {}
                    };
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
        loadedMeta: loadedMeta
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
