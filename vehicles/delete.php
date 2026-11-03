<?php
/**
 * vehicles/delete.php — Delete Vehicle Record
 * Garage Management System
 *
 * RBAC & Anti-IDOR:
 *  ✔ Staff and Admin can delete any vehicle.
 *  ✔ Customers can ONLY delete their own vehicles.
 *  ✔ Enforces ownership check via verifyVehicleOwnership().
 *  ✔ Deletion is POST-only — GET never modifies state.
 *  ✔ CSRF token required on the confirmation form.
 *  ✔ Counts related bookings and service_history before deletion.
 *
 * FIX 8 — Service History Warning:
 *  The database schema uses ON DELETE CASCADE on fk_history_vehicle
 *  (vehicles → service_history). Deleting a vehicle will PERMANENTLY
 *  destroy all associated service history records. This page clearly
 *  warns the user of this before they confirm.
 *
 *  Migration note: If business requirements change to require service
 *  history to survive vehicle deletion, alter the FK to ON DELETE SET NULL
 *  and add a nullable vehicle_id column with a fallback display. That
 *  schema change is deferred to avoid disrupting existing data.
 */

$pageTitle = 'Delete Vehicle';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

// Staff role must NOT delete customer vehicles. Only the vehicle Owner or Admin can delete.
if (hasRole('staff')) {
    setFlash('Access Denied: Staff members cannot delete customer vehicles.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

$pdo = getDBConnection();
$vehicleId = (int)($_GET['id'] ?? 0);

if ($vehicleId <= 0) {
    setFlash('Invalid vehicle ID.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

// Anti-IDOR Verification Guard: Enforces ownership or admin access
verifyVehicleOwnership($pdo, $vehicleId);

// Fetch vehicle details with related record counts for the warning panel
try {
    $stmt = $pdo->prepare(
        'SELECT v.*,
                c.name AS customer_name,
                (SELECT COUNT(*) FROM bookings    b  WHERE b.vehicle_id  = v.id) AS booking_count,
                (SELECT COUNT(*) FROM service_history sh WHERE sh.vehicle_id = v.id) AS history_count
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
} catch (PDOException $e) {
    error_log('[GarageMS] Delete Vehicle Fetch Error: ' . $e->getMessage());
    setFlash('Database error loading vehicle details.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

// ── POST Handler — Confirmed Deletion ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF verification (must be first)
    verifyCsrfToken();

    // Re-verify vehicle ID from POST against the authoritative GET-loaded ID.
    // We use $vehicleId (from GET) that already passed the ownership check.
    // Do NOT trust a POST['vehicle_id'] hidden field.

    try {
        $pdo->beginTransaction();

        // Delete the vehicle record. Due to database FK ON DELETE CASCADE:
        //  - bookings with this vehicle_id will be cascade-deleted
        //  - service_history rows with this vehicle_id will be cascade-deleted
        $stmtDelete = $pdo->prepare('DELETE FROM vehicles WHERE id = ?');
        $stmtDelete->execute([$vehicleId]);

        $pdo->commit();

        logActivity(
            $pdo,
            'Vehicle Deleted',
            "Vehicle '{$vehicle['vehicle_number']}' (ID: {$vehicleId}) owned by '{$vehicle['customer_name']}' was deleted."
            . " Cascade deleted: {$vehicle['booking_count']} booking(s), {$vehicle['history_count']} service history record(s)."
        );
        setFlash(
            "Vehicle {$vehicle['vehicle_number']} and all associated records were permanently deleted.",
            'success'
        );
        redirect(BASE_URL . 'vehicles/list.php');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[GarageMS] Delete Vehicle Error: ' . $e->getMessage());
        setFlash('Failed to delete vehicle. A system error occurred.', 'danger');
        redirect(BASE_URL . 'vehicles/list.php');
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>vehicles/list.php">Vehicles</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $vehicleId ?>"><?= e($vehicle['vehicle_number']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Delete</li>
            </ol>
        </nav>
        <h1><i class="bi bi-trash-fill me-2 text-danger"></i>Delete Vehicle</h1>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card gms-panel border-danger shadow-sm">
                    <div class="card-header bg-danger bg-opacity-10 border-danger py-3">
                        <h5 class="card-title text-danger mb-0 fw-bold">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>Confirm Irreversible Deletion
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="fs-6">Are you sure you want to permanently delete this vehicle?</p>

                        <!-- Vehicle Summary -->
                        <div class="p-3 bg-dark border border-secondary rounded mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-dark-subtle text-warning border border-warning-subtle font-monospace px-3 py-1 fs-6 fw-bold">
                                    <?= e($vehicle['vehicle_number']) ?>
                                </span>
                                <span class="badge bg-secondary text-uppercase"><?= e($vehicle['vehicle_type']) ?></span>
                            </div>
                            <div class="fw-bold text-light mb-1"><?= e($vehicle['brand']) ?> <?= e($vehicle['model']) ?> (<?= (int)$vehicle['year'] ?>)</div>
                            <div class="small text-muted">
                                <i class="bi bi-person-circle text-warning me-1"></i>
                                Owner: <?= e($vehicle['customer_name']) ?>
                            </div>
                        </div>

                        <!-- Cascade Deletion Warnings -->
                        <?php
                        $hasBookings = (int)$vehicle['booking_count'] > 0;
                        $hasHistory  = (int)$vehicle['history_count'] > 0;
                        ?>

                        <?php if ($hasBookings || $hasHistory): ?>
                            <div class="alert alert-danger mb-4">
                                <h6 class="fw-bold mb-2">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    Cascading Deletion Warning
                                </h6>
                                <p class="mb-2 small">Deleting this vehicle will permanently destroy all associated records:</p>
                                <ul class="mb-0 ps-3 small">
                                    <?php if ($hasBookings): ?>
                                        <li><strong><?= (int)$vehicle['booking_count'] ?> service booking(s)</strong> will be permanently deleted.</li>
                                    <?php endif; ?>
                                    <?php if ($hasHistory): ?>
                                        <li>
                                            <strong><?= (int)$vehicle['history_count'] ?> service history record(s)</strong> will be permanently destroyed.
                                            <br><span class="text-danger fw-semibold">⚠ This includes all maintenance records — this data cannot be recovered.</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary mb-4">
                                <i class="bi bi-info-circle me-1"></i>
                                This vehicle has no associated bookings or service history. It can be safely deleted.
                            </div>
                        <?php endif; ?>

                        <!-- Confirmation Form (POST only) -->
                        <form method="POST" action="<?= BASE_URL ?>vehicles/delete.php?id=<?= $vehicleId ?>">
                            <?= csrfField() ?>
                            <div class="d-flex align-items-center justify-content-between pt-2">
                                <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $vehicleId ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Cancel, Keep Vehicle
                                </a>
                                <button type="submit" class="btn btn-danger px-4">
                                    <i class="bi bi-trash-fill me-1"></i> Yes, Delete Permanently
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
