/**
 * Smart College Lost and Found System (SCLFS)
 * Item Management & Catalog Module (assets/js/items.js)
 *
 * Implements:
 * 1. Catalog live search with 300ms debounce and dynamic filter controls.
 * 2. Asynchronous catalog querying, skeleton loading states, and dynamic card rendering.
 * 3. Drag-and-drop image dropzone with client-side 2MB file size and MIME checks.
 * 4. Image preview before item report submission with remove action.
 * 5. Client validation for Report Lost and Report Found forms.
 * 6. Interactive custody radio cards on Report Found.
 * 7. Custody transfer controls on My Reports.
 */

(function (window, document) {
    'use strict';

    const SCLFS = window.SCLFS || {};

    // =========================================================================
    // 1. IMAGE DROPZONE & PREVIEW UTILITY (Shared across Report Lost & Found)
    // =========================================================================
    function initImageDropzone() {
        const dropzone = document.getElementById('fileDropzone');
        const input = document.getElementById('itemImage');
        const container = document.getElementById('imagePreviewContainer');
        const preview = document.getElementById('imagePreview');
        const removeBtn = document.getElementById('imagePreviewRemove');

        if (!input) return;

        const MAX_BYTES = 2 * 1024 * 1024; // 2MB
        const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

        const handleFile = (file) => {
            if (!file) return;

            // Validate MIME type
            if (!ALLOWED_TYPES.includes(file.type)) {
                SCLFS.showToast('Invalid file format. Please upload a JPEG, PNG, or WebP image.', 'danger');
                input.value = '';
                if (container) container.style.display = 'none';
                return;
            }

            // Validate File Size (2MB)
            if (file.size > MAX_BYTES) {
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                SCLFS.showToast(`Selected image is ${sizeMb}MB. Maximum allowed file size is 2MB.`, 'danger');
                input.value = '';
                if (container) container.style.display = 'none';
                return;
            }

            // Display FileReader preview
            const reader = new FileReader();
            reader.onload = (e) => {
                if (preview) {
                    preview.src = e.target.result;
                    preview.alt = file.name;
                }
                if (container) {
                    container.style.display = 'block';
                }
                SCLFS.showToast(`Image "${file.name}" ready for upload.`, 'info', 2500);
            };
            reader.readAsDataURL(file);
        };

        input.addEventListener('change', () => {
            if (input.files && input.files[0]) {
                handleFile(input.files[0]);
            }
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                input.value = '';
                if (preview) preview.src = '';
                if (container) container.style.display = 'none';
                SCLFS.showToast('Selected image removed.', 'info', 2000);
            });
        }

        // Drag and Drop interactivity
        if (dropzone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    input.files = dt.files;
                    handleFile(dt.files[0]);
                }
            }, false);
        }
    }

    // =========================================================================
    // 2. REPORT LOST FORM VALIDATION & SUBMISSION
    // =========================================================================
    function initReportLostForm() {
        const form = document.getElementById('reportLostForm');
        if (!form) return;

        const titleInput = document.getElementById('itemTitle');
        const categorySelect = document.getElementById('itemCategory');
        const locationSelect = document.getElementById('itemLocation');
        const dateInput = document.getElementById('incidentDate');
        const descTextarea = document.getElementById('itemDescription');
        const submitBtn = document.getElementById('submitLostBtn') || form.querySelector('button[type="submit"]');

        const validateTitle = () => {
            return SCLFS.validateField(titleInput, {
                required: true,
                requiredMessage: 'Item title is required.',
                minLength: 5,
                minLengthMessage: 'Item title must be at least 5 characters.',
                maxLength: 120,
                maxLengthMessage: 'Item title cannot exceed 120 characters.'
            });
        };

        const validateCategory = () => {
            return SCLFS.validateField(categorySelect, {
                required: true,
                requiredMessage: 'Please select an item category.'
            });
        };

        const validateLocation = () => {
            return SCLFS.validateField(locationSelect, {
                required: true,
                requiredMessage: 'Please select the campus location where item was lost.'
            });
        };

        const validateDate = () => {
            return SCLFS.validateField(dateInput, {
                required: true,
                requiredMessage: 'Date lost is required.',
                dateNotFuture: true,
                dateMessage: 'Date lost cannot be in the future.',
                dateMaxPastDays: 180,
                datePastMessage: 'Reports cannot exceed 180 days in the past.'
            });
        };

        const validateDesc = () => {
            return SCLFS.validateField(descTextarea, {
                required: true,
                requiredMessage: 'Detailed description is required.',
                minLength: 15,
                minLengthMessage: 'Description must be at least 15 characters.',
                maxLength: 2000,
                maxLengthMessage: 'Description cannot exceed 2000 characters.'
            });
        };

        // Live event listeners
        if (titleInput) {
            titleInput.addEventListener('blur', validateTitle);
            titleInput.addEventListener('input', () => {
                if (titleInput.classList.contains('is-invalid')) validateTitle();
            });
        }
        if (categorySelect) categorySelect.addEventListener('change', validateCategory);
        if (locationSelect) locationSelect.addEventListener('change', validateLocation);
        if (dateInput) dateInput.addEventListener('change', validateDate);
        if (descTextarea) {
            descTextarea.addEventListener('blur', validateDesc);
            descTextarea.addEventListener('input', () => {
                if (descTextarea.classList.contains('is-invalid')) validateDesc();
            });
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const vTitle = validateTitle();
            const vCat = validateCategory();
            const vLoc = validateLocation();
            const vDate = validateDate();
            const vDesc = validateDesc();

            if (!vTitle || !vCat || !vLoc || !vDate || !vDesc) {
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                SCLFS.showToast('Please correct the highlighted fields before submitting.', 'warning');
                return;
            }

            SCLFS.setButtonLoading(submitBtn, true, 'Submitting Report...');

            const formData = new FormData(form);
            const actionUrl = form.getAttribute('action') || 'api/items/create.php';

            try {
                const response = await SCLFS.fetchAPI(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    SCLFS.showToast('Lost item reported successfully! Scanning for smart matches...', 'success');
                    const newItemId = response.data && response.data.item_id;
                    setTimeout(() => {
                        window.location.href = newItemId ? `item-details.php?id=${newItemId}` : 'my-reports.php?tab=lost';
                    }, 800);
                } else if (response.notImplemented) {
                    const notice = 'Backend endpoint (api/items/create.php) has not been implemented yet by Member 3.';
                    SCLFS.showToast(notice, 'warning', 6000);
                    SCLFS.setButtonLoading(submitBtn, false);
                } else {
                    let msg = response.message || 'Failed to submit report.';
                    if (Array.isArray(response.errors) && response.errors.length > 0) {
                        msg = response.errors.join(' ');
                    }
                    SCLFS.showToast(msg, 'danger');
                    SCLFS.setButtonLoading(submitBtn, false);
                }
            } catch (err) {
                console.error('[Report Lost Error]', err);
                SCLFS.showToast('An unexpected error occurred. Please try again.', 'danger');
                SCLFS.setButtonLoading(submitBtn, false);
            }
        });
    }

    // =========================================================================
    // 3. REPORT FOUND FORM VALIDATION & CUSTODY SELECTION
    // =========================================================================
    function initReportFoundForm() {
        const form = document.getElementById('reportFoundForm');
        if (!form) return;

        // Custody radio cards selection
        const custodyCards = document.querySelectorAll('.radio-card');
        const custodyRadios = document.querySelectorAll('input[name="custody_status"]');

        custodyRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                custodyCards.forEach(card => card.classList.remove('selected'));
                const parentCard = radio.closest('.radio-card');
                if (parentCard) parentCard.classList.add('selected');
            });
        });

        custodyCards.forEach(card => {
            card.addEventListener('click', () => {
                const radio = card.querySelector('input[type="radio"]');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            });
        });

        const titleInput = document.getElementById('itemTitle');
        const categorySelect = document.getElementById('itemCategory');
        const locationSelect = document.getElementById('itemLocation');
        const dateInput = document.getElementById('incidentDate');
        const descTextarea = document.getElementById('itemDescription');
        const featuresTextarea = document.getElementById('identifyingFeatures');
        const submitBtn = document.getElementById('submitFoundBtn') || form.querySelector('button[type="submit"]');

        const validateTitle = () => {
            return SCLFS.validateField(titleInput, {
                required: true,
                requiredMessage: 'Public title is required.',
                minLength: 5,
                minLengthMessage: 'Title must be at least 5 characters.',
                maxLength: 120
            });
        };

        const validateCategory = () => {
            return SCLFS.validateField(categorySelect, {
                required: true,
                requiredMessage: 'Please select an item category.'
            });
        };

        const validateLocation = () => {
            return SCLFS.validateField(locationSelect, {
                required: true,
                requiredMessage: 'Please select where you found the item.'
            });
        };

        const validateDate = () => {
            return SCLFS.validateField(dateInput, {
                required: true,
                requiredMessage: 'Date found is required.',
                dateNotFuture: true,
                dateMessage: 'Date found cannot be in the future.',
                dateMaxPastDays: 180,
                datePastMessage: 'Date cannot exceed 180 days in the past.'
            });
        };

        const validateDesc = () => {
            return SCLFS.validateField(descTextarea, {
                required: true,
                requiredMessage: 'Public description is required.',
                minLength: 15,
                minLengthMessage: 'Public description must be at least 15 characters.',
                maxLength: 2000
            });
        };

        const validateFeatures = () => {
            return SCLFS.validateField(featuresTextarea, {
                required: true,
                requiredMessage: 'Secret identifying features are mandatory to prevent fraudulent claims.',
                minLength: 10,
                minLengthMessage: 'Please specify secret details known only to genuine owner (at least 10 chars).',
                maxLength: 2000
            });
        };

        // Live validation listeners
        if (titleInput) {
            titleInput.addEventListener('blur', validateTitle);
            titleInput.addEventListener('input', () => {
                if (titleInput.classList.contains('is-invalid')) validateTitle();
            });
        }
        if (categorySelect) categorySelect.addEventListener('change', validateCategory);
        if (locationSelect) locationSelect.addEventListener('change', validateLocation);
        if (dateInput) dateInput.addEventListener('change', validateDate);
        if (descTextarea) {
            descTextarea.addEventListener('blur', validateDesc);
            descTextarea.addEventListener('input', () => {
                if (descTextarea.classList.contains('is-invalid')) validateDesc();
            });
        }
        if (featuresTextarea) {
            featuresTextarea.addEventListener('blur', validateFeatures);
            featuresTextarea.addEventListener('input', () => {
                if (featuresTextarea.classList.contains('is-invalid')) validateFeatures();
            });
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const vTitle = validateTitle();
            const vCat = validateCategory();
            const vLoc = validateLocation();
            const vDate = validateDate();
            const vDesc = validateDesc();
            const vFeatures = validateFeatures();

            if (!vTitle || !vCat || !vLoc || !vDate || !vDesc || !vFeatures) {
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                SCLFS.showToast('Please fill out all required fields, including secret identifying marks.', 'warning');
                return;
            }

            SCLFS.setButtonLoading(submitBtn, true, 'Saving Found Report...');

            const formData = new FormData(form);
            const actionUrl = form.getAttribute('action') || 'api/items/create.php';

            try {
                const response = await SCLFS.fetchAPI(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    SCLFS.showToast('Found item reported successfully! Stored safely.', 'success');
                    const newItemId = response.data && response.data.item_id;
                    setTimeout(() => {
                        window.location.href = newItemId ? `item-details.php?id=${newItemId}` : 'my-reports.php?tab=found';
                    }, 800);
                } else if (response.notImplemented) {
                    const notice = 'Backend endpoint (api/items/create.php) has not been implemented yet by Member 3.';
                    SCLFS.showToast(notice, 'warning', 6000);
                    SCLFS.setButtonLoading(submitBtn, false);
                } else {
                    let msg = response.message || 'Failed to submit report.';
                    if (Array.isArray(response.errors) && response.errors.length > 0) {
                        msg = response.errors.join(' ');
                    }
                    SCLFS.showToast(msg, 'danger');
                    SCLFS.setButtonLoading(submitBtn, false);
                }
            } catch (err) {
                console.error('[Report Found Error]', err);
                SCLFS.showToast('An unexpected error occurred. Please try again.', 'danger');
                SCLFS.setButtonLoading(submitBtn, false);
            }
        });
    }

    // =========================================================================
    // 4. CATALOG SEARCH & FILTER CONTROLS (items.php)
    // =========================================================================
    function initCatalogFilters() {
        const filterForm = document.getElementById('filterForm');
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.getElementById('searchClearBtn');
        const itemsContainer = document.getElementById('itemsContainer');
        const resultsCountEl = document.getElementById('resultsCount');
        const paginationContainer = document.getElementById('paginationContainer');

        if (!filterForm || !searchInput) return;

        let debounceTimer = null;

        // Toggle clear button visibility
        const updateClearBtnState = () => {
            if (clearBtn) {
                clearBtn.style.display = searchInput.value.trim().length > 0 ? 'block' : 'none';
            }
        };

        updateClearBtnState();

        // 300ms Debounce search input
        searchInput.addEventListener('input', () => {
            updateClearBtnState();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchFilteredCatalog(1);
            }, 300);
        });

        // Clear button handler
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                searchInput.value = '';
                updateClearBtnState();
                searchInput.focus();
                fetchFilteredCatalog(1);
            });
        }

        // Dropdown filters change listeners
        const filterSelects = filterForm.querySelectorAll('select');
        filterSelects.forEach(select => {
            // Remove inline onchange="this.form.submit()" behavior for progressive async
            select.removeAttribute('onchange');
            select.addEventListener('change', () => {
                fetchFilteredCatalog(1);
            });
        });

        // Form submit
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            fetchFilteredCatalog(1);
        });

        /**
         * Render Skeleton Shimmer cards while fetching
         */
        function renderLoadingSkeletons() {
            if (!itemsContainer) return;
            let skeletonHtml = '<div class="items-grid">';
            for (let i = 0; i < 6; i++) {
                skeletonHtml += `
                    <div class="item-card" style="pointer-events:none;">
                        <div class="skeleton" style="width:100%; aspect-ratio:16/10;"></div>
                        <div class="item-card-body">
                            <div class="skeleton" style="width:40%; height:14px; margin-bottom:8px;"></div>
                            <div class="skeleton" style="width:85%; height:20px; margin-bottom:8px;"></div>
                            <div class="skeleton" style="width:100%; height:40px; margin-bottom:12px;"></div>
                            <div class="skeleton" style="width:60%; height:14px;"></div>
                        </div>
                    </div>
                `;
            }
            skeletonHtml += '</div>';
            itemsContainer.innerHTML = skeletonHtml;
        }

        /**
         * Fetch filtered catalog from API or fall back to native GET if endpoint pending.
         * @param {number} page
         */
        async function fetchFilteredCatalog(page = 1) {
            const formData = new FormData(filterForm);
            const params = new URLSearchParams();

            for (const [key, value] of formData.entries()) {
                if (value !== '' && value !== '0' && value !== 'all') {
                    params.append(key, value);
                }
            }
            params.set('page', String(page));

            // Update browser URL query string without reloading page
            const newRelativePathQuery = window.location.pathname + '?' + params.toString();
            window.history.replaceState(null, '', newRelativePathQuery);

            renderLoadingSkeletons();

            const apiUrl = `api/items/list.php?${params.toString()}`;

            try {
                const response = await SCLFS.fetchAPI(apiUrl);

                if (response.success && response.data) {
                    renderCatalogItems(response.data);
                } else if (response.notImplemented) {
                    // Endpoint not yet implemented: perform standard page GET submission
                    console.info('[SCLFS Catalog] API not yet available, falling back to server reload.');
                    filterForm.submit();
                } else {
                    SCLFS.showToast(response.message || 'Error fetching catalog items.', 'danger');
                    // Fall back to server rendering
                    filterForm.submit();
                }
            } catch (err) {
                console.error('[Catalog Fetch Error]', err);
                filterForm.submit();
            }
        }

        /**
         * Dynamically render item cards into #itemsContainer
         * @param {Object} data
         */
        function renderCatalogItems(data) {
            const items = data.items || [];
            const total = data.total || 0;
            const page = data.page || 1;
            const totalPages = data.total_pages || 1;
            const query = searchInput.value.trim();

            // Update Results Count
            if (resultsCountEl) {
                resultsCountEl.innerHTML = `Showing <strong>${items.length}</strong> of <strong>${total}</strong> reported items` +
                    (query ? ` for query &ldquo;<strong>${SCLFS.escapeHtml(query)}</strong>&rdquo;` : '');
            }

            if (!itemsContainer) return;

            if (items.length === 0) {
                itemsContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </div>
                        <h3 class="empty-state-title">No Matching Items Found</h3>
                        <p class="empty-state-desc">
                            We could not find any reported items matching your active search and filter criteria. Try broadening your filters or reporting a new item.
                        </p>
                        <div style="display: flex; gap: var(--space-3); justify-content: center; flex-wrap: wrap;">
                            <a href="items.php" class="btn btn-secondary btn-sm" id="emptyResetFiltersBtn">Reset All Filters</a>
                            <a href="report-lost.php" class="btn btn-primary btn-sm">Report a Lost Item</a>
                        </div>
                    </div>
                `;

                const emptyReset = document.getElementById('emptyResetFiltersBtn');
                if (emptyReset) {
                    emptyReset.addEventListener('click', (e) => {
                        e.preventDefault();
                        filterForm.reset();
                        fetchFilteredCatalog(1);
                    });
                }

                if (paginationContainer) paginationContainer.innerHTML = '';
                return;
            }

            // Render item cards
            let cardsHtml = '<div class="items-grid">';
            items.forEach(item => {
                const isLost = item.item_type === 'lost';
                const imageSrc = item.image_path ? `uploads/items/${SCLFS.escapeHtml(item.image_path)}` : 'assets/images/default-item.svg';

                let custodyBadge = '';
                if (!isLost) {
                    if (item.custody_status === 'submitted_to_security') {
                        custodyBadge = '<span class="badge badge-custody-security" title="Item physically deposited at Campus Security Desk">At Security Desk</span>';
                    } else if (item.custody_status === 'with_finder') {
                        custodyBadge = '<span class="badge badge-custody-finder" title="Item kept with finder until verified claim">With Finder</span>';
                    } else if (item.custody_status === 'returned_to_owner') {
                        custodyBadge = '<span class="badge badge-custody-returned">Returned</span>';
                    }
                }

                const descTruncated = item.description && item.description.length > 110
                    ? item.description.substring(0, 110) + '...'
                    : (item.description || '');

                const locationText = SCLFS.escapeHtml(item.building || 'Campus') +
                    (item.specific_area ? ' &bull; ' + SCLFS.escapeHtml(item.specific_area) : '');

                cardsHtml += `
                    <article class="item-card" data-item-id="${item.id}">
                        <div class="item-card-media">
                            <div class="item-card-badges">
                                <span class="badge badge-${isLost ? 'lost' : 'found'}">
                                    ${isLost ? 'Lost' : 'Found'}
                                </span>
                                ${custodyBadge}
                            </div>
                            <img src="${imageSrc}" alt="${SCLFS.escapeHtml(item.title)}" loading="lazy">
                        </div>
                        <div class="item-card-body">
                            <div class="item-card-meta-top">
                                <span class="badge badge-secondary" style="font-size: 0.6875rem;">
                                    ${SCLFS.escapeHtml(item.category_name || 'General')}
                                </span>
                                <span>&bull;</span>
                                <time datetime="${SCLFS.escapeHtml(item.incident_date)}">${SCLFS.escapeHtml(item.incident_date)}</time>
                            </div>
                            <h2 class="item-card-title">
                                <a href="item-details.php?id=${item.id}">
                                    ${SCLFS.escapeHtml(item.title)}
                                </a>
                            </h2>
                            <p class="item-card-desc">
                                ${SCLFS.escapeHtml(descTruncated)}
                            </p>
                            <div class="item-card-meta-list">
                                <div class="item-card-meta-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <span>${locationText}</span>
                                </div>
                            </div>
                        </div>
                        <div class="item-card-footer">
                            <span style="font-size: 0.8125rem; color: var(--color-text-muted); text-transform: capitalize;">
                                Status: <strong>${SCLFS.escapeHtml(item.status || 'open')}</strong>
                            </span>
                            <a href="item-details.php?id=${item.id}" class="btn btn-outline btn-sm">
                                View Details &rarr;
                            </a>
                        </div>
                    </article>
                `;
            });
            cardsHtml += '</div>';

            itemsContainer.innerHTML = cardsHtml;

            // Render Pagination
            if (paginationContainer) {
                if (totalPages > 1) {
                    let pagHtml = '';
                    for (let p = 1; p <= totalPages; p++) {
                        pagHtml += `
                            <button type="button" class="btn btn-sm ${p === page ? 'btn-primary' : 'btn-secondary'}" data-page="${p}" style="min-width:36px;">
                                ${p}
                            </button>
                        `;
                    }
                    paginationContainer.innerHTML = pagHtml;

                    paginationContainer.querySelectorAll('button[data-page]').forEach(pBtn => {
                        pBtn.addEventListener('click', () => {
                            const targetPage = parseInt(pBtn.dataset.page, 10);
                            fetchFilteredCatalog(targetPage);
                            itemsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    });
                } else {
                    paginationContainer.innerHTML = '';
                }
            }
        }
    }

    // =========================================================================
    // 5. CUSTODY TRANSFER ACTION (My Reports: with_finder -> submitted_to_security)
    // =========================================================================
    function initCustodyTransfer() {
        document.addEventListener('click', async (e) => {
            const transferBtn = e.target.closest('[data-transfer-custody]');
            if (!transferBtn) return;

            e.preventDefault();
            const itemId = transferBtn.getAttribute('data-transfer-custody');
            const itemTitle = transferBtn.getAttribute('data-item-title') || 'this item';

            const confirmed = await SCLFS.confirm(
                'Surrender to Campus Security Desk',
                `Are you sure you want to mark "${itemTitle}" as surrendered to the Campus Security Desk (Ground Floor, Admin Block)? Claimants will be directed to collect it from the desk.`,
                {
                    confirmText: 'Yes, Mark Surrendered',
                    cancelText: 'Cancel',
                    confirmVariant: 'primary'
                }
            );

            if (!confirmed) return;

            SCLFS.setButtonLoading(transferBtn, true, 'Updating...');

            try {
                const response = await SCLFS.fetchAPI('api/items/update-custody.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `item_id=${encodeURIComponent(itemId)}&new_custody=submitted_to_security&csrf_token=${encodeURIComponent(SCLFS.getCsrfToken())}`
                });

                if (response.success) {
                    SCLFS.showToast('Item custody updated to Security Desk.', 'success');
                    setTimeout(() => window.location.reload(), 600);
                } else if (response.notImplemented) {
                    SCLFS.showToast('Custody update API endpoint is pending Member 3 implementation.', 'warning');
                    SCLFS.setButtonLoading(transferBtn, false);
                } else {
                    SCLFS.showToast(response.message || 'Failed to update custody.', 'danger');
                    SCLFS.setButtonLoading(transferBtn, false);
                }
            } catch (err) {
                console.error('[Custody Update Error]', err);
                SCLFS.showToast('An unexpected error occurred.', 'danger');
                SCLFS.setButtonLoading(transferBtn, false);
            }
        });
    }

    // DOM Ready initialization
    document.addEventListener('DOMContentLoaded', () => {
        initImageDropzone();
        initReportLostForm();
        initReportFoundForm();
        initCatalogFilters();
        initCustodyTransfer();
    });

    SCLFS.items = {
        initImageDropzone,
        initReportLostForm,
        initReportFoundForm,
        initCatalogFilters,
        initCustodyTransfer
    };

})(window, document);
