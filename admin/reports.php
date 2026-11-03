<?php
/**
 * admin/reports.php — Reports & Analytics
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 * Uses real SQL aggregation: COUNT(), GROUP BY, ORDER BY, JOIN
 */
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo = getDBConnection();

try {
    // 1. Booking summary
    $totalBookings     = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $pendingBookings   = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
    $confirmedBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetchColumn();
    $inProgressBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='in_progress'")->fetchColumn();
    $completedBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
    $cancelledBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetchColumn();

    // 2. Revenue
    $totalRevenue = (float)($pdo->query("SELECT COALESCE(SUM(service_cost),0) FROM service_history")->fetchColumn() ?: 0);

    // 3. Most booked services (top 8)
    $topServices = $pdo->query(
        "SELECT s.service_name, COUNT(b.id) AS total_bookings, COALESCE(SUM(s.price),0) AS total_value
           FROM services s
      LEFT JOIN bookings b ON b.service_id = s.id
          GROUP BY s.id
          ORDER BY total_bookings DESC
          LIMIT 8"
    )->fetchAll();

    $maxServiceBookings = !empty($topServices) ? max(array_column($topServices,'total_bookings')) : 1;

    // 4. Customers by address/city (extracted from address or booking city)
    $customersByCity = $pdo->query(
        "SELECT COALESCE(NULLIF(TRIM(b.city),''), 'Unknown') AS city,
                COUNT(DISTINCT b.customer_id) AS customer_count,
                COUNT(b.id) AS booking_count
           FROM bookings b
          GROUP BY city
          HAVING city IS NOT NULL AND city != ''
          ORDER BY booking_count DESC
          LIMIT 10"
    )->fetchAll();

    $maxCityBookings = !empty($customersByCity) ? max(array_column($customersByCity,'booking_count')) : 1;

    // 5. Monthly booking trend (last 6 months)
    $bookingTrend = $pdo->query(
        "SELECT DATE_FORMAT(booking_date, '%b %Y') AS month_label,
                DATE_FORMAT(booking_date, '%Y-%m') AS month_key,
                COUNT(*) AS total
           FROM bookings
          WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY month_key, month_label
          ORDER BY month_key ASC"
    )->fetchAll();

    $maxTrend = !empty($bookingTrend) ? max(array_column($bookingTrend,'total')) : 1;

    // 6. System overview
    $totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $totalStaff     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
    $totalVehicles  = (int)$pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
    $totalServices  = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='active'")->fetchColumn();
    $totalHistory   = (int)$pdo->query("SELECT COUNT(*) FROM service_history")->fetchColumn();
    $totalLoginLogs = (int)$pdo->query("SELECT COUNT(*) FROM login_logs")->fetchColumn();

    // 7. Login activity by role
    $loginByRole = $pdo->query(
        "SELECT u.role, COUNT(l.id) AS total_logins
           FROM login_logs l
           JOIN users u ON l.user_id = u.id
          GROUP BY u.role
          ORDER BY total_logins DESC"
    )->fetchAll();

    // 8. Service history summary
    $historyByMonth = $pdo->query(
        "SELECT DATE_FORMAT(service_date, '%b %Y') AS month_label,
                COUNT(*) AS total, COALESCE(SUM(service_cost),0) AS revenue
           FROM service_history
          WHERE service_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(service_date, '%Y-%m')
          ORDER BY DATE_FORMAT(service_date, '%Y-%m') ASC"
    )->fetchAll();

} catch (PDOException $e) {
    error_log('[GarageMS] Admin reports: ' . $e->getMessage());
    $totalBookings = $pendingBookings = $confirmedBookings = $completedBookings = $cancelledBookings = 0;
    $totalRevenue = 0; $topServices = $customersByCity = $bookingTrend = $loginByRole = $historyByMonth = [];
    $maxServiceBookings = $maxCityBookings = $maxTrend = 1;
    $totalUsers = $totalCustomers = $totalStaff = $totalVehicles = $totalServices = $totalHistory = $totalLoginLogs = 0;
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-bar-chart-line-fill"></i>Reports & Analytics</h1>
            <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Reports</div>
        </div>
        <span class="badge bg-warning text-dark px-3 py-2">
            <i class="bi bi-database me-1"></i>Real-time Database Data
        </span>
    </div>

    <!-- System Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon blue"><i class="bi bi-people-fill"></i></div>
                <div><div class="admin-stat-value"><?= $totalUsers ?></div><div class="admin-stat-label">Total Users</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon orange"><i class="bi bi-person-lines-fill"></i></div>
                <div><div class="admin-stat-value"><?= $totalCustomers ?></div><div class="admin-stat-label">Customers</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon cyan"><i class="bi bi-car-front-fill"></i></div>
                <div><div class="admin-stat-value"><?= $totalVehicles ?></div><div class="admin-stat-label">Vehicles</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon green"><i class="bi bi-currency-rupee"></i></div>
                <div><div class="admin-stat-value text-success" style="font-size:1.1rem;">₹<?= number_format($totalRevenue,0) ?></div><div class="admin-stat-label">Total Revenue</div></div>
            </div>
        </div>
    </div>

    <!-- Booking Status Report -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="admin-card-header">
                    <h5 class="admin-card-title"><i class="bi bi-pie-chart-fill"></i>Booking Status Summary</h5>
                </div>
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between text-center mb-3">
                        <div><div class="h4 fw-bold text-white"><?= $totalBookings ?></div><div class="text-muted small">Total</div></div>
                        <div><div class="h4 fw-bold text-warning"><?= $pendingBookings ?></div><div class="text-muted small">Pending</div></div>
                        <div><div class="h4 fw-bold text-info"><?= $inProgressBookings ?></div><div class="text-muted small">In Progress</div></div>
                        <div><div class="h4 fw-bold text-success"><?= $completedBookings ?></div><div class="text-muted small">Completed</div></div>
                        <div><div class="h4 fw-bold text-danger"><?= $cancelledBookings ?></div><div class="text-muted small">Cancelled</div></div>
                    </div>
                    <?php
                    $statusData = [
                        ['Completed','completed',$completedBookings,'#22c55e'],
                        ['Pending','pending',$pendingBookings,'#eab308'],
                        ['Cancelled','cancelled',$cancelledBookings,'#ef4444'],
                        ['In Progress','in_progress',$inProgressBookings,'#06b6d4'],
                        ['Confirmed','confirmed',$confirmedBookings,'#3b82f6'],
                    ];
                    $maxBkg = max($totalBookings, 1);
                    foreach ($statusData as [$label,$key,$count,$color]):
                    ?>
                    <div class="admin-bar-item">
                        <div class="admin-bar-label">
                            <span class="name"><?= $label ?></span>
                            <span class="count"><?= $count ?> <?= $totalBookings > 0 ? '(' . round($count/$totalBookings*100) . '%)' : '' ?></span>
                        </div>
                        <div class="admin-bar-track">
                            <div class="admin-bar-fill"
                                 style="width:<?= $totalBookings > 0 ? round($count/$totalBookings*100) : 0 ?>%;background:<?= $color ?>;">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Login Activity by Role -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="admin-card-header">
                    <h5 class="admin-card-title"><i class="bi bi-person-check-fill"></i>Login Activity</h5>
                </div>
                <div class="admin-card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-4 text-center">
                            <div class="h4 fw-bold text-white"><?= $totalLoginLogs ?></div>
                            <div class="text-muted small">Total Sessions</div>
                        </div>
                        <?php foreach ($loginByRole as $lr): ?>
                        <div class="col-4 text-center">
                            <div class="h4 fw-bold" style="color:<?= $lr['role']==='admin'?'#f97316':($lr['role']==='staff'?'#3b82f6':'#a855f7') ?>;">
                                <?= $lr['total_logins'] ?>
                            </div>
                            <div class="text-muted small"><?= ucfirst($lr['role']) ?> Logins</div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Service History by Month -->
                    <h6 class="text-muted small text-uppercase fw-bold mt-3 mb-2">Service History (Last 6 Months)</h6>
                    <?php if (empty($historyByMonth)): ?>
                        <p class="text-muted small">No service history data available.</p>
                    <?php else: ?>
                        <?php $maxHistoryRev = max(array_column($historyByMonth,'revenue') + [1]); ?>
                        <?php foreach ($historyByMonth as $hm): ?>
                        <div class="admin-bar-item">
                            <div class="admin-bar-label">
                                <span class="name"><?= e($hm['month_label']) ?> — <?= $hm['total'] ?> services</span>
                                <span class="count">₹<?= number_format((float)$hm['revenue'],0) ?></span>
                            </div>
                            <div class="admin-bar-track">
                                <div class="admin-bar-fill" style="width:<?= $maxHistoryRev > 0 ? min(100, round($hm['revenue']/$maxHistoryRev*100)) : 0 ?>%;background:#22c55e;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Most Booked Services -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="admin-card-header">
                    <h5 class="admin-card-title"><i class="bi bi-bar-chart-fill"></i>Most Booked Services</h5>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($topServices)): ?>
                        <div class="admin-empty"><i class="bi bi-tools"></i><p>No service data available.</p></div>
                    <?php else: ?>
                        <?php foreach ($topServices as $svc): ?>
                        <div class="admin-bar-item">
                            <div class="admin-bar-label">
                                <span class="name"><?= e($svc['service_name']) ?></span>
                                <span class="count"><?= $svc['total_bookings'] ?> bookings</span>
                            </div>
                            <div class="admin-bar-track">
                                <div class="admin-bar-fill" style="width:<?= $maxServiceBookings > 0 ? round($svc['total_bookings']/$maxServiceBookings*100) : 0 ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bookings by City -->
        <div class="col-lg-6">
            <div class="admin-card h-100">
                <div class="admin-card-header">
                    <h5 class="admin-card-title"><i class="bi bi-geo-alt-fill"></i>Bookings by City</h5>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($customersByCity)): ?>
                        <div class="admin-empty"><i class="bi bi-geo-alt"></i><p>No city data available. Bookings without city info will appear when city is recorded.</p></div>
                    <?php else: ?>
                        <?php foreach ($customersByCity as $city): ?>
                        <div class="admin-bar-item">
                            <div class="admin-bar-label">
                                <span class="name"><i class="bi bi-geo-alt-fill me-1 text-warning" style="font-size:0.75rem;"></i><?= e($city['city']) ?></span>
                                <span class="count"><?= $city['booking_count'] ?> bookings / <?= $city['customer_count'] ?> customers</span>
                            </div>
                            <div class="admin-bar-track">
                                <div class="admin-bar-fill" style="width:<?= $maxCityBookings > 0 ? round($city['booking_count']/$maxCityBookings*100) : 0 ?>%;background:#a855f7;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Booking Trend -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h5 class="admin-card-title"><i class="bi bi-graph-up-arrow"></i>Monthly Booking Trend (Last 6 Months)</h5>
        </div>
        <div class="admin-card-body">
            <?php if (empty($bookingTrend)): ?>
                <div class="admin-empty"><i class="bi bi-graph-up"></i><p>No booking trend data available yet.</p></div>
            <?php else: ?>
                <div class="row g-3 align-items-end" style="min-height:120px;">
                    <?php foreach ($bookingTrend as $mt): ?>
                    <div class="col text-center">
                        <div class="text-warning fw-bold mb-1" style="font-size:1.1rem;"><?= $mt['total'] ?></div>
                        <div style="background:linear-gradient(0deg,#f97316,#fb923c);border-radius:4px 4px 0 0;width:100%;height:<?= $maxTrend > 0 ? max(12,round($mt['total']/$maxTrend*80)) : 12 ?>px;"></div>
                        <div class="text-muted small mt-1" style="font-size:0.72rem;"><?= e($mt['month_label']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
require_once __DIR__ . '/../includes/footer.php';
?>
