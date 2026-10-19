<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Master Catalog - Browse Lost & Found Items
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Browse Campus Items - ' . APP_SHORT_NAME;
$activeNav = 'items';

// Load filter metadata
$categories = [];
$locations = [];
$items = [];
$totalCount = 0;

$searchQuery = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? 'all');
$categoryFilter = (int)($_GET['category'] ?? 0);
$locationFilter = (int)($_GET['location'] ?? 0);
$custodyFilter = trim($_GET['custody'] ?? 'all');
$statusFilter = trim($_GET['status'] ?? 'open');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

try {
    $pdo = get_db_connection();

    // Fetch categories for filter dropdown
    $catStmt = $pdo->query("SELECT id, name, icon FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch unique buildings for filter dropdown
    $locStmt = $pdo->query("SELECT id, campus_zone, building FROM locations ORDER BY building ASC");
    $locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build dynamic query with prepared parameters
    $where = ["1=1"];
    $params = [];

    // Filter by type
    if ($typeFilter === 'lost' || $typeFilter === 'found') {
        $where[] = "i.item_type = :item_type";
        $params[':item_type'] = $typeFilter;
    }

    // Filter by category
    if ($categoryFilter > 0) {
        $where[] = "i.category_id = :category_id";
        $params[':category_id'] = $categoryFilter;
    }

    // Filter by location
    if ($locationFilter > 0) {
        $where[] = "i.location_id = :location_id";
        $params[':location_id'] = $locationFilter;
    }

    // Filter by custody
    if (in_array($custodyFilter, ['with_finder', 'submitted_to_security', 'returned_to_owner'])) {
        $where[] = "i.custody_status = :custody_status";
        $params[':custody_status'] = $custodyFilter;
    }

    // Filter by status
    if ($statusFilter !== 'all' && in_array($statusFilter, ['open', 'pending_claim', 'claimed', 'closed'])) {
        $where[] = "i.status = :status";
        $params[':status'] = $statusFilter;
    }

    // Search query (safe parameter binding)
    if ($searchQuery !== '') {
        $where[] = "(i.title LIKE :search OR i.description LIKE :search)";
        $params[':search'] = '%' . $searchQuery . '%';
    }

    $whereSql = implode(' AND ', $where);

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM items i WHERE $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();

    // Fetch paginated items (strictly excluding identifying_features to protect privacy)
    $itemsSql = "SELECT i.id, i.item_type, i.title, i.description, i.incident_date, i.custody_status, i.status, i.image_path,
                        c.name AS category_name, l.campus_zone, l.building, l.specific_area
                 FROM items i
                 JOIN categories c ON i.category_id = c.id
                 JOIN locations l ON i.location_id = l.id
                 WHERE $whereSql
                 ORDER BY i.created_at DESC
                 LIMIT :limit OFFSET :offset";

    $itemsStmt = $pdo->prepare($itemsSql);
    foreach ($params as $key => $val) {
        $itemsStmt->bindValue($key, $val);
    }
    $itemsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $itemsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $itemsStmt->execute();
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPages = (int)ceil($totalCount / $limit);
} catch (Throwable $e) {
    $items = [];
    $totalPages = 1;
}

$extraScripts = ['assets/js/items.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container section" style="padding-top: var(--space-8);">
    <!-- Breadcrumb & Header Title -->
    <div style="margin-bottom: var(--space-6);">
        <nav aria-label="Breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: var(--space-2);">
            <a href="<?= url('index.php') ?>">Home</a> &rsaquo; <span>Campus Items Catalog</span>
        </nav>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--space-4);">
            <div>
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--color-text); margin-bottom: var(--space-1);">
                    Campus Lost &amp; Found Catalog
                </h1>
                <p style="color: var(--color-text-muted); font-size: 1rem;">
                    Browse active items, filter by campus zone, or report a new belonging.
                </p>
            </div>
            <div style="display: flex; gap: var(--space-3);">
                <a href="<?= url('report-lost.php') ?>" class="btn btn-primary btn-sm">Report Lost Item</a>
                <a href="<?= url('report-found.php') ?>" class="btn btn-secondary btn-sm">Report Found Item</a>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls Card -->
    <div class="catalog-filters" id="catalogFilters">
        <form id="filterForm" method="GET" action="<?= url('items.php') ?>">
            <div class="filter-search-row">
                <div class="search-input-wrap">
                    <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" 
                           id="searchInput" 
                           name="search" 
                           value="<?= e($searchQuery) ?>" 
                           placeholder="Live search by item name, model, color, or keywords..." 
                           aria-label="Live search catalog">
                    <button type="button" class="search-clear-btn" id="searchClearBtn" aria-label="Clear search">
                        &times;
                    </button>
                </div>
                <button type="submit" class="btn btn-primary" id="searchSubmitBtn">
                    Search Catalog
                </button>
                <?php if ($searchQuery !== '' || $typeFilter !== 'all' || $categoryFilter > 0 || $locationFilter > 0 || $custodyFilter !== 'all' || $statusFilter !== 'open'): ?>
                    <a href="<?= url('items.php') ?>" class="btn btn-secondary" id="resetFiltersBtn">
                        Clear Filters
                    </a>
                <?php endif; ?>
            </div>

            <!-- Filter Dropdowns Row -->
            <div class="filter-selects-row">
                <!-- Type Filter -->
                <div>
                    <label for="typeFilter" class="form-label" style="font-size: 0.75rem;">Report Type</label>
                    <select id="typeFilter" name="type" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Items (Lost &amp; Found)</option>
                        <option value="lost" <?= $typeFilter === 'lost' ? 'selected' : '' ?>>Lost Only</option>
                        <option value="found" <?= $typeFilter === 'found' ? 'selected' : '' ?>>Found Only</option>
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label for="categoryFilter" class="form-label" style="font-size: 0.75rem;">Category</label>
                    <select id="categoryFilter" name="category" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Location Filter -->
                <div>
                    <label for="locationFilter" class="form-label" style="font-size: 0.75rem;">Campus Building</label>
                    <select id="locationFilter" name="location" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Campus Locations</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= $locationFilter === (int)$loc['id'] ? 'selected' : '' ?>>
                                <?= e($loc['building']) ?> (<?= e($loc['campus_zone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Custody Filter -->
                <div>
                    <label for="custodyFilter" class="form-label" style="font-size: 0.75rem;">Physical Custody</label>
                    <select id="custodyFilter" name="custody" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $custodyFilter === 'all' ? 'selected' : '' ?>>All Custody Types</option>
                        <option value="with_finder" <?= $custodyFilter === 'with_finder' ? 'selected' : '' ?>>Retained with Finder</option>
                        <option value="submitted_to_security" <?= $custodyFilter === 'submitted_to_security' ? 'selected' : '' ?>>Deposited at Security Desk</option>
                        <option value="returned_to_owner" <?= $custodyFilter === 'returned_to_owner' ? 'selected' : '' ?>>Returned to Owner</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="statusFilter" class="form-label" style="font-size: 0.75rem;">Resolution Status</label>
                    <select id="statusFilter" name="status" class="form-select" onchange="this.form.submit()">
                        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Active / Open Only</option>
                        <option value="pending_claim" <?= $statusFilter === 'pending_claim' ? 'selected' : '' ?>>Claim Pending Review</option>
                        <option value="claimed" <?= $statusFilter === 'claimed' ? 'selected' : '' ?>>Claimed &amp; Reunited</option>
                        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed Reports</option>
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Count Bar -->
    <div class="filter-results-header">
        <div class="filter-count" id="resultsCount">
            Showing <strong><?= count($items) ?></strong> of <strong><?= $totalCount ?></strong> reported items
            <?php if ($searchQuery !== ''): ?>
                for query &ldquo;<strong><?= e($searchQuery) ?></strong>&rdquo;
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Grid Container (Coordinated with Member 2 async rendering) -->
    <div id="itemsContainer">
        <?php if (!empty($items)): ?>
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <article class="item-card" data-item-id="<?= $item['id'] ?>">
                        <div class="item-card-media">
                            <div class="item-card-badges">
                                <span class="badge badge-<?= $item['item_type'] === 'lost' ? 'lost' : 'found' ?>">
                                    <?= ucfirst(e($item['item_type'])) ?>
                                </span>
                                <?php if ($item['item_type'] === 'found'): ?>
                                    <?php if ($item['custody_status'] === 'submitted_to_security'): ?>
                                        <span class="badge badge-custody-security" title="Item physically deposited at Campus Security Desk">At Security Desk</span>
                                    <?php elseif ($item['custody_status'] === 'with_finder'): ?>
                                        <span class="badge badge-custody-finder" title="Item kept with finder until verified claim">With Finder</span>
                                    <?php elseif ($item['custody_status'] === 'returned_to_owner'): ?>
                                        <span class="badge badge-custody-returned">Returned</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <img src="<?= !empty($item['image_path']) ? url('uploads/items/' . e($item['image_path'])) : url('assets/images/default-item.svg') ?>" 
                                 alt="<?= e($item['title']) ?>" 
                                 loading="lazy">
                        </div>
                        <div class="item-card-body">
                            <div class="item-card-meta-top">
                                <span class="badge badge-secondary" style="font-size: 0.6875rem;">
                                    <?= e($item['category_name']) ?>
                                </span>
                                <span>&bull;</span>
                                <time datetime="<?= e($item['incident_date']) ?>"><?= format_date($item['incident_date']) ?></time>
                            </div>
                            <h2 class="item-card-title">
                                <a href="<?= url('item-details.php?id=' . $item['id']) ?>">
                                    <?= e($item['title']) ?>
                                </a>
                            </h2>
                            <p class="item-card-desc">
                                <?= e(mb_strimwidth($item['description'], 0, 110, '...')) ?>
                            </p>
                            <div class="item-card-meta-list">
                                <div class="item-card-meta-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <span><?= e($item['building']) ?><?= !empty($item['specific_area']) ? ' &bull; ' . e($item['specific_area']) : '' ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="item-card-footer">
                            <span style="font-size: 0.8125rem; color: var(--color-text-muted); text-transform: capitalize;">
                                Status: <strong><?= e($item['status']) ?></strong>
                            </span>
                            <a href="<?= url('item-details.php?id=' . $item['id']) ?>" class="btn btn-outline btn-sm">
                                View Details &rarr;
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
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
                    <a href="<?= url('items.php') ?>" class="btn btn-secondary btn-sm">Reset All Filters</a>
                    <a href="<?= url('report-lost.php') ?>" class="btn btn-primary btn-sm">Report a Lost Item</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination Controls Container -->
    <?php if ($totalPages > 1): ?>
        <div id="paginationContainer" style="display: flex; justify-content: center; gap: var(--space-2); margin-top: var(--space-8);">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="<?= url('items.php?' . http_build_query(array_merge($_GET, ['page' => $p]))) ?>" 
                   class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"
                   style="min-width: 36px;">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
