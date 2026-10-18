<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Public Homepage
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home - ' . APP_NAME;
$activeNav = 'home';

// Query quick live metrics safely (falls back to defaults if database is not yet seeded)
$stats = [
    'total_items'  => 0,
    'active_lost'  => 0,
    'active_found' => 0,
    'claimed'      => 0
];

try {
    $pdo = get_db_connection();

    // Total reported items
    $stats['total_items'] = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();

    // Active open lost items
    $stats['active_lost'] = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE item_type = 'lost' AND status = 'open'")->fetchColumn();

    // Active open found items
    $stats['active_found'] = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE item_type = 'found' AND status = 'open'")->fetchColumn();

    // Reunited / Claimed items
    $stats['claimed'] = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE status = 'claimed'")->fetchColumn();
} catch (Throwable $e) {
    // Database connection or table not yet ready; fallback to default 0 counts
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-pill">
                <span class="badge badge-primary">Campus Recovery Portal &bull; Foundation Ready</span>
            </div>
            <h1 class="hero-title">
                Reuniting Students with Belongings through <span class="highlight">Smart Matching</span>
            </h1>
            <p class="hero-lead">
                A centralized, transparent, and secure platform for our college campus. 
                Report missing items, track found belongings, verify ownership safely, and claim items at the Security Desk.
            </p>
            <div class="hero-actions">
                <a href="<?= url('items.php') ?>" class="btn btn-primary btn-lg">Browse Campus Items</a>
                <a href="<?= url('report-lost.php') ?>" class="btn btn-secondary btn-lg">Report Lost Item</a>
                <a href="<?= url('report-found.php') ?>" class="btn btn-outline btn-lg">Report Found Item</a>
            </div>
        </div>
    </div>
</section>

<!-- Stats Bar (Elevated Overlap) -->
<section class="stats-bar">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= e($stats['total_items']) ?></div>
                <div class="stat-label">Total Reported</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--color-danger);"><?= e($stats['active_lost']) ?></div>
                <div class="stat-label">Active Lost</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--color-info);"><?= e($stats['active_found']) ?></div>
                <div class="stat-label">Active Found</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--color-success);"><?= e($stats['claimed']) ?></div>
                <div class="stat-label">Reunited with Owners</div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="section">
    <div class="container">
        <div class="section-title-wrap">
            <span class="badge badge-info" style="margin-bottom: 8px;">Structured Workflow</span>
            <h2 class="section-title">How Our Campus System Works</h2>
            <p class="section-subtitle">
                Designed to minimize false claims, protect confidential item details, and speed up property recovery.
            </p>
        </div>

        <div class="steps-grid">
            <!-- Step 1 -->
            <div class="step-card">
                <div class="step-number">1</div>
                <h3 class="step-title">Report an Item</h3>
                <p class="step-desc">
                    Submit lost or found reports with category, building location, and date. Secret identifying details on found items remain hidden from the public.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="step-card">
                <div class="step-number">2</div>
                <h3 class="step-title">Smart Heuristic Match</h3>
                <p class="step-desc">
                    Our engine evaluates category, location, date proximity, and keyword overlap to compute similarity scores and alert both parties.
                </p>
            </div>

            <!-- Step 3 -->
            <div class="step-card">
                <div class="step-number">3</div>
                <h3 class="step-title">Admin Review</h3>
                <p class="step-desc">
                    Claimants provide proof of ownership. Campus security administrators compare proof against secret markings to prevent false claims.
                </p>
            </div>

            <!-- Step 4 -->
            <div class="step-card">
                <div class="step-number">4</div>
                <h3 class="step-title">Physical Handover</h3>
                <p class="step-desc">
                    Claimant visits the Security Desk with student ID. An atomic handover transaction updates custody to returned and closes reports.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Core Pillars Section -->
<section class="section" style="background-color: var(--color-surface); border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border);">
    <div class="container">
        <div class="section-title-wrap">
            <span class="badge badge-primary" style="margin-bottom: 8px;">Key Features</span>
            <h2 class="section-title">Built for Campus Accountability</h2>
            <p class="section-subtitle">A balanced system combining ease of use for students with strict security controls for administration.</p>
        </div>

        <div class="features-grid">
            <!-- Feature 1 -->
            <div class="feature-card">
                <div class="feature-icon-box">&#128269;</div>
                <h3 class="feature-title">Smart Heuristic Matching</h3>
                <p class="feature-desc">
                    Multi-factor scoring algorithm weights category (30%), location proximity (25%), date window (20%), and keyword similarity (25%) on-demand.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="feature-card">
                <div class="feature-icon-box">&#128274;</div>
                <h3 class="feature-title">Fraud Prevention & Privacy</h3>
                <p class="feature-desc">
                    Secret distinguishing marks are never exposed on public catalogs. Only authorized administrators can review proof descriptions and receipts.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="feature-card">
                <div class="feature-icon-box">&#128193;</div>
                <h3 class="feature-title">Explicit Custody Tracking</h3>
                <p class="feature-desc">
                    Full visibility into whether a found item is retained with the finder or safely deposited at the central campus security desk.
                </p>
            </div>

            <!-- Feature 4 -->
            <div class="feature-card">
                <div class="feature-icon-box">&#128276;</div>
                <h3 class="feature-title">In-App Notifications</h3>
                <p class="feature-desc">
                    Real-time alerts keep users informed when a potential match is found, when a claim is reviewed, and when an item is ready for collection.
                </p>
            </div>

            <!-- Feature 5 -->
            <div class="feature-card">
                <div class="feature-icon-box">&#128221;</div>
                <h3 class="feature-title">Atomic Handover & Audit Trail</h3>
                <p class="feature-desc">
                    Single-transaction physical handovers prevent race conditions, automatically resolve linked lost reports, and generate audit logs.
                </p>
            </div>

            <!-- Feature 6 -->
            <div class="feature-card">
                <div class="feature-icon-box">&#9881;</div>
                <h3 class="feature-title">XAMPP Optimized</h3>
                <p class="feature-desc">
                    Engineered with standard plain PHP (PDO) and MySQL, running natively on local XAMPP without complex package managers or external services.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Team Foundation Notice Section -->
<section class="section">
    <div class="container">
        <div class="card" style="border-left: 4px solid var(--color-primary);">
            <div class="card-header">
                <div>
                    <h3 style="font-size: 1.125rem; font-weight: 700;">Shared Foundation Status</h3>
                    <p style="font-size: 0.875rem; color: var(--color-text-muted);">
                        The core shared architecture, database schema, design system, and configuration are initialized.
                    </p>
                </div>
                <a href="<?= url('test_db.php') ?>" class="btn btn-primary btn-sm">Test Database Connection</a>
            </div>
            <div class="card-body">
                <p style="font-size: 0.9375rem; color: var(--color-text); margin-bottom: var(--space-4);">
                    Team members can now branch from <code>develop</code> and implement their assigned modules as designated in 
                    <strong><a href="<?= url('PROJECT_SPEC.md') ?>">PROJECT_SPEC.md</a></strong>:
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); font-size: 0.875rem;">
                    <div style="background:#f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--color-border);">
                        <strong>Member 1:</strong> UI/UX Design System &amp; Responsive Views
                    </div>
                    <div style="background:#f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--color-border);">
                        <strong>Member 2:</strong> Frontend JS &amp; Client Interactivity
                    </div>
                    <div style="background:#f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--color-border);">
                        <strong>Member 3:</strong> Auth, Sessions &amp; Core Item CRUD
                    </div>
                    <div style="background:#f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--color-border);">
                        <strong>Member 4:</strong> Database &amp; Smart Matching Engine
                    </div>
                    <div style="background:#f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--color-border);">
                        <strong>Member 5:</strong> Claims, Handover Desk &amp; Notifications
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
