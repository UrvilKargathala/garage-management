<?php
/**
 * includes/header.php
 * Garage Management System
 *
 * Outputs the full HTML <head> section for every page.
 * Must be included FIRST on every PHP page.
 *
 * Expecting page to set $pageTitle before including this file.
 */

// Start session (safe if already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Site-wide constants ───────────────────────────────────────
// BASE_URL is used by all includes and modules for href/src paths.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/GarageManagement/');
}

// Load helpers (required by every page)
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Default page title
$pageTitle = isset($pageTitle) ? trim($pageTitle) : 'Home';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <!-- ── SEO ──────────────────────────────────────────────── -->
    <title><?= e($pageTitle) ?> | GarageMS — Professional Garage Management</title>
    <meta name="description"
          content="GarageMS — A complete garage management system to book services, track vehicles, and manage your garage professionally." />
    <meta name="keywords"
          content="garage, car service, vehicle management, booking, oil change, PHP project" />
    <meta name="author" content="GarageMS" />

    <!-- ── Open Graph (for social sharing) ──────────────────── -->
    <meta property="og:title"       content="<?= e($pageTitle) ?> | GarageMS" />
    <meta property="og:description" content="Professional Garage Management System" />
    <meta property="og:type"        content="website" />

    <!-- ── Favicon (placeholder) ────────────────────────────── -->
    <link rel="icon" type="image/svg+xml"
          href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔧</text></svg>" />

    <!-- ── Bootstrap 5.3 CSS ────────────────────────────────── -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    />

    <!-- ── Bootstrap Icons ───────────────────────────────────── -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    />

    <!-- ── Custom CSS ────────────────────────────────────────── -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>" />
</head>
<body>
