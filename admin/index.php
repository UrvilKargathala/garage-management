<?php
/**
 * admin/index.php — Admin Panel Entry Point
 * Garage Management System
 *
 * Redirects to the Admin Dashboard. Required so that
 * admin/ (directory URL) works correctly.
 */

require_once __DIR__ . '/../includes/header.php';

requireAdmin();

header('Location: ' . BASE_URL . 'admin/dashboard.php');
exit;
