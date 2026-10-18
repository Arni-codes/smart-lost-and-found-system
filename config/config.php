<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Core Application Configuration
 */

// Prevent multiple inclusions
if (defined('SCLFS_CONFIG_LOADED')) {
    return;
}
define('SCLFS_CONFIG_LOADED', true);

// Application Metadata
define('APP_NAME', 'Smart College Lost and Found System');
define('APP_SHORT_NAME', 'College Lost & Found');
define('APP_VERSION', '1.0.0');

// Filesystem Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('SERVICES_PATH', ROOT_PATH . '/services');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ITEMS_UPLOAD_PATH', UPLOADS_PATH . '/items');
define('PROOFS_UPLOAD_PATH', UPLOADS_PATH . '/proofs');

// Base URL Auto-detection
// Adapts automatically to http://localhost/smart-lost-and-found-system/ or http://localhost:8000/
if (!defined('BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = dirname($scriptName);

    // If installed in a subdirectory (e.g. /smart-lost-and-found-system)
    if (preg_match('#(/[^/]*smart-lost-and-found-system[^/]*)#i', $scriptName, $matches)) {
        define('BASE_URL', rtrim($matches[1], '/'));
    } elseif ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
        define('BASE_URL', '');
    } else {
        define('BASE_URL', rtrim($scriptDir, '/'));
    }
}

// Smart Matching Algorithm Settings
define('MATCH_SCORE_THRESHOLD', 40.0); // Minimum heuristic similarity score (out of 100) to trigger match

// Registration & Email Validation Settings
// Set to false for local development to allow any valid email; set to true in production
define('ENFORCE_COLLEGE_EMAIL_DOMAIN', false);
define('COLLEGE_EMAIL_REGEX', '/^[A-Za-z0-9._%+-]+@college\.edu$/i');

// Media Upload Restrictions
define('MAX_UPLOAD_SIZE_BYTES', 2 * 1024 * 1024); // 2 MB limit
define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Default Database Settings (Standard XAMPP credentials)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'smart_lost_and_found');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Load local overrides if config/env.local.php exists (gitignored)
if (file_exists(CONFIG_PATH . '/env.local.php')) {
    require_once CONFIG_PATH . '/env.local.php';
}

/**
 * Initialize a secure PHP session if not already started.
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 86400; // 24 hours
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    // Initialize CSRF token if missing
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
