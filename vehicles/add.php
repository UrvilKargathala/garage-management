<?php
/**
 * vehicles/add.php — Register New Vehicle
 * Garage Management System
 *
 * Requirements & Security:
 *  ✔ Validates all vehicle fields (Vehicle Number, Type, Brand, Model, Year, Fuel Type)
 *  ✔ Checks that selected customer_id exists in the database
 *  ✔ Customer role is automatically locked to their own customer ID
 *  ✔ Staff/Admin can select any valid customer or pass customer_id in URL
 *  ✔ PDO prepared statements
 */

$pageTitle = 'Register Vehicle';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

// Staff role must NOT add vehicles. Only Customers (for their own fleet) or Admin can add vehicles.
if (hasRole('staff')) {
    setFlash('Access Denied: Staff members cannot create or register customer vehicles.', 'danger');
    redirect(BASE_URL . 'vehicles/list.php');
}

$pdo = getDBConnection();
$errors = [];

// Determine default or selected customer ID
$selectedCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;

// If Customer role, restrict strictly to their own linked customer record
if (hasRole('customer')) {
    $loggedInCustId = getLoggedInCustomerId($pdo);
    if (!$loggedInCustId) {
        setFlash('Your customer profile could not be found. Please contact staff.', 'danger');
        redirect(BASE_URL . 'dashboard.php');
    }
    $selectedCustomerId = $loggedInCustId;
}

// Fetch all customers for Admin dropdown (Staff is already barred)
$customersList = [];
if (isAdmin()) {
    try {
        $stmtCust = $pdo->query('SELECT id, name, phone, email FROM customers ORDER BY name ASC');
        $customersList = $stmtCust->fetchAll();
    } catch (PDOException $e) {
        $customersList = [];
    }
}

// Initialize form values
$vehicleNumber = '';
$vehicleType   = 'car';
$brand         = '';
$model         = '';
$year          = (string)date('Y');
$fuelType      = 'petrol';
$color         = '';

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // CSRF guard — must be first in POST handler
    $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
    $vehicleType   = trim($_POST['vehicle_type'] ?? 'car');
    $brand         = trim($_POST['brand'] ?? '');
    $model         = trim($_POST['model'] ?? '');
    $year          = trim($_POST['year'] ?? '');
    $fuelType      = trim($_POST['fuel_type'] ?? 'petrol');
    $color         = trim($_POST['color'] ?? '');
    
    // Customer ID processing
    if (hasRole('customer')) {
        $targetCustomerId = $selectedCustomerId;
    } else {
        $targetCustomerId = (int)($_POST['customer_id'] ?? 0);
    }

    // Validate Customer ID exists in DB
    if ($targetCustomerId <= 0) {
        $errors['customer_id'] = 'Please select a valid customer.';
    } else {
        try {
            $stmtCheckCust = $pdo->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
            $stmtCheckCust->execute([$targetCustomerId]);
            if (!$stmtCheckCust->fetch()) {
                $errors['customer_id'] = 'Selected customer does not exist in the database.';
            }
        } catch (PDOException $e) {
            $errors['customer_id'] = 'Database error checking customer.';
        }
    }

    // Validate Vehicle Fields
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

    // FIX 3: Duplicate registration number check (excludeId=0 for new vehicle)
    if (!isset($errors['vehicle_number'])) {
        if (($err = validateVehicleNumberUnique($pdo, $vehicleNumber, 0)) !== '') {
            $errors['vehicle_number'] = $err;
        }
    }

    // Insert record if valid
    if (empty($errors)) {
        try {
            $stmtInsert = $pdo->prepare(
                'INSERT INTO vehicles (customer_id, vehicle_number, vehicle_type, brand, model, year, fuel_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtInsert->execute([
                $targetCustomerId,
                strtoupper($vehicleNumber),
                strtolower($vehicleType),
                $brand,
                $model,
                (int)$year,
                strtolower($fuelType)
            ]);
            $newVehicleId = (int)$pdo->lastInsertId();

            logActivity($pdo, 'Vehicle Registered', "Vehicle '{$vehicleNumber}' was added to Customer ID {$targetCustomerId}.");
            setFlash("Vehicle " . strtoupper($vehicleNumber) . " registered successfully!", 'success');
            redirect(BASE_URL . 'vehicles/list.php?added=1&new_id=' . $newVehicleId . '#vehicle-' . $newVehicleId);
        } catch (PDOException $e) {
            error_log('[GarageMS] Add Vehicle Error: ' . $e->getMessage());
            $errors['general'] = 'Failed to register vehicle. Duplicate registration number or system error.';
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
                <li class="breadcrumb-item active" aria-current="page">Register Vehicle</li>
            </ol>
        </nav>
        <h1><i class="bi bi-car-front-fill me-2 text-warning"></i>Register New Vehicle</h1>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card gms-panel shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-card-heading me-2 text-warning"></i>Vehicle Specifications
                        </h5>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span><?= e($errors['general']) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL ?>vehicles/add.php<?= $selectedCustomerId ? '?customer_id=' . $selectedCustomerId : '' ?>" novalidate>

                            <?= csrfField() ?>

                            <!-- Customer Selection (Staff/Admin) -->
                            <?php if (hasRole(['admin', 'staff'])): ?>
                                <div class="mb-3">
                                    <label for="vehCustomerId" class="form-label fw-semibold">
                                        Owner Customer <span class="text-danger">*</span>
                                    </label>
                                    <select id="vehCustomerId" name="customer_id" class="form-select gms-input<?= isset($errors['customer_id']) ? ' is-invalid' : '' ?>" required>
                                        <option value="">-- Select Customer --</option>
                                        <?php foreach ($customersList as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $selectedCustomerId === (int)$c['id'] ? 'selected' : '' ?>>
                                                <?= e($c['name']) ?> (<?= e($c['phone']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['customer_id'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['customer_id']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

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
                                        <option value="car" <?= $vehicleType === 'car' ? 'selected' : '' ?>>Car / Sedan / SUV</option>
                                        <option value="motorcycle" <?= $vehicleType === 'motorcycle' ? 'selected' : '' ?>>Motorcycle / Scooter</option>
                                        <option value="van" <?= $vehicleType === 'van' ? 'selected' : '' ?>>Van / Minivan</option>
                                        <option value="truck" <?= $vehicleType === 'truck' ? 'selected' : '' ?>>Truck / Pickup</option>
                                        <option value="bus" <?= $vehicleType === 'bus' ? 'selected' : '' ?>>Bus</option>
                                        <option value="other" <?= $vehicleType === 'other' ? 'selected' : '' ?>>Other</option>
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
                                        <option value="petrol" <?= $fuelType === 'petrol' ? 'selected' : '' ?>>Petrol</option>
                                        <option value="diesel" <?= $fuelType === 'diesel' ? 'selected' : '' ?>>Diesel</option>
                                        <option value="electric" <?= $fuelType === 'electric' ? 'selected' : '' ?>>Electric (EV)</option>
                                        <option value="hybrid" <?= $fuelType === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                        <option value="cng" <?= $fuelType === 'cng' ? 'selected' : '' ?>>CNG</option>
                                        <option value="other" <?= $fuelType === 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                    <?php if (isset($errors['fuel_type'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['fuel_type']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Brand, Model & Year -->
                            <div class="row g-3 mb-3">
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

                            <!-- Color (Optional) -->
                            <div class="mb-4">
                                <label for="vehColor" class="form-label fw-semibold">
                                    Vehicle Color
                                </label>
                                <input
                                    type="text"
                                    id="vehColor"
                                    name="color"
                                    class="form-control gms-input"
                                    value="<?= e($color) ?>"
                                    placeholder="e.g. Pearl White, Silver Metallic, Black"
                                >
                            </div>

                            <!-- Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary">
                                <a href="<?= BASE_URL ?>vehicles/list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-warning fw-bold text-dark px-4">
                                    <i class="bi bi-check-circle-fill me-1"></i> Register Vehicle
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
