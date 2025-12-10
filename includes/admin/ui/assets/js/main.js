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

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Admin UI initialized');
    });

})();

