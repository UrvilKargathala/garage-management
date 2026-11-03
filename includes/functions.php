<?php
/**
 * functions.php — Global helper functions
 * Garage Management System
 *
 * Place reusable utility functions here so every page can
 * include this file instead of duplicating code.
 */

/**
 * Sanitize output to prevent XSS attacks.
 * Always use this when printing user-supplied data in HTML.
 *
 * @param  string $data
 * @return string
 */
function e(?string $data = ''): string
{
    return htmlspecialchars($data ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Redirect the browser to a different page.
 *
 * @param  string $url  Relative or absolute URL
 * @return void
 */
function redirect(string $url): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header("Location: $url");
    exit;
}

/**
 * Store a flash message in the session.
 * Flash messages are shown once on the next page load, then cleared.
 *
 * @param  string $message
 * @param  string $type     Bootstrap alert type: success | danger | warning | info
 * @return void
 */
function setFlash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/**
 * Display and clear the flash message (if one exists).
 *
 * @return void
 */
function showFlash(): void
{
    if (!empty($_SESSION['flash'])) {
        $msg  = e($_SESSION['flash']['message']);
        $type = e($_SESSION['flash']['type']);
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$msg}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        unset($_SESSION['flash']);
    }
}

/**
 * Check if a string is empty after trimming whitespace.
 *
 * @param  string $value
 * @return bool
 */
function isEmpty(string $value): bool
{
    return trim($value) === '';
}

/**
 * Format a date string for display (e.g. "12 Aug 2026").
 *
 * @param  string $dateStr  MySQL DATE or DATETIME string
 * @return string
 */
function formatDate(string $dateStr): string
{
    $dt = new DateTime($dateStr);
    return $dt->format('d M Y');
}

/**
 * Format a datetime string for display (e.g. "12 Aug 2026, 05:30 PM").
 *
 * @param  string $dateStr
 * @return string
 */
function formatDateTime(string $dateStr): string
{
    $dt = new DateTime($dateStr);
    return $dt->format('d M Y, h:i A');
}

if (!function_exists('currentUserId')) {
    /**
     * Return the currently logged-in user's ID from the session.
     * Returns null if no one is logged in.
     *
     * @return int|null
     */
    function currentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('currentUserRole')) {
    /**
     * Return the currently logged-in user's role.
     * Returns null if no one is logged in.
     *
     * @return string|null  'admin' | 'staff' | null
     */
    function currentUserRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }
}

if (!function_exists('isAdmin')) {
    /**
     * Check if the current user is an admin.
     *
     * @return bool
     */
    function isAdmin(): bool
    {
        return (currentUserRole() === 'admin');
    }
}

/* ================================================================
   INPUT VALIDATION HELPERS
   Used by register.php, login.php, and any module form.
   Each function returns an error string on failure, or '' on pass.
   ================================================================ */

/**
 * Validate a person's full name.
 *  - Must not be empty.
 *  - 2–100 characters.
 *  - Only letters, spaces, hyphens, and apostrophes.
 *
 * @param  string $name
 * @return string  Error message, or '' if valid.
 */
function validateName(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'Full name is required.';
    }
    if (mb_strlen($name) < 2) {
        return 'Full name must be at least 2 characters.';
    }
    if (mb_strlen($name) > 100) {
        return 'Full name must not exceed 100 characters.';
    }
    // Allow letters (any script), spaces, hyphens, apostrophes
    if (!preg_match("/^[\p{L}\s\-']+$/u", $name)) {
        return 'Full name may only contain letters, spaces, hyphens, and apostrophes.';
    }
    return '';
}

/**
 * Validate an email address.
 *  - Must not be empty.
 *  - Must pass PHP's FILTER_VALIDATE_EMAIL.
 *  - Maximum 150 characters (matches DB column).
 *
 * @param  string $email
 * @return string  Error message, or '' if valid.
 */
function validateEmail(string $email): string
{
    $email = trim($email);
    if ($email === '') {
        return 'Email address is required.';
    }
    if (mb_strlen($email) > 150) {
        return 'Email address must not exceed 150 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address (e.g. name@example.com).';
    }
    return '';
}

/**
 * Validate a phone number.
 *  - Must not be empty.
 *  - Allows digits, spaces, +, -, (, ) — common international formats.
 *  - 7–20 characters after stripping formatting.
 *
 * @param  string $phone
 * @return string  Error message, or '' if valid.
 */
function validatePhone(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return 'Phone number is required.';
    }
    // Strip allowed formatting characters, count remaining digits
    $digitsOnly = preg_replace('/[\s\-\(\)\+]/', '', $phone);
    if (!ctype_digit($digitsOnly)) {
        return 'Phone number may only contain digits, spaces, +, -, and parentheses.';
    }
    $len = strlen($digitsOnly);
    if ($len < 7 || $len > 20) {
        return 'Phone number must contain 7 to 20 digits.';
    }
    return '';
}

/**
 * Validate a password for new accounts.
 *  - At least 8 characters.
 *  - At least one uppercase letter.
 *  - At least one lowercase letter.
 *  - At least one digit.
 *
 * @param  string $password
 * @return string  Error message, or '' if valid.
 */
function validatePassword(string $password): string
{
    if ($password === '') {
        return 'Password is required.';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }
    return '';
}

/**
 * Validate the chosen account role.
 * Public registration only allows 'customer' or 'staff'.
 * 'admin' accounts must be created directly in the database.
 *
 * @param  string $role
 * @return string  Error message, or '' if valid.
 */
function validateRole(string $role): string
{
    $allowed = ['customer', 'staff'];
    if (!in_array($role, $allowed, true)) {
        return 'Invalid account type. Please select Customer or Staff.';
    }
    return '';
}

/* ================================================================
   FORM REPOPULATION HELPER
   ================================================================ */

/**
 * old(string $field, string $default = '')
 *
 * Returns the previously submitted value for a form field so the
 * form can be repopulated after a failed validation without the
 * user having to retype everything.
 *
 * Usage in HTML:
 *   <input value="<?= old('name') ?>" ...>
 *
 * The submitted values are stored in $_SESSION['old_input'] by
 * the form-processing page before redirecting back.
 *
 * @param  string $field    Form field name (key in $_POST).
 * @param  string $default  Fallback value if no old input exists.
 * @return string           HTML-safe old value.
 */
function old(string $field, string $default = ''): string
{
    $value = $_SESSION['old_input'][$field] ?? $default;
    unset($_SESSION['old_input'][$field]);   // clear after one read
    return e((string) $value);
}

/**
 * storeOldInput(array $fields)
 *
 * Stores a subset of $_POST in the session so old() can read it.
 * Call this before redirecting back to the form on failure.
 * NEVER store passwords here.
 *
 * @param  array $fields  Keys to save from $_POST.
 * @return void
 */
function storeOldInput(array $fields): void
{
    $safe = [];
    foreach ($fields as $key) {
        // Explicitly skip any password fields for security
        if (str_contains(strtolower($key), 'password')) {
            continue;
        }
        $safe[$key] = $_POST[$key] ?? '';
    }
    $_SESSION['old_input'] = $safe;
}

/* ================================================================
   ACTIVITY LOG HELPER
   ================================================================ */

/**
 * logActivity(PDO $pdo, string $action, string $description = '', ?string $entityType = null, ?int $entityId = null)
 *
 * Writes one row to the activity_logs table.
 * Called after any significant action (register, login, CRUD).
 * Silently fails — logging must never break the main application.
 *
 * @param  PDO    $pdo
 * @param  string $action       Short label e.g. 'BOOK_SERVICE'
 * @param  string $description  Optional longer description
 * @param  string|null $entityType Type of entity modified
 * @param  int|null $entityId   ID of the entity modified
 * @return void
 */
function logActivity(PDO $pdo, string $action, string $description = '', ?string $entityType = null, ?int $entityId = null): void
{
    try {
        $userId    = currentUserId();
        $role      = currentUserRole() ?? 'customer'; // Default fallback
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // Sanitize IP — accept only valid IPv4/IPv6 addresses
        if ($ipAddress !== null && !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $ipAddress = null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, role, action, entity_type, entity_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $role, $action, $entityType, $entityId, $description, $ipAddress]);
    } catch (Throwable $e) {
        // Logging failure must never interrupt the user's flow
        error_log('[GarageMS] logActivity error: ' . $e->getMessage());
    }
}

/* ================================================================
   VEHICLE & PAGINATION HELPERS
   ================================================================ */

/**
 * Validate vehicle registration number.
 */
function validateVehicleNumber(string $num): string
{
    $num = trim($num);
    if ($num === '') {
        return 'Vehicle registration number is required.';
    }
    if (strlen($num) < 3 || strlen($num) > 30) {
        return 'Vehicle registration number must be between 3 and 30 characters.';
    }
    if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $num)) {
        return 'Vehicle registration number may only contain letters, numbers, spaces, and hyphens.';
    }
    return '';
}

/**
 * Validate vehicle type.
 */
function validateVehicleType(string $type): string
{
    $allowed = ['car', 'motorcycle', 'truck', 'van', 'bus', 'other'];
    if (!in_array(strtolower($type), $allowed, true)) {
        return 'Invalid vehicle type selected.';
    }
    return '';
}

/**
 * Validate vehicle brand name.
 */
function validateBrand(string $brand): string
{
    $brand = trim($brand);
    if ($brand === '') {
        return 'Brand name is required.';
    }
    if (mb_strlen($brand) > 50) {
        return 'Brand name cannot exceed 50 characters.';
    }
    return '';
}

/**
 * Validate vehicle model name.
 */
function validateModel(string $model): string
{
    $model = trim($model);
    if ($model === '') {
        return 'Model name is required.';
    }
    if (mb_strlen($model) > 50) {
        return 'Model name cannot exceed 50 characters.';
    }
    return '';
}

/**
 * Validate vehicle manufacturing year.
 */
function validateYear(int|string $year): string
{
    $y = (int)$year;
    $currentYear = (int)date('Y');
    if ($y < 1900 || $y > ($currentYear + 1)) {
        return "Year must be a 4-digit number between 1900 and " . ($currentYear + 1) . ".";
    }
    return '';
}

/**
 * Validate fuel type.
 */
function validateFuelType(string $fuel): string
{
    $allowed = ['petrol', 'diesel', 'electric', 'hybrid', 'cng', 'other'];
    if (!in_array(strtolower($fuel), $allowed, true)) {
        return 'Invalid fuel type selected.';
    }
    return '';
}

/**
 * Render Bootstrap 5 pagination HTML component.
 */
function renderPagination(int $currentPage, int $totalPages, string $baseUrl, array $queryParams = []): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav aria-label="Page navigation" class="mt-4"><ul class="pagination pagination-sm justify-content-center">';

    // Previous button
    $prevPage = max(1, $currentPage - 1);
    $prevDisabled = ($currentPage <= 1) ? ' disabled' : '';
    $queryParams['page'] = $prevPage;
    $prevUrl = $baseUrl . '?' . http_build_query($queryParams);
    $html .= '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="' . e($prevUrl) . '">&laquo; Prev</a></li>';

    // Page number links
    for ($p = 1; $p <= $totalPages; $p++) {
        $active = ($p === $currentPage) ? ' active' : '';
        $queryParams['page'] = $p;
        $url = $baseUrl . '?' . http_build_query($queryParams);
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($url) . '">' . $p . '</a></li>';
    }

    // Next button
    $nextPage = min($totalPages, $currentPage + 1);
    $nextDisabled = ($currentPage >= $totalPages) ? ' disabled' : '';
    $queryParams['page'] = $nextPage;
    $nextUrl = $baseUrl . '?' . http_build_query($queryParams);
    $html .= '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . e($nextUrl) . '">Next &raquo;</a></li>';

    $html .= '</ul></nav>';
    return $html;
}

/* ================================================================
   SERVICE VALIDATION HELPERS
   ================================================================ */

function validateServiceName(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'Service name is required.';
    }
    if (mb_strlen($name) < 2) {
        return 'Service name must be at least 2 characters.';
    }
    if (mb_strlen($name) > 100) {
        return 'Service name cannot exceed 100 characters.';
    }
    return '';
}

function validateServicePrice(float|int|string $price): string
{
    if ($price === '' || $price === null) {
        return 'Service price is required.';
    }
    if (!is_numeric($price)) {
        return 'Service price must be a valid number.';
    }
    if ((float)$price < 0) {
        return 'Service price cannot be negative.';
    }
    return '';
}

function validateEstimatedTime(float|int|string $time): string
{
    if ($time !== '' && $time !== null) {
        if (!is_numeric($time) || (float)$time < 0) {
            return 'Estimated time must be a non-negative number (hours).';
        }
    }
    return '';
}

function validateServiceStatus(string $status): string
{
    $allowed = ['active', 'inactive'];
    if (!in_array(strtolower($status), $allowed, true)) {
        return 'Invalid service status selected.';
    }
    return '';
}

/* ================================================================
   BOOKING VALIDATION HELPERS
   ================================================================ */

function validateBookingDate(string $dateStr): string
{
    $dateStr = trim($dateStr);
    if ($dateStr === '') {
        return 'Booking date is required.';
    }

    $d = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$d || $d->format('Y-m-d') !== $dateStr) {
        return 'Please enter a valid date in YYYY-MM-DD format.';
    }

    $today = new DateTime('today');
    if ($d < $today) {
        return 'Booking date cannot be in the past.';
    }

    return '';
}

function validateBookingTime(string $timeStr): string
{
    $timeStr = trim($timeStr);
    if ($timeStr === '') {
        return ''; // Time slot is optional or defaults to morning/afternoon
    }

    if (!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $timeStr)) {
        return 'Please enter a valid time (e.g. 10:00).';
    }

    return '';
}

function validateBookingStatus(string $status): string
{
    $allowed = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
    if (!in_array(strtolower(trim($status)), $allowed, true)) {
        return 'Invalid booking status.';
    }
    return '';
}

/* ================================================================
   VEHICLE UNIQUENESS HELPER  (FIX 3)
   ================================================================ */

/**
 * validateVehicleNumberUnique()
 *
 * Checks that the given (normalised) vehicle registration number is
 * not already used by any other vehicle record.
 *
 * @param  PDO    $pdo
 * @param  string $vehicleNumber  Raw value; will be normalised (uppercase+trim) inside.
 * @param  int    $excludeId      ID of the vehicle being edited (0 for add). Allows the
 *                                vehicle to keep its own registration number unchanged.
 * @return string  Error message, or '' if unique.
 */
function validateVehicleNumberUnique(PDO $pdo, string $vehicleNumber, int $excludeId = 0): string
{
    $normalised = strtoupper(trim($vehicleNumber));
    if ($normalised === '') {
        return ''; // Let validateVehicleNumber() handle the empty case
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id FROM vehicles
              WHERE vehicle_number = ?
                AND id != ?
              LIMIT 1'
        );
        $stmt->execute([$normalised, $excludeId]);
        if ($stmt->fetch()) {
            return 'A vehicle with this registration number already exists.';
        }
    } catch (PDOException $e) {
        error_log('[GarageMS] validateVehicleNumberUnique error: ' . $e->getMessage());
        return 'Unable to verify vehicle number uniqueness. Please try again.';
    }
    return '';
}


/* ================================================================
   BOOKING STATE MACHINE  (FIX 4 & 5)
   ================================================================ */

/**
 * validateBookingTransition()
 *
 * Enforces the booking lifecycle state machine:
 *
 *   pending    → confirmed, cancelled
 *   confirmed  → in_progress, cancelled
 *   in_progress → completed            (staff/admin only)
 *   completed  → [terminal]
 *   cancelled  → [terminal]
 *
 * Customer restriction:
 *   Customers may ONLY transition to 'cancelled'.
 *   Customers may ONLY cancel 'pending' or 'confirmed' bookings.
 *
 * @param  string $currentStatus  Current status read from the database (authoritative).
 * @param  string $newStatus      Requested new status from POST.
 * @param  bool   $isCustomer     True when the acting user has the 'customer' role.
 * @return string  Error message, or '' if the transition is valid.
 */
function validateBookingTransition(string $currentStatus, string $newStatus, bool $isCustomer = false): string
{
    $currentStatus = strtolower(trim($currentStatus));
    $newStatus     = strtolower(trim($newStatus));

    // No-op: staying at the same status is always allowed (e.g., page reload).
    if ($currentStatus === $newStatus) {
        return '';
    }

    // Define allowed next states for each current state (staff/admin view).
    $allowedTransitions = [
        'pending'     => ['confirmed', 'cancelled'],
        'confirmed'   => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        'completed'   => [],  // terminal — no further transitions
        'cancelled'   => [],  // terminal — no further transitions
    ];

    // Customer-specific restriction: can ONLY cancel, and ONLY from pending/confirmed.
    if ($isCustomer) {
        if ($newStatus !== 'cancelled') {
            return 'Customers are only allowed to cancel their bookings.';
        }
        if (!in_array($currentStatus, ['pending', 'confirmed'], true)) {
            return 'You cannot cancel a booking that is currently ' . str_replace('_', ' ', $currentStatus) . '.';
        }
        // Customer cancel of pending/confirmed is valid.
        return '';
    }

    // Staff/Admin transition check.
    if (!isset($allowedTransitions[$currentStatus])) {
        return 'Unknown current booking status.';
    }

    if (!in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
        if (empty($allowedTransitions[$currentStatus])) {
            return 'This booking is in a terminal state (' . str_replace('_', ' ', $currentStatus) . ') and cannot be changed.';
        }
        return 'Invalid status transition: cannot move from '
            . str_replace('_', ' ', $currentStatus)
            . ' to '
            . str_replace('_', ' ', $newStatus)
            . '.';
    }

    return '';
}
