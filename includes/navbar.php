<?php
/**
 * includes/navbar.php
 * Garage Management System
 *
 * Custom sticky navbar — becomes opaque on scroll via script.js.
 * Renders different links based on login status and role.
 * No Bootstrap navbar classes used — fully custom for design control.
 */
?>

<!-- ═══════════════════════════════════════════════════════════
     NAVBAR
     ═══════════════════════════════════════════════════════════ -->
<header id="gmsNavbar" class="gms-navbar" role="banner">
    <div class="container-fluid px-3 px-xl-4 d-flex align-items-center justify-content-between">

        <!-- ── Brand ──────────────────────────────────────── -->
        <a href="<?= BASE_URL ?>index.php" class="gms-brand me-2 flex-shrink-0" aria-label="GarageMS Home">
            <div class="gms-brand-icon" aria-hidden="true">
                <i class="bi bi-wrench-adjustable"></i>
            </div>
            <div class="gms-brand-text d-none d-sm-block">
                <span class="gms-brand-name">GarageMS</span>
                <span class="gms-brand-sub">Service Center</span>
            </div>
        </a>

        <!-- ── Desktop Nav Links ──────────────────────────── -->
        <nav class="gms-desktop-nav d-none d-lg-block" aria-label="Primary navigation">
            <ul class="gms-nav-links" role="list">
                <?php if (!isLoggedIn()): ?>
                    <li><a href="<?= BASE_URL ?>index.php"       class="gms-nav-link">Home</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#services" class="gms-nav-link">Services</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#about"   class="gms-nav-link">About</a></li>
                    <li><a href="<?= BASE_URL ?>index.php#contact" class="gms-nav-link">Contact</a></li>
                <?php else: ?>
                    <?php 
                    $role = currentUserRole(); 
                    $currentUri = $_SERVER['REQUEST_URI'] ?? '';
                    $isActive = function(string $path) use ($currentUri): string {
                        return str_contains($currentUri, $path) ? ' active' : '';
                    };
                    ?>
                    <?php if ($role === 'customer'): ?>
                        <li><a href="<?= BASE_URL ?>customer/dashboard.php" class="gms-nav-link<?= $isActive('customer/dashboard.php') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                        <li><a href="<?= BASE_URL ?>vehicles/list.php" class="gms-nav-link<?= $isActive('vehicles/') ?>"><i class="bi bi-car-front me-1"></i>My Vehicles</a></li>
                        <li><a href="<?= BASE_URL ?>bookings/create.php" class="gms-nav-link<?= $isActive('bookings/create.php') ?>"><i class="bi bi-calendar-plus me-1"></i>Book Service</a></li>
                        <li><a href="<?= BASE_URL ?>bookings/list.php" class="gms-nav-link<?= $isActive('bookings/list.php') || $isActive('bookings/view.php') ? ' active' : '' ?>"><i class="bi bi-calendar-check me-1"></i>My Bookings</a></li>
                        <li><a href="<?= BASE_URL ?>bookings/history.php" class="gms-nav-link<?= $isActive('bookings/history.php') ?>"><i class="bi bi-clock-history me-1"></i>Service History</a></li>
                        <li><a href="<?= BASE_URL ?>customer/activity.php" class="gms-nav-link<?= $isActive('customer/activity.php') ?>"><i class="bi bi-activity me-1"></i>Activity</a></li>
                    <?php elseif ($role === 'staff' || $role === 'admin'): ?>
                        <li><a href="<?= BASE_URL ?>staff/dashboard.php" class="gms-nav-link<?= $isActive('staff/dashboard.php') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                        <li><a href="<?= BASE_URL ?>staff/service-requests.php" class="gms-nav-link<?= $isActive('staff/service-requests.php') ?>"><i class="bi bi-bell-fill me-1"></i>Requests</a></li>
                        <li><a href="<?= BASE_URL ?>bookings/list.php" class="gms-nav-link<?= $isActive('bookings/list.php') || $isActive('bookings/view.php') || $isActive('bookings/update-status.php') ? ' active' : '' ?>"><i class="bi bi-calendar-check me-1"></i>Bookings</a></li>
                        <li><a href="<?= BASE_URL ?>customers/list.php" class="gms-nav-link<?= $isActive('customers/') ?>"><i class="bi bi-people me-1"></i>Customers</a></li>
                        <li><a href="<?= BASE_URL ?>vehicles/list.php" class="gms-nav-link<?= $isActive('vehicles/') ?>"><i class="bi bi-car-front me-1"></i>Vehicles</a></li>
                        <li><a href="<?= BASE_URL ?>services/list.php" class="gms-nav-link<?= $isActive('services/') ?>"><i class="bi bi-wrench me-1"></i>Services</a></li>
                        <li><a href="<?= BASE_URL ?>bookings/history.php" class="gms-nav-link<?= $isActive('bookings/history.php') ?>"><i class="bi bi-clock-history me-1"></i>History</a></li>
                        <li><a href="<?= BASE_URL ?>staff/activity-logs.php" class="gms-nav-link<?= $isActive('staff/activity-logs.php') ?>"><i class="bi bi-activity me-1"></i>Activity</a></li>
                        <?php if ($role === 'admin'): ?>
                            <li><a href="<?= BASE_URL ?>admin/" class="gms-nav-link<?= $isActive('/admin/') ?>"><i class="bi bi-gear-fill me-1 text-warning"></i>Admin</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- ── Desktop CTA & User Profile (Never clipped) ── -->
        <div class="gms-nav-cta flex-shrink-0 d-flex align-items-center gap-2">
            <?php if (isLoggedIn()): ?>
                <div class="d-none d-md-flex align-items-center gap-2 px-2.5 py-1 rounded border border-secondary" style="background:rgba(255,255,255,0.04);">
                    <div class="gms-avatar-xs" style="width:28px;height:28px;border-radius:50%;background:var(--grad-accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:0.8rem;">
                        <?= strtoupper(mb_substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="d-flex flex-column" style="line-height:1.1;">
                        <span class="text-white fw-semibold" style="font-size:0.82rem;max-width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= e($_SESSION['user_name'] ?? 'User') ?>
                        </span>
                        <span class="badge <?= currentUserRole() === 'customer' ? 'bg-warning text-dark' : 'bg-primary' ?> p-0 px-1" style="font-size:0.6rem;text-transform:uppercase;width:fit-content;">
                            <?= e(currentUserRole() ?? '') ?>
                        </span>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>logout.php?token=<?= urlencode(csrfToken()) ?>" class="btn btn-outline-danger btn-sm px-2.5 py-1 fw-bold" title="Logout" style="font-size:0.8rem;">
                    <i class="bi bi-box-arrow-right"></i> <span class="d-none d-xl-inline">Logout</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>login.php"    class="btn-gms btn-gms-outline btn-gms-sm">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </a>
                <a href="<?= BASE_URL ?>register.php" class="btn-gms btn-gms-primary btn-gms-sm">
                    <i class="bi bi-person-plus"></i> Register
                </a>
            <?php endif; ?>

            <!-- ── Mobile Hamburger ───────────────────────────── -->
            <button
                id="gmsHamburger"
                class="gms-hamburger d-lg-none ms-1"
                type="button"
                aria-label="Open navigation menu"
                aria-expanded="false"
                aria-controls="gmsMobileMenu"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

    </div><!-- /.container-fluid -->
</header>

<!-- ── Mobile Slide-Down Menu ─────────────────────────────── -->
<nav id="gmsMobileMenu" class="gms-mobile-menu" aria-label="Mobile navigation">
    <?php if (!isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>index.php">Home</a>
        <a href="<?= BASE_URL ?>index.php#services">Services</a>
        <a href="<?= BASE_URL ?>index.php#about">About</a>
        <a href="<?= BASE_URL ?>index.php#contact">Contact</a>
    <?php else: ?>
        <?php $role = currentUserRole(); ?>
        <?php if ($role === 'customer'): ?>
            <a href="<?= BASE_URL ?>customer/dashboard.php" class="<?= $isActive('customer/dashboard.php') ?>"><i class="bi bi-speedometer2 me-2"></i>My Dashboard</a>
            <a href="<?= BASE_URL ?>vehicles/list.php" class="<?= $isActive('vehicles/') ?>"><i class="bi bi-car-front me-2"></i>My Vehicles</a>
            <a href="<?= BASE_URL ?>bookings/create.php" class="<?= $isActive('bookings/create.php') ?>"><i class="bi bi-calendar-plus me-2 text-success"></i>Book Service</a>
            <a href="<?= BASE_URL ?>bookings/list.php" class="<?= $isActive('bookings/list.php') || $isActive('bookings/view.php') ? ' active' : '' ?>"><i class="bi bi-calendar-check me-2"></i>My Bookings</a>
            <a href="<?= BASE_URL ?>bookings/history.php" class="<?= $isActive('bookings/history.php') ?>"><i class="bi bi-clock-history me-2"></i>Service History</a>
            <a href="<?= BASE_URL ?>customer/activity.php" class="<?= $isActive('customer/activity.php') ?>"><i class="bi bi-activity me-2"></i>Activity</a>
        <?php elseif ($role === 'staff' || $role === 'admin'): ?>
            <a href="<?= BASE_URL ?>staff/dashboard.php" class="<?= $isActive('staff/dashboard.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= BASE_URL ?>staff/service-requests.php" class="<?= $isActive('staff/service-requests.php') ?>"><i class="bi bi-bell-fill me-2"></i>Service Requests</a>
            <a href="<?= BASE_URL ?>bookings/list.php" class="<?= $isActive('bookings/list.php') || $isActive('bookings/view.php') || $isActive('bookings/update-status.php') ? ' active' : '' ?>"><i class="bi bi-calendar-check me-2"></i>Bookings</a>
            <a href="<?= BASE_URL ?>customers/list.php" class="<?= $isActive('customers/') ?>"><i class="bi bi-people me-2"></i>Customers</a>
            <a href="<?= BASE_URL ?>vehicles/list.php" class="<?= $isActive('vehicles/') ?>"><i class="bi bi-car-front me-2"></i>Vehicles</a>
            <a href="<?= BASE_URL ?>services/list.php" class="<?= $isActive('services/') ?>"><i class="bi bi-wrench me-2"></i>Services</a>
            <a href="<?= BASE_URL ?>bookings/history.php" class="<?= $isActive('bookings/history.php') ?>"><i class="bi bi-clock-history me-2"></i>History</a>
            <a href="<?= BASE_URL ?>staff/activity-logs.php" class="<?= $isActive('staff/activity-logs.php') ?>"><i class="bi bi-activity me-2"></i>Activity</a>
            <?php if ($role === 'admin'): ?>
                <a href="<?= BASE_URL ?>admin/" class="<?= $isActive('/admin/') ?>"><i class="bi bi-gear-fill me-2 text-warning"></i>Admin Panel</a>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="mobile-cta">
        <?php if (isLoggedIn()): ?>
            <a href="<?= BASE_URL ?>logout.php?token=<?= urlencode(csrfToken()) ?>" class="btn-gms btn-gms-outline w-100">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>login.php"    class="btn-gms btn-gms-outline" style="flex:1">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </a>
            <a href="<?= BASE_URL ?>register.php" class="btn-gms btn-gms-primary" style="flex:1">
                <i class="bi bi-person-plus"></i> Register
            </a>
        <?php endif; ?>
    </div>
</nav>
