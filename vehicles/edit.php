<?php
/**
 * vehicles/edit.php — Edit Vehicle Details
 * Garage Management System
 *
 * RBAC & Anti-IDOR:
 *  ✔ Staff and Admin can edit any vehicle record.
 *  ✔ Customers can ONLY edit their own vehicle records.
 *  ✔ Direct object reference attempt (changing id parameter) is verified via verifyVehicleOwnership().
 *  ✔ customer_id is NEVER accepted from POST; it is always read from the existing DB record.
 *  ✔ CSRF token validated on every POST submission.
 *  ✔ Duplicate vehicle registration number checked before UPDATE.
 */

$pageTitle = 'Edit Vehicle';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

// Staff role must NOT edit customer vehicles. Only the vehicle Owner or Admin can edit.
if (hasRole('staff')) {
    setFlash('Access Denied: Staff members cannot edit or modify customer vehicle specifications.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

$pdo = getDBConnection();
$vehicleId = (int)($_GET['id'] ?? 0);

// Guard: require a valid positive vehicle ID
if ($vehicleId <= 0) {
    setFlash('Invalid vehicle ID.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

// Anti-IDOR Verification Guard: Enforces ownership or admin access
verifyVehicleOwnership($pdo, $vehicleId);

// Fetch the existing vehicle record
try {
    $stmt = $pdo->prepare(
        'SELECT v.*, c.name AS customer_name
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
    error_log('[GarageMS] Edit Vehicle Fetch Error: ' . $e->getMessage());
    setFlash('Database error loading vehicle details.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

$errors = [];

// Initialise form values from the existing record (used on GET and on failed POST)
$vehicleNumber = $vehicle['vehicle_number'];
$vehicleType   = $vehicle['vehicle_type'];
$brand         = $vehicle['brand'];
$model         = $vehicle['model'];
$year          = (string)$vehicle['year'];
$fuelType      = $vehicle['fuel_type'];

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF verification (must be first)
    verifyCsrfToken();

    // Read submitted values
    $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
    $vehicleType   = trim($_POST['vehicle_type'] ?? '');
    $brand         = trim($_POST['brand'] ?? '');
    $model         = trim($_POST['model'] ?? '');
    $year          = trim($_POST['year'] ?? '');
    $fuelType      = trim($_POST['fuel_type'] ?? '');

    // Security: customer_id is NEVER trusted from POST — always use the DB value
    $targetCustomerId = (int)$vehicle['customer_id'];

    // Validate all fields using existing helpers
    if (($err = validateVehicleNumber($vehicleNumber)) !== '') {
        $errors['vehicle_number'] = $err;
    }
    if (($err = validateVehicleType($vehicleType)) !== '') {
        $errors['vehicle_type'] = $err;
    }
    if (($err = validateBrand($brand)) !== '') {
        $errors['brand'] = $err;
    }
    if (($err = validateModel($model)) !== '') {
        $errors['model'] = $err;
    }
    if (($err = validateYear($year)) !== '') {
        $errors['year'] = $err;
    }
    if (($err = validateFuelType($fuelType)) !== '') {
        $errors['fuel_type'] = $err;
    }

    // Duplicate registration number check (excluding this vehicle's own ID)
    if (!isset($errors['vehicle_number'])) {
        if (($err = validateVehicleNumberUnique($pdo, $vehicleNumber, $vehicleId)) !== '') {
            $errors['vehicle_number'] = $err;
        }
    }

    // Update record if all validations pass
    if (empty($errors)) {
        try {
            $stmtUpdate = $pdo->prepare(
                'UPDATE vehicles
                    SET vehicle_number = ?,
                        vehicle_type   = ?,
                        brand          = ?,
                        model          = ?,
                        year           = ?,
                        fuel_type      = ?
                  WHERE id = ?'
            );
            $stmtUpdate->execute([
                strtoupper(trim($vehicleNumber)), // normalise: uppercase + trim
                strtolower($vehicleType),
                $brand,
                $model,
                (int)$year,
                strtolower($fuelType),
                $vehicleId                        // always use the server-side ID
            ]);

            logActivity($pdo, 'Vehicle Updated', "Vehicle '{$vehicleNumber}' (ID: {$vehicleId}) details updated.");
            setFlash('Vehicle ' . strtoupper(trim($vehicleNumber)) . ' updated successfully!', 'success');
            redirect(BASE_URL . 'vehicles/view.php?id=' . $vehicleId);

        } catch (PDOException $e) {
            error_log('[GarageMS] Edit Vehicle Update Error: ' . $e->getMessage());
            $errors['general'] = 'Failed to update vehicle. A system error occurred.';
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
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>vehicles/list.php">Vehicles</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $vehicleId ?>"><?= e($vehicle['vehicle_number']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Vehicle</h1>
            <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $vehicleId ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Vehicle
            </a>
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
                            <i class="bi bi-car-front me-2 text-warning"></i>Update Vehicle Specifications
                        </h5>
                        <span class="badge bg-dark border border-secondary text-muted font-monospace">
                            #VEH-<?= $vehicleId ?>
                        </span>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span><?= e($errors['general']) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Owner info (read-only) -->
                        <div class="p-3 bg-dark border border-secondary rounded mb-4">
                            <div class="small text-muted mb-1">
                                <i class="bi bi-person-circle text-warning me-1"></i> Registered Owner
                            </div>
                            <strong class="text-light"><?= e($vehicle['customer_name']) ?></strong>
                            <span class="text-muted small ms-2">(Customer ID: #CUST-<?= (int)$vehicle['customer_id'] ?>)</span>
                        </div>

                        <form method="POST" action="<?= BASE_URL ?>vehicles/edit.php?id=<?= $vehicleId ?>" novalidate>

                            <?= csrfField() ?>

                            <!-- Registration Number -->
                            <div class="mb-3">
                                <label for="vehNumber" class="form-label fw-semibold">
                                    Vehicle Registration Number <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="vehNumber"
                                    name="vehicle_number"
                                    class="form-control gms-input font-monospace uppercase<?= isset($errors['vehicle_number']) ? ' is-invalid' : '' ?>"
                                    value="<?= e($vehicleNumber) ?>"
                                    placeholder="e.g. MH-12-AB-1234"
                                    required
                                >
                                <?php if (isset($errors['vehicle_number'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['vehicle_number']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Vehicle Type & Fuel Type -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="vehType" class="form-label fw-semibold">
                                        Vehicle Type <span class="text-danger">*</span>
                                    </label>
                                    <select id="vehType" name="vehicle_type" class="form-select gms-input<?= isset($errors['vehicle_type']) ? ' is-invalid' : '' ?>" required>
                                        <option value="car"        <?= $vehicleType === 'car'        ? 'selected' : '' ?>>Car / Sedan / SUV</option>
                                        <option value="motorcycle" <?= $vehicleType === 'motorcycle' ? 'selected' : '' ?>>Motorcycle / Scooter</option>
                                        <option value="van"        <?= $vehicleType === 'van'        ? 'selected' : '' ?>>Van / Minivan</option>
                                        <option value="truck"      <?= $vehicleType === 'truck'      ? 'selected' : '' ?>>Truck / Pickup</option>
                                        <option value="bus"        <?= $vehicleType === 'bus'        ? 'selected' : '' ?>>Bus</option>
                                        <option value="other"      <?= $vehicleType === 'other'      ? 'selected' : '' ?>>Other</option>
                                    </select>
                                    <?php if (isset($errors['vehicle_type'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['vehicle_type']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="vehFuel" class="form-label fw-semibold">
                                        Fuel Type <span class="text-danger">*</span>
                                    </label>
                                    <select id="vehFuel" name="fuel_type" class="form-select gms-input<?= isset($errors['fuel_type']) ? ' is-invalid' : '' ?>" required>
                                        <option value="petrol"   <?= $fuelType === 'petrol'   ? 'selected' : '' ?>>Petrol</option>
                                        <option value="diesel"   <?= $fuelType === 'diesel'   ? 'selected' : '' ?>>Diesel</option>
                                        <option value="electric" <?= $fuelType === 'electric' ? 'selected' : '' ?>>Electric (EV)</option>
                                        <option value="hybrid"   <?= $fuelType === 'hybrid'   ? 'selected' : '' ?>>Hybrid</option>
                                        <option value="cng"      <?= $fuelType === 'cng'      ? 'selected' : '' ?>>CNG</option>
                                        <option value="other"    <?= $fuelType === 'other'    ? 'selected' : '' ?>>Other</option>
                                    </select>
                                    <?php if (isset($errors['fuel_type'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['fuel_type']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Brand, Model & Year -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-5">
                                    <label for="vehBrand" class="form-label fw-semibold">
                                        Brand / Make <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="vehBrand"
                                        name="brand"
                                        class="form-control gms-input<?= isset($errors['brand']) ? ' is-invalid' : '' ?>"
                                        value="<?= e($brand) ?>"
                                        placeholder="e.g. Toyota"
                                        required
                                    >
                                    <?php if (isset($errors['brand'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['brand']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="vehModel" class="form-label fw-semibold">
                                        Model <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="vehModel"
                                        name="model"
                                        class="form-control gms-input<?= isset($errors['model']) ? ' is-invalid' : '' ?>"
                                        value="<?= e($model) ?>"
                                        placeholder="e.g. Corolla"
                                        required
                                    >
                                    <?php if (isset($errors['model'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['model']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <label for="vehYear" class="form-label fw-semibold">
                                        Year <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        id="vehYear"
                                        name="year"
                                        class="form-control gms-input<?= isset($errors['year']) ? ' is-invalid' : '' ?>"
                                        value="<?= e($year) ?>"
                                        min="1900"
                                        max="<?= date('Y') + 1 ?>"
                                        required
                                    >
                                    <?php if (isset($errors['year'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['year']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary">
                                <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= $vehicleId ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-gms-primary px-4">
                                    <i class="bi bi-check-circle me-1"></i> Save Changes
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
