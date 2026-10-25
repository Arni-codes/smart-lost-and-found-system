<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Item Details & Claim Submission View
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

init_session();
$currentUser = get_current_user_session();

$itemId = (int)($_GET['id'] ?? 0);
if ($itemId <= 0) {
    header('Location: ' . url('items.php'));
    exit;
}

$item = null;
$matches = [];
$isAuthorOrAdmin = false;

try {
    $pdo = get_db_connection();

    // Query item with category and location joins
    $stmt = $pdo->prepare(
        "SELECT i.*, 
                c.name AS category_name, c.icon AS category_icon,
                l.campus_zone, l.building, l.specific_area,
                u.full_name AS reporter_name, u.department AS reporter_department, u.role AS reporter_role
         FROM items i
         JOIN categories c ON i.category_id = c.id
         JOIN locations l ON i.location_id = l.id
         JOIN users u ON i.user_id = u.id
         WHERE i.id = :id"
    );
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        $pageTitle = 'Item Not Found - ' . APP_SHORT_NAME;
        require_once __DIR__ . '/includes/header.php';
        echo '<div class="container section"><div class="empty-state">
                <h2 class="empty-state-title">Item Not Found</h2>
                <p class="empty-state-desc">The item you are looking for does not exist or has been removed.</p>
                <a href="' . url('items.php') . '" class="btn btn-primary">Return to Catalog</a>
              </div></div>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    // Check privacy authorization
    $isAuthor = $currentUser && ((int)$currentUser['id'] === (int)$item['user_id']);
    $isAdminUser = is_admin();
    $isAuthorOrAdmin = $isAuthor || $isAdminUser;

    // Fetch on-demand cached smart matches if any
    if ($item['item_type'] === 'lost') {
        $mStmt = $pdo->prepare(
            "SELECT m.match_score, m.score_breakdown, m.status AS match_status,
                    i.id AS matched_id, i.title, i.incident_date, i.custody_status, i.image_path,
                    l.building
             FROM smart_matches m
             JOIN items i ON m.found_item_id = i.id
             JOIN locations l ON i.location_id = l.id
             WHERE m.lost_item_id = :id AND i.status = 'open'
             ORDER BY m.match_score DESC
             LIMIT 3"
        );
    } else {
        $mStmt = $pdo->prepare(
            "SELECT m.match_score, m.score_breakdown, m.status AS match_status,
                    i.id AS matched_id, i.title, i.incident_date, i.custody_status, i.image_path,
                    l.building
             FROM smart_matches m
             JOIN items i ON m.lost_item_id = i.id
             JOIN locations l ON i.location_id = l.id
             WHERE m.found_item_id = :id AND i.status = 'open'
             ORDER BY m.match_score DESC
             LIMIT 3"
        );
    }
    $mStmt->execute([':id' => $itemId]);
    $matches = $mStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    http_response_code(500);
    die("An error occurred while loading item details: " . e($e->getMessage()));
}

$pageTitle = e($item['title']) . ' - ' . APP_SHORT_NAME;
$activeNav = 'items';
$extraScripts = ['assets/js/claims.js'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-4);">
        <a href="<?= url('index.php') ?>">Home</a> &rsaquo; 
        <a href="<?= url('items.php') ?>">Campus Items</a> &rsaquo; 
        <span><?= e($item['title']) ?></span>
    </nav>

    <!-- Main Item Details Layout -->
    <div class="item-details-layout">
        <!-- Left Column: Media & Custody Card -->
        <div>
            <div class="item-details-media-card">
                <div class="item-details-image-holder">
                    <img src="<?= !empty($item['image_path']) ? url('uploads/items/' . e($item['image_path'])) : url('assets/images/default-item.svg') ?>" 
                         alt="<?= e($item['title']) ?>" 
                         id="mainItemImage">
                </div>
                <div style="padding: var(--space-4); background-color: #fafbfc; border-top: 1px solid var(--color-border); font-size: 0.8125rem; color: var(--color-text-muted); display: flex; justify-content: space-between; align-items: center;">
                    <span>Photo Reference</span>
                    <span>Report #<?= e($item['id']) ?></span>
                </div>
            </div>

            <!-- Custody & Security Status Card (For Found Items) -->
            <?php if ($item['item_type'] === 'found'): ?>
                <div class="card" style="margin-top: var(--space-6);">
                    <div class="card-header">
                        <strong style="font-size: 0.9375rem;">Physical Item Custody</strong>
                        <?php if ($item['custody_status'] === 'submitted_to_security'): ?>
                            <span class="badge badge-custody-security">At Security Desk</span>
                        <?php elseif ($item['custody_status'] === 'with_finder'): ?>
                            <span class="badge badge-custody-finder">With Finder</span>
                        <?php elseif ($item['custody_status'] === 'returned_to_owner'): ?>
                            <span class="badge badge-custody-returned">Returned to Owner</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body" style="font-size: 0.875rem; color: var(--color-text-muted); line-height: 1.6;">
                        <?php if ($item['custody_status'] === 'submitted_to_security'): ?>
                            <p>
                                <strong>Safekeeping:</strong> This item has been safely deposited at the central campus 
                                <strong>Campus Security Desk (Ground Floor, Admin Block)</strong>.
                            </p>
                            <p style="margin-top: var(--space-2);">
                                If your claim is approved, you will collect the item directly from the desk upon presenting your student ID card.
                            </p>
                        <?php elseif ($item['custody_status'] === 'with_finder'): ?>
                            <p>
                                <strong>Temporary Custody:</strong> This item is currently held by the student/staff finder.
                            </p>
                            <p style="margin-top: var(--space-2);">
                                The finder will transfer the item to the Campus Security Desk once an ownership claim is verified by the administrator.
                            </p>
                        <?php elseif ($item['custody_status'] === 'returned_to_owner'): ?>
                            <p style="color: var(--color-success); font-weight: 600;">
                                &check; This item has already been successfully reunited with its verified owner.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Smart Match Suggestions Card -->
            <?php if (!empty($matches)): ?>
                <div class="card" style="margin-top: var(--space-6);">
                    <div class="card-header">
                        <strong style="font-size: 0.9375rem;">Potential System Matches</strong>
                        <span class="badge badge-primary">Smart Heuristic</span>
                    </div>
                    <div class="card-body" style="padding: var(--space-4);">
                        <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-3);">
                            Our multi-factor matching algorithm detected similar reports in the database:
                        </p>
                        <?php foreach ($matches as $match): ?>
                            <div style="background: #f8fafc; border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3); margin-bottom: var(--space-3); display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <h4 style="font-size: 0.875rem; font-weight: 700; margin-bottom: 2px;">
                                        <a href="<?= url('item-details.php?id=' . $match['matched_id']) ?>">
                                            <?= e($match['title']) ?>
                                        </a>
                                    </h4>
                                    <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                        <?= e($match['building']) ?> &bull; <?= format_date($match['incident_date']) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge <?= (float)$match['match_score'] >= 70 ? 'badge-match-high' : 'badge-match-med' ?>" title="Heuristic similarity score">
                                        <?= round((float)$match['match_score']) ?>% Match
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Primary Item Info & Distinguishing Features -->
        <div>
            <div class="item-details-header">
                <div class="item-details-badges">
                    <span class="badge badge-<?= $item['item_type'] === 'lost' ? 'lost' : 'found' ?>">
                        <?= ucfirst(e($item['item_type'])) ?> Item
                    </span>
                    <span class="badge badge-secondary">
                        <?= e($item['category_name']) ?>
                    </span>
                    <span class="badge <?= $item['status'] === 'open' ? 'badge-success' : 'badge-warning' ?>">
                        Status: <?= ucfirst(e($item['status'])) ?>
                    </span>
                </div>

                <h1 class="item-details-title">
                    <?= e($item['title']) ?>
                </h1>

                <p style="font-size: 1.0625rem; color: var(--color-text); line-height: 1.7; white-space: pre-line; margin-bottom: var(--space-6);">
                    <?= e($item['description']) ?>
                </p>
            </div>

            <!-- Metadata List -->
            <dl class="item-details-dl">
                <dt class="item-details-dt">Incident Date</dt>
                <dd class="item-details-dd">
                    <strong><?= format_date($item['incident_date']) ?></strong>
                    <?php if (!empty($item['incident_time'])): ?>
                        at <?= date('g:i A', strtotime($item['incident_time'])) ?>
                    <?php endif; ?>
                </dd>

                <dt class="item-details-dt">Campus Location</dt>
                <dd class="item-details-dd">
                    <strong><?= e($item['building']) ?></strong>
                    <?php if (!empty($item['specific_area'])): ?>
                        <span style="color: var(--color-text-muted);"> &mdash; <?= e($item['specific_area']) ?></span>
                    <?php endif; ?>
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-top: 2px;">
                        Zone: <?= e($item['campus_zone']) ?>
                    </div>
                </dd>

                <dt class="item-details-dt">Reported By</dt>
                <dd class="item-details-dd">
                    <?= e($item['reporter_name']) ?> 
                    <?php if (!empty($item['reporter_department'])): ?>
                        <span style="color: var(--color-text-muted); font-size: 0.8125rem;">(<?= e($item['reporter_department']) ?>)</span>
                    <?php endif; ?>
                </dd>

                <dt class="item-details-dt">Reported On</dt>
                <dd class="item-details-dd">
                    <?= format_datetime($item['created_at']) ?>
                </dd>
            </dl>

            <!-- Confidential Identifying Features Callout (Security Protection) -->
            <?php if ($item['item_type'] === 'found'): ?>
                <div class="confidential-box" id="confidentialFeatureBox">
                    <div class="confidential-header">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Secret Identifying Features (Verification Only)</span>
                    </div>

                    <?php if ($isAuthorOrAdmin): ?>
                        <p class="confidential-desc">
                            <strong>Authorized View:</strong> You are logged in as the report author or administrator. The secret markings below are hidden from all other public viewers:
                        </p>
                        <div class="confidential-secret-content">
                            <?= !empty($item['identifying_features']) ? e($item['identifying_features']) : '<em>No confidential markings entered.</em>' ?>
                        </div>
                    <?php else: ?>
                        <p class="confidential-desc">
                            <strong>Confidential Protection Active:</strong> Distinguishing marks (such as engraved initials, private serial numbers, internal contents, or wallpaper descriptions) are strictly hidden from public view. 
                        </p>
                        <p class="confidential-desc" style="margin-top: var(--space-2);">
                            To claim this item, click &ldquo;Claim This Item&rdquo; below and describe these secret features in your proof of ownership.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Claim Call to Action Banner (Only for open Found items) -->
            <?php if ($item['item_type'] === 'found' && $item['status'] === 'open'): ?>
                <div class="claim-cta-banner">
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text); margin-bottom: var(--space-1);">
                            Is this your missing belonging?
                        </h3>
                        <p style="font-size: 0.875rem; color: var(--color-text-muted); margin: 0;">
                            Submit an ownership claim with distinguishing proof for administrative review.
                        </p>
                    </div>

                    <?php if (is_logged_in()): ?>
                        <button type="button" 
                                class="btn btn-success btn-lg" 
                                id="claimItemBtn" 
                                data-modal-target="claimModal">
                            Claim This Item
                        </button>
                    <?php else: ?>
                        <a href="<?= url('login.php?redirect=' . urlencode('item-details.php?id=' . $item['id'])) ?>" class="btn btn-primary">
                            Log In to Claim
                        </a>
                    <?php endif; ?>
                </div>
            <?php elseif ($item['status'] === 'pending_claim'): ?>
                <div class="alert alert-warning" style="margin-top: var(--space-6);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div>
                        <strong>Claim Pending Review:</strong> An ownership claim has been submitted for this item and is currently undergoing administrative evaluation.
                    </div>
                </div>
            <?php elseif ($item['status'] === 'claimed'): ?>
                <div class="alert alert-success" style="margin-top: var(--space-6);">
                    <span>&check; <strong>Reunited:</strong> This item has been verified and returned to its rightful owner.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- =====================================================================
     OWNERSHIP CLAIM MODAL DIALOG
     Coordinated with Member 2 (claims.js) & Member 5 (Claims API)
     ===================================================================== -->
<div class="modal" id="claimModal" role="dialog" aria-labelledby="claimModalTitle" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="claimModalTitle">Claim Ownership of Item</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="Close claim dialog">&times;</button>
        </div>

        <form id="claimForm" method="POST" action="<?= url('api/claims/submit.php') ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
            <input type="hidden" name="item_id" value="<?= e($item['id']) ?>">

            <div class="modal-body">
                <div class="alert alert-info" style="font-size: 0.8125rem;">
                    <strong>Security Notice:</strong> Campus security will compare your proof against confidential private markings provided by the finder. False claims may result in account suspension.
                </div>

                <div class="form-group">
                    <label for="proofDescription" class="form-label">
                        Proof Description &amp; Secret Markings <span style="color: var(--color-danger);">*</span>
                    </label>
                    <textarea id="proofDescription" 
                              name="proof_description" 
                              class="form-control" 
                              rows="5" 
                              placeholder="Describe specific details only the real owner would know: e.g. scratches, stickers, screen lock wallpaper, inner wallet contents, or serial numbers..." 
                              required 
                              minlength="20"></textarea>
                    <div class="form-hint">Minimum 20 characters required.</div>
                    <div class="invalid-feedback" id="proofDescError">Please describe specific proof details (min 20 chars).</div>
                </div>

                <div class="form-group">
                    <label for="proofImage" class="form-label">
                        Supporting Photo / Receipt Proof (Optional)
                    </label>
                    <input type="file" 
                           id="proofImage" 
                           name="proof_image" 
                           class="form-control" 
                           accept="image/jpeg,image/png,image/webp">
                    <div class="form-hint">Upload receipt, purchase bill, or reference photo of you with the item (Max 2MB).</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="claimSubmitBtn">Submit Claim for Review</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
