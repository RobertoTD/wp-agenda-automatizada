/**
 * Push Device Key Service — identidad local por blog para tareas enable_push:*.
 */
(function () {
    'use strict';

    var STORAGE_KEY_PREFIX = 'aa_push_device_key_v1';
    var DEVICE_KEY_PATTERN = /^[a-f0-9]{32}$/;

    function getGlobalRoot() {
        if (typeof window !== 'undefined') {
            return window;
        }

        if (typeof globalThis !== 'undefined') {
            return globalThis;
        }

        return {};
    }

    function resolveBlogId() {
        var root = getGlobalRoot();
        var ctx = root.AA_ADMIN_CONTEXT;

        if (!ctx || ctx.blogId === null || ctx.blogId === undefined) {
            return '';
        }

        return String(ctx.blogId);
    }

    function buildStorageKey(blogId) {
        var bid = typeof blogId === 'string' ? blogId.trim() : '';

        if (!bid) {
            bid = resolveBlogId();
        }

        return bid !== '' ? STORAGE_KEY_PREFIX + ':' + bid : '';
    }

    function isValidDeviceKey(value) {
        return typeof value === 'string' && DEVICE_KEY_PATTERN.test(value);
    }

    function canUseLocalStorage() {
        var root = getGlobalRoot();

        try {
            return !!(root.localStorage && typeof root.localStorage.getItem === 'function');
        } catch (err) {
            return false;
        }
    }

    function canUseWebCryptoRandom() {
        var root = getGlobalRoot();

        try {
            return !!(
                root.crypto
                && typeof root.crypto.getRandomValues === 'function'
            );
        } catch (err) {
            return false;
        }
    }

    function bytesToHex(bytes) {
        var hex = '';
        var i;

        for (i = 0; i < bytes.length; i += 1) {
            hex += bytes[i].toString(16).padStart(2, '0');
        }

        return hex;
    }

    function generateDeviceKey() {
        var root = getGlobalRoot();
        var bytes = new Uint8Array(16);

        root.crypto.getRandomValues(bytes);

        return bytesToHex(bytes);
    }

    /**
     * @returns {string|null}
     */
    function getOrCreateDeviceKey() {
        if (!canUseLocalStorage() || !canUseWebCryptoRandom()) {
            return null;
        }

        var root = getGlobalRoot();
        var storageKey = buildStorageKey();

        if (storageKey === '') {
            return null;
        }

        try {
            var existing = root.localStorage.getItem(storageKey);

            if (isValidDeviceKey(existing)) {
                return existing;
            }

            if (existing !== null && existing !== '') {
                root.localStorage.removeItem(storageKey);
            }

            var created = generateDeviceKey();
            root.localStorage.setItem(storageKey, created);

            return created;
        } catch (err) {
            return null;
        }
    }

    var api = {
        getOrCreateDeviceKey: getOrCreateDeviceKey,
        isValidDeviceKey: isValidDeviceKey
    };

    getGlobalRoot().PushDeviceKeyService = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
        module.exports.__test = {
            buildStorageKey: buildStorageKey,
            canUseLocalStorage: canUseLocalStorage,
            canUseWebCryptoRandom: canUseWebCryptoRandom,
            generateDeviceKey: generateDeviceKey,
            bytesToHex: bytesToHex,
            DEVICE_KEY_PATTERN: DEVICE_KEY_PATTERN,
            STORAGE_KEY_PREFIX: STORAGE_KEY_PREFIX
        };
    }
})();
