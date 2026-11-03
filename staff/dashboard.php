<?php
/**
 * staff/dashboard.php
 * Garage Management System
 *
 * Dedicated dashboard for staff.
 */
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Enforce staff access
requireStaffOrAdmin();

$pdo = getDBConnection();

// ── Fetch Staff Operational Statistics ────────────────────────
$todayDate = date('Y-m-d');

// New pending service requests
$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$pendingCount = (int)$stmt->fetchColumn();

// Confirmed bookings
$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
$confirmedCount = (int)$stmt->fetchColumn();

// In progress bookings
$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'in_progress'");
$inProgressCount = (int)$stmt->fetchColumn();

// Completed today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'completed' AND (DATE(updated_at) = ? OR booking_date = ?)");
$stmt->execute([$todayDate, $todayDate]);
$completedTodayCount = (int)$stmt->fetchColumn();

// Fetch New Pending Requests (Limit 6)
$stmt = $pdo->query("
    SELECT b.*,
           c.name AS customer_name, c.phone AS customer_phone,
           v.vehicle_number, v.brand, v.model, v.year,
           s.service_name, s.price
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN services s ON b.service_id = s.id
    WHERE b.status = 'pending'
    ORDER BY b.booking_date ASC, b.id ASC
    LIMIT 6
");
$pendingRequests = $stmt->fetchAll();

// Fetch Active In-Progress/Confirmed Services (Limit 6)
$stmt = $pdo->query("
    SELECT b.*,
           c.name AS customer_name, c.phone AS customer_phone,
           v.vehicle_number, v.brand, v.model,
           s.service_name, s.price,
           u.name AS staff_name
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN services s ON b.service_id = s.id
    LEFT JOIN users u ON b.staff_id = u.id
    WHERE b.status IN ('confirmed', 'in_progress')
    ORDER BY b.booking_date ASC, b.id ASC
    LIMIT 6
");
$activeServices = $stmt->fetchAll();

// Fetch Recent Activity Logs (Limit 6)
try {
    $stmtLogs = $pdo->query("
        SELECT al.*, u.name AS user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 6
    ");
    $recentActivities = $stmtLogs->fetchAll();
} catch (PDOException $e) {
    $recentActivities = [];
}

// ── Pull session data into local variables ────────────────────
$userName  = (string)($_SESSION['user_name']  ?? 'Staff Member');
$userRole  = (string)($_SESSION['user_role']  ?? 'staff');
$userEmail = (string)($_SESSION['user_email'] ?? '');

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="mb-1 text-white fw-bold">
                    Good day, <span class="text-warning"><?= e($userName) ?></span> 👋
                </h1>
                <p class="text-light opacity-75 small mb-0">Manage today's vehicle service requests, accept appointments, and track garage operations.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>staff/service-requests.php" class="btn btn-warning fw-bold text-dark position-relative shadow">
                    <i class="bi bi-bell-fill me-1"></i>Service Requests
                    <?php if ($pendingCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $pendingCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>bookings/list.php" class="btn btn-outline-light btn-sm d-flex align-items-center">
                    <i class="bi bi-list-task me-1"></i>All Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <!-- ══════════════════════════════════════════════════════
             OPERATIONAL METRIC CARDS
             ══════════════════════════════════════════════════════ -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <a href="<?= BASE_URL ?>staff/service-requests.php" class="text-decoration-none">
                    <div class="gms-stat-card border-warning">
                        <div class="gms-stat-card-icon orange">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <div class="gms-stat-card-body">
                            <div class="value text-warning"><?= number_format($pendingCount) ?></div>
                            <div class="label">New Requests</div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-xl-3">
                <a href="<?= BASE_URL ?>bookings/list.php?status=confirmed" class="text-decoration-none">
                    <div class="gms-stat-card">
                        <div class="gms-stat-card-icon blue">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="gms-stat-card-body">
                            <div class="value text-info"><?= number_format($confirmedCount) ?></div>
                            <div class="label">Confirmed Services</div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-xl-3">
                <a href="<?= BASE_URL ?>bookings/list.php?status=in_progress" class="text-decoration-none">
                    <div class="gms-stat-card">
                        <div class="gms-stat-card-icon green">
                            <i class="bi bi-tools"></i>
                        </div>
                        <div class="gms-stat-card-body">
                            <div class="value text-success"><?= number_format($inProgressCount) ?></div>
                            <div class="label">In Progress</div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-xl-3">
                <a href="<?= BASE_URL ?>bookings/history.php" class="text-decoration-none">
                    <div class="gms-stat-card">
                        <div class="gms-stat-card-icon purple">
                            <i class="bi bi-patch-check-fill"></i>
                        </div>
                        <div class="gms-stat-card-body">
                            <div class="value text-white"><?= number_format($completedTodayCount) ?></div>
                            <div class="label">Completed Today</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════
             NEW SERVICE REQUESTS SECTION (Prominent Cards)
             ══════════════════════════════════════════════════════ -->
        <div class="card gms-panel border-secondary mb-4 shadow-sm">
            <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-inbox-fill text-warning fs-5"></i>
                    <h5 class="card-title mb-0 fw-bold text-white">
                        New Incoming Service Requests
                    </h5>
                    <span class="badge bg-warning text-dark font-monospace fw-bold ms-1"><?= count($pendingRequests) ?> Pending</span>
                </div>
                <a href="<?= BASE_URL ?>staff/service-requests.php" class="btn btn-sm btn-outline-warning">
                    View Full Queue <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-4">
                <?php if (empty($pendingRequests)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check2-circle fs-1 text-success d-block mb-2 opacity-75"></i>
                        <p class="mb-0">No new service requests waiting for review.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($pendingRequests as $pr): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="p-3 rounded border border-secondary h-100 d-flex flex-column" style="background:var(--clr-dark-4);">
                                    
                                    <!-- Customer & Ref -->
                                    <div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom border-secondary">
                                        <div>
                                            <div class="fw-bold text-white fs-6"><?= e($pr['customer_name']) ?></div>
                                            <div class="text-muted small"><i class="bi bi-telephone text-warning me-1"></i><?= e($pr['customer_phone']) ?></div>
                                        </div>
                                        <span class="badge bg-warning text-dark font-monospace fw-bold">#BKG-<?= (int)$pr['id'] ?></span>
                                    </div>

                                    <!-- Vehicle & Service -->
                                    <div class="mb-2">
                                        <div class="text-white fw-semibold small">🚗 <?= e($pr['brand']) ?> <?= e($pr['model']) ?> (<?= e($pr['year']) ?>)</div>
                                        <div class="font-monospace text-warning small"><?= e($pr['vehicle_number']) ?></div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="text-light small fw-bold">🔧 <?= e($pr['service_name']) ?></div>
                                        <div class="text-muted small">Scheduled: <span class="text-white"><?= formatDate($pr['booking_date']) ?> • <?= e($pr['booking_time'] ?: '10:00 AM') ?></span></div>
                                    </div>

                                    <!-- Address -->
                                    <div class="p-2 rounded mb-3 mt-auto border border-secondary" style="background:rgba(0,0,0,0.3);">
                                        <div class="text-warning small fw-bold"><i class="bi bi-geo-alt-fill me-1"></i>Location:</div>
                                        <div class="text-light small text-truncate">
                                            <?= e($pr['service_address'] ?: 'On record') ?><?= $pr['city'] ? ', ' . e($pr['city']) : '' ?>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="d-flex gap-2">
                                        <a href="<?= BASE_URL ?>bookings/view.php?id=<?= (int)$pr['id'] ?>" class="btn btn-sm btn-outline-info flex-grow-1">
                                            <i class="bi bi-eye me-1"></i>Details
                                        </a>
                                        <form method="POST" action="<?= BASE_URL ?>staff/accept-service.php" class="flex-grow-1">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="booking_id" value="<?= (int)$pr['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success w-100 fw-bold">
                                                <i class="bi bi-check-lg me-1"></i>Accept
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModalDash<?= (int)$pr['id'] ?>" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>

                                </div>
                            </div>

                            <!-- Dash Reject Modal -->
                            <div class="modal fade" id="rejectModalDash<?= (int)$pr['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content gms-panel border-danger">
                                        <div class="modal-header bg-danger bg-opacity-10 border-danger py-3">
                                            <h5 class="modal-title text-danger fw-bold">
                                                <i class="bi bi-exclamation-octagon-fill me-2"></i>Reject Request #<?= (int)$pr['id'] ?>
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="<?= BASE_URL ?>staff/reject-service.php">
                                            <div class="modal-body p-4">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="booking_id" value="<?= (int)$pr['id'] ?>">
                                                <p class="text-light mb-3">Reject request for <strong><?= e($pr['customer_name']) ?></strong> (<?= e($pr['brand']) ?> <?= e($pr['model']) ?>)?</p>
                                                <div class="mb-3">
                                                    <label for="rejReasonDash<?= (int)$pr['id'] ?>" class="form-label text-light small fw-semibold">Reason (Optional)</label>
                                                    <textarea id="rejReasonDash<?= (int)$pr['id'] ?>" name="rejection_reason" class="form-control gms-input" rows="2" placeholder="e.g. Schedule fully booked..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger btn-sm fw-bold">Confirm Rejection</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════
             ACTIVE SERVICES & RECENT ACTIVITY
             ══════════════════════════════════════════════════════ -->
        <div class="row g-4">
            <!-- Active Services Table -->
            <div class="col-lg-7">
                <div class="card gms-panel h-100 shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-white">
                            <i class="bi bi-tools text-info me-2"></i>Active & Confirmed Services
                        </h5>
                        <a href="<?= BASE_URL ?>bookings/list.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($activeServices)): ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">No active confirmed or in-progress services.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0">
                                    <thead class="text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-3">ID & Date</th>
                                            <th>Customer & Vehicle</th>
                                            <th>Service</th>
                                            <th>Status</th>
                                            <th class="pe-3 text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeServices as $as): ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <a href="<?= BASE_URL ?>bookings/view.php?id=<?= (int)$as['id'] ?>" class="text-warning font-monospace fw-bold text-decoration-none">
                                                        #BKG-<?= (int)$as['id'] ?>
                                                    </a>
                                                    <div class="text-muted small"><?= formatDate($as['booking_date']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-white fw-semibold small"><?= e($as['customer_name']) ?></div>
                                                    <div class="text-muted small"><?= e($as['brand']) ?> <?= e($as['model']) ?> (<?= e($as['vehicle_number']) ?>)</div>
                                                </td>
                                                <td>
                                                    <div class="text-light small"><?= e($as['service_name']) ?></div>
                                                    <span class="text-warning small font-monospace">₹<?= number_format($as['price'], 2) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $as['status'] === 'in_progress' ? 'bg-primary' : 'bg-info text-dark' ?> text-uppercase" style="font-size:0.7rem;">
                                                        <?= str_replace('_', ' ', $as['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="pe-3 text-end">
                                                    <a href="<?= BASE_URL ?>bookings/update-status.php?id=<?= (int)$as['id'] ?>" class="btn btn-sm btn-outline-warning" title="Update status">
                                                        <i class="bi bi-gear-fill"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Log Feed -->
            <div class="col-lg-5">
                <div class="card gms-panel h-100 shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-white">
                            <i class="bi bi-activity text-warning me-2"></i>Recent Activity Feed
                        </h5>
                        <a href="<?= BASE_URL ?>staff/activity-logs.php" class="btn btn-sm btn-outline-secondary">Full Log</a>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($recentActivities)): ?>
                            <p class="text-muted text-center py-3 mb-0">No recent activity recorded.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($recentActivities as $act): ?>
                                    <div class="p-2.5 rounded border border-secondary" style="background:var(--clr-dark-4);">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge bg-dark border border-secondary text-warning font-monospace" style="font-size:0.7rem;">
                                                <?= e($act['action']) ?>
                                            </span>
                                            <span class="text-muted small" style="font-size:0.75rem;">
                                                <?= formatDateTime($act['created_at']) ?>
                                            </span>
                                        </div>
                                        <div class="text-light small">
                                            <?= e($act['description']) ?>
                                        </div>
                                        <?php if (!empty($act['user_name'])): ?>
                                            <div class="text-muted small mt-1" style="font-size:0.72rem;">
                                                By: <span class="text-info"><?= e($act['user_name']) ?></span> (<?= e($act['role'] ?? 'user') ?>)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    function updateGreeting() {
        const hour = new Date().getHours();
        const greetingEl = document.getElementById('dashGreeting');
        if (!greetingEl) return;
        if (hour < 12)      greetingEl.textContent = 'Good morning,';
        else if (hour < 17) greetingEl.textContent = 'Good afternoon,';
        else                greetingEl.textContent = 'Good evening,';
    }

    function updateDate() {
        const dateEl = document.getElementById('dashDate');
        if (dateEl) {
            dateEl.textContent = new Date().toLocaleDateString('en-GB', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });
        }
    }

    updateGreeting();
    updateDate();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
