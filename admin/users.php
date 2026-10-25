<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Admin User Directory & Account Oversight
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

require_admin(false);

$pageTitle = 'Campus Users - ' . APP_SHORT_NAME;
$activeNav = 'admin';

$users = [];
$search = trim($_GET['search'] ?? '');
$loadError = false;

try {
    $pdo = get_db_connection();

    if ($search !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, role, student_id, full_name, email, department, status, created_at
             FROM users 
             WHERE full_name LIKE :name_search OR student_id LIKE :id_search OR email LIKE :email_search
             ORDER BY created_at DESC"
        );
        $searchValue = '%' . $search . '%';
        $stmt->execute([
            ':name_search' => $searchValue,
            ':id_search' => $searchValue,
            ':email_search' => $searchValue,
        ]);
    } else {
        $stmt = $pdo->query(
            "SELECT id, role, student_id, full_name, email, department, status, created_at
             FROM users 
             ORDER BY created_at DESC"
        );
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Admin/users page error: ' . $e->getMessage());
    $loadError = true;
}

$extraScripts = ['assets/js/admin.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <?php if ($loadError): ?>
        <div class="alert alert-danger" role="alert">User accounts could not be loaded. Please try again later.</div>
    <?php endif; ?>
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('admin/index.php') ?>">Admin Desk</a> &rsaquo; 
        <span>User Accounts</span>
    </nav>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
        <div>
            <span class="badge badge-warning" style="margin-bottom: var(--space-2);">Campus Directory</span>
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: 2px;">
                Registered Campus Users
            </h1>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Search student and faculty profiles, view activity history, and monitor account statuses.
            </p>
        </div>

        <div style="display: flex; gap: var(--space-3);">
            <a href="<?= url('admin/index.php') ?>" class="btn btn-secondary btn-sm">
                Dashboard
            </a>
            <a href="<?= url('admin/manage-claims.php') ?>" class="btn btn-primary btn-sm">
                Claims Queue
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="catalog-filters" style="margin-bottom: var(--space-6);">
        <form method="GET" action="<?= url('admin/users.php') ?>" style="display: flex; gap: var(--space-3); max-width: 500px;">
            <div class="search-input-wrap">
                <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" 
                       name="search" 
                       value="<?= e($search) ?>" 
                       placeholder="Search by student name, roll number, or email...">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search !== ''): ?>
                <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table Card -->
    <div class="card">
        <div class="card-header">
            <strong>Campus Accounts (<?= count($users) ?> registered)</strong>
            <span class="badge badge-secondary">All Roles</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Roll / Staff ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Role &amp; Account Status</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><code><?= e($u['student_id'] ?? 'N/A') ?></code></td>
                                    <td><strong><?= e($u['full_name']) ?></strong></td>
                                    <td><?= e($u['email']) ?></td>
                                    <td><?= e($u['department'] ?? 'General') ?></td>
                                    <td>
                                        <form method="POST" action="<?= url('api/admin/users.php') ?>" class="admin-user-update-form" style="display: flex; gap: var(--space-2); align-items: center; min-width: 260px;">
                                            <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                            <label class="sr-only" for="userRole<?= e($u['id']) ?>">Role for <?= e($u['full_name']) ?></label>
                                            <select id="userRole<?= e($u['id']) ?>" name="role" class="form-select">
                                                <?php foreach (['student', 'staff', 'admin'] as $role): ?>
                                                    <option value="<?= e($role) ?>" <?= $u['role'] === $role ? 'selected' : '' ?>><?= ucfirst(e($role)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="sr-only" for="userStatus<?= e($u['id']) ?>">Status for <?= e($u['full_name']) ?></label>
                                            <select id="userStatus<?= e($u['id']) ?>" name="status" class="form-select">
                                                <?php foreach (['active', 'suspended'] as $status): ?>
                                                    <option value="<?= e($status) ?>" <?= $u['status'] === $status ? 'selected' : '' ?>><?= ucfirst(e($status)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        </form>
                                    </td>
                                    <td><?= format_date($u['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="border: none; margin: 0; padding: var(--space-8);">
                    <p class="empty-state-desc" style="font-size: 0.875rem; margin: 0;">
                        No users found matching your search.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
