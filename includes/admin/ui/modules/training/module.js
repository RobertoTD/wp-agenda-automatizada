/**
 * Training Module — portal shell only (C8A2).
 *
 * Content fetch (course / lesson) is deferred to C8A3.
 */
(function (root) {
    'use strict';

    var SHELL_ONLY = true;

    function getEl(id) {
        return document.getElementById(id);
    }

    /**
     * Shows the shell loading surface. No content fetch in C8A2.
     */
    function showShellReady() {
        var loadingEl = getEl('aa-training-shell-loading');
        var errorEl = getEl('aa-training-shell-error');

        if (errorEl) {
            errorEl.classList.add('hidden');
        }
        if (loadingEl) {
            loadingEl.classList.remove('hidden');
        }
    }

    function init() {
        var root = getEl('aa-training-shell-root');
        if (!root) {
            return;
        }
        showShellReady();
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', init);
    }

    var api = {
        SHELL_ONLY: SHELL_ONLY,
        init: init,
        showShellReady: showShellReady
    };

    root.TrainingModule = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
