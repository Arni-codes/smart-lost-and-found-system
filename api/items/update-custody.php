<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/items/update-custody.php — Update Custody Status
 *
 * Permitted transition: with_finder → submitted_to_security
 * Authorization: item owner (finder) OR admin.
 * The transition to returned_to_owner is performed exclusively by
 * the atomic handover endpoint (api/admin/handover.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

require_login();
csrf_verify();

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';
$itemId   = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
$newCustody = trim($_POST['new_custody'] ?? '');

if (!$itemId || $itemId <= 0) {
    json_response(false, 'A valid item ID is required.', null, null, 400);
}

// Only 'submitted_to_security' is a permitted user-initiated transition
if ($newCustody !== 'submitted_to_security') {
    json_response(false, 'Invalid custody transition. Only "with_finder → submitted_to_security" is permitted here.', null, null, 422);
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        json_response(false, 'Item not found.', null, null, 404);
    }

    // Must be a found item
    if ($item['item_type'] !== 'found') {
        json_response(false, 'Custody status can only be updated on found item reports.', null, null, 409);
    }

    // Authorization: owner or admin
    if ((int) $item['user_id'] !== $userId && $userRole !== 'admin') {
        json_response(false, 'You are not authorized to update custody for this item.', null, null, 403);
    }

    // Validate current custody allows this transition
    if ($item['custody_status'] !== 'with_finder') {
        json_response(
            false,
            'Custody can only be updated from "with_finder" to "submitted_to_security". Current status: ' . $item['custody_status'],
            null,
            null,
            409
        );
    }

    // Perform update
    $stmt = $pdo->prepare(
        'UPDATE items SET custody_status = :custody WHERE id = :id'
    );
    $stmt->execute([':custody' => 'submitted_to_security', ':id' => $itemId]);

    // Notify active claimants about custody change
    $claimStmt = $pdo->prepare(
        'SELECT c.claimant_id FROM claims c
         WHERE c.item_id = :item_id AND c.status IN (\'pending\', \'under_review\', \'approved\')'
    );
    $claimStmt->execute([':item_id' => $itemId]);
    $claimants = $claimStmt->fetchAll();

    foreach ($claimants as $claimant) {
        $notifStmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, reference_id, reference_url)
             VALUES (:uid, :title, :msg, :type, :ref, :url)'
        );
        $notifStmt->execute([
            ':uid'   => $claimant['claimant_id'],
            ':title' => 'Item Custody Updated',
            ':msg'   => 'The item you claimed ("' . $item['title'] . '") has been transferred to the Campus Security Desk. Please visit the desk with your Student ID to proceed.',
            ':type'  => 'custody_update',
            ':ref'   => $itemId,
            ':url'   => url('item-details.php?id=' . $itemId),
        ]);
    }

    log_audit_event('CUSTODY_UPDATED', 'item', $itemId, [
        'from'       => 'with_finder',
        'to'         => 'submitted_to_security',
        'updated_by' => $userId,
    ]);

    json_response(true, 'Custody updated to "Submitted to Security Desk" successfully.', [
        'item_id'        => $itemId,
        'custody_status' => 'submitted_to_security',
    ]);

} catch (PDOException $e) {
    error_log('Items/update-custody error: ' . $e->getMessage());
    json_response(false, 'Failed to update custody status. Please try again.', null, null, 500);
}
