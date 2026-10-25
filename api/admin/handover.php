<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/admin/handover.php — Atomic Physical Desk Handover
 *
 * Executes a ACID transaction with row-level locks to prevent:
 *  - Double handovers
 *  - Concurrent race conditions
 *
 * Authorization: Admin only.
 *
 * Steps (per spec § 7.2.5):
 *  1. Lock & validate Claim (status='approved', handover_date IS NULL)
 *  2. Lock & validate Item (status IN open/pending_claim)
 *  3. UPDATE Claim: handover_date = NOW()
 *  4. UPDATE Item: status='claimed', custody_status='returned_to_owner'
 *  5. IF linked lost_item_id: UPDATE Lost Item status='closed' + notify owner
 *  6. Notify finder
 *  7. Audit log
 *  8. Commit
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../services/NotificationService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

require_admin();
csrf_verify();

$adminId             = (int) $_SESSION['user_id'];
$claimId             = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$studentIdConfirmed  = !empty($_POST['student_id_confirmed']) && $_POST['student_id_confirmed'] === '1';
$handoverNotes       = trim($_POST['handover_notes'] ?? '');

if (!$claimId || $claimId <= 0) {
    json_response(false, 'A valid claim ID is required.', null, null, 400);
}

if (!$studentIdConfirmed) {
    json_response(false, 'Student ID verification is required before completing the handover.', null, null, 422);
}

$handoverNotes = trim($handoverNotes);
if (mb_strlen($handoverNotes) > 1000) {
    json_response(false, 'Handover notes may not exceed 1000 characters.', null, null, 422);
}

$pdo = null;
try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // ── Step 1: Lock & validate Claim ────────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT c.*, i.user_id AS finder_user_id, i.title AS item_title
         FROM claims c
         JOIN items i ON i.id = c.item_id
         WHERE c.id = :id FOR UPDATE'
    );
    $stmt->execute([':id' => $claimId]);
    $claim = $stmt->fetch();

    if (!$claim) {
        $pdo->rollBack();
        json_response(false, 'Claim not found.', null, null, 404);
    }
    if ($claim['status'] !== 'approved') {
        $pdo->rollBack();
        json_response(false, 'Handover can only be completed for approved claims (current status: ' . $claim['status'] . ').', null, null, 409);
    }
    if (!empty($claim['handover_date'])) {
        $pdo->rollBack();
        json_response(false, 'This item has already been handed over on ' . $claim['handover_date'] . '. Duplicate handover prevented.', null, null, 409);
    }

    // ── Step 2: Lock & validate Item ─────────────────────────────────────────
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $claim['item_id']]);
    $item = $stmt->fetch();

    if (!$item || $item['item_type'] !== 'found') {
        $pdo->rollBack();
        json_response(false, 'Associated found item not found.', null, null, 404);
    }
    if (!in_array($item['status'], ['open', 'pending_claim'], true)) {
        $pdo->rollBack();
        json_response(false, 'Item is not in a handover-eligible state (current status: ' . $item['status'] . ').', null, null, 409);
    }

    // ── Step 3: Update Claim handover_date ───────────────────────────────────
    $stmt = $pdo->prepare(
        'UPDATE claims SET handover_date = NOW(), admin_notes = CONCAT(IFNULL(admin_notes,""), :notes)
         WHERE id = :id'
    );
    $notes = !empty($handoverNotes) ? "\n[Handover Notes]: " . $handoverNotes : '';
    $stmt->execute([':notes' => $notes, ':id' => $claimId]);

    // ── Step 4: Update Item status & custody ─────────────────────────────────
    $stmt = $pdo->prepare(
        "UPDATE items SET status = 'claimed', custody_status = 'returned_to_owner' WHERE id = :id"
    );
    $stmt->execute([':id' => $item['id']]);

    // ── Step 5: Auto-close linked lost item ──────────────────────────────────
    if (!empty($claim['lost_item_id'])) {
        $stmt = $pdo->prepare(
            "UPDATE items SET status = 'closed' WHERE id = :id AND status IN ('open','pending_claim')"
        );
        $stmt->execute([':id' => $claim['lost_item_id']]);

        // Notify the lost-item owner (the claimant)
        if (!NotificationService::send(
            (int) $claim['claimant_id'],
            'Lost Item Report Closed',
            'Your lost report #' . $claim['lost_item_id'] . ' was automatically closed following the successful handover of "' . $claim['item_title'] . '" at the Security Desk.',
            'system',
            (int) $claim['lost_item_id'],
            url('item-details.php?id=' . $claim['lost_item_id'])
        )) {
            throw new RuntimeException('Unable to notify the claimant about the closed lost report.');
        }
    }

    // ── Step 6: Notify claimant of successful handover ───────────────────────
    if (!NotificationService::send(
        (int) $claim['claimant_id'],
        'Item Handover Complete 🎉',
        'Your claim for "' . $claim['item_title'] . '" has been verified and the item has been returned to you at the Campus Security Desk.',
        'claim_status',
        (int) $claim['item_id'],
        url('item-details.php?id=' . $claim['item_id'])
    )) {
        throw new RuntimeException('Unable to notify the claimant about the handover.');
    }

    // ── Step 6b: Notify finder ───────────────────────────────────────────────
    if (!NotificationService::send(
        (int) $claim['finder_user_id'],
        'Item Successfully Returned to Owner',
        'The item you found ("' . $claim['item_title'] . '") has been verified and returned to its rightful owner at the Security Desk. Thank you for your honesty!',
        'custody_update',
        (int) $claim['item_id'],
        url('item-details.php?id=' . $claim['item_id'])
    )) {
        throw new RuntimeException('Unable to notify the item reporter about the handover.');
    }

    // ── Step 7: Audit log ────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
         VALUES (:uid, 'HANDOVER_COMPLETED', 'claim', :cid, :details, :ip)"
    );
    $stmt->execute([
        ':uid'     => $adminId,
        ':cid'     => $claimId,
        ':details' => json_encode([
            'item_id'       => $item['id'],
            'claimant_id'   => $claim['claimant_id'],
            'finder_id'     => $claim['finder_user_id'],
            'lost_item_id'  => $claim['lost_item_id'],
            'notes'         => $handoverNotes,
        ]),
        ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    // ── Step 8: Commit ───────────────────────────────────────────────────────
    $pdo->commit();

    json_response(true, 'Handover verified and completed successfully. Item returned to owner.', [
        'claim_id'  => $claimId,
        'item_id'   => $item['id'],
        'item_title'=> $claim['item_title'],
    ]);

} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin/handover transaction error: ' . $e->getMessage());
    json_response(false, 'Handover transaction failed. Please try again.', null, null, 500);
}
