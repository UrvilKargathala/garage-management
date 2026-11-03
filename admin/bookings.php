<?php
/**
 * admin/bookings.php — Complete Booking Management
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 * Features: View all bookings with full JOIN data (customer, vehicle, service, location)
 */
$pageTitle = 'Booking Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo = getDBConnection();

$search       = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$validStatuses = ['all','pending','confirmed','in_progress','completed','cancelled'];
if (!in_array($statusFilter,$validStatuses,true)) $statusFilter='all';

$conds  = [];
$params = [];

if ($search !== '') {
    $conds[]       = '(c.name LIKE :s1 OR c.email LIKE :s2 OR v.vehicle_number LIKE :s3 OR s.service_name LIKE :s4)';
    $params['s1']  = '%'.$search.'%';
    $params['s2']  = '%'.$search.'%';
    $params['s3']  = '%'.$search.'%';
    $params['s4']  = '%'.$search.'%';
}
if ($statusFilter !== 'all') {
    $conds[]           = 'b.status = :bstatus';
    $params['bstatus'] = $statusFilter;
}
$wc = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

try {
    // Full JOIN query with all booking info required
    $sql = "SELECT b.id, b.booking_date, b.booking_time, b.status, b.remarks,
                   b.service_address, b.area, b.city, b.state, b.pincode,
                   b.created_at, b.updated_at,
                   c.id AS customer_id, c.name AS customer_name, c.email AS customer_email,
                   c.phone AS customer_phone, c.address AS customer_address,
                   v.vehicle_number, v.brand, v.model, v.vehicle_type, v.fuel_type, v.year,
                   s.service_name, s.price AS service_price,
                   u.name AS mechanic_name, u.email AS mechanic_email
              FROM bookings b
              JOIN customers c ON b.customer_id = c.id
              JOIN vehicles v ON b.vehicle_id = v.id
              JOIN services s ON b.service_id = s.id
         LEFT JOIN users u ON b.staff_id = u.id
              $wc
          ORDER BY b.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();

    $totalBookings    = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $pendingBookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
    $completedBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
    $cancelledBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetchColumn();

} catch (PDOException $e) {
    error_log('[GarageMS] Admin bookings: ' . $e->getMessage());
    $bookings = [];
    $totalBookings = $pendingBookings = $completedBookings = $cancelledBookings = 0;
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-calendar-check-fill"></i>Booking Management</h1>
            <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Bookings</div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon blue"><i class="bi bi-calendar-check-fill"></i></div>
                <div><div class="admin-stat-value"><?= $totalBookings ?></div><div class="admin-stat-label">Total Bookings</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="admin-stat-value"><?= $pendingBookings ?></div><div class="admin-stat-label">Pending</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="admin-stat-value"><?= $completedBookings ?></div><div class="admin-stat-label">Completed</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
                <div><div class="admin-stat-value"><?= $cancelledBookings ?></div><div class="admin-stat-label">Cancelled</div></div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="admin-filter-bar">
        <form method="GET" action="<?= BASE_URL ?>admin/bookings.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label text-muted small fw-semibold mb-1">Search Bookings</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control gms-input"
                           placeholder="Customer, email, vehicle or service..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select gms-input">
                    <option value="all"         <?= $statusFilter==='all'        ?'selected':'' ?>>All Statuses</option>
                    <option value="pending"     <?= $statusFilter==='pending'    ?'selected':'' ?>>Pending</option>
                    <option value="confirmed"   <?= $statusFilter==='confirmed'  ?'selected':'' ?>>Confirmed</option>
                    <option value="in_progress" <?= $statusFilter==='in_progress'?'selected':'' ?>>In Progress</option>
                    <option value="completed"   <?= $statusFilter==='completed'  ?'selected':'' ?>>Completed</option>
                    <option value="cancelled"   <?= $statusFilter==='cancelled'  ?'selected':'' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-gms-primary px-4"><i class="bi bi-filter me-1"></i>Filter</button>
                <?php if ($search || $statusFilter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>admin/bookings.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h5 class="admin-card-title"><i class="bi bi-list-ul"></i>All Bookings
                <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= count($bookings) ?></span>
            </h5>
            <small class="text-muted">Shows complete customer + vehicle + service + location data</small>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>City / Area</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="8">
                            <div class="admin-empty"><i class="bi bi-calendar-x"></i><p>No bookings found.</p></div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                        <?php $bs = match($b['status']) {
                            'pending'=>'admin-badge-pending','confirmed'=>'admin-badge-confirmed',
                            'in_progress'=>'admin-badge-progress','completed'=>'admin-badge-completed',
                            'cancelled'=>'admin-badge-cancelled',default=>'admin-badge-inactive'
                        }; ?>
                        <tr>
                            <td class="cell-id">#<?= $b['id'] ?></td>
                            <td>
                                <div class="cell-name"><?= e($b['customer_name']) ?></div>
                                <div class="text-muted" style="font-size:0.73rem;"><?= e($b['customer_email'] ?? $b['customer_phone']) ?></div>
                            </td>
                            <td>
                                <div class="cell-mono"><?= e($b['vehicle_number']) ?></div>
                                <div class="text-muted" style="font-size:0.73rem;"><?= e($b['brand'] . ' ' . $b['model']) ?></div>
                            </td>
                            <td>
                                <div class="small text-light"><?= e($b['service_name']) ?></div>
                                <div class="text-success fw-bold" style="font-size:0.75rem;">₹<?= number_format((float)$b['service_price'],2) ?></div>
                            </td>
                            <td>
                                <div class="small"><?= formatDate($b['booking_date']) ?></div>
                                <?php if ($b['booking_time']): ?><div class="text-muted" style="font-size:0.72rem;"><?= e(substr($b['booking_time'],0,5)) ?></div><?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= e($b['city'] ?? ($b['area'] ?? '—')) ?>
                                <?php if ($b['state']): ?><div style="font-size:0.72rem;"><?= e($b['state']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="admin-badge <?= $bs ?>"><?= str_replace('_',' ',$b['status']) ?></span></td>
                            <td>
                                <button type="button" class="btn btn-outline-info btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#bkgModal<?= $b['id'] ?>" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Booking Detail Modal -->
                        <div class="modal fade" id="bkgModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content" style="background:#1c2333;border:1px solid rgba(255,255,255,0.1);">
                                    <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title text-white fw-bold">
                                            <i class="bi bi-calendar-check-fill me-2 text-warning"></i>
                                            Booking #<?= $b['id'] ?> Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <h6 class="text-warning mb-2 small text-uppercase fw-bold">Customer Info</h6>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Name</span><span class="admin-detail-value"><?= e($b['customer_name']) ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Email</span><span class="admin-detail-value"><?= e($b['customer_email'] ?? '—') ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Phone</span><span class="admin-detail-value"><?= e($b['customer_phone']) ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Address</span><span class="admin-detail-value"><?= e($b['customer_address'] ?? '—') ?></span></div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-warning mb-2 small text-uppercase fw-bold">Vehicle Info</h6>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Reg. Number</span><span class="admin-detail-value cell-mono"><?= e($b['vehicle_number']) ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Brand/Model</span><span class="admin-detail-value"><?= e($b['brand'] . ' ' . $b['model']) ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Type</span><span class="admin-detail-value"><?= e($b['vehicle_type']) ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Year / Fuel</span><span class="admin-detail-value"><?= e($b['year'] . ' / ' . $b['fuel_type']) ?></span></div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-warning mb-2 small text-uppercase fw-bold">Booking Info</h6>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Service</span><span class="admin-detail-value"><?= e($b['service_name']) ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Price</span><span class="admin-detail-value text-success">₹<?= number_format((float)$b['service_price'],2) ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Date</span><span class="admin-detail-value"><?= formatDate($b['booking_date']) ?><?= $b['booking_time'] ? ' at ' . substr($b['booking_time'],0,5) : '' ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Status</span><span class="admin-detail-value"><span class="admin-badge <?= $bs ?>"><?= str_replace('_',' ',$b['status']) ?></span></span></div>
                                                <?php if ($b['mechanic_name']): ?>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Assigned To</span><span class="admin-detail-value"><?= e($b['mechanic_name']) ?></span></div>
                                                <?php endif; ?>
                                                <?php if ($b['remarks']): ?>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Remarks</span><span class="admin-detail-value"><?= e($b['remarks']) ?></span></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-warning mb-2 small text-uppercase fw-bold">Service Location</h6>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Address</span><span class="admin-detail-value"><?= e($b['service_address'] ?? '—') ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Area</span><span class="admin-detail-value"><?= e($b['area'] ?? '—') ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">City</span><span class="admin-detail-value"><?= e($b['city'] ?? '—') ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">State</span><span class="admin-detail-value"><?= e($b['state'] ?? '—') ?></span></div>
                                                <div class="admin-detail-row"><span class="admin-detail-label">Pincode</span><span class="admin-detail-value"><?= e($b['pincode'] ?? '—') ?></span></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
require_once __DIR__ . '/../includes/footer.php';
?>
