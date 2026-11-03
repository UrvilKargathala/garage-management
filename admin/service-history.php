<?php
/**
 * admin/service-history.php — Service History Management
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 */
$pageTitle = 'Service History';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo    = getDBConnection();
$search = trim($_GET['search'] ?? '');

$conds  = [];
$params = [];

if ($search !== '') {
    $conds[]       = '(c.name LIKE :s1 OR v.vehicle_number LIKE :s2 OR sh.mechanic_name LIKE :s3 OR sh.service_description LIKE :s4)';
    $params['s1']  = '%'.$search.'%';
    $params['s2']  = '%'.$search.'%';
    $params['s3']  = '%'.$search.'%';
    $params['s4']  = '%'.$search.'%';
}
$wc = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

try {
    $sql = "SELECT sh.id, sh.service_description, sh.service_cost, sh.mechanic_name,
                   sh.service_date, sh.remarks, sh.created_at,
                   sh.booking_id,
                   v.vehicle_number, v.brand, v.model,
                   c.id AS customer_id, c.name AS customer_name, c.phone AS customer_phone,
                   s.service_name
              FROM service_history sh
              JOIN vehicles v ON sh.vehicle_id = v.id
              JOIN customers c ON v.customer_id = c.id
         LEFT JOIN bookings b ON sh.booking_id = b.id
         LEFT JOIN services s ON b.service_id = s.id
              $wc
          ORDER BY sh.service_date DESC, sh.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll();

    $totalHistory  = (int)$pdo->query("SELECT COUNT(*) FROM service_history")->fetchColumn();
    $totalRevenue  = (float)($pdo->query("SELECT COALESCE(SUM(service_cost),0) FROM service_history")->fetchColumn() ?: 0);

} catch (PDOException $e) {
    error_log('[GarageMS] Admin service history: ' . $e->getMessage());
    $history = []; $totalHistory = 0; $totalRevenue = 0;
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-clock-history"></i>Service History</h1>
            <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Service History</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon cyan"><i class="bi bi-clipboard-check-fill"></i></div>
                <div><div class="admin-stat-value"><?= number_format($totalHistory) ?></div><div class="admin-stat-label">Completed Services</div></div>
            </div>
        </div>
        <div class="col-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon green"><i class="bi bi-currency-rupee"></i></div>
                <div><div class="admin-stat-value text-success">₹<?= number_format($totalRevenue,2) ?></div><div class="admin-stat-label">Total Revenue</div></div>
            </div>
        </div>
    </div>

    <div class="admin-filter-bar">
        <form method="GET" action="<?= BASE_URL ?>admin/service-history.php" class="row g-2 align-items-end">
            <div class="col-md-7">
                <label class="form-label text-muted small fw-semibold mb-1">Search History</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control gms-input"
                           placeholder="Customer, vehicle, mechanic or description..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-gms-primary px-4"><i class="bi bi-filter me-1"></i>Search</button>
                <?php if ($search): ?>
                    <a href="<?= BASE_URL ?>admin/service-history.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h5 class="admin-card-title"><i class="bi bi-list-ul"></i>Service Records
                <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= count($history) ?></span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Service Done</th>
                        <th>Mechanic</th>
                        <th>Date</th>
                        <th>Cost</th>
                        <th>Booking Ref</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="9">
                            <div class="admin-empty"><i class="bi bi-clipboard-x"></i><p>No service history found.</p></div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td class="cell-id">#<?= $h['id'] ?></td>
                            <td>
                                <div class="cell-name"><?= e($h['customer_name']) ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= e($h['customer_phone']) ?></div>
                            </td>
                            <td>
                                <div class="cell-mono"><?= e($h['vehicle_number']) ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= e($h['brand'] . ' ' . $h['model']) ?></div>
                            </td>
                            <td class="small text-light" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= e($h['service_name'] ?? $h['service_description']) ?>
                            </td>
                            <td class="small text-muted"><?= e($h['mechanic_name'] ?? '—') ?></td>
                            <td class="small"><?= formatDate($h['service_date']) ?></td>
                            <td class="text-success fw-bold font-monospace">₹<?= number_format((float)$h['service_cost'],2) ?></td>
                            <td class="cell-id">
                                <?= $h['booking_id'] ? '#' . $h['booking_id'] : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td class="small text-muted" style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= e($h['remarks'] ?? '—') ?>
                            </td>
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
