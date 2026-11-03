<?php
/**
 * register.php — New User Registration
 * Garage Management System
 *
 * Flow:
 *  GET  → display the empty registration form
 *  POST → validate all fields → check for duplicate email
 *       → hash password → insert into DB → redirect to login
 *
 * Security checklist:
 *  ✔ All input validated server-side before touching the DB
 *  ✔ Email checked with filter_var(FILTER_VALIDATE_EMAIL)
 *  ✔ Duplicate email detected with a SELECT prepared statement
 *  ✔ Password hashed with password_hash(PASSWORD_BCRYPT, cost=12)
 *  ✔ Plain-text password NEVER stored or logged
 *  ✔ All DB queries use PDO prepared statements (? placeholders)
 *  ✔ SQL errors are caught and never shown to the user
 *  ✔ Admin role registration is blocked on this public form
 *  ✔ Form fields repopulated after failure (except passwords)
 */

// ── Bootstrap the application ─────────────────────────────────
require_once __DIR__ . '/includes/header.php';   // session_start, BASE_URL, functions
require_once __DIR__ . '/config/database.php';   // getDBConnection()

// If the user is already logged in, send them to the dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

// ── Initialise state ──────────────────────────────────────────
$errors  = [];   // field-name → error string
$success = false;

// ══════════════════════════════════════════════════════════════
//  POST HANDLER — runs only when the form is submitted
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF verification
    verifyCsrfToken();

    // ── 1. Read raw input ─────────────────────────────────────
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $role     = trim($_POST['role']     ?? '');
    $password = $_POST['password']      ?? '';   // NOT trimmed — spaces in passwords are valid
    $confirm  = $_POST['confirm_password'] ?? '';

    // ── 2. Server-side validation ─────────────────────────────
    // Use the helpers added in functions.php

    if (($err = validateName($name))     !== '') $errors['name']     = $err;
    if (($err = validateEmail($email))   !== '') $errors['email']    = $err;
    if (($err = validatePhone($phone))   !== '') $errors['phone']    = $err;
    if (($err = validatePassword($password)) !== '') $errors['password'] = $err;
    if (($err = validateRole($role))     !== '') $errors['role']     = $err;

    // Confirm password check (only if base password is itself valid)
    if (!isset($errors['password'])) {
        if ($confirm === '') {
            $errors['confirm_password'] = 'Please confirm your password.';
        } elseif ($confirm !== $password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
    }

    // ── 3. If all fields are valid, check for duplicate email ─
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();

            /*
             * SELECT query to detect a duplicate email.
             * Using COUNT(*) is efficient — MySQL can use the unique
             * index on 'email' and return immediately.
             */
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS cnt FROM users WHERE email = ?'
            );
            $stmt->execute([strtolower($email)]);
            $row = $stmt->fetch();

            if ((int)($row['cnt'] ?? 0) > 0) {
                $errors['email'] = 'This email address is already registered. '
                                 . 'Please <a href="' . BASE_URL . 'login.php" '
                                 . 'class="alert-link">login instead</a>.';
            }

        } catch (PDOException $e) {
            // Log internally; show a generic message to the user
            error_log('[GarageMS] Registration DB check error: ' . $e->getMessage());
            $errors['general'] = 'A system error occurred. Please try again later.';
        }
    }

    // ── 4. If still no errors, insert the new user ────────────
    if (empty($errors)) {
        try {
            /*
             * Hash the password using bcrypt (PASSWORD_BCRYPT).
             * Cost factor 12 is strong but completes in ~200 ms on
             * most servers — a good balance for a college project.
             *
             * The resulting hash is ~60 characters and is stored in
             * the users.password column (VARCHAR 255).
             * password_verify() is used during login to check it.
             */
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password, role, phone)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                strtolower($email),   // store email in lowercase for consistency
                $hashedPassword,       // bcrypt hash — NEVER the plain password
                $role,
                $phone,
            ]);

            $newUserId = (int) $pdo->lastInsertId();

            // Log the event to activity_logs
            // We manually set a temporary session user_id so logActivity can read it
            $_SESSION['user_id'] = $newUserId;
            logActivity($pdo, 'User registered', "New {$role} account: {$email}");
            unset($_SESSION['user_id']); // clear — login will set this properly

            // ── 5. Success: redirect to login with a flash message ─
            setFlash(
                '🎉 Registration successful! Welcome to GarageMS. '
                . 'Please log in to continue.',
                'success'
            );
            redirect(BASE_URL . 'login.php');

        } catch (PDOException $e) {
            error_log('[GarageMS] Registration insert error: ' . $e->getMessage());
            $errors['general'] = 'Registration failed due to a system error. Please try again.';
        }
    }

    // ── 6. On failure: save safe fields for repopulation ──────
    if (!empty($errors)) {
        storeOldInput(['name', 'email', 'phone', 'role']);
        // Passwords are NEVER stored in old_input
    }
}

// ── Page title ────────────────────────────────────────────────
$pageTitle = 'Create Account';

// Navbar and page rendered below
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- ── Top spacing to clear fixed navbar ──────────────────────── -->
<div style="padding-top: 72px;"></div>

<main class="register-page" style="background: var(--clr-dark); min-height: calc(100vh - 72px);">
<div class="container" style="padding: 3rem 1rem;">
<div class="row justify-content-center">
<div class="col-lg-7 col-xl-6">

    <!-- ── Page header ──────────────────────────────────────── -->
    <div class="text-center mb-4">
        <div style="width:56px;height:56px;background:linear-gradient(135deg,#f97316,#fb923c);
                    border-radius:14px;display:flex;align-items:center;justify-content:center;
                    font-size:1.6rem;color:#fff;margin:0 auto 1rem;
                    box-shadow:0 4px 20px rgba(249,115,22,0.35);">
            <i class="bi bi-person-plus-fill"></i>
        </div>
        <h1 style="font-family:var(--font-display);font-size:1.75rem;font-weight:800;
                   color:var(--clr-text);margin-bottom:0.4rem;">
            Create Your Account
        </h1>
        <p style="color:var(--clr-text-muted);font-size:0.9rem;">
            Already have an account?
            <a href="<?= BASE_URL ?>login.php"
               style="color:var(--clr-accent);font-weight:600;text-decoration:none;">
                Login here
            </a>
        </p>
    </div>

    <!-- ── General error banner ─────────────────────────────── -->
    <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <div><?= e($errors['general']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- ── Validation summary (when multiple errors) ─────────── -->
    <?php
    $fieldErrors = array_diff_key($errors, ['general' => '']);
    if (count($fieldErrors) > 1):
    ?>
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-circle-fill me-2"></i>
        <strong>Please fix <?= count($fieldErrors) ?> error(s) below before submitting.</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         REGISTRATION FORM
         ══════════════════════════════════════════════════════ -->
    <div style="background:var(--clr-dark-3);border:1px solid var(--clr-border);
                border-radius:1.25rem;padding:2.5rem;">

        <form
            id="registerForm"
            method="POST"
            action="<?= BASE_URL ?>register.php"
            novalidate
        >
            <?= csrfField() ?>

            <!-- ── Row 1: Full Name ────────────────────────── -->
            <div class="mb-4">
                <label for="reg_name" class="form-label fw-600"
                       style="color:var(--clr-text);font-weight:600;font-size:0.875rem;">
                    <i class="bi bi-person-fill me-1" style="color:var(--clr-accent);"></i>
                    Full Name <span style="color:#ef4444;">*</span>
                </label>
                <input
                    type="text"
                    id="reg_name"
                    name="name"
                    class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                    placeholder="e.g. Rahul Sharma"
                    value="<?= old('name') ?>"
                    maxlength="100"
                    required
                    autocomplete="name"
                />
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback d-flex align-items-center gap-1">
                        <i class="bi bi-exclamation-circle-fill" style="font-size:0.75rem;"></i>
                        <?= e($errors['name']) ?>
                    </div>
                <?php else: ?>
                    <div class="form-text" style="color:var(--clr-text-subtle);font-size:0.78rem;">
                        Enter your full legal name (2–100 characters).
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Row 2: Email ───────────────────────────── -->
            <div class="mb-4">
                <label for="reg_email" class="form-label"
                       style="color:var(--clr-text);font-weight:600;font-size:0.875rem;">
                    <i class="bi bi-envelope-fill me-1" style="color:var(--clr-accent);"></i>
                    Email Address <span style="color:#ef4444;">*</span>
                </label>
                <input
                    type="email"
                    id="reg_email"
                    name="email"
                    class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                    placeholder="you@example.com"
                    value="<?= old('email') ?>"
                    maxlength="150"
                    required
                    autocomplete="email"
                />
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback d-flex align-items-center gap-1">
                        <i class="bi bi-exclamation-circle-fill" style="font-size:0.75rem;"></i>
                        <?= $errors['email'] /* already escaped or contains a safe link */ ?>
                    </div>
                <?php else: ?>
                    <div class="form-text" style="color:var(--clr-text-subtle);font-size:0.78rem;">
                        Used as your login. Must be a real email address.
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Row 3: Phone ───────────────────────────── -->
            <div class="mb-4">
                <label for="reg_phone" class="form-label"
                       style="color:var(--clr-text);font-weight:600;font-size:0.875rem;">
                    <i class="bi bi-telephone-fill me-1" style="color:var(--clr-accent);"></i>
                    Phone Number <span style="color:#ef4444;">*</span>
                </label>
                <input
                    type="tel"
                    id="reg_phone"
                    name="phone"
                    class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                    placeholder="+91 98765 43210"
                    value="<?= old('phone') ?>"
                    maxlength="20"
                    required
                    autocomplete="tel"
                />
                <?php if (isset($errors['phone'])): ?>
                    <div class="invalid-feedback d-flex align-items-center gap-1">
                        <i class="bi bi-exclamation-circle-fill" style="font-size:0.75rem;"></i>
                        <?= e($errors['phone']) ?>
                    </div>
                <?php else: ?>
                    <div class="form-text" style="color:var(--clr-text-subtle);font-size:0.78rem;">
                        Digits only. Include country code if needed (e.g. +91).
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Row 4: Account Type ────────────────────── -->
            <div class="mb-4">
                <label for="reg_role" class="form-label"
                       style="color:var(--clr-text);font-weight:600;font-size:0.875rem;">
                    <i class="bi bi-shield-fill me-1" style="color:var(--clr-accent);"></i>
                    Account Type <span style="color:#ef4444;">*</span>
                </label>
                <select
                    id="reg_role"
                    name="role"
                    class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>"
                    required
                >
                    <option value="" disabled
                        <?= (old('role') === '') ? 'selected' : '' ?>>
                        — Select account type —
                    </option>
                    <option value="customer"
                        <?= (old('role') === 'customer') ? 'selected' : '' ?>>
                        🧑 Customer — Book services for my vehicle
                    </option>
                    <option value="staff"
                        <?= (old('role') === 'staff') ? 'selected' : '' ?>>
                        🔧 Staff — Work at the garage
                    </option>
                    <!--
                        Admin role is intentionally omitted.
                        Admin accounts are created only by a System Administrator
                        directly in the database. This prevents privilege escalation.
                    -->
                </select>
                <?php if (isset($errors['role'])): ?>
                    <div class="invalid-feedback d-flex align-items-center gap-1">
                        <i class="bi bi-exclamation-circle-fill" style="font-size:0.75rem;"></i>
                        <?= e($errors['role']) ?>
                    </div>
                <?php else: ?>
                    <div class="form-text" style="color:var(--clr-text-subtle);font-size:0.78rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        Admin accounts are created only by the system administrator.
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Divider ───────────────────────────────── -->
            <hr style="border-color:var(--clr-border);margin:1.5rem 0;" />
            <p style="color:var(--clr-text-muted);font-size:0.82rem;margin-bottom:1.25rem;">
                <i class="bi bi-lock-fill me-1" style="color:var(--clr-accent);"></i>
                <strong style="color:var(--clr-text);">Password Rules:</strong>
                Minimum 8 characters · At least one uppercase letter ·
                At least one lowercase letter · At least one number.
            </p>

            <!-- ── Row 5: Password ────────────────────────── -->
            <div class="mb-4">
                <label for="reg_password" class="form-label"
                       style="color:var(--clr-text);font-weight:600;font-size:0.875rem;">
                    <i class="bi bi-key-fill me-1" style="color:var(--clr-accent);"></i>
                    Password <span style="color:#ef4444;">*</span>
                </label>
                <div class="input-group">
                    <input
                        type="password"
                        id="reg_password"
                        name="password"
                        class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                        placeholder="Create a strong password"
                        minlength="8"
                        required
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="togglePassword"
                        title="Show / hide password"
                        style="border-color:#6b7280;color:#9ca3af;background:var(--clr-dark-4);"
                        aria-label="Toggle password visibility"
                    >
                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                    </button>
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback d-flex align-items-center gap-1">
                            <i class="bi bi-exclamation-circle-fill" style="font-size:0.75rem;"></i>
                            <?= e($errors['password']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Live strength bar -->
                <div id="strengthWrap" style="display:none;margin-top:0.5rem;">
                    <div style="display:flex;justify-content:space-between;
                                font-size:0.75rem;color:var(--clr-text-muted);margin-bottom:3px;">
                        <span>Password strength</span>
                        <span id="strengthLabel">—</span>
                    </div>
                    <div style="height:4px;background:var(--clr-dark-4);
                                border-radius:2px;overflow:hidden;">
                        <div id="strengthBar"
                             style="height:100%;width:0%;border-radius:2px;
                                    transition:width 0.3s,background 0.3s;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Row 6: Confirm Password ─────────────────── -->
            <div class="mb-4">
                <label for="reg_confirm" class="form-label"
                       style="color:var(--clr-text);font-weight:600;font-size:0.875rem;">
                    <i class="bi bi-key-fill me-1" style="color:var(--clr-accent);"></i>
                    Confirm Password <span style="color:#ef4444;">*</span>
                </label>
                <div class="input-group">
                    <input
                        type="password"
                        id="reg_confirm"
                        name="confirm_password"
                        class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                        placeholder="Re-enter your password"
                        required
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="toggleConfirm"
                        title="Show / hide password"
                        style="border-color:#6b7280;color:#9ca3af;background:var(--clr-dark-4);"
                        aria-label="Toggle confirm password visibility"
                    >
                        <i class="bi bi-eye" id="toggleConfirmIcon"></i>
                    </button>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="invalid-feedback d-flex align-items-center gap-1">
                            <i class="bi bi-exclamation-circle-fill" style="font-size:0.75rem;"></i>
                            <?= e($errors['confirm_password']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Live match indicator -->
                <div id="matchIndicator"
                     style="font-size:0.78rem;margin-top:0.4rem;display:none;">
                </div>
            </div>

            <!-- ── Submit Button ──────────────────────────── -->
            <div class="d-grid mt-3">
                <button type="submit" id="submitBtn" class="btn-gms btn-gms-primary"
                        style="justify-content:center;font-size:1rem;padding:0.85rem;">
                    <i class="bi bi-person-check-fill"></i>
                    Create Account
                </button>
            </div>

            <!-- ── Terms notice ───────────────────────────── -->
            <p class="text-center mt-3"
               style="color:var(--clr-text-subtle);font-size:0.78rem;">
                By registering you agree to use this system responsibly.<br />
                This is a <strong style="color:var(--clr-text-muted);">BCA College Project</strong>.
            </p>

        </form>
    </div><!-- /card -->

    <!-- ── Already have account? ─────────────────────────────── -->
    <p class="text-center mt-4" style="color:var(--clr-text-muted);font-size:0.9rem;">
        Already have an account?
        <a href="<?= BASE_URL ?>login.php"
           style="color:var(--clr-accent);font-weight:700;text-decoration:none;">
            Login to GarageMS →
        </a>
    </p>

</div><!-- /col -->
</div><!-- /row -->
</div><!-- /container -->
</main>


<!-- ── Inline JS for register page UX (short, page-specific) ── -->
<script>
/* ──────────────────────────────────────────────────────────────
   SHOW / HIDE PASSWORD TOGGLE
   ────────────────────────────────────────────────────────────── */
function setupToggle(btnId, inputId, iconId) {
    var btn   = document.getElementById(btnId);
    var input = document.getElementById(inputId);
    var icon  = document.getElementById(iconId);
    if (!btn) return;

    btn.addEventListener('click', function () {
        var isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        icon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
}
setupToggle('togglePassword', 'reg_password', 'togglePasswordIcon');
setupToggle('toggleConfirm',  'reg_confirm',  'toggleConfirmIcon');

/* ──────────────────────────────────────────────────────────────
   PASSWORD STRENGTH METER
   Checks length, uppercase, lowercase, digit, special char
   ────────────────────────────────────────────────────────────── */
var pwInput  = document.getElementById('reg_password');
var strengthWrap  = document.getElementById('strengthWrap');
var strengthBar   = document.getElementById('strengthBar');
var strengthLabel = document.getElementById('strengthLabel');

if (pwInput) {
    pwInput.addEventListener('input', function () {
        var pw = this.value;

        if (pw.length === 0) {
            strengthWrap.style.display = 'none';
            return;
        }
        strengthWrap.style.display = 'block';

        var score = 0;
        if (pw.length >= 8)               score++;
        if (pw.length >= 12)              score++;
        if (/[A-Z]/.test(pw))             score++;
        if (/[a-z]/.test(pw))             score++;
        if (/[0-9]/.test(pw))             score++;
        if (/[^A-Za-z0-9]/.test(pw))      score++;

        var levels = [
            { label: 'Very Weak',  color: '#ef4444', pct: '15%' },
            { label: 'Weak',       color: '#f97316', pct: '30%' },
            { label: 'Fair',       color: '#eab308', pct: '50%' },
            { label: 'Good',       color: '#22c55e', pct: '72%' },
            { label: 'Strong',     color: '#10b981', pct: '88%' },
            { label: 'Very Strong',color: '#06b6d4', pct: '100%'},
        ];
        var lvl = levels[Math.min(score, 5)];
        strengthBar.style.width      = lvl.pct;
        strengthBar.style.background = lvl.color;
        strengthLabel.textContent    = lvl.label;
        strengthLabel.style.color    = lvl.color;
    });
}

/* ──────────────────────────────────────────────────────────────
   PASSWORD MATCH INDICATOR
   ────────────────────────────────────────────────────────────── */
var confirmInput   = document.getElementById('reg_confirm');
var matchIndicator = document.getElementById('matchIndicator');

function checkMatch() {
    if (!confirmInput || !pwInput) return;
    var pw  = pwInput.value;
    var cfm = confirmInput.value;

    if (cfm.length === 0) {
        matchIndicator.style.display = 'none';
        return;
    }
    matchIndicator.style.display = 'block';
    if (pw === cfm) {
        matchIndicator.innerHTML = '<i class="bi bi-check-circle-fill" style="color:#22c55e;"></i> <span style="color:#22c55e;">Passwords match</span>';
    } else {
        matchIndicator.innerHTML = '<i class="bi bi-x-circle-fill" style="color:#ef4444;"></i> <span style="color:#ef4444;">Passwords do not match</span>';
    }
}

if (confirmInput) confirmInput.addEventListener('input', checkMatch);
if (pwInput)      pwInput.addEventListener('input', checkMatch);

/* ──────────────────────────────────────────────────────────────
   PREVENT DOUBLE-SUBMIT
   ────────────────────────────────────────────────────────────── */
var form = document.getElementById('registerForm');
if (form) {
    form.addEventListener('submit', function () {
        var btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Creating account…';
        }
    });
}
</script>


<?php
$showCtaBanner = false;   // suppress footer CTA on auth pages
require_once __DIR__ . '/includes/footer.php';
?>
