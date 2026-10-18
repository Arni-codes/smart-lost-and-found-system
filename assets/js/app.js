/**
 * Smart College Lost and Found System (SCLFS)
 * Core Client Application Utilities
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Navigation Toggle
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const navMenu = document.getElementById('navbarMenu');

    if (mobileToggle && navMenu) {
        mobileToggle.addEventListener('click', () => {
            const isExpanded = mobileToggle.getAttribute('aria-expanded') === 'true';
            mobileToggle.setAttribute('aria-expanded', !isExpanded);
            navMenu.classList.toggle('is-active');
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
            window.openModal(modalId);
        }

        const closeTrigger = e.target.closest('[data-modal-close]');
        if (closeTrigger) {
            e.preventDefault();
            const modal = closeTrigger.closest('.modal');
            if (modal) {
                window.closeModal(modal.id);
            }
        }
    });

    // 4. Close Modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal.is-visible');
            if (activeModal) {
                window.closeModal(activeModal.id);
            }
        }
    });
});

/**
 * Open a modal dialog with backdrop blur and focus management.
 *
 * @param {string} modalId Element ID of the modal container
 */
window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    let backdrop = document.querySelector('.modal-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        document.body.appendChild(backdrop);
    }

    backdrop.classList.add('is-visible');
    modal.classList.add('is-visible');
    document.body.style.overflow = 'hidden';

    // Focus first interactive element in modal
    const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable) {
        focusable.focus();
    }
};

/**
 * Close an active modal dialog.
 *
 * @param {string} modalId Element ID of the modal container
 */
window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('is-visible');
    }

    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
        backdrop.classList.remove('is-visible');
    }

    document.body.style.overflow = '';
};

/**
 * Global CSRF Token Retrieval.
 * Reads the token from the <meta name="csrf-token" content="..."> tag in header.
 *
 * @returns {string} CSRF Token string
 */
window.getCsrfToken = function() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
};

/**
 * Display a user-friendly Toast Notification.
 *
 * @param {string} message Text message to display
 * @param {string} type 'success' | 'danger' | 'warning' | 'info' (default: 'info')
 * @param {number} duration Milliseconds before auto-dismiss (default: 4000)
 */
window.showToast = function(message, type = 'info', duration = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">${escapeHtml(message)}</div>
        <button type="button" class="toast-close" style="background:none;border:none;cursor:pointer;color:inherit;font-weight:bold;margin-left:8px;" aria-label="Close">&times;</button>
    `;

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => toast.remove());

    container.appendChild(toast);

    if (duration > 0) {
        setTimeout(() => {
            toast.style.transition = 'opacity 300ms ease, transform 300ms ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

/**
 * Escape HTML in strings to prevent XSS during dynamic DOM rendering.
 *
 * @param {string} str Raw string
 * @returns {string} Escaped HTML string
 */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
