<?php
/**
 * customer/dashboard.php
 * Garage Management System
 *
 * Dedicated dashboard for customers.
 */
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Enforce customer access
requireCustomer();

$pdo = getDBConnection();
$userId = currentUserId();

$customerId = getLoggedInCustomerId($pdo);

$totalVehicles = 0;
$activeBookingsCount = 0;
$activeService = null;
$recentBookings = [];
$vehicles = [];

if ($customerId) {
    // Stat: Total Vehicles
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vehicles WHERE customer_id = ?');
    $stmt->execute([$customerId]);
    $totalVehicles = (int)$stmt->fetchColumn();

    // Active Services (All pending, confirmed, or in_progress bookings)
    $stmt = $pdo->prepare("
        SELECT b.*, v.vehicle_number, v.brand, v.model, v.fuel_type, s.service_name, s.price, s.estimated_time,
               u.name AS staff_name
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        JOIN services s ON b.service_id = s.id
        LEFT JOIN users u ON b.staff_id = u.id
        WHERE b.customer_id = ? AND b.status IN ('pending', 'confirmed', 'in_progress')
        ORDER BY b.id DESC
    ");
    $stmt->execute([$customerId]);
    $activeServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activeService = $activeServices[0] ?? null;

    // Stat: Active Bookings count
    $activeBookingsCount = count($activeServices);

    // Recent Bookings (Limit 5)
    $stmt = $pdo->prepare("
        SELECT b.*, v.vehicle_number, v.brand, v.model, s.service_name, s.price 
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        JOIN services s ON b.service_id = s.id
        WHERE b.customer_id = ?
        ORDER BY b.id DESC
        LIMIT 5
    ");
    $stmt->execute([$customerId]);
    $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vehicles (Limit 4)
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY id DESC LIMIT 4");
    $stmt->execute([$customerId]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── UI Setup ──────────────────────────────────────────────────
$userName  = (string)($_SESSION['user_name']  ?? 'Customer');
$userEmail = (string)($_SESSION['user_email'] ?? '');

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="gms-page-header">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="mb-1 text-white fw-bold">
                    Welcome back, <span class="text-warning"><?= e($userName) ?></span> 👋
                </h1>
                <p class="text-light opacity-75 small mb-0">Take care of your vehicle with professional garage services & live tracking.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>bookings/create.php" class="btn btn-gms-primary px-4 py-2 fw-bold shadow">
                    <i class="bi bi-calendar-plus-fill me-2"></i>+ Book a Service
                </a>
            </div>
        </div>
    </div>
</div>

<div class="gms-page-wrapper">
    <div class="container-fluid px-4 py-4">

        <?php showFlash(); ?>

        <!-- ══════════════════════════════════════════════════════
             ACTIVE SERVICES TRACKER (Scrollable for Multi-Service)
             ══════════════════════════════════════════════════════ -->
        <?php if (!empty($activeServices)): ?>
            <div class="card gms-panel border-warning mb-4 shadow-lg">
                <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="gms-live-pulse-dot"></span>
                        <h5 class="card-title mb-0 fw-bold text-white text-uppercase letter-spacing-1">
                            <?= count($activeServices) > 1 ? 'Active Services in Progress (' . count($activeServices) . ')' : 'Active Service in Progress' ?>
                        </h5>
                    </div>
                    <div>
                        <span class="badge bg-warning text-dark font-monospace fw-bold px-3 py-1.5" style="font-size:0.8rem;">
                            <?= count($activeServices) ?> ACTIVE <?= count($activeServices) > 1 ? 'REQUESTS' : 'REQUEST' ?>
                        </span>
                    </div>
                </div>

                <!-- Scrollable Container for Active Services -->
                <div class="<?= count($activeServices) > 1 ? 'gms-active-services-scroll' : '' ?>">
                    <?php foreach ($activeServices as $idx => $activeService): ?>
                        <div class="card-body p-4 <?= $idx > 0 ? 'border-top border-secondary' : '' ?>" style="background:linear-gradient(145deg, #182235, #121824);">
                            
                            <!-- Header Info for Multiple Items -->
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom border-secondary border-opacity-50">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-dark border border-warning text-warning font-monospace fw-bold px-2.5 py-1">
                                        #BKG-<?= (int)$activeService['id'] ?>
                                    </span>
                                    <span class="text-white fw-bold fs-6"><?= e($activeService['brand']) ?> <?= e($activeService['model']) ?></span>
                                    <span class="badge bg-dark border border-secondary text-info font-monospace small"><?= e($activeService['vehicle_number']) ?></span>
                                </div>
                                <?php
                                    $statusBadge = match($activeService['status']) {
                                        'pending'     => 'bg-warning text-dark border border-warning',
                                        'confirmed'   => 'bg-primary text-white border border-primary',
                                        'in_progress' => 'bg-info text-dark border border-info',
                                        'completed'   => 'bg-success text-white border border-success',
                                        default       => 'bg-secondary text-white'
                                    };
                                    $statusLabel = match($activeService['status']) {
                                        'pending'     => '⏳ PENDING — WAITING FOR GARAGE',
                                        'confirmed'   => '✓ SERVICE CONFIRMED',
                                        'in_progress' => '🔧 SERVICE IN PROGRESS',
                                        'completed'   => '✓ COMPLETED',
                                        default       => strtoupper(str_replace('_', ' ', $activeService['status']))
                                    };
                                ?>
                                <span class="badge <?= $statusBadge ?> font-monospace fw-bold px-3 py-1.5 fs-7">
                                    <?= $statusLabel ?>
                                </span>
                            </div>

                            <!-- Service & Vehicle Meta Row -->
                            <div class="row g-3 align-items-center mb-4 pb-3 border-bottom border-secondary">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="gms-service-icon-box">
                                            <i class="bi bi-car-front-fill text-warning fs-3"></i>
                                        </div>
                                        <div>
                                            <span class="text-muted small text-uppercase d-block" style="font-size:0.72rem;">Vehicle Details</span>
                                            <h6 class="mb-0 fw-bold text-white"><?= e($activeService['brand']) ?> <?= e($activeService['model']) ?></h6>
                                            <div class="small text-slate-300" style="font-size:0.8rem; color:#cbd5e1 !important;">
                                                Fuel: <span class="text-capitalize text-warning"><?= e($activeService['fuel_type']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <span class="text-muted small text-uppercase d-block" style="font-size:0.72rem;">Service Package</span>
                                    <h6 class="mb-0 fw-bold text-white"><?= e($activeService['service_name']) ?></h6>
                                    <span class="text-warning font-monospace fw-bold">₹<?= number_format($activeService['price'], 2) ?></span>
                                    <?php if ($activeService['estimated_time']): ?>
                                        <span class="text-slate-300 small ms-1" style="color:#cbd5e1 !important;">(~<?= e($activeService['estimated_time']) ?> hrs)</span>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-4">
                                    <span class="text-muted small text-uppercase d-block" style="font-size:0.72rem;">Scheduled Appointment</span>
                                    <div class="text-white fw-semibold">
                                        <i class="bi bi-calendar3 text-warning me-1"></i><?= formatDate($activeService['booking_date']) ?>
                                    </div>
                                    <div class="text-slate-300 small" style="color:#cbd5e1 !important;">
                                        <i class="bi bi-clock me-1 text-info"></i><?= e($activeService['booking_time'] ?: '10:00 AM') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Service Location & Assigned Staff -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-7">
                                    <div class="p-3 rounded border border-secondary h-100" style="background:var(--clr-dark-4);">
                                        <div class="d-flex align-items-center gap-2 mb-1 text-warning small fw-bold">
                                            <i class="bi bi-geo-alt-fill"></i> Service Location
                                        </div>
                                        <div class="text-white fw-normal small">
                                            <?= e($activeService['service_address'] ?: 'Garage Service Center') ?><?= $activeService['area'] ? ', ' . e($activeService['area']) : '' ?><?= $activeService['city'] ? ', ' . e($activeService['city']) : '' ?><?= $activeService['state'] ? ', ' . e($activeService['state']) : '' ?><?= $activeService['pincode'] ? ' - ' . e($activeService['pincode']) : '' ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <div class="p-3 rounded border border-secondary h-100" style="background:var(--clr-dark-4);">
                                        <div class="d-flex align-items-center gap-2 mb-1 text-info small fw-bold">
                                            <i class="bi bi-person-badge-fill"></i> Assigned Staff / Technician
                                        </div>
                                        <?php if (!empty($activeService['staff_name'])): ?>
                                            <div class="text-white fw-bold fs-6">
                                                <?= e($activeService['staff_name']) ?>
                                            </div>
                                            <div class="text-success small"><i class="bi bi-patch-check-fill me-1"></i>GarageMS Technician Assigned</div>
                                        <?php else: ?>
                                            <div class="text-slate-300 small" style="color:#cbd5e1 !important;">
                                                <i class="bi bi-hourglass-split text-warning me-1"></i> Waiting for technician assignment
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Interactive Multi-Step Timeline ── -->
                            <div class="mt-2 pt-2">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted small fw-bold text-uppercase">Live Service Progress</span>
                                    <a href="<?= BASE_URL ?>bookings/view.php?id=<?= (int)$activeService['id'] ?>" class="btn btn-sm btn-outline-warning fw-semibold">
                                        View Full Status Details <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>

                                <?php
                                    $status = $activeService['status'];
                                    $s1 = true;
                                    $s2 = in_array($status, ['confirmed', 'in_progress', 'completed']);
                                    $s3 = $s2 && !empty($activeService['staff_id']);
                                    $s4 = in_array($status, ['in_progress', 'completed']);
                                    $s5 = ($status === 'completed');
                                ?>

                                <div class="gms-timeline-tracker">
                                    <!-- Step 1 -->
                                    <div class="timeline-step <?= $s1 ? 'completed' : '' ?>">
                                        <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                                        <div class="step-label">Request Submitted</div>
                                    </div>
                                    <div class="step-line <?= $s2 ? 'active' : '' ?>"></div>

                                    <!-- Step 2 -->
                                    <div class="timeline-step <?= $s2 ? 'completed' : ($status === 'pending' ? 'current' : '') ?>">
                                        <div class="step-circle"><?= $s2 ? '<i class="bi bi-check-lg"></i>' : '2' ?></div>
                                        <div class="step-label"><?= $s2 ? 'Garage Accepted' : 'Waiting for Garage' ?></div>
                                    </div>
                                    <div class="step-line <?= $s3 ? 'active' : '' ?>"></div>

                                    <!-- Step 3 -->
                                    <div class="timeline-step <?= $s3 ? 'completed' : ($s2 && !$s4 ? 'current' : '') ?>">
                                        <div class="step-circle"><?= $s3 ? '<i class="bi bi-check-lg"></i>' : '3' ?></div>
                                        <div class="step-label"><?= $s3 ? 'Staff Assigned' : 'Technician Assignment' ?></div>
                                    </div>
                                    <div class="step-line <?= $s4 ? 'active' : '' ?>"></div>

                                    <!-- Step 4 -->
                                    <div class="timeline-step <?= $s4 ? ($s5 ? 'completed' : 'current') : '' ?>">
                                        <div class="step-circle"><?= $s5 ? '<i class="bi bi-check-lg"></i>' : '4' ?></div>
                                        <div class="step-label">Service In Progress</div>
                                    </div>
                                    <div class="step-line <?= $s5 ? 'active' : '' ?>"></div>

                                    <!-- Step 5 -->
                                    <div class="timeline-step <?= $s5 ? 'completed' : '' ?>">
                                        <div class="step-circle"><?= $s5 ? '<i class="bi bi-check-lg"></i>' : '5' ?></div>
                                        <div class="step-label">Completed</div>
                                    </div>
                                </div>

                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════
             STATS & QUICK NAVIGATION CARDS
             ══════════════════════════════════════════════════════ -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-md-3">
                <a href="<?= BASE_URL ?>bookings/create.php" class="gms-quick-action border-warning">
                    <i class="bi bi-calendar-plus text-warning fs-2"></i>
                    <span class="fw-bold text-white">Book New Service</span>
                    <small class="text-muted">Instant scheduling</small>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= BASE_URL ?>vehicles/list.php" class="gms-quick-action">
                    <i class="bi bi-car-front text-info fs-2"></i>
                    <span class="fw-bold text-white">My Vehicles (<?= $totalVehicles ?>)</span>
                    <small class="text-muted">Manage car records</small>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= BASE_URL ?>bookings/list.php" class="gms-quick-action">
                    <i class="bi bi-calendar-check text-success fs-2"></i>
                    <span class="fw-bold text-white">My Bookings (<?= $activeBookingsCount ?> Active)</span>
                    <small class="text-muted">View all appointments</small>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= BASE_URL ?>bookings/history.php" class="gms-quick-action">
                    <i class="bi bi-clock-history text-secondary fs-2"></i>
                    <span class="fw-bold text-white">Service History</span>
                    <small class="text-muted">Completed logs</small>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- ── My Vehicles List ── -->
            <div class="col-lg-5">
                <div class="card gms-panel h-100 shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-white">
                            <i class="bi bi-car-front-fill text-info me-2"></i>My Vehicles
                        </h5>
                        <a href="<?= BASE_URL ?>vehicles/list.php" class="btn btn-sm btn-outline-info fw-bold">
                            <i class="bi bi-plus-lg me-1"></i> Add / View All
                        </a>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($vehicles)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-car-front fs-1 d-block mb-2 opacity-50"></i>
                                <p class="mb-2 text-white fw-semibold">No vehicles registered yet.</p>
                                <a href="<?= BASE_URL ?>vehicles/list.php" class="btn btn-sm btn-warning fw-bold text-dark">Register Your Car</a>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($vehicles as $v): ?>
                                    <div class="p-3 rounded border border-secondary d-flex justify-content-between align-items-center" style="background:var(--clr-dark-4);">
                                        <div>
                                            <div class="fw-bold text-white fs-6"><?= e($v['brand']) ?> <?= e($v['model']) ?> <span class="text-slate-300 small" style="color:#cbd5e1 !important;">(<?= e($v['year']) ?>)</span></div>
                                            <div class="small text-slate-300 mt-1" style="color:#cbd5e1 !important;">
                                                <span class="badge bg-dark border border-warning text-warning font-monospace me-1"><?= e($v['vehicle_number']) ?></span>
                                                • <span class="text-capitalize text-white fw-medium"><?= e($v['fuel_type']) ?></span>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <a href="<?= BASE_URL ?>bookings/create.php?vehicle_id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-warning fw-bold text-dark" title="Book Service for this car">
                                                <i class="bi bi-calendar-plus me-1"></i> Book
                                            </a>
                                            <a href="<?= BASE_URL ?>vehicles/view.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Recent Service Bookings ── -->
            <div class="col-lg-7">
                <div class="card gms-panel h-100 shadow-sm">
                    <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-white">
                            <i class="bi bi-clock-history text-warning me-2"></i>Recent Service Bookings
                        </h5>
                        <a href="<?= BASE_URL ?>bookings/list.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentBookings)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-50"></i>
                                <p class="mb-0">You have no recent booking requests.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0">
                                    <thead class="text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-3">ID & Date</th>
                                            <th>Vehicle</th>
                                            <th>Service</th>
                                            <th class="text-center">Status</th>
                                            <th class="pe-3 text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentBookings as $b): ?>
                                            <?php
                                                $badgeCls = match($b['status']) {
                                                    'pending'     => 'bg-warning text-dark',
                                                    'confirmed'   => 'bg-info text-dark',
                                                    'in_progress' => 'bg-primary text-white',
                                                    'completed'   => 'bg-success text-white',
                                                    'cancelled'   => 'bg-danger text-white',
                                                    default       => 'bg-secondary'
                                                };
                                            ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <a href="<?= BASE_URL ?>bookings/view.php?id=<?= (int)$b['id'] ?>" class="text-warning font-monospace fw-bold text-decoration-none">
                                                        #BKG-<?= (int)$b['id'] ?>
                                                    </a>
                                                    <div class="text-muted small"><?= formatDate($b['booking_date']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-white fw-semibold small"><?= e($b['brand']) ?> <?= e($b['model']) ?></div>
                                                    <div class="font-monospace text-muted small"><?= e($b['vehicle_number']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="text-light small"><?= e($b['service_name']) ?></div>
                                                    <div class="text-warning small font-monospace">₹<?= number_format($b['price'], 2) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $badgeCls ?> px-2 py-1 rounded-pill text-uppercase" style="font-size:0.7rem;">
                                                        <?= str_replace('_', ' ', $b['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="pe-3 text-end">
                                                    <a href="<?= BASE_URL ?>bookings/view.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
/* ── Live Pulse Dot ── */
.gms-live-pulse-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #22c55e;
    box-shadow: 0 0 10px #22c55e;
    animation: livePulse 1.8s infinite;
}
@keyframes livePulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.gms-service-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: var(--clr-dark-4);
    border: 1px solid var(--clr-border);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* ── Multi-Step Progress Tracker ── */
.gms-timeline-tracker {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    padding: 1.5rem 0.5rem 0.5rem;
}
.timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
}
.step-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--clr-dark-4);
    border: 2px solid var(--clr-border);
    color: var(--clr-text-muted);
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}
.step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--clr-text-muted);
    max-width: 110px;
    line-height: 1.2;
}
.timeline-step.completed .step-circle {
    background: #22c55e;
    border-color: #22c55e;
    color: #fff;
    box-shadow: 0 0 14px rgba(34, 197, 94, 0.4);
}
.timeline-step.completed .step-label {
    color: #22c55e;
}
.timeline-step.current .step-circle {
    background: var(--clr-accent);
    border-color: #fb923c;
    color: #fff;
    box-shadow: 0 0 16px rgba(249, 115, 22, 0.5);
    animation: livePulse 1.8s infinite;
}
.timeline-step.current .step-label {
    color: #fb923c;
}
.step-line {
    flex: 1;
    height: 3px;
    background: var(--clr-dark-4);
    margin-top: -1.6rem;
    position: relative;
    z-index: 1;
}
.step-line.active {
    background: #22c55e;
}

/* ── Scrollable Active Services Container for Multiple Requests ── */
.gms-active-services-scroll {
    max-height: 560px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
}

/* Custom Scrollbar for Active Services */
.gms-active-services-scroll::-webkit-scrollbar {
    width: 7px;
}
.gms-active-services-scroll::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.75);
    border-radius: 4px;
}
.gms-active-services-scroll::-webkit-scrollbar-thumb {
    background: rgba(249, 115, 22, 0.45);
    border-radius: 4px;
    border: 1px solid rgba(249, 115, 22, 0.2);
}
.gms-active-services-scroll::-webkit-scrollbar-thumb:hover {
    background: #f97316;
}

@media (max-width: 767.98px) {
    .gms-active-services-scroll {
        max-height: 480px;
    }
    .gms-timeline-tracker {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.25rem;
        padding: 0;
    }
    .timeline-step {
        flex-direction: row;
        gap: 1rem;
        text-align: left;
    }
    .step-line {
        display: none;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
