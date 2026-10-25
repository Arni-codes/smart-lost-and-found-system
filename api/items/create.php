<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/items/create.php — Create Lost or Found Item Report
 *
 * Accepts multipart/form-data. Validates all fields, uploads image,
 * inserts item, invokes MatchingService stub, and returns JSON.
 *
 * Authorization: Authenticated users only.
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

$userId = (int) $_SESSION['user_id'];

// ── Collect & trim inputs ────────────────────────────────────────────────────
$itemType            = trim($_POST['item_type']            ?? '');
$categoryId          = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
$locationId          = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
$title               = trim($_POST['title']                ?? '');
$description         = trim($_POST['description']          ?? '');
$identifyingFeatures = trim($_POST['identifying_features'] ?? '');
$incidentDate        = trim($_POST['incident_date']        ?? '');
$incidentTime        = trim($_POST['incident_time']        ?? '') ?: null;
$custodyStatus       = trim($_POST['custody_status']       ?? 'not_applicable');

// ── Validation ───────────────────────────────────────────────────────────────
$errors = [];

// Item type
if (!in_array($itemType, ['lost', 'found'], true)) {
    $errors[] = 'Item type must be either "lost" or "found".';
}

// Category
if (!$categoryId || $categoryId <= 0) {
    $errors[] = 'Please select a valid category.';
}

// Location
if (!$locationId || $locationId <= 0) {
    $errors[] = 'Please select a valid campus location.';
}

// Title
if (empty($title) || mb_strlen($title) < 5 || mb_strlen($title) > 120) {
    $errors[] = 'Item title must be between 5 and 120 characters.';
}

// Description
if (empty($description) || mb_strlen($description) < 15 || mb_strlen($description) > 2000) {
    $errors[] = 'Description must be between 15 and 2000 characters.';
}

// Identifying features — mandatory for found items
if ($itemType === 'found' && empty($identifyingFeatures)) {
    $errors[] = 'Secret identifying features are mandatory for found item reports.';
}
if (!empty($identifyingFeatures) && mb_strlen($identifyingFeatures) > 2000) {
    $errors[] = 'Identifying features may not exceed 2000 characters.';
}

// Incident date: required, not future, not older than 180 days
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

// Incident time format (HH:MM or HH:MM:SS)
if (!empty($incidentTime) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $incidentTime)) {
    $errors[] = 'Please provide a valid time in HH:MM format.';
}

// Custody status for found items
$allowedCustodyOnCreate = ['with_finder', 'submitted_to_security'];
if ($itemType === 'found' && !in_array($custodyStatus, $allowedCustodyOnCreate, true)) {
    $errors[] = 'Custody status must be "with_finder" or "submitted_to_security".';
}
if ($itemType === 'lost') {
    $custodyStatus = 'not_applicable';
}

if (!empty($errors)) {
    json_response(false, 'Validation failed. Please correct the errors and try again.', null, $errors, 422);
}

// ── Validate FK references exist in DB ──────────────────────────────────────
try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $categoryId]);
    if (!$stmt->fetch()) {
        json_response(false, 'The selected category does not exist.', null, null, 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM locations WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $locationId]);
    if (!$stmt->fetch()) {
        json_response(false, 'The selected campus location does not exist.', null, null, 422);
    }

} catch (PDOException $e) {
    error_log('Items/create FK check error: ' . $e->getMessage());
    json_response(false, 'Database error during validation. Please try again.', null, null, 500);
}

// ── Handle image upload ──────────────────────────────────────────────────────
$imagePath = null;
if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload = FileUploadService::handleUpload($_FILES['image'], 'items');
    if (!$upload['ok']) {
        json_response(false, $upload['error'], null, null, 422);
    }
    $imagePath = $upload['filename'];
}

// ── Insert item ──────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO items
         (user_id, item_type, category_id, location_id, title, description,
          identifying_features, incident_date, incident_time, image_path,
          custody_status, status)
         VALUES
         (:uid, :type, :cat, :loc, :title, :desc,
          :features, :date, :time, :image,
          :custody, :status)'
    );
    $stmt->execute([
        ':uid'      => $userId,
        ':type'     => $itemType,
        ':cat'      => $categoryId,
        ':loc'      => $locationId,
        ':title'    => $title,
        ':desc'     => $description,
        ':features' => $identifyingFeatures ?: null,
        ':date'     => $incidentDate,
        ':time'     => $incidentTime,
        ':image'    => $imagePath,
        ':custody'  => $custodyStatus,
        ':status'   => 'open',
    ]);

    $newItemId = (int) $pdo->lastInsertId();

    // Audit
    log_audit_event('ITEM_CREATED', 'item', $newItemId, [
        'item_type'     => $itemType,
        'category_id'   => $categoryId,
        'location_id'   => $locationId,
        'title'         => $title,
        'custody_status'=> $custodyStatus,
    ]);

    // ── Invoke MatchingService if it is available ────────────────────────────
    $matchesFound = 0;
    $matchingServicePath = __DIR__ . '/../../services/MatchingService.php';
    if (file_exists($matchingServicePath)) {
        require_once $matchingServicePath;
        if (class_exists('MatchingService') && method_exists('MatchingService', 'findMatchesForItem')) {
            try {
                $matchesFound = MatchingService::findMatchesForItem($newItemId) ?? 0;
            } catch (Throwable $me) {
                // Matching failure must NOT abort the item creation
                error_log('MatchingService::findMatchesForItem error for item ' . $newItemId . ': ' . $me->getMessage());
            }
        }
    }

    json_response(
        true,
        'Your report has been submitted successfully.' . ($matchesFound > 0 ? " {$matchesFound} potential match(es) found!" : ''),
        ['item_id' => $newItemId, 'matches_found' => $matchesFound],
        null,
        201
    );

} catch (PDOException $e) {
    // Clean up uploaded file on DB failure
    FileUploadService::deleteFile($imagePath);
    error_log('Items/create insert error: ' . $e->getMessage());
    json_response(false, 'Failed to save the report. Please try again.', null, null, 500);
}
