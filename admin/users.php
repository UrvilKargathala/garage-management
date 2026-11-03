<?php
/**
 * admin/users.php — Full User Management
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 * Features: View all users, search, filter by role, view details, delete
 */
$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo = getDBConnection();

// ── Handle DELETE ─────────────────────────────────────────────
$deleteMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verifyCsrfToken();
    $delId = (int)($_POST['user_id'] ?? 0);
    $currentId = currentUserId();

    if ($delId === $currentId) {
        $deleteMsg = 'danger:You cannot delete your own account.';
    } elseif ($delId > 0) {
        try {
            // Check not the last admin
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            $targetRole = $pdo->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
            $targetRole->execute([$delId]);
            $targetRoleVal = $targetRole->fetchColumn();

            if ($targetRoleVal === 'admin' && $adminCount <= 1) {
                $deleteMsg = 'danger:Cannot delete the last Administrator account.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delId]);
                logActivity($pdo, 'DELETE_USER', "Admin deleted user ID $delId.", 'user', $delId);
                setFlash('User deleted successfully.', 'success');
                header('Location: ' . BASE_URL . 'admin/users.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('[GarageMS] Admin delete user: ' . $e->getMessage());
            $deleteMsg = 'danger:Could not delete user. They may have associated records.';
        }
    }
}

// ── Read filters ──────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? 'all');
$validRoles = ['all', 'admin', 'staff', 'customer'];
if (!in_array($roleFilter, $validRoles, true)) $roleFilter = 'all';

// ── Build query ───────────────────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $where[]           = '(u.name LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)';
    $params['s1']      = '%' . $search . '%';
    $params['s2']      = '%' . $search . '%';
    $params['s3']      = '%' . $search . '%';
}
if ($roleFilter !== 'all') {
    $where[]           = 'u.role = :role';
    $params['role']    = $roleFilter;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $sql = "SELECT u.id, u.name, u.email, u.role, u.phone, u.created_at,
                   (SELECT COUNT(*) FROM customers c WHERE c.user_id = u.id) AS customer_records,
                   (SELECT MAX(l.login_time) FROM login_logs l WHERE l.user_id = u.id) AS last_login
              FROM users u
              $whereClause
             ORDER BY u.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    $totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalAdmins    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    $totalStaff     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
} catch (PDOException $e) {
    error_log('[GarageMS] Admin users list: ' . $e->getMessage());
    $users = [];
    $totalUsers = $totalAdmins = $totalStaff = $totalCustomers = 0;
}

// Parse delete message
$flashType = ''; $flashMsg = '';
if ($deleteMsg) {
    [$flashType, $flashMsg] = explode(':', $deleteMsg, 2);
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">

    <?php if ($flashMsg): ?>
        <div class="alert alert-<?= e($flashType) ?> alert-dismissible fade show mb-3" role="alert">
            <?= e($flashMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php showFlash(); ?>

    <!-- Page Header -->
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-people-fill"></i>User Management</h1>
            <div class="admin-breadcrumb">
                <a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Users
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon blue"><i class="bi bi-people-fill"></i></div>
                <div class="admin-stat-body">
                    <div class="admin-stat-value"><?= number_format($totalUsers) ?></div>
                    <div class="admin-stat-label">Total Users</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon orange"><i class="bi bi-shield-lock-fill"></i></div>
                <div class="admin-stat-body">
                    <div class="admin-stat-value"><?= number_format($totalAdmins) ?></div>
                    <div class="admin-stat-label">Administrators</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon cyan"><i class="bi bi-wrench-adjustable-circle-fill"></i></div>
                <div class="admin-stat-body">
                    <div class="admin-stat-value"><?= number_format($totalStaff) ?></div>
                    <div class="admin-stat-label">Staff / Mechanics</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="admin-stat-icon purple"><i class="bi bi-person-fill"></i></div>
                <div class="admin-stat-body">
                    <div class="admin-stat-value"><?= number_format($totalCustomers) ?></div>
                    <div class="admin-stat-label">Customers</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="admin-filter-bar">
        <form method="GET" action="<?= BASE_URL ?>admin/users.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label text-muted small fw-semibold mb-1">Search Users</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control gms-input"
                           placeholder="Name, email or phone..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-semibold mb-1">Role</label>
                <select name="role" class="form-select gms-input">
                    <option value="all"     <?= $roleFilter==='all'      ? 'selected' : '' ?>>All Roles</option>
                    <option value="admin"   <?= $roleFilter==='admin'    ? 'selected' : '' ?>>Admin</option>
                    <option value="staff"   <?= $roleFilter==='staff'    ? 'selected' : '' ?>>Staff / Mechanic</option>
                    <option value="customer"<?= $roleFilter==='customer' ? 'selected' : '' ?>>Customer</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-gms-primary px-4">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                <?php if ($search !== '' || $roleFilter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h5 class="admin-card-title"><i class="bi bi-list-ul"></i>
                <?= $search || $roleFilter !== 'all' ? 'Filtered Results' : 'All Users' ?>
                <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= count($users) ?></span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8">
                            <div class="admin-empty">
                                <i class="bi bi-person-x"></i>
                                <p>No users found<?= $search ? " for \"" . e($search) . "\"" : '' ?>.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="cell-id">#<?= $u['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="admin-avatar-sm">
                                            <?= strtoupper(mb_substr($u['name'], 0, 1)) ?>
                                        </div>
                                        <span class="cell-name"><?= e($u['name']) ?></span>
                                    </div>
                                </td>
                                <td class="text-muted small"><?= e($u['email']) ?></td>
                                <td class="text-muted small"><?= e($u['phone'] ?? '—') ?></td>
                                <td>
                                    <?php
                                    $roleBadge = match($u['role']) {
                                        'admin'    => 'admin-badge-admin',
                                        'staff'    => 'admin-badge-staff',
                                        'customer' => 'admin-badge-customer',
                                        default    => 'admin-badge-inactive',
                                    };
                                    ?>
                                    <span class="admin-badge <?= $roleBadge ?>"><?= e($u['role']) ?></span>
                                </td>
                                <td class="small text-muted">
                                    <?= $u['last_login'] ? formatDateTime($u['last_login']) : '<span class="opacity-50">Never</span>' ?>
                                </td>
                                <td class="small text-muted">
                                    <?= formatDate($u['created_at']) ?>
                                </td>
                                <td>
                                    <div class="admin-btn-group">
                                        <button type="button" class="btn btn-outline-info btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#userModal<?= $u['id'] ?>"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($u['id'] !== currentUserId()): ?>
                                            <button type="button"
                                                    class="btn btn-outline-danger btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal<?= $u['id'] ?>"
                                                    title="Delete User">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- View Details Modal -->
                            <div class="modal fade" id="userModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content" style="background:#1c2333;border:1px solid rgba(255,255,255,0.1);">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title text-white fw-bold">
                                                <i class="bi bi-person-circle me-2 text-warning"></i>User Details
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="admin-detail-row">
                                                <span class="admin-detail-label">User ID</span>
                                                <span class="admin-detail-value cell-id">#<?= $u['id'] ?></span>
                                            </div>
                                            <div class="admin-detail-row">
                                                <span class="admin-detail-label">Full Name</span>
                                                <span class="admin-detail-value"><?= e($u['name']) ?></span>
                                            </div>
                                            <div class="admin-detail-row">
                                                <span class="admin-detail-label">Email</span>
                                                <span class="admin-detail-value"><?= e($u['email']) ?></span>
                                            </div>
                                            <div class="admin-detail-row">
                                                <span class="admin-detail-label">Phone</span>
                                                <span class="admin-detail-value"><?= e($u['phone'] ?? '—') ?></span>
                                            </div>
                                            <div class="admin-detail-row">
                                                <span class="admin-detail-label">Role</span>
                                                <span class="admin-detail-value">
                                                    <span class="admin-badge <?= $roleBadge ?>"><?= e($u['role']) ?></span>
                                                </span>
                                            </div>
                                            <div class="admin-detail-row">
                                                <span class="admin-detail-label">Joined</span>
                                                <span class="admin-detail-value"><?= formatDateTime($u['created_at']) ?></span>
                                            </div>
                                            <div class="admin-detail-row">
                                                <span class="admin-detail-label">Last Login</span>
                                                <span class="admin-detail-value">
                                                    <?= $u['last_login'] ? formatDateTime($u['last_login']) : 'Never logged in' ?>
                                                </span>
                                            </div>
                                            <?php if ($u['customer_records']): ?>
                                            <div class="admin-detail-row">
                                                <span class="admin-detail-label">Customer Profile</span>
                                                <span class="admin-detail-value text-success">Linked</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer border-0">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Confirm Modal -->
                            <?php if ($u['id'] !== currentUserId()): ?>
                            <div class="modal fade" id="deleteModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-sm">
                                    <div class="modal-content" style="background:#1c2333;border:1px solid rgba(239,68,68,0.3);">
                                        <div class="modal-body text-center py-4">
                                            <i class="bi bi-exclamation-triangle-fill text-danger display-4 d-block mb-3"></i>
                                            <h6 class="text-white fw-bold mb-1">Delete User?</h6>
                                            <p class="text-muted small mb-3">
                                                <strong><?= e($u['name']) ?></strong> will be permanently removed.
                                                This cannot be undone.
                                            </p>
                                            <form method="POST" action="<?= BASE_URL ?>admin/users.php">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="bi bi-trash3 me-1"></i>Delete
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.admin-content -->

<?php
require_once __DIR__ . '/includes/admin_footer.php';
require_once __DIR__ . '/../includes/footer.php';
?>
