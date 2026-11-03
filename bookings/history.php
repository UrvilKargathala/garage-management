<?php
/**
 * bookings/history.php — Completed Service History Log
 * Garage Management System
 *
 * Requirements & Scoping:
 *  ✔ Customer role sees ONLY completed service history for their own vehicles
 *  ✔ Staff and Admin roles see complete service history across all customers
 *  ✔ Pagination & search support
 *  ✔ Displays Mechanic Name, Service Date, Actual Cost Charged, Vehicle Details, and Work Notes
 */

$pageTitle = 'Service History';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

// Scoping: Customers only see history for their own vehicles / bookings
if (hasRole('customer')) {
    $customerId = getLoggedInCustomerId($pdo);
    $userId = currentUserId();
    if ($customerId) {
        $where[] = '(c.id = :cust_id OR c.user_id = :user_id)';
        $params['cust_id'] = $customerId;
        $params['user_id'] = $userId;
    } else {
        $where[] = 'c.user_id = :user_id';
        $params['user_id'] = $userId;
    }
}

if ($search !== '') {
    $where[] = '(v.vehicle_number LIKE :search1 OR h.service_description LIKE :search2 OR h.mechanic_name LIKE :search3 OR c.name LIKE :search4)';
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
    $params['search4'] = '%' . $search . '%';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count query for pagination
try {
    $countSql = "SELECT COUNT(*)
                   FROM service_history h
                   JOIN vehicles v ON h.vehicle_id = v.id
                   JOIN customers c ON v.customer_id = c.id
                {$whereClause}";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = (int)$stmtCount->fetchColumn();
    $totalPages   = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $totalRecords = 0;
    $totalPages   = 1;
}

// Fetch main service history records with booking and staff joins
$sql = "SELECT h.*,
               v.vehicle_number, v.brand, v.model, v.year, v.fuel_type,
               c.name AS customer_name, c.phone AS customer_phone, c.id AS customer_id,
               b.id AS booking_id, b.status AS booking_status,
               u.name AS staff_user_name
          FROM service_history h
          JOIN vehicles v ON h.vehicle_id = v.id
          JOIN customers c ON v.customer_id = c.id
     LEFT JOIN bookings b ON h.booking_id = b.id
     LEFT JOIN users u ON b.staff_id = u.id
       {$whereClause}
      ORDER BY h.service_date DESC, h.id DESC
         LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[GarageMS] Service History Fetch Error: ' . $e->getMessage());
    $history = [];
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?><?= hasRole('customer') ? 'customer/dashboard.php' : 'staff/dashboard.php' ?>">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Service History</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="text-white fw-bold mb-1"><i class="bi bi-clock-history me-2 text-warning"></i>Completed Service History</h1>
                <p class="text-slate-300 small mb-0" style="color:#cbd5e1 !important;">Verified log of all completed maintenance, repairs, parts and work done.</p>
            </div>
            <?php if (hasRole('customer')): ?>
                <a href="<?= BASE_URL ?>bookings/create.php" class="btn btn-warning fw-bold text-dark">
                    <i class="bi bi-calendar-plus me-1"></i> Book New Service
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <!-- ── Search & Filter Panel ─────────────────── -->
        <div class="card gms-panel mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>bookings/history.php" class="row g-3 align-items-end">
                    <div class="col-md-8 col-lg-6">
                        <label for="searchHist" class="form-label fw-semibold text-light small">Search History</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-warning"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                id="searchHist"
                                name="search"
                                class="form-control gms-input"
                                placeholder="Search by vehicle reg, service description, or mechanic..."
                                value="<?= e($search) ?>"
                            >
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-warning fw-bold text-dark px-4">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                        <?php if ($search !== ''): ?>
                            <a href="<?= BASE_URL ?>bookings/history.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Service History Table ────────────────────────── -->
        <div class="card gms-panel shadow-sm">
            <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-white">
                    <i class="bi bi-check-circle-fill me-2 text-success"></i>Service Records Archive
                </h5>
                <span class="badge bg-success bg-opacity-20 text-success border border-success px-3 py-1 font-monospace">
                    <?= number_format($totalRecords) ?> COMPLETED RECORD(S)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 gms-table">
                        <thead class="text-muted uppercase small">
                            <tr>
                                <th scope="col" class="ps-4">Date & Ref</th>
                                <th scope="col">Vehicle</th>
                                <?php if (!hasRole('customer')): ?>
                                    <th scope="col">Customer</th>
                                <?php endif; ?>
                                <th scope="col">Service Performed</th>
                                <th scope="col">Staff / Technician</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Cost Charged</th>
                                <th scope="col" class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="<?= !hasRole('customer') ? '8' : '7' ?>" class="text-center py-5 text-muted">
                                        <i class="bi bi-journal-check display-4 d-block mb-3 text-success opacity-50"></i>
                                        <h5 class="text-white fw-bold">No completed service history found</h5>
                                        <p class="small text-muted mb-0">Completed service bookings will automatically archive here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <!-- Service Date & Ref -->
                                        <td class="ps-4">
                                            <div class="text-white fw-bold">
                                                <i class="bi bi-calendar3 text-warning me-1"></i>
                                                <?= formatDate($h['service_date']) ?>
                                            </div>
                                            <?php if ($h['booking_id']): ?>
                                                <span class="badge bg-dark border border-secondary text-warning font-monospace mt-1">
                                                    #BKG-<?= (int)$h['booking_id'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Vehicle Number -->
                                        <td>
                                            <div class="font-monospace text-warning fw-bold fs-6"><?= e($h['vehicle_number']) ?></div>
                                            <div class="text-white small fw-medium"><?= e($h['brand']) ?> <?= e($h['model']) ?> (<?= e($h['year']) ?>)</div>
                                        </td>

                                        <!-- Customer (Staff/Admin) -->
                                        <?php if (!hasRole('customer')): ?>
                                            <td>
                                                <div class="fw-bold text-white"><?= e($h['customer_name']) ?></div>
                                                <div class="text-muted small"><i class="bi bi-telephone me-1 text-warning"></i><?= e($h['customer_phone']) ?></div>
                                            </td>
                                        <?php endif; ?>

                                        <!-- Service Description -->
                                        <td style="min-width: 200px; max-width: 320px;">
                                            <div class="text-white fw-semibold mb-1"><?= e($h['service_description']) ?></div>
                                            <?php if (!empty($h['remarks'])): ?>
                                                <div class="text-muted small fst-italic">"<?= e($h['remarks']) ?>"</div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Mechanic / Staff -->
                                        <td>
                                            <span class="badge bg-dark border border-secondary text-info px-2.5 py-1.5 fw-semibold">
                                                <i class="bi bi-person-badge-fill me-1 text-info"></i><?= e($h['mechanic_name'] ?: ($h['staff_user_name'] ?: 'Staff Technician')) ?>
                                            </span>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success px-2.5 py-1 text-uppercase font-monospace fw-bold">
                                                <i class="bi bi-check-circle-fill me-1"></i> Completed
                                            </span>
                                        </td>

                                        <!-- Cost Charged -->
                                        <td class="text-end">
                                            <span class="fw-bold text-warning font-monospace fs-6">
                                                ₹<?= number_format($h['service_cost'], 2) ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="pe-4 text-end">
                                            <?php if ($h['booking_id']): ?>
                                                <a href="<?= BASE_URL ?>bookings/view.php?id=<?= (int)$h['booking_id'] ?>" class="btn btn-outline-info btn-sm fw-semibold" title="View Booking Details">
                                                    <i class="bi bi-eye-fill me-1"></i> Details
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
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
                    <?= renderPagination($page, $totalPages, BASE_URL . 'bookings/history.php', $search !== '' ? ['search' => $search] : []) ?>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
