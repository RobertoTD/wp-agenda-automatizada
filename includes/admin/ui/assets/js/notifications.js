/**
 * Notifications Popover Handler
 * 
 * Manages the notifications bell button and popover display.
 * Placeholder implementation - no AJAX or database queries yet.
 */

(function() {
    'use strict';

    /**
     * Fetch unread notifications count and update badge
     */
    function updateNotificationsBadge() {
        const badge = document.getElementById('aa-notifications-badge');
        if (!badge) {
            console.warn('[Notifications] Badge element not found');
            return;
        }

        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const url = ajaxurl + '?action=aa_get_unread_notifications';

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const total = data.data.total || 0;
                    
                    if (total === 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.style.display = 'block';
                        badge.textContent = total.toString();
                    }
                } else {
                    console.warn('[Notifications] Invalid response format');
                    badge.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('[Notifications] Error fetching notifications:', error);
                badge.style.display = 'none';
            });
    }

    /**
     * Initialize notifications popover
     */
    function initNotifications() {
        const btnNotifications = document.getElementById('aa-btn-notifications');
        const popover = document.getElementById('aa-notifications-popover');
        const btnClose = document.getElementById('aa-btn-close-notifications');

        if (!btnNotifications || !popover || !btnClose) {
            console.warn('[Notifications] Required elements not found');
            return;
        }

        // Load initial badge count
        updateNotificationsBadge();

        /**
         * Toggle popover visibility
         */
        function togglePopover() {
            popover.classList.toggle('hidden');
        }

        /**
         * Close popover
         */
        function closePopover() {
            popover.classList.add('hidden');
        }

        /**
         * Handle click on notifications button
         */
        btnNotifications.addEventListener('click', function(e) {
            e.stopPropagation();
            togglePopover();
        });

        /**
         * Handle click on close button
         */
        btnClose.addEventListener('click', function(e) {
            e.stopPropagation();
            closePopover();
        });

        /**
         * Close popover when clicking outside
         */
        document.addEventListener('click', function(e) {
            if (!popover.contains(e.target) && !btnNotifications.contains(e.target)) {
                closePopover();
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotifications);
    } else {
        initNotifications();
    }
})();

