/**
 * Smart College Lost and Found System (SCLFS)
 * Authentication Client Module (assets/js/auth.js)
 *
 * Handles student, staff, and admin registration and login validation,
 * password complexity enforcement, live password-match feedback,
 * college email domain checks, and asynchronous API submission.
 */

(function (window, document) {
    'use strict';

    const SCLFS = window.SCLFS || {};
    if (SCLFS.auth) return;

    /**
     * Display a message in the page's authentication alert box.
     * @param {string} message
     * @param {'danger'|'warning'|'success'} [type='danger']
     */
    function showAuthAlert(message, type = 'danger') {
        const alertBox = document.getElementById('authAlert');
        const alertMsg = document.getElementById('authAlertMsg');
        if (alertBox && alertMsg) {
            alertBox.className = `alert alert-${type}`;
            alertMsg.textContent = message;
            alertBox.style.display = 'block';
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else if (SCLFS.showToast) {
            SCLFS.showToast(message, type);
        }
    }

    /**
     * Hide the page's authentication alert box.
     */
    function hideAuthAlert() {
        const alertBox = document.getElementById('authAlert');
        if (alertBox) {
            alertBox.style.display = 'none';
        }
    }

    // =========================================================================
    // 1. LOGIN FORM VALIDATION & ASYNC SUBMISSION
    // =========================================================================
    function initLoginForm() {
        const form = document.getElementById('loginForm');
        if (!form) return;

        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const submitBtn = document.getElementById('loginSubmitBtn') || form.querySelector('button[type="submit"]');

        const validateEmail = () => {
            return SCLFS.validateField(emailInput, {
                required: true,
                requiredMessage: 'College email is required.',
                email: true,
                emailMessage: 'Please enter a valid email address (e.g. alex@college.edu).'
            });
        };

        const validatePassword = () => {
            return SCLFS.validateField(passwordInput, {
                required: true,
                requiredMessage: 'Password is required.'
            });
        };

        // Live validation listeners
        if (emailInput) {
            emailInput.addEventListener('blur', validateEmail);
            emailInput.addEventListener('input', () => {
                if (emailInput.classList.contains('is-invalid')) validateEmail();
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener('blur', validatePassword);
            passwordInput.addEventListener('input', () => {
                if (passwordInput.classList.contains('is-invalid')) validatePassword();
            });
        }

        // Form Submit Handler
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAuthAlert();

            const isEmailValid = validateEmail();
            const isPasswordValid = validatePassword();

            if (!isEmailValid || !isPasswordValid) {
                if (!isEmailValid && emailInput) emailInput.focus();
                else if (!isPasswordValid && passwordInput) passwordInput.focus();
                showAuthAlert('Please correct the highlighted errors before submitting.');
                return;
            }

            // Set button loading state
            SCLFS.setButtonLoading(submitBtn, true, 'Signing in...');

            const formData = new FormData(form);
            const actionUrl = form.getAttribute('action') || 'api/auth/login.php';

            try {
                const response = await SCLFS.fetchAPI(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    SCLFS.showToast('Login successful! Redirecting...', 'success');
                    const redirectUrl = response.data && response.data.redirect
                        ? response.data.redirect
                        : (window.location.search.includes('redirect=')
                            ? decodeURIComponent(new URLSearchParams(window.location.search).get('redirect'))
                            : 'dashboard.php');

                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 500);
                } else if (response.notImplemented) {
                    const notice = 'Backend endpoint (api/auth/login.php) has not been implemented yet by Member 3. Please create the login API endpoint to enable authentication.';
                    showAuthAlert(notice, 'warning');
                    SCLFS.showToast(notice, 'warning', 6000);
                    SCLFS.setButtonLoading(submitBtn, false);
                } else {
                    const errorMsg = response.message || 'Invalid email or password. Please try again.';
                    showAuthAlert(errorMsg, 'danger');
                    SCLFS.showToast(errorMsg, 'danger');
                    SCLFS.setButtonLoading(submitBtn, false);
                }
            } catch (err) {
                console.error('[Login Error]', err);
                showAuthAlert('An unexpected error occurred. Please try again.', 'danger');
                SCLFS.setButtonLoading(submitBtn, false);
            }
        });
    }

    // =========================================================================
    // 2. REGISTRATION FORM VALIDATION & ASYNC SUBMISSION
    // =========================================================================
    function initRegisterForm() {
        const form = document.getElementById('registerForm');
        if (!form) return;

        const fullNameInput = document.getElementById('fullName');
        const studentIdInput = document.getElementById('studentId');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const departmentSelect = document.getElementById('department');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const submitBtn = document.getElementById('registerSubmitBtn') || form.querySelector('button[type="submit"]');

        const validateFullName = () => {
            return SCLFS.validateField(fullNameInput, {
                required: true,
                requiredMessage: 'Full name is required.',
                minLength: 3,
                minLengthMessage: 'Full name must be at least 3 characters.',
                maxLength: 100
            });
        };

        const validateStudentId = () => {
            return SCLFS.validateField(studentIdInput, {
                required: true,
                requiredMessage: 'College Roll Number / Staff ID is required.',
                studentId: true,
                studentIdMessage: 'Roll Number must be 4 to 20 alphanumeric characters (e.g. STU2026001).'
            });
        };

        const validateEmail = () => {
            return SCLFS.validateField(emailInput, {
                required: true,
                requiredMessage: 'College email address is required.',
                email: true,
                emailMessage: 'Please enter a valid email address.'
            });
        };

        const validatePhone = () => {
            return SCLFS.validateField(phoneInput, {
                required: true,
                requiredMessage: 'Contact phone number is required.',
                phone: true,
                phoneMessage: 'Please enter a valid 10-15 digit phone number (e.g. +1234567890).'
            });
        };

        const validatePassword = () => {
            const isValid = SCLFS.validateField(passwordInput, {
                required: true,
                requiredMessage: 'Password is required.',
                passwordStrength: true,
                passwordStrengthMessage: 'Password must have at least 8 characters with 1 uppercase, 1 lowercase, 1 number, and 1 symbol.'
            });

            // Re-validate confirm password if it already has input
            if (confirmPasswordInput && confirmPasswordInput.value) {
                validateConfirmPassword();
            }
            return isValid;
        };

        const validateConfirmPassword = () => {
            return SCLFS.validateField(confirmPasswordInput, {
                required: true,
                requiredMessage: 'Please confirm your password.',
                matchesField: 'password',
                matchesMessage: 'Passwords do not match.'
            });
        };

        // Attach live listeners
        if (fullNameInput) {
            fullNameInput.addEventListener('blur', validateFullName);
            fullNameInput.addEventListener('input', () => {
                if (fullNameInput.classList.contains('is-invalid')) validateFullName();
            });
        }

        if (studentIdInput) {
            studentIdInput.addEventListener('blur', validateStudentId);
            studentIdInput.addEventListener('input', () => {
                // Auto-uppercase student ID for convenience
                studentIdInput.value = studentIdInput.value.toUpperCase();
                if (studentIdInput.classList.contains('is-invalid')) validateStudentId();
            });
        }

        if (emailInput) {
            emailInput.addEventListener('blur', validateEmail);
            emailInput.addEventListener('input', () => {
                if (emailInput.classList.contains('is-invalid')) validateEmail();
            });
        }

        if (phoneInput) {
            phoneInput.addEventListener('blur', validatePhone);
            phoneInput.addEventListener('input', () => {
                if (phoneInput.classList.contains('is-invalid')) validatePhone();
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener('blur', validatePassword);
            passwordInput.addEventListener('input', () => {
                if (passwordInput.classList.contains('is-invalid')) validatePassword();
            });
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('blur', validateConfirmPassword);
            confirmPasswordInput.addEventListener('input', () => {
                if (confirmPasswordInput.classList.contains('is-invalid')) validateConfirmPassword();
            });
        }

        // Form Submit Handler
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAuthAlert();

            const vName = validateFullName();
            const vId = validateStudentId();
            const vEmail = validateEmail();
            const vPhone = validatePhone();
            const vPass = validatePassword();
            const vConfirm = validateConfirmPassword();

            if (!vName || !vId || !vEmail || !vPhone || !vPass || !vConfirm) {
                // Focus first invalid element
                const firstInvalid = form.querySelector('.form-control.is-invalid, .form-select.is-invalid');
                if (firstInvalid) firstInvalid.focus();
                showAuthAlert('Please correct the highlighted fields before submitting.');
                return;
            }

            SCLFS.setButtonLoading(submitBtn, true, 'Creating Account...');

            const formData = new FormData(form);
            const actionUrl = form.getAttribute('action') || 'api/auth/register.php';

            try {
                const response = await SCLFS.fetchAPI(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    SCLFS.showToast('Account created successfully! Redirecting to login...', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php?registered=1';
                    }, 800);
                } else if (response.notImplemented) {
                    const notice = 'Backend endpoint (api/auth/register.php) has not been implemented yet by Member 3. Please create the registration API endpoint to enable account creation.';
                    showAuthAlert(notice, 'warning');
                    SCLFS.showToast(notice, 'warning', 6000);
                    SCLFS.setButtonLoading(submitBtn, false);
                } else {
                    let errorMsg = response.message || 'Registration failed.';
                    if (Array.isArray(response.errors) && response.errors.length > 0) {
                        errorMsg = response.errors.join(' ');
                    }
                    showAuthAlert(errorMsg, 'danger');
                    SCLFS.showToast(errorMsg, 'danger');
                    SCLFS.setButtonLoading(submitBtn, false);
                }
            } catch (err) {
                console.error('[Registration Error]', err);
                showAuthAlert('An unexpected error occurred. Please try again.', 'danger');
                SCLFS.setButtonLoading(submitBtn, false);
            }
        });
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initLoginForm();
            initRegisterForm();
        });
    } else {
        initLoginForm();
        initRegisterForm();
    }

    // Export module
    SCLFS.auth = {
        initLoginForm,
        initRegisterForm
    };

})(window, document);
