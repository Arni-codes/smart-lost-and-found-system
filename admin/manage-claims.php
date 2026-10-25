<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Admin Claim Adjudication Interface (Side-by-Side Proof Comparison)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

require_admin(false);

$pageTitle = 'Adjudicate Claims - ' . APP_SHORT_NAME;
$activeNav = 'admin';

$claimId = (int)($_GET['id'] ?? 0);
$claimsList = [];
$selectedClaim = null;
$loadError = false;

try {
    $pdo = get_db_connection();

    if ($claimId > 0) {
        $stmt = $pdo->prepare(
            "SELECT c.*, 
                    i.title AS item_title, i.description AS item_description, i.identifying_features, 
                    i.custody_status, i.image_path, i.incident_date,
                    cat.name AS category_name, loc.building, loc.specific_area,
                    finder.full_name AS finder_name,
                    claimant.full_name AS claimant_name, claimant.student_id AS claimant_student_id
             FROM claims c
             JOIN items i ON c.item_id = i.id
             JOIN categories cat ON i.category_id = cat.id
             JOIN locations loc ON i.location_id = loc.id
             JOIN users finder ON i.user_id = finder.id
             JOIN users claimant ON c.claimant_id = claimant.id
             WHERE c.id = :id"
        );
        $stmt->execute([':id' => $claimId]);
        $selectedClaim = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Load claim queue
    $qStmt = $pdo->query(
        "SELECT c.id, c.status, c.created_at,
                i.title AS item_title, i.custody_status,
                u.full_name AS claimant_name, u.student_id
         FROM claims c
         JOIN items i ON c.item_id = i.id
         JOIN users u ON c.claimant_id = u.id
         ORDER BY FIELD(c.status, 'pending', 'under_review', 'approved', 'rejected'), c.created_at DESC"
    );
    $claimsList = $qStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Admin/manage-claims page error: ' . $e->getMessage());
    $loadError = true;
}

$extraScripts = ['assets/js/admin.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <?php if ($loadError): ?>
        <div class="alert alert-danger" role="alert">Claim records could not be loaded. Please try again later.</div>
    <?php endif; ?>
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('admin/index.php') ?>">Admin Desk</a> &rsaquo; 
        <span>Claim Adjudication</span>
    </nav>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <span class="badge badge-warning" style="margin-bottom: var(--space-2);">Authorized Adjudication</span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
                Ownership Claim Review
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Compare claimant's proof of ownership against finder's confidential private markings to prevent fraud.
            </p>
        </div>

        <div style="display: flex; gap: var(--space-3);">
            <a href="<?= url('admin/handover.php') ?>" class="btn btn-success btn-sm">
                Desk Handover Terminal &rarr;
            </a>
            <a href="<?= url('admin/index.php') ?>" class="btn btn-secondary btn-sm">
                Admin Dashboard
            </a>
        </div>
    </div>

    <?php if ($selectedClaim): ?>
        <!-- Side-by-Side Adjudication Interface -->
        <div class="card" style="margin-bottom: var(--space-8); border-top: 4px solid var(--color-primary);">
            <div class="card-header">
                <div>
                    <strong style="font-size: 1.125rem;">
                        Adjudicating Claim #CLM-<?= e($selectedClaim['id']) ?>: <?= e($selectedClaim['item_title']) ?>
                    </strong>
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-top: 2px;">
                        Claimant: <strong><?= e($selectedClaim['claimant_name']) ?></strong> (Roll No: <code><?= e($selectedClaim['claimant_student_id']) ?></code>)
                    </div>
                </div>
                <div>
                    <span class="badge <?= $selectedClaim['status'] === 'approved' ? 'badge-success' : 'badge-warning' ?>">
                        <?= ucfirst(e($selectedClaim['status'])) ?>
                    </span>
                </div>
            </div>

            <div class="card-body">
                <div class="comparison-grid">
                    <!-- Column 1: Finder's Secret Features -->
                    <div class="comparison-card" style="background-color: var(--color-confidential-bg); border-color: var(--color-confidential-border);">
                        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3); color: var(--color-confidential); font-weight: 700;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <span>Finder's Secret Identifying Features</span>
                        </div>
                        <p style="font-size: 0.8125rem; color: #5b21b6; margin-bottom: var(--space-3);">
                            Entered by finder (<?= e($selectedClaim['finder_name']) ?>) and hidden from the public:
                        </p>
                        <div style="background: #ffffff; padding: var(--space-4); border-radius: var(--radius-md); border: 1px dashed var(--color-confidential-border); font-family: var(--font-mono); font-size: 0.875rem; color: var(--color-text); min-height: 120px; white-space: pre-line;">
                            <?= !empty($selectedClaim['identifying_features']) ? e($selectedClaim['identifying_features']) : '<em>No private markings recorded.</em>' ?>
                        </div>
                        <div style="margin-top: var(--space-3); font-size: 0.8125rem; color: var(--color-text-muted);">
                            <strong>Item Custody:</strong> <?= ucwords(str_replace('_', ' ', e($selectedClaim['custody_status']))) ?>
                        </div>
                    </div>

                    <!-- Column 2: Claimant's Submitted Proof -->
                    <div class="comparison-card" style="background-color: #f0fdf4; border-color: var(--color-success-border);">
                        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3); color: var(--color-success); font-weight: 700;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                            <span>Claimant's Submitted Proof</span>
                        </div>
                        <p style="font-size: 0.8125rem; color: #166534; margin-bottom: var(--space-3);">
                            Provided by claimant (<?= e($selectedClaim['claimant_name']) ?>):
                        </p>
                        <div style="background: #ffffff; padding: var(--space-4); border-radius: var(--radius-md); border: 1px dashed var(--color-success-border); font-size: 0.875rem; color: var(--color-text); min-height: 120px; white-space: pre-line;">
                            <?= e($selectedClaim['proof_description']) ?>
                        </div>
                        <?php if (!empty($selectedClaim['proof_image_path'])): ?>
                            <div style="margin-top: var(--space-3);">
                                <a href="<?= url('api/claims/proof-view.php?claim_id=' . $selectedClaim['id']) ?>" target="_blank" class="btn btn-outline btn-sm">
                                    View Attached Proof Photo / Receipt &rarr;
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Adjudication Action Form -->
                <?php if (in_array($selectedClaim['status'], ['pending', 'under_review'], true)): ?>
                <form id="claimReviewForm" method="POST" action="<?= url('api/claims/review.php') ?>" style="margin-top: var(--space-6);">
                    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                    <input type="hidden" name="claim_id" value="<?= e($selectedClaim['id']) ?>">

                    <div class="form-group">
                        <label for="adminNotes" class="form-label">
                            Administrative Evaluation Notes
                        </label>
                        <textarea id="adminNotes" 
                                  name="admin_notes" 
                                  class="form-control" 
                                  rows="3" 
                                  maxlength="2000"
                                  placeholder="State reasoning for decision or collection instructions for the claimant..."><?= e($selectedClaim['admin_notes'] ?? '') ?></textarea>
                    </div>

                    <div style="display: flex; gap: var(--space-3); justify-content: flex-end; flex-wrap: wrap;">
                        <button type="submit" name="action" value="reject" class="btn btn-danger">
                            Reject Claim
                        </button>
                        <button type="submit" name="action" value="under_review" class="btn btn-secondary">
                            Mark Under Review
                        </button>
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-lg">
                            &check; Approve Claim &amp; Authorize Handover
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-top: var(--space-6);">
                        <?php if ($selectedClaim['status'] === 'approved'): ?>
                            This claim is approved and awaiting physical handover at the Security Desk.
                        <?php else: ?>
                            This claim is no longer reviewable.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Master Claim Queue Table -->
    <div class="card">
        <div class="card-header">
            <strong>All Claims Queue</strong>
            <span class="badge badge-secondary"><?= count($claimsList) ?> Total</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Claim ID</th>
                            <th>Item Title</th>
                            <th>Claimant</th>
                            <th>Roll Number</th>
                            <th>Custody</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claimsList as $c): ?>
                            <tr style="<?= $claimId === (int)$c['id'] ? 'background-color: var(--color-primary-light);' : '' ?>">
                                <td><code>#CLM-<?= e($c['id']) ?></code></td>
                                <td><strong><?= e($c['item_title']) ?></strong></td>
                                <td><?= e($c['claimant_name']) ?></td>
                                <td><code><?= e($c['student_id']) ?></code></td>
                                <td>
                                    <span class="badge badge-custody-<?= $c['custody_status'] === 'submitted_to_security' ? 'security' : 'finder' ?>">
                                        <?= $c['custody_status'] === 'submitted_to_security' ? 'Security Desk' : 'With Finder' ?>
                                    </span>
                                </td>
                                <td><?= format_date($c['created_at']) ?></td>
                                <td>
                                    <span class="badge <?= $c['status'] === 'approved' ? 'badge-success' : ($c['status'] === 'rejected' ? 'badge-danger' : 'badge-warning') ?>">
                                        <?= ucfirst(e($c['status'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?= url('admin/manage-claims.php?id=' . $c['id']) ?>" class="btn btn-outline btn-sm">
                                        Inspect
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
