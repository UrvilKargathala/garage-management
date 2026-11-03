<?php
/**
 * services/list.php — Garage Services Catalogue
 * Garage Management System
 *
 * Requirements & Scoping:
 *  ✔ Admin / Staff can view all services (active & inactive) + manage them (Add, Edit, Delete)
 *  ✔ Customers can ONLY view ACTIVE services (`status = 'active'`) with "Book Now" CTA
 *  ✔ All service data comes dynamically from MySQL database (zero hardcoding)
 *  ✔ PDO prepared statements
 */

$pageTitle = 'Services Catalogue';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

$where = [];
$params = [];

// Customers only see active services
if (hasRole('customer')) {
    $where[] = "status = 'active'";
} elseif ($statusFilter !== 'all') {
    $where[] = 'status = :status';
    $params['status'] = $statusFilter;
}

if ($search !== '') {
    $where[] = '(service_name LIKE :search1 OR description LIKE :search2)';
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT * FROM services {$whereClause} ORDER BY id ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[GarageMS] Services Fetch Error: ' . $e->getMessage());
    $services = [];
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Services</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1><i class="bi bi-tools me-2 text-warning"></i>Garage Services Catalogue</h1>
            <?php if (hasRole(['admin', 'staff'])): ?>
                <a href="<?= BASE_URL ?>services/add.php" class="btn btn-gms-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add New Service
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <!-- ── Search & Status Filter Panel ─────────────────── -->
        <div class="card gms-panel mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>services/list.php" class="row g-3 align-items-end">
                    <div class="col-md-6 col-lg-5">
                        <label for="searchSvc" class="form-label fw-semibold text-muted small">Search Services</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                id="searchSvc"
                                name="search"
                                class="form-control gms-input"
                                placeholder="Search service by name or description..."
                                value="<?= e($search) ?>"
                            >
                        </div>
                    </div>

                    <?php if (hasRole(['admin', 'staff'])): ?>
                        <div class="col-md-4 col-lg-3">
                            <label for="statusSvc" class="form-label fw-semibold text-muted small">Status</label>
                            <select id="statusSvc" name="status" class="form-select gms-input">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-gms-primary px-4 w-100">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                        <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                            <a href="<?= BASE_URL ?>services/list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── CUSTOMER GRID VIEW / STAFF TABLE VIEW ───────── -->
        <?php if (hasRole('customer')): ?>
            <!-- Customer Card View -->
            <div class="row g-4">
                <?php if (empty($services)): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-tools display-4 d-block mb-3 opacity-50"></i>
                        <p class="h6">No active services match your criteria.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($services as $s): ?>
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <div class="card gms-panel h-100 shadow-sm p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="gms-service-icon">
                                            <i class="bi bi-wrench-adjustable text-warning"></i>
                                        </div>
                                        <span class="fs-5 fw-bold text-warning font-monospace">
                                            ₹<?= number_format($s['price'], 2) ?>
                                        </span>
                                    </div>
                                    <h4 class="h5 fw-bold text-light mb-2"><?= e($s['service_name']) ?></h4>
                                    <p class="text-muted small mb-3" style="min-height: 48px;">
                                        <?= e($s['description'] ?: 'Professional garage service provided by expert technicians.') ?>
                                    </p>
                                </div>
                                <div>
                                    <?php if ($s['estimated_time']): ?>
                                        <div class="small text-muted mb-3">
                                            <i class="bi bi-clock me-1 text-warning"></i> Est. Time: <?= e($s['estimated_time']) ?> hour(s)
                                        </div>
                                    <?php endif; ?>
                                    <a href="<?= BASE_URL ?>bookings/create.php?service_id=<?= $s['id'] ?>" class="btn btn-gms-primary w-100">
                                        <i class="bi bi-calendar-plus me-1"></i> Book Service
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Staff & Admin Table View -->
            <div class="card gms-panel shadow-sm">
                <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0 fw-bold text-light">
                        <i class="bi bi-gear-wide-connected me-2 text-warning"></i>Services Catalogue
                    </h5>
                    <span class="text-muted small">Total: <?= count($services) ?> Service(s)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0 gms-table">
                            <thead class="table-dark text-muted uppercase small">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Service Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Est. Time</th>
                                    <th class="text-center">Status</th>
                                    <th class="pe-4 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($services)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-tools display-5 d-block mb-3 opacity-50"></i>
                                            <p class="h6">No services found in database.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($services as $s): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge bg-dark border border-secondary text-warning font-monospace px-2.5 py-1 fw-bold fs-6">
                                                    #SVC-<?= $s['id'] ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-white fs-6">
                                                <?= e($s['service_name']) ?>
                                            </td>
                                            <td class="text-light small" style="min-width: 220px; max-width: 380px; white-space: normal; line-height: 1.45;">
                                                <?= e($s['description'] ?: 'Standard manufacturer recommended garage service and inspection.') ?>
                                            </td>
                                            <td class="fw-bold text-warning font-monospace fs-6">
                                                ₹<?= number_format($s['price'], 2) ?>
                                            </td>
                                            <td class="small text-white fw-semibold">
                                                <?= $s['estimated_time'] ? e($s['estimated_time']) . ' hr(s)' : '1 hr' ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($s['status'] === 'active'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill">
                                                        <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i> active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-muted border border-secondary px-3 py-1.5 rounded-pill opacity-75">
                                                        <i class="bi bi-circle me-1" style="font-size:0.5rem;"></i> inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= BASE_URL ?>services/edit.php?id=<?= $s['id'] ?>" class="btn btn-outline-warning" title="Edit Service">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </a>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-danger"
                                                        title="Delete Service"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteSvcModal<?= $s['id'] ?>"
                                                    >
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </div>

                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade text-start" id="deleteSvcModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content bg-dark border-secondary text-light">
                                                            <div class="modal-header border-secondary">
                                                                <h5 class="modal-title text-danger">
                                                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete the service <strong><?= e($s['service_name']) ?></strong>?</p>
                                                            </div>
                                                            <div class="modal-footer border-secondary">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <a href="<?= BASE_URL ?>services/delete.php?id=<?= $s['id'] ?>" class="btn btn-danger">
                                                                    <i class="bi bi-trash me-1"></i> Yes, Delete Service
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
.gms-service-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(249, 115, 22, 0.12);
    border: 1px solid rgba(249, 115, 22, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
