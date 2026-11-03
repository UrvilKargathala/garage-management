<?php
/**
 * vehicles/view.php — View Vehicle Specifications & Service History
 * Garage Management System
 *
 * Requirements & IDOR Security:
 *  ✔ Staff and Admin can view any vehicle details
 *  ✔ Customer role can ONLY view their own vehicle details
 *  ✔ Anti-IDOR check via verifyVehicleOwnership($pdo, $vehicleId)
 */

$pageTitle = 'Vehicle Details';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDBConnection();
$vehicleId = (int)($_GET['id'] ?? 0);

if ($vehicleId <= 0) {
    setFlash('Invalid vehicle ID.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

// Anti-IDOR Verification Guard
verifyVehicleOwnership($pdo, $vehicleId);

// Fetch Vehicle details with owner information
try {
    $stmt = $pdo->prepare(
        'SELECT v.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.id AS customer_id
           FROM vehicles v
           JOIN customers c ON v.customer_id = c.id
          WHERE v.id = ?
          LIMIT 1'
    );
    $stmt->execute([$vehicleId]);
    $vehicle = $stmt->fetch();

    if (!$vehicle) {
        setFlash('Vehicle record not found.', 'danger');
        redirect(BASE_URL . 'vehicles/list.php');
    }

    // Fetch Service Bookings for this vehicle
    $stmtBookings = $pdo->prepare(
        'SELECT b.*, s.service_name, s.price
           FROM bookings b
           JOIN services s ON b.service_id = s.id
          WHERE b.vehicle_id = ?
       ORDER BY b.booking_date DESC, b.id DESC'
    );
    $stmtBookings->execute([$vehicleId]);
    $bookings = $stmtBookings->fetchAll();

    // Fetch Owner Stats: Total Vehicles Owned and Active Services
    $stmtOwnerStats = $pdo->prepare('
        SELECT 
            (SELECT COUNT(*) FROM vehicles WHERE customer_id = ?) AS total_vehicles,
            (SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status IN ("pending", "confirmed", "in_progress")) AS active_services
    ');
    $stmtOwnerStats->execute([$vehicle['customer_id'], $vehicle['customer_id']]);
    $ownerStats = $stmtOwnerStats->fetch() ?: ['total_vehicles' => 1, 'active_services' => 0];

} catch (PDOException $e) {
    error_log('[GarageMS] View Vehicle Error: ' . $e->getMessage());
    setFlash('Database error loading vehicle details.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>vehicles/list.php">Vehicles</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($vehicle['vehicle_number']) ?></li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="text-white fw-bold mb-1"><i class="bi bi-car-front-fill me-2 text-warning"></i>Vehicle Record Details</h1>
                <p class="text-slate-300 small mb-0" style="color:#cbd5e1 !important;">Detailed technical specifications and ownership profile for <?= e($vehicle['vehicle_number']) ?>.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (hasRole('customer')): ?>
                    <a href="<?= BASE_URL ?>bookings/create.php?vehicle_id=<?= $vehicle['id'] ?>" class="btn btn-warning fw-bold text-dark btn-sm">
                        <i class="bi bi-calendar-plus me-1"></i> Book Service
                    </a>
                    <a href="<?= BASE_URL ?>vehicles/edit.php?id=<?= $vehicle['id'] ?>" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-pencil-square me-1"></i> Edit Vehicle
                    </a>
                <?php elseif (isAdmin()): ?>
                    <a href="<?= BASE_URL ?>vehicles/edit.php?id=<?= $vehicle['id'] ?>" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-pencil-square me-1"></i> Edit Vehicle
                    </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>vehicles/list.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <div class="row g-4 mb-4">

            <!-- ── VEHICLE CARD ────────────────────────────────── -->
            <div class="col-lg-7 col-xl-8">
                <div class="card gms-panel shadow-sm p-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom border-secondary pb-3 mb-3">
                        <div>
                            <span class="badge bg-dark-subtle text-warning border border-warning-subtle font-monospace px-3 py-1.5 fs-6 fw-bold">
                                <?= e($vehicle['vehicle_number']) ?>
                            </span>
                            <h2 class="h4 fw-bold text-light mt-2 mb-0"><?= e($vehicle['brand']) ?> <?= e($vehicle['model']) ?></h2>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary text-uppercase px-3 py-1.5 fs-6">
                                <?= e($vehicle['vehicle_type']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6 col-sm-4">
                            <small class="text-muted text-uppercase d-block fw-semibold" style="font-size:0.7rem;">Brand / Make</small>
                            <span class="text-light fw-semibold fs-6"><?= e($vehicle['brand']) ?></span>
                        </div>
                        <div class="col-6 col-sm-4">
                            <small class="text-muted text-uppercase d-block fw-semibold" style="font-size:0.7rem;">Model</small>
                            <span class="text-light fw-semibold fs-6"><?= e($vehicle['model']) ?></span>
                        </div>
                        <div class="col-6 col-sm-4">
                            <small class="text-muted text-uppercase d-block fw-semibold" style="font-size:0.7rem;">Manufacturing Year</small>
                            <span class="text-light fw-semibold fs-6"><?= e($vehicle['year']) ?></span>
                        </div>
                        <div class="col-6 col-sm-4">
                            <small class="text-muted text-uppercase d-block fw-semibold" style="font-size:0.7rem;">Fuel Type</small>
                            <span class="text-warning text-capitalize fw-semibold fs-6"><?= e($vehicle['fuel_type']) ?></span>
                        </div>
                        <div class="col-6 col-sm-4">
                            <small class="text-muted text-uppercase d-block fw-semibold" style="font-size:0.7rem;">Registration Date</small>
                            <span class="text-muted small"><?= formatDate($vehicle['created_at']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── OWNER CARD ──────────────────────────────────── -->
            <div class="col-lg-5 col-xl-4">
                <div class="card gms-panel shadow-sm p-4 border-warning border-opacity-25">
                    <h5 class="card-title fw-bold text-white border-bottom border-secondary pb-3 mb-3 d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-person-badge-fill me-2 text-warning"></i>Owner Information</span>
                        <span class="badge bg-dark border border-secondary text-warning font-monospace small">#CUST-<?= $vehicle['customer_id'] ?></span>
                    </h5>
                    
                    <h3 class="h4 fw-bold text-white mb-1"><?= e($vehicle['customer_name']) ?></h3>
                    <div class="text-slate-300 small mb-3" style="color:#cbd5e1 !important;">Verified Registered Vehicle Owner</div>

                    <div class="p-3 rounded border border-secondary mb-3" style="background: rgba(255,255,255,0.03);">
                        <div class="mb-2">
                            <small class="text-muted text-uppercase d-block fw-bold" style="font-size:0.7rem;">Contact Phone</small>
                            <span class="text-white fw-semibold"><i class="bi bi-telephone-fill text-warning me-2"></i><?= e($vehicle['customer_phone'] ?: 'Not Provided') ?></span>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted text-uppercase d-block fw-bold" style="font-size:0.7rem;">Email Address</small>
                            <span class="text-white fw-semibold"><i class="bi bi-envelope-fill text-warning me-2"></i><?= e($vehicle['customer_email'] ?: 'Not Provided') ?></span>
                        </div>
                    </div>

                    <!-- Owner Stats Breakdown -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="p-2.5 rounded border border-secondary text-center" style="background: rgba(59,130,246,0.08);">
                                <small class="text-info text-uppercase d-block fw-bold" style="font-size:0.68rem;">Vehicles Owned</small>
                                <span class="text-white font-monospace fw-bold fs-5"><?= (int)$ownerStats['total_vehicles'] ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2.5 rounded border border-secondary text-center" style="background: rgba(249,115,22,0.08);">
                                <small class="text-warning text-uppercase d-block fw-bold" style="font-size:0.68rem;">Active Services</small>
                                <span class="text-warning font-monospace fw-bold fs-5"><?= (int)$ownerStats['active_services'] ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (hasRole(['admin', 'staff'])): ?>
                        <div class="pt-2 border-top border-secondary mt-2">
                            <a href="<?= BASE_URL ?>customers/view.php?id=<?= $vehicle['customer_id'] ?>" class="btn btn-outline-info btn-sm w-100 fw-semibold">
                                <i class="bi bi-person-vcard me-1"></i> View Full Customer Profile
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ── SERVICE HISTORY / BOOKINGS TABLE ────────────── -->
        <div class="card gms-panel shadow-sm">
            <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-light">
                    <i class="bi bi-clock-history me-2 text-warning"></i>Service History & Bookings
                </h5>
                <a href="<?= BASE_URL ?>bookings/create.php?vehicle_id=<?= $vehicle['id'] ?>" class="btn btn-gms-primary btn-sm">
                    <i class="bi bi-calendar-plus me-1"></i> Book New Service
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($bookings)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x display-5 d-block mb-3 opacity-50"></i>
                        <p class="h6">No service bookings recorded for this vehicle yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0 gms-table">
                            <thead class="table-dark text-muted uppercase small">
                                <tr>
                                    <th class="ps-4">Booking Date</th>
                                    <th>Service Name</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th class="pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace small"><?= formatDate($b['booking_date']) ?></td>
                                        <td class="fw-semibold text-light"><?= e($b['service_name']) ?></td>
                                        <td class="text-warning fw-semibold">₹<?= number_format($b['price'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-dark-subtle text-warning border border-warning-subtle text-uppercase px-2.5 py-1" style="font-size:0.7rem;">
                                                <?= e($b['status']) ?>
                                            </span>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <a href="<?= BASE_URL ?>bookings/view.php?id=<?= $b['id'] ?>" class="btn btn-outline-info btn-sm">
                                                <i class="bi bi-eye"></i> View
                                            </a>
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
