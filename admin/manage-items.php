<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Master Item Moderation & Custody Management (Admin)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

require_admin(false);

$pageTitle = 'Manage Campus Items - ' . APP_SHORT_NAME;
$activeNav = 'admin';

$items = [];
$loadError = false;
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';

try {
    $pdo = get_db_connection();

    $where = ["1=1"];
    $params = [];

    if ($statusFilter !== 'all') {
        $where[] = "i.status = :status";
        $params[':status'] = $statusFilter;
    }

    if ($typeFilter !== 'all') {
        $where[] = "i.item_type = :type";
        $params[':type'] = $typeFilter;
    }

    $whereSql = implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT i.*, c.name AS category_name, l.building, u.full_name AS reporter_name, u.student_id
         FROM items i
         JOIN categories c ON i.category_id = c.id
         JOIN locations l ON i.location_id = l.id
         JOIN users u ON i.user_id = u.id
         WHERE $whereSql
         ORDER BY i.created_at DESC"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Admin/manage-items page error: ' . $e->getMessage());
    $loadError = true;
}

$extraScripts = ['assets/js/admin.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <?php if ($loadError): ?>
        <div class="alert alert-danger" role="alert">Item reports could not be loaded. Please try again later.</div>
    <?php endif; ?>
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('admin/index.php') ?>">Admin Desk</a> &rsaquo; 
        <span>Manage Items</span>
    </nav>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <span class="badge badge-warning" style="margin-bottom: var(--space-2);">Campus Moderation</span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
                Master Item Control Table
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Oversee lost and found posts, inspect private distinguishing marks, and moderate status.
            </p>
        </div>

        <div style="display: flex; gap: var(--space-3);">
            <a href="<?= url('admin/manage-claims.php') ?>" class="btn btn-primary btn-sm">Claims Queue</a>
            <a href="<?= url('admin/handover.php') ?>" class="btn btn-success btn-sm">Handover Terminal</a>
        </div>
    </div>

    <!-- Filters Row -->
    <div class="catalog-filters" style="margin-bottom: var(--space-6);">
        <form method="GET" action="<?= url('admin/manage-items.php') ?>" style="display: flex; gap: var(--space-4); flex-wrap: wrap; align-items: flex-end;">
            <div>
                <label for="adminTypeFilter" class="form-label" style="font-size: 0.75rem;">Report Type</label>
                <select id="adminTypeFilter" name="type" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="lost" <?= $typeFilter === 'lost' ? 'selected' : '' ?>>Lost Only</option>
                    <option value="found" <?= $typeFilter === 'found' ? 'selected' : '' ?>>Found Only</option>
                </select>
            </div>

            <div>
                <label for="adminStatusFilter" class="form-label" style="font-size: 0.75rem;">Status</label>
                <select id="adminStatusFilter" name="status" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="pending_claim" <?= $statusFilter === 'pending_claim' ? 'selected' : '' ?>>Pending Claim</option>
                    <option value="claimed" <?= $statusFilter === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <?php if ($statusFilter !== 'all' || $typeFilter !== 'all'): ?>
                <a href="<?= url('admin/manage-items.php') ?>" class="btn btn-secondary btn-sm" style="height: 42px; display: inline-flex; align-items: center;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Items Moderation Table Card -->
    <div class="card">
        <div class="card-header">
            <strong>Campus Inventory (<?= count($items) ?> items)</strong>
            <span class="badge badge-secondary">Admin Visibility</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($items)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Building</th>
                                <th>Reporter</th>
                                <th>Custody</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><code>#<?= e($it['id']) ?></code></td>
                                    <td>
                                        <span class="badge badge-<?= $it['item_type'] === 'lost' ? 'lost' : 'found' ?>">
                                            <?= ucfirst(e($it['item_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong>
                                            <a href="<?= url('item-details.php?id=' . $it['id']) ?>">
                                                <?= e($it['title']) ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?= e($it['category_name']) ?></td>
                                    <td><?= e($it['building']) ?></td>
                                    <td>
                                        <?= e($it['reporter_name']) ?>
                                        <span style="font-size: 0.75rem; color: var(--color-text-muted);">(<code><?= e($it['student_id']) ?></code>)</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-custody-<?= $it['custody_status'] === 'submitted_to_security' ? 'security' : ($it['custody_status'] === 'with_finder' ? 'finder' : ($it['custody_status'] === 'returned_to_owner' ? 'returned' : 'secondary')) ?>">
                                            <?= ucwords(str_replace('_', ' ', e($it['custody_status']))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $it['status'] === 'open' ? 'badge-success' : ($it['status'] === 'claimed' ? 'badge-primary' : 'badge-warning') ?>">
                                            <?= ucfirst(e($it['status'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="<?= url('item-details.php?id=' . $it['id']) ?>" class="btn btn-outline btn-sm">
                                            View Full
                                        </a>
                                        <?php if ($it['status'] === 'open'): ?>
                                            <form method="POST" action="<?= url('api/admin/items.php') ?>" class="admin-item-moderation-form" style="margin-top: var(--space-2); text-align: left; min-width: 220px;">
                                                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                                <input type="hidden" name="item_id" value="<?= e($it['id']) ?>">
                                                <label class="sr-only" for="moderationStatus<?= e($it['id']) ?>">New status for <?= e($it['title']) ?></label>
                                                <select id="moderationStatus<?= e($it['id']) ?>" name="status" class="form-select" required>
                                                    <option value="closed">Close as resolved</option>
                                                    <option value="rejected">Reject report</option>
                                                </select>
                                                <label class="sr-only" for="moderationReason<?= e($it['id']) ?>">Reason for moderating <?= e($it['title']) ?></label>
                                                <input id="moderationReason<?= e($it['id']) ?>" type="text" name="reason" class="form-control" minlength="5" maxlength="500" placeholder="Reason (5–500 characters)" required style="margin-top: var(--space-2);">
                                                <button type="submit" class="btn btn-danger btn-sm" style="margin-top: var(--space-2);">Moderate</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="border: none; margin: 0; padding: var(--space-8);">
                    <p class="empty-state-desc" style="font-size: 0.875rem; margin: 0;">
                        No items match the selected filter parameters.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
