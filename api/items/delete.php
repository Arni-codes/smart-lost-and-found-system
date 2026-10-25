<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/items/delete.php — Close or Delete an Item Report
 *
 * Permitted actions:
 *   action=close  — Owner marks their own open report as 'closed'
 *   action=delete — Admin deletes any item (removes record + image)
 *
 * Authorization:
 *   close : item owner only (status must be 'open')
 *   delete: admin only
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

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';
$itemId   = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
$action   = trim($_POST['action'] ?? 'close'); // 'close' or 'delete'

if (!$itemId || $itemId <= 0) {
    json_response(false, 'A valid item ID is required.', null, null, 400);
}

if (!in_array($action, ['close', 'delete'], true)) {
    json_response(false, 'Invalid action. Use "close" or "delete".', null, null, 400);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        json_response(false, 'Item not found.', null, null, 404);
    }

    if ($action === 'close') {
        // Owner self-closes their own open report
        if ((int) $item['user_id'] !== $userId) {
            json_response(false, 'You can only close your own reports.', null, null, 403);
        }
        if (!in_array($item['status'], ['open', 'pending_claim'], true)) {
            json_response(false, 'Only open or pending-claim items can be closed by the owner.', null, null, 409);
        }

        $stmt = $pdo->prepare('UPDATE items SET status = :status WHERE id = :id');
        $stmt->execute([':status' => 'closed', ':id' => $itemId]);

        log_audit_event('ITEM_CLOSED', 'item', $itemId, ['closed_by' => $userId]);

        json_response(true, 'Report marked as closed. Thank you for keeping the catalog tidy!', ['item_id' => $itemId]);

    } elseif ($action === 'delete') {
        // Admin-only: physical removal
        if ($userRole !== 'admin') {
            json_response(false, 'Administrator access is required to delete items.', null, null, 403);
        }

        $reason = trim($_POST['reason'] ?? 'No reason provided.');

        // Delete item record (cascades to smart_matches, claims via FK)
        $stmt = $pdo->prepare('DELETE FROM items WHERE id = :id');
        $stmt->execute([':id' => $itemId]);

        // Delete associated image file
        FileUploadService::deleteFile($item['image_path']);

        log_audit_event('ITEM_DELETED', 'item', $itemId, [
            'deleted_by' => $userId,
            'title'      => $item['title'],
            'reason'     => $reason,
        ]);

        json_response(true, 'Item deleted successfully.', ['item_id' => $itemId]);
    }

} catch (PDOException $e) {
    error_log('Items/delete error: ' . $e->getMessage());
    json_response(false, 'Failed to process your request. Please try again.', null, null, 500);
}
