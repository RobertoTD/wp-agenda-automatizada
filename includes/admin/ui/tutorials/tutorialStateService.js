/**
 * Tutorial State Service — cliente HTTP del estado durable de tutoriales (MC3C).
 *
 * Depends on window.AA_TUTORIAL_DATA (ajaxUrl, getAction, updateAction, nonce).
 * No business rules; backend MC3A is authoritative for FSM transitions.
 */
(function () {
    'use strict';

    var TRANSITION_FIELDS = ['tutorial_id', 'status', 'current_step_id'];

    function getConfig() {
        return window.AA_TUTORIAL_DATA || null;
    }

    /**
     * @param {'get'|'update'|'reconcile'} mode
     * @returns {object}
     */
    function assertConfig(mode) {
        var config = getConfig();

        if (!config || !config.ajaxUrl || !config.nonce) {
            throw createError('AA_TUTORIAL_DATA no configurado', 'missing_config');
        }

        if (mode === 'get' && !config.getAction) {
            throw createError('AA_TUTORIAL_DATA.getAction no configurado', 'missing_config');
        }

        if (mode === 'update' && !config.updateAction) {
            throw createError('AA_TUTORIAL_DATA.updateAction no configurado', 'missing_config');
        }

        if (mode === 'reconcile' && !config.reconcileAction) {
            throw createError('AA_TUTORIAL_DATA.reconcileAction no configurado', 'missing_config');
        }

        return config;
    }

    /**
     * @param {string} message
     * @param {string} code
     * @param {number} [httpStatus]
     * @returns {Error}
     */
    function createError(message, code, httpStatus) {
        var err = new Error(message || 'Error de tutorial state');
        err.code = code || 'unknown_error';

        if (typeof httpStatus === 'number') {
            err.httpStatus = httpStatus;
        }

        return err;
    }

    /**
     * @param {Response} response
     * @returns {Promise<object>}
     */
    function parseWpJsonResponse(response) {
        return response.json().then(function (json) {
            if (response.ok && json && json.success === true) {
                return json.data || {};
            }

            var payload = json && json.data && typeof json.data === 'object' ? json.data : {};
            var message = payload.message
                ? String(payload.message)
                : ('HTTP ' + response.status);

            throw createError(
                message,
                payload.code ? String(payload.code) : 'ajax_error',
                response.status
            );
        }).catch(function (err) {
            if (err instanceof Error && err.code) {
                throw err;
            }

            throw createError(
                'HTTP ' + response.status,
                'http_error',
                response.status
            );
        });
    }

    /**
     * @returns {Promise<{version:number,tutorials:Object}>}
     */
    function fetchState() {
        var config;

        try {
            config = assertConfig('get');
        } catch (err) {
            return Promise.reject(err);
        }

        var url = config.ajaxUrl
            + '?action=' + encodeURIComponent(config.getAction)
            + '&_wpnonce=' + encodeURIComponent(config.nonce);

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(parseWpJsonResponse)
            .catch(function (err) {
                if (err instanceof Error) {
                    console.error('[TutorialStateService] fetchState failed:', err);
                }

                return Promise.reject(err);
            });
    }

    /**
     * @param {object} input
     * @returns {object}
     */
    function buildTransitionFields(input) {
        if (!input || typeof input !== 'object') {
            throw createError('Input de transición inválido', 'invalid_input');
        }

        var tutorialId = typeof input.tutorialId === 'string' ? input.tutorialId.trim() : '';
        var status = typeof input.status === 'string' ? input.status.trim() : '';

        if (!tutorialId) {
            throw createError('tutorialId requerido', 'invalid_input');
        }

        if (!status) {
            throw createError('status requerido', 'invalid_input');
        }

        var fields = {
            tutorial_id: tutorialId,
            status: status
        };

        if (Object.prototype.hasOwnProperty.call(input, 'currentStepId')) {
            var step = input.currentStepId;

            if (step === null || step === '') {
                fields.current_step_id = '';
            } else if (typeof step === 'string') {
                fields.current_step_id = step.trim();
            } else {
                throw createError('currentStepId inválido', 'invalid_input');
            }
        }

        return fields;
    }

    /**
     * @param {object} config
     * @param {string} action
     * @param {object} [fields]
     * @returns {FormData}
     */
    function buildFormData(config, action, fields) {
        var formData = new FormData();

        formData.append('action', action);
        formData.append('_wpnonce', config.nonce);

        if (fields && typeof fields === 'object') {
            TRANSITION_FIELDS.forEach(function (field) {
                if (!Object.prototype.hasOwnProperty.call(fields, field)) {
                    return;
                }

                formData.append(field, String(fields[field]));
            });
        }

        return formData;
    }

    /**
     * @param {{tutorialId:string,status:string,currentStepId?:string|null}} input
     * @returns {Promise<{version:number,tutorials:Object}>}
     */
    function transition(input) {
        var config;
        var fields;

        try {
            config = assertConfig('update');
            fields = buildTransitionFields(input);
        } catch (err) {
            return Promise.reject(err);
        }

        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: buildFormData(config, config.updateAction, fields)
        })
            .then(parseWpJsonResponse)
            .catch(function (err) {
                if (err instanceof Error) {
                    console.error('[TutorialStateService] transition failed:', err);
                }

                return Promise.reject(err);
            });
    }

    /**
     * @returns {Promise<{version:number,tutorials:Object,reconciled:boolean}>}
     */
    function reconcileState() {
        var config;

        try {
            config = assertConfig('reconcile');
        } catch (err) {
            return Promise.reject(err);
        }

        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: buildFormData(config, config.reconcileAction)
        })
            .then(parseWpJsonResponse)
            .catch(function (err) {
                if (err instanceof Error) {
                    console.error('[TutorialStateService] reconcileState failed:', err);
                }

                return Promise.reject(err);
            });
    }

    window.TutorialStateService = {
        fetchState: fetchState,
        transition: transition,
        reconcileState: reconcileState
    };
})();
