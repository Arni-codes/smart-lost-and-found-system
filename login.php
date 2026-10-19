<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Student / Staff / Admin Login View
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

init_session();

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$pageTitle = 'Log In - ' . APP_SHORT_NAME;
$activeNav = 'login';
$extraScripts = ['assets/js/auth.js'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div style="display: flex; justify-content: center; margin-bottom: var(--space-3);">
                <img src="<?= url('assets/images/logo.svg') ?>" alt="SCLFS Logo" width="48" height="48">
            </div>
            <h1>Welcome Back</h1>
            <p>Access your college lost &amp; found reports, match notifications, and claims.</p>
        </div>

        <div class="auth-body">
            <!-- Client / Server Feedback Alert Box -->
            <div id="authAlert" class="alert alert-danger" style="display: none;" role="alert">
                <span id="authAlertMsg"></span>
            </div>

            <form id="loginForm" method="POST" action="<?= url('api/auth/login.php') ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">

                <div class="form-group">
                    <label for="email" class="form-label">College Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           placeholder="e.g. alex@college.edu" 
                           required 
                           autocomplete="email"
                           autofocus>
                    <div class="invalid-feedback" id="emailError">Please enter a valid email address.</div>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                        <label for="password" class="form-label" style="margin-bottom: 0;">Password</label>
                        <span style="font-size: 0.75rem; color: var(--color-text-muted);">Minimum 8 chars</span>
                    </div>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Enter your account password" 
                           required 
                           autocomplete="current-password">
                    <div class="invalid-feedback" id="passwordError">Password is required.</div>
                </div>

                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between; margin-top: var(--space-4);">
                    <label style="display: inline-flex; align-items: center; gap: var(--space-2); font-size: 0.875rem; cursor: pointer;">
                        <input type="checkbox" name="remember_me" id="rememberMe">
                        <span>Remember me on this browser</span>
                    </label>
                </div>

                <div style="margin-top: var(--space-6);">
                    <button type="submit" id="loginSubmitBtn" class="btn btn-primary btn-lg" style="width: 100%;">
                        Log In to Account
                    </button>
                </div>
            </form>

            <!-- Demo Credentials for Easy Local Evaluation -->
            <div class="demo-credentials-box">
                <div class="demo-credentials-title">
                    <span class="badge badge-info" style="font-size: 0.6875rem;">Local Demo</span>
                    Quick Fill Test Credentials:
                </div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: var(--space-2);">
                    Pre-seeded test accounts from <code>database/seed.sql</code>:
                </div>
                <div class="demo-btn-group">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="fillDemo('alex@college.edu', 'Student@123')">
                        Student (Alex)
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="fillDemo('admin@college.edu', 'Admin@123')">
                        Admin (Security Desk)
                    </button>
                </div>
            </div>
        </div>

        <div class="auth-footer">
            Don't have an account yet? 
            <a href="<?= url('register.php') ?>" style="font-weight: 600;">Register here</a>
        </div>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
