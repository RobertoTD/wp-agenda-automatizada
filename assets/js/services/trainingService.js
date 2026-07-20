/**
 * Training Service — admin-ajax client for Training endpoints (C8A1b).
 *
 * Depends on an injectable config (C8A2 will supply it from Cuenta / module).
 * Talks only to admin-ajax; never to the platform API directly.
 *
 * Config shape:
 * {
 *   ajaxUrl: string,
 *   nonce: string,
 *   actions: {
 *     getStatus, enroll, unsubscribe,
 *     getConsentStatus, acceptConsent, revokeConsent,
 *     getCourse, getLesson,
 *     markOpened, markCompleted
 *   }
 * }
 */
(function (root) {
    'use strict';

    var DEFAULT_ACTIONS = {
        getStatus: 'aa_get_training_status',
        enroll: 'aa_enroll_training',
        unsubscribe: 'aa_unsubscribe_training',
        getConsentStatus: 'aa_get_training_consent_status',
        acceptConsent: 'aa_accept_training_consent',
        revokeConsent: 'aa_revoke_training_consent',
        getCourse: 'aa_get_training_course',
        getLesson: 'aa_get_training_lesson',
        markOpened: 'aa_mark_training_lesson_opened',
        markCompleted: 'aa_mark_training_lesson_completed'
    };

    /**
     * @param {object|null|undefined} override
     * @returns {object|null}
     */
    function resolveConfig(override) {
        if (override && typeof override === 'object') {
            return override;
        }
        if (root.AA_TRAINING_DATA && typeof root.AA_TRAINING_DATA === 'object') {
            return root.AA_TRAINING_DATA;
        }
        return null;
    }

    /**
     * @param {object|null} cfg
     * @param {string} methodKey
     * @returns {{ ajaxUrl: string, nonce: string, action: string }}
     */
    function requireRequestConfig(cfg, methodKey) {
        if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
            var missing = new Error('AA_TRAINING_DATA no configurado');
            missing.code = 'training_config_missing';
            missing.kind = 'invalid_response';
            throw missing;
        }

        var actions = cfg.actions && typeof cfg.actions === 'object'
            ? cfg.actions
            : DEFAULT_ACTIONS;
        var action = actions[methodKey] || DEFAULT_ACTIONS[methodKey];

        if (!action) {
            var badAction = new Error('Acción Training desconocida');
            badAction.code = 'training_config_missing';
            badAction.kind = 'invalid_response';
            throw badAction;
        }

        return {
            ajaxUrl: String(cfg.ajaxUrl),
            nonce: String(cfg.nonce),
            action: String(action)
        };
    }

    /**
     * @param {string} methodKey
     * @param {Object} [fields]
     * @param {{ signal?: AbortSignal, config?: object }} [options]
     * @returns {Promise<object>}
     */
    function postAction(methodKey, fields, options) {
        options = options || {};

        var req;
        try {
            req = requireRequestConfig(resolveConfig(options.config), methodKey);
        } catch (err) {
            return Promise.reject(err);
        }

        var formData = new FormData();
        formData.append('action', req.action);
        formData.append('_wpnonce', req.nonce);

        if (fields) {
            Object.keys(fields).forEach(function (field) {
                var value = fields[field];
                if (value === undefined || value === null) {
                    return;
                }
                formData.append(field, String(value));
            });
        }

        var fetchOptions = {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        };

        if (options.signal) {
            fetchOptions.signal = options.signal;
        }

        return fetch(req.ajaxUrl, fetchOptions)
            .then(function (response) {
                if (!response.ok) {
                    var httpErr = new Error('HTTP ' + response.status);
                    httpErr.code = 'training_network_error';
                    httpErr.kind = 'network';
                    httpErr.httpStatus = response.status;
                    throw httpErr;
                }

                return response.json().catch(function () {
                    var invalid = new Error('Invalid JSON response');
                    invalid.code = 'training_invalid_response';
                    invalid.kind = 'invalid_response';
                    throw invalid;
                });
            })
            .then(function (json) {
                if (!json || typeof json !== 'object') {
                    var bad = new Error('Invalid training response');
                    bad.code = 'training_invalid_response';
                    bad.kind = 'invalid_response';
                    throw bad;
                }

                if (json.success === true) {
                    return {
                        success: true,
                        data: json.data && typeof json.data === 'object' ? json.data : {}
                    };
                }

                var payload = json.data && typeof json.data === 'object' ? json.data : {};
                var code = typeof payload.code === 'string' && payload.code !== ''
                    ? payload.code
                    : 'training_backend_error';
                var message = typeof payload.message === 'string' ? payload.message : '';
                var err = new Error(message || code);
                err.code = code;
                err.kind = code.indexOf('training_') === 0 ? 'training' : 'invalid_response';
                err.success = false;
                throw err;
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    var aborted = new Error('Aborted');
                    aborted.code = 'training_aborted';
                    aborted.kind = 'aborted';
                    throw aborted;
                }
                if (err && err.kind) {
                    throw err;
                }
                var network = new Error(err && err.message ? String(err.message) : 'Network error');
                network.code = 'training_network_error';
                network.kind = 'network';
                throw network;
            });
    }

    function getStatus(options) {
        return postAction('getStatus', null, options);
    }

    function enroll(options) {
        return postAction('enroll', null, options);
    }

    function unsubscribe(options) {
        return postAction('unsubscribe', null, options);
    }

    function getConsentStatus(options) {
        return postAction('getConsentStatus', null, options);
    }

    function acceptConsent(options) {
        return postAction('acceptConsent', null, options);
    }

    function revokeConsent(options) {
        return postAction('revokeConsent', null, options);
    }

    function getCourse(options) {
        return postAction('getCourse', null, options);
    }

    /**
     * @param {string} lessonKey
     * @param {{ signal?: AbortSignal, config?: object }} [options]
     */
    function getLesson(lessonKey, options) {
        return postAction('getLesson', { lessonKey: lessonKey }, options);
    }

    /**
     * @param {string} lessonKey
     * @param {{ signal?: AbortSignal, config?: object }} [options]
     */
    function markOpened(lessonKey, options) {
        return postAction('markOpened', { lessonKey: lessonKey }, options);
    }

    /**
     * @param {string} lessonKey
     * @param {{ signal?: AbortSignal, config?: object }} [options]
     */
    function markCompleted(lessonKey, options) {
        return postAction('markCompleted', { lessonKey: lessonKey }, options);
    }

    var api = {
        getStatus: getStatus,
        enroll: enroll,
        unsubscribe: unsubscribe,
        getConsentStatus: getConsentStatus,
        acceptConsent: acceptConsent,
        revokeConsent: revokeConsent,
        getCourse: getCourse,
        getLesson: getLesson,
        markOpened: markOpened,
        markCompleted: markCompleted,
        DEFAULT_ACTIONS: DEFAULT_ACTIONS
    };

    root.TrainingService = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
