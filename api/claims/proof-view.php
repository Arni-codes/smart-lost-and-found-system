<?php
/**
 * GET /api/claims/proof-view.php — Stream proof only to its claimant or an admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

require_login();

$claimId = filter_input(INPUT_GET, 'claim_id', FILTER_VALIDATE_INT);
if (!$claimId || $claimId < 1) {
    http_response_code(404);
    exit;
}

try {
    $stmt = get_db_connection()->prepare(
        'SELECT claimant_id, proof_image_path FROM claims WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $claimId]);
    $claim = $stmt->fetch();
    if (!$claim || ((int) $claim['claimant_id'] !== (int) $_SESSION['user_id'] && !is_admin())) {
        http_response_code(404);
        exit;
    }

    $relativePath = $claim['proof_image_path'] ?? '';
    if (!preg_match('#^uploads/proofs/[a-f0-9]{32}\.(jpg|png|webp)$#i', $relativePath)) {
        http_response_code(404);
        exit;
    }

    $proofRoot = realpath(PROOFS_UPLOAD_PATH);
    $proofPath = realpath(ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if (!$proofRoot || !$proofPath || dirname($proofPath) !== $proofRoot || !is_file($proofPath)) {
        http_response_code(404);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $proofPath) : false;
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(415);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($proofPath));
    header('Content-Disposition: inline; filename="claim-proof-' . $claimId . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($proofPath);
} catch (PDOException $e) {
    error_log('Claims/proof-view error: ' . $e->getMessage());
    http_response_code(500);
}
