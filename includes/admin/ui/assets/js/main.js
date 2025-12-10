/**
 * Admin UI - Shared JavaScript
 * 
 * This file contains:
 * - Shared utilities
 * - Common event handlers
 * - AJAX helpers
 * - UI helpers
 */

(function() {
    'use strict';

    // Admin UI namespace
    window.AAAdmin = window.AAAdmin || {};

    /**
     * AJAX helper for admin requests
     */
    AAAdmin.ajax = function(action, data, callback) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', window.aaAdminNonce || '');

        if (data) {
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });
        }

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(callback)
        .catch(error => {
            console.error('AJAX Error:', error);
        });
    };

    /**
     * Show notification message
     */
    AAAdmin.notify = function(message, type = 'info') {
        // Implementation for notifications
        console.log(`[${type}] ${message}`);
    };

    /**
     * Iframe auto-resize functionality
     * Sends content height to parent window for dynamic iframe sizing
     */
    AAAdmin.iframeResize = function() {
        // Only run if we're inside an iframe
        if (window.self === window.top) return;

        let lastHeight = 0;
        let resizeTimeout = null;

        function sendHeight() {
            // Get the actual content height
            const body = document.body;
            const html = document.documentElement;

            // Use the maximum of various height measurements
            const height = Math.max(
                body.scrollHeight,
                body.offsetHeight,
                html.clientHeight,
                html.scrollHeight,
                html.offsetHeight
            );

            // Only send if height changed (avoid unnecessary updates)
            if (height !== lastHeight) {
                lastHeight = height;

                // Send height to parent window
                window.parent.postMessage({
                    type: 'aa-iframe-resize',
                    height: height,
                    iframeId: 'aa-settings-iframe'
                }, '*');
            }
        }

        // Send height on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', sendHeight);
        } else {
            sendHeight();
        }

        // Send height when content changes (debounced)
        function debouncedSendHeight() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(sendHeight, 100);
        }

        // Watch for content changes
        if (typeof ResizeObserver !== 'undefined') {
            const resizeObserver = new ResizeObserver(debouncedSendHeight);
            resizeObserver.observe(document.body);
        }

        // Also watch for DOM mutations (dynamic content)
        if (typeof MutationObserver !== 'undefined') {
            const mutationObserver = new MutationObserver(debouncedSendHeight);
            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        }

        // Send height on window resize (in case of viewport changes)
        window.addEventListener('resize', debouncedSendHeight);
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Admin UI initialized');
        
        // Initialize iframe auto-resize
        AAAdmin.iframeResize();
    });

})();

