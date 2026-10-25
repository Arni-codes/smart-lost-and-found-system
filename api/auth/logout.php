<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/auth/logout.php — Destroy Session & Log Out
 *
 * Accepts POST (CSRF-protected) from the JS fetch() call or a direct
 * GET/POST href from the nav bar "Log Out" button.  Both are supported
 * so the nav link continues to work without JavaScript.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

init_session();

$isApiCall = (
    str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
    ($_SERVER['REQUEST_METHOD'] === 'POST')
);

// For POST requests (JS fetch), verify CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

if (is_logged_in()) {
    // Audit before destroying session
    log_audit_event('USER_LOGOUT', 'user', (int) ($_SESSION['user_id'] ?? 0), [], (int) ($_SESSION['user_id'] ?? 0));
}

// Destroy session and unset all session variables
$_SESSION = [];

// Invalidate the session cookie
$params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 42000,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
);

session_destroy();

if ($isApiCall && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'You have been logged out successfully.',
        'data'    => ['redirect' => url('login.php')],
        'errors'  => null,
    ]);
    exit;
}

// For GET nav-link logout: redirect to login page with flash
// (session already destroyed, start a new one just to set flash)
session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
flash_set('success', 'You have been logged out successfully.');
header('Location: ' . url('login.php'));
exit;
