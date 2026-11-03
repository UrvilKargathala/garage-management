<?php
/**
 * admin/services.php — Service Catalogue Management
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 * Features: View, Add, Edit, Delete services with search
 */
$pageTitle = 'Service Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo = getDBConnection();

// ── Handle POST actions ───────────────────────────────────────
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name   = trim($_POST['service_name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $price  = trim($_POST['price'] ?? '');
        $estTime = trim($_POST['estimated_time'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        // Validate
        $err = validateServiceName($name);   if ($err) $errors[] = $err;
        $err = validateServicePrice($price); if ($err) $errors[] = $err;
        if ($estTime !== '') { $err = validateEstimatedTime($estTime); if ($err) $errors[] = $err; }
        $err = validateServiceStatus($status); if ($err) $errors[] = $err;

        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO services (service_name, description, price, estimated_time, status)
                         VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$name, $desc ?: null, (float)$price, $estTime !== '' ? (float)$estTime : null, $status]);
                    logActivity($pdo, 'ADD_SERVICE', "Admin added service: $name", 'service', (int)$pdo->lastInsertId());
                    setFlash("Service \"$name\" added successfully.", 'success');
                } else {
                    $editId = (int)($_POST['service_id'] ?? 0);
                    // Check if service has bookings before allowing deactivation
                    if ($status === 'inactive') {
                        $activeBookings = (int)$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE service_id=? AND status IN ('pending','confirmed','in_progress')")->execute([$editId]);
                    }
                    $stmt = $pdo->prepare(
                        "UPDATE services SET service_name=?, description=?, price=?, estimated_time=?, status=?
                         WHERE id=?"
                    );
                    $stmt->execute([$name, $desc ?: null, (float)$price, $estTime !== '' ? (float)$estTime : null, $status, $editId]);
                    logActivity($pdo, 'UPDATE_SERVICE', "Admin updated service ID: $editId", 'service', $editId);
                    setFlash("Service \"$name\" updated successfully.", 'success');
                }
                header('Location: ' . BASE_URL . 'admin/services.php');
                exit;
            } catch (PDOException $e) {
                error_log('[GarageMS] Admin services save: ' . $e->getMessage());
                $errors[] = 'Database error: Could not save service.';
            }
        }
    }

    if ($action === 'delete') {
        $delId = (int)($_POST['service_id'] ?? 0);
        if ($delId > 0) {
            try {
                // Check active bookings
                $activeStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE service_id=? AND status IN ('pending','confirmed','in_progress')");
                $activeStmt->execute([$delId]);
                if ((int)$activeStmt->fetchColumn() > 0) {
                    setFlash('Cannot delete: This service has active/pending bookings.', 'danger');
                } else {
                    $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$delId]);
                    logActivity($pdo, 'DELETE_SERVICE', "Admin deleted service ID: $delId", 'service', $delId);
                    setFlash('Service deleted successfully.', 'success');
                }
            } catch (PDOException $e) {
                error_log('[GarageMS] Admin delete service: ' . $e->getMessage());
                setFlash('Cannot delete service — it may have associated bookings.', 'danger');
            }
        }
        header('Location: ' . BASE_URL . 'admin/services.php');
        exit;
    }
}

// ── Fetch data ─────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$conds        = [];
$params       = [];

if ($search !== '') {
    $conds[]       = '(s.service_name LIKE :s1 OR s.description LIKE :s2)';
    $params['s1']  = '%'.$search.'%';
    $params['s2']  = '%'.$search.'%';
}
if ($statusFilter !== 'all') {
    $conds[]           = 's.status = :st';
    $params['st']      = $statusFilter;
}
$wc = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

try {
    $sql = "SELECT s.*, COUNT(b.id) AS booking_count
              FROM services s
         LEFT JOIN bookings b ON b.service_id = s.id
              $wc
          GROUP BY s.id
          ORDER BY s.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll();

    $totalServices  = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    $activeServices = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='active'")->fetchColumn();

    // Edit data
    $editService = null;
    if (isset($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        $stmtE  = $pdo->prepare("SELECT * FROM services WHERE id=? LIMIT 1");
        $stmtE->execute([$editId]);
        $editService = $stmtE->fetch();
    }

} catch (PDOException $e) {
    error_log('[GarageMS] Admin services list: ' . $e->getMessage());
    $services = []; $totalServices = $activeServices = 0;
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-tools"></i>Service Management</h1>
            <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Services</div>
        </div>
        <button type="button" class="btn btn-gms-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addServiceModal">
            <i class="bi bi-plus-circle me-1"></i>Add New Service
        </button>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon purple"><i class="bi bi-tools"></i></div>
                <div><div class="admin-stat-value"><?= $totalServices ?></div><div class="admin-stat-label">Total Services</div></div>
            </div>
        </div>
        <div class="col-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div><div class="admin-stat-value"><?= $activeServices ?></div><div class="admin-stat-label">Active Services</div></div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="admin-filter-bar">
        <form method="GET" action="<?= BASE_URL ?>admin/services.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label text-muted small fw-semibold mb-1">Search Services</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control gms-input"
                           placeholder="Service name or description..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select gms-input">
                    <option value="all"     <?= $statusFilter==='all'?'selected':'' ?>>All Statuses</option>
                    <option value="active"  <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive"<?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-gms-primary px-4"><i class="bi bi-filter me-1"></i>Filter</button>
                <?php if ($search || $statusFilter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>admin/services.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h5 class="admin-card-title"><i class="bi bi-list-ul"></i>Service Catalogue
                <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= count($services) ?></span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Est. Time</th>
                        <th>Total Bookings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr><td colspan="8">
                            <div class="admin-empty"><i class="bi bi-tools"></i><p>No services found.</p></div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($services as $s): ?>
                        <tr>
                            <td class="cell-id">#<?= $s['id'] ?></td>
                            <td class="cell-name"><?= e($s['service_name']) ?></td>
                            <td class="text-muted small" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= e($s['description'] ?? '—') ?>
                            </td>
                            <td class="text-success fw-bold font-monospace">₹<?= number_format((float)$s['price'],2) ?></td>
                            <td class="text-muted small">
                                <?= $s['estimated_time'] ? number_format((float)$s['estimated_time'],1) . ' hr' : '—' ?>
                            </td>
                            <td><span class="badge bg-primary"><?= $s['booking_count'] ?></span></td>
                            <td>
                                <span class="admin-badge <?= $s['status']==='active'?'admin-badge-active':'admin-badge-inactive' ?>">
                                    <?= $s['status'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="admin-btn-group">
                                    <a href="<?= BASE_URL ?>admin/services.php?edit=<?= $s['id'] ?>"
                                       class="btn btn-outline-warning btn-sm" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteServiceModal<?= $s['id'] ?>" title="Delete">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Delete Confirm -->
                        <div class="modal fade" id="deleteServiceModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-sm">
                                <div class="modal-content" style="background:#1c2333;border:1px solid rgba(239,68,68,0.3);">
                                    <div class="modal-body text-center py-4">
                                        <i class="bi bi-exclamation-triangle-fill text-danger display-4 d-block mb-3"></i>
                                        <h6 class="text-white fw-bold mb-2">Delete Service?</h6>
                                        <p class="text-muted small mb-3"><strong><?= e($s['service_name']) ?></strong> will be permanently removed.</p>
                                        <form method="POST" action="<?= BASE_URL ?>admin/services.php">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash3 me-1"></i>Delete</button>
                                            </div>
                                        </form>
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

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#1c2333;border:1px solid rgba(249,115,22,0.2);">
            <form method="POST" action="<?= BASE_URL ?>admin/services.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-white fw-bold"><i class="bi bi-plus-circle me-2 text-warning"></i>Add New Service</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Service Name *</label>
                        <input type="text" name="service_name" class="form-control gms-input" required maxlength="100" placeholder="e.g. Full Car Service">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Description</label>
                        <textarea name="description" class="form-control gms-input" rows="3" placeholder="Detailed description of the service..."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-semibold">Price (₹) *</label>
                            <input type="number" name="price" class="form-control gms-input" required step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-semibold">Est. Time (hours)</label>
                            <input type="number" name="estimated_time" class="form-control gms-input" step="0.5" min="0" placeholder="1.5">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-muted small fw-semibold">Status</label>
                        <select name="status" class="form-select gms-input">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gms-primary"><i class="bi bi-plus-circle me-1"></i>Add Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Service Modal (if ?edit= is set) -->
<?php if ($editService): ?>
<script>document.addEventListener('DOMContentLoaded', function() { new bootstrap.Modal(document.getElementById('editServiceModal')).show(); });</script>
<div class="modal fade" id="editServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#1c2333;border:1px solid rgba(249,115,22,0.2);">
            <form method="POST" action="<?= BASE_URL ?>admin/services.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="service_id" value="<?= $editService['id'] ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-white fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Service</h5>
                    <a href="<?= BASE_URL ?>admin/services.php" class="btn-close btn-close-white"></a>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Service Name *</label>
                        <input type="text" name="service_name" class="form-control gms-input" required maxlength="100"
                               value="<?= e($editService['service_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Description</label>
                        <textarea name="description" class="form-control gms-input" rows="3"><?= e($editService['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-semibold">Price (₹) *</label>
                            <input type="number" name="price" class="form-control gms-input" required step="0.01" min="0"
                                   value="<?= e($editService['price']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-semibold">Est. Time (hours)</label>
                            <input type="number" name="estimated_time" class="form-control gms-input" step="0.5" min="0"
                                   value="<?= e($editService['estimated_time'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-muted small fw-semibold">Status</label>
                        <select name="status" class="form-select gms-input">
                            <option value="active"   <?= $editService['status']==='active'   ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $editService['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <a href="<?= BASE_URL ?>admin/services.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-gms-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
require_once __DIR__ . '/../includes/footer.php';
?>
