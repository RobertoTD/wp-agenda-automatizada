/**
 * Executive Proposal Service — lectura read-only de Propuesta ejecutiva (MC2).
 *
 * Depends on window.AA_EXECUTIVE_PROPOSAL_DATA (ajaxUrl, action, nonce).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    function getConfig() {
        var cfg = globalRoot.AA_EXECUTIVE_PROPOSAL_DATA;

        if (!cfg || !cfg.ajaxUrl || !cfg.nonce || !cfg.action) {
            return null;
        }

        return cfg;
    }

    /**
     * @returns {Promise<Object>}
     */
    function getExecutiveProposal() {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(new Error('AA_EXECUTIVE_PROPOSAL_DATA no configurado'));
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
                if (!result.success) {
                    var payload = result.data || {};
                    var message = payload.message || 'No se pudo cargar la propuesta ejecutiva.';
                    var err = new Error(message);
                    err.code = payload.code || 'unknown_error';
                    throw err;
                }

                return result.data || {};
            });
    }

    globalRoot.AAExecutiveProposalService = {
        getExecutiveProposal: getExecutiveProposal
    };
})();
