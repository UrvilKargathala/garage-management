<?php
/**
 * dashboard.php — Main Dashboard
 * Garage Management System
 *
 * Protected page — requires an active login session.
 * Displays a personalised welcome banner, role-aware user info card,
 * and stat cards (ready for live data in later modules).
 *
 * Security:
 *  ✔ requireLogin() redirects unauthenticated visitors to login.php
 *  ✔ All session values output through e() to prevent XSS
 */

// ── Bootstrap ─────────────────────────────────────────────────
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';   // session_start, BASE_URL, functions, auth

// ── ACCESS CONTROL ────────────────────────────────────────────
// requireLogin() is defined in includes/auth.php (loaded by header.php).
// It redirects to login.php and exits if no user is in session.
requireLogin();

// ── ROLE-BASED ROUTING ────────────────────────────────────────
$userRole = currentUserRole();

if ($userRole === 'admin') {
    redirect(BASE_URL . 'admin/');
} elseif ($userRole === 'staff') {
    redirect(BASE_URL . 'staff/dashboard.php');
} elseif ($userRole === 'customer') {
    redirect(BASE_URL . 'customer/dashboard.php');
} else {
    // Fallback if role is missing or invalid
    redirect(BASE_URL . 'login.php');
}
?>
