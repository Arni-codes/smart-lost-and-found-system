<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Notification Center View
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_middleware.php';

require_login(false);

$pageTitle = 'Notifications - ' . APP_SHORT_NAME;
$activeNav = 'notifications';

$currentUser = get_current_user_session();
$userId = (int)$currentUser['id'];

$notifications = [];
$unreadFilter = isset($_GET['unread']);
$loadError = false;

try {
    $pdo = get_db_connection();

    $sql = "SELECT * FROM notifications WHERE user_id = :uid";
    if ($unreadFilter) {
        $sql .= " AND is_read = 0";
    }
    $sql .= " ORDER BY created_at DESC LIMIT 30";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Notifications page error: ' . $e->getMessage());
    $loadError = true;
}

$extraScripts = ['assets/js/notifications.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8); max-width: 860px;">
    <?php if ($loadError): ?>
        <div class="alert alert-danger" role="alert">Notifications could not be loaded. Please try again later.</div>
    <?php endif; ?>
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('dashboard.php') ?>">Dashboard</a> &rsaquo; 
        <span>Notifications</span>
    </nav>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <span class="badge badge-primary" style="margin-bottom: var(--space-2);">Activity Alerts</span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
                Notification Center
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Stay informed about smart heuristic matches, claim decisions, and desk handovers.
            </p>
        </div>

        <div style="display: flex; gap: var(--space-2);">
            <a href="<?= url('notifications.php') ?>" class="btn btn-sm <?= !$unreadFilter ? 'btn-primary' : 'btn-secondary' ?>">
                All Alerts
            </a>
            <a href="<?= url('notifications.php?unread=1') ?>" class="btn btn-sm <?= $unreadFilter ? 'btn-primary' : 'btn-secondary' ?>">
                Unread Only
            </a>
            <button type="button" class="btn btn-outline btn-sm" id="markAllReadBtn" onclick="markAllNotificationsRead()">
                Mark All Read
            </button>
        </div>
    </div>

    <!-- Notification Feed Card -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($notifications)): ?>
                <div id="notificationsContainer" style="display: flex; flex-direction: column;">
                    <?php foreach ($notifications as $n): ?>
                        <div class="notification-item" 
                             style="padding: var(--space-5); border-bottom: 1px solid var(--color-border); display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4); background-color: <?= $n['is_read'] ? '#ffffff' : '#f0f9ff' ?>;">
                            <div style="display: flex; gap: var(--space-3); align-items: flex-start;">
                                <div style="font-size: 1.5rem; line-height: 1; flex-shrink: 0; padding-top: 2px;">
                                    <?php if ($n['type'] === 'match_found'): ?>
                                        &#128269;
                                    <?php elseif ($n['type'] === 'claim_status'): ?>
                                        &#128221;
                                    <?php elseif ($n['type'] === 'custody_update'): ?>
                                        &#128737;
                                    <?php else: ?>
                                        &#128276;
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text); margin-bottom: var(--space-1);">
                                        <?= e($n['title']) ?>
                                        <?php if (!$n['is_read']): ?>
                                            <span class="badge badge-primary" style="font-size: 0.625rem; margin-left: 6px;">New</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p style="font-size: 0.875rem; color: var(--color-text-muted); line-height: 1.5; margin-bottom: var(--space-2);">
                                        <?= e($n['message']) ?>
                                    </p>
                                    <div style="font-size: 0.75rem; color: var(--color-text-light);">
                                        <?= format_datetime($n['created_at']) ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($n['reference_id'])): ?>
                                <a href="<?= url('item-details.php?id=' . $n['reference_id']) ?>" class="btn btn-outline btn-sm" style="flex-shrink: 0;">
                                    View
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="border: none; margin: 0; padding: var(--space-12) var(--space-6);">
                    <div class="empty-state-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </div>
                    <h3 class="empty-state-title">No Notifications to Display</h3>
                    <p class="empty-state-desc">
                        You're all caught up! When matching items are reported or claim statuses change, you'll see alerts here.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function markAllNotificationsRead() {
    const token = window.getCsrfToken ? window.getCsrfToken() : '';
    fetch('<?= url("api/notifications/mark-read.php") ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': token
        },
        body: 'all=1&csrf_token=' + encodeURIComponent(token)
    }).then(res => res.json()).then(data => {
        if (data.success) {
            window.location.reload();
        } else if (window.showToast) {
            window.showToast(data.message || 'Error marking notifications', 'danger');
        }
    }).catch(err => {
        if (window.showToast) window.showToast('Network error', 'danger');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
