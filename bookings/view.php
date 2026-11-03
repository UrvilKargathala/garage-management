<?php
/**
 * bookings/view.php — Detailed View of Service Appointment
 * Garage Management System
 *
 * Requirements & Security:
 *  ✔ Customer role can ONLY view their own bookings
 *  ✔ Anti-IDOR check via verifyBookingOwnership($pdo, $bookingId)
 *  ✔ Detailed cards for Service, Vehicle, Customer, and Status Timeline
 *  ✔ Direct action triggers based on user role and booking lifecycle
 */

$pageTitle = 'Booking Details';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDBConnection();
$bookingId = (int)($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    setFlash('Invalid booking ID.', 'danger');
    redirect(BASE_URL . 'bookings/list.php');
}

// Anti-IDOR Verification Guard
verifyBookingOwnership($pdo, $bookingId);

// Fetch full booking details with joins
try {
    $stmt = $pdo->prepare(
        'SELECT b.*,
                c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.address AS customer_address, c.id AS customer_id,
                v.vehicle_number, v.brand, v.model, v.year, v.fuel_type, v.vehicle_type, v.id AS vehicle_id,
                s.service_name, s.description AS service_desc, s.price, s.estimated_time,
                u.name AS staff_name
           FROM bookings b
           JOIN customers c ON b.customer_id = c.id
           JOIN vehicles v ON b.vehicle_id = v.id
           JOIN services s ON b.service_id = s.id
           LEFT JOIN users u ON b.staff_id = u.id
          WHERE b.id = ?
          LIMIT 1'
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        setFlash('Booking record not found.', 'danger');
        redirect(BASE_URL . (hasRole('customer') ? 'customer/dashboard.php' : 'staff/dashboard.php'));
    }
} catch (PDOException $e) {
    error_log('[GarageMS] View Booking Error: ' . $e->getMessage());
    setFlash('Database error loading booking details.', 'danger');
    redirect(BASE_URL . (hasRole('customer') ? 'customer/dashboard.php' : 'staff/dashboard.php'));
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?><?= hasRole('customer') ? 'customer/dashboard.php' : 'staff/dashboard.php' ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>bookings/list.php">Bookings</a></li>
                <li class="breadcrumb-item active" aria-current="page">#BKG-<?= $booking['id'] ?></li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="text-white fw-bold"><i class="bi bi-calendar-check me-2 text-warning"></i>Booking Details #BKG-<?= $booking['id'] ?></h1>
                <span class="text-muted small">Created on <?= formatDateTime($booking['created_at']) ?></span>
            </div>

            <?php
            $statusBadge = match ($booking['status']) {
                'pending'     => 'bg-warning text-dark border-warning',
                'confirmed'   => 'bg-primary border-primary text-white',
                'in_progress' => 'bg-info text-dark border-info',
                'completed'   => 'bg-success border-success text-white',
                'cancelled'   => 'bg-danger border-danger text-white',
                default       => 'bg-secondary'
            };
            ?>
            <span class="badge <?= $statusBadge ?> px-3 py-2 fs-6 rounded-pill text-uppercase font-monospace fw-bold">
                <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i> <?= str_replace('_', ' ', $booking['status']) ?>
            </span>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <?php if ($booking['status'] === 'cancelled' && !empty($booking['rejection_reason'])): ?>
            <div class="alert alert-danger mb-4 p-3 border-danger shadow-sm">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-octagon-fill fs-4 text-danger"></i>
                    <div>
                        <strong class="text-danger">Booking Cancelled / Rejected:</strong>
                        <div class="text-light small mt-1"><?= e($booking['rejection_reason']) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- ── LEFT COLUMN: APPOINTMENT & SERVICE SPECS ────── -->
            <div class="col-lg-8">

                <!-- Service Info Card -->
                <div class="card gms-panel shadow-sm mb-4">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-tools me-2 text-warning"></i>Garage Service Requested
                        </h5>
                        <span class="fs-4 fw-bold text-warning font-monospace">₹<?= number_format($booking['price'], 2) ?></span>
                    </div>
                    <div class="card-body p-4">
                        <h4 class="h5 fw-bold text-light mb-2"><?= e($booking['service_name']) ?></h4>
                        <p class="text-muted mb-3"><?= e($booking['service_desc'] ?: 'Standard garage service operation.') ?></p>

                        <?php if ($booking['estimated_time']): ?>
                            <div class="small text-muted border-top border-secondary pt-3">
                                <i class="bi bi-clock me-1 text-warning"></i> Estimated Duration: <strong><?= e($booking['estimated_time']) ?> hour(s)</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Service Location / Address Card -->
                <div class="card gms-panel shadow-sm mb-4">
                    <div class="card-header bg-transparent border-secondary py-3">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-geo-alt-fill me-2 text-warning"></i>Service Address & Location
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="p-3 rounded border border-secondary" style="background:var(--clr-dark-4);">
                            <div class="text-light fs-6 fw-semibold mb-1">
                                <?= e($booking['service_address'] ?: 'Garage Service Center') ?>
                            </div>
                            <div class="text-muted small">
                                <?= $booking['area'] ? e($booking['area']) . ', ' : '' ?><?= e($booking['city'] ?: 'Surat') ?>, <?= e($booking['state'] ?: 'Gujarat') ?> - <?= e($booking['pincode'] ?: '395009') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointment Schedule & Remarks -->
                <div class="card gms-panel shadow-sm mb-4">
                    <div class="card-header bg-transparent border-secondary py-3">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-calendar-event me-2 text-warning"></i>Schedule & Notes
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <small class="text-muted text-uppercase d-block fw-semibold" style="font-size:0.7rem;">Scheduled Date</small>
                                <span class="fs-5 fw-bold text-light font-monospace">
                                    <i class="bi bi-calendar3 me-2 text-warning"></i><?= formatDate($booking['booking_date']) ?>
                                </span>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted text-uppercase d-block fw-semibold" style="font-size:0.7rem;">Time Slot</small>
                                <span class="fs-5 fw-bold text-light font-monospace">
                                    <i class="bi bi-clock me-2 text-warning"></i><?= $booking['booking_time'] ? e($booking['booking_time']) : 'Flexible' ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($booking['remarks']): ?>
                            <div class="border-top border-secondary pt-3 mt-2">
                                <small class="text-muted text-uppercase d-block fw-semibold mb-1" style="font-size:0.7rem;">Customer Remarks / Notes</small>
                                <div class="p-3 bg-dark border border-secondary rounded text-light small">
                                    <?= e($booking['remarks']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Panel -->
                <div class="card gms-panel shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-gear-fill me-2 text-warning"></i>Booking Actions
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap gap-2 align-items-center">

                            <?php if (hasRole(['admin', 'staff'])): ?>
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>staff/accept-service.php" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <button type="submit" class="btn btn-success fw-bold">
                                            <i class="bi bi-check-lg me-1"></i> Accept Service
                                        </button>
                                    </form>
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>bookings/update-status.php?id=<?= $booking['id'] ?>" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="btn btn-primary fw-bold">
                                            <i class="bi bi-tools me-1"></i> Start Service (In Progress)
                                        </button>
                                    </form>
                                <?php elseif ($booking['status'] === 'in_progress'): ?>
                                    <a href="<?= BASE_URL ?>bookings/update-status.php?id=<?= $booking['id'] ?>" class="btn btn-success fw-bold">
                                        <i class="bi bi-check2-circle me-1"></i> Mark Service Completed
                                    </a>
                                <?php endif; ?>

                                <?php if (!in_array($booking['status'], ['completed', 'cancelled'], true)): ?>
                                    <a href="<?= BASE_URL ?>bookings/update-status.php?id=<?= $booking['id'] ?>" class="btn btn-warning fw-bold">
                                        <i class="bi bi-sliders me-1"></i> Update Status & Progress
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (hasRole('customer') && in_array($booking['status'], ['pending', 'confirmed'], true)): ?>
                                <form method="POST" action="<?= BASE_URL ?>bookings/update-status.php?id=<?= $booking['id'] ?>" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="bi bi-x-circle me-1"></i> Cancel My Booking
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a href="<?= BASE_URL ?>bookings/list.php" class="btn btn-outline-secondary ms-auto">
                                <i class="bi bi-arrow-left me-1"></i> Back to Bookings
                            </a>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── RIGHT COLUMN: VEHICLE & CUSTOMER DETAILS ───── -->
            <div class="col-lg-4">

                <!-- Assigned Staff Card -->
                <div class="card gms-panel shadow-sm mb-4 p-4">
                    <h5 class="card-title fw-bold text-light border-bottom border-secondary pb-3 mb-3">
                        <i class="bi bi-person-badge-fill me-2 text-info"></i>Assigned Staff
                    </h5>
                    <?php if (!empty($booking['staff_name'])): ?>
                        <div class="d-flex align-items-center gap-3">
                            <div class="gms-avatar-small bg-info text-dark fw-bold">
                                <?= strtoupper(mb_substr($booking['staff_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h5 class="mb-0 text-white fw-bold"><?= e($booking['staff_name']) ?></h5>
                                <span class="badge bg-info text-dark mt-1">GarageMS Technician</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0"><i class="bi bi-hourglass-split me-1 text-warning"></i>Not yet assigned to a technician.</p>
                    <?php endif; ?>
                </div>

                <!-- Vehicle Info -->
                <div class="card gms-panel shadow-sm mb-4 p-4">
                    <h5 class="card-title fw-bold text-light border-bottom border-secondary pb-3 mb-3">
                        <i class="bi bi-car-front-fill me-2 text-warning"></i>Vehicle Details
                    </h5>
                    <div class="font-monospace fw-bold text-warning fs-5 mb-1"><?= e($booking['vehicle_number']) ?></div>
                    <h3 class="h5 fw-bold text-light mb-3"><?= e($booking['brand']) ?> <?= e($booking['model']) ?></h3>

                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Type:</span>
                            <span class="text-light text-capitalize"><?= e($booking['vehicle_type']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Year:</span>
                            <span class="text-light"><?= e($booking['year']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-0">
                            <span class="text-muted">Fuel:</span>
                            <span class="text-warning text-capitalize"><?= e($booking['fuel_type']) ?></span>
                        </div>
                    </div>

                    <div class="pt-3 border-top border-secondary mt-3">
                        <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $booking['vehicle_id'] ?>" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bi bi-eye me-1"></i> View Vehicle Record
                        </a>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="card gms-panel shadow-sm p-4">
                    <h5 class="card-title fw-bold text-light border-bottom border-secondary pb-3 mb-3">
                        <i class="bi bi-person-circle me-2 text-warning"></i>Customer Info
                    </h5>
                    <h3 class="h5 fw-bold text-light mb-1"><?= e($booking['customer_name']) ?></h3>
                    <small class="text-muted font-monospace d-block mb-3">ID: #CUST-<?= $booking['customer_id'] ?></small>

                    <div class="small">
                        <div class="mb-2">
                            <i class="bi bi-telephone text-warning me-2"></i><?= e($booking['customer_phone']) ?>
                        </div>
                        <?php if ($booking['customer_email']): ?>
                            <div class="mb-2">
                                <i class="bi bi-envelope text-warning me-2"></i><?= e($booking['customer_email']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (hasRole(['admin', 'staff'])): ?>
                        <div class="pt-3 border-top border-secondary mt-3">
                            <a href="<?= BASE_URL ?>customers/view.php?id=<?= $booking['customer_id'] ?>" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-person-vcard me-1"></i> View Customer Profile
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
