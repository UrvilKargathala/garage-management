<?php
/**
 * admin/vehicles.php — Vehicle Management
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 */
$pageTitle = 'Vehicle Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo    = getDBConnection();
$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? 'all');
$validTypes = ['all','car','motorcycle','truck','van','bus','other'];
if (!in_array($typeFilter,$validTypes,true)) $typeFilter='all';

$conds  = [];
$params = [];

if ($search !== '') {
    $conds[]       = '(v.vehicle_number LIKE :s1 OR v.brand LIKE :s2 OR v.model LIKE :s3 OR c.name LIKE :s4)';
    $params['s1']  = '%'.$search.'%';
    $params['s2']  = '%'.$search.'%';
    $params['s3']  = '%'.$search.'%';
    $params['s4']  = '%'.$search.'%';
}
if ($typeFilter !== 'all') {
    $conds[]       = 'v.vehicle_type = :vtype';
    $params['vtype'] = $typeFilter;
}
$wc = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

try {
    $sql = "SELECT v.id, v.vehicle_number, v.vehicle_type, v.brand, v.model, v.year, v.fuel_type, v.created_at,
                   c.id AS customer_id, c.name AS customer_name, c.phone AS customer_phone,
                   COUNT(b.id) AS booking_count
              FROM vehicles v
              JOIN customers c ON v.customer_id = c.id
         LEFT JOIN bookings b ON b.vehicle_id = v.id
              $wc
          GROUP BY v.id
          ORDER BY v.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vehicles     = $stmt->fetchAll();
    $totalVehicles = (int)$pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();

    $typeStats = $pdo->query("SELECT vehicle_type, COUNT(*) AS cnt FROM vehicles GROUP BY vehicle_type ORDER BY cnt DESC")->fetchAll();

} catch (PDOException $e) {
    error_log('[GarageMS] Admin vehicles: ' . $e->getMessage());
    $vehicles = []; $totalVehicles = 0; $typeStats = [];
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-car-front-fill"></i>Vehicle Management</h1>
            <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Vehicles</div>
        </div>
        <span class="badge bg-primary px-3 py-2">
            <i class="bi bi-car-front me-1"></i><?= number_format($totalVehicles) ?> Registered Vehicles
        </span>
    </div>

    <!-- Type breakdown -->
    <?php if (!empty($typeStats)): ?>
    <div class="row g-2 mb-4">
        <?php foreach ($typeStats as $ts): ?>
        <div class="col-6 col-sm-4 col-lg-2">
            <div class="admin-card text-center py-3">
                <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-subtle);"><?= e($ts['vehicle_type']) ?></div>
                <div style="font-size:1.4rem;font-weight:800;color:#fff;"><?= $ts['cnt'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="admin-filter-bar">
        <form method="GET" action="<?= BASE_URL ?>admin/vehicles.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label text-muted small fw-semibold mb-1">Search Vehicles</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control gms-input"
                           placeholder="Reg. No., brand, model or customer..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-semibold mb-1">Vehicle Type</label>
                <select name="type" class="form-select gms-input">
                    <option value="all" <?= $typeFilter==='all'?'selected':'' ?>>All Types</option>
                    <?php foreach (['car','motorcycle','truck','van','bus','other'] as $t): ?>
                        <option value="<?= $t ?>" <?= $typeFilter===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-gms-primary px-4"><i class="bi bi-filter me-1"></i>Filter</button>
                <?php if ($search || $typeFilter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>admin/vehicles.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h5 class="admin-card-title"><i class="bi bi-list-ul"></i>Vehicle Records
                <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= count($vehicles) ?></span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reg. Number</th>
                        <th>Brand / Model</th>
                        <th>Type</th>
                        <th>Year</th>
                        <th>Fuel</th>
                        <th>Customer</th>
                        <th>Bookings</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr><td colspan="9">
                            <div class="admin-empty">
                                <i class="bi bi-car-front"></i>
                                <p>No vehicles found<?= $search ? ' for "' . e($search) . '"' : '' ?>.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $v): ?>
                        <tr>
                            <td class="cell-id">#<?= $v['id'] ?></td>
                            <td>
                                <span class="cell-mono fw-bold text-warning"><?= e($v['vehicle_number']) ?></span>
                            </td>
                            <td>
                                <div class="cell-name"><?= e($v['brand']) ?> <?= e($v['model']) ?></div>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-staff"><?= e($v['vehicle_type']) ?></span>
                            </td>
                            <td class="text-muted small"><?= e($v['year']) ?></td>
                            <td class="text-muted small"><?= e($v['fuel_type']) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>admin/customers.php?detail=<?= $v['customer_id'] ?>"
                                   class="text-info small text-decoration-none">
                                    <?= e($v['customer_name']) ?>
                                </a>
                                <div class="text-muted" style="font-size:0.72rem;"><?= e($v['customer_phone']) ?></div>
                            </td>
                            <td><span class="badge bg-secondary"><?= $v['booking_count'] ?></span></td>
                            <td class="small text-muted"><?= formatDate($v['created_at']) ?></td>
                        </tr>
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
