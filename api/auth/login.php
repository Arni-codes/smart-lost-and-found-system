<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/auth/login.php — Authenticate & Start Session
 *
 * Validates credentials, verifies bcrypt hash, regenerates session ID,
 * stores minimal user metadata in session, returns JSON redirect.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

init_session();

if (is_logged_in()) {
    json_response(true, 'Already authenticated.', ['redirect' => url('dashboard.php')]);
}

// ── CSRF guard ───────────────────────────────────────────────────────────────
csrf_verify();

// ── Collect inputs ───────────────────────────────────────────────────────────
$email    = trim(strtolower($_POST['email']    ?? ''));
$password = $_POST['password'] ?? '';
$remember = !empty($_POST['remember_me']);

// ── Basic format validation ──────────────────────────────────────────────────
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please enter a valid email address.', null, null, 422);
}

if (empty($password)) {
    json_response(false, 'Password is required.', null, null, 422);
}

// ── Lookup user by email ─────────────────────────────────────────────────────
try {
    $pdo  = get_db_connection();
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, password_hash, role, status
         FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Deliberate timing-safe comparison even on no-match to prevent user enumeration
    $dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
    $hashToCheck = $user ? $user['password_hash'] : $dummyHash;

    if (!$user || !password_verify($password, $hashToCheck)) {
        // Generic message — do not confirm whether the email exists
        json_response(false, 'Incorrect email address or password. Please check your credentials and try again.', null, null, 401);
    }

    // Account active check
    if ($user['status'] !== 'active') {
        json_response(false, 'Your account has been suspended. Please contact the campus security desk for assistance.', null, null, 403);
    }

    // ── Successful login ─────────────────────────────────────────────────────

    // Security: regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Re-issue CSRF token on new session
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Store minimal, non-sensitive session data
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];

    // Handle "Remember Me": extend cookie lifetime to 30 days
    if ($remember) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            time() + (30 * 24 * 3600),  // 30 days
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Audit log
    log_audit_event('USER_LOGIN', 'user', (int) $user['id'], [
        'email'      => $user['email'],
        'role'       => $user['role'],
        'remember_me'=> $remember,
    ], (int) $user['id']);

    // Redirect destination
    $redirect = $user['role'] === 'admin' ? url('admin/index.php') : url('dashboard.php');

    json_response(true, 'Login successful. Redirecting…', ['redirect' => $redirect]);

} catch (PDOException $e) {
    error_log('Login endpoint DB error: ' . $e->getMessage());
    json_response(false, 'A database error occurred. Please try again.', null, null, 500);
}
