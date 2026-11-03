<?php
/**
 * admin/includes/admin_nav.php — Admin Sidebar Navigation
 * Garage Management System
 *
 * Included at the top of every admin page (after the main navbar).
 * Provides a vertical admin sidebar with all navigation links.
 * Active state detected via REQUEST_URI.
 */

$adminCurrentUri = $_SERVER['REQUEST_URI'] ?? '';
$adminActive = function(string $path) use ($adminCurrentUri): string {
    return str_contains($adminCurrentUri, $path) ? 'active' : '';
};
?>
<!-- ════════════════════════════════════════════════════════════
     ADMIN LAYOUT WRAPPER
     ════════════════════════════════════════════════════════════ -->
<div class="admin-layout">

    <!-- ── Admin Sidebar ────────────────────────────────────── -->
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Sidebar Header -->
        <div class="admin-sidebar-header">
            <div class="admin-sidebar-brand">
                <div class="admin-brand-icon">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div class="admin-brand-text">
                    <span class="admin-brand-name">Admin Panel</span>
                    <span class="admin-brand-sub">GarageMS Control</span>
                </div>
            </div>
            <!-- Mobile close button -->
            <button class="admin-sidebar-close d-lg-none" id="adminSidebarClose" aria-label="Close sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Admin User Info -->
        <div class="admin-user-info">
            <div class="admin-user-avatar">
                <?= strtoupper(mb_substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div>
                <div class="admin-user-name"><?= e($_SESSION['user_name'] ?? 'Administrator') ?></div>
                <div class="admin-user-role"><i class="bi bi-shield-check me-1"></i>Administrator</div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="admin-nav">
            <div class="admin-nav-label">Main</div>
            <ul class="admin-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>admin/dashboard.php"
                       class="admin-nav-link <?= $adminActive('admin/dashboard') ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>

            <div class="admin-nav-label">Management</div>
            <ul class="admin-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>admin/users.php"
                       class="admin-nav-link <?= $adminActive('admin/users') ?>">
                        <i class="bi bi-people-fill"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/customers.php"
                       class="admin-nav-link <?= $adminActive('admin/customers') ?>">
                        <i class="bi bi-person-lines-fill"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/mechanics.php"
                       class="admin-nav-link <?= $adminActive('admin/mechanics') ?>">
                        <i class="bi bi-wrench-adjustable-circle-fill"></i>
                        <span>Mechanics / Staff</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/vehicles.php"
                       class="admin-nav-link <?= $adminActive('admin/vehicles') ?>">
                        <i class="bi bi-car-front-fill"></i>
                        <span>Vehicles</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/services.php"
                       class="admin-nav-link <?= $adminActive('admin/services') ?>">
                        <i class="bi bi-tools"></i>
                        <span>Services</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/bookings.php"
                       class="admin-nav-link <?= $adminActive('admin/bookings') ?>">
                        <i class="bi bi-calendar-check-fill"></i>
                        <span>Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/service-history.php"
                       class="admin-nav-link <?= $adminActive('admin/service-history') ?>">
                        <i class="bi bi-clock-history"></i>
                        <span>Service History</span>
                    </a>
                </li>
            </ul>

            <div class="admin-nav-label">Audit</div>
            <ul class="admin-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>admin/logs.php"
                       class="admin-nav-link <?= $adminActive('admin/logs') ?>">
                        <i class="bi bi-journal-text"></i>
                        <span>Login Logs</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/activity-logs.php"
                       class="admin-nav-link <?= $adminActive('admin/activity-logs') ?>">
                        <i class="bi bi-activity"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/reports.php"
                       class="admin-nav-link <?= $adminActive('admin/reports') ?>">
                        <i class="bi bi-bar-chart-line-fill"></i>
                        <span>Reports & Analytics</span>
                    </a>
                </li>
            </ul>

            <div class="admin-nav-label">Account</div>
            <ul class="admin-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>admin/profile.php"
                       class="admin-nav-link <?= $adminActive('admin/profile') ?>">
                        <i class="bi bi-person-circle"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>logout.php?token=<?= urlencode(csrfToken()) ?>" class="admin-nav-link text-danger-emphasis">
                        <i class="bi bi-box-arrow-right text-danger"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Sidebar overlay (mobile) -->
    <div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

    <!-- ── Admin Main Content Area ──────────────────────────── -->
    <main class="admin-main" id="adminMain">

        <!-- Mobile topbar -->
        <div class="admin-mobile-topbar d-lg-none">
            <button class="admin-sidebar-toggle" id="adminSidebarToggle" aria-label="Open admin sidebar">
                <i class="bi bi-list"></i>
            </button>
            <span class="fw-bold text-warning">
                <i class="bi bi-shield-lock-fill me-1"></i>Admin Panel
            </span>
            <a href="<?= BASE_URL ?>logout.php?token=<?= urlencode(csrfToken()) ?>" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
