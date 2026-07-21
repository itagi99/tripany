<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

$id = $_GET['id'] ?? 0;

// Handle driver assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'assign_driver') {
        $driverId = $_POST['driver_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM drivers WHERE id=?");
        $stmt->execute([$driverId]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($driver) {
            $pdo->prepare("UPDATE bookings SET driver_id=?, driver_name=?, driver_phone=?, status='confirmed' WHERE id=?")
                ->execute([$driverId, $driver['name'], $driver['phone'], $id]);
            $pdo->prepare("UPDATE drivers SET status='busy' WHERE id=?")->execute([$driverId]);
            // Send notification to user
            $b = $pdo->prepare("SELECT user_id, booking_ref FROM bookings WHERE id=?");
            $b->execute([$id]);
            $bk = $b->fetch(PDO::FETCH_ASSOC);
            if ($bk) {
                $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)")
                    ->execute([$bk['user_id'], 'Driver Assigned 🚗', 'Driver ' . $driver['name'] . ' has been assigned to your booking ' . $bk['booking_ref'] . '. Track now!', 'booking']);
            }
            header("Location: booking_detail.php?id=$id&success=assigned");
            exit;
        }
    }
    
    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, ['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'])) {
            $pdo->prepare("UPDATE bookings SET status=?, completed_at=CASE WHEN ?='completed' THEN CURRENT_TIMESTAMP ELSE completed_at END WHERE id=?")
                ->execute([$newStatus, $newStatus, $id]);
            // Send notification to user
            $b = $pdo->prepare("SELECT user_id, booking_ref FROM bookings WHERE id=?");
            $b->execute([$id]);
            $bk = $b->fetch(PDO::FETCH_ASSOC);
            if ($bk) {
                $statusLabels = ['confirmed'=>'Booking Confirmed ✅', 'ongoing'=>'Trip Started 🚀', 'completed'=>'Trip Completed 🎉', 'cancelled'=>'Booking Cancelled ❌'];
                $statusMsgs = ['confirmed'=>'Your booking ' . $bk['booking_ref'] . ' is confirmed!', 'ongoing'=>'Your trip ' . $bk['booking_ref'] . ' has started. Have a safe journey!', 'completed'=>'Your trip ' . $bk['booking_ref'] . ' is completed. Rate your experience!', 'cancelled'=>'Your booking ' . $bk['booking_ref'] . ' has been cancelled.'];
                $title = $statusLabels[$newStatus] ?? 'Booking Updated';
                $msg = $statusMsgs[$newStatus] ?? 'Your booking status has been updated to ' . $newStatus;
                $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)")
                    ->execute([$bk['user_id'], $title, $msg, 'booking']);
            }
            header("Location: booking_detail.php?id=$id&success=status_updated");
            exit;
        }
    }
    
    if ($action === 'update_fare') {
        $pdo->prepare("UPDATE bookings SET base_fare=?, tax=?, discount=?, total_fare=? WHERE id=?")
            ->execute([$_POST['base_fare'] ?? 0, $_POST['tax'] ?? 0, $_POST['discount'] ?? 0, $_POST['total_fare'] ?? 0, $id]);
        header("Location: booking_detail.php?id=$id&success=fare_updated");
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone, u.avatar as user_avatar,
           v.name as vehicle_name, v.brand as vehicle_brand, v.model as vehicle_model, v.type as vehicle_type,
           v.image as vehicle_image, v.fuel_type, v.transmission, v.seats, v.price_per_km, v.price_per_day,
           v.rating as vehicle_rating
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.id=?
");
$stmt->execute([$id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$booking) { header("Location: bookings.php"); exit; }

$drivers = $pdo->query("SELECT * FROM drivers ORDER BY status DESC, rating DESC")->fetchAll(PDO::FETCH_ASSOC);

$selectedDriver = null;
if ($booking['driver_id']) {
    $d = $pdo->prepare("SELECT * FROM drivers WHERE id=?");
    $d->execute([$booking['driver_id']]);
    $selectedDriver = $d->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Booking #' . ($booking['booking_ref'] ?? $booking['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — <?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
    .info-grid { display:grid;grid-template-columns:1fr 1fr;gap:0.75rem; }
    .info-item { display:flex;flex-direction:column;gap:0.25rem; }
    .info-label { font-size:0.6875rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px; }
    .info-value { font-size:0.9375rem;font-weight:600;color:var(--text-primary); }
    .timeline { position:relative;padding-left:1.5rem; }
    .timeline::before { content:'';position:absolute;left:6px;top:8px;bottom:8px;width:2px;background:var(--border); }
    .timeline-item { position:relative;padding-bottom:1.25rem; }
    .timeline-item::before { content:'';position:absolute;left:-1.5rem;top:4px;width:14px;height:14px;border-radius:50%;border:2px solid var(--border);background:var(--bg-card); }
    .timeline-item.active::before { background:var(--primary);border-color:var(--primary);box-shadow:0 0 0 4px rgba(37,99,235,0.2); }
    .timeline-item .tl-title { font-size:0.875rem;font-weight:600;color:var(--text-primary); }
    .timeline-item .tl-desc { font-size:0.75rem;color:var(--text-muted); }
    .route-line { position:relative;padding-left:1.5rem;min-height:60px; }
    .route-line::before { content:'';position:absolute;left:7px;top:24px;bottom:24px;width:2px;background:linear-gradient(to bottom,var(--success),var(--danger)); }
    .route-dot { position:absolute;left:0;top:0;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center; }
    .route-dot.start { background:var(--success); }
    .route-dot.end { background:var(--danger);bottom:0;top:auto; }
    .action-btn-group { display:flex;gap:0.5rem;flex-wrap:wrap; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'includes/navbar.php'; ?>
        <div class="content-wrapper">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i><?= ucfirst(str_replace('_',' ',$_GET['success'])) ?> successfully!</div>
            <?php endif; ?>

            <div class="page-header">
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <a href="bookings.php" class="btn-premium btn-ghost btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h2 class="page-title" style="margin:0;">Booking #<?= e($booking['booking_ref'] ?? $booking['id']) ?></h2>
                        <p class="page-subtitle" style="margin:0;">Created <?= e($booking['created_at']) ?></p>
                    </div>
                </div>
                <span class="badge-premium badge-<?= $booking['status'] ?>" style="font-size:0.8125rem;padding:0.5rem 1rem;"><?= ucfirst($booking['status']) ?></span>
            </div>

            <!-- Main Grid -->
            <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:1rem;">

                <!-- LEFT: Customer & Route -->
                <div style="display:flex;flex-direction:column;gap:1rem;">

                    <!-- Customer Card -->
                    <div class="card-premium">
                        <div class="card-header-premium"><h5><i class="bi bi-person"></i> Customer Details</h5></div>
                        <div class="card-body-premium">
                            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
                                <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:1.25rem;flex-shrink:0;">
                                    <?= strtoupper(substr($booking['user_name'] ?? '?',0,1)) ?>
                                </div>
                                <div>
                                    <div style="font-size:1.125rem;font-weight:700;color:var(--text-primary);"><?= e($booking['user_name'] ?? 'Unknown') ?></div>
                                    <div style="font-size:0.8125rem;color:var(--text-muted);"><?= e($booking['user_email'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="info-grid">
                                <div class="info-item"><span class="info-label">Phone</span><span class="info-value"><i class="bi bi-phone" style="font-size:0.75rem;"></i> <?= e($booking['user_phone'] ?? 'N/A') ?></span></div>
                                <div class="info-item"><span class="info-label">User ID</span><span class="info-value">#<?= $booking['user_id'] ?></span></div>
                                <div class="info-item"><span class="info-label">Payment Method</span><span class="info-value"><?= e(ucfirst($booking['payment_method'] ?? 'N/A')) ?></span></div>
                                <div class="info-item"><span class="info-label">Payment Status</span><span class="info-value"><span class="badge-premium badge-<?= $booking['payment_status'] === 'paid' ? 'active' : 'warning' ?>"><?= ucfirst($booking['payment_status'] ?? 'pending') ?></span></span></div>
                            </div>
                            <?php if (!empty($booking['booking_notes'])): ?>
                            <div style="margin-top:0.75rem;padding:0.75rem;background:rgba(245,158,11,0.05);border:1px solid rgba(245,158,11,0.15);border-radius:12px;">
                                <div style="font-size:0.6875rem;font-weight:700;color:var(--warning);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.25rem;"><i class="bi bi-sticky"></i> Customer Notes</div>
                                <div style="font-size:0.875rem;color:var(--text-primary);"><?= e($booking['booking_notes']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Route Card -->
                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-route"></i> Trip Route</h5>
                            <span class="badge-premium badge-<?= $booking['trip_type'] === 'hourly' ? 'warning' : ($booking['trip_type'] === 'round_trip' ? 'active' : 'primary') ?>">
                                <?= ucfirst(str_replace('_',' ',$booking['trip_type'] ?? 'One Way')) ?>
                            </span>
                        </div>
                        <div class="card-body-premium">
                            <div class="route-line">
                                <div class="route-dot start"><i class="bi bi-circle-fill" style="font-size:0.375rem;color:white;"></i></div>
                                <div style="margin-bottom:1.25rem;">
                                    <div class="info-value" style="font-size:1rem;"><?= e($booking['pickup_location'] ?? 'Not specified') ?></div>
                                    <div class="info-label">
                                        <?php if ($booking['pickup_lat'] && $booking['pickup_lng']): ?>
                                            <?= number_format($booking['pickup_lat'],4) ?>, <?= number_format($booking['pickup_lng'],4) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($booking['pickup_city'])): ?> · <?= e($booking['pickup_city']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <?php
                                $stops = [];
                                if (!empty($booking['stops'])) {
                                    $stops = json_decode($booking['stops'], true) ?? [];
                                    foreach ($stops as $i => $stop):
                                ?>
                                <div style="position:relative;padding-left:1.5rem;margin-bottom:1rem;">
                                    <div style="position:absolute;left:4px;top:4px;width:10px;height:10px;border-radius:50%;background:var(--warning);border:2px solid rgba(245,158,11,0.3);"></div>
                                    <div class="info-value" style="font-size:0.875rem;"><?= e($stop) ?></div>
                                    <div class="info-label">Stop <?= $i+1 ?></div>
                                </div>
                                <?php endforeach; } ?>
                                <div class="route-dot end"><i class="bi bi-square-fill" style="font-size:0.375rem;color:white;"></i></div>
                                <div>
                                    <div class="info-value" style="font-size:1rem;"><?= e($booking['drop_location'] ?? 'Not specified') ?></div>
                                    <div class="info-label">
                                        <?php if ($booking['drop_lat'] && $booking['drop_lng']): ?>
                                            <?= number_format($booking['drop_lat'],4) ?>, <?= number_format($booking['drop_lng'],4) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($booking['drop_city'])): ?> · <?= e($booking['drop_city']) ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex;gap:1rem;margin-top:0.5rem;padding-top:0.75rem;border-top:1px solid var(--border);flex-wrap:wrap;">
                                <div><span class="info-label">Distance</span><div class="info-value"><?= $booking['distance_km'] ?> km</div></div>
                                <div><span class="info-label">Pickup Time</span><div class="info-value" style="font-size:0.8125rem;"><?= e(!empty($booking['pickup_time']) ? date('d M Y, h:i A', strtotime($booking['pickup_time'])) : ($booking['pickup_date'] ?? 'N/A')) ?></div></div>
                                <?php if (!empty($booking['return_time'])): ?>
                                <div><span class="info-label">Return Time</span><div class="info-value" style="font-size:0.8125rem;"><?= e(date('d M Y, h:i A', strtotime($booking['return_time']))) ?></div></div>
                                <?php endif; ?>
                            </div>
                            <!-- Navigation Link -->
                            <?php
                            $navUrl = '';
                            if (!empty($booking['pickup_lat']) && !empty($booking['pickup_lng']) && !empty($booking['drop_lat']) && !empty($booking['drop_lng'])) {
                                $navUrl = 'https://www.google.com/maps/dir/' . $booking['pickup_lat'] . ',' . $booking['pickup_lng'] . '/' . $booking['drop_lat'] . ',' . $booking['drop_lng'];
                            }
                            ?>
                            <div style="margin-top:0.75rem;">
                                <a href="<?= e($navUrl) ?>" target="_blank" class="btn-premium btn-primary btn-sm btn-block" style="justify-content:center;gap:0.5rem;">
                                    <i class="bi bi-map"></i> Open in Google Maps
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Fare Card -->
                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-currency-rupee"></i> Fare Breakdown</h5>
                            <button class="btn-premium btn-ghost btn-sm" onclick="document.getElementById('fareModal').style.display='flex'"><i class="bi bi-pencil"></i> Edit</button>
                        </div>
                        <div class="card-body-premium">
                            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border);">
                                    <span style="color:var(--text-muted);font-size:0.8125rem;">Base Fare (<?= $booking['distance_km'] ?> km × ₹<?= number_format($booking['base_fare'] && $booking['distance_km'] ? $booking['base_fare']/$booking['distance_km'] : 0,2) ?>)</span>
                                    <span style="font-weight:600;color:var(--text-primary);">₹<?= number_format($booking['base_fare'],2) ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border);">
                                    <span style="color:var(--text-muted);font-size:0.8125rem;">Tax (GST)</span>
                                    <span style="font-weight:600;color:var(--text-primary);">₹<?= number_format($booking['tax'],2) ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border);">
                                    <span style="color:var(--text-muted);font-size:0.8125rem;">Discount</span>
                                    <span style="font-weight:600;color:var(--success);">−₹<?= number_format($booking['discount'],2) ?></span>
                                </div>
                                <?php if ($booking['coupon_id']): ?>
                                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border);">
                                    <span style="color:var(--text-muted);font-size:0.8125rem;">Coupon Applied</span>
                                    <span class="badge-premium badge-success">#<?= $booking['coupon_id'] ?></span>
                                </div>
                                <?php endif; ?>
                                <div style="display:flex;justify-content:space-between;padding:1rem 0 0;">
                                    <span style="font-weight:800;font-size:1.125rem;color:var(--text-primary);">Total Fare</span>
                                    <span style="font-weight:900;font-size:1.375rem;color:var(--primary);">₹<?= number_format($booking['total_fare'],2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Vehicle, Driver, Status -->
                <div style="display:flex;flex-direction:column;gap:1rem;">

                    <!-- Vehicle Card -->
                    <div class="card-premium">
                        <div class="card-header-premium"><h5><i class="bi bi-car-front"></i> Vehicle</h5></div>
                        <div class="card-body-premium">
                            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
                                <div style="width:72px;height:54px;border-radius:12px;background:linear-gradient(135deg,rgba(37,99,235,0.1),rgba(6,182,212,0.1));display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid var(--border);">
                                    <?php if ($booking['vehicle_image']): ?>
                                        <img src="<?= e($booking['vehicle_image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">
                                    <?php else: ?>
                                        <i class="bi bi-car-front" style="font-size:1.5rem;color:var(--primary);"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-size:1.125rem;font-weight:700;color:var(--text-primary);"><?= e($booking['vehicle_name'] ?? 'Unknown') ?></div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= e($booking['vehicle_brand'] ?? '') ?> · <?= e($booking['vehicle_model'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="info-grid">
                                <div class="info-item"><span class="info-label">Type</span><span class="info-value"><?= e($booking['vehicle_type'] ?? 'N/A') ?></span></div>
                                <div class="info-item"><span class="info-label">Seats</span><span class="info-value"><?= e($booking['seats'] ?? 'N/A') ?></span></div>
                                <div class="info-item"><span class="info-label">Fuel</span><span class="info-value"><?= e($booking['fuel_type'] ?? 'N/A') ?></span></div>
                                <div class="info-item"><span class="info-label">Transmission</span><span class="info-value"><?= e($booking['transmission'] ?? 'N/A') ?></span></div>
                                <div class="info-item"><span class="info-label">₹/km</span><span class="info-value">₹<?= number_format($booking['price_per_km'] ?? 0,2) ?></span></div>
                                <div class="info-item"><span class="info-label">₹/day</span><span class="info-value">₹<?= number_format($booking['price_per_day'] ?? 0) ?></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Driver Assignment Card -->
                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-person-badge"></i> Driver Assignment</h5>
                            <?php if (empty($booking['driver_id'])): ?>
                                <span class="badge-premium badge-warning">Awaiting Assignment</span>
                            <?php elseif ($booking['status'] === 'confirmed' || $booking['status'] === 'completed'): ?>
                                <span class="badge-premium badge-active">Assigned</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body-premium">
                            <?php if ($selectedDriver): ?>
                                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;padding:0.75rem;background:rgba(34,197,94,0.05);border:1px solid rgba(34,197,94,0.15);border-radius:14px;">
                                    <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--success),var(--secondary));display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:1rem;"><?= strtoupper(substr($selectedDriver['name'],0,1)) ?></div>
                                    <div style="flex:1;">
                                        <div style="font-weight:700;font-size:0.9375rem;color:var(--text-primary);"><?= e($selectedDriver['name']) ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">
                                            <i class="bi bi-phone"></i> <?= e($selectedDriver['phone']) ?> ·
                                            <i class="bi bi-star-fill" style="color:var(--warning);font-size:0.5rem;"></i> <?= number_format($selectedDriver['rating'],1) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge-premium badge-<?= $selectedDriver['status'] ?>"><?= ucfirst($selectedDriver['status']) ?></span>
                                        <div style="font-size:0.625rem;color:var(--text-muted);margin-top:0.25rem;"><?= e($selectedDriver['vehicle_number'] ?? '') ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="text-align:center;padding:0.75rem;background:rgba(245,158,11,0.05);border-radius:14px;margin-bottom:1rem;">
                                    <i class="bi bi-hourglass-split" style="font-size:1.5rem;color:var(--warning);margin-bottom:0.5rem;display:block;"></i>
                                    <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);">No Driver Assigned</div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">Select a driver below and confirm</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($booking['status'] === 'pending' || ($booking['status'] === 'confirmed' && empty($booking['driver_id']))): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_driver">
                                <div class="form-group">
                                    <label class="form-label">Select Driver</label>
                                    <select name="driver_id" class="form-select" required>
                                        <option value="">Choose available driver...</option>
                                        <?php foreach ($drivers as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= $booking['driver_id'] == $d['id'] ? 'selected' : '' ?>>
                                                <?= e($d['name']) ?> — <?= e($d['vehicle_type'] ?? '') ?> (<?= e($d['vehicle_number'] ?? '') ?>) — ⭐<?= $d['rating'] ?> — <?= ucfirst($d['status']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn-premium btn-primary btn-block" style="justify-content:center;">
                                    <i class="bi bi-check2-circle"></i> Confirm & Assign Driver
                                </button>
                                <div style="font-size:0.6875rem;color:var(--text-muted);text-align:center;margin-top:0.5rem;">
                                    Customer will be notified once assigned
                                </div>
                            </form>
                            <?php endif; ?>

                            <?php if ($booking['status'] === 'confirmed' && !empty($booking['driver_id'])): ?>
                            <div style="display:flex;gap:0.5rem;">
                                <a href="tel:<?= e($selectedDriver['phone'] ?? '') ?>" class="btn-premium btn-ghost btn-sm" style="flex:1;justify-content:center;"><i class="bi bi-telephone"></i> Call Driver</a>
                                <form method="POST" style="flex:1;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn-premium btn-success btn-sm btn-block" style="justify-content:center;"><i class="bi bi-check-lg"></i> Complete</button>
                                </form>
                                <form method="POST" style="flex:1;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn-premium btn-danger btn-sm btn-block" style="justify-content:center;" onclick="return confirm('Cancel this booking?')"><i class="bi bi-x-lg"></i> Cancel</button>
                                </form>
                            </div>
                            <?php endif; ?>

                            <?php if ($booking['status'] === 'pending' || ($booking['status'] === 'confirmed' && empty($booking['driver_id']))): ?>
                            <div style="display:flex;gap:0.5rem;margin-top:0.75rem;">
                                <form method="POST" style="flex:1;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn-premium btn-danger btn-sm btn-block" style="justify-content:center;" onclick="return confirm('Cancel this booking?')"><i class="bi bi-x-lg"></i> Cancel Booking</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Live Tracking Card -->
                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-geo-alt-fill"></i> Live Tracking</h5>
                            <?php if ($booking['status'] === 'confirmed' && !empty($booking['driver_id'])): ?>
                                <span class="badge-premium badge-active"><i class="bi bi-broadcast"></i> Live</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body-premium">
                            <div style="height:160px;border-radius:14px;background:linear-gradient(135deg,#0F172A,#1E293B);border:1px solid var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;overflow:hidden;">
                                <?php if ($booking['status'] === 'confirmed' && $selectedDriver): ?>
                                    <div style="position:absolute;top:0.5rem;left:0.5rem;display:flex;gap:0.25rem;">
                                        <span class="badge-premium badge-active" style="font-size:0.55rem;"><i class="bi bi-broadcast"></i> Live</span>
                                    </div>
                                    <i class="bi bi-truck" style="font-size:2.5rem;color:var(--primary);opacity:0.5;margin-bottom:0.5rem;"></i>
                                    <span style="font-size:0.8125rem;color:var(--text-muted);font-weight:600;">Driver is on the way</span>
                                    <span style="font-size:0.6875rem;color:var(--text-muted);">Last updated: just now</span>
                                    <div style="position:absolute;bottom:0.5rem;right:0.5rem;display:flex;gap:0.5rem;">
                                        <span style="font-size:0.6rem;color:var(--text-muted);background:rgba(0,0,0,0.4);padding:0.2rem 0.5rem;border-radius:6px;"><i class="bi bi-geo-alt"></i> Live</span>
                                    </div>
                                <?php elseif ($booking['status'] === 'pending' || ($booking['status'] === 'confirmed' && empty($booking['driver_id']))): ?>
                                    <i class="bi bi-hourglass-split" style="font-size:2.5rem;color:var(--warning);opacity:0.4;margin-bottom:0.5rem;"></i>
                                    <span style="font-size:0.8125rem;color:var(--text-muted);">Waiting for driver assignment</span>
                                    <span style="font-size:0.6875rem;color:var(--text-muted);">Tracking will activate after confirmation</span>
                                <?php else: ?>
                                    <i class="bi bi-pin-map" style="font-size:2.5rem;color:var(--text-muted);opacity:0.3;margin-bottom:0.5rem;"></i>
                                    <span style="font-size:0.8125rem;color:var(--text-muted);">Trip completed / Tracking ended</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($booking['status'] === 'confirmed' && $selectedDriver): ?>
                            <div style="display:flex;justify-content:space-between;margin-top:0.75rem;">
                                <div><span class="info-label">ETA</span><div style="font-size:1.25rem;font-weight:800;color:var(--text-primary);">8 <span style="font-size:0.75rem;font-weight:500;color:var(--text-muted);">min</span></div></div>
                                <div><span class="info-label">Speed</span><div style="font-size:1.25rem;font-weight:800;color:var(--text-primary);">32 <span style="font-size:0.75rem;font-weight:500;color:var(--text-muted);">km/h</span></div></div>
                                <div><span class="info-label">Distance</span><div style="font-size:1.25rem;font-weight:800;color:var(--text-primary);">4.2 <span style="font-size:0.75rem;font-weight:500;color:var(--text-muted);">km</span></div></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Status Timeline -->
                    <div class="card-premium">
                        <div class="card-header-premium"><h5><i class="bi bi-clock-history"></i> Status Timeline</h5></div>
                        <div class="card-body-premium">
                            <div class="timeline">
                                <div class="timeline-item <?= in_array($booking['status'],['pending','confirmed','completed']) ? 'active' : '' ?>">
                                    <div class="tl-title">Booking Created</div>
                                    <div class="tl-desc"><?= e($booking['created_at']) ?></div>
                                </div>
                                <div class="timeline-item <?= (!empty($booking['driver_id']) && in_array($booking['status'],['confirmed','completed'])) ? 'active' : '' ?>">
                                    <div class="tl-title">Driver Assigned & Confirmed</div>
                                    <div class="tl-desc"><?= $booking['driver_name'] ? e($booking['driver_name']).' assigned' : 'Pending' ?></div>
                                </div>
                                <div class="timeline-item <?= $booking['status'] === 'completed' ? 'active' : '' ?>">
                                    <div class="tl-title">Trip Completed</div>
                                    <div class="tl-desc"><?= e($booking['completed_at'] ?? 'Pending') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Edit Fare Modal -->
<div id="fareModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:420px;">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-currency-rupee" style="color:var(--primary);"></i> Edit Fare</h5><button class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=fareModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_fare">
                <div class="form-group"><label class="form-label">Base Fare (₹)</label><input type="number" name="base_fare" class="form-input" step="0.01" value="<?= $booking['base_fare'] ?>"></div>
                <div class="form-group"><label class="form-label">Tax (₹)</label><input type="number" name="tax" class="form-input" step="0.01" value="<?= $booking['tax'] ?>"></div>
                <div class="form-group"><label class="form-label">Discount (₹)</label><input type="number" name="discount" class="form-input" step="0.01" value="<?= $booking['discount'] ?>"></div>
                <div class="form-group"><label class="form-label">Total Fare (₹)</label><input type="number" name="total_fare" class="form-input" step="0.01" value="<?= $booking['total_fare'] ?>"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=fareModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-check-circle"></i> Update Fare</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
