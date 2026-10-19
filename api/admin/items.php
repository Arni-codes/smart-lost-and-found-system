<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * GET|POST /api/admin/items.php — Admin Item Moderation
 *
 * GET  — list all items with full details (including identifying_features)
 * POST — update item status (reject spam, reopen, change custody)
 *
 * Authorization: Admin only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

require_admin();
$adminId = (int) $_SESSION['user_id'];

// ─── GET: List items ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page   = max(1, (int) ($_GET['page']     ?? 1));
    $limit  = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $type   = trim($_GET['type']   ?? '');

    $where  = [];
    $params = [];

    if (!empty($status) && in_array($status, ['open','pending_claim','claimed','closed','rejected'], true)) {
        $where[]          = 'i.status = :status';
        $params[':status']= $status;
    }
    if (in_array($type, ['lost','found'], true)) {
        $where[]         = 'i.item_type = :type';
        $params[':type'] = $type;
    }
    if (!empty($search)) {
        $where[]           = 'MATCH(i.title, i.description) AGAINST (:search IN BOOLEAN MODE)';
        $params[':search'] = preg_replace('/\s+/', '* ', trim($search)) . '*';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $pdo = get_db_connection();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM items i {$whereClause}");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / $limit);

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $stmt = $pdo->prepare(
            "SELECT i.*, c.name AS category_name, l.building, l.campus_zone,
                    u.full_name AS reporter_name, u.email AS reporter_email,
                    u.student_id AS reporter_student_id
             FROM items i
             LEFT JOIN categories c ON c.id = i.category_id
             LEFT JOIN locations  l ON l.id = i.location_id
             LEFT JOIN users      u ON u.id = i.user_id
             {$whereClause}
             ORDER BY i.created_at DESC
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
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['image_url'] = !empty($item['image_path']) ? url($item['image_path']) : null;
        }
        unset($item);

        json_response(true, 'Items retrieved.', [
            'items'      => $items,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => $totalPages],
        ]);

    } catch (PDOException $e) {
        error_log('Admin/items GET error: ' . $e->getMessage());
        json_response(false, 'Failed to retrieve items.', null, null, 500);
    }
}

// ─── POST: Moderate item ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $itemId    = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $newStatus = trim($_POST['status'] ?? '');
    $reason    = trim($_POST['reason'] ?? '');

    if (!$itemId || $itemId <= 0) {
        json_response(false, 'A valid item ID is required.', null, null, 400);
    }

    $allowedStatuses = ['open', 'pending_claim', 'claimed', 'closed', 'rejected'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        json_response(false, 'Invalid status value.', null, null, 422);
    }

    try {
        $pdo  = get_db_connection();
        $stmt = $pdo->prepare('SELECT id, status FROM items WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            json_response(false, 'Item not found.', null, null, 404);
        }

        $stmt = $pdo->prepare('UPDATE items SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $newStatus, ':id' => $itemId]);

        log_audit_event('ITEM_STATUS_CHANGED', 'item', $itemId, [
            'from'       => $item['status'],
            'to'         => $newStatus,
            'reason'     => $reason,
            'changed_by' => $adminId,
        ]);

        json_response(true, 'Item status updated to "' . $newStatus . '".', ['item_id' => $itemId, 'status' => $newStatus]);

    } catch (PDOException $e) {
        error_log('Admin/items POST error: ' . $e->getMessage());
        json_response(false, 'Failed to update item status.', null, null, 500);
    }
}
