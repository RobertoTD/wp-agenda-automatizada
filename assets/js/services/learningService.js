/**
 * Learning Service — Guías/Aprendizaje (lectura y acciones de estado).
 *
 * Depends on window.AA_LEARNING_DATA (ajaxUrl, action, nonce).
 */
(function () {
    'use strict';

    function getConfig() {
        var cfg = window.AA_LEARNING_DATA;

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
            return Promise.reject(new Error('AA_LEARNING_DATA no configurado'));
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
                    var message = payload.message || 'No se pudo completar la acción.';
                    var err = new Error(message);
                    err.code = payload.code || 'unknown_error';
                    throw err;
                }

                return result.data || {};
            });
    }

    /**
     * @returns {Promise<{list_1:Array,list_2:Array,all_visible:Array}>}
     */
    function getRecommendations() {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(new Error('AA_LEARNING_DATA no configurado'));
        }

        return postAction(cfg.action).then(function (data) {
            return {
                list_1: Array.isArray(data.list_1) ? data.list_1 : [],
                list_2: Array.isArray(data.list_2) ? data.list_2 : [],
                all_visible: Array.isArray(data.all_visible) ? data.all_visible : []
            };
        });
    }

    /**
     * @param {string} recommendationKey
     * @returns {Promise<Object>}
     */
    function ignoreRecommendation(recommendationKey) {
        return postAction('aa_ignore_learning_recommendation', {
            recommendation_key: recommendationKey
        });
    }

    /**
     * @param {string} recommendationKey
     * @returns {Promise<Object>}
     */
    function dismissRecommendation(recommendationKey) {
        return postAction('aa_dismiss_learning_recommendation', {
            recommendation_key: recommendationKey
        });
    }

    /**
     * @param {string} recommendationKey
     * @returns {Promise<Object>}
     */
    function completeRecommendation(recommendationKey) {
        return postAction('aa_complete_learning_recommendation', {
            recommendation_key: recommendationKey
        });
    }

    /**
     * @param {string} recommendationKey
     * @returns {Promise<Object>}
     */
    function reactivateRecommendation(recommendationKey) {
        return postAction('aa_reactivate_learning_recommendation', {
            recommendation_key: recommendationKey
        });
    }

    window.LearningService = {
        getRecommendations: getRecommendations,
        ignoreRecommendation: ignoreRecommendation,
        dismissRecommendation: dismissRecommendation,
        completeRecommendation: completeRecommendation,
        reactivateRecommendation: reactivateRecommendation
    };
})();
