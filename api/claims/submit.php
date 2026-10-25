<?php
/**
 * POST /api/claims/submit.php — Submit a private ownership claim.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../services/FileUploadService.php';
require_once __DIR__ . '/../../services/NotificationService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

require_login();
csrf_verify();

$claimantId = (int) $_SESSION['user_id'];
$itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
$lostItemInput = trim($_POST['lost_item_id'] ?? '');
$lostItemId = $lostItemInput === '' ? null : filter_var($lostItemInput, FILTER_VALIDATE_INT);
$proofDescription = trim($_POST['proof_description'] ?? '');
$errors = [];

if (!$itemId || $itemId < 1) {
    $errors[] = 'A valid found item ID is required.';
}
if ($lostItemInput !== '' && (!$lostItemId || $lostItemId < 1)) {
    $errors[] = 'The linked lost report ID is invalid.';
}
if (mb_strlen($proofDescription) < 20 || mb_strlen($proofDescription) > 2000) {
    $errors[] = 'Proof description must be between 20 and 2000 characters.';
}
if (!empty($errors)) {
    json_response(false, 'Validation failed.', null, $errors, 422);
}

$proofPath = null;
$pdo = null;

try {
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = FileUploadService::handleUpload($_FILES['proof_image'], 'proofs');
        if (!$upload['ok']) {
            json_response(false, $upload['error'] ?? 'Proof image upload failed.', null, null, 422);
        }
        $proofPath = $upload['path'];
    }

    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT id, user_id, item_type, status, title
         FROM items WHERE id = :id FOR UPDATE'
    );
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();
    if (!$item || $item['item_type'] !== 'found') {
        throw new DomainException('The selected found item does not exist.', 404);
    }
    if ((int) $item['user_id'] === $claimantId) {
        throw new DomainException('You cannot claim an item that you reported finding.', 403);
    }
    if ($item['status'] !== 'open') {
        throw new DomainException('This found item is not currently eligible for claims.', 409);
    }

    if ($lostItemId !== null) {
        $stmt = $pdo->prepare(
            "SELECT id, item_type, status FROM items
             WHERE id = :id AND user_id = :uid FOR UPDATE"
        );
        $stmt->execute([':id' => $lostItemId, ':uid' => $claimantId]);
        $lostItem = $stmt->fetch();
        if (!$lostItem || $lostItem['item_type'] !== 'lost' || $lostItem['status'] !== 'open') {
            throw new DomainException('The linked lost report must be an open report owned by you.', 422);
        }
    }

    $sql = "SELECT id FROM claims
            WHERE status IN ('pending', 'under_review', 'approved')
              AND (item_id = :item_id";
    $params = [':item_id' => $itemId];
    if ($lostItemId !== null) {
        $sql .= ' OR lost_item_id = :lost_item_id';
        $params[':lost_item_id'] = $lostItemId;
    }
    $sql .= ') LIMIT 1 FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        throw new DomainException('An active claim already exists for this item or linked lost report.', 409);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO claims
            (item_id, claimant_id, lost_item_id, proof_description, proof_image_path, status)
         VALUES (:item_id, :claimant_id, :lost_item_id, :proof_description, :proof_image_path, 'pending')"
    );
    $stmt->execute([
        ':item_id' => $itemId,
        ':claimant_id' => $claimantId,
        ':lost_item_id' => $lostItemId,
        ':proof_description' => $proofDescription,
        ':proof_image_path' => $proofPath,
    ]);
    $claimId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "UPDATE items SET status = 'pending_claim'
         WHERE id = :id AND item_type = 'found' AND status = 'open'"
    );
    $stmt->execute([':id' => $itemId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The item status changed while the claim was being submitted.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
         VALUES (:uid, 'CLAIM_SUBMITTED', 'claim', :claim_id, :details, :ip)"
    );
    $stmt->execute([
        ':uid' => $claimantId,
        ':claim_id' => $claimId,
        ':details' => json_encode(['item_id' => $itemId, 'lost_item_id' => $lostItemId]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $notify = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
    $notify->execute();
    foreach ($notify->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
        if (!NotificationService::send(
            (int) $adminId,
            'Ownership Claim Submitted',
            'A new ownership claim is awaiting review for found item #' . $itemId . '.',
            'claim_received',
            $claimId,
            url('admin/manage-claims.php?id=' . $claimId)
        )) {
            throw new RuntimeException('Unable to notify administrators about the claim.');
        }
    }

    if (!NotificationService::send(
        (int) $item['user_id'],
        'A Claim Was Submitted for Your Found Item',
        'A member has submitted an ownership claim for "' . $item['title'] . '". An administrator will review it.',
        'claim_received',
        $claimId,
        url('claims.php')
    )) {
        throw new RuntimeException('Unable to notify the item reporter about the claim.');
    }

    $pdo->commit();
    json_response(
        true,
        'Claim submitted. An administrator will review your proof.',
        ['claim_id' => $claimId, 'status' => 'pending'],
        null,
        201
    );
} catch (DomainException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($proofPath !== null) {
        FileUploadService::deleteFile($proofPath);
    }
    $status = in_array($e->getCode(), [403, 404, 409, 422], true) ? $e->getCode() : 422;
    json_response(false, $e->getMessage(), null, null, $status);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($proofPath !== null) {
        FileUploadService::deleteFile($proofPath);
    }
    error_log('Claims/submit error: ' . $e->getMessage());
    json_response(false, 'Failed to submit claim. Please try again.', null, null, 500);
}
