<?php
/**
 * services/delete.php — Delete Garage Service
 * Garage Management System
 *
 * Requirements & RBAC:
 *  ✔ Staff or Admin only (`requireStaffOrAdmin()`)
 *  ✔ Confirmation before deletion
 *  ✔ Handles active booking reference checks
 *  ✔ Uses PDO prepared statements
 */

$pageTitle = 'Delete Service';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireStaffOrAdmin();

$pdo = getDBConnection();
$serviceId = (int)($_GET['id'] ?? 0);

if ($serviceId <= 0) {
    setFlash('Invalid service ID.', 'danger');
    redirect(BASE_URL . 'services/list.php');
}

// Fetch Service details & check for linked bookings
try {
    $stmt = $pdo->prepare(
        'SELECT s.*,
                (SELECT COUNT(*) FROM bookings b WHERE b.service_id = s.id) AS booking_count
           FROM services s
          WHERE s.id = ?
          LIMIT 1'
    );
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch();

    if (!$service) {
        setFlash('Service record not found.', 'danger');
        redirect(BASE_URL . 'services/list.php');
    }
} catch (PDOException $e) {
    error_log('[GarageMS] Delete Service Fetch Error: ' . $e->getMessage());
    setFlash('Database error retrieving service record.', 'danger');
    redirect(BASE_URL . 'services/list.php');
}

// ── POST Handler — Confirmed Deletion ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // CSRF guard — must be first in POST handler
    if ($service['booking_count'] > 0) {
        // Soft delete / mark inactive instead if referenced by bookings to prevent FK violation
        try {
            $stmtSoft = $pdo->prepare("UPDATE services SET status = 'inactive' WHERE id = ?");
            $stmtSoft->execute([$serviceId]);

            logActivity($pdo, 'Service Marked Inactive', "Service '{$service['service_name']}' (ID: {$serviceId}) marked inactive because it has linked bookings.");
            setFlash("Service '{$service['service_name']}' has linked bookings, so it was set to Inactive instead of permanently deleted.", 'warning');
            redirect(BASE_URL . 'services/list.php');
        } catch (PDOException $e) {
            setFlash('Failed to update service status.', 'danger');
            redirect(BASE_URL . 'services/list.php');
        }
    } else {
        // Permanent delete
        try {
            $stmtDelete = $pdo->prepare('DELETE FROM services WHERE id = ?');
            $stmtDelete->execute([$serviceId]);

            logActivity($pdo, 'Service Deleted', "Service '{$service['service_name']}' (ID: {$serviceId}) was deleted.");
            setFlash("Service '{$service['service_name']}' deleted successfully.", 'success');
            redirect(BASE_URL . 'services/list.php');
        } catch (PDOException $e) {
            error_log('[GarageMS] Delete Service Error: ' . $e->getMessage());
            setFlash('Failed to delete service. System error occurred.', 'danger');
            redirect(BASE_URL . 'services/list.php');
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
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>services/list.php">Services</a></li>
                <li class="breadcrumb-item active" aria-current="page">Delete Service</li>
            </ol>
        </nav>
        <h1><i class="bi bi-trash-fill me-2 text-danger"></i>Delete Service Record</h1>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card gms-panel border-danger shadow-sm">
                    <div class="card-header bg-danger bg-opacity-10 border-danger py-3">
                        <h5 class="card-title text-danger mb-0 fw-bold">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>Confirm Service Deletion
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="fs-6">Are you sure you want to delete the service record for:</p>

                        <div class="p-3 bg-dark border border-secondary rounded mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="h5 fw-bold text-light mb-1"><?= e($service['service_name']) ?></h4>
                                <span class="fs-5 fw-bold text-warning font-monospace">₹<?= number_format($service['price'], 2) ?></span>
                            </div>
                            <div class="small text-muted mb-2">Service ID: #SVC-<?= $service['id'] ?></div>
                            <p class="small text-muted mb-0"><?= e($service['description'] ?: 'No description provided.') ?></p>
                        </div>

                        <?php if ($service['booking_count'] > 0): ?>
                            <div class="alert alert-warning mb-4">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                This service has <strong><?= $service['booking_count'] ?> existing booking(s)</strong>. Confirmed deletion will safely mark it as <strong>Inactive</strong> so historical booking records remain intact.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL ?>services/delete.php?id=<?= $serviceId ?>">
                            <?= csrfField() ?>
                            <div class="d-flex align-items-center justify-content-between pt-2">
                                <a href="<?= BASE_URL ?>services/list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Cancel, Keep Service
                                </a>
                                <button type="submit" class="btn btn-danger px-4">
                                    <i class="bi bi-trash-fill me-1"></i> Confirm Delete / Deactivate
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
