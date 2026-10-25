<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * POST /api/auth/register.php — User Registration Endpoint
 *
 * Validates all inputs, enforces email-domain policy from config,
 * hashes password with bcrypt, inserts user, and returns JSON.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// ── Ensure JSON response for all exit paths ─────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', null, null, 405);
}

// ── Redirect logged-in users ─────────────────────────────────────────────────
init_session();
if (is_logged_in()) {
    json_response(false, 'You are already logged in.', null, null, 400);
}

// ── CSRF guard ───────────────────────────────────────────────────────────────
csrf_verify();

// ── Collect and trim inputs ──────────────────────────────────────────────────
$studentId   = trim($_POST['student_id']       ?? '');
$fullName    = trim($_POST['full_name']         ?? '');
$email       = trim(strtolower($_POST['email'] ?? ''));
$phone       = trim($_POST['phone']             ?? '');
$department  = trim($_POST['department']        ?? '');
$password    = $_POST['password']               ?? '';
$confirmPw   = $_POST['confirm_password']       ?? '';

// ── Validation ───────────────────────────────────────────────────────────────
$errors = [];

// Full Name
if (empty($fullName) || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
    $errors[] = 'Full name must be between 2 and 100 characters.';
}

// Student / Staff ID
if (empty($studentId)) {
    $errors[] = 'Roll Number / Staff ID is required.';
} elseif (!preg_match('/^[A-Za-z0-9\-]{4,30}$/', $studentId)) {
    $errors[] = 'Roll Number must be 4–30 alphanumeric characters (hyphens allowed).';
}

// Email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
} elseif (ENFORCE_COLLEGE_EMAIL_DOMAIN && !preg_match(COLLEGE_EMAIL_REGEX, $email)) {
    $errors[] = 'Only college email addresses (@college.edu) are accepted for registration.';
}

// Phone
if (empty($phone) || !preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
    $errors[] = 'A valid phone number (10–15 digits, optional leading +) is required.';
}

// Password strength
if (empty($password)) {
    $errors[] = 'Password is required.';
} elseif (mb_strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
} elseif (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Password must contain at least one uppercase letter.';
} elseif (!preg_match('/[a-z]/', $password)) {
    $errors[] = 'Password must contain at least one lowercase letter.';
} elseif (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must contain at least one number.';
} elseif (!preg_match('/[\W_]/', $password)) {
    $errors[] = 'Password must contain at least one special character (e.g. @, #, !, $).';
}

// Confirm password
if ($password !== $confirmPw) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    json_response(false, 'Registration validation failed.', null, $errors, 422);
}

// ── Database uniqueness checks & insert (atomic) ─────────────────────────────
try {
    $pdo = get_db_connection();

    // Email uniqueness
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        json_response(false, 'An account with this email address already exists.', null, null, 409);
    }

    // Student ID uniqueness
    $stmt = $pdo->prepare('SELECT id FROM users WHERE student_id = :sid LIMIT 1');
    $stmt->execute([':sid' => $studentId]);
    if ($stmt->fetch()) {
        json_response(false, 'An account with this Roll Number / Staff ID already exists.', null, null, 409);
    }

    // Hash password with bcrypt
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Insert user
    $stmt = $pdo->prepare(
        'INSERT INTO users (role, student_id, full_name, email, password_hash, phone, department, status)
         VALUES (:role, :sid, :name, :email, :hash, :phone, :dept, :status)'
    );
    $stmt->execute([
        ':role'   => 'student',
        ':sid'    => $studentId,
        ':name'   => $fullName,
        ':email'  => $email,
        ':hash'   => $hash,
        ':phone'  => $phone,
        ':dept'   => $department ?: null,
        ':status' => 'active',
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    // Welcome notification
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, title, message, type)
         VALUES (:uid, :title, :msg, :type)'
    );
    $stmt->execute([
        ':uid'   => $newUserId,
        ':title' => 'Welcome to ' . APP_SHORT_NAME . '!',
        ':msg'   => "Hi {$fullName}, your account has been created. You can now report lost or found items and receive smart match alerts.",
        ':type'  => 'system',
    ]);

    // Audit log
    log_audit_event('USER_REGISTERED', 'user', $newUserId, [
        'email'      => $email,
        'student_id' => $studentId,
        'department' => $department,
    ], $newUserId);

    json_response(
        true,
        'Account created successfully. You can now log in.',
        null,
        null,
        201
    );

} catch (PDOException $e) {
    error_log('Register endpoint DB error: ' . $e->getMessage());
    json_response(false, 'A database error occurred during registration. Please try again.', null, null, 500);
}
