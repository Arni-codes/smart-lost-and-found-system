<?php
/**
 * POST /api/claims/review.php — Admin claim adjudication.
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

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$action = trim($_POST['action'] ?? '');
$adminNotes = trim($_POST['admin_notes'] ?? '');
if (!$claimId || $claimId < 1) {
    json_response(false, 'A valid claim ID is required.', null, null, 400);
}
if (!in_array($action, ['approve', 'reject', 'under_review'], true)) {
    json_response(false, 'Action must be approve, reject, or under_review.', null, null, 422);
}
if (mb_strlen($adminNotes) > 2000) {
    json_response(false, 'Administrative notes may not exceed 2000 characters.', null, null, 422);
}
if ($action === 'reject' && $adminNotes === '') {
    json_response(false, 'A reason is required when rejecting a claim.', null, null, 422);
}

$adminId = (int) $_SESSION['user_id'];
$pdo = null;
try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT c.id, c.item_id, c.claimant_id, c.status, c.handover_date,
                i.user_id AS finder_id, i.title AS item_title, i.status AS item_status,
                i.item_type
         FROM claims c JOIN items i ON i.id = c.item_id
         WHERE c.id = :id FOR UPDATE'
    );
    $stmt->execute([':id' => $claimId]);
    $claim = $stmt->fetch();
    if (!$claim) {
        throw new DomainException('Claim not found.', 404);
    }
    $allowedTransitions = [
        'pending' => ['under_review', 'approved', 'rejected'],
        'under_review' => ['approved', 'rejected'],
    ];
    if (!in_array($action, $allowedTransitions[$claim['status']] ?? [], true)) {
        throw new DomainException('This claim cannot transition from ' . $claim['status'] . ' to ' . $action . '.', 409);
    }
    if ($claim['item_type'] !== 'found' || $claim['item_status'] !== 'pending_claim' || $claim['handover_date'] !== null) {
        throw new DomainException('The found item is not in a reviewable claim state.', 409);
    }

    $stmt = $pdo->prepare('SELECT id, status FROM items WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $claim['item_id']]);
    $item = $stmt->fetch();
    if (!$item || $item['status'] !== 'pending_claim') {
        throw new DomainException('The found item status changed; review is no longer available.', 409);
    }
    if ($action === 'approve') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM claims
             WHERE item_id = :item_id AND id <> :claim_id
               AND status IN ('pending', 'under_review', 'approved')"
        );
        $stmt->execute([':item_id' => $claim['item_id'], ':claim_id' => $claimId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new DomainException('Another active claim conflicts with this approval.', 409);
        }
    }

    $newStatus = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'under_review');
    $stmt = $pdo->prepare(
        'UPDATE claims
         SET status = :status, admin_notes = :notes, reviewed_by_admin_id = :admin_id, reviewed_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        ':status' => $newStatus,
        ':notes' => $adminNotes !== '' ? $adminNotes : null,
        ':admin_id' => $adminId,
        ':id' => $claimId,
    ]);

    if ($newStatus === 'rejected') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM claims
             WHERE item_id = :item_id AND status IN ('pending', 'under_review', 'approved')"
        );
        $stmt->execute([':item_id' => $claim['item_id']]);
        if ((int) $stmt->fetchColumn() === 0) {
            $stmt = $pdo->prepare("UPDATE items SET status = 'open' WHERE id = :id AND status = 'pending_claim'");
            $stmt->execute([':id' => $claim['item_id']]);
        }
    }

    $auditAction = match ($newStatus) {
        'approved' => 'CLAIM_APPROVED',
        'rejected' => 'CLAIM_REJECTED',
        default => 'CLAIM_UNDER_REVIEW',
    };
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
         VALUES (:uid, :action, \'claim\', :claim_id, :details, :ip)'
    );
    $stmt->execute([
        ':uid' => $adminId,
        ':action' => $auditAction,
        ':claim_id' => $claimId,
        ':details' => json_encode(['item_id' => (int) $claim['item_id'], 'status' => $newStatus]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $title = match ($newStatus) {
        'approved' => 'Ownership Claim Approved',
        'rejected' => 'Ownership Claim Rejected',
        default => 'Ownership Claim Under Review',
    };
    $message = match ($newStatus) {
        'approved' => 'Your claim for "' . $claim['item_title'] . '" was approved. Bring your student ID to the Campus Security Desk for handover.',
        'rejected' => 'Your claim for "' . $claim['item_title'] . '" was rejected. Review the administrator notes on your claims page.',
        default => 'Your claim for "' . $claim['item_title'] . '" is being reviewed by an administrator.',
    };
    if (!NotificationService::send(
        (int) $claim['claimant_id'],
        $title,
        $message,
        'claim_status',
        $claimId,
        url('claims.php')
    )) {
        throw new RuntimeException('Unable to notify the claimant about the review decision.');
    }

    if ($newStatus === 'approved' && !NotificationService::send(
        (int) $claim['finder_id'],
        'Claim Approved for Your Found Item',
        'The ownership claim for "' . $claim['item_title'] . '" was approved. Please coordinate transfer of the item to the Campus Security Desk.',
        'custody_update',
        $claimId,
        url('claims.php')
    )) {
        throw new RuntimeException('Unable to notify the item reporter about the approval.');
    }

    $pdo->commit();
    json_response(true, 'Claim marked as ' . str_replace('_', ' ', $newStatus) . '.', [
        'claim_id' => $claimId,
        'status' => $newStatus,
        'item_status' => $newStatus === 'rejected' ? 'open' : 'pending_claim',
    ]);
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
    error_log('Claims/review error: ' . $e->getMessage());
    json_response(false, 'Failed to process the claim review. Please try again.', null, null, 500);
}
