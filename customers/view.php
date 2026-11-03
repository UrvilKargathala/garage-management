<?php
/**
 * customers/view.php — View Customer Profile & Vehicles
 * Garage Management System
 *
 * Requirements & IDOR Security:
 *  ✔ Staff and Admin can view any customer details
 *  ✔ Customer role can ONLY view their own customer profile
 *  ✔ Anti-IDOR verified via verifyCustomerOwnership($pdo, $customerId)
 */

$pageTitle = 'Customer Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDBConnection();
$customerId = (int)($_GET['id'] ?? 0);

if ($customerId <= 0) {
    setFlash('Invalid customer ID.', 'danger');
    redirect(BASE_URL . (hasRole(['admin', 'staff']) ? 'customers/list.php' : 'dashboard.php'));
}

// Anti-IDOR Check: Ensures customer only accesses their own profile
verifyCustomerOwnership($pdo, $customerId);

// Fetch Customer Record
try {
    $stmt = $pdo->prepare(
        'SELECT c.*, u.email AS portal_email, u.role AS portal_role
           FROM customers c
      LEFT JOIN users u ON c.user_id = u.id
          WHERE c.id = ?
          LIMIT 1'
    );
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        setFlash('Customer not found.', 'danger');
        redirect(BASE_URL . (hasRole(['admin', 'staff']) ? 'customers/list.php' : 'dashboard.php'));
    }

    // Fetch Customer's Vehicles
    $stmtVehicles = $pdo->prepare(
        'SELECT * FROM vehicles WHERE customer_id = ? ORDER BY id DESC'
    );
    $stmtVehicles->execute([$customerId]);
    $vehicles = $stmtVehicles->fetchAll();

    // Fetch Customer's Recent Bookings
    $stmtBookings = $pdo->prepare(
        'SELECT b.*, s.service_name, v.vehicle_number, v.brand, v.model
           FROM bookings b
           JOIN services s ON b.service_id = s.id
           JOIN vehicles v ON b.vehicle_id = v.id
          WHERE b.customer_id = ?
       ORDER BY b.booking_date DESC, b.id DESC
          LIMIT 5'
    );
    $stmtBookings->execute([$customerId]);
    $recentBookings = $stmtBookings->fetchAll();

} catch (PDOException $e) {
    error_log('[GarageMS] View Customer Error: ' . $e->getMessage());
    setFlash('Error loading customer data.', 'danger');
    redirect(BASE_URL . 'dashboard.php');
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <?php if (hasRole(['admin', 'staff'])): ?>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customers/list.php">Customers</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($customer['name']) ?></li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="text-white fw-bold mb-1"><i class="bi bi-person-badge-fill me-2 text-warning"></i>Customer Profile</h1>
                <p class="text-slate-300 small mb-0" style="color:#cbd5e1 !important;">Account records and registered vehicle assets for <?= e($customer['name']) ?>.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (hasRole('customer')): ?>
                    <a href="<?= BASE_URL ?>vehicles/add.php" class="btn btn-warning fw-bold text-dark btn-sm">
                        <i class="bi bi-car-front-fill me-1"></i> Add Vehicle
                    </a>
                    <a href="<?= BASE_URL ?>customers/edit.php?id=<?= $customer['id'] ?>" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-pencil-square me-1"></i> Edit My Profile
                    </a>
                <?php elseif (isAdmin()): ?>
                    <a href="<?= BASE_URL ?>vehicles/add.php?customer_id=<?= $customer['id'] ?>" class="btn btn-warning fw-bold text-dark btn-sm">
                        <i class="bi bi-car-front-fill me-1"></i> Add Vehicle
                    </a>
                    <a href="<?= BASE_URL ?>customers/edit.php?id=<?= $customer['id'] ?>" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-pencil-square me-1"></i> Edit Profile
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <div class="row g-4">

            <!-- ── CUSTOMER PROFILE CARD ───────────────────────── -->
            <div class="col-lg-4">
                <div class="card gms-panel shadow-sm text-center p-4">
                    <div class="gms-avatar-lg mx-auto mb-3">
                        <?= strtoupper(mb_substr($customer['name'], 0, 1)) ?>
                    </div>
                    <h3 class="h4 fw-bold text-light mb-1"><?= e($customer['name']) ?></h3>
                    <span class="badge bg-dark-subtle text-warning border border-warning-subtle px-3 py-1 mb-3 font-monospace">
                        #CUST-<?= $customer['id'] ?>
                    </span>

                    <div class="text-start border-top border-secondary pt-3 mt-2">
                        <div class="mb-3">
                            <small class="text-muted text-uppercase d-block fw-semibold mb-1" style="font-size:0.7rem;">Phone Number</small>
                            <span class="text-light fs-6"><i class="bi bi-telephone text-warning me-2"></i><?= e($customer['phone']) ?></span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted text-uppercase d-block fw-semibold mb-1" style="font-size:0.7rem;">Email Address</small>
                            <span class="text-light fs-6">
                                <i class="bi bi-envelope text-warning me-2"></i><?= e($customer['email'] ?: 'Not Provided') ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted text-uppercase d-block fw-semibold mb-1" style="font-size:0.7rem;">Saved Profile Address</small>
                            <span class="text-light fs-6">
                                <i class="bi bi-house-door text-info me-2"></i><?= e($customer['address'] ?: 'Address not provided') ?>
                            </span>
                        </div>
                        <?php 
                        // Fetch latest booking address if available
                        $stmtLatestAddr = $pdo->prepare('SELECT service_address, area, city, state, pincode FROM bookings WHERE customer_id = ? AND service_address IS NOT NULL AND service_address != "" ORDER BY id DESC LIMIT 1');
                        $stmtLatestAddr->execute([$customer['id']]);
                        $latestAddr = $stmtLatestAddr->fetch();
                        ?>
                        <?php if ($latestAddr): ?>
                            <div class="mb-3 p-2.5 rounded border border-secondary" style="background:rgba(249,115,22,0.06);">
                                <small class="text-warning text-uppercase d-block fw-bold mb-1" style="font-size:0.7rem;">
                                    <i class="bi bi-geo-alt-fill me-1"></i>Latest Service Address
                                </small>
                                <span class="text-white small fw-medium">
                                    <?= e($latestAddr['service_address']) ?><?= $latestAddr['area'] ? ', ' . e($latestAddr['area']) : '' ?><br>
                                    <?= e($latestAddr['city'] ?: 'Surat') ?>, <?= e($latestAddr['state'] ?: 'Gujarat') ?> - <?= e($latestAddr['pincode'] ?: '') ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="mb-0">
                            <small class="text-muted text-uppercase d-block fw-semibold mb-1" style="font-size:0.7rem;">Member Since</small>
                            <span class="text-muted small">
                                <i class="bi bi-calendar3 me-2"></i><?= formatDate($customer['created_at']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── REGISTERED VEHICLES & BOOKINGS ─────────────── -->
            <div class="col-lg-8">

                <!-- Registered Vehicles -->
                <div class="card gms-panel shadow-sm mb-4">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-car-front-fill me-2 text-warning"></i>Registered Vehicles (<?= count($vehicles) ?>)
                        </h5>
                        <?php if (hasRole('customer')): ?>
                            <a href="<?= BASE_URL ?>vehicles/add.php" class="btn btn-warning btn-sm fw-bold text-dark">
                                <i class="bi bi-plus-lg me-1"></i> Register Vehicle
                            </a>
                        <?php elseif (isAdmin()): ?>
                            <a href="<?= BASE_URL ?>vehicles/add.php?customer_id=<?= $customer['id'] ?>" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> Register Vehicle
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($vehicles)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-car-front display-6 d-block mb-2 opacity-50"></i>
                                <span>No vehicles registered yet for this customer.</span>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0 gms-table">
                                    <thead class="table-dark text-muted uppercase small">
                                        <tr>
                                            <th class="ps-4">Reg Number</th>
                                            <th>Brand & Model</th>
                                            <th>Type</th>
                                            <th>Year</th>
                                            <th>Fuel</th>
                                            <th class="pe-4 text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vehicles as $v): ?>
                                            <tr>
                                                <td class="ps-4 font-monospace fw-bold text-warning">
                                                    <?= e($v['vehicle_number']) ?>
                                                </td>
                                                <td class="fw-semibold text-light">
                                                    <?= e($v['brand']) ?> <?= e($v['model']) ?>
                                                </td>
                                                <td><span class="badge bg-dark border border-secondary text-info text-capitalize"><?= e($v['vehicle_type']) ?></span></td>
                                                <td><span class="text-white font-monospace small"><?= e($v['year']) ?></span></td>
                                                <td><span class="badge bg-dark border border-secondary text-warning text-capitalize"><?= e($v['fuel_type']) ?></span></td>
                                                <td class="pe-4 text-end">
                                                    <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $v['id'] ?>" class="btn btn-outline-info btn-sm" title="View Vehicle">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </a>
                                                    <?php if (hasRole('customer') || isAdmin()): ?>
                                                        <a href="<?= BASE_URL ?>vehicles/edit.php?id=<?= $v['id'] ?>" class="btn btn-outline-warning btn-sm" title="Edit Vehicle">
                                                            <i class="bi bi-pencil-fill"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>vehicles/delete.php?id=<?= $v['id'] ?>" class="btn btn-outline-danger btn-sm" title="Delete Vehicle">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Bookings Overview -->
                <div class="card gms-panel shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-calendar-check-fill me-2 text-warning"></i>Recent Service Bookings
                        </h5>
                        <a href="<?= BASE_URL ?>bookings/create.php?customer_id=<?= $customer['id'] ?>" class="btn btn-gms-primary btn-sm">
                            <i class="bi bi-calendar-plus me-1"></i> New Booking
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentBookings)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-x display-6 d-block mb-2 opacity-50"></i>
                                <span>No bookings found for this customer.</span>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0 gms-table">
                                    <thead class="table-dark text-muted uppercase small">
                                        <tr>
                                            <th class="ps-4">Date</th>
                                            <th>Service</th>
                                            <th>Vehicle</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentBookings as $b): ?>
                                            <tr>
                                                <td class="ps-4 small font-monospace"><?= formatDate($b['booking_date']) ?></td>
                                                <td class="fw-semibold text-light"><?= e($b['service_name']) ?></td>
                                                <td class="small text-warning"><?= e($b['vehicle_number']) ?> (<?= e($b['brand']) ?>)</td>
                                                <td>
                                                    <span class="badge bg-dark-subtle text-warning border border-warning-subtle text-uppercase px-2.5 py-1" style="font-size:0.7rem;">
                                                        <?= e($b['status']) ?>
                                                    </span>
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

    </div>
</div>

<style>
.gms-avatar-lg {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: var(--grad-accent);
    color: #fff;
    font-size: 2rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(249,115,22,0.3);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
