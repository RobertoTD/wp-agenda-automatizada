/**
 * Executive Proposal Module — orquestación read-only de Propuesta ejecutiva (MC2).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var isLoading = false;

    function setVisible(el, visible) {
        if (!el) {
            return;
        }

        if (visible) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    }

    function getService() {
        return globalRoot.AAExecutiveProposalService || null;
    }

    function getRenderer() {
        return globalRoot.AAExecutiveProposalRenderer || null;
    }

    /**
     * @returns {Promise<void>}
     */
    function reloadExecutiveProposalBestEffort() {
        var api = globalRoot.AAExecutiveProposal;

        if (api && typeof api.reload === 'function') {
            return api.reload({ silent: true }).catch(function () {});
        }

        return Promise.resolve();
    }

    function showProposalError(message) {
        var errorEl = document.getElementById('aa-executive-proposal-error');
        setVisible(errorEl, true);

        if (errorEl) {
            errorEl.textContent = message || 'No se pudo cargar la propuesta ejecutiva.';
        }
    }

    function clearProposalError() {
        setVisible(document.getElementById('aa-executive-proposal-error'), false);
    }

    /**
     * @param {{silent?:boolean}} [options]
     * @returns {Promise<void>}
     */
    function loadProposal(options) {
        var opts = options || {};
        var silent = opts.silent === true;
        var service = getService();
        var renderer = getRenderer();
        var loadingEl = document.getElementById('aa-executive-proposal-loading');
        var root = document.getElementById('aa-executive-proposal');

        if (!root) {
            return Promise.resolve();
        }

        if (!service || typeof service.getExecutiveProposal !== 'function') {
            showProposalError('No se pudo inicializar el servicio de propuesta ejecutiva.');
            return Promise.resolve();
        }

        if (!renderer || typeof renderer.renderProposal !== 'function') {
            showProposalError('No se pudo inicializar el renderer de propuesta ejecutiva.');
            return Promise.resolve();
        }

        if (isLoading) {
            return Promise.resolve();
        }

        isLoading = true;

        if (!silent) {
            setVisible(loadingEl, true);
        }

        clearProposalError();

        return service.getExecutiveProposal()
            .then(function (payload) {
                renderer.renderProposal(payload);
            })
            .catch(function (err) {
                if (!silent) {
                    showProposalError((err && err.message) ? err.message : 'No se pudo cargar la propuesta ejecutiva.');
                }
            })
            .finally(function () {
                isLoading = false;
                setVisible(loadingEl, false);
            });
    }

    function initExecutiveProposalModule() {
        loadProposal();
    }

    globalRoot.AAExecutiveProposal = {
        reload: loadProposal
    };

    var moduleExports = {
        loadProposal: loadProposal,
        reloadExecutiveProposalBestEffort: reloadExecutiveProposalBestEffort
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExecutiveProposalModule);
    } else {
        initExecutiveProposalModule();
    }
})();
