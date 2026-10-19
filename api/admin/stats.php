<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * GET /api/admin/stats.php — Admin Dashboard Metrics
 *
 * Authorization: Admin only.
 * Returns key performance indicators for the executive dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

require_admin();

try {
    $pdo = get_db_connection();

    // Total items
    $total = (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

    // Reunited (claimed + closed items that had an approved claim)
    $reunited = (int) $pdo->query(
        "SELECT COUNT(*) FROM items WHERE status = 'claimed'"
    )->fetchColumn();

    // Active open items
    $active = (int) $pdo->query(
        "SELECT COUNT(*) FROM items WHERE status = 'open'"
    )->fetchColumn();

    // Pending claims awaiting review
    $pendingClaims = (int) $pdo->query(
        "SELECT COUNT(*) FROM claims WHERE status IN ('pending', 'under_review')"
    )->fetchColumn();

    // Recovery rate
    $recoveryRate = $total > 0 ? round(($reunited / $total) * 100, 1) : 0.0;

    // Custody breakdown
    $custodyStmt = $pdo->query(
        "SELECT custody_status, COUNT(*) AS cnt
         FROM items WHERE item_type = 'found' AND status IN ('open','pending_claim')
         GROUP BY custody_status"
    );
    $custodyBreakdown = $custodyStmt->fetchAll();

    // Category breakdown (top 6)
    $catStmt = $pdo->query(
        'SELECT c.name, COUNT(i.id) AS cnt
         FROM items i JOIN categories c ON c.id = i.category_id
         GROUP BY c.id ORDER BY cnt DESC LIMIT 6'
    );
    $categoryBreakdown = $catStmt->fetchAll();

    // Recent audit logs
    $auditStmt = $pdo->query(
        'SELECT a.action, a.target_type, a.target_id, a.created_at, u.full_name
         FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC LIMIT 10'
    );
    $recentAudit = $auditStmt->fetchAll();

    // Registered users
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

    json_response(true, 'Dashboard metrics retrieved.', [
        'total_items'       => $total,
        'reunited_items'    => $reunited,
        'active_items'      => $active,
        'pending_claims'    => $pendingClaims,
        'recovery_rate_pct' => $recoveryRate,
        'user_count'        => $userCount,
        'active_users'      => $activeUsers,
        'custody_breakdown' => $custodyBreakdown,
        'category_breakdown'=> $categoryBreakdown,
        'recent_audit_logs' => $recentAudit,
    ]);

} catch (PDOException $e) {
    error_log('Admin/stats error: ' . $e->getMessage());
    json_response(false, 'Failed to retrieve dashboard metrics.', null, null, 500);
}
