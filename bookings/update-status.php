<?php
/**
 * bookings/update-status.php — Booking Status State Machine & Completion Tracker
 * Garage Management System
 *
 * Requirements & Security:
 *  ✔ Staff/Admin can change status via the state machine: pending→confirmed→in_progress→completed
 *  ✔ Staff/Admin can cancel from pending or confirmed only
 *  ✔ When status changes to 'completed', a PDO Database Transaction updates bookings AND inserts into service_history
 *  ✔ Customer role can ONLY cancel their own pending/confirmed bookings (FIX 5)
 *  ✔ Anti-IDOR check via verifyBookingOwnership($pdo, $bookingId)
 *  ✔ State machine transitions enforced server-side via validateBookingTransition() (FIX 4)
 *  ✔ GET requests NEVER modify booking state (FIX 6 — removed GET cancellation)
 *  ✔ CSRF token required and verified on every POST (FIX 7)
 */

$pageTitle = 'Update Booking Status';
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

// Fetch booking record (current status from DB is authoritative)
try {
    $stmt = $pdo->prepare(
        'SELECT b.*,
                v.vehicle_number, v.brand, v.model, v.id AS vehicle_id,
                s.service_name, s.price, s.description AS service_desc,
                c.name AS customer_name
           FROM bookings b
           JOIN vehicles v ON b.vehicle_id = v.id
           JOIN services s ON b.service_id = s.id
           JOIN customers c ON b.customer_id = c.id
          WHERE b.id = ?
          LIMIT 1'
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        setFlash('Booking record not found.', 'danger');
        redirect(BASE_URL . 'bookings/list.php');
    }
} catch (PDOException $e) {
    error_log('[GarageMS] Update Status Fetch Error: ' . $e->getMessage());
    setFlash('Database error loading booking.', 'danger');
    redirect(BASE_URL . 'bookings/list.php');
}

// FIX 6: GET requests must NEVER modify booking state.
// The old GET ?action=cancel flow has been removed entirely.
// All state changes now require a POST request with a valid CSRF token.

$errors = [];

// Pre-populate form display values from DB (never trust client for current status)
$currentStatus = $booking['status'];                                          // authoritative from DB
$newStatus     = $_POST['status'] ?? $currentStatus;                          // submitted or default to current
$mechanicName  = $_POST['mechanic_name'] ?? ($_SESSION['user_name'] ?? 'Staff Mechanic');
$serviceCost   = $_POST['service_cost'] ?? $booking['price'];
$workDoneNotes = $_POST['work_done_notes'] ?? ($booking['remarks'] ?? '');

// ── POST Handler — Status Change ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF verification (must be first)
    verifyCsrfToken();

    $newStatus = strtolower(trim($_POST['status'] ?? ''));

    // FIX 4 & 5: Validate state machine transition using the AUTHORITATIVE current status
    // from the DB (already in $currentStatus). Never trust a hidden field.
    $isCustomer = hasRole('customer');
    if (($err = validateBookingTransition($currentStatus, $newStatus, $isCustomer)) !== '') {
        $errors['status'] = $err;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Update Booking Status
            $stmtUpdate = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
            $stmtUpdate->execute([$newStatus, $bookingId]);

            // 2. If status is set to 'completed', populate service_history
            if ($newStatus === 'completed') {
                $finalCost = !empty($_POST['service_cost']) ? (float)$_POST['service_cost'] : (float)$booking['price'];
                $mechanic  = trim($_POST['mechanic_name'] ?? '');
                $workNotes = trim($_POST['work_done_notes'] ?? '');
                $svcDesc   = $booking['service_name'] . ($workNotes !== '' ? ' — ' . $workNotes : '');

                // Check if history record already exists for this booking (idempotent)
                $stmtCheckHist = $pdo->prepare('SELECT id FROM service_history WHERE booking_id = ? LIMIT 1');
                $stmtCheckHist->execute([$bookingId]);

                if (!$stmtCheckHist->fetch()) {
                    $stmtHist = $pdo->prepare(
                        'INSERT INTO service_history (booking_id, vehicle_id, service_description, service_cost, mechanic_name, service_date, remarks)
                         VALUES (?, ?, ?, ?, ?, CURDATE(), ?)'
                    );
                    $stmtHist->execute([
                        $bookingId,
                        $booking['vehicle_id'],
                        $svcDesc,
                        $finalCost,
                        $mechanic !== '' ? $mechanic : null,
                        $workNotes !== '' ? $workNotes : null
                    ]);
                }
            }

            $pdo->commit();

            $logAction = match($newStatus) {
                'in_progress' => 'START_SERVICE',
                'completed'   => 'COMPLETE_SERVICE',
                'confirmed'   => 'ACCEPT_SERVICE',
                'cancelled'   => 'CANCEL_SERVICE',
                default       => 'UPDATE_SERVICE_STATUS'
            };
            $userName = $_SESSION['user_name'] ?? 'Staff';
            logActivity(
                $pdo,
                $logAction,
                "Booking #{$bookingId} for {$booking['customer_name']} ({$booking['brand']} {$booking['model']}) marked as " . str_replace('_', ' ', $newStatus) . " by {$userName}.",
                'booking',
                $bookingId
            );
            setFlash("Booking #{$bookingId} status updated to " . strtoupper(str_replace('_', ' ', $newStatus)) . '!', 'success');
            redirect(BASE_URL . 'bookings/view.php?id=' . $bookingId);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[GarageMS] Update Booking Status Error: ' . $e->getMessage());
            $errors['general'] = 'Failed to update booking status. A database error occurred.';
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>bookings/list.php">Bookings</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>bookings/view.php?id=<?= $bookingId ?>">#BKG-<?= $bookingId ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Update Status</li>
            </ol>
        </nav>
        <h1><i class="bi bi-sliders me-2 text-warning"></i>Update Service Progress</h1>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-xl-6">
                <div class="card gms-panel shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-gear-wide-connected me-2 text-warning"></i>Booking Lifecycle Control
                        </h5>
                        <span class="badge bg-dark border border-secondary text-warning font-monospace">
                            #BKG-<?= $bookingId ?>
                        </span>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span><?= e($errors['general']) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Current Overview Card -->
                        <div class="p-3 bg-dark border border-secondary rounded mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="fw-bold text-light mb-0"><?= e($booking['service_name']) ?></h6>
                                <span class="badge bg-warning text-dark px-2.5 py-1 text-uppercase small">
                                    Current: <?= str_replace('_', ' ', $currentStatus) ?>
                                </span>
                            </div>
                            <div class="small text-muted mb-1">
                                Vehicle: <strong class="text-warning"><?= e($booking['vehicle_number']) ?></strong> (<?= e($booking['brand']) ?> <?= e($booking['model']) ?>)
                            </div>
                            <div class="small text-muted">
                                Customer: <strong class="text-light"><?= e($booking['customer_name']) ?></strong>
                            </div>
                        </div>

                        <?php
                        // Determine terminal / non-cancellable states for UI guidance
                        $isTerminal   = in_array($currentStatus, ['completed', 'cancelled'], true);
                        $isInProgress = $currentStatus === 'in_progress';
                        ?>

                        <?php if ($isTerminal): ?>
                            <div class="alert alert-secondary mb-4">
                                <i class="bi bi-lock-fill me-2"></i>
                                This booking is <strong><?= str_replace('_', ' ', $currentStatus) ?></strong> and cannot be modified further.
                            </div>
                            <div class="text-center">
                                <a href="<?= BASE_URL ?>bookings/view.php?id=<?= $bookingId ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Back to Booking
                                </a>
                            </div>
                        <?php else: ?>

                        <form method="POST" action="<?= BASE_URL ?>bookings/update-status.php?id=<?= $bookingId ?>" novalidate>

                            <?= csrfField() ?>

                            <!-- Target Status Selection -->
                            <div class="mb-4">
                                <label for="selectStatus" class="form-label fw-semibold">
                                    New Status <span class="text-danger">*</span>
                                </label>
                                <select id="selectStatus" name="status" class="form-select gms-input<?= isset($errors['status']) ? ' is-invalid' : '' ?>" required>
                                    <?php if (hasRole(['admin', 'staff'])): ?>
                                        <?php if ($currentStatus === 'pending'): ?>
                                            <option value="pending"   <?= $newStatus === 'pending'   ? 'selected' : '' ?>>Pending — Awaiting Staff Confirmation (current)</option>
                                            <option value="confirmed" <?= $newStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed — Approved &amp; Scheduled</option>
                                            <option value="cancelled" <?= $newStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled — Appointment Voided</option>
                                        <?php elseif ($currentStatus === 'confirmed'): ?>
                                            <option value="confirmed"   <?= $newStatus === 'confirmed'   ? 'selected' : '' ?>>Confirmed — Approved &amp; Scheduled (current)</option>
                                            <option value="in_progress" <?= $newStatus === 'in_progress' ? 'selected' : '' ?>>In Progress — Vehicle Currently Being Serviced</option>
                                            <option value="cancelled"   <?= $newStatus === 'cancelled'   ? 'selected' : '' ?>>Cancelled — Appointment Voided</option>
                                        <?php elseif ($currentStatus === 'in_progress'): ?>
                                            <option value="in_progress" <?= $newStatus === 'in_progress' ? 'selected' : '' ?>>In Progress — Vehicle Currently Being Serviced (current)</option>
                                            <option value="completed"   <?= $newStatus === 'completed'   ? 'selected' : '' ?>>Completed — Work Finished (Creates Service History Record)</option>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Customer view: only cancellation allowed (server enforces this too) -->
                                        <option value="cancelled">Cancel this Booking</option>
                                    <?php endif; ?>
                                </select>
                                <?php if (isset($errors['status'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['status']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Additional Details for Completion (Staff/Admin only) -->
                            <?php if (hasRole(['admin', 'staff'])): ?>
                            <div id="completionFields" class="p-3 bg-dark border border-secondary rounded mb-4">
                                <h6 class="fw-bold text-warning mb-3"><i class="bi bi-tools me-2"></i>Completion &amp; Invoice Details</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="mechName" class="form-label fw-semibold small">Mechanic Name</label>
                                        <input
                                            type="text"
                                            id="mechName"
                                            name="mechanic_name"
                                            class="form-control gms-input form-control-sm"
                                            value="<?= e($mechanicName) ?>"
                                            placeholder="e.g. Master Mechanic Mike"
                                        >
                                    </div>
                                    <div class="col-md-6">
                                        <label for="finalCost" class="form-label fw-semibold small">Final Cost Charged (₹)</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            id="finalCost"
                                            name="service_cost"
                                            class="form-control gms-input form-control-sm font-monospace"
                                            value="<?= e($serviceCost) ?>"
                                        >
                                    </div>
                                    <div class="col-12">
                                        <label for="workNotes" class="form-label fw-semibold small">Work Completed Notes</label>
                                        <textarea
                                            id="workNotes"
                                            name="work_done_notes"
                                            rows="2"
                                            class="form-control gms-input form-control-sm"
                                            placeholder="Describe parts replaced, fluids topped up, test drive results..."
                                        ><?= e($workDoneNotes) ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Customer info: warn about in_progress cancellation (already blocked server-side) -->
                            <?php if (hasRole('customer') && $isInProgress): ?>
                                <div class="alert alert-warning mb-4">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    This booking is currently <strong>In Progress</strong> and cannot be cancelled.
                                </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary">
                                <a href="<?= BASE_URL ?>bookings/view.php?id=<?= $bookingId ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-gms-primary px-4">
                                    <i class="bi bi-check-circle me-1"></i> Save Status Change
                                </button>
                            </div>

                        </form>

                        <?php endif; // end !$isTerminal ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
