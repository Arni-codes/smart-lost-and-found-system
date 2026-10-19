<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Student / User Dashboard View
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

init_session();

// Guard: Must be authenticated
if (!is_logged_in()) {
    flash_set('warning', 'Please log in to access your dashboard.');
    header('Location: ' . url('login.php?redirect=dashboard.php'));
    exit;
}

$pageTitle = 'My Dashboard - ' . APP_SHORT_NAME;
$activeNav = 'dashboard';

$currentUser = get_current_user_session();
$userId = (int)$currentUser['id'];

// Default KPI metrics
$kpi = [
    'active_lost'   => 0,
    'active_found'  => 0,
    'pending_claims'=> 0,
    'unread_notifs' => 0
];
$userReports = [];
$matchSuggestions = [];
$userDetails = null;

try {
    $pdo = get_db_connection();

    // Fetch user profile info
    $uStmt = $pdo->prepare("SELECT student_id, department, email, phone FROM users WHERE id = :id");
    $uStmt->execute([':id' => $userId]);
    $userDetails = $uStmt->fetch(PDO::FETCH_ASSOC);

    // KPI 1: Active lost
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE user_id = :uid AND item_type = 'lost' AND status = 'open'");
    $stmt->execute([':uid' => $userId]);
    $kpi['active_lost'] = (int)$stmt->fetchColumn();

    // KPI 2: Active found
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE user_id = :uid AND item_type = 'found' AND status = 'open'");
    $stmt->execute([':uid' => $userId]);
    $kpi['active_found'] = (int)$stmt->fetchColumn();

    // KPI 3: Claims submitted
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE claimant_id = :uid AND status IN ('pending', 'under_review')");
    $stmt->execute([':uid' => $userId]);
    $kpi['pending_claims'] = (int)$stmt->fetchColumn();

    // KPI 4: Unread notifications
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute([':uid' => $userId]);
    $kpi['unread_notifs'] = (int)$stmt->fetchColumn();

    // Fetch user's recent reports (both lost and found)
    $stmt = $pdo->prepare(
        "SELECT i.*, c.name AS category_name, l.building, l.specific_area
         FROM items i
         JOIN categories c ON i.category_id = c.id
         JOIN locations l ON i.location_id = l.id
         WHERE i.user_id = :uid
         ORDER BY i.created_at DESC
         LIMIT 6"
    );
    $stmt->execute([':uid' => $userId]);
    $userReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch top cached smart match suggestions involving user's items
    $stmt = $pdo->prepare(
        "SELECT m.id AS match_id, m.match_score, m.score_breakdown, m.status AS match_status,
                lost.id AS lost_id, lost.title AS lost_title, lost.incident_date AS lost_date,
                found.id AS found_id, found.title AS found_title, found.custody_status,
                c.name AS category_name, l.building
         FROM smart_matches m
         JOIN items lost ON m.lost_item_id = lost.id
         JOIN items found ON m.found_item_id = found.id
         JOIN categories c ON lost.category_id = c.id
         JOIN locations l ON found.location_id = l.id
         WHERE (lost.user_id = :uid OR found.user_id = :uid) AND lost.status = 'open' AND found.status = 'open'
         ORDER BY m.match_score DESC
         LIMIT 3"
    );
    $stmt->execute([':uid' => $userId]);
    $matchSuggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    // Graceful error fallback
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <!-- Welcome Header Banner -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--space-8); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <span class="badge badge-primary" style="margin-bottom: var(--space-2);">Student Portal</span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
                Welcome, <?= e($currentUser['full_name']) ?>
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Roll No: <strong><?= e($userDetails['student_id'] ?? 'N/A') ?></strong> &bull; 
                Department: <strong><?= e($userDetails['department'] ?? 'Campus Member') ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: var(--space-3); flex-wrap: wrap;">
            <a href="<?= url('report-lost.php') ?>" class="btn btn-primary btn-sm">
                + Report Lost Item
            </a>
            <a href="<?= url('report-found.php') ?>" class="btn btn-secondary btn-sm">
                + Report Found Item
            </a>
            <a href="<?= url('items.php') ?>" class="btn btn-outline btn-sm">
                Browse Catalog
            </a>
        </div>
    </div>

    <!-- 4 KPI Metrics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-8);">
        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-danger);"><?= $kpi['active_lost'] ?></div>
            <div class="stat-label">My Active Lost</div>
        </div>

        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-info);"><?= $kpi['active_found'] ?></div>
            <div class="stat-label">My Active Found</div>
        </div>

        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-warning);"><?= $kpi['pending_claims'] ?></div>
            <div class="stat-label">Pending Claims</div>
        </div>

        <div class="stat-card">
            <div class="stat-number" style="color: var(--color-primary);"><?= $kpi['unread_notifs'] ?></div>
            <div class="stat-label">Unread Alerts</div>
        </div>
    </div>

    <!-- Smart Match Suggestions Section -->
    <div class="card" style="margin-bottom: var(--space-8);">
        <div class="card-header">
            <div>
                <strong style="font-size: 1.125rem;">Smart Match Suggestions for Your Reports</strong>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0;">
                    4-Factor similarity algorithm evaluates Category, Location, Date Proximity, and Keywords.
                </p>
            </div>
            <span class="badge badge-primary">Threshold: &ge; 40 pts</span>
        </div>
        <div class="card-body">
            <?php if (!empty($matchSuggestions)): ?>
                <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <?php foreach ($matchSuggestions as $match): ?>
                        <div class="match-card" style="margin-bottom: 0;">
                            <div class="match-card-header">
                                <div>
                                    <span class="badge badge-secondary" style="font-size: 0.6875rem; margin-right: 6px;">
                                        <?= e($match['category_name']) ?>
                                    </span>
                                    <strong style="font-size: 1.0625rem; color: var(--color-text);">
                                        "<?= e($match['lost_title']) ?>" &harr; "<?= e($match['found_title']) ?>"
                                    </strong>
                                </div>
                                <div>
                                    <span class="match-score-pill <?= (float)$match['match_score'] >= 70 ? 'high' : 'med' ?>" title="Heuristic similarity score (0 to 100 points)">
                                        <?= round((float)$match['match_score']) ?>% Similarity Score
                                    </span>
                                </div>
                            </div>

                            <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-3);">
                                Found at: <strong><?= e($match['building']) ?></strong> &bull; 
                                Custody: <strong><?= ucwords(str_replace('_', ' ', e($match['custody_status']))) ?></strong>
                            </p>

                            <!-- 4-Factor Breakdown Chips -->
                            <?php 
                            $breakdown = !empty($match['score_breakdown']) ? json_decode($match['score_breakdown'], true) : [];
                            if ($breakdown): 
                            ?>
                                <div class="score-breakdown-grid">
                                    <div>
                                        <div class="score-item-title">Category Match (Max 30)</div>
                                        <div class="score-item-val"><?= $breakdown['category'] ?? '30' ?> pts</div>
                                    </div>
                                    <div>
                                        <div class="score-item-title">Location Proximity (Max 25)</div>
                                        <div class="score-item-val"><?= $breakdown['location'] ?? '25' ?> pts</div>
                                    </div>
                                    <div>
                                        <div class="score-item-title">Date Proximity (Max 20)</div>
                                        <div class="score-item-val"><?= $breakdown['date'] ?? '20' ?> pts</div>
                                    </div>
                                    <div>
                                        <div class="score-item-title">Keyword Overlap (Max 25)</div>
                                        <div class="score-item-val"><?= $breakdown['keywords'] ?? '15' ?> pts</div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top: var(--space-4); display: flex; justify-content: flex-end; gap: var(--space-3);">
                                <a href="<?= url('item-details.php?id=' . $match['found_id']) ?>" class="btn btn-primary btn-sm">
                                    Inspect Match &amp; Claim Item &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: var(--space-8) var(--space-4); margin: 0;">
                    <h3 class="empty-state-title" style="font-size: 1.125rem;">No High-Confidence Matches Detected</h3>
                    <p class="empty-state-desc" style="font-size: 0.875rem;">
                        Our engine continuously evaluates open reports. When an item with matching category, building, or keywords is reported, you will receive an alert.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Recent Reports Table Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <strong style="font-size: 1.125rem;">My Submitted Reports</strong>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0;">
                    Manage reports filed under your student roll number.
                </p>
            </div>
            <a href="<?= url('items.php?author=' . $userId) ?>" class="btn btn-secondary btn-sm">View All in Catalog</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($userReports)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Item Title</th>
                                <th>Category</th>
                                <th>Building</th>
                                <th>Incident Date</th>
                                <th>Custody</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userReports as $rep): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-<?= $rep['item_type'] === 'lost' ? 'lost' : 'found' ?>">
                                            <?= ucfirst(e($rep['item_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong>
                                            <a href="<?= url('item-details.php?id=' . $rep['id']) ?>">
                                                <?= e($rep['title']) ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?= e($rep['category_name']) ?></td>
                                    <td><?= e($rep['building']) ?></td>
                                    <td><?= format_date($rep['incident_date']) ?></td>
                                    <td>
                                        <?php if ($rep['item_type'] === 'found'): ?>
                                            <?php if ($rep['custody_status'] === 'submitted_to_security'): ?>
                                                <span class="badge badge-custody-security">Security Desk</span>
                                            <?php elseif ($rep['custody_status'] === 'with_finder'): ?>
                                                <span class="badge badge-custody-finder">With Me</span>
                                            <?php else: ?>
                                                <span class="badge badge-custody-returned">Returned</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: var(--color-text-light);">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $rep['status'] === 'open' ? 'badge-success' : 'badge-secondary' ?>">
                                            <?= ucfirst(e($rep['status'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="<?= url('item-details.php?id=' . $rep['id']) ?>" class="btn btn-outline btn-sm">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: var(--space-8); margin: 0; border: none;">
                    <h3 class="empty-state-title" style="font-size: 1.125rem;">You Have Not Filed Any Reports Yet</h3>
                    <p class="empty-state-desc" style="font-size: 0.875rem;">
                        If you have misplaced personal belongings or found an unattended item on campus, report it below.
                    </p>
                    <div style="display: flex; gap: var(--space-3); justify-content: center;">
                        <a href="<?= url('report-lost.php') ?>" class="btn btn-primary btn-sm">Report Lost Item</a>
                        <a href="<?= url('report-found.php') ?>" class="btn btn-secondary btn-sm">Report Found Item</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
