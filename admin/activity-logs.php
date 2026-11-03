<?php
/**
 * admin/activity-logs.php — Activity Logs Management
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 * Shows all activities across Customer, Staff/Mechanic, and Administrator roles.
 */
$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo          = getDBConnection();
$search       = trim($_GET['search'] ?? '');
$roleFilter   = trim($_GET['role'] ?? 'all');
$actionFilter = trim($_GET['action'] ?? 'all');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');

$validRoles = ['all', 'admin', 'staff', 'customer'];
if (!in_array($roleFilter, $validRoles, true)) $roleFilter = 'all';

$conds  = [];
$params = [];

if ($search !== '') {
    $conds[]       = '(u.name LIKE :s1 OR u.email LIKE :s2 OR al.action LIKE :s3 OR al.description LIKE :s4)';
    $params['s1']  = '%' . $search . '%';
    $params['s2']  = '%' . $search . '%';
    $params['s3']  = '%' . $search . '%';
    $params['s4']  = '%' . $search . '%';
}

if ($roleFilter !== 'all') {
    $conds[]           = 'COALESCE(u.role, al.role) = :arole';
    $params['arole']   = $roleFilter;
}

if ($actionFilter !== 'all') {
    $conds[]           = 'al.action = :aaction';
    $params['aaction'] = $actionFilter;
}

if ($dateFrom !== '') {
    $conds[]             = 'DATE(al.created_at) >= :date_from';
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $conds[]           = 'DATE(al.created_at) <= :date_to';
    $params['date_to'] = $dateTo;
}

$wc = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

try {
    // Fetch distinct actions for filter dropdown
    $actionsList = $pdo->query('SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT al.id, al.action, al.entity_type, al.entity_id,
                   al.description, al.ip_address, al.created_at,
                   COALESCE(u.role, al.role, 'customer') AS role,
                   u.name AS user_name, u.email AS user_email
              FROM activity_logs al
         LEFT JOIN users u ON al.user_id = u.id
              $wc
          ORDER BY al.created_at DESC, al.id DESC
             LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $totalLogs = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

} catch (PDOException $e) {
    error_log('[GarageMS] Admin activity logs: ' . $e->getMessage());
    $logs = []; $totalLogs = 0; $actionsList = [];
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-activity"></i>Activity Logs</h1>
            <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Activity Logs</div>
        </div>
        <span class="badge bg-secondary px-3 py-2"><?= number_format($totalLogs) ?> Total Entries</span>
    </div>

    <div class="admin-filter-bar">
        <form method="GET" action="<?= BASE_URL ?>admin/activity-logs.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-semibold mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control gms-input"
                           placeholder="User, action, details..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-semibold mb-1">Role</label>
                <select name="role" class="form-select gms-input">
                    <option value="all"     <?= $roleFilter==='all'     ?'selected':'' ?>>All Roles</option>
                    <option value="customer"<?= $roleFilter==='customer'?'selected':'' ?>>Customers</option>
                    <option value="staff"   <?= $roleFilter==='staff'   ?'selected':'' ?>>Staff / Mechanics</option>
                    <option value="admin"   <?= $roleFilter==='admin'   ?'selected':'' ?>>Administrators</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-semibold mb-1">Action</label>
                <select name="action" class="form-select gms-input">
                    <option value="all">All Actions</option>
                    <?php foreach ($actionsList as $act): ?>
                        <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= e($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-semibold mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control gms-input" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-semibold mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control gms-input" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-gms-primary w-100" title="Filter"><i class="bi bi-filter"></i></button>
                <?php if ($search || $roleFilter !== 'all' || $actionFilter !== 'all' || $dateFrom || $dateTo): ?>
                    <a href="<?= BASE_URL ?>admin/activity-logs.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-circle"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h5 class="admin-card-title"><i class="bi bi-list-task"></i>System Audit Trail
                <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= count($logs) ?></span>
            </h5>
            <small class="text-muted"><i class="bi bi-sort-down me-1"></i>Newest first</small>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8">
                            <div class="admin-empty"><i class="bi bi-journal-x"></i><p>No activity logs found.</p></div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                        <?php $roleBadge = match($l['role'] ?? '') {
                            'admin'=>'admin-badge-admin','staff'=>'admin-badge-staff',
                            'customer'=>'admin-badge-customer',default=>'admin-badge-inactive'
                        }; ?>
                        <tr>
                            <td class="cell-id">#<?= $l['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="admin-avatar-sm" style="width:26px;height:26px;font-size:0.7rem;">
                                        <?= strtoupper(mb_substr($l['user_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="small fw-semibold text-light"><?= e($l['user_name'] ?? 'System') ?></div>
                                        <div class="text-muted" style="font-size:0.7rem;"><?= e($l['user_email'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="admin-badge <?= $roleBadge ?>"><?= e($l['role'] ?? '—') ?></span></td>
                            <td>
                                <code class="small text-warning bg-dark px-2 py-1 rounded border border-secondary">
                                    <?= e($l['action']) ?>
                                </code>
                            </td>
                            <td class="small text-muted">
                                <?php if ($l['entity_type']): ?>
                                    <span class="badge bg-dark border border-secondary"><?= e($l['entity_type']) ?></span>
                                    <?= $l['entity_id'] ? ' #' . $l['entity_id'] : '' ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="small text-muted" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                title="<?= e($l['description'] ?? '') ?>">
                                <?= e($l['description'] ?? '—') ?>
                            </td>
                            <td>
                                <code class="text-warning-subtle small px-1 py-0 bg-dark rounded border border-secondary">
                                    <?= e($l['ip_address'] ?? '—') ?>
                                </code>
                            </td>
                            <td class="small text-muted"><?= formatDateTime($l['created_at']) ?></td>
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
