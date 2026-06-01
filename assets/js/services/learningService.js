/**
 * Learning Service — consume aa_get_learning_recommendations para Guías/Aprendizaje.
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
     * @returns {Promise<{list_1:Array,list_2:Array,all_visible:Array}>}
     */
    function getRecommendations() {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(new Error('AA_LEARNING_DATA no configurado'));
        }

        var formData = new FormData();
        formData.append('action', cfg.action);
        formData.append('_wpnonce', cfg.nonce);

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
                if (!result.success || !result.data) {
                    var message = (result.data && result.data.message)
                        ? result.data.message
                        : 'No se pudieron cargar las recomendaciones.';
                    throw new Error(message);
                }

                return {
                    list_1: Array.isArray(result.data.list_1) ? result.data.list_1 : [],
                    list_2: Array.isArray(result.data.list_2) ? result.data.list_2 : [],
                    all_visible: Array.isArray(result.data.all_visible) ? result.data.all_visible : []
                };
            });
    }

    window.LearningService = {
        getRecommendations: getRecommendations
    };
})();
