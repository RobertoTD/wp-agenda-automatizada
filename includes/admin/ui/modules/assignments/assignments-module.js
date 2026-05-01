/**
 * Assignments Module - JavaScript
 * 
 * Main module orchestrator for assignments.
 * Individual sections (areas, staff, assignments) are handled by their own JS files.
 */

(function() {
    'use strict';

    const SETUP_FOCUS_TARGETS = {
        staff: {
            root: '#aa-staff-root',
            input: '#aa-staff-name-input'
        },
        staff_services: {
            root: '#aa-staff-root',
            input: '.aa-staff-toggle-services'
        },
        services: {
            root: '#aa-services-root',
            input: '#aa-service-name-input'
        },
        areas: {
            root: '#aa-areas-root',
            input: '#aa-area-name-input'
        }
    };

    /**
     * Initialize the assignments module
     */
    function initAssignmentsModule() {
        const root = document.getElementById('aa-assignments-root');
        
        // Fail safely if root doesn't exist
        if (!root) {
            console.warn('[Assignments Module] Root element #aa-assignments-root not found');
            return;
        }

        // The root will be populated by assignments-section.js
        // This module just ensures the root is ready
        console.log('[Assignments Module] Root element ready');

        applySetupFocusFromUrl();
    }

    /**
     * Reads the optional setup_focus query param used by AI chat actions.
     */
    function applySetupFocusFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const focusKey = params.get('setup_focus');
        const target = focusKey ? SETUP_FOCUS_TARGETS[focusKey] : null;

        if (!target) {
            return;
        }

        // Wait one frame so <details> layout is stable after module boot.
        window.requestAnimationFrame(function() {
            focusSetupTarget(target);
        });
    }

    /**
     * Opens, scrolls, highlights and focuses a known setup target.
     *
     * @param {{root:string,input:string}} target
     */
    function focusSetupTarget(target) {
        const root = document.querySelector(target.root);
        if (!root) {
            return;
        }

        const details = root.closest('details');
        if (details && !details.open) {
            details.open = true;
        }

        const highlightTarget = details || root;

        highlightTarget.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        applyTemporaryHighlight(highlightTarget);

        const input = document.querySelector(target.input);
        if (input && typeof input.focus === 'function') {
            window.setTimeout(function() {
                input.focus({ preventScroll: true });
            }, 350);
        }
    }

    /**
     * Applies a temporary inline highlight without relying on Tailwind classes.
     *
     * @param {HTMLElement} element
     */
    function applyTemporaryHighlight(element) {
        const previousBoxShadow = element.style.boxShadow;
        const previousBorderColor = element.style.borderColor;
        const previousTransition = element.style.transition;

        element.style.transition = 'box-shadow 180ms ease, border-color 180ms ease';
        element.style.boxShadow = '0 0 0 4px rgba(79, 70, 229, 0.22)';
        element.style.borderColor = 'rgb(99, 102, 241)';

        window.setTimeout(function() {
            element.style.boxShadow = previousBoxShadow;
            element.style.borderColor = previousBorderColor;
            element.style.transition = previousTransition;
        }, 2200);
    }

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAssignmentsModule);
    } else {
        // DOM already ready
        initAssignmentsModule();
    }

})();
