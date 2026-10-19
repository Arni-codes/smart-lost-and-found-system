/**
 * Smart College Lost and Found System (SCLFS)
 * Core Application Framework & Shared Utilities
 *
 * Provides global state management, accessible modals, toast alerts,
 * confirmation dialogs, robust asynchronous fetch client, client-side
 * validation utilities, password visibility toggles, and navigation controls.
 */

(function (window, document) {
    'use strict';

    // Global SCLFS namespace
    const SCLFS = window.SCLFS || {};

    /**
     * Escape HTML special characters to prevent XSS.
     * @param {string} str
     * @returns {string}
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    /**
     * Retrieve CSRF token from header meta tag.
     * @returns {string}
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    /**
     * Display an accessible Toast notification with support for icons and auto-dismiss.
     *
     * @param {string} message Text message to display
     * @param {'info'|'success'|'danger'|'warning'} type Notification type
     * @param {number} duration Duration in milliseconds (default: 4500, 0 = persistent)
     */
    function showToast(message, type = 'info', duration = 4500) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');

        // SVG icons matching the notification type
        let iconSvg = '';
        if (type === 'success') {
            iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
        } else if (type === 'danger') {
            iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-danger)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
        } else if (type === 'warning') {
            iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-warning)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
        } else {
            iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
        }

        toast.innerHTML = `
            <div style="display:flex; align-items:center; gap:8px; flex:1;">
                <span class="toast-icon" style="display:inline-flex; flex-shrink:0;">${iconSvg}</span>
                <div class="toast-content" style="word-break:break-word;">${escapeHtml(message)}</div>
            </div>
            <button type="button" class="toast-close" aria-label="Dismiss notification" style="background:none; border:none; color:var(--color-text-muted); cursor:pointer; font-size:1.25rem; line-height:1; padding:0 4px; margin-left:8px;">&times;</button>
        `;

        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => dismissToast(toast));

        container.appendChild(toast);

        if (duration > 0) {
            toast._timer = setTimeout(() => dismissToast(toast), duration);
        }

        return toast;
    }

    function dismissToast(toast) {
        if (!toast || !toast.parentNode) return;
        if (toast._timer) clearTimeout(toast._timer);
        toast.style.transition = 'opacity 250ms ease, transform 250ms ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 250);
    }

    /**
     * Store the trigger element that opened the currently active modal to restore focus.
     */
    let lastActiveElement = null;

    /**
     * Open a modal dialog with accessibility and focus trapping.
     * @param {string} modalId
     */
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        lastActiveElement = document.activeElement;

        let backdrop = document.querySelector('.modal-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop';
            document.body.appendChild(backdrop);
        }

        backdrop.classList.add('is-visible');
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        // Focus first interactive element in modal
        const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable.length > 0) {
            const firstInput = modal.querySelector('input:not([type="hidden"]), textarea, select') || focusable[0];
            firstInput.focus();
        }
    }

    /**
     * Close a modal dialog and return focus.
     * @param {string} modalId
     */
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
        }

        // Check if any other modal is still visible
        const remainingVisible = document.querySelector('.modal.is-visible');
        if (!remainingVisible) {
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.classList.remove('is-visible');
            }
            document.body.style.overflow = '';
        }

        if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
            lastActiveElement.focus();
            lastActiveElement = null;
        }
    }

    /**
     * Focus trap within active modal dialog for keyboard accessibility.
     */
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            const activeModal = document.querySelector('.modal.is-visible');
            if (!activeModal) return;

            const focusables = Array.from(activeModal.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            ));

            if (focusables.length === 0) return;

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    /**
     * Show an accessible custom confirmation dialog returning a Promise<boolean>.
     *
     * @param {string} title Dialog title
     * @param {string} message Confirmation message
     * @param {Object} [options]
     * @param {string} [options.confirmText='Confirm']
     * @param {string} [options.cancelText='Cancel']
     * @param {'danger'|'primary'|'success'} [options.confirmVariant='primary']
     * @returns {Promise<boolean>}
     */
    function confirmDialog(title, message, options = {}) {
        return new Promise((resolve) => {
            const dialogId = 'sclfs-confirm-dialog-' + Math.random().toString(36).substring(2, 9);
            const confirmText = options.confirmText || 'Confirm';
            const cancelText = options.cancelText || 'Cancel';
            const variant = options.confirmVariant || 'primary';

            const modalHtml = `
                <div class="modal is-visible" id="${dialogId}" role="alertdialog" aria-modal="true" aria-labelledby="${dialogId}-title" aria-describedby="${dialogId}-desc">
                    <div class="modal-dialog" style="max-width: 440px;">
                        <div class="modal-header">
                            <h3 class="modal-title" id="${dialogId}-title">${escapeHtml(title)}</h3>
                            <button type="button" class="modal-close" data-action="cancel" aria-label="Close dialog">&times;</button>
                        </div>
                        <div class="modal-body" id="${dialogId}-desc">
                            <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text); line-height: 1.5;">
                                ${escapeHtml(message)}
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-action="cancel">${escapeHtml(cancelText)}</button>
                            <button type="button" class="btn btn-${variant} btn-sm" data-action="confirm">${escapeHtml(confirmText)}</button>
                        </div>
                    </div>
                </div>
            `;

            let backdrop = document.querySelector('.modal-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop is-visible';
                document.body.appendChild(backdrop);
            } else {
                backdrop.classList.add('is-visible');
            }

            const tempContainer = document.createElement('div');
            tempContainer.innerHTML = modalHtml;
            const modalEl = tempContainer.firstElementChild;
            document.body.appendChild(modalEl);
            document.body.style.overflow = 'hidden';

            const cleanup = (result) => {
                modalEl.remove();
                const remaining = document.querySelector('.modal.is-visible');
                if (!remaining) {
                    backdrop.classList.remove('is-visible');
                    document.body.style.overflow = '';
                }
                resolve(result);
            };

            const confirmBtn = modalEl.querySelector('[data-action="confirm"]');
            const cancelBtns = modalEl.querySelectorAll('[data-action="cancel"]');

            if (confirmBtn) {
                confirmBtn.focus();
                confirmBtn.addEventListener('click', () => cleanup(true));
            }
            cancelBtns.forEach(btn => btn.addEventListener('click', () => cleanup(false)));

            // Close on escape
            const onKeyDown = (e) => {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', onKeyDown);
                    cleanup(false);
                }
            };
            document.addEventListener('keydown', onKeyDown);
        });
    }

    /**
     * Unified, robust Fetch client for communicating with PHP endpoints.
     * Automatically attaches CSRF tokens, handles network outages, non-JSON
     * fallback (e.g. 404 when endpoint is not yet created), unauthenticated sessions (401),
     * and permission rejections (403).
     *
     * @param {string} url Request URL
     * @param {RequestInit} [options={}] Fetch configuration
     * @returns {Promise<{success: boolean, message: string, data?: any, errors?: string[], notImplemented?: boolean, httpStatus?: number}>}
     */
    async function fetchAPI(url, options = {}) {
        const config = { ...options };
        config.headers = config.headers ? new Headers(config.headers) : new Headers();

        // Attach CSRF Token for mutating HTTP methods
        const method = (config.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const token = getCsrfToken();
            if (token && !config.headers.has('X-CSRF-Token')) {
                config.headers.set('X-CSRF-Token', token);
            }
        }

        try {
            const response = await fetch(url, config);

            // Handle unauthenticated session (HTTP 401)
            if (response.status === 401) {
                showToast('Your session has expired. Please log in again.', 'warning');
                return {
                    success: false,
                    httpStatus: 401,
                    message: 'Your session has expired. Please log in to continue.',
                    unauthorized: true
                };
            }

            // Handle forbidden action (HTTP 403)
            if (response.status === 403) {
                showToast('Access denied. Administrator privileges required.', 'danger');
                return {
                    success: false,
                    httpStatus: 403,
                    message: 'Access denied: You do not have permission for this action.'
                };
            }

            // Check if response is JSON
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json();
                return {
                    success: Boolean(data.success),
                    message: data.message || (data.success ? 'Action completed successfully.' : 'Operation failed.'),
                    data: data.data || null,
                    errors: data.errors || null,
                    httpStatus: response.status
                };
            }

            // Non-JSON response (e.g., endpoint file not created yet, Apache 404/500 HTML)
            if (response.status === 404) {
                console.warn(`[SCLFS API] Endpoint not found (404): ${url}. Pending backend implementation.`);
                return {
                    success: false,
                    notImplemented: true,
                    httpStatus: 404,
                    message: 'This feature is pending backend API implementation.'
                };
            }

            // Generic non-JSON fallback
            const textResponse = await response.text();
            return {
                success: response.ok,
                httpStatus: response.status,
                message: response.ok ? 'Action completed.' : `Server returned error (${response.status})`,
                rawText: textResponse
            };

        } catch (error) {
            console.error('[SCLFS API Error]', error);
            const isOffline = !navigator.onLine;
            const message = isOffline
                ? 'Network connection lost. Please check your internet connection.'
                : 'Unable to connect to the server. Please check if local XAMPP is running.';

            showToast(message, 'danger');

            return {
                success: false,
                networkError: true,
                message: message,
                error: error.message
            };
        }
    }

    /**
     * Password Visibility Toggle Controller
     * Enhances password input fields with an accessible toggle button.
     *
     * @param {HTMLInputElement|string} target Input element or CSS selector
     */
    function setupPasswordToggle(target) {
        const input = typeof target === 'string' ? document.querySelector(target) : target;
        if (!input || input.type !== 'password' || input.dataset.hasToggle) return;

        input.dataset.hasToggle = 'true';

        // Wrap input in relative container if not already wrapped
        let wrapper = input.parentElement;
        const isAlreadyRelative = wrapper && window.getComputedStyle(wrapper).position !== 'static';
        if (!isAlreadyRelative) {
            const newWrapper = document.createElement('div');
            newWrapper.style.position = 'relative';
            newWrapper.style.display = 'block';
            input.parentNode.insertBefore(newWrapper, input);
            newWrapper.appendChild(input);
            wrapper = newWrapper;
        }

        // Add padding right to input so text does not overlap eye icon
        input.style.paddingRight = '2.75rem';

        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'password-toggle-btn';
        toggleBtn.setAttribute('aria-label', 'Show password as plain text');
        toggleBtn.setAttribute('aria-pressed', 'false');
        toggleBtn.style.position = 'absolute';
        toggleBtn.style.right = '0.75rem';
        toggleBtn.style.top = '50%';
        toggleBtn.style.transform = 'translateY(-50%)';
        toggleBtn.style.background = 'none';
        toggleBtn.style.border = 'none';
        toggleBtn.style.padding = '4px';
        toggleBtn.style.cursor = 'pointer';
        toggleBtn.style.color = 'var(--color-text-muted)';
        toggleBtn.style.display = 'flex';
        toggleBtn.style.alignItems = 'center';
        toggleBtn.style.justifyContent = 'center';
        toggleBtn.style.zIndex = '2';

        const eyeIcon = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        `;
        const eyeOffIcon = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
        `;

        toggleBtn.innerHTML = eyeIcon;

        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            toggleBtn.innerHTML = isPassword ? eyeOffIcon : eyeIcon;
            toggleBtn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password as plain text');
            input.focus();
        });

        wrapper.appendChild(toggleBtn);
    }

    /**
     * Client-side Field Validation Utility
     * Evaluates a single input element and updates DOM state.
     *
     * @param {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement} field
     * @param {Object} rules
     * @returns {boolean} True if valid, false otherwise
     */
    function validateField(field, rules = {}) {
        if (!field) return true;

        const val = (field.value || '').trim();
        let errorMsg = null;

        if (rules.required && !val) {
            errorMsg = rules.requiredMessage || 'This field is required.';
        } else if (val) {
            if (rules.minLength && val.length < rules.minLength) {
                errorMsg = rules.minLengthMessage || `Must be at least ${rules.minLength} characters.`;
            } else if (rules.maxLength && val.length > rules.maxLength) {
                errorMsg = rules.maxLengthMessage || `Cannot exceed ${rules.maxLength} characters.`;
            } else if (rules.regex && !rules.regex.test(val)) {
                errorMsg = rules.regexMessage || 'Invalid format.';
            } else if (rules.email) {
                const emailRegex = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
                if (!emailRegex.test(val)) {
                    errorMsg = rules.emailMessage || 'Please enter a valid email address.';
                }
            } else if (rules.phone) {
                const phoneRegex = /^\+?[0-9]{10,15}$/;
                if (!phoneRegex.test(val)) {
                    errorMsg = rules.phoneMessage || 'Please enter a valid 10-15 digit phone number.';
                }
            } else if (rules.studentId) {
                const studentIdRegex = /^[A-Za-z0-9\-]{4,20}$/;
                if (!studentIdRegex.test(val)) {
                    errorMsg = rules.studentIdMessage || 'Roll Number must be 4-20 alphanumeric characters.';
                }
            } else if (rules.passwordStrength) {
                const hasUpper = /[A-Z]/.test(val);
                const hasLower = /[a-z]/.test(val);
                const hasNumber = /[0-9]/.test(val);
                const hasSymbol = /[^A-Za-z0-9]/.test(val);
                if (val.length < 8 || !hasUpper || !hasLower || !hasNumber || !hasSymbol) {
                    errorMsg = rules.passwordStrengthMessage || 'Password must be 8+ chars with uppercase, lowercase, number, and symbol.';
                }
            } else if (rules.matchesField) {
                const target = document.getElementById(rules.matchesField);
                if (target && val !== target.value.trim()) {
                    errorMsg = rules.matchesMessage || 'Passwords do not match.';
                }
            } else if (rules.dateNotFuture) {
                const selectedDate = new Date(val);
                const today = new Date();
                today.setHours(23, 59, 59, 999);
                if (selectedDate > today) {
                    errorMsg = rules.dateMessage || 'Date cannot be in the future.';
                }
            } else if (rules.dateMaxPastDays) {
                const selectedDate = new Date(val);
                const maxPast = new Date();
                maxPast.setDate(maxPast.getDate() - rules.dateMaxPastDays);
                maxPast.setHours(0, 0, 0, 0);
                if (selectedDate < maxPast) {
                    errorMsg = rules.datePastMessage || `Date cannot be older than ${rules.dateMaxPastDays} days.`;
                }
            }
        }

        // Custom validator function
        if (!errorMsg && typeof rules.custom === 'function') {
            const customResult = rules.custom(val, field);
            if (customResult !== true && typeof customResult === 'string') {
                errorMsg = customResult;
            }
        }

        if (errorMsg) {
            setFieldError(field, errorMsg);
            return false;
        } else {
            clearFieldError(field);
            return true;
        }
    }

    /**
     * Mark field invalid and display error feedback text.
     */
    function setFieldError(field, message) {
        if (!field) return;
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        // Locate or create .invalid-feedback container
        let feedback = field.parentElement.querySelector('.invalid-feedback');
        if (!feedback) {
            // Check adjacent sibling
            if (field.nextElementSibling && field.nextElementSibling.classList.contains('invalid-feedback')) {
                feedback = field.nextElementSibling;
            } else {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentElement.appendChild(feedback);
            }
        }
        feedback.textContent = message;
        feedback.style.display = 'block';
    }

    /**
     * Clear invalid state and error message from field.
     */
    function clearFieldError(field) {
        if (!field) return;
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');

        const feedback = field.parentElement.querySelector('.invalid-feedback') || 
            (field.nextElementSibling && field.nextElementSibling.classList.contains('invalid-feedback') ? field.nextElementSibling : null);
        if (feedback) {
            feedback.style.display = 'none';
        }
    }

    /**
     * Helper to set loading indicator on a submit button.
     *
     * @param {HTMLButtonElement} btn
     * @param {boolean} isLoading
     * @param {string} [loadingText='Processing...']
     */
    function setButtonLoading(btn, isLoading, loadingText = 'Processing...') {
        if (!btn) return;
        if (isLoading) {
            btn._originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `
                <span class="btn-spinner" style="display:inline-block; width:14px; height:14px; border:2px solid currentColor; border-right-color:transparent; border-radius:50%; animation:sclfs-spin 600ms linear infinite; vertical-align:middle; margin-right:6px;" aria-hidden="true"></span>
                <span>${escapeHtml(loadingText)}</span>
            `;
        } else {
            if (btn._originalHtml !== undefined) {
                btn.innerHTML = btn._originalHtml;
            }
            btn.disabled = false;
        }
    }

    // Inject spinner keyframe animation if not already present
    if (!document.getElementById('sclfs-core-styles')) {
        const style = document.createElement('style');
        style.id = 'sclfs-core-styles';
        style.textContent = `
            @keyframes sclfs-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }

    // Export API onto SCLFS namespace and window
    SCLFS.escapeHtml = escapeHtml;
    SCLFS.getCsrfToken = getCsrfToken;
    SCLFS.showToast = showToast;
    SCLFS.dismissToast = dismissToast;
    SCLFS.openModal = openModal;
    SCLFS.closeModal = closeModal;
    SCLFS.confirm = confirmDialog;
    SCLFS.fetchAPI = fetchAPI;
    SCLFS.setupPasswordToggle = setupPasswordToggle;
    SCLFS.validateField = validateField;
    SCLFS.setFieldError = setFieldError;
    SCLFS.clearFieldError = clearFieldError;
    SCLFS.setButtonLoading = setButtonLoading;

    window.SCLFS = SCLFS;

    // Backward-compatible global bindings
    window.showToast = showToast;
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.getCsrfToken = getCsrfToken;

    // DOM Ready initialization
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Mobile Navigation Toggle & Drawer Overlay
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const navMenu = document.getElementById('navbarMenu');

        if (mobileToggle && navMenu) {
            mobileToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isExpanded = mobileToggle.getAttribute('aria-expanded') === 'true';
                mobileToggle.setAttribute('aria-expanded', String(!isExpanded));
                navMenu.classList.toggle('is-active');
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (navMenu.classList.contains('is-active') && !navMenu.contains(e.target) && e.target !== mobileToggle) {
                    mobileToggle.setAttribute('aria-expanded', 'false');
                    navMenu.classList.remove('is-active');
                }
            });

            // Close on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && navMenu.classList.contains('is-active')) {
                    mobileToggle.setAttribute('aria-expanded', 'false');
                    navMenu.classList.remove('is-active');
                    mobileToggle.focus();
                }
            });
        }

        // 2. Auto-dismiss Flash Messages
        const autoDismissAlerts = document.querySelectorAll('.alert-dismissible');
        autoDismissAlerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 300ms ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        // 3. Declarative Modal Triggers ([data-modal-target], [data-modal-close])
        document.addEventListener('click', (e) => {
            const openTrigger = e.target.closest('[data-modal-target]');
            if (openTrigger) {
                e.preventDefault();
                const modalId = openTrigger.getAttribute('data-modal-target');
                openModal(modalId);
            }

            const closeTrigger = e.target.closest('[data-modal-close]');
            if (closeTrigger) {
                e.preventDefault();
                const modal = closeTrigger.closest('.modal');
                if (modal) {
                    closeModal(modal.id);
                }
            }

            // Click backdrop to close
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });

        // 4. Close Modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.is-visible');
                if (activeModal && !activeModal.id.startsWith('sclfs-confirm-dialog')) {
                    closeModal(activeModal.id);
                }
            }
        });

        // 5. Auto-setup password toggle for all password inputs on the page
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        passwordInputs.forEach(input => {
            setupPasswordToggle(input);
        });

        // 6. Auto-load auth.js on login or register views if not already present
        if ((document.getElementById('loginForm') || document.getElementById('registerForm')) && !SCLFS.auth) {
            const authScript = document.createElement('script');
            const isSubfolder = window.location.pathname.includes('/admin/');
            authScript.src = (isSubfolder ? '../' : '') + 'assets/js/auth.js';
            document.body.appendChild(authScript);
        }
    });

})(window, document);
