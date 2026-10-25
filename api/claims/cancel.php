<?php
/**
 * POST /api/claims/cancel.php — Cancel the caller's claim before handover.
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

require_login();
csrf_verify();

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
if (!$claimId || $claimId < 1) {
    json_response(false, 'A valid claim ID is required.', null, null, 400);
}

$pdo = null;
try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT c.id, c.item_id, c.claimant_id, c.status, c.handover_date,
                i.user_id AS finder_id, i.title, i.status AS item_status
         FROM claims c JOIN items i ON i.id = c.item_id
         WHERE c.id = :id FOR UPDATE'
    );
    $stmt->execute([':id' => $claimId]);
    $claim = $stmt->fetch();
    if (!$claim || (int) $claim['claimant_id'] !== (int) $_SESSION['user_id']) {
        throw new DomainException('Claim not found.', 404);
    }
    if (!in_array($claim['status'], ['pending', 'under_review', 'approved'], true) || $claim['handover_date'] !== null) {
        throw new DomainException('This claim can no longer be cancelled.', 409);
    }

    $stmt = $pdo->prepare('SELECT id, status FROM items WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $claim['item_id']]);
    $item = $stmt->fetch();
    if (!$item || $item['status'] !== 'pending_claim') {
        throw new DomainException('The found item is no longer in a cancellable claim state.', 409);
    }

    $stmt = $pdo->prepare(
        "UPDATE claims SET status = 'cancelled'
         WHERE id = :id AND claimant_id = :uid AND status IN ('pending', 'under_review', 'approved')
           AND handover_date IS NULL"
    );
    $stmt->execute([':id' => $claimId, ':uid' => (int) $_SESSION['user_id']]);
    if ($stmt->rowCount() !== 1) {
        throw new DomainException('This claim can no longer be cancelled.', 409);
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM claims
         WHERE item_id = :item_id AND status IN ('pending', 'under_review', 'approved')"
    );
    $stmt->execute([':item_id' => $claim['item_id']]);
    if ((int) $stmt->fetchColumn() === 0) {
        $stmt = $pdo->prepare("UPDATE items SET status = 'open' WHERE id = :id AND status = 'pending_claim'");
        $stmt->execute([':id' => $claim['item_id']]);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
         VALUES (:uid, 'CLAIM_CANCELLED', 'claim', :claim_id, :details, :ip)"
    );
    $stmt->execute([
        ':uid' => (int) $_SESSION['user_id'],
        ':claim_id' => $claimId,
        ':details' => json_encode(['item_id' => (int) $claim['item_id']]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    if (!NotificationService::send(
        (int) $claim['finder_id'],
        'Ownership Claim Cancelled',
        'The active claim for "' . $claim['title'] . '" was cancelled by the claimant.',
        'claim_status',
        $claimId,
        url('claims.php')
    )) {
        throw new RuntimeException('Unable to notify the item reporter.');
    }

    $pdo->commit();
    json_response(true, 'Your claim was cancelled.', ['claim_id' => $claimId, 'status' => 'cancelled']);
} catch (DomainException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = in_array($e->getCode(), [403, 404, 409, 422], true) ? $e->getCode() : 422;
    json_response(false, $e->getMessage(), null, null, $status);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Claims/cancel error: ' . $e->getMessage());
    json_response(false, 'Failed to cancel claim. Please try again.', null, null, 500);
}
