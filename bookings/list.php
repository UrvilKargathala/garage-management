<?php
/**
 * bookings/list.php — Service Bookings Management
 * Garage Management System
 *
 * Requirements & Scoping:
 *  ✔ Customer role sees ONLY their own bookings
 *  ✔ Staff and Admin roles see ALL bookings
 *  ✔ Search by vehicle number, service name, or customer name
 *  ✔ Status filter (pending, confirmed, in_progress, completed, cancelled)
 *  ✔ Pagination & Status Badges
 */

$pageTitle = 'Service Bookings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

// Scoping
if (hasRole('customer')) {
    $userId = currentUserId();
    $where[] = 'c.user_id = :user_id';
    $params['user_id'] = $userId;
}

if ($statusFilter !== 'all') {
    $where[] = 'b.status = :status';
    $params['status'] = $statusFilter;
}

if ($search !== '') {
    $where[] = '(v.vehicle_number LIKE :search1 OR s.service_name LIKE :search2 OR c.name LIKE :search3)';
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Count for pagination
try {
    $countSql = "SELECT COUNT(*)
                   FROM bookings b
                   JOIN customers c ON b.customer_id = c.id
                   JOIN vehicles v ON b.vehicle_id = v.id
                   JOIN services s ON b.service_id = s.id
                {$whereClause}";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = (int)$stmtCount->fetchColumn();
    $totalPages   = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $totalRecords = 0;
    $totalPages   = 1;
}

// Main query
$sql = "SELECT b.*,
               c.name AS customer_name, c.phone AS customer_phone,
               v.vehicle_number, v.brand, v.model,
               s.service_name, s.price
          FROM bookings b
          JOIN customers c ON b.customer_id = c.id
          JOIN vehicles v ON b.vehicle_id = v.id
          JOIN services s ON b.service_id = s.id
       {$whereClause}
      ORDER BY b.booking_date DESC, b.id DESC
         LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[GarageMS] Bookings List Fetch Error: ' . $e->getMessage());
    $bookings = [];
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Bookings</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1><i class="bi bi-calendar-check-fill me-2 text-warning"></i><?= hasRole('customer') ? 'My Service Bookings' : 'All Service Bookings' ?></h1>
            <a href="<?= BASE_URL ?>bookings/create.php" class="btn btn-gms-primary">
                <i class="bi bi-calendar-plus me-1"></i> Book New Service
            </a>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <!-- ── Search & Status Filter Panel ─────────────────── -->
        <div class="card gms-panel mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" action="<?= BASE_URL ?>bookings/list.php" class="row g-3 align-items-end">
                    <div class="col-md-5 col-lg-4">
                        <label for="searchBkg" class="form-label fw-semibold text-muted small">Search Bookings</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                id="searchBkg"
                                name="search"
                                class="form-control gms-input"
                                placeholder="Registration, service, or customer name..."
                                value="<?= e($search) ?>"
                            >
                        </div>
                    </div>

                    <div class="col-md-4 col-lg-3">
                        <label for="statusBkg" class="form-label fw-semibold text-muted small">Booking Status</label>
                        <select id="statusBkg" name="status" class="form-select gms-input">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Confirmation</option>
                            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-lg-5 d-flex gap-2">
                        <button type="submit" class="btn btn-gms-primary px-4">
                            <i class="bi bi-filter me-1"></i> Filter
                        </button>
                        <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                            <a href="<?= BASE_URL ?>bookings/list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Bookings Table ───────────────────────────────── -->
        <div class="card gms-panel shadow-sm">
            <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-light">
                    <i class="bi bi-list-task me-2 text-warning"></i>Service Appointments
                </h5>
                <span class="text-muted small">Total: <?= number_format($totalRecords) ?> Booking(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 gms-table">
                        <thead class="table-dark text-muted uppercase small">
                            <tr>
                                <th scope="col" class="ps-4">Booking ID</th>
                                <th scope="col">Date & Time</th>
                                <?php if (!hasRole('customer')): ?>
                                    <th scope="col">Customer</th>
                                <?php endif; ?>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Service</th>
                                <th scope="col">Price</th>
                                <th scope="col" class="text-center">Status</th>
                                <th scope="col" class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="<?= hasRole('customer') ? '7' : '8' ?>" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x display-4 d-block mb-3 opacity-50"></i>
                                        <p class="h6">No bookings found</p>
                                        <small>Create a new booking to get started.</small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <!-- ID -->
                                        <td class="ps-4 font-monospace fw-bold text-warning">
                                            <a href="<?= BASE_URL ?>bookings/view.php?id=<?= $b['id'] ?>" class="text-warning text-decoration-none">
                                                #BKG-<?= $b['id'] ?>
                                            </a>
                                        </td>

                                        <!-- Scheduled Date & Time -->
                                        <td>
                                            <div class="small">
                                                <span class="d-block text-light font-monospace"><i class="bi bi-calendar3 me-1 text-warning"></i><?= formatDate($b['booking_date']) ?></span>
                                                <span class="text-muted"><i class="bi bi-clock me-1 opacity-75"></i><?= $b['booking_time'] ? e($b['booking_time']) : 'Flexible' ?></span>
                                            </div>
                                        </td>

                                        <!-- Customer (Staff/Admin) -->
                                        <?php if (!hasRole('customer')): ?>
                                            <td>
                                                <div class="small">
                                                    <span class="d-block text-light fw-semibold"><?= e($b['customer_name']) ?></span>
                                                    <span class="text-muted"><?= e($b['customer_phone']) ?></span>
                                                </div>
                                            </td>
                                        <?php endif; ?>

                                        <!-- Vehicle -->
                                        <td>
                                            <div class="small">
                                                <span class="font-monospace text-warning fw-bold"><?= e($b['vehicle_number']) ?></span>
                                                <span class="d-block text-muted"><?= e($b['brand']) ?> <?= e($b['model']) ?></span>
                                            </div>
                                        </td>

                                        <!-- Service -->
                                        <td class="fw-semibold text-light"><?= e($b['service_name']) ?></td>

                                        <!-- Price -->
                                        <td class="fw-bold text-warning font-monospace">₹<?= number_format($b['price'], 2) ?></td>

                                        <!-- Status Badge -->
                                        <td class="text-center">
                                            <?php
                                            $statusBadge = match ($b['status']) {
                                                'pending'     => 'bg-warning text-dark border-warning',
                                                'confirmed'   => 'bg-primary border-primary text-white',
                                                'in_progress' => 'bg-info text-dark border-info',
                                                'completed'   => 'bg-success border-success text-white',
                                                'cancelled'   => 'bg-danger border-danger text-white',
                                                default       => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $statusBadge ?> px-3 py-1.5 rounded-pill text-uppercase" style="font-size:0.7rem;">
                                                <?= str_replace('_', ' ', $b['status']) ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="pe-4 text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= BASE_URL ?>bookings/view.php?id=<?= $b['id'] ?>" class="btn btn-outline-info" title="View Booking Details">
                                                    <i class="bi bi-eye-fill"></i> View
                                                </a>
                                                <?php if (hasRole(['admin', 'staff'])): ?>
                                                    <a href="<?= BASE_URL ?>bookings/update-status.php?id=<?= $b['id'] ?>" class="btn btn-outline-warning" title="Update Status">
                                                        <i class="bi bi-gear-fill"></i> Status
                                                    </a>
                                                <?php elseif ($b['status'] === 'pending' || $b['status'] === 'confirmed'): ?>
                                                    <!-- FIX 6: Cancel must be POST — never a GET link -->
                                                    <form method="POST" action="<?= BASE_URL ?>bookings/update-status.php?id=<?= $b['id'] ?>" class="d-inline" onsubmit="return confirm('Cancel this booking?');">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Cancel Booking">
                                                            <i class="bi bi-x-circle-fill"></i> Cancel
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-top border-secondary">
                    <?= renderPagination($page, $totalPages, BASE_URL . 'bookings/list.php', array_filter(['search' => $search, 'status' => $statusFilter !== 'all' ? $statusFilter : null])) ?>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
