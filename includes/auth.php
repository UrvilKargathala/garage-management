<?php
/**
 * includes/auth.php — Role-Based Access Control (RBAC) & Authentication Guards
 * Garage Management System
 *
 * Security & Guard Functions:
 *  ✔ isLoggedIn()                         — Checks if user is authenticated
 *  ✔ currentUserId()                       — Returns logged-in user ID or null
 *  ✔ currentUserRole()                     — Returns logged-in user role or null
 *  ✔ hasRole(string|array $roles)         — Checks if user has given role(s)
 *  ✔ requireLogin()                       — Redirects unauthenticated users to login.php
 *  ✔ requireRole(string|array $roles)     — Enforces role requirement with redirect
 *  ✔ requireAdmin()                       — Restricts page to Admin role only
 *  ✔ requireStaffOrAdmin()                — Restricts page to Staff or Admin roles
 *  ✔ requireCustomer()                    — Restricts page to Customer role only
 *
 * Anti-IDOR (Ownership Verification) Helpers:
 *  ✔ getLoggedInCustomerId(PDO $pdo)       — Retrieves customer.id linked to session user_id
 *  ✔ canAccessCustomerData(PDO, customerId) — Returns bool if user owns or is staff/admin
 *  ✔ canAccessVehicleData(PDO, vehicleId)   — Returns bool if user owns or is staff/admin
 *  ✔ canAccessBookingData(PDO, bookingId)   — Returns bool if user owns or is staff/admin
 *  ✔ verifyCustomerOwnership(PDO, customerId)— Blocks IDOR access to customer records
 *  ✔ verifyVehicleOwnership(PDO, vehicleId)  — Blocks IDOR access to vehicle records
 *  ✔ verifyBookingOwnership(PDO, bookingId)  — Blocks IDOR access to booking records
 */

// ── Security Headers ──────────────────────────────────────────
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
}

// ── Start a secure PHP session ────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_set_cookie_params([
        'lifetime' => 0,             // Cookie expires when browser closes
        'path'     => '/',
        'domain'   => '',            // Current domain only
        'secure'   => $isHttps,      // Automatically true in HTTPS production, false in local HTTP
        'httponly' => true,          // JavaScript cannot read cookie
        'samesite' => 'Strict',      // Prevent CSRF via cross-site requests
    ]);
    session_start();
}

// ── Session Fingerprint Integrity Check ───────────────────────
if (isset($_SESSION['user_id'])) {
    $expectedFingerprint = $_SESSION['_fingerprint'] ?? null;
    $currentFingerprint  = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . session_id());

    if ($expectedFingerprint !== null && $expectedFingerprint !== $currentFingerprint) {
        $_SESSION = [];
        session_destroy();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('currentUserId')) {
    function currentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('currentUserRole')) {
    function currentUserRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }
}

if (!function_exists('hasRole')) {
    function hasRole(string|array $roles): bool
    {
        if (!isLoggedIn()) {
            return false;
        }

        $userRole = currentUserRole();
        if ($userRole === null) {
            return false;
        }

        if (is_string($roles)) {
            return strtolower($userRole) === strtolower($roles);
        }

        if (is_array($roles)) {
            $allowed = array_map('strtolower', $roles);
            return in_array(strtolower($userRole), $allowed, true);
        }

        return false;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        return hasRole('admin');
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(): void
    {
        if (!isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        }
    }
}

if (!function_exists('requireRole')) {
    function requireRole(string|array $roles, ?string $redirectUrl = null): void
    {
        requireLogin();

        if (!hasRole($roles)) {
            if (function_exists('setFlash')) {
                setFlash('Access Denied: You do not have permission to access that page.', 'danger');
            }
            $target = $redirectUrl ?? (BASE_URL . 'dashboard.php');
            header('Location: ' . $target);
            exit;
        }
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(): void
    {
        requireRole('admin');
    }
}

if (!function_exists('requireStaffOrAdmin')) {
    function requireStaffOrAdmin(): void
    {
        requireRole(['admin', 'staff']);
    }
}

if (!function_exists('requireStaff')) {
    function requireStaff(): void
    {
        requireRole('staff');
    }
}

if (!function_exists('requireCustomer')) {
    function requireCustomer(): void
    {
        requireRole('customer');
    }
}


/* ================================================================
   ANTI-IDOR / OWNERSHIP VERIFICATION HELPERS
   Prevents customers from accessing other customers' records
   by manipulating IDs in URL query parameters.
   ================================================================ */

/**
 * getLoggedInCustomerId()
 *
 * Retrieves the customer table record ID linked to the active session user_id.
 *
 * @param  PDO $pdo
 * @return int|null
 */
function getLoggedInCustomerId(PDO $pdo): ?int
{
    $userId = currentUserId();
    if (!$userId) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['id'];
        }

        // If user is a customer but no customers record is linked yet, auto-provision one
        if (hasRole('customer')) {
            $stmtUser = $pdo->prepare('SELECT name, email, phone FROM users WHERE id = ? LIMIT 1');
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();
            if ($user) {
                // Check if matching customer exists by phone/email without user_id
                $stmtCheck = $pdo->prepare('SELECT id FROM customers WHERE (email = ? AND email IS NOT NULL AND email != "") OR (phone = ? AND phone != "") LIMIT 1');
                $stmtCheck->execute([$user['email'], $user['phone']]);
                $existingCust = $stmtCheck->fetch();

                if ($existingCust) {
                    $cId = (int)$existingCust['id'];
                    $stmtUpdate = $pdo->prepare('UPDATE customers SET user_id = ? WHERE id = ?');
                    $stmtUpdate->execute([$userId, $cId]);
                    return $cId;
                } else {
                    $stmtInsert = $pdo->prepare('INSERT INTO customers (user_id, name, phone, email) VALUES (?, ?, ?, ?)');
                    $stmtInsert->execute([$userId, $user['name'], $user['phone'], $user['email']]);
                    return (int)$pdo->lastInsertId();
                }
            }
        }

        return null;
    } catch (PDOException $e) {
        error_log('[GarageMS] getLoggedInCustomerId error: ' . $e->getMessage());
        return null;
    }
}

/**
 * canAccessCustomerData()
 *
 * Returns true if current user is Admin/Staff OR if customerId belongs to the logged-in customer.
 *
 * @param  PDO $pdo
 * @param  int $customerId
 * @return bool
 */
function canAccessCustomerData(PDO $pdo, int $customerId): bool
{
    if (hasRole(['admin', 'staff'])) {
        return true;
    }

    if (!hasRole('customer')) {
        return false;
    }

    $loggedInCustId = getLoggedInCustomerId($pdo);
    return $loggedInCustId !== null && $loggedInCustId === $customerId;
}

/**
 * canAccessVehicleData()
 *
 * Returns true if current user is Admin/Staff OR if vehicleId belongs to the logged-in customer.
 *
 * @param  PDO $pdo
 * @param  int $vehicleId
 * @return bool
 */
function canAccessVehicleData(PDO $pdo, int $vehicleId): bool
{
    if (hasRole(['admin', 'staff'])) {
        return true;
    }

    if (!hasRole('customer')) {
        return false;
    }

    $userId = currentUserId();
    if (!$userId) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT v.id
               FROM vehicles v
               JOIN customers c ON v.customer_id = c.id
              WHERE v.id = ? AND c.user_id = ?
              LIMIT 1'
        );
        $stmt->execute([$vehicleId, $userId]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        error_log('[GarageMS] canAccessVehicleData error: ' . $e->getMessage());
        return false;
    }
}

/**
 * canAccessBookingData()
 *
 * Returns true if current user is Admin/Staff OR if bookingId belongs to the logged-in customer.
 *
 * @param  PDO $pdo
 * @param  int $bookingId
 * @return bool
 */
function canAccessBookingData(PDO $pdo, int $bookingId): bool
{
    if (hasRole(['admin', 'staff'])) {
        return true;
    }

    if (!hasRole('customer')) {
        return false;
    }

    $userId = currentUserId();
    if (!$userId) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT b.id
               FROM bookings b
               JOIN customers c ON b.customer_id = c.id
              WHERE b.id = ? AND c.user_id = ?
              LIMIT 1'
        );
        $stmt->execute([$bookingId, $userId]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        error_log('[GarageMS] canAccessBookingData error: ' . $e->getMessage());
        return false;
    }
}

/**
 * verifyCustomerOwnership()
 *
 * Enforces ownership check for a customer record. Redirects to dashboard on failure.
 *
 * @param  PDO $pdo
 * @param  int $customerId
 * @return void
 */
function verifyCustomerOwnership(PDO $pdo, int $customerId): void
{
    requireLogin();
    if (!canAccessCustomerData($pdo, $customerId)) {
        if (function_exists('setFlash')) {
            setFlash('Access Denied: You cannot view or modify another customer\'s profile.', 'danger');
        }
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

/**
 * verifyVehicleOwnership()
 *
 * Enforces ownership check for a vehicle record. Redirects to dashboard on failure.
 *
 * @param  PDO $pdo
 * @param  int $vehicleId
 * @return void
 */
function verifyVehicleOwnership(PDO $pdo, int $vehicleId): void
{
    requireLogin();
    if (!canAccessVehicleData($pdo, $vehicleId)) {
        if (function_exists('setFlash')) {
            setFlash('Access Denied: You cannot view or modify another customer\'s vehicle.', 'danger');
        }
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

/**
 * verifyBookingOwnership()
 *
 * Enforces ownership check for a booking record. Redirects to dashboard on failure.
 *
 * @param  PDO $pdo
 * @param  int $bookingId
 * @return void
 */
function verifyBookingOwnership(PDO $pdo, int $bookingId): void
{
    requireLogin();
    if (!canAccessBookingData($pdo, $bookingId)) {
        if (function_exists('setFlash')) {
            setFlash('Access Denied: You cannot view or modify another customer\'s booking.', 'danger');
        }
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}


/* ================================================================
   CSRF PROTECTION HELPERS
   Provides token-per-session CSRF protection for all state-changing
   POST forms in the application.
   ================================================================ */

/**
 * csrfToken()
 *
 * Returns the current session CSRF token, generating one with
 * cryptographically secure random bytes if none exists yet.
 *
 * @return string  64-character hex string
 */
function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * csrfField()
 *
 * Returns a hidden HTML input containing the CSRF token.
 * Call this inside every state-changing <form>.
 *
 * @return string  HTML <input type="hidden"> element
 */
function csrfField(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';
}

/**
 * verifyCsrfToken()
 *
 * Validates the CSRF token submitted in a POST request.
 * Uses hash_equals() to prevent timing-based attacks.
 * Terminates with a 403 error and flash message if the token is
 * missing or does not match.
 *
 * Call at the very beginning of every POST handler.
 *
 * @return void
 */
function verifyCsrfToken(): void
{
    $submitted = $_POST['_csrf_token'] ?? '';
    $expected  = $_SESSION['_csrf_token'] ?? '';

    if ($expected === '' || !hash_equals($expected, $submitted)) {
        // Rotate the token after a failed attempt
        unset($_SESSION['_csrf_token']);

        if (function_exists('setFlash')) {
            setFlash('Security Error: Invalid or expired form token. Please try again.', 'danger');
        }
        // Redirect back to referer or dashboard; never expose details
        $referer = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'dashboard.php');
        header('Location: ' . $referer);
        exit;
    }
}

// ══════════════════════════════════════════════════════════════
//  PERSISTENT RATE-LIMITING HELPERS (Database-Backed)
// ══════════════════════════════════════════════════════════════

/**
 * Check if an email or client IP is currently rate-limited.
 *
 * @param PDO    $pdo
 * @param string $email Normalized email
 * @param string $ip    Client IP address (REMOTE_ADDR)
 * @param int    $maxAccountAttempts Max failed attempts per email before cooldown (default: 5)
 * @param int    $maxIpAttempts      Max failed attempts per IP before cooldown (default: 15)
 * @param int    $windowMinutes      Sliding window in minutes (default: 15)
 * @return int Remaining cooldown minutes, or 0 if allowed
 */
function checkLoginRateLimit(
    PDO $pdo,
    string $email,
    string $ip,
    int $maxAccountAttempts = 5,
    int $maxIpAttempts = 15,
    int $windowMinutes = 15
): int {
    try {
        $windowSeconds = $windowMinutes * 60;
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);

        // 1. Account-level threshold
        if ($email !== '') {
            $stmtAccount = $pdo->prepare(
                'SELECT COUNT(*) AS cnt, MAX(attempted_at) AS latest
                   FROM login_attempts
                  WHERE email = ? AND attempted_at >= ?'
            );
            $stmtAccount->execute([$email, $cutoff]);
            $accountStats = $stmtAccount->fetch(PDO::FETCH_ASSOC);

            if ($accountStats && (int)$accountStats['cnt'] >= $maxAccountAttempts && !empty($accountStats['latest'])) {
                $elapsed = time() - strtotime($accountStats['latest']);
                $remaining = (int)ceil(($windowSeconds - $elapsed) / 60);
                if ($remaining > 0) {
                    return $remaining;
                }
            }
        }

        // 2. IP-level threshold (prevents distributed brute-forcing across multiple accounts)
        if ($ip !== '' && $ip !== '0.0.0.0') {
            $stmtIp = $pdo->prepare(
                'SELECT COUNT(*) AS cnt, MAX(attempted_at) AS latest
                   FROM login_attempts
                  WHERE ip_address = ? AND attempted_at >= ?'
            );
            $stmtIp->execute([$ip, $cutoff]);
            $ipStats = $stmtIp->fetch(PDO::FETCH_ASSOC);

            if ($ipStats && (int)$ipStats['cnt'] >= $maxIpAttempts && !empty($ipStats['latest'])) {
                $elapsed = time() - strtotime($ipStats['latest']);
                $remaining = (int)ceil(($windowSeconds - $elapsed) / 60);
                if ($remaining > 0) {
                    return $remaining;
                }
            }
        }

        return 0;
    } catch (Throwable $e) {
        error_log('[GarageMS] checkLoginRateLimit error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Record a failed login attempt in login_attempts table.
 *
 * @param PDO    $pdo
 * @param string $email Normalized email
 * @param string $ip    Client IP address (REMOTE_ADDR)
 * @return void
 */
function recordFailedLoginAttempt(PDO $pdo, string $email, string $ip): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, attempted_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$email, $ip]);

        // Cleanup expired records older than 24 hours (probabilistic: 1 in 10 requests)
        if (random_int(1, 10) === 1) {
            $cleanupCutoff = date('Y-m-d H:i:s', time() - 86400);
            $stmtCleanup = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
            $stmtCleanup->execute([$cleanupCutoff]);
        }
    } catch (Throwable $e) {
        error_log('[GarageMS] recordFailedLoginAttempt error: ' . $e->getMessage());
    }
}

/**
 * Clear failed login attempts upon successful login.
 *
 * @param PDO    $pdo
 * @param string $email Normalized email
 * @param string $ip    Client IP address (REMOTE_ADDR)
 * @return void
 */
function clearFailedLoginAttempts(PDO $pdo, string $email, string $ip): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email = ?');
        $stmt->execute([$email]);
    } catch (Throwable $e) {
        error_log('[GarageMS] clearFailedLoginAttempts error: ' . $e->getMessage());
    }
}

