<?php
/**
 * customers/list.php — Customer Directory & Management
 * Garage Management System
 *
 * Requirements:
 *  ✔ Staff or Admin access (`requireStaffOrAdmin()`)
 *  ✔ Search by name, phone, or email
 *  ✔ Pagination support
 *  ✔ Displays vehicle count per customer
 *  ✔ Direct action links (View, Edit, Delete confirmation)
 */

$pageTitle = 'Customer Directory';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireStaffOrAdmin();

$pdo = getDBConnection();

// ── Search & Pagination parameters ───────────────────────────
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$whereClause = '';
$params = [];

if ($search !== '') {
    $whereClause = 'WHERE (c.name LIKE :search1 OR c.phone LIKE :search2 OR c.email LIKE :search3)';
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
}

// Total records query for pagination
try {
    $countSql = "SELECT COUNT(*) FROM customers c {$whereClause}";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = (int)$stmtCount->fetchColumn();
    $totalPages   = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $totalRecords = 0;
    $totalPages   = 1;
}

// Main fetch query with vehicle counts and latest booking service address
$sql = "SELECT c.id,
               c.name,
               c.phone,
               c.email,
               c.address,
               c.user_id,
               c.created_at,
               (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id) AS total_vehicles,
               (SELECT CONCAT_WS(', ', b.service_address, b.area, b.city, b.state, b.pincode) 
                FROM bookings b 
                WHERE b.customer_id = c.id AND (b.service_address IS NOT NULL AND b.service_address != '')
                ORDER BY b.id DESC LIMIT 1) AS latest_service_address
          FROM customers c
       {$whereClause}
      ORDER BY c.id DESC
         LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[GarageMS] Customer List Fetch Error: ' . $e->getMessage());
    $customers = [];
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Customers</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="text-white fw-bold mb-1"><i class="bi bi-people-fill me-2 text-warning"></i>Customer Directory</h1>
                <p class="text-slate-300 small mb-0" style="color:#cbd5e1 !important;">Operational directory of registered customers, vehicles and active services.</p>
            </div>
            <?php if (isAdmin()): ?>
                <a href="<?= BASE_URL ?>customers/add.php" class="btn btn-warning fw-bold text-dark">
                    <i class="bi bi-person-plus-fill me-1"></i> Add New Customer
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <!-- ── Search & Filter Panel ─────────────────────────── -->
        <div class="card gms-panel mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>customers/list.php" class="row g-3 align-items-end">
                    <div class="col-md-8 col-lg-6">
                        <label for="searchCust" class="form-label fw-semibold text-muted small">Search Customer</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                id="searchCust"
                                name="search"
                                class="form-control gms-input"
                                placeholder="Search by name, phone, or email..."
                                value="<?= e($search) ?>"
                            >
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-gms-primary px-4">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                        <?php if ($search !== ''): ?>
                            <a href="<?= BASE_URL ?>customers/list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Customer Table ───────────────────────────────── -->
        <div class="card gms-panel shadow-sm">
            <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-light">
                    <i class="bi bi-list-stars me-2 text-warning"></i>All Registered Customers
                </h5>
                <span class="text-muted small">Total: <?= number_format($totalRecords) ?> Customer(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 gms-table">
                        <thead class="table-dark text-muted uppercase small">
                            <tr>
                                <th scope="col" class="ps-4">Customer Name</th>
                                <th scope="col">Contact Info</th>
                                <th scope="col">Address</th>
                                <th scope="col" class="text-center">Vehicles</th>
                                <th scope="col">Portal Account</th>
                                <th scope="col" class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-exclamation display-4 d-block mb-3 opacity-50"></i>
                                        <p class="h6">No customers found</p>
                                        <small>Try a different search query or add a new customer.</small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <!-- Name -->
                                        <td class="ps-4 fw-semibold text-light">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="gms-avatar-sm" aria-hidden="true">
                                                    <?= strtoupper(mb_substr($c['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <a href="<?= BASE_URL ?>customers/view.php?id=<?= $c['id'] ?>" class="text-light text-decoration-none hover-warning">
                                                        <?= e($c['name']) ?>
                                                    </a>
                                                    <small class="d-block text-muted" style="font-size:0.75rem;">ID: #CUST-<?= $c['id'] ?></small>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Contact Info -->
                                        <td>
                                            <div class="small">
                                                <span class="d-block text-light"><i class="bi bi-telephone me-1 text-warning"></i><?= e($c['phone']) ?></span>
                                                <?php if (!empty($c['email'])): ?>
                                                    <span class="text-muted"><i class="bi bi-envelope me-1 opacity-75"></i><?= e($c['email']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted italic opacity-50">No email</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Address -->
                                        <td style="max-width: 260px;">
                                            <?php if (!empty($c['latest_service_address'])): ?>
                                                <div class="small text-light fw-medium">
                                                    <span class="text-warning fw-bold d-block" style="font-size:0.75rem;"><i class="bi bi-geo-alt-fill me-1"></i>Service Address:</span>
                                                    <?= e($c['latest_service_address']) ?>
                                                </div>
                                            <?php elseif (!empty($c['address'])): ?>
                                                <div class="small text-light fw-medium">
                                                    <span class="text-info fw-bold d-block" style="font-size:0.75rem;"><i class="bi bi-house-door-fill me-1"></i>Saved Profile:</span>
                                                    <?= e($c['address']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-dark border border-secondary text-secondary fw-normal">
                                                    Address not provided
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Total Vehicles -->
                                        <td class="text-center">
                                            <span class="badge bg-dark-subtle text-warning border border-warning-subtle px-3 py-1.5 rounded-pill">
                                                <i class="bi bi-car-front-fill me-1"></i><?= (int)$c['total_vehicles'] ?>
                                            </span>
                                        </td>

                                        <!-- Portal Account -->
                                        <td>
                                            <?php if ($c['user_id']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1">
                                                    <i class="bi bi-check-circle me-1"></i>Linked
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-muted border border-secondary px-2.5 py-1">
                                                    Walk-in
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="pe-4 text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= BASE_URL ?>customers/view.php?id=<?= $c['id'] ?>" class="btn btn-outline-info fw-semibold" title="View Profile">
                                                    <i class="bi bi-eye-fill me-1"></i> View
                                                </a>
                                                <?php if (isAdmin()): ?>
                                                    <a href="<?= BASE_URL ?>customers/edit.php?id=<?= $c['id'] ?>" class="btn btn-outline-warning" title="Edit Profile">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </a>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-danger"
                                                        title="Delete Customer"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteCustModal<?= $c['id'] ?>"
                                                    >
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Confirmation Delete Modal (Admin Only) -->
                                            <?php if (isAdmin()): ?>
                                                <div class="modal fade text-start" id="deleteCustModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content bg-dark border-secondary text-light">
                                                            <div class="modal-header border-secondary">
                                                                <h5 class="modal-title text-danger">
                                                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete customer <strong><?= e($c['name']) ?></strong>?</p>
                                                                <?php if ($c['total_vehicles'] > 0): ?>
                                                                    <div class="alert alert-warning small mb-0">
                                                                        <i class="bi bi-info-circle me-1"></i>
                                                                        Deleting this customer will also delete their <strong><?= $c['total_vehicles'] ?> registered vehicle(s)</strong> and associated bookings.
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer border-secondary">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <a href="<?= BASE_URL ?>customers/delete.php?id=<?= $c['id'] ?>" class="btn btn-danger">
                                                                    <i class="bi bi-trash me-1"></i> Yes, Delete Customer
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-top border-secondary">
                    <?= renderPagination($page, $totalPages, BASE_URL . 'customers/list.php', $search !== '' ? ['search' => $search] : []) ?>
                </div>

            </div>
        </div>

    </div>
</div>

<style>
.gms-avatar-sm {
    width: 34px;
    height: 34px;
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
.hover-warning:hover {
    color: var(--clr-accent) !important;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
