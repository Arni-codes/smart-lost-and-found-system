<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * GET|POST /api/admin/items.php — Admin Item Moderation
 *
 * GET  — list all items with full details (including identifying_features)
 * POST — close or reject an open item report
 *
 * Authorization: Admin only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../services/NotificationService.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    json_response(false, 'Method not allowed.', null, null, 405);
}

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

    if ($status !== '' && !in_array($status, ['open', 'pending_claim', 'claimed', 'closed', 'rejected'], true)) {
        json_response(false, 'Invalid item status filter.', null, null, 422);
    }
    if ($type !== '' && !in_array($type, ['lost', 'found'], true)) {
        json_response(false, 'Invalid item type filter.', null, null, 422);
    }

    $where  = [];
    $params = [];

    if ($status !== '') {
        $where[]          = 'i.status = :status';
        $params[':status']= $status;
    }
    if ($type !== '') {
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
                    u.full_name AS reporter_name
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
            $item['image_path'] = !empty($item['image_path']) ? basename($item['image_path']) : null;
            $item['image_url']  = !empty($item['image_path']) ? url('uploads/items/' . $item['image_path']) : null;
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

    if (!in_array($newStatus, ['closed', 'rejected'], true)) {
        json_response(false, 'Administrators may only close or reject an open item. Claim and handover statuses are workflow-controlled.', null, null, 422);
    }
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
        json_response(false, 'A moderation reason between 5 and 500 characters is required.', null, null, 422);
    }

    $pdo = null;
    try {
        $pdo  = get_db_connection();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, status FROM items WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            $pdo->rollBack();
            json_response(false, 'Item not found.', null, null, 404);
        }
        if ($item['status'] !== 'open') {
            $pdo->rollBack();
            json_response(false, 'Only open items may be closed or rejected through moderation.', null, null, 409);
        }

        $stmt = $pdo->prepare("UPDATE items SET status = :status WHERE id = :id AND status = 'open'");
        $stmt->execute([':status' => $newStatus, ':id' => $itemId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Item status changed while moderation was in progress.');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
             VALUES (:uid, 'ITEM_STATUS_CHANGED', 'item', :item_id, :details, :ip)"
        );
        $stmt->execute([
            ':uid' => $adminId,
            ':item_id' => $itemId,
            ':details' => json_encode([
                'from' => $item['status'],
                'to' => $newStatus,
                'reason' => $reason,
            ]),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $reporterStmt = $pdo->prepare('SELECT user_id, title FROM items WHERE id = :id');
        $reporterStmt->execute([':id' => $itemId]);
        $reporter = $reporterStmt->fetch();
        if (!$reporter || !NotificationService::send(
            (int) $reporter['user_id'],
            'Your Item Report Was Moderated',
            'Your report "' . $reporter['title'] . '" was ' . $newStatus . '. Reason: ' . $reason,
            'admin_alert',
            $itemId,
            url('my-reports.php')
        )) {
            throw new RuntimeException('Unable to notify the report owner.');
        }

        $pdo->commit();

        json_response(true, 'Item status updated to "' . $newStatus . '".', ['item_id' => $itemId, 'status' => $newStatus]);

    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin/items POST error: ' . $e->getMessage());
        json_response(false, 'Failed to update item status.', null, null, 500);
    }
}
