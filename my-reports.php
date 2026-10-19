<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * User's Submitted Reports (Tabbed: Lost vs Found with Custody Update)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

init_session();

// Guard: Must be authenticated
if (!is_logged_in()) {
    flash_set('warning', 'Please log in to manage your reports.');
    header('Location: ' . url('login.php?redirect=my-reports.php'));
    exit;
}

$pageTitle = 'My Submitted Reports - ' . APP_SHORT_NAME;
$activeNav = 'dashboard';

$currentUser = get_current_user_session();
$userId = (int)$currentUser['id'];

$tab = $_GET['tab'] ?? 'lost';
$lostReports = [];
$foundReports = [];

try {
    $pdo = get_db_connection();

    // Lost items
    $stmt = $pdo->prepare(
        "SELECT i.*, c.name AS category_name, l.building, l.specific_area
         FROM items i
         JOIN categories c ON i.category_id = c.id
         JOIN locations l ON i.location_id = l.id
         WHERE i.user_id = :uid AND i.item_type = 'lost'
         ORDER BY i.created_at DESC"
    );
    $stmt->execute([':uid' => $userId]);
    $lostReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Found items
    $stmt = $pdo->prepare(
        "SELECT i.*, c.name AS category_name, l.building, l.specific_area
         FROM items i
         JOIN categories c ON i.category_id = c.id
         JOIN locations l ON i.location_id = l.id
         WHERE i.user_id = :uid AND i.item_type = 'found'
         ORDER BY i.created_at DESC"
    );
    $stmt->execute([':uid' => $userId]);
    $foundReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    // Graceful error fallback
}

$extraScripts = ['assets/js/items.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('dashboard.php') ?>">Dashboard</a> &rsaquo; 
        <span>My Reports</span>
    </nav>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <span class="badge badge-primary" style="margin-bottom: var(--space-2);">Activity Management</span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
                My Submitted Reports
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Manage items you reported, update custody from finder to security desk, or close resolved posts.
            </p>
        </div>

        <div style="display: flex; gap: var(--space-3);">
            <a href="<?= url('report-lost.php') ?>" class="btn btn-primary btn-sm">+ Report Lost</a>
            <a href="<?= url('report-found.php') ?>" class="btn btn-secondary btn-sm">+ Report Found</a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: var(--space-3); border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-6);">
        <a href="<?= url('my-reports.php?tab=lost') ?>" 
           class="nav-link <?= $tab === 'lost' ? 'active' : '' ?>" 
           style="padding-bottom: var(--space-3); font-weight: 700; font-size: 1rem;">
            Lost Reports (<?= count($lostReports) ?>)
        </a>
        <a href="<?= url('my-reports.php?tab=found') ?>" 
           class="nav-link <?= $tab === 'found' ? 'active' : '' ?>" 
           style="padding-bottom: var(--space-3); font-weight: 700; font-size: 1rem;">
            Found Reports (<?= count($foundReports) ?>)
        </a>
    </div>

    <!-- Tab Content -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <?php 
            $activeList = ($tab === 'found') ? $foundReports : $lostReports;
            if (!empty($activeList)): 
            ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item Title</th>
                                <th>Category</th>
                                <th>Building</th>
                                <th>Incident Date</th>
                                <?php if ($tab === 'found'): ?>
                                    <th>Custody Status</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeList as $item): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?= url('item-details.php?id=' . $item['id']) ?>">
                                                <?= e($item['title']) ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?= e($item['category_name']) ?></td>
                                    <td><?= e($item['building']) ?></td>
                                    <td><?= format_date($item['incident_date']) ?></td>
                                    <?php if ($tab === 'found'): ?>
                                        <td>
                                            <span class="badge badge-custody-<?= $item['custody_status'] === 'submitted_to_security' ? 'security' : 'finder' ?>">
                                                <?= $item['custody_status'] === 'submitted_to_security' ? 'Security Desk' : 'With Me' ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge <?= $item['status'] === 'open' ? 'badge-success' : 'badge-secondary' ?>">
                                            <?= ucfirst(e($item['status'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="<?= url('item-details.php?id=' . $item['id']) ?>" class="btn btn-outline btn-sm">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="border: none; margin: 0; padding: var(--space-10);">
                    <h3 class="empty-state-title" style="font-size: 1.125rem;">
                        No <?= ucfirst($tab) ?> Reports Filed
                    </h3>
                    <p class="empty-state-desc" style="font-size: 0.875rem;">
                        You have not reported any <?= $tab ?> items yet.
                    </p>
                    <a href="<?= url($tab === 'found' ? 'report-found.php' : 'report-lost.php') ?>" class="btn btn-primary btn-sm">
                        Report <?= ucfirst($tab) ?> Item Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
