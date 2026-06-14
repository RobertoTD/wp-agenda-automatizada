/**
 * Executive Proposal Service — lectura y acciones de Propuesta ejecutiva (MC2/MC3).
 *
 * Depends on window.AA_EXECUTIVE_PROPOSAL_DATA (ajaxUrl, action, actionPost, nonce).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);

    function getConfig() {
        var cfg = globalRoot.AA_EXECUTIVE_PROPOSAL_DATA;

        if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
            return null;
        }

        return cfg;
    }

    /**
     * @param {string} action
     * @param {Object} [extraFields]
     * @returns {Promise<Object>}
     */
    function postForm(action, extraFields) {
        var cfg = getConfig();

        if (!cfg) {
            return Promise.reject(new Error('AA_EXECUTIVE_PROPOSAL_DATA no configurado'));
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
                    var message = payload.message || 'No se pudo completar la acción ejecutiva.';
                    var err = new Error(message);
                    err.code = payload.code || 'unknown_error';
                    throw err;
                }

                return result.data || {};
            });
    }

    /**
     * @returns {Promise<Object>}
     */
    function getExecutiveProposal() {
        var cfg = getConfig();

        if (!cfg || !cfg.action) {
            return Promise.reject(new Error('AA_EXECUTIVE_PROPOSAL_DATA no configurado'));
        }

        return postForm(cfg.action);
    }

    /**
     * @param {{taskId:number|string, actionKey:string}} payload
     * @returns {Promise<{action:Object, proposal:Object, client_action:Object|null}>}
     */
    function postExecutiveAction(payload) {
        var cfg = getConfig();
        var data = payload || {};
        var actionPost = cfg && cfg.actionPost ? cfg.actionPost : 'aa_executive_action';

        return postForm(actionPost, {
            task_id: String(data.taskId || ''),
            action_key: String(data.actionKey || '')
        }).then(function (result) {
            return {
                action: result.action && typeof result.action === 'object' ? result.action : {},
                proposal: result.proposal && typeof result.proposal === 'object' ? result.proposal : {},
                client_action: result.client_action && typeof result.client_action === 'object'
                    ? result.client_action
                    : null
            };
        });
    }

    var api = {
        getExecutiveProposal: getExecutiveProposal,
        postExecutiveAction: postExecutiveAction
    };

    globalRoot.AAExecutiveProposalService = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
