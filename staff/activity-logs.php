<?php
/**
 * staff/activity-logs.php — Staff / Mechanic Activity Logs
 * Garage Management System
 *
 * Security & Access Control:
 *  ✔ Restricted to Staff and Admin roles via requireStaffOrAdmin()
 *  ✔ Staff / Mechanics see ONLY their own activity logs (WHERE a.user_id = CURRENT_USER_ID)
 *  ✔ Prevents cross-mechanic or customer log leakage
 */

$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireStaffOrAdmin();

$pdo = getDBConnection();
$currentUserId   = currentUserId();
$currentUserRole = currentUserRole();

$actionFilter = trim($_GET['action'] ?? 'all');
$moduleFilter = trim($_GET['module'] ?? 'all');
$search       = trim($_GET['search'] ?? '');

$where  = [];
$params = [];

// ── Strict Role-Based SQL Filter ─────────────────────────────
// If logged in as staff/mechanic, enforce viewing ONLY their own activity logs.
if ($currentUserRole === 'staff') {
    $where[] = 'a.user_id = :staff_user_id';
    $params['staff_user_id'] = $currentUserId;
}

if ($actionFilter !== 'all') {
    $where[] = 'a.action = :action';
    $params['action'] = $actionFilter;
}

if ($moduleFilter !== 'all') {
    $where[] = 'a.entity_type = :module';
    $params['module'] = $moduleFilter;
}

if ($search !== '') {
    $where[] = '(a.description LIKE :search1 OR a.action LIKE :search2)';
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch distinct actions and entity types for filter dropdowns
try {
    if ($currentUserRole === 'staff') {
        $stmtAct = $pdo->prepare('SELECT DISTINCT action FROM activity_logs WHERE user_id = ? AND action IS NOT NULL ORDER BY action ASC');
        $stmtAct->execute([$currentUserId]);
        $actions = $stmtAct->fetchAll(PDO::FETCH_COLUMN);

        $stmtMod = $pdo->prepare('SELECT DISTINCT entity_type FROM activity_logs WHERE user_id = ? AND entity_type IS NOT NULL AND entity_type != "" ORDER BY entity_type ASC');
        $stmtMod->execute([$currentUserId]);
        $modules = $stmtMod->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $actions = $pdo->query('SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN);
        $modules = $pdo->query('SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL AND entity_type != "" ORDER BY entity_type ASC')->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $actions = [];
    $modules = [];
}

// Fetch staff activity logs
$sql = "
    SELECT a.*, u.name as user_name, u.role as user_role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    {$whereClause}
    ORDER BY a.created_at DESC, a.id DESC 
    LIMIT 100
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[GarageMS] Staff Activity Logs Fetch Error: ' . $e->getMessage());
    $logs = [];
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>staff/dashboard.php">Staff Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Activity Logs</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="text-white fw-bold mb-1"><i class="bi bi-activity me-2 text-warning"></i>Garage Operations Activity Logs</h1>
                <p class="text-slate-300 small mb-0" style="color:#cbd5e1 !important;">Real-time audit trails of bookings, service acceptances, rejections, status updates, and staff actions.</p>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2 fs-6 font-monospace fw-bold shadow-sm">
                <?= count($logs) ?> EVENT(S) LOADED
            </span>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <!-- ── Filter Controls ───────────────────────────── -->
        <div class="card gms-panel mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>staff/activity-logs.php" class="row g-3 align-items-end">
                    <div class="col-md-4 col-lg-4">
                        <label for="searchLog" class="form-label fw-semibold text-light small">Search Logs</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-warning"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                id="searchLog"
                                name="search"
                                class="form-control gms-input"
                                placeholder="Search by staff name or description..."
                                value="<?= e($search) ?>"
                            >
                        </div>
                    </div>

                    <div class="col-md-3 col-lg-3">
                        <label for="actionLog" class="form-label fw-semibold text-light small">Action Type</label>
                        <select id="actionLog" name="action" class="form-select gms-input">
                            <option value="all">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= e($act) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 col-lg-3">
                        <label for="moduleLog" class="form-label fw-semibold text-light small">Module</label>
                        <select id="moduleLog" name="module" class="form-select gms-input">
                            <option value="all">All Modules</option>
                            <?php foreach ($modules as $mod): ?>
                                <option value="<?= e($mod) ?>" <?= $moduleFilter === $mod ? 'selected' : '' ?>><?= e(ucfirst($mod)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-warning fw-bold text-dark w-100">
                            <i class="bi bi-funnel-fill me-1"></i> Filter
                        </button>
                        <?php if ($search !== '' || $actionFilter !== 'all' || $moduleFilter !== 'all'): ?>
                            <a href="<?= BASE_URL ?>staff/activity-logs.php" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Activity Logs Feed Table ─────────────────── -->
        <div class="card gms-panel shadow-sm">
            <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-white">
                    <i class="bi bi-list-check text-warning me-2"></i>Audit Trail
                </h5>
                <span class="text-muted small">Showing up to 100 recent entries</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x display-4 d-block mb-3 opacity-50"></i>
                        <h5 class="text-white fw-bold">No activity logs recorded</h5>
                        <p class="small text-muted mb-0">System events and staff actions will appear here automatically.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle gms-table">
                            <thead class="text-muted uppercase small">
                                <tr>
                                    <th class="ps-4" style="min-width: 140px;">Time</th>
                                    <th>Staff User</th>
                                    <th>Action Type</th>
                                    <th>Module</th>
                                    <th class="pe-4" style="min-width: 300px;">Details & Audit Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <!-- Time -->
                                        <td class="ps-4">
                                            <div class="text-white fw-semibold small font-monospace">
                                                <?= date('d M Y', strtotime($log['created_at'])) ?>
                                            </div>
                                            <div class="text-warning small font-monospace" style="font-size:0.75rem;">
                                                <i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($log['created_at'])) ?>
                                            </div>
                                        </td>

                                        <!-- Staff -->
                                        <td>
                                            <div class="fw-bold text-white small">
                                                <?= e($log['user_name'] ?: 'System / User #' . ($log['user_id'] ?? '')) ?>
                                            </div>
                                            <span class="badge bg-dark border border-secondary text-info text-uppercase" style="font-size:0.65rem;">
                                                <?= e($log['role'] ?? ($log['user_role'] ?? 'staff')) ?>
                                            </span>
                                        </td>

                                        <!-- Action Badge -->
                                        <td>
                                            <?php 
                                            $actionBadge = match($log['action']) {
                                                'ACCEPT_SERVICE' => 'bg-success text-white border-success',
                                                'REJECT_SERVICE' => 'bg-danger text-white border-danger',
                                                'BOOK_SERVICE'   => 'bg-warning text-dark border-warning',
                                                'REGISTER'       => 'bg-info text-dark border-info',
                                                default          => 'bg-dark border-secondary text-warning'
                                            };
                                            ?>
                                            <span class="badge <?= $actionBadge ?> font-monospace fw-bold px-2.5 py-1" style="font-size:0.75rem;">
                                                <?= e($log['action']) ?>
                                            </span>
                                        </td>

                                        <!-- Module -->
                                        <td>
                                            <span class="text-capitalize text-light fw-medium small">
                                                <i class="bi bi-folder2-open me-1 text-secondary"></i><?= e($log['entity_type'] ?: 'General') ?>
                                            </span>
                                            <?php if ($log['entity_id']): ?>
                                                <span class="text-muted font-monospace small">#<?= (int)$log['entity_id'] ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Details -->
                                        <td class="pe-4 text-light small fw-medium" style="line-height: 1.45;">
                                            <?= e($log['description'] ?: '-') ?>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

