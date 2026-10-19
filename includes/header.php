<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Reusable Header & Navigation Partial
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

init_session();
$currentUser = get_current_user_session();
$flash = flash_get();
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="<?= e(get_csrf_token()) ?>">
    <title><?= e($pageTitle) ?></title>

    <!-- Design Tokens & Stylesheets -->
    <link rel="stylesheet" href="<?= url('assets/css/variables.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/responsive.css') ?>">
</head>
<body>
    <!-- Skip to Main Content Accessibility Link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Site Header & Global Navigation -->
    <header class="site-header">
        <div class="container">
            <nav class="navbar" aria-label="Main Navigation">
                <a href="<?= url('index.php') ?>" class="navbar-brand">
                    <img src="<?= url('assets/images/logo.svg') ?>" alt="SCLFS Logo" width="36" height="36">
                    <div class="brand-text">
                        <span class="brand-title"><?= e(APP_SHORT_NAME) ?></span>
                        <span class="brand-subtitle">Campus Recovery Portal</span>
                    </div>
                </a>

                <!-- Mobile Hamburger Toggle -->
                <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="navbarMenu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <!-- Navigation Links -->
                <ul class="navbar-menu" id="navbarMenu">
                    <li>
                        <a href="<?= url('index.php') ?>" class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>">
                            Home
                        </a>
                    </li>
                    <li>
                        <a href="<?= url('items.php') ?>" class="nav-link <?= $activeNav === 'items' ? 'active' : '' ?>">
                            Browse Items
                        </a>
                    </li>
                    <li>
                        <a href="<?= url('report-lost.php') ?>" class="nav-link <?= $activeNav === 'report-lost' ? 'active' : '' ?>">
                            Report Lost
                        </a>
                    </li>
                    <li>
                        <a href="<?= url('report-found.php') ?>" class="nav-link <?= $activeNav === 'report-found' ? 'active' : '' ?>">
                            Report Found
                        </a>
                    </li>

                    <?php if ($currentUser): ?>
                        <li>
                            <a href="<?= url('dashboard.php') ?>" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="<?= url('claims.php') ?>" class="nav-link <?= $activeNav === 'claims' ? 'active' : '' ?>">
                                Claims
                            </a>
                        </li>
                        <li>
                            <a href="<?= url('notifications.php') ?>" class="nav-link <?= $activeNav === 'notifications' ? 'active' : '' ?>">
                                Notifications
                            </a>
                        </li>
                        <?php if (is_admin()): ?>
                            <li>
                                <a href="<?= url('admin/index.php') ?>" class="nav-link <?= $activeNav === 'admin' ? 'active' : '' ?>">
                                    Admin Desk
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <li>
                        <a href="<?= url('test_db.php') ?>" class="nav-link <?= $activeNav === 'test-db' ? 'active' : '' ?>">
                            Status
                        </a>
                    </li>

                    <!-- Mobile-Only Session Actions Container -->
                    <li class="navbar-actions mobile-visible">
                        <?php if ($currentUser): ?>
                            <div class="user-greeting" style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-2);">
                                Hello, <strong><?= e($currentUser['full_name']) ?></strong>
                                <?php if (is_admin()): ?>
                                    <span class="badge badge-warning">Admin</span>
                                <?php endif; ?>
                            </div>
                            <a href="<?= url('api/auth/logout.php') ?>" class="btn btn-secondary btn-sm">Log Out</a>
                        <?php else: ?>
                            <a href="<?= url('login.php') ?>" class="btn btn-secondary btn-sm" style="margin-bottom: var(--space-2);">Log In</a>
                            <a href="<?= url('register.php') ?>" class="btn btn-primary btn-sm">Register</a>
                        <?php endif; ?>
                    </li>
                </ul>

                <!-- Desktop User Session Actions -->
                <div class="navbar-actions">
                    <?php if ($currentUser): ?>
                        <div class="user-greeting" style="font-size: 0.875rem; color: var(--color-text-muted);">
                            Hello, <strong><?= e($currentUser['full_name']) ?></strong>
                            <?php if (is_admin()): ?>
                                <span class="badge badge-warning">Admin</span>
                            <?php endif; ?>
                        </div>
                        <a href="<?= url('api/auth/logout.php') ?>" class="btn btn-secondary btn-sm">Log Out</a>
                    <?php else: ?>
                        <a href="<?= url('login.php') ?>" class="btn btn-secondary btn-sm">Log In</a>
                        <a href="<?= url('register.php') ?>" class="btn btn-primary btn-sm">Register</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <!-- Flash Notifications Container -->
    <?php if ($flash): ?>
        <div class="container" style="margin-top: var(--space-4);">
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible" role="alert">
                <span><?= e($flash['message']) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main id="main-content">
