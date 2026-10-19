<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * GET /api/admin/claims.php — Master Claim List for Admin Adjudication
 *
 * Authorization: Admin only.
 * Returns full claim details including claimant proof description,
 * item's identifying_features (for comparison), and admin review info.
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

require_admin();

$page   = max(1, (int) ($_GET['page']     ?? 1));
$limit  = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$status = trim($_GET['status'] ?? '');

$where  = [];
$params = [];
$allowedStatuses = ['pending', 'under_review', 'approved', 'rejected', 'cancelled'];
if (in_array($status, $allowedStatuses, true)) {
    $where[]           = 'cl.status = :status';
    $params[':status'] = $status;
}
$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $pdo = get_db_connection();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM claims cl {$whereClause}");
    $countStmt->execute($params);
    $total      = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($total / $limit);

    $params[':limit']  = $limit;
    $params[':offset'] = $offset;

    $stmt = $pdo->prepare(
        "SELECT
             cl.id,
             cl.item_id,
             cl.claimant_id,
             cl.lost_item_id,
             cl.proof_description,
             cl.proof_image_path,
             cl.status,
             cl.admin_notes,
             cl.reviewed_by_admin_id,
             cl.reviewed_at,
             cl.handover_date,
             cl.created_at,
             -- Claimant info
             cu.full_name     AS claimant_name,
             cu.email         AS claimant_email,
             cu.student_id    AS claimant_student_id,
             cu.department    AS claimant_department,
             cu.phone         AS claimant_phone,
             -- Found item info (with private features for admin comparison)
             fi.title                AS item_title,
             fi.identifying_features AS item_private_features,
             fi.status               AS item_status,
             fi.custody_status       AS item_custody,
             -- Reviewing admin
             au.full_name AS reviewed_by_name
         FROM claims cl
         JOIN users u2 ON u2.id = cl.claimant_id  -- alias avoided
         JOIN users cu ON cu.id = cl.claimant_id
         JOIN items fi ON fi.id = cl.item_id
         LEFT JOIN users au ON au.id = cl.reviewed_by_admin_id
         {$whereClause}
         ORDER BY
             FIELD(cl.status, 'pending','under_review','approved','rejected','cancelled'),
             cl.created_at ASC
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
    $claims = $stmt->fetchAll();

    // Add proof image URL (protected, not direct path)
    foreach ($claims as &$claim) {
        $claim['proof_image_url'] = !empty($claim['proof_image_path'])
            ? url('api/claims/proof-view.php?claim_id=' . $claim['id'])
            : null;
    }
    unset($claim);

    json_response(true, 'Claims retrieved.', [
        'claims'     => $claims,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => $totalPages],
    ]);

} catch (PDOException $e) {
    error_log('Admin/claims GET error: ' . $e->getMessage());
    json_response(false, 'Failed to retrieve claims.', null, null, 500);
}
