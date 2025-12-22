/**
 * Notifications Popover Handler
 * 
 * Manages the notifications bell button and popover display.
 * Handles fetching, rendering, and marking notifications as read.
 */

(function() {
    'use strict';

    // Type labels mapping
    const TYPE_LABELS = {
        'pending': 'Pendientes de confirmación',
        'confirmed': 'Confirmaciones',
        'cancelled': 'Cancelaciones'
    };

    /**
     * Fetch unread notifications data
     * @returns {Promise<Object|null>} Notification data or null on error
     */
    function fetchNotificationsData() {
        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const url = ajaxurl + '?action=aa_get_unread_notifications';

        return fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    return data.data;
                }
                return null;
            })
            .catch(error => {
                console.error('[Notifications] Error fetching notifications:', error);
                return null;
            });
    }

    /**
     * Update badge with notification count
     */
    function updateNotificationsBadge() {
        const badge = document.getElementById('aa-notifications-badge');
        if (!badge) {
            console.warn('[Notifications] Badge element not found');
            return;
        }

        fetchNotificationsData().then(data => {
            if (!data) {
                badge.style.display = 'none';
                return;
            }

            const total = data.total || 0;
            
            if (total === 0) {
                badge.style.display = 'none';
            } else {
                badge.style.display = 'block';
                badge.textContent = total.toString();
            }
        });
    }

    /**
     * Render notifications list in popover
     */
    function renderNotificationsList() {
        const popover = document.getElementById('aa-notifications-popover');
        if (!popover) {
            console.warn('[Notifications] Popover element not found');
            return;
        }

        // Find the content container (the div inside popover that contains the list)
        const contentContainer = popover.querySelector('.aa-notifications-content');
        if (!contentContainer) {
            console.warn('[Notifications] Content container not found');
            return;
        }

        fetchNotificationsData().then(data => {
            if (!data || data.total === 0 || !data.by_type || Object.keys(data.by_type).length === 0) {
                // No notifications - show empty state
                contentContainer.innerHTML = '<div class="text-sm text-gray-500 text-center py-4">No hay notificaciones por ahora</div>';
                return;
            }

            // Build list HTML
            let listHTML = '<div class="space-y-1">';
            
            // Iterate through types in order: pending, confirmed, cancelled
            const typeOrder = ['pending', 'confirmed', 'cancelled'];
            
            typeOrder.forEach(type => {
                const count = data.by_type[type] || 0;
                if (count > 0) {
                    const label = TYPE_LABELS[type] || type;
                    listHTML += `
                        <button 
                            type="button"
                            class="aa-notification-item w-full text-left px-3 py-2 rounded hover:bg-gray-50 transition-colors cursor-pointer text-sm text-gray-700 hover:text-gray-900"
                            data-type="${type}"
                            role="button"
                            aria-label="${label} (${count})"
                        >
                            ${label} (${count})
                        </button>
                    `;
                }
            });
            
            listHTML += '</div>';
            contentContainer.innerHTML = listHTML;

            // Attach click handlers to notification items
            const items = contentContainer.querySelectorAll('.aa-notification-item');
            items.forEach(item => {
                item.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    if (type) {
                        markNotificationsAsRead(type);
                    }
                });
            });
        });
    }

    /**
     * Mark notifications as read by type
     * @param {string} type - Notification type (pending, confirmed, cancelled)
     */
    function markNotificationsAsRead(type) {
        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const url = ajaxurl + '?action=aa_mark_notifications_as_read&type=' + encodeURIComponent(type);

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh badge and list
                    updateNotificationsBadge();
                    renderNotificationsList();
                    
                    // TODO: open appointments modal filtered by type
                    // AAAppointmentsModal.open({ type: type });
                } else {
                    console.error('[Notifications] Error marking as read:', data.data?.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('[Notifications] Error marking notifications as read:', error);
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
            const isHidden = popover.classList.contains('hidden');
            popover.classList.toggle('hidden');
            
            // When opening, refresh the list
            if (isHidden) {
                renderNotificationsList();
            }
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

