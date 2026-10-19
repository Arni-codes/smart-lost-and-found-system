/**
 * Smart College Lost and Found System (SCLFS)
 * Admin Portal & Security Desk Module (assets/js/admin.js)
 *
 * Implements:
 * 1. Admin claim adjudication confirmation and validation (approvals/rejections).
 * 2. Campus Security Desk physical handover checklist validation and atomic transaction execution.
 * 3. Master item moderation controls.
 * 4. User directory live table search.
 */

(function (window, document) {
    'use strict';

    const SCLFS = window.SCLFS || {};

    // =========================================================================
    // 1. CLAIM ADJUDICATION (admin/manage-claims.php)
    // =========================================================================
    function initClaimAdjudication() {
        const form = document.getElementById('claimReviewForm');
        if (!form) return;

        const adminNotes = document.getElementById('adminNotes');
        let selectedAction = null;

        // Track which action button was clicked
        const actionButtons = form.querySelectorAll('button[name="action"]');
        actionButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                selectedAction = btn.value;
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const action = selectedAction || 'approve';
            const notes = (adminNotes ? adminNotes.value : '').trim();

            if (action === 'reject' && !notes) {
                SCLFS.setFieldError(adminNotes, 'Please provide an administrative note explaining the rejection reason.');
                adminNotes.focus();
                SCLFS.showToast('Please provide an administrative explanation when rejecting a claim.', 'warning');
                return;
            } else if (adminNotes) {
                SCLFS.clearFieldError(adminNotes);
            }

            // Accessible confirmation dialog
            let dialogTitle = 'Confirm Claim Adjudication';
            let dialogMessage = 'Are you sure you want to proceed with this adjudication decision?';
            let confirmVariant = 'primary';
            let confirmBtnText = 'Proceed';

            if (action === 'approve') {
                dialogTitle = 'Approve Claim & Authorize Handover';
                dialogMessage = 'Approving this claim authorizes the student to collect the item from the Campus Security Desk with their Student ID. Proceed?';
                confirmVariant = 'success';
                confirmBtnText = 'Yes, Approve Claim';
            } else if (action === 'reject') {
                dialogTitle = 'Reject Ownership Claim';
                dialogMessage = 'Are you sure you want to reject this claim? The claimant will be notified with your administrative explanation.';
                confirmVariant = 'danger';
                confirmBtnText = 'Yes, Reject Claim';
            } else if (action === 'under_review') {
                dialogTitle = 'Mark Claim Under Review';
                dialogMessage = 'Mark this claim as actively under administrative review?';
                confirmVariant = 'primary';
                confirmBtnText = 'Mark Under Review';
            }

            const confirmed = await SCLFS.confirm(dialogTitle, dialogMessage, {
                confirmText: confirmBtnText,
                cancelText: 'Cancel',
                confirmVariant: confirmVariant
            });

            if (!confirmed) return;

            const submitBtn = form.querySelector(`button[name="action"][value="${action}"]`) || form.querySelector('button[type="submit"]');
            SCLFS.setButtonLoading(submitBtn, true, 'Saving Decision...');

            const formData = new FormData(form);
            formData.set('action', action);
            const actionUrl = form.getAttribute('action') || 'api/claims/review.php';

            try {
                const response = await SCLFS.fetchAPI(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    SCLFS.showToast(`Claim successfully marked as ${action.replace('_', ' ')}.`, 'success');
                    setTimeout(() => {
                        window.location.href = 'admin/manage-claims.php';
                    }, 800);
                } else if (response.notImplemented) {
                    const notice = 'Backend endpoint (api/claims/review.php) has not been implemented yet by Member 5.';
                    SCLFS.showToast(notice, 'warning', 6000);
                    SCLFS.setButtonLoading(submitBtn, false);
                } else {
                    SCLFS.showToast(response.message || 'Adjudication failed.', 'danger');
                    SCLFS.setButtonLoading(submitBtn, false);
                }
            } catch (err) {
                console.error('[Adjudication Error]', err);
                SCLFS.showToast('An unexpected error occurred.', 'danger');
                SCLFS.setButtonLoading(submitBtn, false);
            }
        });
    }

    // =========================================================================
    // 2. SECURITY DESK HANDOVER CHECKLIST (admin/handover.php)
    // =========================================================================
    function initHandoverTerminal() {
        const handoverForms = document.querySelectorAll('.handover-checklist-form');
        if (handoverForms.length === 0) return;

        handoverForms.forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const studentIdCheck = form.querySelector('input[name="student_id_confirmed"]');
                const itemCheck = form.querySelector('input[type="checkbox"]:not([name="student_id_confirmed"])');
                const submitBtn = form.querySelector('button[type="submit"]');

                if (studentIdCheck && !studentIdCheck.checked) {
                    SCLFS.showToast('You must physically inspect the student ID card before handover.', 'warning');
                    studentIdCheck.focus();
                    return;
                }

                if (itemCheck && !itemCheck.checked) {
                    SCLFS.showToast('You must confirm item condition and physical transfer to claimant.', 'warning');
                    itemCheck.focus();
                    return;
                }

                const confirmed = await SCLFS.confirm(
                    'Execute Atomic Handover',
                    'Warning: This action executes an atomic ACID transaction that updates custody to "returned_to_owner", marks the claim complete, and automatically closes any linked lost reports. Are you sure physical handover is complete?',
                    {
                        confirmText: 'Yes, Complete Handover',
                        cancelText: 'Cancel',
                        confirmVariant: 'success'
                    }
                );

                if (!confirmed) return;

                SCLFS.setButtonLoading(submitBtn, true, 'Executing Handover...');

                const formData = new FormData(form);
                const actionUrl = form.getAttribute('action') || 'api/admin/handover.php';

                try {
                    const response = await SCLFS.fetchAPI(actionUrl, {
                        method: 'POST',
                        body: formData
                    });

                    if (response.success) {
                        SCLFS.showToast('Physical handover recorded successfully. Reports closed.', 'success', 5000);
                        setTimeout(() => window.location.reload(), 1000);
                    } else if (response.notImplemented) {
                        const notice = 'Backend endpoint (api/admin/handover.php) has not been implemented yet by Member 5.';
                        SCLFS.showToast(notice, 'warning', 6000);
                        SCLFS.setButtonLoading(submitBtn, false);
                    } else {
                        SCLFS.showToast(response.message || 'Handover transaction failed.', 'danger');
                        SCLFS.setButtonLoading(submitBtn, false);
                    }
                } catch (err) {
                    console.error('[Handover Error]', err);
                    SCLFS.showToast('An unexpected error occurred during handover transaction.', 'danger');
                    SCLFS.setButtonLoading(submitBtn, false);
                }
            });
        });
    }

    // =========================================================================
    // 3. USER DIRECTORY LIVE TABLE FILTER (admin/users.php)
    // =========================================================================
    function initUserTableFilter() {
        const searchInput = document.querySelector('form[action*="users.php"] input[name="search"]');
        const userTable = document.querySelector('.table');

        if (!searchInput || !userTable) return;

        let timer = null;
        searchInput.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const query = searchInput.value.toLowerCase().trim();
                const rows = userTable.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            }, 200);
        });
    }

    // DOM Ready initialization
    document.addEventListener('DOMContentLoaded', () => {
        initClaimAdjudication();
        initHandoverTerminal();
        initUserTableFilter();
    });

    SCLFS.admin = {
        initClaimAdjudication,
        initHandoverTerminal,
        initUserTableFilter
    };

})(window, document);
