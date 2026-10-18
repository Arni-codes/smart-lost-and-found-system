<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Campus Security Desk Physical Handover Terminal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

init_session();

// Guard: Strictly Admin / Desk Officer Only
if (!is_admin()) {
    flash_set('danger', 'Access denied. Administrator privileges required.');
    header('Location: ' . url('login.php'));
    exit;
}

$pageTitle = 'Desk Handover Terminal - ' . APP_SHORT_NAME;
$activeNav = 'admin';

$approvedClaims = [];
$completedHandovers = [];

try {
    $pdo = get_db_connection();

    // Fetch approved claims eligible for physical handover (handover_date IS NULL)
    $stmt = $pdo->query(
        "SELECT c.id AS claim_id, c.status, c.created_at, c.admin_notes,
                i.id AS item_id, i.title AS item_title, i.custody_status,
                u.id AS claimant_id, u.full_name AS claimant_name, u.student_id, u.department, u.phone
         FROM claims c
         JOIN items i ON c.item_id = i.id
         JOIN users u ON c.claimant_id = u.id
         WHERE c.status = 'approved' AND c.handover_date IS NULL
         ORDER BY c.reviewed_at DESC"
    );
    $approvedClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent completed handovers
    $stmt = $pdo->query(
        "SELECT c.id AS claim_id, c.handover_date,
                i.title AS item_title,
                u.full_name AS claimant_name, u.student_id
         FROM claims c
         JOIN items i ON c.item_id = i.id
         JOIN users u ON c.claimant_id = u.id
         WHERE c.handover_date IS NOT NULL
         ORDER BY c.handover_date DESC
         LIMIT 6"
    );
    $completedHandovers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    // Graceful error fallback
}

$extraScripts = ['assets/js/admin.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('admin/index.php') ?>">Admin Desk</a> &rsaquo; 
        <span>Desk Handover Terminal</span>
    </nav>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--space-8); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <span class="badge badge-success" style="margin-bottom: var(--space-2);">Campus Security Desk Handover</span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
                Physical Handover Terminal
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Ground Floor Admin Block &bull; Verify physical student ID card and execute atomic property return transaction.
            </p>
        </div>

        <div style="display: flex; gap: var(--space-3);">
            <a href="<?= url('admin/manage-claims.php') ?>" class="btn btn-primary btn-sm">
                Adjudicate Claims Queue
            </a>
            <a href="<?= url('admin/index.php') ?>" class="btn btn-secondary btn-sm">
                Admin Dashboard
            </a>
        </div>
    </div>

    <!-- Active Approved Claims Awaiting Pickup -->
    <div class="card" style="margin-bottom: var(--space-8); border-top: 4px solid var(--color-success);">
        <div class="card-header">
            <div>
                <strong style="font-size: 1.125rem;">Approved Claims Ready for Desk Handover (<?= count($approvedClaims) ?>)</strong>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0;">
                    These claims have been authorized by administrators and are awaiting student in-person verification.
                </p>
            </div>
            <span class="badge badge-success">Atomic Auto-Closure Ready</span>
        </div>

        <div class="card-body">
            <?php if (!empty($approvedClaims)): ?>
                <div style="display: flex; flex-direction: column; gap: var(--space-6);">
                    <?php foreach ($approvedClaims as $claim): ?>
                        <div style="border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-6); background: #fdfdfd;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-2);">
                                <div>
                                    <span class="badge badge-primary" style="margin-bottom: var(--space-1);">Claim #CLM-<?= e($claim['claim_id']) ?></span>
                                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--color-text); margin: 0;">
                                        Item: <?= e($claim['item_title']) ?>
                                    </h3>
                                    <div style="font-size: 0.875rem; color: var(--color-text-muted); margin-top: 4px;">
                                        Claimant: <strong><?= e($claim['claimant_name']) ?></strong> &bull; 
                                        Roll No: <code><?= e($claim['student_id']) ?></code> &bull; 
                                        Dept: <?= e($claim['department']) ?> &bull; 
                                        Phone: <?= e($claim['phone']) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge badge-success">Approved for Return</span>
                                </div>
                            </div>

                            <!-- Physical ID Verification Checklist Form -->
                            <form method="POST" action="<?= url('api/admin/handover.php') ?>" class="handover-checklist-form" style="background: #f8fafc; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-5);">
                                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                <input type="hidden" name="claim_id" value="<?= e($claim['claim_id']) ?>">

                                <div style="font-weight: 700; font-size: 0.9375rem; margin-bottom: var(--space-3); color: var(--color-text);">
                                    Desk Officer Verification Checklist:
                                </div>

                                <div class="checklist-item">
                                    <input type="checkbox" id="checkId_<?= $claim['claim_id'] ?>" name="student_id_confirmed" value="1" required>
                                    <label for="checkId_<?= $claim['claim_id'] ?>" style="cursor: pointer;">
                                        I have physically inspected the student's College ID card matching roll number <strong><code><?= e($claim['student_id']) ?></code></strong> and full name <strong><?= e($claim['claimant_name']) ?></strong>.
                                    </label>
                                </div>

                                <div class="checklist-item" style="margin-top: var(--space-2);">
                                    <input type="checkbox" id="checkItem_<?= $claim['claim_id'] ?>" required>
                                    <label for="checkItem_<?= $claim['claim_id'] ?>" style="cursor: pointer;">
                                        Physical item condition inspected and handed directly to claimant.
                                    </label>
                                </div>

                                <div class="form-group" style="margin-top: var(--space-4); margin-bottom: var(--space-4);">
                                    <label for="handoverNotes_<?= $claim['claim_id'] ?>" class="form-label" style="font-size: 0.8125rem;">
                                        Desk Officer Handover Notes (Optional)
                                    </label>
                                    <input type="text" 
                                           id="handoverNotes_<?= $claim['claim_id'] ?>" 
                                           name="handover_notes" 
                                           class="form-control" 
                                           placeholder="e.g. Student ID verified in person; item collected in good condition.">
                                </div>

                                <div style="display: flex; justify-content: flex-end;">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        &check; Execute Atomic Handover &amp; Close Report
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="border: none; margin: 0; padding: var(--space-8);">
                    <h3 class="empty-state-title" style="font-size: 1.125rem;">No Claims Pending Handover</h3>
                    <p class="empty-state-desc" style="font-size: 0.875rem;">
                        There are currently no approved claims waiting for physical property return at the Security Desk.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Completed Handovers Audit Table -->
    <div class="card">
        <div class="card-header">
            <strong>Recent Completed Desk Handovers (Audit Log)</strong>
            <span class="badge badge-secondary">Immutable Log</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($completedHandovers)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Item Reunited</th>
                                <th>Owner Name</th>
                                <th>Student Roll No</th>
                                <th>Completed Timestamp</th>
                                <th>Custody Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedHandovers as $done): ?>
                                <tr>
                                    <td><code>#CLM-<?= e($done['claim_id']) ?></code></td>
                                    <td><strong><?= e($done['item_title']) ?></strong></td>
                                    <td><?= e($done['claimant_name']) ?></td>
                                    <td><code><?= e($done['student_id']) ?></code></td>
                                    <td><?= format_datetime($done['handover_date']) ?></td>
                                    <td>
                                        <span class="badge badge-custody-returned">&check; Returned to Owner</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="border: none; margin: 0; padding: var(--space-6);">
                    <p class="empty-state-desc" style="font-size: 0.875rem; margin: 0;">
                        No completed handovers logged yet.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
