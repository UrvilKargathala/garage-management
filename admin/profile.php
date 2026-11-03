<?php
/**
 * admin/profile.php — Admin Profile Page
 * Garage Management System
 *
 * Access Control: requireAdmin() — Admin only
 * Features: View and update admin profile info, change password
 */
$pageTitle = 'Admin Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$pdo    = getDBConnection();
$userId = currentUserId();
$errors = [];

// Fetch current admin data
try {
    $stmtAdmin = $pdo->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id=? AND role='admin' LIMIT 1");
    $stmtAdmin->execute([$userId]);
    $adminData = $stmtAdmin->fetch();

    // Stats for admin
    $totalLogins    = (int)$pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_id=?")->execute([$userId]) ? 0 : 0;
    $stmtLogins     = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE user_id=?");
    $stmtLogins->execute([$userId]);
    $totalLogins    = (int)$stmtLogins->fetchColumn();

    $stmtLastLogin  = $pdo->prepare("SELECT login_time FROM login_logs WHERE user_id=? ORDER BY login_time DESC LIMIT 1");
    $stmtLastLogin->execute([$userId]);
    $lastLogin      = $stmtLastLogin->fetchColumn();
} catch (PDOException $e) {
    error_log('[GarageMS] Admin profile: ' . $e->getMessage());
    $adminData = null;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $err = validateName($name);   if ($err) $errors[] = $err;
        $err = validatePhone($phone); if ($err) $errors[] = $err;

        if (empty($errors)) {
            try {
                $pdo->prepare("UPDATE users SET name=?, phone=? WHERE id=?")->execute([$name, $phone, $userId]);
                $_SESSION['user_name'] = $name;
                logActivity($pdo, 'UPDATE_PROFILE', 'Admin updated their profile.', 'user', $userId);
                setFlash('Profile updated successfully.', 'success');
                header('Location: ' . BASE_URL . 'admin/profile.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'change_password') {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd     = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';

        // Verify current password
        $stmtPwd = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $stmtPwd->execute([$userId]);
        $storedHash = $stmtPwd->fetchColumn();

        if (!password_verify($currentPwd, $storedHash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($newPwd !== $confirmPwd) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $err = validatePassword($newPwd);
            if ($err) $errors[] = $err;
        }

        if (empty($errors)) {
            try {
                $newHash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newHash, $userId]);
                logActivity($pdo, 'CHANGE_PASSWORD', 'Admin changed their password.', 'user', $userId);
                setFlash('Password changed successfully.', 'success');
                header('Location: ' . BASE_URL . 'admin/profile.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Database error: Could not update password.';
            }
        }
    }
}

if (!$adminData) {
    setFlash('Could not load admin profile.', 'danger');
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/includes/admin_nav.php';
?>
<div class="admin-content">
    <?php showFlash(); ?>

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><i class="bi bi-person-circle"></i>Admin Profile</h1>
            <div class="admin-breadcrumb"><a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a> › Profile</div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
            <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile Card -->
        <div class="col-md-4">
            <div class="admin-card text-center">
                <div class="admin-card-body py-4">
                    <div class="admin-user-avatar mx-auto mb-3" style="width:72px;height:72px;font-size:1.8rem;">
                        <?= strtoupper(mb_substr($adminData['name'],0,1)) ?>
                    </div>
                    <div class="fw-bold text-white fs-5"><?= e($adminData['name']) ?></div>
                    <div class="text-muted small mb-2"><?= e($adminData['email']) ?></div>
                    <span class="admin-badge admin-badge-admin">
                        <i class="bi bi-shield-lock-fill me-1"></i>Administrator
                    </span>
                    <hr class="border-secondary my-3">
                    <div class="admin-detail-row"><span class="admin-detail-label">Phone</span><span class="admin-detail-value"><?= e($adminData['phone'] ?? '—') ?></span></div>
                    <div class="admin-detail-row"><span class="admin-detail-label">Joined</span><span class="admin-detail-value"><?= formatDate($adminData['created_at']) ?></span></div>
                    <div class="admin-detail-row"><span class="admin-detail-label">Total Logins</span><span class="admin-detail-value"><?= $totalLogins ?></span></div>
                    <div class="admin-detail-row"><span class="admin-detail-label">Last Login</span><span class="admin-detail-value"><?= $lastLogin ? formatDateTime($lastLogin) : '—' ?></span></div>
                </div>
            </div>
        </div>

        <!-- Edit Forms -->
        <div class="col-md-8">
            <!-- Update Profile -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h5 class="admin-card-title"><i class="bi bi-pencil-square"></i>Update Profile</h5>
                </div>
                <div class="admin-card-body">
                    <form method="POST" action="<?= BASE_URL ?>admin/profile.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Full Name</label>
                                <input type="text" name="name" class="form-control gms-input"
                                       value="<?= e($adminData['name']) ?>" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Phone Number</label>
                                <input type="text" name="phone" class="form-control gms-input"
                                       value="<?= e($adminData['phone'] ?? '') ?>" required maxlength="20">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Email Address</label>
                                <input type="email" class="form-control gms-input" value="<?= e($adminData['email']) ?>" disabled>
                                <small class="text-muted">Email cannot be changed here.</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-gms-primary">
                                <i class="bi bi-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h5 class="admin-card-title"><i class="bi bi-lock-fill"></i>Change Password</h5>
                </div>
                <div class="admin-card-body">
                    <form method="POST" action="<?= BASE_URL ?>admin/profile.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Current Password</label>
                            <input type="password" name="current_password" class="form-control gms-input" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">New Password</label>
                            <input type="password" name="new_password" class="form-control gms-input" required minlength="8">
                            <small class="text-muted">At least 8 characters with uppercase, lowercase, and digit.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control gms-input" required>
                        </div>
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="bi bi-lock-fill me-1"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
require_once __DIR__ . '/../includes/footer.php';
?>
