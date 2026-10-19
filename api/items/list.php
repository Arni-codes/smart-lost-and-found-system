<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * GET /api/items/list.php — Filter & Paginate Public Item Catalog
 *
 * Privacy rule: identifying_features is NEVER returned.
 * Public access is allowed; authenticated users see item ownership flags.
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

// ── Query parameters ─────────────────────────────────────────────────────────
$itemType      = trim($_GET['type']           ?? '');
$categoryId    = filter_input(INPUT_GET, 'category_id',  FILTER_VALIDATE_INT);
$locationId    = filter_input(INPUT_GET, 'location_id',  FILTER_VALIDATE_INT);
$custodyStatus = trim($_GET['custody_status'] ?? '');
$status        = trim($_GET['status']         ?? 'open');
$search        = trim($_GET['search']         ?? '');
$dateFrom      = trim($_GET['date_from']      ?? '');
$dateTo        = trim($_GET['date_to']        ?? '');
$page          = max(1, (int) ($_GET['page']  ?? 1));
$limit         = min(50, max(1, (int) ($_GET['limit'] ?? 12)));
$offset        = ($page - 1) * $limit;

// ── Build query ──────────────────────────────────────────────────────────────
try {
    $pdo = get_db_connection();

    $where  = [];
    $params = [];

    // Status filter (default to open items)
    $allowedStatuses = ['open', 'pending_claim', 'claimed', 'closed', 'rejected', ''];
    if (in_array($status, $allowedStatuses, true) && $status !== '') {
        $where[]            = 'i.status = :status';
        $params[':status']  = $status;
    }

    // Item type
    if (in_array($itemType, ['lost', 'found'], true)) {
        $where[]           = 'i.item_type = :type';
        $params[':type']   = $itemType;
    }

    // Category
    if ($categoryId && $categoryId > 0) {
        $where[]             = 'i.category_id = :cat';
        $params[':cat']      = $categoryId;
    }

    // Location
    if ($locationId && $locationId > 0) {
        $where[]             = 'i.location_id = :loc';
        $params[':loc']      = $locationId;
    }

    // Custody status
    $allowedCustody = ['with_finder', 'submitted_to_security', 'returned_to_owner', 'not_applicable'];
    if (in_array($custodyStatus, $allowedCustody, true)) {
        $where[]                 = 'i.custody_status = :custody';
        $params[':custody']      = $custodyStatus;
    }

    // Date range
    if (!empty($dateFrom) && strtotime($dateFrom)) {
        $where[]               = 'i.incident_date >= :date_from';
        $params[':date_from']  = $dateFrom;
    }
    if (!empty($dateTo) && strtotime($dateTo)) {
        $where[]               = 'i.incident_date <= :date_to';
        $params[':date_to']    = $dateTo;
    }

    // Full-text search
    if (!empty($search)) {
        $where[]               = 'MATCH(i.title, i.description) AGAINST (:search IN BOOLEAN MODE)';
        // Append wildcard for partial-word matches on short queries
        $params[':search']     = preg_replace('/\s+/', '* ', trim($search)) . '*';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $countSql = "SELECT COUNT(*) FROM items i {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalItems / $limit);

    // Main query — identifying_features is deliberately excluded
    $sql = "SELECT
                i.id,
                i.user_id,
                i.item_type,
                i.title,
                i.description,
                i.incident_date,
                i.incident_time,
                i.image_path,
                i.custody_status,
                i.status,
                i.created_at,
                c.name  AS category_name,
                c.icon  AS category_icon,
                l.building      AS location_building,
                l.campus_zone   AS location_zone,
                l.specific_area AS location_area,
                u.full_name     AS reporter_name
            FROM items i
            LEFT JOIN categories c ON c.id = i.category_id
            LEFT JOIN locations  l ON l.id = i.location_id
            LEFT JOIN users      u ON u.id = i.user_id
            {$whereClause}
            ORDER BY i.created_at DESC
            LIMIT :limit OFFSET :offset";

    $params[':limit']  = $limit;
    $params[':offset'] = $offset;

    $stmt = $pdo->prepare($sql);
    // Bind limit & offset as integers
    foreach ($params as $key => $val) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, (int) $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $val);
        }
    }
    $stmt->execute();
    $items = $stmt->fetchAll();

    // Annotate with ownership flag for authenticated users
    $currentUserId = $_SESSION['user_id'] ?? null;
    foreach ($items as &$item) {
        $item['is_owner']    = ($currentUserId && (int) $item['user_id'] === (int) $currentUserId);
        $item['image_url']   = !empty($item['image_path']) ? url($item['image_path']) : null;
        // identifying_features is intentionally never added here
        unset($item['user_id']); // do not expose user_id to public
    }
    unset($item);

    json_response(true, 'Items retrieved successfully.', [
        'items'       => $items,
        'pagination'  => [
            'page'        => $page,
            'limit'       => $limit,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
        ],
    ]);

} catch (PDOException $e) {
    error_log('Items/list error: ' . $e->getMessage());
    json_response(false, 'Failed to retrieve items. Please try again.', null, null, 500);
}
