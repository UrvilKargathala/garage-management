<?php
/**
 * staff/service-requests.php — Staff Incoming Service Requests Queue
 * Garage Management System
 *
 * Operational dashboard for Staff/Admin to review pending requests, inspect
 * full customer & address details, and accept/reject them with modern dialogs.
 */

$pageTitle = 'Service Requests Queue';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireStaffOrAdmin();

$pdo = getDBConnection();

// Fetch all pending service requests with full joins
try {
    $stmt = $pdo->query("
        SELECT b.*,
               c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
               v.vehicle_number, v.brand, v.model, v.year, v.fuel_type,
               s.service_name, s.price, s.estimated_time
        FROM bookings b
        JOIN customers c ON b.customer_id = c.id
        JOIN vehicles v ON b.vehicle_id = v.id
        JOIN services s ON b.service_id = s.id
        WHERE b.status = 'pending'
        ORDER BY b.booking_date ASC, b.id ASC
    ");
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[GarageMS] Service Requests Fetch Error: ' . $e->getMessage());
    $requests = [];
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>staff/dashboard.php">Staff Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Service Requests</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="mb-1 text-white fw-bold"><i class="bi bi-bell-fill me-2 text-warning"></i>Incoming Service Requests Queue</h1>
                <p class="text-slate-300 small mb-0 mt-1" style="color:#cbd5e1 !important; font-size:0.95rem;">Review new customer service requests, check vehicle & address details, and accept or reject.</p>
            </div>
            <div>
                <span class="badge bg-warning text-dark px-3 py-2 fs-6 font-monospace fw-bold shadow-sm">
                    <?= count($requests) ?> PENDING REQUEST(S)
                </span>
            </div>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <?php if (empty($requests)): ?>
            <div class="card gms-panel shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="bi bi-clipboard-check display-3 text-success d-block mb-3 opacity-75"></i>
                    <h4 class="text-white fw-bold">All Caught Up!</h4>
                    <p class="text-muted">There are no pending service requests waiting for review.</p>
                    <a href="<?= BASE_URL ?>staff/dashboard.php" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Return to Staff Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($requests as $r): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="card gms-panel h-100 border-secondary shadow-sm gms-request-card">
                            <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                                <span class="badge bg-warning text-dark font-monospace fw-bold">
                                    #BKG-<?= (int)$r['id'] ?>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-calendar3 me-1 text-warning"></i><?= formatDate($r['booking_date']) ?> • <?= e($r['booking_time'] ?: '10:00 AM') ?>
                                </span>
                            </div>
                            <div class="card-body p-4 d-flex flex-column">

                                <!-- Customer Header -->
                                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom border-secondary">
                                    <div class="gms-avatar-small">
                                        <?= strtoupper(mb_substr($r['customer_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 text-white fw-bold"><?= e($r['customer_name']) ?></h5>
                                        <div class="text-muted small">
                                            <i class="bi bi-telephone me-1 text-warning"></i><?= e($r['customer_phone']) ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vehicle & Service Details -->
                                <div class="mb-3">
                                    <div class="small text-muted text-uppercase fw-bold mb-1">Vehicle</div>
                                    <div class="text-white fw-semibold">
                                        🚗 <?= e($r['brand']) ?> <?= e($r['model']) ?> (<?= e($r['year']) ?>)
                                    </div>
                                    <span class="badge bg-dark border border-secondary text-warning font-monospace mt-1">
                                        <?= e($r['vehicle_number']) ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <div class="small text-muted text-uppercase fw-bold mb-1">Service Package</div>
                                    <div class="text-white fw-semibold">
                                        🔧 <?= e($r['service_name']) ?> — <span class="text-warning font-monospace">₹<?= number_format($r['price'], 2) ?></span>
                                    </div>
                                </div>

                                <!-- Location Details -->
                                <div class="p-2.5 rounded border border-secondary mb-4 mt-auto" style="background:var(--clr-dark-4);">
                                    <div class="text-warning small fw-bold mb-1">
                                        <i class="bi bi-geo-alt-fill me-1"></i>Service Location:
                                    </div>
                                    <div class="text-light small">
                                        <?= e($r['service_address'] ?: 'Customer address on file') ?><?= $r['area'] ? ', ' . e($r['area']) : '' ?>, <?= e($r['city'] ?: 'Surat') ?>
                                    </div>
                                    <?php if (!empty($r['remarks'])): ?>
                                        <div class="mt-2 pt-2 border-top border-secondary text-muted small fst-italic">
                                            "<?= e($r['remarks']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Operational Action Buttons -->
                                <div class="d-flex gap-2">
                                    <a href="<?= BASE_URL ?>bookings/view.php?id=<?= (int)$r['id'] ?>" class="btn btn-outline-info flex-grow-1 btn-sm fw-bold">
                                        <i class="bi bi-eye me-1"></i> Details
                                    </a>

                                    <!-- Accept Form -->
                                    <form method="POST" action="<?= BASE_URL ?>staff/accept-service.php" class="flex-grow-1">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-success w-100 btn-sm fw-bold">
                                            <i class="bi bi-check-lg me-1"></i> Accept
                                        </button>
                                    </form>

                                    <!-- Reject Trigger Modal Button -->
                                    <button type="button" class="btn btn-outline-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#rejectModal<?= (int)$r['id'] ?>" title="Reject Request">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- ── Reject Service Modal ── -->
                    <div class="modal fade" id="rejectModal<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content gms-panel border-danger">
                                <div class="modal-header bg-danger bg-opacity-10 border-danger py-3">
                                    <h5 class="modal-title text-danger fw-bold">
                                        <i class="bi bi-exclamation-octagon-fill me-2"></i>Reject Service Request #<?= (int)$r['id'] ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="<?= BASE_URL ?>staff/reject-service.php">
                                    <div class="modal-body p-4">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">

                                        <p class="text-light mb-3">
                                            Are you sure you want to reject the service request for <strong><?= e($r['customer_name']) ?></strong> (<?= e($r['brand']) ?> <?= e($r['model']) ?>)?
                                        </p>

                                        <div class="mb-3">
                                            <label for="rejectionReason<?= (int)$r['id'] ?>" class="form-label text-light fw-semibold small">
                                                Reason for Rejection <span class="text-muted">(Optional)</span>
                                            </label>
                                            <textarea
                                                id="rejectionReason<?= (int)$r['id'] ?>"
                                                name="rejection_reason"
                                                class="form-control gms-input"
                                                rows="3"
                                                placeholder="e.g. Schedule fully booked, parts currently out of stock, outside service area..."
                                            ></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-secondary">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger fw-bold">
                                            <i class="bi bi-x-circle me-1"></i> Confirm Rejection
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
.gms-avatar-small {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--grad-accent);
    color: #fff;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: 0 2px 10px rgba(249, 115, 22, 0.3);
}
.gms-request-card {
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.gms-request-card:hover {
    transform: translateY(-4px);
    border-color: rgba(249, 115, 22, 0.4) !important;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
