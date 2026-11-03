<?php
/**
 * customers/delete.php — Delete Customer Profile
 * Garage Management System
 *
 * Requirements:
 *  ✔ Admin role required (`requireAdmin()`)
 *  ✔ Confirmation step before deletion
 *  ✔ Foreign key cascade cleanup
 *  ✔ PDO prepared statement
 */

$pageTitle = 'Delete Customer';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$pdo = getDBConnection();
$customerId = (int)($_GET['id'] ?? 0);

if ($customerId <= 0) {
    setFlash('Invalid customer ID.', 'danger');
    redirect(BASE_URL . 'customers/list.php');
}

// Fetch Customer & related vehicle/booking stats for warning
try {
    $stmt = $pdo->prepare(
        'SELECT c.*,
                (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id) AS vehicle_count,
                (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = c.id) AS booking_count
           FROM customers c
          WHERE c.id = ?
          LIMIT 1'
    );
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        setFlash('Customer record not found.', 'danger');
        redirect(BASE_URL . 'customers/list.php');
    }
} catch (PDOException $e) {
    error_log('[GarageMS] Delete Customer Fetch Error: ' . $e->getMessage());
    setFlash('Database error while retrieving customer details.', 'danger');
    redirect(BASE_URL . 'customers/list.php');
}

// ── POST Handler — Confirmed Deletion ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // CSRF guard — must be first in POST handler
    try {
        $stmtDelete = $pdo->prepare('DELETE FROM customers WHERE id = ?');
        $stmtDelete->execute([$customerId]);

        logActivity($pdo, 'Customer Deleted', "Customer '{$customer['name']}' (ID: {$customerId}) was deleted by Admin.");
        setFlash("Customer '{$customer['name']}' and associated records were deleted successfully.", 'success');
        redirect(BASE_URL . 'customers/list.php');
    } catch (PDOException $e) {
        error_log('[GarageMS] Delete Customer Error: ' . $e->getMessage());
        setFlash('Failed to delete customer. System error occurred.', 'danger');
        redirect(BASE_URL . 'customers/list.php');
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customers/list.php">Customers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Delete Customer</li>
            </ol>
        </nav>
        <h1><i class="bi bi-trash-fill me-2 text-danger"></i>Delete Customer Record</h1>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card gms-panel border-danger shadow-sm">
                    <div class="card-header bg-danger bg-opacity-10 border-danger py-3">
                        <h5 class="card-title text-danger mb-0 fw-bold">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>Confirm Irreversible Action
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="fs-6">Are you sure you want to permanently delete the customer profile for:</p>

                        <div class="p-3 bg-dark border border-secondary rounded mb-4">
                            <h4 class="h5 fw-bold text-light mb-1"><?= e($customer['name']) ?></h4>
                            <div class="small text-muted mb-2">Customer ID: #CUST-<?= $customer['id'] ?></div>
                            <div class="small text-light"><i class="bi bi-telephone text-warning me-2"></i><?= e($customer['phone']) ?></div>
                            <?php if ($customer['email']): ?>
                                <div class="small text-light"><i class="bi bi-envelope text-warning me-2"></i><?= e($customer['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($customer['vehicle_count'] > 0 || $customer['booking_count'] > 0): ?>
                            <div class="alert alert-danger mb-4">
                                <h6 class="fw-bold mb-1"><i class="bi bi-warning-fill me-1"></i>Cascading Deletion Warning:</h6>
                                <ul class="mb-0 ps-3 small">
                                    <li><strong><?= $customer['vehicle_count'] ?> registered vehicle(s)</strong> will be deleted.</li>
                                    <li><strong><?= $customer['booking_count'] ?> booking appointment(s)</strong> will be deleted.</li>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL ?>customers/delete.php?id=<?= $customerId ?>">
                            <?= csrfField() ?>
                            <div class="d-flex align-items-center justify-content-between pt-2">
                                <a href="<?= BASE_URL ?>customers/list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Cancel, Keep Customer
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
