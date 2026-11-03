<?php
/**
 * admin/dashboard.php — Professional Admin Dashboard
 * Garage Management System
 *
 * Access Control:
 *  ✔ Admin role required (`requireAdmin()`) — Customers & Staff are blocked & redirected
 *
 * Real MySQL Analytics & Data:
 *  ✔ Total Customers, Total Vehicles, Total Services, Total Bookings
 *  ✔ Pending, Confirmed, In Progress, Completed, Cancelled Bookings Breakdown
 *  ✔ Total Revenue calculated dynamically from completed services
 *  ✔ Recent Bookings table with quick status management links
 *  ✔ Recent Login Activity audit log snippet
 *  ✔ Service Popularity ranking
 */

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Access Control: Strictly Admin Only
requireAdmin();

$pdo = getDBConnection();

try {
    // 1. High-level metric counts
    $totalCustomers = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $totalVehicles  = (int)$pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
    $totalServices  = (int)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
    $totalBookings  = (int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();

    // 2. Booking lifecycle breakdown
    $pendingBookings    = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
    $confirmedBookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn();
    $inProgressBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'in_progress'")->fetchColumn();
    $completedBookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn();
    $cancelledBookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn();

    // 3. Total Calculated Revenue (Service History + Completed Bookings)
    $stmtRev = $pdo->query(
        "SELECT COALESCE(SUM(h.service_cost), 0) +
                COALESCE((SELECT SUM(s.price)
                            FROM bookings b
                            JOIN services s ON b.service_id = s.id
                           WHERE b.status = 'completed'
                             AND b.id NOT IN (SELECT booking_id FROM service_history WHERE booking_id IS NOT NULL)), 0)
           FROM service_history h"
    );
    $totalRevenue = (float)($stmtRev->fetchColumn() ?: 0.00);

    // 4. Recent Bookings (Top 5)
    $stmtRecentBkg = $pdo->query(
        "SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, v.vehicle_number, s.service_name, s.price
           FROM bookings b
           JOIN customers c ON b.customer_id = c.id
           JOIN vehicles v ON b.vehicle_id = v.id
           JOIN services s ON b.service_id = s.id
          ORDER BY b.id DESC
          LIMIT 5"
    );
    $recentBookings = $stmtRecentBkg->fetchAll();

    // 5. Recent Login Activity (Top 5)
    $stmtLogins = $pdo->query(
        "SELECT l.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
           FROM login_logs l
      LEFT JOIN users u ON l.user_id = u.id
          ORDER BY l.login_time DESC
          LIMIT 5"
    );
    $recentLogins = $stmtLogins->fetchAll();

    // 6. Service Popularity Ranking (Top 5)
    $stmtPopServices = $pdo->query(
        "SELECT s.id, s.service_name, s.price, s.status,
                COUNT(b.id) AS total_bookings,
                COALESCE(SUM(s.price), 0) AS total_value
           FROM services s
      LEFT JOIN bookings b ON s.id = b.service_id
       GROUP BY s.id
       ORDER BY total_bookings DESC, s.service_name ASC
          LIMIT 5"
    );
    $popularServices = $stmtPopServices->fetchAll();

} catch (PDOException $e) {
    error_log('[GarageMS] Admin Dashboard Metrics Error: ' . $e->getMessage());
    $totalCustomers = $totalVehicles = $totalServices = $totalBookings = 0;
    $pendingBookings = $confirmedBookings = $inProgressBookings = $completedBookings = $cancelledBookings = 0;
    $totalRevenue = 0.00;
    $recentBookings = $recentLogins = $popularServices = [];
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>

<!-- ════════════════════════════════════════════════════════════
     ADMIN DASHBOARD CONTENT
     ════════════════════════════════════════════════════════════ -->
<div class="admin-content">

    <!-- ── ADMIN PAGE HEADER ──────────────────────────────────── -->
    <div class="admin-page-header">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Admin Overview</li>
                </ol>
            </nav>
            <h1 class="admin-page-title">
                <i class="bi bi-shield-lock-fill text-warning"></i>Admin System Operations
            </h1>
            <span class="text-muted small">Real-time analytical metrics from MySQL database</span>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="<?= BASE_URL ?>admin/logs.php" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-journal-text me-1"></i> Audit Logs
            </a>
            <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-gms-primary btn-sm">
                <i class="bi bi-person-gear me-1"></i> User Management
            </a>
        </div>
    </div>

    <?php showFlash(); ?>

    <!-- ── ROW 1: PRIMARY METRIC CARDS ───────────────────── -->
    <div class="row g-3 mb-4">

        <!-- Total Revenue -->
        <div class="col-sm-6 col-xl-3">
            <div class="gms-stat-card border-left-accent">
                <div class="gms-stat-card-icon green">
                    <i class="bi bi-currency-rupee"></i>
                </div>
                <div class="gms-stat-card-body">
                    <div class="value font-monospace text-success">₹<?= number_format($totalRevenue, 2) ?></div>
                    <div class="label">Total Generated Revenue</div>
                </div>
            </div>
        </div>

        <!-- Total Customers -->
        <div class="col-sm-6 col-xl-3">
            <div class="gms-stat-card">
                <div class="gms-stat-card-icon orange">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="gms-stat-card-body">
                    <div class="value"><?= number_format($totalCustomers) ?></div>
                    <div class="label">Registered Customers</div>
                </div>
            </div>
        </div>

        <!-- Total Vehicles -->
        <div class="col-sm-6 col-xl-3">
            <div class="gms-stat-card">
                <div class="gms-stat-card-icon blue">
                    <i class="bi bi-car-front-fill"></i>
                </div>
                <div class="gms-stat-card-body">
                    <div class="value"><?= number_format($totalVehicles) ?></div>
                    <div class="label">Registered Vehicles</div>
                </div>
            </div>
        </div>

        <!-- Total Services Offered -->
        <div class="col-sm-6 col-xl-3">
            <div class="gms-stat-card">
                <div class="gms-stat-card-icon purple">
                    <i class="bi bi-tools"></i>
                </div>
                <div class="gms-stat-card-body">
                    <div class="value"><?= number_format($totalServices) ?></div>
                    <div class="label">Catalogue Services</div>
                </div>
            </div>
        </div>

    </div><!-- /.row metric cards -->

    <!-- ── ROW 2: BOOKING LIFECYCLE SUMMARY ─────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card gms-panel shadow-sm">
                <div class="card-header bg-transparent border-secondary py-3">
                    <h5 class="card-title mb-0 fw-bold text-light">
                        <i class="bi bi-pie-chart-fill me-2 text-warning"></i>Booking Lifecycle & Progress Summary
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-5 text-center g-3">
                        <div class="col border-end border-secondary border-opacity-25">
                            <div class="h3 fw-bold text-warning font-monospace mb-1"><?= $pendingBookings ?></div>
                            <span class="badge bg-warning text-dark px-2.5 py-1 text-uppercase small">Pending</span>
                        </div>
                        <div class="col border-end border-secondary border-opacity-25">
                            <div class="h3 fw-bold text-primary font-monospace mb-1"><?= $confirmedBookings ?></div>
                            <span class="badge bg-primary text-white px-2.5 py-1 text-uppercase small">Confirmed</span>
                        </div>
                        <div class="col border-end border-secondary border-opacity-25">
                            <div class="h3 fw-bold text-info font-monospace mb-1"><?= $inProgressBookings ?></div>
                            <span class="badge bg-info text-dark px-2.5 py-1 text-uppercase small">In Progress</span>
                        </div>
                        <div class="col border-end border-secondary border-opacity-25">
                            <div class="h3 fw-bold text-success font-monospace mb-1"><?= $completedBookings ?></div>
                            <span class="badge bg-success text-white px-2.5 py-1 text-uppercase small">Completed</span>
                        </div>
                        <div class="col">
                            <div class="h3 fw-bold text-danger font-monospace mb-1"><?= $cancelledBookings ?></div>
                            <span class="badge bg-danger text-white px-2.5 py-1 text-uppercase small">Cancelled</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── ROW 3: RECENT BOOKINGS & POPULAR SERVICES ───── -->
    <div class="row g-4 mb-4">

        <!-- Recent Bookings Table -->
        <div class="col-lg-8">
            <div class="card gms-panel shadow-sm h-100">
                <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="card-title mb-0 fw-bold text-light">
                        <i class="bi bi-calendar-check-fill me-2 text-warning"></i>Recent Booking Appointments
                    </h5>
                    <a href="<?= BASE_URL ?>bookings/list.php" class="btn btn-outline-warning btn-sm">
                        View All Bookings <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0 gms-table">
                            <thead class="table-dark text-muted uppercase small">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Customer</th>
                                    <th>Vehicle</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th class="text-center">Status</th>
                                    <th class="pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentBookings)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No recent bookings recorded.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBookings as $b): ?>
                                        <tr>
                                            <td class="ps-4 font-monospace small text-warning">#BKG-<?= $b['id'] ?></td>
                                            <td class="fw-semibold text-light"><?= e($b['customer_name']) ?></td>
                                            <td class="small font-monospace text-warning"><?= e($b['vehicle_number']) ?></td>
                                            <td class="small text-light"><?= e($b['service_name']) ?></td>
                                            <td class="small font-monospace"><?= formatDate($b['booking_date']) ?></td>
                                            <td class="text-center">
                                                <?php
                                                $statusBadge = match ($b['status']) {
                                                    'pending'     => 'bg-warning text-dark border-warning',
                                                    'confirmed'   => 'bg-primary border-primary text-white',
                                                    'in_progress' => 'bg-info text-dark border-info',
                                                    'completed'   => 'bg-success border-success text-white',
                                                    'cancelled'   => 'bg-danger border-danger text-white',
                                                    default       => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?= $statusBadge ?> px-2.5 py-1 text-uppercase" style="font-size:0.65rem;">
                                                    <?= str_replace('_', ' ', $b['status']) ?>
                                                </span>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <a href="<?= BASE_URL ?>bookings/view.php?id=<?= $b['id'] ?>" class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Popularity Summary -->
        <div class="col-lg-4">
            <div class="card gms-panel shadow-sm h-100">
                <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="card-title mb-0 fw-bold text-light">
                        <i class="bi bi-bar-chart-line-fill me-2 text-warning"></i>Popular Services
                    </h5>
                    <a href="<?= BASE_URL ?>services/list.php" class="btn btn-outline-secondary btn-sm">
                        Manage
                    </a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (empty($popularServices)): ?>
                            <li class="list-group-item bg-transparent text-muted text-center py-4">No services recorded.</li>
                        <?php else: ?>
                            <?php foreach ($popularServices as $svc): ?>
                                <li class="list-group-item bg-transparent border-secondary text-light py-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-bold"><?= e($svc['service_name']) ?></span>
                                        <span class="badge bg-dark-subtle text-warning border border-warning-subtle font-monospace">
                                            <?= (int)$svc['total_bookings'] ?> Booking(s)
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between text-muted small">
                                        <span>Price: ₹<?= number_format($svc['price'], 2) ?></span>
                                        <span class="text-success font-monospace">Val: ₹<?= number_format($svc['total_value'], 2) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

    </div><!-- /.row recent bookings & services -->

    <!-- ── ROW 4: RECENT LOGIN AUDIT LOG ────────────────── -->
    <div class="row g-4 mb-2">
        <div class="col-12">
            <div class="card gms-panel shadow-sm">
                <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="card-title mb-0 fw-bold text-light">
                        <i class="bi bi-journal-text me-2 text-warning"></i>Recent User Login Activity
                    </h5>
                    <a href="<?= BASE_URL ?>admin/logs.php" class="btn btn-outline-warning btn-sm">
                        View Full Audit Trail <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0 gms-table">
                            <thead class="table-dark text-muted uppercase small">
                                <tr>
                                    <th class="ps-4">User</th>
                                    <th>Email</th>
                                    <th>Login Time</th>
                                    <th>Logout Time</th>
                                    <th>IP Address</th>
                                    <th class="pe-4 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentLogins)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No login activity logged yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentLogins as $l): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-light"><?= e($l['user_name'] ?? 'Unknown') ?></td>
                                            <td class="small text-muted"><?= e($l['user_email'] ?? 'N/A') ?></td>
                                            <td class="small font-monospace"><?= formatDateTime($l['login_time']) ?></td>
                                            <td class="small font-monospace">
                                                <?= $l['logout_time'] ? formatDateTime($l['logout_time']) : '<span class="text-success fw-semibold">Active Session</span>' ?>
                                            </td>
                                            <td><code class="small text-warning bg-dark px-2 py-1 rounded border border-secondary"><?= e($l['ip_address'] ?? '127.0.0.1') ?></code></td>
                                            <td class="pe-4 text-center">
                                                <?php if ($l['status'] === 'logged_in'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">logged_in</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-muted border border-secondary px-2.5 py-1 rounded-pill">logged_out</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.admin-content -->

<?php
require_once __DIR__ . '/includes/admin_footer.php';
require_once __DIR__ . '/../includes/footer.php';
?>

