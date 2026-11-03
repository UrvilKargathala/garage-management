<?php
/**
 * customers/edit.php — Edit Customer Profile
 * Garage Management System
 *
 * Requirements & IDOR Security:
 *  ✔ Staff and Admin can edit any customer profile
 *  ✔ Customer role can ONLY edit their own customer profile
 *  ✔ IDOR protection verified via verifyCustomerOwnership($pdo, $customerId)
 */

$pageTitle = 'Edit Customer Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

// Staff role must NOT edit customer profiles. Only Admin or the Customer themselves can edit.
if (hasRole('staff')) {
    setFlash('Access Denied: Staff members cannot modify customer account profiles.', 'danger');
    redirect(BASE_URL . 'customers/list.php');
}

$pdo = getDBConnection();
$customerId = (int)($_GET['id'] ?? 0);

if ($customerId <= 0) {
    setFlash('Invalid customer ID.', 'danger');
    redirect(BASE_URL . (hasRole('admin') ? 'customers/list.php' : 'customer/dashboard.php'));
}

// Anti-IDOR Check: Enforces ownership or admin access
verifyCustomerOwnership($pdo, $customerId);

// Fetch existing record
try {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        setFlash('Customer record not found.', 'danger');
        redirect(BASE_URL . (hasRole(['admin', 'staff']) ? 'customers/list.php' : 'dashboard.php'));
    }
} catch (PDOException $e) {
    error_log('[GarageMS] Edit Customer Fetch Error: ' . $e->getMessage());
    setFlash('Error fetching customer record.', 'danger');
    redirect(BASE_URL . 'dashboard.php');
}

$errors  = [];
$name    = $customer['name'];
$phone   = $customer['phone'];
$email   = $customer['email'] ?? '';
$address = $customer['address'] ?? '';

// ── POST Handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(); // CSRF guard — must be first in POST handler
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

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
            $stmtUpdate = $pdo->prepare(
                'UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?'
            );
            $stmtUpdate->execute([$name, $phone, $email !== '' ? $email : null, $address !== '' ? $address : null, $customerId]);

            logActivity($pdo, 'Customer Updated', "Customer '{$name}' (ID: {$customerId}) profile was updated.");
            setFlash("Customer profile updated successfully!", 'success');
            redirect(BASE_URL . 'customers/view.php?id=' . $customerId);
        } catch (PDOException $e) {
            error_log('[GarageMS] Edit Customer Update Error: ' . $e->getMessage());
            $errors['general'] = 'Failed to update customer record. Duplicate phone or email.';
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
                <?php if (hasRole(['admin', 'staff'])): ?>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customers/list.php">Customers</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customers/view.php?id=<?= $customerId ?>"><?= e($customer['name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
        <h1><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Customer Profile</h1>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card gms-panel shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 fw-bold text-light">
                            <i class="bi bi-person-gear me-2 text-warning"></i>Update Profile Details
                        </h5>
                        <span class="badge bg-dark border border-secondary text-muted font-monospace">
                            #CUST-<?= $customerId ?>
                        </span>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span><?= e($errors['general']) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL ?>customers/edit.php?id=<?= $customerId ?>" novalidate>

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
                                    >
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="mb-4">
                                <label for="custAddress" class="form-label fw-semibold">
                                    Address <span class="text-muted font-normal">(Optional)</span>
                                </label>
                                <textarea
                                    id="custAddress"
                                    name="address"
                                    rows="3"
                                    class="form-control gms-input"
                                ><?= e($address) ?></textarea>
                            </div>

                            <!-- Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary">
                                <a href="<?= BASE_URL ?>customers/view.php?id=<?= $customerId ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Cancel
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
