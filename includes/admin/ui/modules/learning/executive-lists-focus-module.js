/**
 * Executive Lists Focus — atenuación contextual propuesta/listas (MC-UX-F / MC6).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var ROOT_ID = 'aa-tasks-module-root';
    var PROPOSAL_ID = 'aa-executive-proposal';
    var LISTS_ID = 'aa-lists-section';
    var isBound = false;
    var activeWorkZone = 'executive';

    function setMuted(element, muted) {
        if (!element || !element.classList) {
            return;
        }

        element.classList.toggle('is-muted', !!muted);
    }

    function notifyWorkZone(zone) {
        var nextZone = zone === 'organizing' ? 'organizing' : 'executive';

        if (nextZone === activeWorkZone) {
            return;
        }

        activeWorkZone = nextZone;

        var api = globalRoot.AAExecutiveProposal;

        if (api && typeof api.setWorkZone === 'function') {
            api.setWorkZone(zone);
        }
    }

    function activateFromTarget(root, proposal, listsSection, target) {
        if (!target || !proposal || !listsSection) {
            return;
        }

        if (listsSection.contains(target)) {
            setMuted(listsSection, false);
            setMuted(proposal, true);
            notifyWorkZone('organizing');

            return;
        }

        if (proposal.contains(target)) {
            setMuted(listsSection, true);
            setMuted(proposal, false);
            notifyWorkZone('executive');
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

        var proposal = document.getElementById(PROPOSAL_ID);
        var listsSection = document.getElementById(LISTS_ID);

        if (!proposal || !listsSection) {
            return;
        }

        isBound = true;
        activeWorkZone = 'executive';
        setMuted(listsSection, true);
        setMuted(proposal, false);

        var root = document.getElementById(ROOT_ID);

        root.addEventListener('click', handleRootInteraction, true);
        root.addEventListener('focusin', handleRootInteraction, true);
    }

    function initExecutiveListsFocusModule() {
        bindExecutiveListsFocusModule();
    }

    var moduleExports = {
        setMuted: setMuted,
        activateFromTarget: activateFromTarget,
        handleRootInteraction: handleRootInteraction,
        bindExecutiveListsFocusModule: bindExecutiveListsFocusModule,
        getActiveWorkZone: function () {
            return activeWorkZone;
        }
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
