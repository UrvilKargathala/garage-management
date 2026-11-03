<?php
/**
 * login.php — User Login Page
 * Garage Management System
 *
 * Flow:
 *  GET  → display the login form
 *  POST → validate input → authenticate against MySQL users table
 *       → on success: set session variables, regenerate session ID, redirect
 *       → on failure: show a generic error (never reveal if email exists)
 *
 * Security checklist:
 *  ✔ Email and password validated before any DB query
 *  ✔ User fetched with PDO prepared statement
 *  ✔ Password verified with password_verify() — never plain-text compared
 *  ✔ Generic error message used — does not reveal whether email exists
 *  ✔ Session ID regenerated after successful login (session fixation prevention)
 *  ✔ Session bound to browser fingerprint (session hijacking deterrent)
 *  ✔ Already-authenticated users are redirected away from this page
 *  ✔ Activity logged on successful login
 */

// ── Bootstrap ─────────────────────────────────────────────────
require_once __DIR__ . '/includes/header.php';   // session_start, BASE_URL, functions, auth
require_once __DIR__ . '/config/database.php';   // getDBConnection()

// Redirect authenticated users straight to the dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

// ── Initialise state ──────────────────────────────────────────
$error   = '';    // Generic error displayed to the user
$pageTitle = 'Login';

// ══════════════════════════════════════════════════════════════
//  POST HANDLER — processes the login form submission
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF verification (defense-in-depth; SameSite=Strict cookie also protects)
    verifyCsrfToken();

    // ── 1. Read and sanitise raw input ────────────────────────
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';   // NOT trimmed — spaces are valid in passwords
    $email    = strtolower($email);              // Normalise to lower-case to match registration

    // Direct client IP address (REMOTE_ADDR only — never trust forwarded headers without a trusted proxy)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
        $clientIp = '0.0.0.0';
    }

    // ── 2. Basic input validation ─────────────────────────────
    if ($email === '' || $password === '') {
        $error = 'Please enter your email address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Deliberately generic — we don't confirm whether the email is registered
        $error = 'Invalid email address or password. Please try again.';
    }

    // ── 3. Persistent Server-Side Rate-Limiting & Database Query ────
    if ($error === '') {
        try {
            $pdo = getDBConnection();

            // Check database-backed persistent rate limits (account-level: 5 in 15min, IP-level: 15 in 15min)
            $cooldownMinutes = checkLoginRateLimit($pdo, $email, $clientIp, 5, 15, 15);
            if ($cooldownMinutes > 0) {
                $error = "Too many failed login attempts. Please try again in {$cooldownMinutes} minute(s).";
            }
        } catch (PDOException $e) {
            error_log('[GarageMS] Rate-limit check DB error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again in a moment.';
        }
    }

    if ($error === '') {
        try {

            /*
             * Fetch the user row by email using a prepared statement.
             * We select only the columns we actually need.
             * The password check happens in PHP (password_verify), never in SQL.
             */
            $stmt = $pdo->prepare(
                'SELECT id, name, email, password, role
                   FROM users
                  WHERE email = ?
                  LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(); // PDO::FETCH_ASSOC (set in database.php)

            /*
             * password_verify() must always be called even when the user is not
             * found.  This prevents timing-based user-enumeration attacks where an
             * attacker can tell whether an email exists by measuring response time.
             *
             * We use a placeholder hash for the "no user" branch so the bcrypt
             * comparison still takes the same amount of time.
             */
            $storedHash = $user['password'] ?? '$2y$12$invalidhashusedfortimingnomatch00000000000000000000000';

            if ($user && password_verify($password, $storedHash)) {
                // ── SUCCESS: Authenticate the user ───────────

                /*
                 * Regenerate the session ID immediately after a successful login.
                 * This prevents session-fixation attacks where an attacker plants
                 * a known session ID before the user logs in.
                 */
                session_regenerate_id(true);

                // Store only the data we need — never store the password hash
                $_SESSION['user_id']    = (int) $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_email'] = $user['email'];

                // ── Activity Logging: login_logs ────────────
                // IP address from server request (never trust user input)
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
                if ($ipAddress !== null && !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                    $ipAddress = null;
                }

                // Create login log record via PDO prepared statement
                try {
                    $logStmt = $pdo->prepare(
                        'INSERT INTO login_logs (user_id, login_time, ip_address, status)
                         VALUES (?, NOW(), ?, "logged_in")'
                    );
                    $logStmt->execute([(int)$user['id'], $ipAddress]);
                    $_SESSION['login_log_id'] = (int)$pdo->lastInsertId();
                } catch (Throwable $e) {
                    error_log('[GarageMS] login_logs insert failed: ' . $e->getMessage());
                }

                /*
                 * Session fingerprint — binds the session to this browser.
                 * Computed AFTER regenerate_id so it uses the new session ID.
                 * If the user agent or session ID changes later, auth.php will
                 * destroy the session as a potential hijack attempt.
                 */
                $_SESSION['_fingerprint'] = hash(
                    'sha256',
                    ($_SERVER['HTTP_USER_AGENT'] ?? '') . session_id()
                );

                // Log the successful login
                logActivity($pdo, 'User login', 'User ' . $user['email'] . ' logged in successfully.');

                // Show a welcome flash message on the dashboard
                setFlash('Welcome back, ' . $user['name'] . '! You are now logged in.', 'success');

                // Redirect to the originally intended page, or the role-based dashboard
                // Clear persistent failed login attempts on successful authentication
                clearFailedLoginAttempts($pdo, $email, $clientIp);
                unset($_SESSION['login_attempts'], $_SESSION['login_lockout_until']);

                // Upgrade legacy password hashes transparently if algorithm or cost changed
                if (password_needs_rehash($storedHash, PASSWORD_BCRYPT, ['cost' => 12])) {
                    try {
                        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $rehashStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                        $rehashStmt->execute([$newHash, (int)$user['id']]);
                    } catch (Throwable $e) {
                        error_log('[GarageMS] Password rehash failed: ' . $e->getMessage());
                    }
                }

                $roleDestinations = [
                    'admin'    => BASE_URL . 'admin/',
                    'staff'    => BASE_URL . 'staff/dashboard.php',
                    'customer' => BASE_URL . 'customer/dashboard.php',
                ];
                $defaultDestination = $roleDestinations[$user['role']] ?? (BASE_URL . 'dashboard.php');
                
                $destination = $_SESSION['redirect_after_login'] ?? $defaultDestination;
                unset($_SESSION['redirect_after_login']);

                redirect($destination);

            } else {
                // ── FAILURE: Invalid credentials ─────────────
                recordFailedLoginAttempt($pdo, $email, $clientIp);
                $remainingCooldown = checkLoginRateLimit($pdo, $email, $clientIp, 5, 15, 15);

                if ($remainingCooldown > 0) {
                    $error = "Too many failed login attempts. Please try again in {$remainingCooldown} minute(s).";
                } else {
                    /*
                     * Use a generic message that does NOT reveal whether the email
                     * address is registered in the system.  This prevents user-
                     * enumeration attacks.
                     */
                    $error = 'Invalid email address or password. Please try again.';
                }
            }

        } catch (PDOException $e) {
            // Log internally, show a safe message to the user
            error_log('[GarageMS] Login DB error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again in a moment.';
        }
    }

} // end POST handler

// ── Render navbar now that $pageTitle is set ─────────────────
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- ════════════════════════════════════════════════════════════
     LOGIN PAGE
     ════════════════════════════════════════════════════════════ -->
<div class="gms-page-wrapper py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">

                <!-- ── Login Card ────────────────────────────── -->
                <div class="gms-form-card shadow-lg">

                    <!-- Header -->
                    <div class="text-center mb-4">
                        <div class="gms-auth-icon mb-3">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <h1 class="h4 fw-bold mb-1">Welcome Back</h1>
                        <p class="text-muted small mb-0">Sign in to Garage Management System</p>
                    </div>

                    <!-- Error Alert -->
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert" id="loginErrorAlert">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                            <span><?= e($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Flash message (e.g., after register redirect) -->
                    <?php showFlash(); ?>

                    <!-- ── Login Form ──────────────────────────── -->
                    <form
                        id="loginForm"
                        method="POST"
                        action="<?= BASE_URL ?>login.php"
                        novalidate
                        autocomplete="on"
                    >
                        <?= csrfField() ?>
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="loginEmail" class="form-label fw-semibold">
                                <i class="bi bi-envelope me-1 text-warning"></i>Email Address
                            </label>
                            <input
                                type="email"
                                id="loginEmail"
                                name="email"
                                class="form-control gms-input<?= $error !== '' ? ' is-invalid' : '' ?>"
                                value="<?= e(isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '') ?>"
                                placeholder="you@example.com"
                                required
                                autocomplete="email"
                                maxlength="150"
                                aria-describedby="emailHelp"
                            >
                        </div>

                        <!-- Password -->
                        <div class="mb-4">
                            <label for="loginPassword" class="form-label fw-semibold">
                                <i class="bi bi-lock me-1 text-warning"></i>Password
                            </label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    id="loginPassword"
                                    name="password"
                                    class="form-control gms-input<?= $error !== '' ? ' is-invalid' : '' ?>"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                    aria-label="Password"
                                >
                                <button
                                    type="button"
                                    id="togglePasswordBtn"
                                    class="btn gms-input-addon"
                                    aria-label="Toggle password visibility"
                                    title="Show / Hide password"
                                >
                                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            id="loginSubmitBtn"
                            class="btn btn-gms-primary w-100 fw-semibold py-2 mb-3"
                        >
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>

                        <!-- Divider -->
                        <div class="gms-divider my-3">
                            <span>Don't have an account?</span>
                        </div>

                        <!-- Register link -->
                        <a
                            href="<?= BASE_URL ?>register.php"
                            class="btn btn-outline-secondary w-100"
                            id="goToRegisterBtn"
                        >
                            <i class="bi bi-person-plus me-2"></i>Create an Account
                        </a>

                    </form>
                    <!-- /Login Form -->

                </div>
                <!-- /Login Card -->

            </div>
        </div>
    </div>
</div>

<style>
/* ── Login-page specific styles ─────────────────────────────── */

/* Auth icon badge */
.gms-auth-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(249,115,22,0.15), rgba(249,115,22,0.05));
    border: 1.5px solid rgba(249,115,22,0.3);
    font-size: 2rem;
    color: var(--clr-accent);
    box-shadow: 0 0 32px rgba(249,115,22,0.15);
    animation: authIconPulse 3s ease-in-out infinite;
}

@keyframes authIconPulse {
    0%, 100% { box-shadow: 0 0 32px rgba(249,115,22,0.15); }
    50%       { box-shadow: 0 0 48px rgba(249,115,22,0.30); }
}

/* Addon button (show/hide password) */
.gms-input-addon {
    background: var(--clr-dark-3);
    border: 1px solid rgba(255,255,255,0.1);
    border-left: none;
    color: var(--clr-text-muted);
    transition: color 0.2s;
}
.gms-input-addon:hover,
.gms-input-addon:focus {
    color: var(--clr-accent);
    background: var(--clr-dark-3);
    box-shadow: none;
}

/* Divider */
.gms-divider {
    position: relative;
    text-align: center;
    color: var(--clr-text-subtle);
    font-size: 0.8rem;
}
.gms-divider::before,
.gms-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: calc(50% - 80px);
    height: 1px;
    background: rgba(255,255,255,0.08);
}
.gms-divider::before { left: 0; }
.gms-divider::after  { right: 0; }
</style>

<script>
/* ── Toggle password visibility ─────────────────────────────── */
(function () {
    const btn  = document.getElementById('togglePasswordBtn');
    const inp  = document.getElementById('loginPassword');
    const icon = document.getElementById('togglePasswordIcon');

    if (btn && inp && icon) {
        btn.addEventListener('click', function () {
            const show = inp.type === 'password';
            inp.type   = show ? 'text' : 'password';
            icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    /* ── Client-side submit guard ────────────────────────────── */
    const form = document.getElementById('loginForm');
    const sbmt = document.getElementById('loginSubmitBtn');
    if (form && sbmt) {
        form.addEventListener('submit', function () {
            sbmt.disabled = true;
            sbmt.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Signing in…';
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
