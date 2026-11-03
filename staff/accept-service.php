<?php
/**
 * staff/accept-service.php — Staff Accept Service Request Handler
 * Garage Management System
 *
 * Requirements & Security:
 *  ✔ Staff or Admin only (`requireStaffOrAdmin()`)
 *  ✔ POST only with verified CSRF token
 *  ✔ Validates booking is in 'pending' status
 *  ✔ Updates status to 'confirmed' and assigns current staff_id
 *  ✔ Logs activity to `activity_logs`
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireStaffOrAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('Invalid request method. Action must be submitted via POST.', 'danger');
    redirect(BASE_URL . 'staff/dashboard.php');
}

verifyCsrfToken();

$pdo = getDBConnection();
$bookingId = (int)($_POST['booking_id'] ?? 0);
$staffUserId = currentUserId();

if ($bookingId <= 0) {
    setFlash('Invalid booking ID.', 'danger');
    redirect(BASE_URL . 'staff/dashboard.php');
}

try {
    $stmt = $pdo->prepare('
        SELECT b.*, c.name AS customer_name, v.brand, v.model, v.vehicle_number, s.service_name
        FROM bookings b
        JOIN customers c ON b.customer_id = c.id
        JOIN vehicles v ON b.vehicle_id = v.id
        JOIN services s ON b.service_id = s.id
        WHERE b.id = ? LIMIT 1
    ');
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        setFlash('Service request not found.', 'danger');
        redirect(BASE_URL . 'staff/dashboard.php');
    }

    if ($booking['status'] !== 'pending') {
        setFlash("Booking #{$bookingId} is already {$booking['status']}.", 'warning');
        redirect(BASE_URL . 'staff/dashboard.php');
    }

    // Update status to confirmed and assign staff member
    $pdo->beginTransaction();

    $stmtUpdate = $pdo->prepare('UPDATE bookings SET status = "confirmed", staff_id = ? WHERE id = ?');
    $stmtUpdate->execute([$staffUserId, $bookingId]);

    $pdo->commit();

    logActivity(
        $pdo,
        'ACCEPT_SERVICE',
        "Service request #{$bookingId} for {$booking['customer_name']} ({$booking['brand']} {$booking['model']}) accepted.",
        'booking',
        $bookingId
    );

    // If request asks for JSON response (e.g. AJAX from dashboard)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Service accepted successfully',
            'customer_name' => $booking['customer_name'],
            'vehicle' => $booking['brand'] . ' ' . $booking['model'],
            'service' => $booking['service_name']
        ]);
        exit;
    }

    // Set flash and redirect to booking view or staff dashboard
    setFlash("✓ Service request #{$bookingId} accepted! Customer {$booking['customer_name']} has been notified and scheduled.", 'success');
    redirect($_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'staff/dashboard.php'));

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[GarageMS] Accept Service Error: ' . $e->getMessage());
    setFlash('Failed to accept service request due to a database error.', 'danger');
    redirect(BASE_URL . 'staff/dashboard.php');
}
