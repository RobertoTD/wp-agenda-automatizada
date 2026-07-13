'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const servicePath = path.join(__dirname, '../../assets/js/services/pushDeviceKeyService.js');

function createStorage() {
    var store = {};

    return {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null;
        },
        setItem: function (key, value) {
            store[key] = String(value);
        },
        removeItem: function (key) {
            delete store[key];
        },
        clear: function () {
            store = {};
        },
        _dump: function () {
            return store;
        }
    };
}

function loadService(options) {
    options = options || {};

    globalThis.AA_ADMIN_CONTEXT = {
        blogId: options.blogId === undefined ? 42 : options.blogId
    };

    if (options.localStorage === false) {
        delete globalThis.localStorage;
    } else {
        globalThis.localStorage = options.localStorage || createStorage();
    }

    if (options.crypto === false) {
        delete globalThis.crypto;
    } else {
        var sequence = Array.isArray(options.randomSequence) ? options.randomSequence.slice() : null;

        Object.defineProperty(globalThis, 'crypto', {
            configurable: true,
            writable: true,
            value: {
                getRandomValues: function (bytes) {
                    if (sequence && sequence.length > 0) {
                        var chunk = sequence.shift();

                        for (var i = 0; i < bytes.length; i += 1) {
                            bytes[i] = chunk[i] !== undefined ? chunk[i] : 0;
                        }

                        return bytes;
                    }

                    for (var j = 0; j < bytes.length; j += 1) {
                        bytes[j] = (j + 1) % 256;
                    }

                    return bytes;
                }
            }
        });
    }

    delete require.cache[servicePath];
    return require(servicePath);
}

describe('PushDeviceKeyService', () => {
    afterEach(() => {
        delete globalThis.PushDeviceKeyService;
        delete globalThis.localStorage;
        delete globalThis.crypto;
        delete globalThis.AA_ADMIN_CONTEXT;
        delete require.cache[servicePath];
    });

    it('genera device key valida de 32 hex', () => {
        var service = loadService({
            randomSequence: [[0xab, 0xcd, 0xef, 0x01, 0x23, 0x45, 0x67, 0x89, 0x10, 0x32, 0x54, 0x76, 0x98, 0xba, 0xdc, 0xfe]]
        });

        var key = service.getOrCreateDeviceKey();

        assert.equal(key, 'abcdef01234567891032547698badcfe');
        assert.match(key, service.__test.DEVICE_KEY_PATTERN);
    });

    it('reutiliza device key persistida', () => {
        var storage = createStorage();
        storage.setItem('aa_push_device_key_v1:42', '0123456789abcdef0123456789abcdef');

        var service = loadService({ localStorage: storage });
        var first = service.getOrCreateDeviceKey();
        var second = service.getOrCreateDeviceKey();

        assert.equal(first, '0123456789abcdef0123456789abcdef');
        assert.equal(second, first);
        assert.equal(Object.keys(storage._dump()).length, 1);
    });

    it('sustituye valor invalido en localStorage', () => {
        var storage = createStorage();
        storage.setItem('aa_push_device_key_v1:42', 'not-a-valid-key');

        var service = loadService({
            localStorage: storage,
            randomSequence: [[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16]]
        });

        var key = service.getOrCreateDeviceKey();

        assert.match(key, service.__test.DEVICE_KEY_PATTERN);
        assert.notEqual(key, 'not-a-valid-key');
        assert.equal(storage.getItem('aa_push_device_key_v1:42'), key);
    });

    it('sin localStorage devuelve null', () => {
        var service = loadService({ localStorage: false });

        assert.equal(service.getOrCreateDeviceKey(), null);
    });

    it('sin Web Crypto devuelve null', () => {
        var service = loadService({ crypto: false });

        assert.equal(service.getOrCreateDeviceKey(), null);
    });
});
