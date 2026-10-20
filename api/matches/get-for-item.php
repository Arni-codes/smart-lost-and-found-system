<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * GET /api/matches/get-for-item.php?item_id={id} — Fetch Pre-Calculated Matches
 *
 * Fast O(1) indexed lookup from smart_matches table without re-running scoring.
 * Enforces privacy rules: identifying_features is omitted for public callers.
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

init_session();

$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT)
       ?: filter_var($_GET['item_id'] ?? null, FILTER_VALIDATE_INT);
if (!$itemId || $itemId <= 0) {
    json_response(false, 'A valid item ID is required.', null, null, 400);
}

try {
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $isAdminUser   = is_admin();

    $matches = MatchingService::getMatchesForItem($itemId, $currentUserId ?: null, $isAdminUser);

    json_response(true, 'Matches retrieved successfully.', [
        'item_id'       => $itemId,
        'threshold'     => MatchingService::getThreshold(),
        'total_matches' => count($matches),
        'matches'       => $matches
    ]);

} catch (Throwable $e) {
    error_log('api/matches/get-for-item error: ' . $e->getMessage());
    json_response(false, 'Failed to retrieve match suggestions. Please try again.', null, null, 500);
}
