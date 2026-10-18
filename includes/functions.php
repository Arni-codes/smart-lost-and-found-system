<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Shared Helper Functions Library
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Context-aware HTML output escaping to prevent Cross-Site Scripting (XSS).
 *
 * @param string|null $string Raw user-supplied string
 * @return string Safely escaped string
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get the standardized application base URL.
 *
 * @param string $path Optional relative path to append (e.g. 'assets/css/style.css')
 * @return string Full application URL or relative web path
 */
function url(string $path = ''): string {
    $base = rtrim(BASE_URL, '/');
    $cleanPath = ltrim($path, '/');
    if ($cleanPath === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return $base === '' ? '/' . $cleanPath : $base . '/' . $cleanPath;
}

/**
 * Retrieve the current session's CSRF token.
 *
 * @return string Hexadecimal CSRF token
 */
function get_csrf_token(): string {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token against the active session token.
 *
 * @param string|null $token Submitted CSRF token
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token(?string $token): bool {
    init_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Render a standardized JSON response envelope and terminate script execution.
 *
 * @param bool $success Operation status
 * @param string $message Human-readable status message
 * @param mixed|null $data Payload data
 * @param array|string|null $errors Error details
 * @param int $statusCode HTTP status code (default 200)
 */
function json_response(bool $success, string $message, $data = null, $errors = null, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    // Format errors as array if single string passed
    if (is_string($errors)) {
        $errors = [$errors];
    }

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
        'errors'  => $errors
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Set a one-time flash message for display on the next page view.
 *
 * @param string $type Message type ('success', 'danger', 'warning', 'info')
 * @param string $message Flash message text
 */
function flash_set(string $type, string $message): void {
    init_session();
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear the active flash message.
 *
 * @return array|null Flash message array or null
 */
function flash_get(): ?array {
    init_session();
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/**
 * Check if the current user session is authenticated.
 *
 * @return bool
 */
function is_logged_in(): bool {
    init_session();
    return !empty($_SESSION['user_id']);
}

/**
 * Check if the current user has the administrator role.
 *
 * @return bool
 */
function is_admin(): bool {
    init_session();
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Retrieve current logged-in user session metadata.
 *
 * @return array|null User details or null if unauthenticated
 */
function get_current_user_session(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'role'      => $_SESSION['role'] ?? 'student',
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'email'     => $_SESSION['email'] ?? ''
    ];
}

/**
 * Format an ISO date or timestamp into a friendly human-readable format.
 *
 * @param string|null $date ISO date string (e.g. '2026-10-18')
 * @return string Formatted date (e.g. 'Oct 18, 2026')
 */
function format_date(?string $date): string {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date('M j, Y', $timestamp) : e($date);
}

/**
 * Format an ISO datetime into a friendly human-readable timestamp.
 *
 * @param string|null $datetime ISO datetime string
 * @return string Formatted datetime
 */
function format_datetime(?string $datetime): string {
    if (empty($datetime)) return 'N/A';
    $timestamp = strtotime($datetime);
    return $timestamp ? date('M j, Y g:i A', $timestamp) : e($datetime);
}
