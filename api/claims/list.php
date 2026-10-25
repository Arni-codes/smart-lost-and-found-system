<?php
/**
 * GET /api/claims/list.php — Paginated claims submitted by the current user.
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

$userId = (int) $_SESSION['user_id'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 15)));
$offset = ($page - 1) * $limit;
$status = trim($_GET['status'] ?? '');
$allowedStatuses = ['pending', 'under_review', 'approved', 'rejected', 'cancelled'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    json_response(false, 'Invalid claim status filter.', null, null, 422);
}

try {
    $pdo = get_db_connection();
    $where = 'WHERE c.claimant_id = :uid';
    $params = [':uid' => $userId];
    if ($status !== '') {
        $where .= ' AND c.status = :status';
        $params[':status'] = $status;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM claims c {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT c.id, c.item_id, c.lost_item_id, c.proof_description, c.proof_image_path, c.status,
                c.admin_notes, c.reviewed_at, c.handover_date, c.created_at, c.updated_at,
                i.title AS item_title, i.status AS item_status
         FROM claims c JOIN items i ON i.id = c.item_id
         {$where}
         ORDER BY c.created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $claims = $stmt->fetchAll();
    foreach ($claims as &$claim) {
        $claim['proof_image_url'] = !empty($claim['proof_image_path'])
            ? url('api/claims/proof-view.php?claim_id=' . $claim['id'])
            : null;
        unset($claim['proof_image_path']);
    }
    unset($claim);

    json_response(true, 'Your claims were retrieved.', [
        'claims' => $claims,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) ceil($total / $limit),
        ],
    ]);
} catch (PDOException $e) {
    error_log('Claims/list error: ' . $e->getMessage());
    json_response(false, 'Failed to retrieve your claims.', null, null, 500);
}
