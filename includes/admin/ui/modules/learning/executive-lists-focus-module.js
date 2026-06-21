/**
 * Executive Lists Focus — atenuación contextual propuesta/listas (MC-UX-F / MC6, MC-UX-G MC1/MC3).
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
    var WHEEL_DELTA_THRESHOLD = 120;
    var WHEEL_BUCKET_RESET_MS = 400;
    var WHEEL_TRANSITION_COOLDOWN_MS = 350;
    var EXECUTIVE_RETURN_SCROLL_Y_MAX = 80;
    var EXECUTIVE_RETURN_PROPOSAL_TOP_MAX = 120;
    var isBound = false;
    var activeWorkZone = 'executive';
    var wheelDeltaBucket = 0;
    var wheelBucketUpdatedAt = 0;
    var wheelTransitionCooldownUntil = 0;

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
     * @returns {{scrollY:number, proposalTop:number}}
     */
    function getWheelMetrics() {
        var docEl = globalRoot.document && globalRoot.document.documentElement
            ? globalRoot.document.documentElement
            : null;
        var scrollY = globalRoot.pageYOffset
            || (docEl ? docEl.scrollTop : 0)
            || 0;
        var proposal = document.getElementById(PROPOSAL_ID);
        var proposalTop = Number.POSITIVE_INFINITY;

        if (proposal && typeof proposal.getBoundingClientRect === 'function') {
            proposalTop = proposal.getBoundingClientRect().top;
        }

        return {
            scrollY: scrollY,
            proposalTop: proposalTop
        };
    }

    /**
     * @param {{scrollY?:number, proposalTop?:number}|null|undefined} metrics
     * @returns {boolean}
     */
    function canReturnToExecutiveFromWheel(metrics) {
        var data = metrics || getWheelMetrics();
        var scrollY = typeof data.scrollY === 'number' ? data.scrollY : 0;
        var proposalTop = typeof data.proposalTop === 'number'
            ? data.proposalTop
            : Number.POSITIVE_INFINITY;

        return scrollY <= EXECUTIVE_RETURN_SCROLL_Y_MAX
            || proposalTop <= EXECUTIVE_RETURN_PROPOSAL_TOP_MAX;
    }

    /**
     * @param {{
     *   deltaY:number,
     *   now:number,
     *   zone:'executive'|'organizing',
     *   bucket:number,
     *   bucketUpdatedAt:number,
     *   cooldownUntil:number,
     *   metrics?:{scrollY?:number, proposalTop?:number}
     * }} options
     * @returns {{
     *   zone:'executive'|'organizing',
     *   bucket:number,
     *   bucketUpdatedAt:number,
     *   cooldownUntil:number,
     *   nextZone:'executive'|'organizing'|null
     * }}
     */
    function stepWheelGesture(options) {
        var deltaY = typeof options.deltaY === 'number' ? options.deltaY : 0;
        var now = typeof options.now === 'number' ? options.now : 0;
        var zone = options.zone === 'organizing' ? 'organizing' : 'executive';
        var bucket = typeof options.bucket === 'number' ? options.bucket : 0;
        var bucketUpdatedAt = typeof options.bucketUpdatedAt === 'number'
            ? options.bucketUpdatedAt
            : now;
        var cooldownUntil = typeof options.cooldownUntil === 'number'
            ? options.cooldownUntil
            : 0;
        var metrics = options.metrics || {};

        if (now - bucketUpdatedAt > WHEEL_BUCKET_RESET_MS) {
            bucket = 0;
        }

        bucket += deltaY;
        bucketUpdatedAt = now;

        if (now < cooldownUntil) {
            return {
                zone: zone,
                bucket: bucket,
                bucketUpdatedAt: bucketUpdatedAt,
                cooldownUntil: cooldownUntil,
                nextZone: null
            };
        }

        var nextZone = null;

        if (bucket >= WHEEL_DELTA_THRESHOLD && zone === 'executive') {
            nextZone = 'organizing';
        } else if (bucket <= -WHEEL_DELTA_THRESHOLD
            && zone === 'organizing'
            && canReturnToExecutiveFromWheel(metrics)) {
            nextZone = 'executive';
        }

        if (nextZone && nextZone !== zone) {
            return {
                zone: nextZone,
                bucket: 0,
                bucketUpdatedAt: now,
                cooldownUntil: now + WHEEL_TRANSITION_COOLDOWN_MS,
                nextZone: nextZone
            };
        }

        return {
            zone: zone,
            bucket: bucket,
            bucketUpdatedAt: bucketUpdatedAt,
            cooldownUntil: cooldownUntil,
            nextZone: null
        };
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

    function handleRootWheel(event) {
        if (!event || typeof event.deltaY !== 'number') {
            return;
        }

        var now = Date.now();
        var result = stepWheelGesture({
            deltaY: event.deltaY,
            now: now,
            zone: activeWorkZone,
            bucket: wheelDeltaBucket,
            bucketUpdatedAt: wheelBucketUpdatedAt,
            cooldownUntil: wheelTransitionCooldownUntil,
            metrics: getWheelMetrics()
        });

        wheelDeltaBucket = result.bucket;
        wheelBucketUpdatedAt = result.bucketUpdatedAt;
        wheelTransitionCooldownUntil = result.cooldownUntil;

        if (result.nextZone) {
            applyWorkZone(result.nextZone);
        }
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
        wheelDeltaBucket = 0;
        wheelBucketUpdatedAt = 0;
        wheelTransitionCooldownUntil = 0;
        applyWorkZone('executive');

        var root = document.getElementById(ROOT_ID);

        root.addEventListener('click', handleRootInteraction, true);
        root.addEventListener('focusin', handleRootInteraction, true);
        root.addEventListener('wheel', handleRootWheel, { passive: true });
    }

    function initExecutiveListsFocusModule() {
        bindExecutiveListsFocusModule();
    }

    var moduleExports = {
        WHEEL_DELTA_THRESHOLD: WHEEL_DELTA_THRESHOLD,
        WHEEL_BUCKET_RESET_MS: WHEEL_BUCKET_RESET_MS,
        WHEEL_TRANSITION_COOLDOWN_MS: WHEEL_TRANSITION_COOLDOWN_MS,
        EXECUTIVE_RETURN_SCROLL_Y_MAX: EXECUTIVE_RETURN_SCROLL_Y_MAX,
        EXECUTIVE_RETURN_PROPOSAL_TOP_MAX: EXECUTIVE_RETURN_PROPOSAL_TOP_MAX,
        setMuted: setMuted,
        setListsBodyCollapsed: setListsBodyCollapsed,
        getWheelMetrics: getWheelMetrics,
        canReturnToExecutiveFromWheel: canReturnToExecutiveFromWheel,
        stepWheelGesture: stepWheelGesture,
        applyWorkZone: applyWorkZone,
        activateFromTarget: activateFromTarget,
        handleRootInteraction: handleRootInteraction,
        handleRootWheel: handleRootWheel,
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
