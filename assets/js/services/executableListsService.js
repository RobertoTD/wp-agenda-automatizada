/**
 * Executable Lists Service — feed común MC7 (lectura shadow MC10A).
 *
 * Depends on window.AA_EXECUTABLE_LISTS_DATA (ajaxUrl, action, nonce).
 */
(function () {
    'use strict';

    function getConfig() {
        var cfg = window.AA_EXECUTABLE_LISTS_DATA;

        if (!cfg || !cfg.ajaxUrl || !cfg.nonce || !cfg.action) {
            return null;
        }

        return cfg;
    }

    /**
     * @param {string} action
     * @param {Object} [extraFields]
     * @returns {Promise<Object>}
     */
    function postAction(action, extraFields) {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(new Error('AA_EXECUTABLE_LISTS_DATA no configurado'));
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('_wpnonce', cfg.nonce);

        if (extraFields) {
            Object.keys(extraFields).forEach(function (field) {
                formData.append(field, extraFields[field]);
            });
        }

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (result) {
                if (!result.success) {
                    var payload = result.data || {};
                    var message = payload.message || 'No se pudo cargar el feed de listas.';
                    var err = new Error(message);
                    err.code = payload.code || 'unknown_error';
                    err.meta = payload.meta && typeof payload.meta === 'object' ? payload.meta : null;
                    throw err;
                }

                return result.data || {};
            });
    }

    /**
     * @returns {Promise<{lists:Array,meta:Object}>}
     */
    function getFeed() {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(new Error('AA_EXECUTABLE_LISTS_DATA no configurado'));
        }

        return postAction(cfg.action).then(function (data) {
            return {
                lists: Array.isArray(data.lists) ? data.lists : [],
                meta: data.meta && typeof data.meta === 'object' ? data.meta : {}
            };
        });
    }

    window.ExecutableListsService = {
        getFeed: getFeed
    };
})();
