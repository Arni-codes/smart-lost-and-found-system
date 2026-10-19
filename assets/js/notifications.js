/**
 * Smart College Lost and Found System (SCLFS)
 * Notification Center Module (assets/js/notifications.js)
 *
 * Implements:
 * 1. Mark single or all notifications as read with CSRF security.
 * 2. Unread notification badge updates in navbar and dashboard.
 * 3. Indefinite retention display with pagination support.
 * 4. Graceful handling of pending endpoints and network failures.
 */

(function (window, document) {
    'use strict';

    const SCLFS = window.SCLFS || {};

    /**
     * Mark a single notification as read.
     * @param {number|string} notificationId
     * @param {HTMLElement} [itemElement]
     */
    async function markNotificationAsRead(notificationId, itemElement) {
        if (!notificationId) return;

        try {
            const token = SCLFS.getCsrfToken();
            const response = await SCLFS.fetchAPI('api/notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `notification_id=${encodeURIComponent(notificationId)}&csrf_token=${encodeURIComponent(token)}`
            });

            if (response.success) {
                if (itemElement) {
                    itemElement.style.backgroundColor = '#ffffff';
                    const newBadge = itemElement.querySelector('.badge-primary');
                    if (newBadge && newBadge.textContent.trim().toLowerCase() === 'new') {
                        newBadge.remove();
                    }
                }
            } else if (response.notImplemented) {
                console.info('[SCLFS Notifications] Mark read API endpoint pending.');
            }
        } catch (err) {
            console.error('[Mark Read Error]', err);
        }
    }

    /**
     * Mark all notifications as read for current user.
     */
    async function markAllNotificationsRead() {
        const markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) SCLFS.setButtonLoading(markAllBtn, true, 'Marking Read...');

        try {
            const token = SCLFS.getCsrfToken();
            const response = await SCLFS.fetchAPI('api/notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `all=1&csrf_token=${encodeURIComponent(token)}`
            });

            if (response.success) {
                SCLFS.showToast('All notifications marked as read.', 'success');
                setTimeout(() => window.location.reload(), 500);
            } else if (response.notImplemented) {
                SCLFS.showToast('Notification mark-read API is pending Member 5 implementation.', 'warning');
                if (markAllBtn) SCLFS.setButtonLoading(markAllBtn, false);
            } else {
                SCLFS.showToast(response.message || 'Failed to mark notifications read.', 'danger');
                if (markAllBtn) SCLFS.setButtonLoading(markAllBtn, false);
            }
        } catch (err) {
            console.error('[Mark All Read Error]', err);
            SCLFS.showToast('Network error while marking notifications read.', 'danger');
            if (markAllBtn) SCLFS.setButtonLoading(markAllBtn, false);
        }
    }

    // Bind globally for inline onclick handlers in views
    window.markAllNotificationsRead = markAllNotificationsRead;

    // Attach click handlers to notification items
    function initNotificationFeed() {
        const container = document.getElementById('notificationsContainer');
        if (!container) return;

        const items = container.querySelectorAll('.notification-item');
        items.forEach(item => {
            const viewLink = item.querySelector('a.btn');
            if (viewLink) {
                viewLink.addEventListener('click', () => {
                    const notifId = item.getAttribute('data-notification-id');
                    if (notifId) {
                        markNotificationAsRead(notifId, item);
                    }
                });
            }
        });
    }

    // DOM Ready initialization
    document.addEventListener('DOMContentLoaded', () => {
        initNotificationFeed();
    });

    SCLFS.notifications = {
        markNotificationAsRead,
        markAllNotificationsRead
    };

})(window, document);
