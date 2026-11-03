<?php
/**
 * logout.php — Secure Session Logout & Activity Logging
 * Garage Management System
 *
 * Flow:
 *  1. Fetch user_id and login_log_id from authenticated PHP session.
 *  2. Update login_logs table: SET logout_time = NOW(), status = 'logged_out' (PDO prepared statement).
 *  3. Destroy session data, clear session cookie.
 *  4. Redirect user to login.php.
 *
 * Security:
 *  ✔ Never trusts user input for user_id (reads strictly from $_SESSION).
 *  ✔ Complete session tear-down prevents access to protected pages after logout.
 */

// ── Start session if not started ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Load database configuration and helpers ───────────────────
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '/GarageManagement/');
}

// ── CSRF Check on Logout ──────────────────────────────────────
$providedToken = $_POST['_csrf_token'] ?? $_GET['token'] ?? '';
$sessionToken  = $_SESSION['_csrf_token'] ?? '';

if (!empty($_SESSION['user_id']) && $providedToken !== '' && !empty($sessionToken)) {
    if (!hash_equals($sessionToken, $providedToken)) {
        setFlash('Security Error: Invalid logout token.', 'danger');
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

// ── 1. Update login_logs before destroying session ───────────
$userId     = $_SESSION['user_id'] ?? null;
$loginLogId = $_SESSION['login_log_id'] ?? null;

if ($userId !== null) {
    try {
        $pdo = getDBConnection();

        if ($loginLogId !== null) {
            // Update specific login session record using PDO prepared statement
            $stmt = $pdo->prepare(
                'UPDATE login_logs
                    SET logout_time = NOW(),
                        status = "logged_out"
                  WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([(int)$loginLogId, (int)$userId]);
        } else {
            // Fallback: update most recent active login session for this user
            $stmt = $pdo->prepare(
                'UPDATE login_logs
                    SET logout_time = NOW(),
                        status = "logged_out"
                  WHERE user_id = ? AND status = "logged_in"
               ORDER BY id DESC
                  LIMIT 1'
            );
            $stmt->execute([(int)$userId]);
        }

        logActivity($pdo, 'User logout', 'User ID ' . $userId . ' logged out.');
    } catch (Throwable $e) {
        error_log('[GarageMS] logout log update failed: ' . $e->getMessage());
    }
}

// ── 2. Clear session variables ────────────────────────────────
$_SESSION = [];

// ── 3. Delete session cookie from browser ─────────────────────
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict',
        ]
    );
}

// ── 4. Destroy server-side session ────────────────────────────
session_destroy();

// ── 5. Redirect to login.php ──────────────────────────────────
header('Location: ' . BASE_URL . 'login.php?msg=loggedout');
exit;
