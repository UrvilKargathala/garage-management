<?php
/**
 * vehicles/list.php — Vehicle Directory & List
 * Garage Management System
 *
 * Requirements & Role Scoping:
 *  ✔ Customer role sees ONLY their own vehicles
 *  ✔ Staff and Admin roles see ALL vehicles
 *  ✔ Search by vehicle registration number, brand, model, or customer name
 *  ✔ Pagination support
 *  ✔ Deletion confirmation modal
 */

$pageTitle = 'Vehicle Directory';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDBConnection();

// ── Search & Pagination ───────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

// Role-based scoping: Customers only see their own vehicles
if (hasRole('customer')) {
    $userId = currentUserId();
    $where[] = 'c.user_id = :user_id';
    $params['user_id'] = $userId;
}

// Search filter
if ($search !== '') {
    $where[] = '(v.vehicle_number LIKE :search1 OR v.brand LIKE :search2 OR v.model LIKE :search3 OR c.name LIKE :search4)';
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
    $params['search4'] = '%' . $search . '%';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count query for pagination
try {
    $countSql = "SELECT COUNT(*) FROM vehicles v JOIN customers c ON v.customer_id = c.id {$whereClause}";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = (int)$stmtCount->fetchColumn();
    $totalPages   = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $totalRecords = 0;
    $totalPages   = 1;
}

// Highlight newly added vehicle if redirected from add.php
$highlightVehicleId = isset($_GET['new_id']) ? (int)$_GET['new_id'] : 0;
$showSuccessModal   = isset($_GET['added']) && $_GET['added'] === '1' && $highlightVehicleId > 0;
$newlyAddedVehicle  = null;

// Fetch query with owner phone and details
$sql = "SELECT v.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.id AS customer_id
          FROM vehicles v
          JOIN customers c ON v.customer_id = c.id
       {$whereClause}
      ORDER BY v.id DESC
         LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $vehicles = $stmt->fetchAll();

    if ($highlightVehicleId > 0) {
        foreach ($vehicles as $v) {
            if ((int)$v['id'] === $highlightVehicleId) {
                $newlyAddedVehicle = $v;
                break;
            }
        }
        if (!$newlyAddedVehicle) {
            $stmtNew = $pdo->prepare('SELECT v.*, c.name AS customer_name, c.phone AS customer_phone FROM vehicles v JOIN customers c ON v.customer_id = c.id WHERE v.id = ? LIMIT 1');
            $stmtNew->execute([$highlightVehicleId]);
            $newlyAddedVehicle = $stmtNew->fetch();
        }
    }
} catch (PDOException $e) {
    error_log('[GarageMS] Vehicle List Fetch Error: ' . $e->getMessage());
    $vehicles = [];
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?><?= hasRole('customer') ? 'customer/dashboard.php' : 'staff/dashboard.php' ?>">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Vehicles</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="text-white fw-bold mb-1"><i class="bi bi-car-front-fill me-2 text-warning"></i><?= hasRole('customer') ? 'My Registered Vehicles' : 'Vehicles Directory' ?></h1>
                <p class="text-slate-300 small mb-0" style="color:#cbd5e1 !important;"><?= hasRole('customer') ? 'Manage your registered vehicles, view diagnostics, and schedule garage service bookings.' : 'Operational registry of customer vehicles, specifications and fuel types.' ?></p>
            </div>
            <?php if (hasRole('customer') || isAdmin()): ?>
                <button type="button" id="btnRegisterVehicle" class="btn btn-warning fw-bold text-dark d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                    <i class="bi bi-plus-lg me-1"></i> Register Vehicle
                </button>
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
                <form method="GET" action="<?= BASE_URL ?>vehicles/list.php" class="row g-3 align-items-end">
                    <div class="col-md-8 col-lg-6">
                        <label for="searchVeh" class="form-label fw-semibold text-light small">Search Vehicles</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-warning"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                id="searchVeh"
                                name="search"
                                class="form-control gms-input text-white"
                                placeholder="Search by registration number, brand, model..."
                                value="<?= e($search) ?>"
                            >
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-warning fw-bold text-dark px-4">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                        <?php if ($search !== ''): ?>
                            <a href="<?= BASE_URL ?>vehicles/list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($vehicles)): ?>
            <!-- ── EMPTY STATE ──────────────────────────────────── -->
            <div class="card gms-panel text-center py-5 px-3 mb-4 shadow-sm" style="border: 2px dashed rgba(255,255,255,0.15);">
                <div class="py-4">
                    <div style="font-size: 3.5rem; line-height: 1;" class="mb-3">🚗</div>
                    <h3 class="text-white fw-bold mb-2">No Vehicles Added Yet</h3>
                    <p class="text-slate-300 small mb-4" style="max-width: 480px; margin: 0 auto; color:#cbd5e1 !important;">
                        Add your first vehicle to start booking garage services, scheduling oil changes, repairs, and tracking maintenance history.
                    </p>
                    <?php if (hasRole('customer') || isAdmin()): ?>
                        <button type="button" class="btn btn-warning fw-bold text-dark px-4 py-2" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                            <i class="bi bi-plus-circle-fill me-1"></i> Add Vehicle Now
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>

            <?php if (hasRole('customer')): ?>
                <!-- ── CUSTOMER VEHICLE CARDS GRID ─────────────────── -->
                <div class="row g-4 mb-4">
                    <?php foreach ($vehicles as $v): ?>
                        <?php 
                        $isNewlyAdded = ($highlightVehicleId > 0 && (int)$v['id'] === $highlightVehicleId);
                        ?>
                        <div class="col-md-6 col-xl-4" id="vehicle-<?= $v['id'] ?>">
                            <div class="gms-vehicle-card <?= $isNewlyAdded ? 'newly-added-highlight' : '' ?>">
                                <div>
                                    <!-- Header Row with Registration & Status -->
                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                        <div>
                                            <span class="badge bg-dark border border-warning text-warning font-monospace px-2.5 py-1.5 fw-bold fs-6 shadow-sm">
                                                <?= e($v['vehicle_number']) ?>
                                            </span>
                                            <?php if ($isNewlyAdded): ?>
                                                <span class="badge bg-success text-white font-monospace ms-1 px-2 py-1 small animate-pulse">
                                                    <i class="bi bi-stars me-1"></i>NEWLY ADDED
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-info bg-opacity-10 border border-info text-info text-capitalize px-2.5 py-1 fw-semibold">
                                            <i class="bi bi-tag-fill me-1"></i><?= e($v['vehicle_type']) ?>
                                        </span>
                                    </div>

                                    <!-- Vehicle Name & Make -->
                                    <h3 class="gms-vehicle-title">
                                        <?= e($v['brand']) ?> <?= e($v['model']) ?>
                                    </h3>

                                    <!-- Technical Specs Grid -->
                                    <div class="row g-2 pt-2 border-top border-secondary border-opacity-50">
                                        <div class="col-6">
                                            <div class="gms-vehicle-meta-label">Manufacturing Year</div>
                                            <div class="gms-vehicle-meta-value font-monospace">
                                                <i class="bi bi-calendar-event me-1 text-warning"></i><?= e($v['year']) ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="gms-vehicle-meta-label">Fuel Type</div>
                                            <div class="gms-vehicle-meta-value text-capitalize">
                                                <i class="bi bi-fuel-pump-fill me-1 text-warning"></i><?= e($v['fuel_type']) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Owner Information Box -->
                                    <div class="gms-owner-badge-box">
                                        <div class="gms-vehicle-meta-label text-warning"><i class="bi bi-person-fill me-1"></i>Owner Details</div>
                                        <div class="text-white fw-bold small"><?= e($v['customer_name']) ?></div>
                                        <div class="text-slate-300 small" style="color:#cbd5e1 !important;">
                                            <i class="bi bi-telephone text-warning me-1"></i><?= e($v['customer_phone'] ?: 'No phone recorded') ?>
                                        </div>
                                        <div class="text-muted small" style="font-size:0.75rem; margin-top:2px;">
                                            <i class="bi bi-clock-history me-1"></i>Added on <?= formatDate($v['created_at']) ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons Bottom -->
                                <div class="pt-3 border-top border-secondary border-opacity-50 d-flex gap-2">
                                    <a href="<?= BASE_URL ?>bookings/create.php?vehicle_id=<?= $v['id'] ?>" class="btn btn-warning btn-sm fw-bold text-dark flex-grow-1">
                                        <i class="bi bi-calendar-plus me-1"></i> Book Service
                                    </a>
                                    <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $v['id'] ?>" class="btn btn-outline-info btn-sm px-3" title="View Full Vehicle Details">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>vehicles/edit.php?id=<?= $v['id'] ?>" class="btn btn-outline-warning btn-sm px-2.5" title="Edit Vehicle">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm px-2.5" title="Delete Vehicle" data-bs-toggle="modal" data-bs-target="#deleteVehModal<?= $v['id'] ?>">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade text-start" id="deleteVehModal<?= $v['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content gms-modal-content text-light">
                                    <div class="modal-header gms-modal-header border-secondary">
                                        <h5 class="modal-title text-danger fw-bold">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Vehicle
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body gms-modal-body">
                                        <p class="text-white">Are you sure you want to delete vehicle <strong><?= e($v['vehicle_number']) ?></strong> (<?= e($v['brand']) ?> <?= e($v['model']) ?>)?</p>
                                        <div class="alert alert-danger small mb-0 border-0 bg-danger bg-opacity-10 text-danger">
                                            <i class="bi bi-info-circle me-1"></i> This will remove this vehicle from your account profile.
                                        </div>
                                    </div>
                                    <div class="modal-footer gms-modal-footer border-secondary">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <a href="<?= BASE_URL ?>vehicles/delete.php?id=<?= $v['id'] ?>" class="btn btn-danger fw-bold">
                                            <i class="bi bi-trash me-1"></i> Yes, Delete Vehicle
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <!-- ── STAFF / ADMIN READ-ONLY TABLE ───────────────── -->
                <div class="card gms-panel shadow-sm mb-4">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-white">
                            <i class="bi bi-car-front me-2 text-warning"></i>Operational Vehicle Registry
                        </h5>
                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning px-3 py-1 font-monospace">
                            <?= number_format($totalRecords) ?> VEHICLE(S)
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0 gms-table">
                                <thead class="text-muted uppercase small">
                                    <tr>
                                        <th scope="col" class="ps-4">Vehicle</th>
                                        <th scope="col">Registration</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Year</th>
                                        <th scope="col">Fuel</th>
                                        <th scope="col">Owner</th>
                                        <th scope="col" class="pe-4 text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehicles as $v): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-white fs-6"><?= e($v['brand']) ?> <?= e($v['model']) ?></div>
                                                <div class="text-muted small" style="font-size:0.75rem;">Created <?= formatDate($v['created_at']) ?></div>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $v['id'] ?>" class="badge bg-dark border border-secondary text-warning font-monospace px-2.5 py-1.5 fs-6 text-decoration-none fw-bold">
                                                    <?= e($v['vehicle_number']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-dark border border-secondary text-info text-capitalize px-2.5 py-1.5 fw-semibold">
                                                    <i class="bi bi-tag-fill me-1"></i><?= e($v['vehicle_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-white fw-semibold font-monospace">
                                                    <?= e($v['year']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-dark border border-secondary text-warning text-capitalize px-2.5 py-1.5 fw-semibold">
                                                    <i class="bi bi-fuel-pump-fill me-1"></i><?= e($v['fuel_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>customers/view.php?id=<?= $v['customer_id'] ?>" class="text-white text-decoration-none fw-medium small hover-warning d-block">
                                                    <i class="bi bi-person-fill me-1 text-warning"></i><?= e($v['customer_name']) ?>
                                                </a>
                                                <div class="text-muted small" style="font-size:0.75rem;"><i class="bi bi-telephone me-1"></i><?= e($v['customer_phone']) ?></div>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $v['id'] ?>" class="btn btn-outline-info" title="View Details">
                                                        <i class="bi bi-eye-fill me-1"></i> View
                                                    </a>
                                                    <?php if (isAdmin()): ?>
                                                        <a href="<?= BASE_URL ?>vehicles/edit.php?id=<?= $v['id'] ?>" class="btn btn-outline-warning" title="Edit Vehicle">
                                                            <i class="bi bi-pencil-fill"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" title="Delete Vehicle" data-bs-toggle="modal" data-bs-target="#deleteVehModalStaff<?= $v['id'] ?>">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Pagination -->
            <div class="p-3 border-top border-secondary">
                <?= renderPagination($page, $totalPages, BASE_URL . 'vehicles/list.php', $search !== '' ? ['search' => $search] : []) ?>
            </div>

        <?php endif; ?>

    </div>
</div>

<!-- ── ADD VEHICLE MODAL DIALOG ───────────────────────────── -->
<?php if (hasRole('customer') || isAdmin()): ?>
    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content gms-modal-content">
                <form method="POST" action="<?= BASE_URL ?>vehicles/add.php">
                    <?= csrfField() ?>
                    <div class="modal-header gms-modal-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:36px;height:36px;border-radius:10px;background:rgba(249,115,22,0.15);color:#f97316;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                                <i class="bi bi-car-front-fill"></i>
                            </div>
                            <div>
                                <h5 class="modal-title text-white fw-bold mb-0" id="addVehicleModalLabel">Vehicle Registration</h5>
                                <small class="text-slate-300" style="color:#cbd5e1 !important;">Add vehicle specs for diagnostics and servicing</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body gms-modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="modalRegNumber" class="form-label fw-semibold text-white small">Vehicle Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control gms-input text-white font-monospace" id="modalRegNumber" name="vehicle_number" placeholder="e.g. GJ05AB1234" style="text-transform:uppercase;" required>
                            </div>
                            <div class="col-md-6">
                                <label for="modalType" class="form-label fw-semibold text-white small">Vehicle Type <span class="text-danger">*</span></label>
                                <select class="form-select gms-input text-white" id="modalType" name="vehicle_type" required>
                                    <option value="car" selected>Car / Sedan / SUV</option>
                                    <option value="motorcycle">Motorcycle / Scooter</option>
                                    <option value="van">Van / Minivan</option>
                                    <option value="truck">Truck / Commercial</option>
                                    <option value="bus">Bus</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="modalBrand" class="form-label fw-semibold text-white small">Brand <span class="text-danger">*</span></label>
                                <input type="text" class="form-control gms-input text-white" id="modalBrand" name="brand" placeholder="e.g. Toyota, Honda, Hyundai" required>
                            </div>
                            <div class="col-md-6">
                                <label for="modalModel" class="form-label fw-semibold text-white small">Model <span class="text-danger">*</span></label>
                                <input type="text" class="form-control gms-input text-white" id="modalModel" name="model" placeholder="e.g. Corolla, City, Creta" required>
                            </div>
                            <div class="col-md-6">
                                <label for="modalYear" class="form-label fw-semibold text-white small">Manufacturing Year <span class="text-danger">*</span></label>
                                <input type="number" class="form-control gms-input text-white" id="modalYear" name="year" min="1900" max="<?= date('Y') + 1 ?>" value="<?= date('Y') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="modalFuel" class="form-label fw-semibold text-white small">Fuel Type <span class="text-danger">*</span></label>
                                <select class="form-select gms-input text-white" id="modalFuel" name="fuel_type" required>
                                    <option value="petrol" selected>Petrol</option>
                                    <option value="diesel">Diesel</option>
                                    <option value="cng">CNG</option>
                                    <option value="electric">Electric (EV)</option>
                                    <option value="hybrid">Hybrid</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="modalColor" class="form-label fw-semibold text-white small">Color</label>
                                <input type="text" class="form-control gms-input text-white" id="modalColor" name="color" placeholder="e.g. Pearl White, Silver Metallic, Black">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer gms-modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold text-dark px-4">
                            <i class="bi bi-check-circle-fill me-1"></i> Register Vehicle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ── SUCCESS ANIMATION CONFIRMATION DIALOG ──────────────── -->
<?php if ($showSuccessModal && $newlyAddedVehicle): ?>
    <div class="modal fade" id="vehicleSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content gms-modal-content text-center py-3">
                <div class="modal-body gms-modal-body">
                    <div class="gms-success-icon-wrapper">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h3 class="text-white fw-bold mb-1">Vehicle Registered Successfully</h3>
                    <p class="text-slate-300 small mb-4" style="color:#cbd5e1 !important;">
                        <strong><?= e($newlyAddedVehicle['brand']) ?> <?= e($newlyAddedVehicle['model']) ?></strong> has been added to your garage account.
                    </p>

                    <div class="p-3 rounded border border-secondary mb-4" style="background: rgba(255,255,255,0.03);">
                        <small class="text-muted text-uppercase d-block fw-bold" style="font-size:0.7rem;">Vehicle Registration</small>
                        <span class="text-warning font-monospace fw-bold fs-5">
                            <?= e($newlyAddedVehicle['vehicle_number']) ?>
                        </span>
                        <div class="text-light small mt-1 text-capitalize">
                            <?= e($newlyAddedVehicle['vehicle_type']) ?> &bull; <?= e($newlyAddedVehicle['year']) ?> &bull; <?= e($newlyAddedVehicle['fuel_type']) ?>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= (int)$newlyAddedVehicle['id'] ?>" class="btn btn-warning fw-bold text-dark py-2.5">
                            <i class="bi bi-eye-fill me-1"></i> View Vehicle Details
                        </a>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-warning w-50 py-2" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                                <i class="bi bi-plus-lg me-1"></i> Add Another
                            </button>
                            <button type="button" class="btn btn-outline-secondary w-50 py-2" data-bs-dismiss="modal">
                                OK
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var successModal = new bootstrap.Modal(document.getElementById('vehicleSuccessModal'));
                successModal.show();
            }
            
            // Smoothly scroll to newly added vehicle card
            var targetCard = document.getElementById('vehicle-<?= $highlightVehicleId ?>');
            if (targetCard) {
                targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var regBtns = document.querySelectorAll('[data-bs-target="#addVehicleModal"], #btnRegisterVehicle');
    var modalEl = document.getElementById('addVehicleModal');
    if (modalEl && regBtns.length > 0) {
        regBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                } else {
                    window.location.href = '<?= BASE_URL ?>vehicles/add.php';
                }
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

