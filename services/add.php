<?php
/**
 * services/add.php — Add Garage Service
 * Garage Management System
 *
 * Requirements & RBAC:
 *  ✔ Staff or Admin only (`requireStaffOrAdmin()`)
 *  ✔ Validates Service Name, Description, Price, Estimated Time, Status
 *  ✔ Uses PDO prepared statements
 */

$pageTitle = 'Add New Service';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireStaffOrAdmin();

$pdo = getDBConnection();
$errors = [];

$serviceName   = '';
$description   = '';
$price         = '';
$estimatedTime = '';
$status        = 'active';

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // CSRF guard — must be first in POST handler
    $serviceName   = trim($_POST['service_name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $price         = trim($_POST['price'] ?? '');
    $estimatedTime = trim($_POST['estimated_time'] ?? '');
    $status        = trim($_POST['status'] ?? 'active');

    // Validation
    if (($err = validateServiceName($serviceName)) !== '') {
        $errors['service_name'] = $err;
    }
    if (($err = validateServicePrice($price)) !== '') {
        $errors['price'] = $err;
    }
    if (($err = validateEstimatedTime($estimatedTime)) !== '') {
        $errors['estimated_time'] = $err;
    }
    if (($err = validateServiceStatus($status)) !== '') {
        $errors['status'] = $err;
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO services (service_name, description, price, estimated_time, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $serviceName,
                $description !== '' ? $description : null,
                (float)$price,
                $estimatedTime !== '' ? (float)$estimatedTime : null,
                strtolower($status)
            ]);
            $newSvcId = (int)$pdo->lastInsertId();

            logActivity($pdo, 'Service Added', "Service '{$serviceName}' (ID: {$newSvcId}) was created.");
            setFlash("Service '{$serviceName}' added successfully!", 'success');
            redirect(BASE_URL . 'services/list.php');
        } catch (PDOException $e) {
            error_log('[GarageMS] Add Service Error: ' . $e->getMessage());
            $errors['general'] = 'Failed to add service. System error occurred.';
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
                <li class="breadcrumb-item active" aria-current="page">Add Service</li>
            </ol>
        </nav>
        <h1><i class="bi bi-tools me-2 text-warning"></i>Add New Service</h1>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card gms-panel shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-plus-circle me-2 text-warning"></i>Service Specifications
                        </h5>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span><?= e($errors['general']) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL ?>services/add.php" novalidate>

                            <?= csrfField() ?>

                            <!-- Service Name -->
                            <div class="mb-3">
                                <label for="svcName" class="form-label fw-semibold">
                                    Service Name <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="svcName"
                                    name="service_name"
                                    class="form-control gms-input<?= isset($errors['service_name']) ? ' is-invalid' : '' ?>"
                                    value="<?= e($serviceName) ?>"
                                    placeholder="e.g. Full Engine Diagnostic & Tune-up"
                                    required
                                >
                                <?php if (isset($errors['service_name'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['service_name']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="svcDesc" class="form-label fw-semibold">
                                    Service Description <span class="text-muted font-normal">(Optional)</span>
                                </label>
                                <textarea
                                    id="svcDesc"
                                    name="description"
                                    rows="3"
                                    class="form-control gms-input"
                                    placeholder="Detailed overview of what is included in this service..."
                                ><?= e($description) ?></textarea>
                            </div>

                            <!-- Price & Estimated Time -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="svcPrice" class="form-label fw-semibold">
                                        Standard Price (₹) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-warning fw-bold">₹</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            id="svcPrice"
                                            name="price"
                                            class="form-control gms-input font-monospace<?= isset($errors['price']) ? ' is-invalid' : '' ?>"
                                            value="<?= e($price) ?>"
                                            placeholder="0.00"
                                            required
                                        >
                                    </div>
                                    <?php if (isset($errors['price'])): ?>
                                        <div class="invalid-feedback d-block"><?= e($errors['price']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label for="svcTime" class="form-label fw-semibold">
                                        Estimated Time (Hours) <span class="text-muted font-normal">(Optional)</span>
                                    </label>
                                    <input
                                        type="number"
                                        step="0.25"
                                        min="0"
                                        id="svcTime"
                                        name="estimated_time"
                                        class="form-control gms-input<?= isset($errors['estimated_time']) ? ' is-invalid' : '' ?>"
                                        value="<?= e($estimatedTime) ?>"
                                        placeholder="e.g. 1.5"
                                    >
                                    <?php if (isset($errors['estimated_time'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['estimated_time']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="mb-4">
                                <label for="svcStatus" class="form-label fw-semibold">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <select id="svcStatus" name="status" class="form-select gms-input<?= isset($errors['status']) ? ' is-invalid' : '' ?>" required>
                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (Visible to customers for booking)</option>
                                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden from customer booking)</option>
                                </select>
                                <?php if (isset($errors['status'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['status']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary">
                                <a href="<?= BASE_URL ?>services/list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-gms-primary px-4">
                                    <i class="bi bi-check-circle me-1"></i> Save Service
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
