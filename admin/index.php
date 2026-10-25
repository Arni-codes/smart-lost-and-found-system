<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Admin & Campus Security Desk Executive Dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

require_admin(false);

$pageTitle = 'Admin Desk Dashboard - ' . APP_SHORT_NAME;
$activeNav = 'admin';

$stats = [
    'total_reported'    => 0,
    'open_lost'         => 0,
    'open_found'        => 0,
    'security_custody'  => 0,
    'pending_claims'    => 0,
    'reunited_total'    => 0,
    'recovery_rate'     => 0
];
$recentClaims = [];
$recentLogs = [];
$loadError = false;

try {
    $pdo = get_db_connection();

    $stats['total_reported']   = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $stats['open_lost']        = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE item_type = 'lost' AND status = 'open'")->fetchColumn();
    $stats['open_found']       = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE item_type = 'found' AND status = 'open'")->fetchColumn();
    $stats['security_custody'] = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE custody_status = 'submitted_to_security'")->fetchColumn();
    $stats['pending_claims']   = (int)$pdo->query("SELECT COUNT(*) FROM claims WHERE status IN ('pending', 'under_review')")->fetchColumn();
    $stats['reunited_total']   = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status = 'claimed'")->fetchColumn();

    if ($stats['total_reported'] > 0) {
        $stats['recovery_rate'] = round(($stats['reunited_total'] / $stats['total_reported']) * 100, 1);
    }

    // Pending claims needing adjudication
    $cStmt = $pdo->query(
        "SELECT c.id, c.status, c.created_at,
                i.title AS item_title, i.custody_status,
                u.full_name AS claimant_name, u.student_id
         FROM claims c
         JOIN items i ON c.item_id = i.id
         JOIN users u ON c.claimant_id = u.id
         WHERE c.status IN ('pending', 'under_review')
         ORDER BY c.created_at ASC
         LIMIT 5"
    );
    $recentClaims = $cStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
    $loadError = true;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <?php if ($loadError): ?>
        <div class="alert alert-danger" role="alert">Dashboard data could not be loaded. Please try again later.</div>
    <?php endif; ?>
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <span>Admin Security Desk</span>
    </nav>

    <!-- Header & Navigation Shortcuts -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--space-8); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <span class="badge badge-warning" style="margin-bottom: var(--space-2);">Campus Security Officer View</span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
                Executive Administration Portal
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Adjudicate claims, manage campus custody inventory, and verify physical handovers at the desk.
            </p>
        </div>

        <div style="display: flex; gap: var(--space-3); flex-wrap: wrap;">
            <a href="<?= url('admin/manage-claims.php') ?>" class="btn btn-primary btn-sm">
                Adjudicate Claims (<?= $stats['pending_claims'] ?>)
            </a>
            <a href="<?= url('admin/handover.php') ?>" class="btn btn-success btn-sm">
                Desk Handover Terminal
            </a>
            <a href="<?= url('admin/manage-items.php') ?>" class="btn btn-secondary btn-sm">
                Manage Items
            </a>
            <a href="<?= url('admin/users.php') ?>" class="btn btn-outline btn-sm">
                User Accounts
            </a>
        </div>
    </div>

    <!-- Executive Metrics Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-8);">
        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-warning);"><?= $stats['pending_claims'] ?></div>
            <div class="stat-label">Pending Claims</div>
        </div>

        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-custody-security);"><?= $stats['security_custody'] ?></div>
            <div class="stat-label">In Security Custody</div>
        </div>

        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-danger);"><?= $stats['open_lost'] ?></div>
            <div class="stat-label">Active Lost</div>
        </div>

        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-info);"><?= $stats['open_found'] ?></div>
            <div class="stat-label">Active Found</div>
        </div>

        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-success);"><?= $stats['recovery_rate'] ?>%</div>
            <div class="stat-label">Recovery Rate</div>
        </div>
    </div>

    <!-- Pending Claims Requiring Adjudication Table -->
    <div class="card" style="margin-bottom: var(--space-8);">
        <div class="card-header">
            <div>
                <strong style="font-size: 1.125rem;">Claims Awaiting Admin Review</strong>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0;">
                    Review claimant proof description against finder's confidential private markings.
                </p>
            </div>
            <a href="<?= url('admin/manage-claims.php') ?>" class="btn btn-primary btn-sm">Open Claim Queue &rarr;</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($recentClaims)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Item Title</th>
                                <th>Claimant Student</th>
                                <th>Roll Number</th>
                                <th>Custody</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th style="text-align: right;">Review Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentClaims as $clm): ?>
                                <tr>
                                    <td><code>#CLM-<?= e($clm['id']) ?></code></td>
                                    <td><strong><?= e($clm['item_title']) ?></strong></td>
                                    <td><?= e($clm['claimant_name']) ?></td>
                                    <td><code><?= e($clm['student_id']) ?></code></td>
                                    <td>
                                        <span class="badge badge-custody-<?= $clm['custody_status'] === 'submitted_to_security' ? 'security' : 'finder' ?>">
                                            <?= $clm['custody_status'] === 'submitted_to_security' ? 'At Desk' : 'With Finder' ?>
                                        </span>
                                    </td>
                                    <td><?= format_date($clm['created_at']) ?></td>
                                    <td>
                                        <span class="badge badge-warning"><?= ucfirst(e($clm['status'])) ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="<?= url('admin/manage-claims.php?id=' . $clm['id']) ?>" class="btn btn-primary btn-sm">
                                            Adjudicate Proof
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: var(--space-8); margin: 0; border: none;">
                    <h3 class="empty-state-title" style="font-size: 1.125rem;">Zero Claims in Queue</h3>
                    <p class="empty-state-desc" style="font-size: 0.875rem;">
                        All submitted ownership claims have been reviewed. Excellent work!
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
