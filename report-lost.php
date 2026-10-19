<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Report Lost Item Form View
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

init_session();

// Guard: Must be authenticated
if (!is_logged_in()) {
    flash_set('warning', 'Please log in to report a lost belonging.');
    header('Location: ' . url('login.php?redirect=report-lost.php'));
    exit;
}

$pageTitle = 'Report Lost Item - ' . APP_SHORT_NAME;
$activeNav = 'report-lost';

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
        <span>Report Lost Item</span>
    </nav>

    <div style="margin-bottom: var(--space-8);">
        <span class="badge badge-lost" style="margin-bottom: var(--space-2);">Lost Property Report</span>
        <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--color-text); margin-bottom: var(--space-2);">
            Report a Missing Belonging
        </h1>
        <p style="font-size: 1.0625rem; color: var(--color-text-muted); line-height: 1.6;">
            Provide accurate details about where and when you lost your property. 
            Our automated matching engine immediately evaluates incoming reports and notifies you when compatible items are found.
        </p>
    </div>

    <!-- Alert Box for Client / Server Validation Feedback -->
    <div id="formAlert" class="alert alert-danger" style="display: none;" role="alert">
        <span id="formAlertMsg"></span>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <strong style="font-size: 1.0625rem;">Lost Item Details</strong>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0;">
                    All fields marked with <span style="color: var(--color-danger);">*</span> are required.
                </p>
            </div>
            <span class="badge badge-primary">Instant Match Scanning</span>
        </div>

        <form id="reportLostForm" method="POST" action="<?= url('api/items/create.php') ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
            <input type="hidden" name="item_type" value="lost">

            <div class="card-body">
                <!-- Item Title -->
                <div class="form-group">
                    <label for="itemTitle" class="form-label">
                        Item Title / Name <span style="color: var(--color-danger);">*</span>
                    </label>
                    <input type="text" 
                           id="itemTitle" 
                           name="title" 
                           class="form-control" 
                           placeholder="e.g. Dell Inspiron 15 Laptop, Black Leather Wallet, Student ID Card" 
                           required 
                           minlength="5" 
                           maxlength="120"
                           autofocus>
                    <div class="form-hint">Be concise and include brand/color (5 to 120 characters).</div>
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
                            Campus Building / Area <span style="color: var(--color-danger);">*</span>
                        </label>
                        <select id="itemLocation" name="location_id" class="form-select" required>
                            <option value="">-- Select Last Seen Location --</option>
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
                            Date Lost <span style="color: var(--color-danger);">*</span>
                        </label>
                        <input type="date" 
                               id="incidentDate" 
                               name="incident_date" 
                               class="form-control" 
                               max="<?= date('Y-m-d') ?>" 
                               value="<?= date('Y-m-d') ?>" 
                               required>
                        <div class="form-hint">Cannot be in the future.</div>
                        <div class="invalid-feedback" id="dateError">Valid past or current date required.</div>
                    </div>

                    <div class="form-group">
                        <label for="incidentTime" class="form-label">Approximate Time (Optional)</label>
                        <input type="time" 
                               id="incidentTime" 
                               name="incident_time" 
                               class="form-control">
                    </div>
                </div>

                <!-- Detailed Description -->
                <div class="form-group">
                    <label for="itemDescription" class="form-label">
                        Detailed Description <span style="color: var(--color-danger);">*</span>
                    </label>
                    <textarea id="itemDescription" 
                              name="description" 
                              class="form-control" 
                              rows="4" 
                              placeholder="Describe the item's appearance, color, brand, condition, stickers, case, or specific circumstance where you might have left it..." 
                              required 
                              minlength="15" 
                              maxlength="2000"></textarea>
                    <div class="form-hint">Minimum 15 characters. The more specific details you include, the higher the smart match accuracy.</div>
                    <div class="invalid-feedback" id="descError">Description must be between 15 and 2000 characters.</div>
                </div>

                <!-- Photo Reference Dropzone -->
                <div class="form-group">
                    <label class="form-label">Photo Reference (Optional)</label>
                    <div class="file-dropzone" id="fileDropzone">
                        <svg class="dropzone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <div class="dropzone-title">Click to upload photo or drag &amp; drop</div>
                        <div class="dropzone-hint">Supports JPEG, PNG, WebP up to 2MB</div>
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
                <button type="submit" id="submitLostBtn" class="btn btn-primary btn-lg">
                    Submit Lost Report &amp; Find Matches
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Simple client dropzone preview helper
document.addEventListener('DOMContentLoaded', () => {
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
