<?php
/**
 * admin/logs.php — Activity & Login Logs Management
 * Garage Management System
 *
 * Access Control:
 *  ✔ Restricted strictly to Admin users via requireAdmin()
 *  ✔ Unauthenticated users or non-admin roles (customers, staff) are blocked & redirected
 *
 * Requirements:
 *  ✔ View User Name, Email, Login Time, Logout Time, IP Address, Status
 *  ✔ Search by user (Name or Email)
 *  ✔ Filter by Status (logged_in / logged_out / all)
 *  ✔ Sort newest activity first (ORDER BY login_time DESC)
 *  ✔ PDO prepared statements with parameterized inputs
 */

$pageTitle = 'Login Activity Logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// ── ACCESS CONTROL ────────────────────────────────────────────
// Only Admin users can access this page. Customers and Staff are blocked.
requireAdmin();

// ── Read & Sanitise Query Parameters ──────────────────────────
$searchQuery  = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$validStatuses = ['all', 'logged_in', 'logged_out'];

if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}

// ── Build Database Query using PDO Prepared Statements ─────────
$pdo   = getDBConnection();
$where = [];
$params = [];

if ($searchQuery !== '') {
    $where[]  = '(u.name LIKE :search1 OR u.email LIKE :search2)';
    $params['search1'] = '%' . $searchQuery . '%';
    $params['search2'] = '%' . $searchQuery . '%';
}

if ($statusFilter !== 'all') {
    $where[]  = 'l.status = :status';
    $params['status'] = $statusFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Fetch Log Records (Sorted Newest Activity First) ─────────
$sql = "SELECT l.id,
               l.user_id,
               l.login_time,
               l.logout_time,
               l.ip_address,
               l.status,
               u.name AS user_name,
               u.email AS user_email,
               u.role AS user_role
          FROM login_logs l
     LEFT JOIN users u ON l.user_id = u.id
       {$whereClause}
      ORDER BY l.login_time DESC, l.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[GarageMS] Admin Logs Fetch Error: ' . $e->getMessage());
    $logs = [];
}

// ── Calculate Summary Statistics ──────────────────────────────
$totalCount     = count($logs);
$activeSessions = 0;
$loggedOutCount = 0;

foreach ($logs as $log) {
    if ($log['status'] === 'logged_in') {
        $activeSessions++;
    } else {
        $loggedOutCount++;
    }
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>

<!-- ════════════════════════════════════════════════════════════
     PAGE CONTENT
     ════════════════════════════════════════════════════════════ -->
<div class="admin-content">

    <div class="admin-page-header">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Login Logs</li>
                </ol>
            </nav>
            <h1 class="admin-page-title">
                <i class="bi bi-shield-lock-fill text-warning"></i>Login Activity Logs
            </h1>
            <span class="text-muted small">Comprehensive audit trail of user sessions</span>
        </div>
        <div>
            <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-semibold">
                <i class="bi bi-person-shield me-1"></i>Admin Access Only
            </span>
        </div>
    </div>

    <?php showFlash(); ?>

    <!-- ── STAT CARDS ────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="gms-stat-card">
                <div class="gms-stat-card-icon blue"><i class="bi bi-journal-text"></i></div>
                <div class="gms-stat-card-body">
                    <div class="value"><?= number_format($totalCount) ?></div>
                    <div class="label">Total Filtered Logs</div>
                </div>
            </div>
        </div>
            <div class="col-sm-6 col-lg-4">
                <div class="gms-stat-card">
                    <div class="gms-stat-card-icon green"><i class="bi bi-person-check-fill"></i></div>
                    <div class="gms-stat-card-body">
                        <div class="value"><?= number_format($activeSessions) ?></div>
                        <div class="label">Active Sessions (Logged In)</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="gms-stat-card">
                    <div class="gms-stat-card-icon purple"><i class="bi bi-person-x-fill"></i></div>
                    <div class="gms-stat-card-body">
                        <div class="value"><?= number_format($loggedOutCount) ?></div>
                        <div class="label">Completed Sessions (Logged Out)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── FILTER & SEARCH PANEL ─────────────────────────── -->
        <div class="card gms-panel mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>admin/logs.php" class="row g-3 align-items-end">

                    <!-- Search Input -->
                    <div class="col-md-5 col-lg-4">
                        <label for="searchInput" class="form-label fw-semibold text-muted small">Search User / Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                id="searchInput"
                                name="search"
                                class="form-control gms-input"
                                placeholder="Search by name or email..."
                                value="<?= e($searchQuery) ?>"
                            >
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-md-4 col-lg-3">
                        <label for="statusSelect" class="form-label fw-semibold text-muted small">Session Status</label>
                        <select id="statusSelect" name="status" class="form-select gms-input">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="logged_in" <?= $statusFilter === 'logged_in' ? 'selected' : '' ?>>Logged In (Active)</option>
                            <option value="logged_out" <?= $statusFilter === 'logged_out' ? 'selected' : '' ?>>Logged Out</option>
                        </select>
                    </div>

                    <!-- Submit & Reset Buttons -->
                    <div class="col-md-3 col-lg-5 d-flex gap-2">
                        <button type="submit" class="btn btn-gms-primary px-4">
                            <i class="bi bi-filter me-1"></i> Filter Logs
                        </button>
                        <?php if ($searchQuery !== '' || $statusFilter !== 'all'): ?>
                            <a href="<?= BASE_URL ?>admin/logs.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

        <!-- ── LOGS TABLE ────────────────────────────────────── -->
        <div class="card gms-panel shadow-sm">
            <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-light">
                    <i class="bi bi-list-task me-2 text-warning"></i>Session Activity Trail
                </h5>
                <span class="text-muted small">
                    <i class="bi bi-sort-down me-1"></i>Sorted Newest First
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 gms-table">
                        <thead class="table-dark text-muted uppercase small">
                            <tr>
                                <th scope="col" class="ps-4">User Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Login Time</th>
                                <th scope="col">Logout Time</th>
                                <th scope="col">IP Address</th>
                                <th scope="col" class="pe-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-journal-x display-4 d-block mb-3 opacity-50"></i>
                                        <p class="h6">No activity logs found</p>
                                        <small>Try adjusting your search query or filter options.</small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $row): ?>
                                    <tr>
                                        <!-- User Name -->
                                        <td class="ps-4 fw-semibold text-light">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="gms-avatar-sm" aria-hidden="true">
                                                    <?= strtoupper(mb_substr($row['user_name'] ?? 'U', 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <span><?= e($row['user_name'] ?? 'Unknown User') ?></span>
                                                    <?php if (!empty($row['user_role'])): ?>
                                                        <small class="d-block text-muted text-uppercase" style="font-size:0.68rem;">
                                                            <?= e($row['user_role']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Email -->
                                        <td>
                                            <span class="text-muted small">
                                                <i class="bi bi-envelope me-1 opacity-75"></i>
                                                <?= e($row['user_email'] ?? 'N/A') ?>
                                            </span>
                                        </td>

                                        <!-- Login Time -->
                                        <td>
                                            <span class="badge bg-dark-subtle text-light border border-secondary px-2.5 py-1.5 font-monospace">
                                                <i class="bi bi-box-arrow-in-right text-success me-1"></i>
                                                <?= e(formatDateTime($row['login_time'])) ?>
                                            </span>
                                        </td>

                                        <!-- Logout Time -->
                                        <td>
                                            <?php if (!empty($row['logout_time'])): ?>
                                                <span class="badge bg-dark-subtle text-muted border border-secondary px-2.5 py-1.5 font-monospace">
                                                    <i class="bi bi-box-arrow-right text-warning me-1"></i>
                                                    <?= e(formatDateTime($row['logout_time'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-success small fw-semibold">
                                                    <i class="bi bi-clock-history me-1"></i>Active Session
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- IP Address -->
                                        <td>
                                            <code class="text-warning-subtle small px-2 py-1 bg-dark rounded border border-secondary">
                                                <?= e($row['ip_address'] ?? '127.0.0.1') ?>
                                            </code>
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="pe-4 text-center">
                                            <?php if ($row['status'] === 'logged_in'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill">
                                                    <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i> logged_in
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-light border border-secondary px-3 py-1.5 rounded-pill opacity-75">
                                                    <i class="bi bi-circle me-1" style="font-size:0.5rem;"></i> logged_out
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

<style>
.gms-avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--grad-accent);
    color: #fff;
    font-size: 0.85rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.gms-table tbody tr {
    transition: background-color 0.15s ease-in-out;
}
.gms-table tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.04) !important;
}
</style>

</div><!-- /.admin-content -->

<?php
require_once __DIR__ . '/includes/admin_footer.php';
require_once __DIR__ . '/../includes/footer.php';
?>
