<?php
/**
 * admin/customers.php — Customer Management
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 * Features: View all customers, search, view vehicles, bookings, service history
 */
$pageTitle = 'Customer Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$params = [];
$where  = [];

if ($search !== '') {
    $where[]    = '(c.name LIKE :s1 OR c.email LIKE :s2 OR c.phone LIKE :s3 OR c.address LIKE :s4)';
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
    $params['s3'] = '%' . $search . '%';
    $params['s4'] = '%' . $search . '%';
}
$wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $sql = "SELECT c.id, c.name, c.phone, c.email, c.address, c.created_at,
                   u.email AS user_email, u.role AS user_role,
                   COUNT(DISTINCT v.id) AS vehicle_count,
                   COUNT(DISTINCT b.id) AS booking_count
              FROM customers c
         LEFT JOIN users u ON c.user_id = u.id
         LEFT JOIN vehicles v ON v.customer_id = c.id
         LEFT JOIN bookings b ON b.customer_id = c.id
              $wc
          GROUP BY c.id
          ORDER BY c.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

    // Detail view
    $detailId = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
    $detail = null; $detailVehicles = []; $detailBookings = []; $detailHistory = [];
    if ($detailId > 0) {
        $stmtD = $pdo->prepare("SELECT c.*, u.email AS user_email FROM customers c LEFT JOIN users u ON c.user_id = u.id WHERE c.id=? LIMIT 1");
        $stmtD->execute([$detailId]);
        $detail = $stmtD->fetch();

        if ($detail) {
            $stmtV = $pdo->prepare("SELECT * FROM vehicles WHERE customer_id=? ORDER BY created_at DESC");
            $stmtV->execute([$detailId]);
            $detailVehicles = $stmtV->fetchAll();

            $stmtB = $pdo->prepare("SELECT b.*, s.service_name, v.vehicle_number FROM bookings b JOIN services s ON b.service_id=s.id JOIN vehicles v ON b.vehicle_id=v.id WHERE b.customer_id=? ORDER BY b.booking_date DESC LIMIT 10");
            $stmtB->execute([$detailId]);
            $detailBookings = $stmtB->fetchAll();

            $stmtH = $pdo->prepare("SELECT sh.*, v.vehicle_number FROM service_history sh JOIN vehicles v ON sh.vehicle_id=v.id WHERE v.customer_id=? ORDER BY sh.service_date DESC LIMIT 10");
            $stmtH->execute([$detailId]);
            $detailHistory = $stmtH->fetchAll();
        }
    }
} catch (PDOException $e) {
    error_log('[GarageMS] Admin customers: ' . $e->getMessage());
    $customers = []; $totalCustomers = 0;
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <?php if ($detail): ?>
        <!-- ── Customer Detail View ─────────────────────────── -->
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title"><i class="bi bi-person-lines-fill"></i><?= e($detail['name']) ?></h1>
                <div class="admin-breadcrumb">
                    <a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> ›
                    <a href="<?= BASE_URL ?>admin/customers.php">Customers</a> › Detail
                </div>
            </div>
            <a href="<?= BASE_URL ?>admin/customers.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to List
            </a>
        </div>

        <div class="row g-3 mb-4">
            <!-- Profile Card -->
            <div class="col-md-5 col-lg-4">
                <div class="admin-card h-100">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-person-circle"></i>Customer Profile</h5>
                    </div>
                    <div class="admin-card-body">
                        <div class="text-center mb-4">
                            <div class="admin-user-avatar mx-auto mb-2" style="width:56px;height:56px;font-size:1.4rem;">
                                <?= strtoupper(mb_substr($detail['name'], 0, 1)) ?>
                            </div>
                            <div class="fw-bold text-white"><?= e($detail['name']) ?></div>
                            <div class="text-muted small"><?= e($detail['phone']) ?></div>
                        </div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Customer ID</span><span class="admin-detail-value cell-id">#<?= $detail['id'] ?></span></div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Email</span><span class="admin-detail-value"><?= e($detail['email'] ?? '—') ?></span></div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Address</span><span class="admin-detail-value"><?= e($detail['address'] ?? '—') ?></span></div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Portal Login</span><span class="admin-detail-value"><?= $detail['user_email'] ? '<span class="text-success">Linked: ' . e($detail['user_email']) . '</span>' : '<span class="text-muted">Walk-in</span>' ?></span></div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Member Since</span><span class="admin-detail-value"><?= formatDate($detail['created_at']) ?></span></div>
                    </div>
                </div>
            </div>
            <!-- Vehicles -->
            <div class="col-md-7 col-lg-8">
                <div class="admin-card mb-3">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-car-front-fill"></i>Vehicles (<?= count($detailVehicles) ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead><tr><th>Reg. No.</th><th>Brand/Model</th><th>Type</th><th>Year</th><th>Fuel</th></tr></thead>
                            <tbody>
                                <?php if (empty($detailVehicles)): ?>
                                    <tr><td colspan="5"><div class="admin-empty"><i class="bi bi-car-front"></i><p>No vehicles registered.</p></div></td></tr>
                                <?php else: ?>
                                    <?php foreach ($detailVehicles as $v): ?>
                                    <tr>
                                        <td class="cell-mono"><?= e($v['vehicle_number']) ?></td>
                                        <td class="cell-name"><?= e($v['brand'] . ' ' . $v['model']) ?></td>
                                        <td><span class="admin-badge admin-badge-staff"><?= e($v['vehicle_type']) ?></span></td>
                                        <td><?= e($v['year']) ?></td>
                                        <td class="text-muted small"><?= e($v['fuel_type']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-calendar-check-fill"></i>Recent Bookings (<?= count($detailBookings) ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead><tr><th>ID</th><th>Service</th><th>Vehicle</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($detailBookings)): ?>
                                    <tr><td colspan="5"><div class="admin-empty"><i class="bi bi-calendar-x"></i><p>No bookings yet.</p></div></td></tr>
                                <?php else: ?>
                                    <?php foreach ($detailBookings as $b): ?>
                                    <?php $bs = match($b['status']) {
                                        'pending'=>'admin-badge-pending','confirmed'=>'admin-badge-confirmed',
                                        'in_progress'=>'admin-badge-progress','completed'=>'admin-badge-completed',
                                        'cancelled'=>'admin-badge-cancelled',default=>'admin-badge-inactive'
                                    }; ?>
                                    <tr>
                                        <td class="cell-id">#<?= $b['id'] ?></td>
                                        <td><?= e($b['service_name']) ?></td>
                                        <td class="cell-mono"><?= e($b['vehicle_number']) ?></td>
                                        <td class="small"><?= formatDate($b['booking_date']) ?></td>
                                        <td><span class="admin-badge <?= $bs ?>"><?= str_replace('_',' ',$b['status']) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ── Customers List View ─────────────────────────── -->
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title"><i class="bi bi-person-lines-fill"></i>Customer Management</h1>
                <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Customers</div>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2">
                <i class="bi bi-people me-1"></i><?= number_format($totalCustomers) ?> Total Customers
            </span>
        </div>

        <!-- Search -->
        <div class="admin-filter-bar">
            <form method="GET" action="<?= BASE_URL ?>admin/customers.php" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-semibold mb-1">Search Customers</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control gms-input"
                               placeholder="Name, email, phone or address..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-gms-primary px-4"><i class="bi bi-filter me-1"></i>Search</button>
                    <?php if ($search): ?>
                        <a href="<?= BASE_URL ?>admin/customers.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="bi bi-list-ul"></i>Customer Records
                    <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= count($customers) ?></span>
                </h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Vehicles</th>
                            <th>Bookings</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr><td colspan="8">
                                <div class="admin-empty">
                                    <i class="bi bi-person-x"></i>
                                    <p>No customers found<?= $search ? ' for "' . e($search) . '"' : '' ?>.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                            <tr>
                                <td class="cell-id">#<?= $c['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="admin-avatar-sm"><?= strtoupper(mb_substr($c['name'],0,1)) ?></div>
                                        <div>
                                            <div class="cell-name"><?= e($c['name']) ?></div>
                                            <?php if ($c['user_email']): ?>
                                                <div class="text-muted" style="font-size:0.72rem;">
                                                    <i class="bi bi-link-45deg"></i>Portal linked
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small"><?= e($c['phone']) ?></div>
                                    <?php if ($c['email']): ?>
                                        <div class="text-muted" style="font-size:0.75rem;"><?= e($c['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small" style="max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= e($c['address'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?= $c['vehicle_count'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $c['booking_count'] ?></span>
                                </td>
                                <td class="small text-muted"><?= formatDate($c['created_at']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>admin/customers.php?detail=<?= $c['id'] ?>"
                                       class="btn btn-outline-info btn-sm" title="View Details">
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
    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
require_once __DIR__ . '/../includes/footer.php';
?>
