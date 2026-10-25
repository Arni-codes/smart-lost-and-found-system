<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Student Profile & Account Settings View
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

init_session();

// Guard: Must be authenticated
if (!is_logged_in()) {
    flash_set('warning', 'Please log in to view your profile.');
    header('Location: ' . url('login.php?redirect=profile.php'));
    exit;
}

$pageTitle = 'My Profile - ' . APP_SHORT_NAME;
$activeNav = 'profile';

$currentUser = get_current_user_session();
$userId = (int)$currentUser['id'];
$user = null;

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, role, student_id, full_name, email, phone, department, status, created_at FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Graceful error fallback
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8); max-width: 860px;">
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('dashboard.php') ?>">Dashboard</a> &rsaquo; 
        <span>Profile &amp; Settings</span>
    </nav>

    <div style="margin-bottom: var(--space-8);">
        <span class="badge badge-primary" style="margin-bottom: var(--space-2);">User Account</span>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
            My Profile &amp; Preferences
        </h1>
        <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
            View your campus credentials and manage notification contact details.
        </p>
    </div>

    <!-- Profile Details Card -->
    <div class="card" style="margin-bottom: var(--space-8);">
        <div class="card-header">
            <strong>Campus Identity Details</strong>
            <span class="badge <?= ($user['status'] ?? '') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                <?= ucfirst(e($user['status'] ?? 'active')) ?>
            </span>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-6);">
                <div>
                    <label class="form-label" style="font-size: 0.75rem; color: var(--color-text-muted);">Full Name</label>
                    <div style="font-size: 1.125rem; font-weight: 700; color: var(--color-text);">
                        <?= e($user['full_name'] ?? '') ?>
                    </div>
                </div>

                <div>
                    <label class="form-label" style="font-size: 0.75rem; color: var(--color-text-muted);">Roll Number / Staff ID</label>
                    <div style="font-size: 1.125rem; font-family: var(--font-mono); font-weight: 700; color: var(--color-primary);">
                        <?= e($user['student_id'] ?? 'N/A') ?>
                    </div>
                </div>

                <div>
                    <label class="form-label" style="font-size: 0.75rem; color: var(--color-text-muted);">College Email</label>
                    <div style="font-size: 0.9375rem; color: var(--color-text);">
                        <?= e($user['email'] ?? '') ?>
                    </div>
                </div>

                <div>
                    <label class="form-label" style="font-size: 0.75rem; color: var(--color-text-muted);">Contact Phone</label>
                    <div style="font-size: 0.9375rem; color: var(--color-text);">
                        <?= e($user['phone'] ?? '') ?>
                    </div>
                </div>

                <div>
                    <label class="form-label" style="font-size: 0.75rem; color: var(--color-text-muted);">Academic Department</label>
                    <div style="font-size: 0.9375rem; color: var(--color-text);">
                        <?= e($user['department'] ?? 'General') ?>
                    </div>
                </div>

                <div>
                    <label class="form-label" style="font-size: 0.75rem; color: var(--color-text-muted);">Role</label>
                    <div style="font-size: 0.9375rem;">
                        <span class="badge <?= ($user['role'] ?? '') === 'admin' ? 'badge-warning' : 'badge-secondary' ?>">
                            <?= ucfirst(e($user['role'] ?? 'student')) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
