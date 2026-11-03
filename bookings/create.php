<?php
/**
 * bookings/create.php — New Service Booking
 * Garage Management System
 *
 * Requirements & Security Checklist:
 *  ✔ Customer role: customer_id is NEVER trusted from POST/GET — derived strictly from session
 *  ✔ Vehicle selection is restricted to vehicles owned by the authenticated customer
 *  ✔ Anti-IDOR: vehicle_id is verified via canAccessVehicleData($pdo, $vehicleId)
 *  ✔ Active services are loaded dynamically from MySQL
 *  ✔ Date & Time validation (cannot book in the past)
 *  ✔ Uses PDO Database Transactions ($pdo->beginTransaction / commit / rollBack)
 */

$pageTitle = 'Book a Service';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Enforce Customer role only
requireCustomer();

$pdo = getDBConnection();
$errors = [];

// Selected vehicle_id or service_id pre-filled via query params
$preSelectedVehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;
$preSelectedServiceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;

// Determine customer_id and load customer profile + vehicles list
$customerId = getLoggedInCustomerId($pdo);
if (!$customerId) {
    setFlash('Customer profile not found. Please contact support.', 'danger');
    redirect(BASE_URL . 'customer/dashboard.php');
}

// Fetch customer saved profile info
$stmtCust = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
$stmtCust->execute([$customerId]);
$customerProfile = $stmtCust->fetch() ?: [];

// Fetch only this customer's vehicles
try {
    $stmtV = $pdo->prepare('SELECT * FROM vehicles WHERE customer_id = ? ORDER BY vehicle_number ASC');
    $stmtV->execute([$customerId]);
    $vehicles = $stmtV->fetchAll();
} catch (PDOException $e) {
    $vehicles = [];
}

// Fetch active services
try {
    $stmtS = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY service_name ASC");
    $activeServices = $stmtS->fetchAll();
} catch (PDOException $e) {
    $activeServices = [];
}

// Form defaults
$vehicleId      = $preSelectedVehicleId ?? ($vehicles[0]['id'] ?? '');
$serviceId      = $preSelectedServiceId ?? ($activeServices[0]['id'] ?? '');
$bookingDate    = date('Y-m-d');
$bookingTime    = '10:00';
$remarks        = '';
$serviceAddress = $customerProfile['address'] ?? '';
$area           = '';
$city           = 'Surat';
$state          = 'Gujarat';
$pincode        = '';
$submittedBooking = null;

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // CSRF guard — must be first in POST handler
    $vehicleId      = (int)($_POST['vehicle_id'] ?? 0);
    $serviceId      = (int)($_POST['service_id'] ?? 0);
    $bookingDate    = trim($_POST['booking_date'] ?? '');
    $bookingTime    = trim($_POST['booking_time'] ?? '');
    $remarks        = trim($_POST['remarks'] ?? '');
    $serviceAddress = trim($_POST['service_address'] ?? '');
    $area           = trim($_POST['area'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $state          = trim($_POST['state'] ?? '');
    $pincode        = trim($_POST['pincode'] ?? '');

    $targetCustomerId = $customerId; // Strictly session-derived customer ID

    // 1. Validate Vehicle Selection & IDOR Security Check
    if ($vehicleId <= 0) {
        $errors['vehicle_id'] = 'Please select a vehicle.';
    } else {
        if (!canAccessVehicleData($pdo, $vehicleId)) {
            $errors['vehicle_id'] = 'Security Error: You do not have permission to book for this vehicle.';
        }
    }

    // 2. Validate Service Selection
    if ($serviceId <= 0) {
        $errors['service_id'] = 'Please select a service.';
    } else {
        $stmtSvcCheck = $pdo->prepare("SELECT id FROM services WHERE id = ? AND status = 'active' LIMIT 1");
        $stmtSvcCheck->execute([$serviceId]);
        if (!$stmtSvcCheck->fetch()) {
            $errors['service_id'] = 'Invalid or inactive service selected.';
        }
    }

    // 3. Validate Booking Date & Time
    if (($err = validateBookingDate($bookingDate)) !== '') {
        $errors['booking_date'] = $err;
    }
    if (($err = validateBookingTime($bookingTime)) !== '') {
        $errors['booking_time'] = $err;
    }

    // 4. Validate Address Fields (Mandatory for booking)
    if ($serviceAddress === '') {
        $errors['service_address'] = 'Full service address is required.';
    } elseif (mb_strlen($serviceAddress) > 500) {
        $errors['service_address'] = 'Address cannot exceed 500 characters.';
    }

    if ($city === '') {
        $errors['city'] = 'City is required.';
    } elseif (mb_strlen($city) > 100) {
        $errors['city'] = 'City cannot exceed 100 characters.';
    }

    if ($state === '') {
        $errors['state'] = 'State is required.';
    } elseif (mb_strlen($state) > 100) {
        $errors['state'] = 'State cannot exceed 100 characters.';
    }

    if ($pincode === '') {
        $errors['pincode'] = 'Pincode is required.';
    } elseif (!preg_match('/^[0-9]{4,10}$/', $pincode)) {
        $errors['pincode'] = 'Please enter a valid 4 to 10 digit postal pincode.';
    }

    if ($area !== '' && mb_strlen($area) > 100) {
        $errors['area'] = 'Area / locality cannot exceed 100 characters.';
    }

    // 5. Database Transaction
    if (empty($errors) && $targetCustomerId) {
        try {
            $pdo->beginTransaction();

            $stmtInsert = $pdo->prepare(
                'INSERT INTO bookings (customer_id, vehicle_id, service_id, booking_date, booking_time, status, remarks, service_address, area, city, state, pincode)
                 VALUES (?, ?, ?, ?, ?, "pending", ?, ?, ?, ?, ?, ?)'
            );
            $stmtInsert->execute([
                $targetCustomerId,
                $vehicleId,
                $serviceId,
                $bookingDate,
                $bookingTime !== '' ? $bookingTime : null,
                $remarks !== '' ? $remarks : null,
                $serviceAddress,
                $area !== '' ? $area : null,
                $city,
                $state,
                $pincode
            ]);
            $newBookingId = (int)$pdo->lastInsertId();

            $pdo->commit();

            logActivity($pdo, 'BOOK_SERVICE', "Service Booking #{$newBookingId} requested for Vehicle ID {$vehicleId}.", 'booking', $newBookingId);
            
            // Fetch detailed booking for the animated success modal
            $stmtDetail = $pdo->prepare('
                SELECT b.*, v.brand, v.model, v.vehicle_number, s.service_name, s.price
                FROM bookings b
                JOIN vehicles v ON b.vehicle_id = v.id
                JOIN services s ON b.service_id = s.id
                WHERE b.id = ? LIMIT 1
            ');
            $stmtDetail->execute([$newBookingId]);
            $submittedBooking = $stmtDetail->fetch();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[GarageMS] Create Booking Error: ' . $e->getMessage());
            $errors['general'] = 'Failed to create booking. Database transaction error occurred.';
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customer/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>bookings/list.php">Bookings</a></li>
                <li class="breadcrumb-item active" aria-current="page">Book Service</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="mb-0 text-white fw-bold"><i class="bi bi-calendar-plus-fill me-2 text-warning"></i>Schedule Service Booking</h1>
                <p class="text-muted small mb-0 mt-1">Select your vehicle, service package, time, and service pickup/doorstep location.</p>
            </div>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card gms-panel shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-calendar-event me-2 text-warning"></i>Service Request Form
                        </h5>
                        <span class="badge bg-warning text-dark font-monospace fw-bold">STEP 1 OF 1</span>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span><?= e($errors['general']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($vehicles)): ?>
                            <div class="alert alert-warning mb-4 p-3 border-warning" style="background:rgba(234,179,8,0.1);">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                                    <div>
                                        <h6 class="fw-bold text-warning mb-1">No Registered Vehicles Found</h6>
                                        <p class="mb-2 text-light small">You need to register at least one vehicle in your GarageMS account before you can book a service appointment.</p>
                                        <a href="<?= BASE_URL ?>vehicles/add.php" class="btn btn-warning btn-sm fw-bold">
                                            <i class="bi bi-plus-lg me-1"></i> Register Vehicle Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL ?>bookings/create.php" novalidate id="bookingForm">

                            <?= csrfField() ?>

                            <!-- 1. Vehicle Selection -->
                            <div class="mb-4">
                                <label for="bookVehicle" class="form-label fw-semibold text-light">
                                    1. Select Vehicle <span class="text-danger">*</span>
                                </label>
                                <select id="bookVehicle" name="vehicle_id" class="form-select gms-input<?= isset($errors['vehicle_id']) ? ' is-invalid' : '' ?>" required <?= empty($vehicles) ? 'disabled' : '' ?>>
                                    <option value="">-- Choose Your Vehicle --</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= $v['id'] ?>" <?= (int)$vehicleId === (int)$v['id'] ? 'selected' : '' ?>>
                                            🚗 <?= e($v['brand']) ?> <?= e($v['model']) ?> (<?= e($v['year']) ?>) — [<?= e($v['vehicle_number']) ?>]
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['vehicle_id'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['vehicle_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- 2. Service Selection -->
                            <div class="mb-4">
                                <label for="bookService" class="form-label fw-semibold text-light">
                                    2. Select Service Package <span class="text-danger">*</span>
                                </label>
                                <select id="bookService" name="service_id" class="form-select gms-input<?= isset($errors['service_id']) ? ' is-invalid' : '' ?>" required>
                                    <option value="">-- Choose Garage Service Package --</option>
                                    <?php foreach ($activeServices as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= (int)$serviceId === (int)$s['id'] ? 'selected' : '' ?>>
                                            🔧 <?= e($s['service_name']) ?> — ₹<?= number_format($s['price'], 2) ?>
                                            <?= $s['estimated_time'] ? ' (~' . e($s['estimated_time']) . ' hrs)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['service_id'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['service_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- 3. Preferred Date & Time -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="bookDate" class="form-label fw-semibold text-light">
                                        3. Preferred Date <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        id="bookDate"
                                        name="booking_date"
                                        class="form-control gms-input<?= isset($errors['booking_date']) ? ' is-invalid' : '' ?>"
                                        value="<?= e($bookingDate) ?>"
                                        min="<?= date('Y-m-d') ?>"
                                        required
                                    >
                                    <?php if (isset($errors['booking_date'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['booking_date']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="bookTime" class="form-label fw-semibold text-light">
                                        4. Preferred Time Slot <span class="text-muted font-normal small">(Optional)</span>
                                    </label>
                                    <select id="bookTime" name="booking_time" class="form-select gms-input<?= isset($errors['booking_time']) ? ' is-invalid' : '' ?>">
                                        <option value="09:00" <?= $bookingTime === '09:00' ? 'selected' : '' ?>>09:00 AM - Morning Slot</option>
                                        <option value="10:00" <?= $bookingTime === '10:00' ? 'selected' : '' ?>>10:00 AM - Morning Slot</option>
                                        <option value="11:00" <?= $bookingTime === '11:00' ? 'selected' : '' ?>>11:00 AM - Late Morning</option>
                                        <option value="14:00" <?= $bookingTime === '14:00' ? 'selected' : '' ?>>02:00 PM - Afternoon Slot</option>
                                        <option value="15:00" <?= $bookingTime === '15:00' ? 'selected' : '' ?>>03:00 PM - Afternoon Slot</option>
                                        <option value="16:00" <?= $bookingTime === '16:00' ? 'selected' : '' ?>>04:00 PM - Late Afternoon</option>
                                    </select>
                                    <?php if (isset($errors['booking_time'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['booking_time']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 4. Service Address / Location Section -->
                            <div class="p-3 mb-4 rounded border border-secondary" style="background:var(--clr-dark-4);">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-geo-alt-fill text-warning fs-5"></i>
                                        <div>
                                            <h6 class="mb-0 fw-bold text-white">5. Service Location & Address</h6>
                                            <small class="text-muted">Where should our technician attend / pick up the vehicle?</small>
                                        </div>
                                    </div>
                                    <?php if (!empty($customerProfile['address'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning" id="btnUseSavedAddress" data-saved-address="<?= e($customerProfile['address']) ?>">
                                            <i class="bi bi-house-door me-1"></i> Use My Saved Address
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Full Address -->
                                <div class="mb-3">
                                    <label for="serviceAddress" class="form-label fw-semibold text-light small">
                                        Full Address / Street <span class="text-danger">*</span>
                                    </label>
                                    <textarea
                                        id="serviceAddress"
                                        name="service_address"
                                        rows="2"
                                        class="form-control gms-input<?= isset($errors['service_address']) ? ' is-invalid' : '' ?>"
                                        placeholder="Flat/House No, Building Name, Street..."
                                        required
                                    ><?= e($serviceAddress) ?></textarea>
                                    <?php if (isset($errors['service_address'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['service_address']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6 mb-2">
                                        <label for="serviceArea" class="form-label fw-semibold text-light small">Area / Locality</label>
                                        <input
                                            type="text"
                                            id="serviceArea"
                                            name="area"
                                            class="form-control gms-input<?= isset($errors['area']) ? ' is-invalid' : '' ?>"
                                            placeholder="e.g. Adajan, Vesu, Ring Road"
                                            value="<?= e($area) ?>"
                                        >
                                        <?php if (isset($errors['area'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['area']) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-2">
                                        <label for="serviceCity" class="form-label fw-semibold text-light small">City <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="serviceCity"
                                            name="city"
                                            class="form-control gms-input<?= isset($errors['city']) ? ' is-invalid' : '' ?>"
                                            placeholder="City"
                                            value="<?= e($city) ?>"
                                            required
                                        >
                                        <?php if (isset($errors['city'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['city']) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-2">
                                        <label for="serviceState" class="form-label fw-semibold text-light small">State <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="serviceState"
                                            name="state"
                                            class="form-control gms-input<?= isset($errors['state']) ? ' is-invalid' : '' ?>"
                                            placeholder="State"
                                            value="<?= e($state) ?>"
                                            required
                                        >
                                        <?php if (isset($errors['state'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['state']) ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 mb-2">
                                        <label for="servicePincode" class="form-label fw-semibold text-light small">Pincode <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="servicePincode"
                                            name="pincode"
                                            class="form-control gms-input<?= isset($errors['pincode']) ? ' is-invalid' : '' ?>"
                                            placeholder="e.g. 395009"
                                            value="<?= e($pincode) ?>"
                                            required
                                        >
                                        <?php if (isset($errors['pincode'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['pincode']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="mb-4">
                                <label for="bookRemarks" class="form-label fw-semibold text-light">
                                    6. Customer Remarks / Specific Issues <span class="text-muted font-normal small">(Optional)</span>
                                </label>
                                <textarea
                                    id="bookRemarks"
                                    name="remarks"
                                    rows="2"
                                    class="form-control gms-input"
                                    placeholder="Describe any abnormal noise, brake vibration, oil leak, or special requests..."
                                ><?= e($remarks) ?></textarea>
                            </div>

                            <!-- Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary">
                                <a href="<?= BASE_URL ?>customer/dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-gms-primary px-4 fw-bold" <?= empty($vehicles) ? 'disabled' : '' ?> id="btnSubmitBooking">
                                    <i class="bi bi-check-circle me-1"></i> Submit Service Request
                                </button>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     MODERN ANIMATED SUCCESS CONFIRMATION MODAL
     ════════════════════════════════════════════════════════════ -->
<?php if ($submittedBooking): ?>
<div class="modal fade show" id="bookingSuccessModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content gms-panel border-warning shadow-lg text-center p-4">
            
            <!-- Animated Checkmark Icon -->
            <div class="mb-3 d-flex justify-content-center">
                <div class="gms-success-icon-wrap">
                    <i class="bi bi-check-lg"></i>
                </div>
            </div>

            <span class="badge bg-warning-subtle text-warning border border-warning-subtle text-uppercase px-3 py-1 font-monospace fw-bold mb-2 align-self-center">
                REQUEST SUBMITTED
            </span>
            <h3 class="fw-bold text-white mb-1">Service Request Sent!</h3>
            <p class="text-muted small mb-4">Your service appointment has been submitted successfully to GarageMS.</p>

            <!-- Booking Summary Card -->
            <div class="p-3 rounded text-start mb-4 border border-secondary" style="background:var(--clr-dark-4);">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary">
                    <span class="text-muted small">Booking Ref</span>
                    <span class="font-monospace text-warning fw-bold">#BKG-<?= (int)$submittedBooking['id'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small"><i class="bi bi-car-front me-1 text-info"></i>Vehicle</span>
                    <span class="text-light fw-semibold"><?= e($submittedBooking['brand']) ?> <?= e($submittedBooking['model']) ?> (<?= e($submittedBooking['vehicle_number']) ?>)</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small"><i class="bi bi-wrench me-1 text-warning"></i>Service</span>
                    <span class="text-light fw-semibold"><?= e($submittedBooking['service_name']) ?> (₹<?= number_format($submittedBooking['price'], 2) ?>)</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small"><i class="bi bi-calendar3 me-1 text-success"></i>Date & Time</span>
                    <span class="text-light fw-semibold"><?= formatDate($submittedBooking['booking_date']) ?> • <?= e($submittedBooking['booking_time'] ?: '10:00 AM') ?></span>
                </div>
                <div class="pt-2 border-top border-secondary">
                    <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1 text-danger"></i>Service Location:</div>
                    <div class="text-light small font-monospace">
                        <?= e($submittedBooking['service_address']) ?><?= $submittedBooking['area'] ? ', ' . e($submittedBooking['area']) : '' ?>, <?= e($submittedBooking['city']) ?>, <?= e($submittedBooking['state']) ?> - <?= e($submittedBooking['pincode']) ?>
                    </div>
                </div>
            </div>

            <div class="alert alert-dark border-secondary p-2 mb-4 text-center small text-muted">
                <i class="bi bi-hourglass-split text-warning me-1"></i> Waiting for GarageMS staff to accept your request.
            </div>

            <div class="d-grid gap-2">
                <a href="<?= BASE_URL ?>customer/dashboard.php" class="btn btn-gms-primary fw-bold py-2">
                    <i class="bi bi-speedometer2 me-1"></i> View Active Service in Dashboard
                </a>
                <a href="<?= BASE_URL ?>bookings/view.php?id=<?= (int)$submittedBooking['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    View Full Booking Details
                </a>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnSaved = document.getElementById('btnUseSavedAddress');
    if (btnSaved) {
        btnSaved.addEventListener('click', function() {
            const addr = this.getAttribute('data-saved-address') || '';
            const addrInput = document.getElementById('serviceAddress');
            if (addrInput) {
                addrInput.value = addr;
                addrInput.focus();
            }
        });
    }
});
</script>

<style>
.gms-success-icon-wrap {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(34,197,94,0.2), rgba(34,197,94,0.05));
    border: 2px solid #22c55e;
    color: #22c55e;
    font-size: 2.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 25px rgba(34,197,94,0.3);
    animation: successPulse 2s infinite ease-in-out;
}
@keyframes successPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 25px rgba(34,197,94,0.3); }
    50% { transform: scale(1.08); box-shadow: 0 0 35px rgba(34,197,94,0.5); }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
