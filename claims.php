<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Claims Tracking Portal (Dual Panel: Submitted & Received)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_middleware.php';

require_login(false);

$pageTitle = 'Claims Tracking - ' . APP_SHORT_NAME;
$activeNav = 'claims';

$currentUser = get_current_user_session();
$userId = (int)$currentUser['id'];

$mySubmittedClaims = [];
$claimsOnMyFoundItems = [];
$loadError = false;

try {
    $pdo = get_db_connection();

    // 1. Panel 1: Claims I have submitted on found items
    $stmt = $pdo->prepare(
        "SELECT c.*, 
                i.title AS item_title, i.incident_date, i.custody_status AS item_custody, i.status AS item_status,
                cat.name AS category_name, loc.building,
                u.full_name AS finder_name
         FROM claims c
         JOIN items i ON c.item_id = i.id
         JOIN categories cat ON i.category_id = cat.id
         JOIN locations loc ON i.location_id = loc.id
         JOIN users u ON i.user_id = u.id
         WHERE c.claimant_id = :uid
         ORDER BY c.created_at DESC"
    );
    $stmt->execute([':uid' => $userId]);
    $mySubmittedClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Panel 2: Claims filed by other students on items I reported found
    $stmt = $pdo->prepare(
        "SELECT c.id AS claim_id, c.status AS claim_status, c.created_at AS claim_date,
                i.id AS item_id, i.title AS item_title, i.custody_status,
                claimant.full_name AS claimant_name, claimant.department AS claimant_dept
         FROM claims c
         JOIN items i ON c.item_id = i.id
         JOIN users claimant ON c.claimant_id = claimant.id
         WHERE i.user_id = :uid AND i.item_type = 'found'
         ORDER BY c.created_at DESC"
    );
    $stmt->execute([':uid' => $userId]);
    $claimsOnMyFoundItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Claims page error: ' . $e->getMessage());
    $loadError = true;
}

$extraScripts = ['assets/js/claims.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <?php if ($loadError): ?>
        <div class="alert alert-danger" role="alert">Claims could not be loaded. Please try again later.</div>
    <?php endif; ?>
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('dashboard.php') ?>">Dashboard</a> &rsaquo; 
        <span>Claims Tracking</span>
    </nav>

    <div style="margin-bottom: var(--space-8);">
        <span class="badge badge-primary" style="margin-bottom: var(--space-2);">Ownership Claims</span>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: var(--space-2);">
            Campus Property Claims
        </h1>
        <p style="font-size: 1rem; color: var(--color-text-muted);">
            Track verification status for belongings you claimed, or review claim activity on items you found.
        </p>
    </div>

    <!-- Panel 1: Claims I Have Submitted -->
    <div class="card" style="margin-bottom: var(--space-10);">
        <div class="card-header">
            <div>
                <strong style="font-size: 1.125rem;">Claims I Have Submitted (<?= count($mySubmittedClaims) ?>)</strong>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0;">
                    Adjudicated exclusively by campus security administrators.
                </p>
            </div>
            <span class="badge badge-info">Desk Verification</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($mySubmittedClaims)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Item Title</th>
                                <th>Category &amp; Building</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                                <th>Admin Decision &amp; Instructions</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mySubmittedClaims as $claim): ?>
                                <tr>
                                    <td><code>#CLM-<?= e($claim['id']) ?></code></td>
                                    <td>
                                        <strong>
                                            <a href="<?= url('item-details.php?id=' . $claim['item_id']) ?>">
                                                <?= e($claim['item_title']) ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?= e($claim['category_name']) ?> &bull; <?= e($claim['building']) ?></td>
                                    <td><?= format_date($claim['created_at']) ?></td>
                                    <td>
                                        <?php if ($claim['status'] === 'approved' && !empty($claim['handover_date'])): ?>
                                            <span class="badge badge-success">Handed Over</span>
                                        <?php elseif ($claim['status'] === 'approved'): ?>
                                            <span class="badge badge-success">Approved</span>
                                        <?php elseif ($claim['status'] === 'under_review'): ?>
                                            <span class="badge badge-info">Under Review</span>
                                        <?php elseif ($claim['status'] === 'rejected'): ?>
                                            <span class="badge badge-danger">Rejected</span>
                                        <?php elseif ($claim['status'] === 'cancelled'): ?>
                                            <span class="badge badge-secondary">Cancelled</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pending Review</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($claim['status'] === 'approved' && !empty($claim['handover_date'])): ?>
                                            <div style="font-size: 0.8125rem; color: var(--color-success); font-weight: 600;">
                                                &check; Handover completed on <?= format_datetime($claim['handover_date']) ?>.
                                            </div>
                                        <?php elseif ($claim['status'] === 'approved'): ?>
                                            <div style="font-size: 0.8125rem; color: var(--color-success); font-weight: 600;">
                                                &check; Approved for handover at Security Desk!
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                                Bring your Student ID card to Ground Floor Admin Block.
                                            </div>
                                        <?php elseif ($claim['status'] === 'rejected'): ?>
                                            <div style="font-size: 0.8125rem; color: var(--color-danger);">
                                                <?= !empty($claim['admin_notes']) ? e($claim['admin_notes']) : 'Proof details were insufficient or mismatched.' ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                                Administrator is comparing proof against private features.
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="<?= url('item-details.php?id=' . $claim['item_id']) ?>" class="btn btn-outline btn-sm">
                                            View Item
                                        </a>
                                        <?php if (in_array($claim['status'], ['pending', 'under_review', 'approved'], true) && empty($claim['handover_date'])): ?>
                                            <form method="POST" action="<?= url('api/claims/cancel.php') ?>" class="claim-cancel-form" style="margin-top: var(--space-2);">
                                                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                                <input type="hidden" name="claim_id" value="<?= e($claim['id']) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Cancel Claim</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: var(--space-8); margin: 0; border: none;">
                    <h3 class="empty-state-title" style="font-size: 1.125rem;">No Submitted Claims</h3>
                    <p class="empty-state-desc" style="font-size: 0.875rem;">
                        You have not claimed any found items yet. Browse the campus catalog to find missing belongings.
                    </p>
                    <a href="<?= url('items.php') ?>" class="btn btn-primary btn-sm">Browse Campus Items</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Panel 2: Claims Received on Items I Found -->
    <div class="card">
        <div class="card-header">
            <div>
                <strong style="font-size: 1.125rem;">Claims on Items I Found (<?= count($claimsOnMyFoundItems) ?>)</strong>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0;">
                    Read-only summary of claims submitted by fellow campus members.
                </p>
            </div>
            <span class="badge badge-secondary">Read-Only</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($claimsOnMyFoundItems)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Item Found by You</th>
                                <th>Claimant Student</th>
                                <th>Claim Date</th>
                                <th>Admin Verification Status</th>
                                <th>Custody Action Needed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claimsOnMyFoundItems as $rec): ?>
                                <tr>
                                    <td><code>#CLM-<?= e($rec['claim_id']) ?></code></td>
                                    <td>
                                        <strong>
                                            <a href="<?= url('item-details.php?id=' . $rec['item_id']) ?>">
                                                <?= e($rec['item_title']) ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <?= e($rec['claimant_name']) ?>
                                        <span style="font-size: 0.75rem; color: var(--color-text-muted);">(<?= e($rec['claimant_dept']) ?>)</span>
                                    </td>
                                    <td><?= format_date($rec['claim_date']) ?></td>
                                    <td>
                                        <span class="badge <?= $rec['claim_status'] === 'approved' ? 'badge-success' : ($rec['claim_status'] === 'rejected' ? 'badge-danger' : ($rec['claim_status'] === 'under_review' ? 'badge-info' : ($rec['claim_status'] === 'cancelled' ? 'badge-secondary' : 'badge-warning'))) ?>">
                                            <?= ucfirst(e($rec['claim_status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($rec['claim_status'] === 'approved' && $rec['custody_status'] === 'with_finder'): ?>
                                            <span style="color: var(--color-warning); font-weight: 600; font-size: 0.8125rem;">
                                                &bull; Please surrender item to Security Desk for owner collection.
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--color-text-muted); font-size: 0.8125rem;">
                                                No action required. Desk handles handover.
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: var(--space-8); margin: 0; border: none;">
                    <h3 class="empty-state-title" style="font-size: 1.125rem;">No Incoming Claims on Found Items</h3>
                    <p class="empty-state-desc" style="font-size: 0.875rem;">
                        None of the items you turned in or reported found currently have active ownership claims.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
