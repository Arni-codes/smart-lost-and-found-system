<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Student & Staff Registration View
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

init_session();

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$pageTitle = 'Register Account - ' . APP_SHORT_NAME;
$activeNav = 'register';

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 580px;">
        <div class="auth-header">
            <div style="display: flex; justify-content: center; margin-bottom: var(--space-3);">
                <img src="<?= url('assets/images/logo.svg') ?>" alt="SCLFS Logo" width="48" height="48">
            </div>
            <h1>Create Campus Account</h1>
            <p>Register as a student or faculty member to report items and receive instant match alerts.</p>
        </div>

        <div class="auth-body">
            <!-- Client / Server Feedback Alert Box -->
            <div id="authAlert" class="alert alert-danger" style="display: none;" role="alert">
                <span id="authAlertMsg"></span>
            </div>

            <form id="registerForm" method="POST" action="<?= url('api/auth/register.php') ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="fullName" class="form-label">Full Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" 
                               id="fullName" 
                               name="full_name" 
                               class="form-control" 
                               placeholder="e.g. Alex Johnson" 
                               required 
                               autocomplete="name">
                        <div class="invalid-feedback" id="nameError">Full name is required.</div>
                    </div>

                    <div class="form-group">
                        <label for="studentId" class="form-label">Roll Number / Staff ID <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" 
                               id="studentId" 
                               name="student_id" 
                               class="form-control" 
                               placeholder="e.g. STU2026001" 
                               required 
                               autocomplete="off">
                        <div class="invalid-feedback" id="studentIdError">Valid college ID is required.</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address <span style="color: var(--color-danger);">*</span></label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="alex@college.edu" 
                               required 
                               autocomplete="email">
                        <div class="invalid-feedback" id="emailError">Please enter a valid email address.</div>
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Mobile Phone <span style="color: var(--color-danger);">*</span></label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               class="form-control" 
                               placeholder="e.g. +1234567890" 
                               required 
                               autocomplete="tel">
                        <div class="invalid-feedback" id="phoneError">Valid 10-15 digit phone required.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="department" class="form-label">Academic Department / Division</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">-- Select Campus Department --</option>
                        <option value="Computer Science">Computer Science &amp; Engineering</option>
                        <option value="Electrical Engineering">Electrical &amp; Electronics Engineering</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Business Administration">Business Administration / Management</option>
                        <option value="Sciences & Humanities">Sciences &amp; Humanities</option>
                        <option value="Campus Administration">Campus Administration &amp; Staff</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="password" class="form-label">Password <span style="color: var(--color-danger);">*</span></label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Minimum 8 characters" 
                               required 
                               autocomplete="new-password">
                        <div class="invalid-feedback" id="passwordError">Password must meet requirements.</div>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword" class="form-label">Confirm Password <span style="color: var(--color-danger);">*</span></label>
                        <input type="password" 
                               id="confirmPassword" 
                               name="confirm_password" 
                               class="form-control" 
                               placeholder="Re-type password" 
                               required 
                               autocomplete="new-password">
                        <div class="invalid-feedback" id="confirmPasswordError">Passwords must match.</div>
                    </div>
                </div>

                <!-- Password Guidance Note -->
                <div class="form-hint" style="background: #f8fafc; padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: var(--space-5);">
                    <span style="font-weight: 600; color: var(--color-text);">Security Requirement:</span>
                    Minimum 8 characters with at least 1 uppercase letter, 1 lowercase letter, 1 number, and 1 symbol.
                </div>

                <div>
                    <button type="submit" id="registerSubmitBtn" class="btn btn-primary btn-lg" style="width: 100%;">
                        Create My Account
                    </button>
                </div>
            </form>
        </div>

        <div class="auth-footer">
            Already have an account? 
            <a href="<?= url('login.php') ?>" style="font-weight: 600;">Log in here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
