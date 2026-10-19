<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Report Found Item Form View
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

init_session();

// Guard: Must be authenticated
if (!is_logged_in()) {
    flash_set('warning', 'Please log in to report a found belonging.');
    header('Location: ' . url('login.php?redirect=report-found.php'));
    exit;
}

$pageTitle = 'Report Found Item - ' . APP_SHORT_NAME;
$activeNav = 'report-found';

$categories = [];
$locations = [];

try {
    $pdo = get_db_connection();
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $locations = $pdo->query("SELECT id, campus_zone, building, specific_area FROM locations ORDER BY building ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Handled gracefully
}

$extraScripts = ['assets/js/items.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8); max-width: 860px;">
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <span>Report Found Item</span>
    </nav>

    <div style="margin-bottom: var(--space-8);">
        <span class="badge badge-found" style="margin-bottom: var(--space-2);">Found Property Report</span>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: var(--space-2);">
            Report a Found Belonging
        </h1>
        <p style="font-size: 1.0625rem; color: var(--color-text-muted); line-height: 1.6;">
            Thank you for helping reunite campus property! Specify public details and <strong>secret identifying marks</strong> 
            (which remain confidential to prevent fraudulent ownership claims).
        </p>
    </div>

    <!-- Alert Box for Client / Server Validation Feedback -->
    <div id="formAlert" class="alert alert-danger" style="display: none;" role="alert">
        <span id="formAlertMsg"></span>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <strong style="font-size: 1.0625rem;">Found Item Details</strong>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0;">
                    All fields marked with <span style="color: var(--color-danger);">*</span> are required.
                </p>
            </div>
            <span class="badge badge-primary">Fraud-Protected Verification</span>
        </div>

        <form id="reportFoundForm" method="POST" action="<?= url('api/items/create.php') ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
            <input type="hidden" name="item_type" value="found">

            <div class="card-body">
                <!-- Custody Status Selection (Interactive Radio Cards) -->
                <div class="form-group" style="margin-bottom: var(--space-6);">
                    <label class="form-label" style="font-size: 0.9375rem;">
                        Current Custody Location <span style="color: var(--color-danger);">*</span>
                    </label>
                    <div class="radio-cards-grid">
                        <label class="radio-card selected" id="custodyFinderCard">
                            <input type="radio" name="custody_status" value="with_finder" checked>
                            <div style="font-weight: 700; color: var(--color-text); margin-bottom: var(--space-1);">
                                I Have Item in Custody
                            </div>
                            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                You keep the item safe until an administrator approves a verified claim.
                            </div>
                        </label>

                        <label class="radio-card" id="custodySecurityCard">
                            <input type="radio" name="custody_status" value="submitted_to_security">
                            <div style="font-weight: 700; color: var(--color-text); margin-bottom: var(--space-1);">
                                Surrendered to Security Desk
                            </div>
                            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                You already dropped off the item at the Campus Security Desk in Admin Block.
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Item Title -->
                <div class="form-group">
                    <label for="itemTitle" class="form-label">
                        Public Item Title <span style="color: var(--color-danger);">*</span>
                    </label>
                    <input type="text" 
                           id="itemTitle" 
                           name="title" 
                           class="form-control" 
                           placeholder="e.g. Scientific Calculator in Gray Case, Brown Leather Wallet" 
                           required 
                           minlength="5" 
                           maxlength="120">
                    <div class="form-hint">Public title visible on catalog (do NOT include secret serial numbers here).</div>
                    <div class="invalid-feedback" id="titleError">Title is required (5 to 120 characters).</div>
                </div>

                <!-- Category & Location Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="itemCategory" class="form-label">
                            Category <span style="color: var(--color-danger);">*</span>
                        </label>
                        <select id="itemCategory" name="category_id" class="form-select" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback" id="categoryError">Please select a category.</div>
                    </div>

                    <div class="form-group">
                        <label for="itemLocation" class="form-label">
                            Found Location / Building <span style="color: var(--color-danger);">*</span>
                        </label>
                        <select id="itemLocation" name="location_id" class="form-select" required>
                            <option value="">-- Select Where You Found It --</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>">
                                    <?= e($loc['building']) ?> (<?= e($loc['campus_zone']) ?>)
                                    <?= !empty($loc['specific_area']) ? ' - ' . e($loc['specific_area']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback" id="locationError">Please select a campus location.</div>
                    </div>
                </div>

                <!-- Date & Time Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="incidentDate" class="form-label">
                            Date Found <span style="color: var(--color-danger);">*</span>
                        </label>
                        <input type="date" 
                               id="incidentDate" 
                               name="incident_date" 
                               class="form-control" 
                               max="<?= date('Y-m-d') ?>" 
                               value="<?= date('Y-m-d') ?>" 
                               required>
                        <div class="invalid-feedback" id="dateError">Valid past or current date required.</div>
                    </div>

                    <div class="form-group">
                        <label for="incidentTime" class="form-label">Approximate Time Found (Optional)</label>
                        <input type="time" 
                               id="incidentTime" 
                               name="incident_time" 
                               class="form-control">
                    </div>
                </div>

                <!-- Public Description -->
                <div class="form-group">
                    <label for="itemDescription" class="form-label">
                        Public Description <span style="color: var(--color-danger);">*</span>
                    </label>
                    <textarea id="itemDescription" 
                              name="description" 
                              class="form-control" 
                              rows="3" 
                              placeholder="General description visible to everyone on campus: e.g. Found on 2nd floor library study desk near window..." 
                              required 
                              minlength="15" 
                              maxlength="2000"></textarea>
                    <div class="form-hint">Visible to everyone browsing the catalog (15 to 2000 characters).</div>
                    <div class="invalid-feedback" id="descError">Description must be at least 15 characters.</div>
                </div>

                <!-- CONFIDENTIAL PRIVATE IDENTIFYING FEATURES -->
                <div class="confidential-box" style="margin-top: var(--space-6); margin-bottom: var(--space-6);">
                    <div class="confidential-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Secret Identifying Features (Strictly Confidential) <span style="color: var(--color-danger);">*</span></span>
                    </div>
                    <p class="confidential-desc">
                        <strong>Anti-Fraud Shield:</strong> This information is strictly hidden from public listings. Only you and authorized campus security officers can see it. Administrators compare claimant proof descriptions against what you enter here.
                    </p>

                    <div style="margin-top: var(--space-3);">
                        <textarea id="identifyingFeatures" 
                                  name="identifying_features" 
                                  class="form-control" 
                                  rows="3" 
                                  style="border-color: var(--color-confidential-border); background-color: #ffffff;"
                                  placeholder="Secret details known only to the genuine owner: e.g. Specific serial number, student ID number on card, wallpaper image on locked screen, names on receipts inside wallet, scratches or stickers..." 
                                  required 
                                  maxlength="2000"></textarea>
                        <div class="invalid-feedback" id="identifyingFeaturesError">Please specify secret identifying features for verification.</div>
                    </div>
                </div>

                <!-- Photo Reference Dropzone -->
                <div class="form-group">
                    <label class="form-label">Public Photo of Found Item (Optional)</label>
                    <div class="file-dropzone" id="fileDropzone">
                        <svg class="dropzone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <div class="dropzone-title">Click to upload photo or drag &amp; drop</div>
                        <div class="dropzone-hint">Supports JPEG, PNG, WebP up to 2MB. Ensure private markings (like full serial numbers) are not exposed in the photo!</div>
                        <input type="file" 
                               id="itemImage" 
                               name="image" 
                               class="dropzone-input" 
                               accept="image/jpeg,image/png,image/webp">
                    </div>

                    <!-- Live Image Preview Box -->
                    <div class="image-preview-container" id="imagePreviewContainer">
                        <img id="imagePreview" src="" alt="Selected Image Preview">
                        <button type="button" class="image-preview-remove" id="imagePreviewRemove" aria-label="Remove image">&times;</button>
                    </div>
                </div>
            </div>

            <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
                <a href="<?= url('items.php') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" id="submitFoundBtn" class="btn btn-primary btn-lg">
                    Submit Found Report &amp; Store Securely
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Radio card selection styling and dropzone preview
document.addEventListener('DOMContentLoaded', () => {
    const custodyRadios = document.querySelectorAll('input[name="custody_status"]');
    custodyRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.radio-card').forEach(c => c.classList.remove('selected'));
            radio.closest('.radio-card').classList.add('selected');
        });
    });

    const input = document.getElementById('itemImage');
    const container = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');
    const removeBtn = document.getElementById('imagePreviewRemove');

    if (input && container && preview) {
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                    container.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                input.value = '';
                preview.src = '';
                container.style.display = 'none';
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
