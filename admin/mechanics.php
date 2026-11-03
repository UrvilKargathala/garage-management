<?php
/**
 * admin/mechanics.php — Staff / Mechanic Management
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 */
$pageTitle = 'Mechanic / Staff Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo    = getDBConnection();
$search = trim($_GET['search'] ?? '');
$params = [];
$where  = [];

if ($search !== '') {
    $where[]      = '(u.name LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)';
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
    $params['s3'] = '%' . $search . '%';
}
$wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Staff users with their workload stats
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.created_at,
                   (SELECT COUNT(*) FROM bookings b WHERE b.staff_id = u.id) AS assigned_jobs,
                   (SELECT COUNT(*) FROM bookings b WHERE b.staff_id = u.id AND b.status = 'completed') AS completed_jobs,
                   (SELECT COUNT(*) FROM bookings b WHERE b.staff_id = u.id AND b.status IN ('pending','confirmed','in_progress')) AS active_jobs,
                   (SELECT MAX(b.updated_at) FROM bookings b WHERE b.staff_id = u.id) AS last_activity
              FROM users u
              $wc
              " . ($where ? 'AND u.role = "staff"' : 'WHERE u.role = "staff"') . "
          ORDER BY u.created_at DESC";

    // Rebuild - the WHERE logic above is a bit off, let's do it properly
    $params2 = [];
    $conds   = ["u.role = 'staff'"];
    if ($search !== '') {
        $conds[]        = '(u.name LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)';
        $params2['s1']  = '%' . $search . '%';
        $params2['s2']  = '%' . $search . '%';
        $params2['s3']  = '%' . $search . '%';
    }
    $sql2 = "SELECT u.id, u.name, u.email, u.phone, u.created_at,
                    COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.staff_id = u.id), 0) AS assigned_jobs,
                    COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.staff_id = u.id AND b.status = 'completed'), 0) AS completed_jobs,
                    COALESCE((SELECT COUNT(*) FROM bookings b WHERE b.staff_id = u.id AND b.status IN ('pending','confirmed','in_progress')), 0) AS active_jobs,
                    (SELECT MAX(b.updated_at) FROM bookings b WHERE b.staff_id = u.id) AS last_activity
               FROM users u
              WHERE " . implode(' AND ', $conds) . "
           ORDER BY u.created_at DESC";

    $stmt = $pdo->prepare($sql2);
    $stmt->execute($params2);
    $mechanics = $stmt->fetchAll();

    $totalMechanics = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();

    // Detail view
    $detailId = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
    $detail   = null;
    $detailJobs = [];
    if ($detailId > 0) {
        $stmtD = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='staff' LIMIT 1");
        $stmtD->execute([$detailId]);
        $detail = $stmtD->fetch();

        if ($detail) {
            $stmtJ = $pdo->prepare(
                "SELECT b.id, b.booking_date, b.status, b.remarks,
                        c.name AS customer_name, v.vehicle_number,
                        s.service_name
                   FROM bookings b
                   JOIN customers c ON b.customer_id = c.id
                   JOIN vehicles v ON b.vehicle_id = v.id
                   JOIN services s ON b.service_id = s.id
                  WHERE b.staff_id = ?
                  ORDER BY b.booking_date DESC
                  LIMIT 20"
            );
            $stmtJ->execute([$detailId]);
            $detailJobs = $stmtJ->fetchAll();
        }
    }

} catch (PDOException $e) {
    error_log('[GarageMS] Admin mechanics: ' . $e->getMessage());
    $mechanics = []; $totalMechanics = 0;
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <?php if ($detail): ?>
        <!-- Detail View -->
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title"><i class="bi bi-wrench-adjustable-circle-fill"></i><?= e($detail['name']) ?></h1>
                <div class="admin-breadcrumb">
                    <a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> ›
                    <a href="<?= BASE_URL ?>admin/mechanics.php">Mechanics</a> › Detail
                </div>
            </div>
            <a href="<?= BASE_URL ?>admin/mechanics.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="admin-card h-100">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-person-badge-fill"></i>Mechanic Profile</h5>
                    </div>
                    <div class="admin-card-body">
                        <div class="text-center mb-4">
                            <div class="admin-user-avatar mx-auto mb-2" style="width:56px;height:56px;font-size:1.4rem;background:linear-gradient(135deg,#3b82f6,#60a5fa);">
                                <?= strtoupper(mb_substr($detail['name'],0,1)) ?>
                            </div>
                            <div class="fw-bold text-white"><?= e($detail['name']) ?></div>
                            <span class="admin-badge admin-badge-staff mt-1">Staff / Mechanic</span>
                        </div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Staff ID</span><span class="admin-detail-value cell-id">#<?= $detail['id'] ?></span></div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Email</span><span class="admin-detail-value"><?= e($detail['email']) ?></span></div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Phone</span><span class="admin-detail-value"><?= e($detail['phone'] ?? '—') ?></span></div>
                        <div class="admin-detail-row"><span class="admin-detail-label">Joined</span><span class="admin-detail-value"><?= formatDate($detail['created_at']) ?></span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h5 class="admin-card-title"><i class="bi bi-calendar-check-fill"></i>Assigned Jobs (<?= count($detailJobs) ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead><tr><th>ID</th><th>Customer</th><th>Vehicle</th><th>Service</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($detailJobs)): ?>
                                    <tr><td colspan="6"><div class="admin-empty"><i class="bi bi-briefcase"></i><p>No jobs assigned yet.</p></div></td></tr>
                                <?php else: ?>
                                    <?php foreach ($detailJobs as $j): ?>
                                    <?php $bs = match($j['status']) {
                                        'pending'=>'admin-badge-pending','confirmed'=>'admin-badge-confirmed',
                                        'in_progress'=>'admin-badge-progress','completed'=>'admin-badge-completed',
                                        'cancelled'=>'admin-badge-cancelled',default=>'admin-badge-inactive'
                                    }; ?>
                                    <tr>
                                        <td class="cell-id">#<?= $j['id'] ?></td>
                                        <td class="cell-name"><?= e($j['customer_name']) ?></td>
                                        <td class="cell-mono"><?= e($j['vehicle_number']) ?></td>
                                        <td class="small"><?= e($j['service_name']) ?></td>
                                        <td class="small"><?= formatDate($j['booking_date']) ?></td>
                                        <td><span class="admin-badge <?= $bs ?>"><?= str_replace('_',' ',$j['status']) ?></span></td>
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
        <!-- List View -->
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title"><i class="bi bi-wrench-adjustable-circle-fill"></i>Mechanics / Staff</h1>
                <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Mechanics</div>
            </div>
            <span class="badge bg-info text-dark px-3 py-2">
                <i class="bi bi-person-gear me-1"></i><?= number_format($totalMechanics) ?> Staff Members
            </span>
        </div>

        <div class="admin-filter-bar">
            <form method="GET" action="<?= BASE_URL ?>admin/mechanics.php" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-semibold mb-1">Search Staff</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control gms-input"
                               placeholder="Name, email or phone..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-gms-primary px-4"><i class="bi bi-filter me-1"></i>Search</button>
                    <?php if ($search): ?>
                        <a href="<?= BASE_URL ?>admin/mechanics.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title"><i class="bi bi-list-ul"></i>Staff Records
                    <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= count($mechanics) ?></span>
                </h5>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Staff Member</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Total Jobs</th>
                            <th>Active Jobs</th>
                            <th>Completed</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mechanics)): ?>
                            <tr><td colspan="9">
                                <div class="admin-empty">
                                    <i class="bi bi-person-x"></i>
                                    <p>No staff members found<?= $search ? ' for "' . e($search) . '"' : '' ?>.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($mechanics as $m): ?>
                            <tr>
                                <td class="cell-id">#<?= $m['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="admin-avatar-sm" style="background:linear-gradient(135deg,#3b82f6,#60a5fa);">
                                            <?= strtoupper(mb_substr($m['name'],0,1)) ?>
                                        </div>
                                        <span class="cell-name"><?= e($m['name']) ?></span>
                                    </div>
                                </td>
                                <td class="text-muted small"><?= e($m['email']) ?></td>
                                <td class="text-muted small"><?= e($m['phone'] ?? '—') ?></td>
                                <td><span class="badge bg-secondary"><?= $m['assigned_jobs'] ?></span></td>
                                <td><span class="badge bg-warning text-dark"><?= $m['active_jobs'] ?></span></td>
                                <td><span class="badge bg-success"><?= $m['completed_jobs'] ?></span></td>
                                <td class="small text-muted"><?= formatDate($m['created_at']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>admin/mechanics.php?detail=<?= $m['id'] ?>"
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
