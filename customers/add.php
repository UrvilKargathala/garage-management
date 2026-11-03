<?php
/**
 * customers/add.php — Add New Customer Profile
 * Garage Management System
 *
 * Requirements:
 *  ✔ Admin access only (`requireAdmin()`)
 *  ✔ Staff members process registered customer bookings and cannot manually create customer records
 *  ✔ Server-side input validation (Name, Phone, Email, Address)
 *  ✔ Optional linking to portal user account (users table)
 *  ✔ PDO prepared statements
 */

$pageTitle = 'Add New Customer';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Enforce Admin only restriction (Staff cannot create customer accounts)
requireAdmin();

$pdo = getDBConnection();
$errors = [];
$name = '';
$phone = '';
$email = '';
$address = '';
$userId = null;

// Fetch portal users without linked customer profiles for the dropdown
try {
    $stmtUsers = $pdo->query(
        "SELECT id, name, email FROM users WHERE role = 'customer' AND id NOT IN (SELECT user_id FROM customers WHERE user_id IS NOT NULL) ORDER BY name ASC"
    );
    $availableUsers = $stmtUsers->fetchAll();
} catch (PDOException $e) {
    $availableUsers = [];
}

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // CSRF guard — must be first in POST handler
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $userId  = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;

    // Validate inputs
    if (($err = validateName($name)) !== '') {
        $errors['name'] = $err;
    }
    if (($err = validatePhone($phone)) !== '') {
        $errors['phone'] = $err;
    }
    if ($email !== '' && ($err = validateEmail($email)) !== '') {
        $errors['email'] = $err;
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO customers (name, phone, email, address, user_id) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $phone, $email !== '' ? $email : null, $address !== '' ? $address : null, $userId]);
            $newCustomerId = (int)$pdo->lastInsertId();

            logActivity($pdo, 'Customer Added', "Customer '{$name}' (ID: {$newCustomerId}) was created.");
            setFlash("Customer '{$name}' created successfully!", 'success');
            redirect(BASE_URL . 'customers/view.php?id=' . $newCustomerId);
        } catch (PDOException $e) {
            error_log('[GarageMS] Add Customer Error: ' . $e->getMessage());
            $errors['general'] = 'Failed to create customer record. Please check for duplicate phone/email.';
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
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customers/list.php">Customers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Customer</li>
            </ol>
        </nav>
        <h1><i class="bi bi-person-plus-fill me-2 text-warning"></i>Add New Customer</h1>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card gms-panel shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-person-vcard me-2 text-warning"></i>Customer Details
                        </h5>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span><?= e($errors['general']) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL ?>customers/add.php" novalidate>

                            <?= csrfField() ?>

                            <!-- Full Name -->
                            <div class="mb-3">
                                <label for="custName" class="form-label fw-semibold">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="custName"
                                    name="name"
                                    class="form-control gms-input<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                                    value="<?= e($name) ?>"
                                    placeholder="e.g. John Doe"
                                    required
                                >
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Phone Number & Email -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="custPhone" class="form-label fw-semibold">
                                        Phone Number <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="custPhone"
                                        name="phone"
                                        class="form-control gms-input<?= isset($errors['phone']) ? ' is-invalid' : '' ?>"
                                        value="<?= e($phone) ?>"
                                        placeholder="e.g. 9876543210"
                                        required
                                    >
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="custEmail" class="form-label fw-semibold">
                                        Email Address <span class="text-muted font-normal">(Optional)</span>
                                    </label>
                                    <input
                                        type="email"
                                        id="custEmail"
                                        name="email"
                                        class="form-control gms-input<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                                        value="<?= e($email) ?>"
                                        placeholder="e.g. john@example.com"
                                    >
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="mb-3">
                                <label for="custAddress" class="form-label fw-semibold">
                                    Address <span class="text-muted font-normal">(Optional)</span>
                                </label>
                                <textarea
                                    id="custAddress"
                                    name="address"
                                    rows="3"
                                    class="form-control gms-input"
                                    placeholder="Enter street address, city, etc."
                                ><?= e($address) ?></textarea>
                            </div>

                            <!-- Link to User Account -->
                            <?php if (!empty($availableUsers)): ?>
                                <div class="mb-4">
                                    <label for="custUserId" class="form-label fw-semibold">
                                        Link Portal Account <span class="text-muted font-normal">(Optional)</span>
                                    </label>
                                    <select id="custUserId" name="user_id" class="form-select gms-input">
                                        <option value="">-- No Linked Account (Walk-in Customer) --</option>
                                        <?php foreach ($availableUsers as $u): ?>
                                            <option value="<?= $u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>>
                                                <?= e($u['name']) ?> (<?= e($u['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted small">
                                        Associating a portal account allows the customer to view their vehicles and bookings when logged in.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-2">
                                <a href="<?= BASE_URL ?>customers/list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-gms-primary px-4">
                                    <i class="bi bi-check-circle me-1"></i> Save Customer
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
