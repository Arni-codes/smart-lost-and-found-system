<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/notifications/mark-read.php — Mark Notification(s) as Read
 *
 * Payload:
 *   notification_id (int, optional) — marks a single notification
 *   all (1, optional)               — marks all user's notifications as read
 *   csrf_token
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

require_login();
csrf_verify();

$userId         = (int) $_SESSION['user_id'];
$notifId        = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
$markAll        = !empty($_POST['all']) && $_POST['all'] === '1';

try {
    $pdo = get_db_connection();

    if ($markAll) {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
        $stmt->execute([':uid' => $userId]);
        $count = $stmt->rowCount();
        json_response(true, "All {$count} notification(s) marked as read.", ['updated' => $count]);
    } elseif ($notifId && $notifId > 0) {
        // Verify ownership before marking
        $stmt = $pdo->prepare('SELECT id FROM notifications WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute([':id' => $notifId, ':uid' => $userId]);
        if (!$stmt->fetch()) {
            json_response(false, 'Notification not found or does not belong to you.', null, null, 404);
        }

        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $notifId, ':uid' => $userId]);
        json_response(true, 'Notification marked as read.', ['notification_id' => $notifId]);
    } else {
        json_response(false, 'Provide either notification_id or all=1.', null, null, 400);
    }

} catch (PDOException $e) {
    error_log('Notifications/mark-read error: ' . $e->getMessage());
    json_response(false, 'Failed to update notifications. Please try again.', null, null, 500);
}
