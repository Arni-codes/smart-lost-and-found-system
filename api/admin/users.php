<?php
/**
 * GET /api/admin/users.php — Paginated user directory.
 * POST /api/admin/users.php — Update a user's role and/or account status.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    json_response(false, 'Method not allowed.', null, null, 405);
}

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');

    try {
        $pdo = get_db_connection();
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE full_name LIKE :name_search OR email LIKE :email_search OR student_id LIKE :id_search';
            $searchValue = '%' . $search . '%';
            $params = [
                ':name_search' => $searchValue,
                ':email_search' => $searchValue,
                ':id_search' => $searchValue,
            ];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users {$where}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT id, role, student_id, full_name, email, department, status, created_at
             FROM users {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        json_response(true, 'Users retrieved.', [
            'users' => $stmt->fetchAll(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ]);
    } catch (PDOException $e) {
        error_log('Admin/users GET error: ' . $e->getMessage());
        json_response(false, 'Failed to retrieve users.', null, null, 500);
    }
}

csrf_verify();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$role = trim($_POST['role'] ?? '');
$status = trim($_POST['status'] ?? '');
$validRoles = ['student', 'staff', 'admin'];
$validStatuses = ['active', 'suspended'];
if (!$userId || $userId < 1) {
    json_response(false, 'A valid user ID is required.', null, null, 400);
}
if (!in_array($role, $validRoles, true) || !in_array($status, $validStatuses, true)) {
    json_response(false, 'Select a valid role and account status.', null, null, 422);
}

$adminId = (int) $_SESSION['user_id'];
if ($userId === $adminId && ($role !== 'admin' || $status !== 'active')) {
    json_response(false, 'You cannot suspend or change your own administrator role.', null, null, 409);
}

$pdo = null;
try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $activeAdmins = $pdo->query(
        "SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id FOR UPDATE"
    )->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch();
    if (!$target) {
        throw new DomainException('User not found.', 404);
    }
    if ($target['role'] === $role && $target['status'] === $status) {
        $pdo->commit();
        json_response(true, 'User account is unchanged.', [
            'user_id' => $userId,
            'role' => $role,
            'status' => $status,
        ]);
    }

    $removesActiveAdmin = $target['role'] === 'admin'
        && $target['status'] === 'active'
        && ($role !== 'admin' || $status !== 'active');
    if ($removesActiveAdmin && count($activeAdmins) <= 1) {
        throw new DomainException('At least one active administrator account must remain.', 409);
    }

    $stmt = $pdo->prepare('UPDATE users SET role = :role, status = :status WHERE id = :id');
    $stmt->execute([':role' => $role, ':status' => $status, ':id' => $userId]);

    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
         VALUES (:uid, 'USER_ACCOUNT_UPDATED', 'user', :target_id, :details, :ip)"
    );
    $stmt->execute([
        ':uid' => $adminId,
        ':target_id' => $userId,
        ':details' => json_encode([
            'from_role' => $target['role'],
            'to_role' => $role,
            'from_status' => $target['status'],
            'to_status' => $status,
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $pdo->commit();
    json_response(true, 'User account updated.', [
        'user_id' => $userId,
        'role' => $role,
        'status' => $status,
    ]);
} catch (DomainException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $code = in_array($e->getCode(), [404, 409, 422], true) ? $e->getCode() : 422;
    json_response(false, $e->getMessage(), null, null, $code);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin/users POST error: ' . $e->getMessage());
    json_response(false, 'Failed to update user account.', null, null, 500);
}
