/**
 * Smart College Lost and Found System (SCLFS)
 * Claims Module (assets/js/claims.js)
 *
 * Implements:
 * 1. Claim modal lifecycle and focus management.
 * 2. Proof description character validation (minimum 20 characters).
 * 3. Supporting proof receipt/image validation (max 2MB, MIME check).
 * 4. Asynchronous submission to api/claims/submit.php with loading feedback.
 * 5. Dynamic status updates and error handling.
 */

(function (window, document) {
    'use strict';

    const SCLFS = window.SCLFS || {};

    function initClaimForm() {
        const form = document.getElementById('claimForm');
        if (!form) return;

        const proofTextarea = document.getElementById('proofDescription');
        const proofImageInput = document.getElementById('proofImage');
        const submitBtn = document.getElementById('claimSubmitBtn') || form.querySelector('button[type="submit"]');

        // Add live character counter if not already present
        if (proofTextarea && !document.getElementById('proofCharCounter')) {
            const counter = document.createElement('div');
            counter.id = 'proofCharCounter';
            counter.style.fontSize = '0.75rem';
            counter.style.color = 'var(--color-text-muted)';
            counter.style.marginTop = '4px';
            counter.style.textAlign = 'right';

            const updateCounter = () => {
                const len = proofTextarea.value.trim().length;
                if (len < 20) {
                    counter.innerHTML = `<span style="color:var(--color-danger);">${len} / 20 characters minimum</span>`;
                } else {
                    counter.innerHTML = `<span style="color:var(--color-success);">&check; ${len} characters</span>`;
                }
            };

            proofTextarea.parentNode.appendChild(counter);
            updateCounter();
            proofTextarea.addEventListener('input', updateCounter);
        }

        const validateProofDescription = () => {
            return SCLFS.validateField(proofTextarea, {
                required: true,
                requiredMessage: 'Detailed proof description is required.',
                minLength: 20,
                minLengthMessage: 'Please provide at least 20 characters describing distinguishing marks, inner contents, or serial numbers.',
                maxLength: 2000
            });
        };

        const validateProofImage = () => {
            if (!proofImageInput || !proofImageInput.files || proofImageInput.files.length === 0) {
                return true;
            }
            const file = proofImageInput.files[0];
            const MAX_BYTES = 2 * 1024 * 1024;
            const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

            if (!ALLOWED_TYPES.includes(file.type)) {
                SCLFS.setFieldError(proofImageInput, 'Only JPEG, PNG, or WebP proof images are accepted.');
                return false;
            }

            if (file.size > MAX_BYTES) {
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                SCLFS.setFieldError(proofImageInput, `Uploaded proof is ${sizeMb}MB. Maximum limit is 2MB.`);
                return false;
            }

            SCLFS.clearFieldError(proofImageInput);
            return true;
        };

        if (proofTextarea) {
            proofTextarea.addEventListener('blur', validateProofDescription);
            proofTextarea.addEventListener('input', () => {
                if (proofTextarea.classList.contains('is-invalid')) validateProofDescription();
            });
        }

        if (proofImageInput) {
            proofImageInput.addEventListener('change', validateProofImage);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const isProofValid = validateProofDescription();
            const isImageValid = validateProofImage();

            if (!isProofValid || !isImageValid) {
                if (!isProofValid && proofTextarea) proofTextarea.focus();
                else if (!isImageValid && proofImageInput) proofImageInput.focus();
                SCLFS.showToast('Please provide sufficient proof details (min 20 characters).', 'warning');
                return;
            }

            SCLFS.setButtonLoading(submitBtn, true, 'Submitting Claim...');

            const formData = new FormData(form);
            const actionUrl = form.getAttribute('action') || 'api/claims/submit.php';

            try {
                const response = await SCLFS.fetchAPI(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    SCLFS.showToast('Claim submitted! An administrator will evaluate your proof.', 'success', 5000);
                    SCLFS.closeModal('claimModal');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    let msg = response.message || 'Failed to submit ownership claim.';
                    if (Array.isArray(response.errors) && response.errors.length > 0) {
                        msg = response.errors.join(' ');
                    }
                    SCLFS.showToast(msg, 'danger');
                    SCLFS.setButtonLoading(submitBtn, false);
                }
            } catch (err) {
                console.error('[Claim Submit Error]', err);
                SCLFS.showToast('An unexpected error occurred. Please try again.', 'danger');
                SCLFS.setButtonLoading(submitBtn, false);
            }
        });
    }

    function initClaimCancellation() {
        document.querySelectorAll('.claim-cancel-form').forEach(form => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const confirmed = await SCLFS.confirm(
                    'Cancel Ownership Claim',
                    'Cancel this claim? The item may become available for other claims.',
                    { confirmText: 'Cancel Claim', cancelText: 'Keep Claim', confirmVariant: 'danger' }
                );
                if (!confirmed) return;

                const button = form.querySelector('button[type="submit"]');
                SCLFS.setButtonLoading(button, true, 'Cancelling...');
                try {
                    const response = await SCLFS.fetchAPI(form.action, {
                        method: 'POST',
                        body: new FormData(form)
                    });
                    if (!response.success) {
                        throw new Error(response.message || 'Unable to cancel claim.');
                    }
                    SCLFS.showToast('Claim cancelled.', 'success');
                    window.location.reload();
                } catch (error) {
                    SCLFS.showToast(error.message || 'Unable to cancel claim.', 'danger');
                    SCLFS.setButtonLoading(button, false);
                }
            });
        });
    }

    // DOM Ready initialization
    document.addEventListener('DOMContentLoaded', () => {
        initClaimForm();
        initClaimCancellation();
    });

    SCLFS.claims = {
        initClaimForm,
        initClaimCancellation
    };

})(window, document);
