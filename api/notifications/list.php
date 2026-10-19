<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * GET /api/notifications/list.php — Paginated Notifications for Authenticated User
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

require_login();

$userId    = (int) $_SESSION['user_id'];
$page      = max(1, (int) ($_GET['page']        ?? 1));
$limit     = min(50, max(1, (int) ($_GET['limit'] ?? 15)));
$offset    = ($page - 1) * $limit;
$unreadOnly = !empty($_GET['unread_only']) && $_GET['unread_only'] === '1';

try {
    $pdo    = get_db_connection();
    $where  = 'WHERE user_id = :uid';
    $params = [':uid' => $userId];

    if ($unreadOnly) {
        $where           .= ' AND is_read = 0';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications {$where}");
    $countStmt->execute($params);
    $total      = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($total / $limit);

    $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
    $unreadStmt->execute([':uid' => $userId]);
    $unreadCount = (int) $unreadStmt->fetchColumn();

    $params[':limit']  = $limit;
    $params[':offset'] = $offset;

    $stmt = $pdo->prepare(
        "SELECT id, title, message, type, reference_id, reference_url, is_read, created_at
         FROM notifications
         {$where}
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $val) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, (int) $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $val);
        }
    }
    $stmt->execute();
    $notifications = $stmt->fetchAll();

    json_response(true, 'Notifications retrieved.', [
        'notifications' => $notifications,
        'unread_count'  => $unreadCount,
        'pagination'    => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => $totalPages,
        ],
    ]);

} catch (PDOException $e) {
    error_log('Notifications/list error: ' . $e->getMessage());
    json_response(false, 'Failed to retrieve notifications.', null, null, 500);
}
