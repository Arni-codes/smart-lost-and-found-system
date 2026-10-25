<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * GET /api/items/get.php?id={id} — Fetch Single Item Details
 *
 * Privacy rule: identifying_features is included ONLY when the caller
 * is the item's owner or an admin. All other callers receive null.
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

init_session();

$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$itemId || $itemId <= 0) {
    json_response(false, 'A valid item ID is required.', null, null, 400);
}

try {
    $pdo  = get_db_connection();
    $stmt = $pdo->prepare(
        'SELECT
             i.id,
             i.user_id,
             i.item_type,
             i.title,
             i.description,
             i.identifying_features,
             i.incident_date,
             i.incident_time,
             i.image_path,
             i.custody_status,
             i.status,
             i.created_at,
             i.updated_at,
             c.id   AS category_id,
             c.name AS category_name,
             c.icon AS category_icon,
             l.id            AS location_id,
             l.building      AS location_building,
             l.campus_zone   AS location_zone,
             l.specific_area AS location_area,
             u.full_name     AS reporter_name,
             u.student_id    AS reporter_student_id
         FROM items i
         LEFT JOIN categories c ON c.id = i.category_id
         LEFT JOIN locations  l ON l.id = i.location_id
         LEFT JOIN users      u ON u.id = i.user_id
         WHERE i.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        json_response(false, 'Item not found.', null, null, 404);
    }

    // ── Privacy gate ────────────────────────────────────────────────────────
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $isOwner       = $currentUserId && $currentUserId === (int) $item['user_id'];
    $isAdminUser   = is_admin();
    $canSeePrivate = $isOwner || $isAdminUser;

    if (!$canSeePrivate) {
        $item['identifying_features'] = null;
    }

    // Add computed fields
    $item['is_owner']    = $isOwner;
    $item['can_edit']    = $canSeePrivate;
    $item['image_path']  = !empty($item['image_path']) ? basename($item['image_path']) : null;
    $item['image_url']   = !empty($item['image_path']) ? url('uploads/items/' . $item['image_path']) : null;

    // Never expose internal user_id in the public payload
    if (!$isAdminUser) {
        unset($item['user_id'], $item['reporter_student_id']);
    }

    json_response(true, 'Item retrieved successfully.', $item);

} catch (PDOException $e) {
    error_log('Items/get error: ' . $e->getMessage());
    json_response(false, 'Failed to retrieve item details. Please try again.', null, null, 500);
}
