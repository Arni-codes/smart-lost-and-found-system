<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/matches/recalculate.php — On-Demand Match Recalculation Trigger
 *
 * Invoked when an item's details are modified or when a user clicks "Refresh Matches".
 * Evaluates candidate items, updates `smart_matches`, and returns the refreshed match list.
 *
 * Authorization: Authenticated user (item owner or administrator).
 *
 * Reference: PROJECT_SPEC.md (Section 7.2.3, FR-05)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../services/MatchingService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed. POST required.', null, null, 405);
}

require_login();
csrf_verify();

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';

// Accept item_id via POST body or query parameter
$itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT)
       ?: filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT)
       ?: filter_var($_POST['item_id'] ?? ($_GET['item_id'] ?? null), FILTER_VALIDATE_INT);

if (!$itemId || $itemId <= 0) {
    json_response(false, 'A valid item ID is required.', null, null, 400);
}

try {
    $pdo = get_db_connection();

    // Verify item exists and check permissions
    $stmt = $pdo->prepare('SELECT id, user_id, title, status FROM items WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        json_response(false, 'Item not found.', null, null, 404);
    }

    // Owner or admin only
    if ((int) $item['user_id'] !== $userId && $userRole !== 'admin') {
        json_response(false, 'You are not authorized to refresh matches for this item.', null, null, 403);
    }

    if ($item['status'] !== 'open') {
        json_response(false, 'Matching is only active for open reports (current status: ' . $item['status'] . ').', null, null, 409);
    }

    $result = MatchingService::recalculateMatchesForItem($itemId);

    // Audit log event
    if (function_exists('log_audit_event')) {
        log_audit_event('MATCHES_RECALCULATED', 'item', $itemId, [
            'recalculated_by' => $userId,
            'matches_found'   => $result['matches_found']
        ]);
    }

    json_response(
        true,
        sprintf('Smart matches refreshed. %d potential match(es) identified.', $result['matches_found']),
        $result
    );

} catch (Throwable $e) {
    error_log('api/matches/recalculate error: ' . $e->getMessage());
    json_response(false, 'Failed to recalculate matches. Please try again.', null, null, 500);
}
