<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Authentication & Authorization Middleware
 *
 * Provides:
 *  - require_login()   : Halt with 401 if guest
 *  - require_admin()   : Halt with 403 if not admin
 *  - csrf_verify()     : Halt with 403 on CSRF mismatch for mutating requests
 *  - log_audit_event() : Write an immutable row to audit_logs
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Enforce that the caller is authenticated.
 *
 * For API endpoints (JSON) returns 401 JSON and exits.
 * For HTML pages redirects to login and exits.
 *
 * @param bool $jsonMode When true, always respond with JSON (used inside api/ files).
 */
function require_login(bool $jsonMode = true): void
{
    init_session();

    if (!is_logged_in()) {
        if ($jsonMode) {
            json_response(false, 'Authentication required. Please log in.', null, null, 401);
        } else {
            flash_set('warning', 'Please log in to access that page.');
            header('Location: ' . url('login.php'));
            exit;
        }
    }

    // Verify the account is still active (not suspended by admin)
    try {
        $pdo  = get_db_connection();
        $stmt = $pdo->prepare('SELECT status FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $row  = $stmt->fetch();

        if (!$row || $row['status'] !== 'active') {
            // Destroy session and reject
            session_unset();
            session_destroy();
            if ($jsonMode) {
                json_response(false, 'Your account has been suspended. Please contact an administrator.', null, null, 403);
            } else {
                header('Location: ' . url('login.php'));
                exit;
            }
        }
    } catch (Throwable $e) {
        error_log('auth_middleware require_login DB error: ' . $e->getMessage());
        // Fail open only for read operations — for safety, still halt writes
        if ($jsonMode) {
            json_response(false, 'Service temporarily unavailable. Please try again.', null, null, 503);
        }
    }
}

/**
 * Enforce that the caller is an authenticated administrator.
 *
 * @param bool $jsonMode When true, always respond with JSON.
 */
function require_admin(bool $jsonMode = true): void
{
    require_login($jsonMode);

    if (!is_admin()) {
        if ($jsonMode) {
            json_response(false, 'Administrator access required. This action is not permitted for your role.', null, null, 403);
        } else {
            flash_set('danger', 'Administrator access is required to view that page.');
            header('Location: ' . url('index.php'));
            exit;
        }
    }
}

/**
 * Verify the CSRF token supplied with a state-changing request.
 *
 * Checks the `csrf_token` POST field AND the `X-CSRF-Token` header (for
 * JavaScript fetch() calls). Halts with HTTP 403 JSON on failure.
 */
function csrf_verify(): void
{
    init_session();

    // Accept token from POST body or custom request header
    $submitted = $_POST['csrf_token']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if (!verify_csrf_token($submitted)) {
        json_response(
            false,
            'Invalid or expired security token. Please refresh the page and try again.',
            null,
            null,
            403
        );
    }
}

/**
 * Write an immutable event row to the audit_logs table.
 *
 * @param string   $action      Enum-style verb (e.g. 'ITEM_CREATED', 'CLAIM_APPROVED')
 * @param string   $targetType  Entity type ('item', 'claim', 'user')
 * @param int      $targetId    Primary key of the affected row
 * @param array    $details     Arbitrary contextual data (stored as JSON)
 * @param int|null $userId      Performing user; defaults to current session user
 */
function log_audit_event(
    string $action,
    string $targetType,
    int    $targetId,
    array  $details  = [],
    ?int   $userId   = null
): void {
    try {
        if ($userId === null) {
            init_session();
            $userId = $_SESSION['user_id'] ?? null;
        }

        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $pdo  = get_db_connection();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
             VALUES (:uid, :action, :type, :tid, :details, :ip)'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':action'  => $action,
            ':type'    => $targetType,
            ':tid'     => $targetId,
            ':details' => !empty($details) ? json_encode($details) : null,
            ':ip'      => $ip,
        ]);
    } catch (Throwable $e) {
        // Never let audit failures break the user-facing flow
        error_log('log_audit_event failed: ' . $e->getMessage());
    }
}
