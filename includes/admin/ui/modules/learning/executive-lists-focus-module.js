/**
 * Executive Lists Focus — atenuación contextual propuesta/listas (MC-UX-F / MC6, MC-UX-G MC1).
 */
(function () {
    'use strict';

    var globalRoot = typeof window !== 'undefined'
        ? window
        : (typeof globalThis !== 'undefined' ? globalThis : this);
    var ROOT_ID = 'aa-tasks-module-root';
    var PROPOSAL_ID = 'aa-executive-proposal';
    var LISTS_ID = 'aa-lists-section';
    var LISTS_BODY_ID = 'aa-lists-body';
    var LISTS_HEADER_TOGGLE_ID = 'aa-lists-header-toggle';
    var isBound = false;
    var activeWorkZone = 'executive';

    function setMuted(element, muted) {
        if (!element || !element.classList) {
            return;
        }

        element.classList.toggle('is-muted', !!muted);
    }

    function setListsBodyCollapsed(listsBody, collapsed) {
        if (!listsBody || !listsBody.classList) {
            return;
        }

        listsBody.classList.toggle('is-collapsed', !!collapsed);

        if (collapsed) {
            listsBody.setAttribute('aria-hidden', 'true');

            if (typeof listsBody.inert !== 'undefined') {
                listsBody.inert = true;
            } else {
                listsBody.setAttribute('inert', '');
            }

            return;
        }

        listsBody.removeAttribute('aria-hidden');

        if (typeof listsBody.inert !== 'undefined') {
            listsBody.inert = false;
        } else {
            listsBody.removeAttribute('inert');
        }
    }

    /**
     * @param {'executive'|'organizing'} zone
     * @returns {boolean} true si la zona cambió
     */
    function applyWorkZone(zone) {
        var nextZone = zone === 'organizing' ? 'organizing' : 'executive';
        var root = document.getElementById(ROOT_ID);
        var proposal = document.getElementById(PROPOSAL_ID);
        var listsBody = document.getElementById(LISTS_BODY_ID);
        var headerToggle = document.getElementById(LISTS_HEADER_TOGGLE_ID);
        var zoneChanged = nextZone !== activeWorkZone;

        if (!root || !proposal || !listsBody) {
            return false;
        }

        root.setAttribute('data-work-zone', nextZone);
        setMuted(proposal, nextZone === 'organizing');
        setListsBodyCollapsed(listsBody, nextZone === 'executive');

        if (headerToggle) {
            headerToggle.setAttribute('aria-expanded', nextZone === 'organizing' ? 'true' : 'false');
        }

        if (!zoneChanged) {
            return false;
        }

        activeWorkZone = nextZone;

        var api = globalRoot.AAExecutiveProposal;

        if (api && typeof api.setWorkZone === 'function') {
            api.setWorkZone(nextZone);
        }

        return true;
    }

    function activateFromTarget(root, proposal, listsSection, target) {
        if (!target || !proposal || !listsSection) {
            return;
        }

        if (listsSection.contains(target)) {
            applyWorkZone('organizing');

            return;
        }

        if (proposal.contains(target)) {
            applyWorkZone('executive');
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
        var listsBody = document.getElementById(LISTS_BODY_ID);

        if (!proposal || !listsSection || !listsBody) {
            return;
        }

        isBound = true;
        applyWorkZone('executive');

        var root = document.getElementById(ROOT_ID);

        root.addEventListener('click', handleRootInteraction, true);
        root.addEventListener('focusin', handleRootInteraction, true);
    }

    function initExecutiveListsFocusModule() {
        bindExecutiveListsFocusModule();
    }

    var moduleExports = {
        setMuted: setMuted,
        setListsBodyCollapsed: setListsBodyCollapsed,
        applyWorkZone: applyWorkZone,
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
