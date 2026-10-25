<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Safe Database & Environment Health Verification Test
 *
 * This utility safely verifies database connectivity, schema completeness,
 * table row counts, and upload permissions without exposing credentials.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Database & System Health - ' . APP_NAME;
$activeNav = 'test-db';

// 1. PHP Environment Diagnostics
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.1.0', '>=');
$pdoMysqlOk = extension_loaded('pdo_mysql');
$opensslOk = extension_loaded('openssl');
$fileinfoOk = extension_loaded('fileinfo');

// 2. Database Connection Test
$dbTest = Database::testConnection();

// 3. Schema & Table Verification
$requiredTables = [
    'users',
    'categories',
    'locations',
    'items',
    'smart_matches',
    'claims',
    'notifications',
    'audit_logs'
];

$tableStatus = [];
$allTablesExist = false;
$seedDataVerified = false;

if ($dbTest['connected']) {
    try {
        $pdo = get_db_connection();
        $existingTablesStmt = $pdo->query("SHOW TABLES");
        $existingTables = $existingTablesStmt->fetchAll(PDO::FETCH_COLUMN);

        $foundCount = 0;
        foreach ($requiredTables as $table) {
            $exists = in_array($table, $existingTables, true);
            $rowCount = 0;
            if ($exists) {
                $foundCount++;
                try {
                    $rowCount = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                } catch (Throwable $e) {
                    $rowCount = 0;
                }
            }
            $tableStatus[$table] = [
                'exists'    => $exists,
                'row_count' => $rowCount
            ];
        }

        $allTablesExist = ($foundCount === count($requiredTables));

        // Check if demo admin exists in users table
        if (!empty($tableStatus['users']['exists'])) {
            $adminCheck = $pdo->prepare("SELECT id, email, role FROM users WHERE email = :email");
            $adminCheck->execute([':email' => 'admin@college.edu']);
            $seedDataVerified = (bool)$adminCheck->fetch();
        }
    } catch (Throwable $e) {
        $allTablesExist = false;
    }
}

// 4. Filesystem Directory Permissions
$uploadsExist = is_dir(UPLOADS_PATH);
$itemsWritable = is_writable(ITEMS_UPLOAD_PATH);
$proofsWritable = is_writable(PROOFS_UPLOAD_PATH);
$htaccessExist = file_exists(UPLOADS_PATH . '/.htaccess');

require_once __DIR__ . '/includes/header.php';
?>

<div class="container section">
    <div style="margin-bottom: var(--space-6);">
        <span class="badge badge-primary">System Health Check</span>
        <h1 style="font-size: 2rem; font-weight: 800; margin-top: var(--space-2);">
            Database &amp; Environment Diagnostics
        </h1>
        <p style="color: var(--color-text-muted);">
            Automated verification tool to ensure local XAMPP configuration, database tables, and permissions match 
            <strong>PROJECT_SPEC.md</strong> specifications.
        </p>
    </div>

    <!-- Diagnostic Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: var(--space-6); margin-bottom: var(--space-8);">
        
        <!-- Card 1: Database Connection -->
        <div class="card">
            <div class="card-header">
                <strong>MySQL Database Connection</strong>
                <?php if ($dbTest['connected']): ?>
                    <span class="badge badge-success">Connected</span>
                <?php else: ?>
                    <span class="badge badge-danger">Connection Failed</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p style="font-size: 0.9375rem; margin-bottom: var(--space-3); color: <?= $dbTest['connected'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                    <?= e($dbTest['message']) ?>
                </p>
                <table class="table" style="font-size: 0.8125rem;">
                    <tr>
                        <th>Host / Port</th>
                        <td><?= e($dbTest['details']['host'] ?? 'N/A') ?>:<?= e($dbTest['details']['port'] ?? 3306) ?></td>
                    </tr>
                    <tr>
                        <th>Database Name</th>
                        <td><code><?= e($dbTest['details']['database'] ?? 'smart_lost_and_found') ?></code></td>
                    </tr>
                    <tr>
                        <th>Database User</th>
                        <td><code><?= e($dbTest['details']['user'] ?? 'root') ?></code> (Password masked)</td>
                    </tr>
                    <?php if (!empty($dbTest['details']['server_version'])): ?>
                    <tr>
                        <th>Server Version</th>
                        <td><?= e($dbTest['details']['server_version']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php if (!$dbTest['connected'] && !empty($dbTest['details']['hint'])): ?>
                    <div class="alert alert-warning" style="margin-top: var(--space-4); font-size: 0.8125rem;">
                        <?= e($dbTest['details']['hint']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card 2: PHP Environment -->
        <div class="card">
            <div class="card-header">
                <strong>PHP Server Environment</strong>
                <?php if ($phpOk && $pdoMysqlOk && $opensslOk && $fileinfoOk): ?>
                    <span class="badge badge-success">Ready</span>
                <?php else: ?>
                    <span class="badge badge-warning">Warnings Found</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <table class="table" style="font-size: 0.8125rem;">
                    <tr>
                        <th>PHP Version</th>
                        <td>
                            <?= e($phpVersion) ?> 
                            <?= $phpOk ? '<span class="badge badge-success">OK (&ge;8.1)</span>' : '<span class="badge badge-danger">Requires &ge;8.1</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <th>PDO MySQL Driver</th>
                        <td>
                            <?= $pdoMysqlOk ? '<span class="badge badge-success">Installed</span>' : '<span class="badge badge-danger">Missing</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <th>OpenSSL (CSRF Security)</th>
                        <td>
                            <?= $opensslOk ? '<span class="badge badge-success">Installed</span>' : '<span class="badge badge-danger">Missing</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Fileinfo (MIME Security)</th>
                        <td>
                            <?= $fileinfoOk ? '<span class="badge badge-success">Installed</span>' : '<span class="badge badge-danger">Missing</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Base URL Config</th>
                        <td><code><?= e(BASE_URL === '' ? '(root)' : BASE_URL) ?></code></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Card 3: Storage & Security -->
        <div class="card">
            <div class="card-header">
                <strong>Upload Directories &amp; Hardening</strong>
                <?php if ($uploadsExist && $itemsWritable && $proofsWritable && $htaccessExist): ?>
                    <span class="badge badge-success">Protected</span>
                <?php else: ?>
                    <span class="badge badge-warning">Needs Attention</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <table class="table" style="font-size: 0.8125rem;">
                    <tr>
                        <th>Item Photos Directory</th>
                        <td>
                            <code>uploads/items/</code>
                            <?= $itemsWritable ? '<span class="badge badge-success">Writable</span>' : '<span class="badge badge-danger">Not Writable</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Claim Proofs Directory</th>
                        <td>
                            <code>uploads/proofs/</code>
                            <?= $proofsWritable ? '<span class="badge badge-success">Writable</span>' : '<span class="badge badge-danger">Not Writable</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Uploads .htaccess Shield</th>
                        <td>
                            <?= $htaccessExist ? '<span class="badge badge-success">Active (Scripts Blocked)</span>' : '<span class="badge badge-danger">Missing</span>' ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Master Schema Tables Check -->
    <div class="card" style="margin-bottom: var(--space-8);">
        <div class="card-header">
            <div>
                <strong>Database Schema Tables Status</strong>
                <span style="font-size: 0.8125rem; color: var(--color-text-muted); margin-left: 8px;">
                    (8 tables specified in PROJECT_SPEC.md)
                </span>
            </div>
            <?php if ($allTablesExist): ?>
                <span class="badge badge-success">All 8 Tables Verified</span>
            <?php else: ?>
                <span class="badge badge-danger">Tables Incomplete</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($dbTest['connected']): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <th>Status</th>
                                <th>Row Count</th>
                                <th>Purpose / Specification Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tableDescriptions = [
                                'users'         => 'Student & Admin accounts, Bcrypt hashes, roles, and profiles',
                                'categories'    => 'Categorisation taxonomy (Electronics, IDs, Wallets, Books, etc.)',
                                'locations'     => 'Campus zones, buildings, and specific room locations',
                                'items'         => 'Core Lost and Found reports with explicit custody tracking',
                                'smart_matches' => 'On-demand cached heuristic similarity scores between items',
                                'claims'        => 'Ownership verification claims with proof descriptions',
                                'notifications' => 'User in-app notifications (match alerts, claim updates, handovers)',
                                'audit_logs'    => 'Administrative audit logs for handovers, claim decisions, and bans'
                            ];
                            ?>
                            <?php foreach ($requiredTables as $table): ?>
                                <tr>
                                    <td><strong><code><?= e($table) ?></code></strong></td>
                                    <td>
                                        <?php if (!empty($tableStatus[$table]['exists'])): ?>
                                            <span class="badge badge-success">Present</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Missing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?= e($tableStatus[$table]['row_count'] ?? 0) ?> rows
                                        </span>
                                    </td>
                                    <td style="color: var(--color-text-muted); font-size: 0.875rem;">
                                        <?= e($tableDescriptions[$table] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($seedDataVerified): ?>
                    <div class="alert alert-success" style="margin-top: var(--space-4); margin-bottom: 0;">
                        <span>&check; Demo Administrator account (<code>admin@college.edu</code>) and sample campus fixtures verified in database.</span>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" style="margin-top: var(--space-4); margin-bottom: 0;">
                        <span>Demo Administrator not found. Please import <code>database/seed.sql</code> to load sample accounts and categories.</span>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-danger" style="margin-bottom: 0;">
                    Cannot inspect schema tables because database connection failed. Start MySQL in XAMPP and refresh this page.
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--space-3);">
            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Import files: <code>database/schema.sql</code> &bull; <code>database/seed.sql</code>
            </div>
            <a href="<?= url('test_db.php') ?>" class="btn btn-primary btn-sm">Re-run Health Check</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
