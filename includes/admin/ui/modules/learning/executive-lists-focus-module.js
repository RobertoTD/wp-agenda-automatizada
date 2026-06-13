/**
 * Executive Lists Focus — atenuación contextual de #aa-lists-section (MC-UX-F).
 */
(function () {
    'use strict';

    var ROOT_ID = 'aa-tasks-module-root';
    var PROPOSAL_ID = 'aa-executive-proposal';
    var LISTS_ID = 'aa-lists-section';
    var isBound = false;

    function setListsMuted(listsSection, muted) {
        if (!listsSection || !listsSection.classList) {
            return;
        }

        listsSection.classList.toggle('is-muted', !!muted);
    }

    function activateFromTarget(root, proposal, listsSection, target) {
        if (!target || !proposal || !listsSection) {
            return;
        }

        if (listsSection.contains(target)) {
            setListsMuted(listsSection, false);
            return;
        }

        if (proposal.contains(target)) {
            setListsMuted(listsSection, true);
        }
    }

    function handleRootInteraction(event) {
        var root = document.getElementById(ROOT_ID);
        var proposal = document.getElementById(PROPOSAL_ID);
        var listsSection = document.getElementById(LISTS_ID);

        if (!root || !proposal || !listsSection) {
            return;
        }

        activateFromTarget(root, proposal, listsSection, event.target);
    }

    function bindExecutiveListsFocusModule() {
        if (isBound || !document.getElementById(ROOT_ID)) {
            return;
        }

        var listsSection = document.getElementById(LISTS_ID);

        if (!listsSection) {
            return;
        }

        isBound = true;
        setListsMuted(listsSection, true);

        var root = document.getElementById(ROOT_ID);

        root.addEventListener('click', handleRootInteraction, true);
        root.addEventListener('focusin', handleRootInteraction, true);
    }

    function initExecutiveListsFocusModule() {
        bindExecutiveListsFocusModule();
    }

    var moduleExports = {
        setListsMuted: setListsMuted,
        activateFromTarget: activateFromTarget,
        handleRootInteraction: handleRootInteraction,
        bindExecutiveListsFocusModule: bindExecutiveListsFocusModule
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExecutiveListsFocusModule);
    } else {
        initExecutiveListsFocusModule();
    }
})();
