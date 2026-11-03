<?php
/**
 * customer/activity.php — Customer Activity Log
 * Garage Management System
 *
 * Security & Access Control:
 *  ✔ Strictly restricted to authenticated Customer role via requireCustomer()
 *  ✔ Retrieves ONLY activity logs belonging to currentUserId()
 *  ✔ Never trusts user_id from GET/POST parameters
 */
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireCustomer();

$pdo = getDBConnection();
$userId = currentUserId();

$search       = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? 'all');

$where  = ['al.user_id = :current_user_id'];
$params = ['current_user_id' => $userId];

if ($search !== '') {
    $where[] = '(al.description LIKE :s1 OR al.action LIKE :s2)';
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
}

if ($actionFilter !== 'all') {
    $where[] = 'al.action = :action';
    $params['action'] = $actionFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    // Fetch distinct actions for this customer
    $stmtAct = $pdo->prepare("SELECT DISTINCT action FROM activity_logs WHERE user_id = ? ORDER BY action ASC");
    $stmtAct->execute([$userId]);
    $availableActions = $stmtAct->fetchAll(PDO::FETCH_COLUMN);

    // Fetch customer logs
    $stmt = $pdo->prepare("
        SELECT al.* 
          FROM activity_logs al
        {$whereClause}
      ORDER BY al.created_at DESC, al.id DESC
         LIMIT 100
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total count for this customer
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ?");
    $stmtCount->execute([$userId]);
    $totalCustomerLogs = (int)$stmtCount->fetchColumn();

} catch (PDOException $e) {
    error_log('[GarageMS] Customer Activity Error: ' . $e->getMessage());
    $logs = [];
    $availableActions = [];
    $totalCustomerLogs = 0;
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customer/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Activity Log</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="text-white fw-bold mb-1"><i class="bi bi-activity me-2 text-warning"></i>My Activity History</h1>
                <p class="text-slate-300 small mb-0" style="color:#cbd5e1 !important;">Your account actions, vehicle registrations, and service bookings.</p>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2 fs-6 font-monospace fw-bold shadow-sm">
                <?= count($logs) ?> of <?= $totalCustomerLogs ?> RECORD(S)
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
                <form method="GET" action="<?= BASE_URL ?>customer/activity.php" class="row g-3 align-items-end">
                    <div class="col-md-6 col-lg-5">
                        <label for="searchLog" class="form-label fw-semibold text-light small">Search Activity</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-warning"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                id="searchLog"
                                name="search"
                                class="form-control gms-input"
                                placeholder="Search by action or details..."
                                value="<?= e($search) ?>"
                            >
                        </div>
                    </div>

                    <div class="col-md-4 col-lg-4">
                        <label for="actionLog" class="form-label fw-semibold text-light small">Action Type</label>
                        <select id="actionLog" name="action" class="form-select gms-input">
                            <option value="all">All Actions</option>
                            <?php foreach ($availableActions as $act): ?>
                                <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= e($act) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-warning fw-bold text-dark w-100">
                            <i class="bi bi-funnel-fill me-1"></i> Filter
                        </button>
                        <?php if ($search !== '' || $actionFilter !== 'all'): ?>
                            <a href="<?= BASE_URL ?>customer/activity.php" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Timeline Card ─────────────────────────────── -->
        <div class="card gms-panel shadow-sm">
            <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-white">
                    <i class="bi bi-list-check text-warning me-2"></i>My Activity Timeline
                </h5>
                <span class="text-slate-300 small" style="color:#cbd5e1 !important;">Showing recent entries</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x display-4 d-block mb-3 opacity-50"></i>
                        <h5 class="text-white fw-bold">No activity recorded</h5>
                        <p class="small text-muted mb-0"><?= $search || $actionFilter !== 'all' ? 'No logs match your filter criteria.' : 'Your bookings, vehicle registrations, and account actions will log here.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle gms-table">
                            <thead class="text-muted uppercase small">
                                <tr>
                                    <th class="ps-4" style="min-width: 150px;">Date & Time</th>
                                    <th>Action Type</th>
                                    <th>Module</th>
                                    <th class="pe-4">Details & Notes</th>
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

                                        <!-- Action Badge -->
                                        <td>
                                            <?php 
                                            $badgeClass = match($log['action']) {
                                                'REGISTER', 'User registered' => 'bg-info text-dark border-info',
                                                'BOOK_SERVICE'                => 'bg-warning text-dark border-warning',
                                                'ADD_VEHICLE', 'Vehicle Registered' => 'bg-success text-white border-success',
                                                'UPDATE_VEHICLE', 'Vehicle Updated' => 'bg-primary text-white border-primary',
                                                'CANCEL_BOOKING', 'CANCEL_SERVICE'  => 'bg-danger text-white border-danger',
                                                'User login'                  => 'bg-success-subtle text-success border border-success',
                                                'User logout'                 => 'bg-secondary-subtle text-muted border border-secondary',
                                                default                       => 'bg-dark border-secondary text-warning'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?> font-monospace fw-bold px-2.5 py-1" style="font-size:0.75rem;">
                                                <?= e($log['action']) ?>
                                            </span>
                                        </td>

                                        <!-- Module -->
                                        <td>
                                            <span class="text-capitalize text-light fw-medium small">
                                                <?= e($log['entity_type'] ?: 'Account') ?>
                                                <?= $log['entity_id'] ? ' #' . (int)$log['entity_id'] : '' ?>
                                            </span>
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

