<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/items/update.php — Edit Item Report Details
 *
 * Authorization: item owner OR admin.
 * After update, triggers MatchingService recalculation if description/
 * category/location changed.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../services/FileUploadService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

require_login();
csrf_verify();

$userId    = (int) $_SESSION['user_id'];
$userRole  = $_SESSION['role'] ?? 'student';
$itemId    = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);

if (!$itemId || $itemId <= 0) {
    json_response(false, 'A valid item ID is required.', null, null, 400);
}

try {
    $pdo = get_db_connection();

    // Fetch item and check ownership/permission
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        json_response(false, 'Item not found.', null, null, 404);
    }

    // Authorization: owner or admin only
    if ((int) $item['user_id'] !== $userId && $userRole !== 'admin') {
        json_response(false, 'You are not authorized to edit this item.', null, null, 403);
    }

    // Only editable in open or pending_claim states
    if (!in_array($item['status'], ['open', 'pending_claim'], true) && $userRole !== 'admin') {
        json_response(false, 'This item can no longer be edited (status: ' . $item['status'] . ').', null, null, 409);
    }

    // ── Collect updates ──────────────────────────────────────────────────────
    $title               = trim($_POST['title']                ?? '');
    $description         = trim($_POST['description']          ?? '');
    $identifyingFeatures = trim($_POST['identifying_features'] ?? '');
    $categoryId          = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $locationId          = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
    $incidentDate        = trim($_POST['incident_date']        ?? '');
    $incidentTime        = trim($_POST['incident_time']        ?? '') ?: null;

    $errors = [];

    if (empty($title) || mb_strlen($title) < 5 || mb_strlen($title) > 120) {
        $errors[] = 'Title must be between 5 and 120 characters.';
    }
    if (empty($description) || mb_strlen($description) < 15 || mb_strlen($description) > 2000) {
        $errors[] = 'Description must be between 15 and 2000 characters.';
    }
    if ($item['item_type'] === 'found' && empty($identifyingFeatures)) {
        $errors[] = 'Secret identifying features are required for found items.';
    }
    if (!empty($identifyingFeatures) && mb_strlen($identifyingFeatures) > 2000) {
        $errors[] = 'Identifying features may not exceed 2000 characters.';
    }
    if (!$categoryId || $categoryId <= 0) {
        $errors[] = 'Please select a valid category.';
    }
    if (!$locationId || $locationId <= 0) {
        $errors[] = 'Please select a valid campus location.';
    }
    if (empty($incidentDate)) {
        $errors[] = 'Incident date is required.';
    } else {
        $dateTs  = strtotime($incidentDate);
        $todayTs = strtotime(date('Y-m-d'));
        if ($dateTs === false || $dateTs > $todayTs) {
            $errors[] = 'Incident date cannot be in the future.';
        } elseif ($dateTs < strtotime('-180 days')) {
            $errors[] = 'Incident date cannot be more than 180 days in the past.';
        }
    }

    if (!empty($errors)) {
        json_response(false, 'Validation failed.', null, $errors, 422);
    }

    // ── Handle optional image replacement ────────────────────────────────────
    $imagePath = $item['image_path']; // keep existing by default
    if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = FileUploadService::handleUpload($_FILES['image'], 'items');
        if (!$upload['ok']) {
            json_response(false, $upload['error'], null, null, 422);
        }
        // Delete old image
        FileUploadService::deleteFile($item['image_path']);
        $imagePath = $upload['path'];
    }

    // Check if remove_image flag sent
    if (!empty($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        FileUploadService::deleteFile($item['image_path']);
        $imagePath = null;
    }

    // ── Detect if matching-relevant fields changed ───────────────────────────
    $matchRelevantChange = (
        (int) $item['category_id'] !== (int) $categoryId ||
        (int) $item['location_id'] !== (int) $locationId ||
        $item['description']       !== $description
    );

    // ── Update item ──────────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        'UPDATE items SET
             title                = :title,
             description          = :desc,
             identifying_features = :features,
             category_id          = :cat,
             location_id          = :loc,
             incident_date        = :date,
             incident_time        = :time,
             image_path           = :image
         WHERE id = :id'
    );
    $stmt->execute([
        ':title'    => $title,
        ':desc'     => $description,
        ':features' => $identifyingFeatures ?: null,
        ':cat'      => $categoryId,
        ':loc'      => $locationId,
        ':date'     => $incidentDate,
        ':time'     => $incidentTime,
        ':image'    => $imagePath,
        ':id'       => $itemId,
    ]);

    log_audit_event('ITEM_UPDATED', 'item', $itemId, [
        'updated_by' => $userId,
        'title'      => $title,
    ]);

    // ── Trigger match recalculation if relevant fields changed ───────────────
    $matchesFound = 0;
    if ($matchRelevantChange) {
        $matchingServicePath = __DIR__ . '/../../services/MatchingService.php';
        if (file_exists($matchingServicePath)) {
            require_once $matchingServicePath;
            if (class_exists('MatchingService') && method_exists('MatchingService', 'findMatchesForItem')) {
                try {
                    $matchesFound = MatchingService::findMatchesForItem($itemId) ?? 0;
                } catch (Throwable $me) {
                    error_log('MatchingService recalc error item ' . $itemId . ': ' . $me->getMessage());
                }
            }
        }
    }

    json_response(true, 'Item updated successfully.', [
        'item_id'       => $itemId,
        'matches_found' => $matchesFound,
    ]);

} catch (PDOException $e) {
    error_log('Items/update error: ' . $e->getMessage());
    json_response(false, 'Failed to update the item. Please try again.', null, null, 500);
}
