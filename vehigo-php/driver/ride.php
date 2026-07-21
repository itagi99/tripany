<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
requireDriverLogin();

$driverId = $_SESSION['driver_id'];
$rideId = (int)($_GET['id'] ?? 0);
if (!$rideId) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT b.*, u.name as user_name, u.phone as user_phone FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.driver_id = ?");
$stmt->execute([$rideId, $driverId]);
$ride = $stmt->fetch();
if (!$ride) { header("Location: index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['start_ride'])) {
            $pdo->prepare("UPDATE bookings SET status = 'ongoing' WHERE id = ? AND status IN ('accepted','confirmed')")->execute([$rideId]);
        }
    if (isset($_POST['complete_ride'])) {
        $pdo->prepare("UPDATE bookings SET status = 'completed', completed_at = datetime('now') WHERE id = ? AND status = 'ongoing'")->execute([$rideId]);
        $pdo->prepare("UPDATE drivers SET status = 'online' WHERE id = ?")->execute([$driverId]);
    }
    if (isset($_POST['cancel_ride'])) {
        $pdo->prepare("UPDATE bookings SET status = 'cancelled', driver_id = NULL WHERE id = ? AND status IN ('accepted')")->execute([$rideId]);
        $pdo->prepare("UPDATE drivers SET status = 'online' WHERE id = ?")->execute([$driverId]);
    }
    header("Location: ride.php?id=" . $rideId);
    exit;
}

$stmt = $pdo->prepare("SELECT b.*, u.name as user_name, u.phone as user_phone FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.driver_id = ?");
$stmt->execute([$rideId, $driverId]);
$ride = $stmt->fetch();

$statusLabels = ['pending' => 'Awaiting Assignment', 'accepted' => 'Ride Accepted', 'confirmed' => 'Assigned — Start When Ready', 'ongoing' => 'Ride In Progress', 'completed' => 'Ride Completed', 'cancelled' => 'Ride Cancelled'];
$statusIcons = ['pending' => 'bi-clock', 'accepted' => 'bi-check-circle', 'confirmed' => 'bi-check-circle', 'ongoing' => 'bi-geo-alt', 'completed' => 'bi-check-circle-fill', 'cancelled' => 'bi-x-circle'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>TripAny - Ride #<?= $ride['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --bg: #F8FAFC; --card: #FFFFFF; --text: #1E293B; --text-secondary: #64748B; --border: #E2E8F0; --radius: 16px; --shadow: 0 2px 12px rgba(56,189,248,0.08); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); max-width: 480px; margin: 0 auto; min-height: 100vh; padding-bottom: 20px; -webkit-font-smoothing: antialiased; }

        .page-header { background: linear-gradient(135deg, #38BDF8, #7DD3FC); padding: 16px 16px 18px; display: flex; align-items: center; gap: 12px; border-radius: 0 0 28px 28px; }
        .btn-back { width: 40px; height: 40px; background: rgba(255,255,255,0.15); border: none; border-radius: 12px; color: #fff; font-size: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; flex-shrink: 0; }
        .page-header .title { flex: 1; color: #fff; font-size: 18px; font-weight: 700; }

        .status-banner { text-align: center; padding: 10px; font-weight: 600; font-size: 13px; margin: 12px 16px; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .status-banner.pending { background: #fef3c7; color: #92400e; }
        .status-banner.accepted { background: #dbeafe; color: #1e40af; }
        .status-banner.confirmed { background: #dbeafe; color: #1e40af; }
        .status-banner.ongoing { background: #dcfce7; color: #166534; }
        .status-banner.completed { background: #dcfce7; color: #166534; }
        .status-banner.cancelled { background: #fee2e2; color: #991b1b; }

        .content { padding: 0 16px 16px; }

        .card { background: var(--card); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); margin-bottom: 14px; border: 1px solid var(--border); }
        .card h3 { font-size: 15px; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
        .card h3 i { color: var(--primary); font-size: 16px; }

        .route-visual .route-point { display: flex; align-items: flex-start; gap: 12px; }
        .route-visual .dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; margin-top: 3px; }
        .route-visual .dot.pickup { background: #38BDF8; }
        .route-visual .dot.drop { background: #f59e0b; }
        .route-visual .label { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .route-visual .text { font-size: 14px; color: var(--text); font-weight: 500; margin-top: 2px; }
        .route-visual .connector { width: 2px; height: 24px; background: var(--border); margin-left: 5px; margin: 6px 0; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
        .info-item { background: var(--card); border-radius: 14px; padding: 14px; box-shadow: var(--shadow); text-align: center; border: 1px solid var(--border); }
        .info-item i { font-size: 20px; color: var(--primary); margin-bottom: 6px; display: block; }
        .info-item .value { font-weight: 700; font-size: 15px; color: var(--text); }
        .info-item .label { font-size: 11px; color: var(--text-secondary); }

        .user-card { display: flex; align-items: center; gap: 12px; }
        .user-card .avatar { width: 48px; height: 48px; border-radius: 50%; background: #dcfce7; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #38BDF8; font-size: 20px; flex-shrink: 0; }
        .user-card .info h6 { margin: 0; font-weight: 600; font-size: 15px; }
        .user-card .info p { margin: 2px 0 0; font-size: 12px; color: var(--text-secondary); }
        .user-card .call-btn { margin-left: auto; width: 44px; height: 44px; border-radius: 50%; background: #dcfce7; border: none; color: #38BDF8; font-size: 18px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: transform 0.2s; }
        .user-card .call-btn:active { transform: scale(0.95); }

        .fare-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; color: var(--text-secondary); }
        .fare-row.total { border-top: 2px solid var(--border); margin-top: 8px; padding-top: 12px; font-weight: 800; font-size: 18px; color: var(--primary); }

        .btn-action { width: 100%; padding: 16px; border: none; border-radius: 14px; font-size: 16px; font-weight: 700; margin-bottom: 10px; cursor: pointer; font-family: inherit; transition: transform 0.2s, box-shadow 0.2s; }
        .btn-action:active { transform: scale(0.98); }
        .btn-start { background: linear-gradient(135deg, #38BDF8, #0EA5E9); color: #fff; box-shadow: 0 4px 16px rgba(56,189,248,0.3); }
        .btn-complete { background: linear-gradient(135deg, #38BDF8, #7DD3FC); color: #fff; box-shadow: 0 4px 16px rgba(56,189,248,0.3); }
        .btn-cancel { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca; }
    </style>
</head>
<body>
    <div class="page-header">
        <a href="javascript:history.back()" class="btn-back"><i class="bi bi-arrow-left"></i></a>
        <div class="title">Ride #<?= $ride['booking_ref'] ?? $ride['id'] ?></div>
    </div>

    <div class="status-banner <?= e($ride['status']) ?>">
        <i class="bi <?= $statusIcons[$ride['status']] ?? 'bi-info-circle' ?>"></i>
        <?= $statusLabels[$ride['status']] ?? e(ucfirst($ride['status'])) ?>
    </div>

    <div class="content">
        <div class="card">
            <h3><i class="bi bi-geo-alt"></i> Route</h3>
            <div class="route-visual">
                <div class="route-point">
                    <div class="dot pickup"></div>
                    <div>
                        <div class="label">Pickup</div>
                        <div class="text"><?= e($ride['pickup_location'] ?? 'N/A') ?></div>
                    </div>
                </div>
                <div class="connector"></div>
                <div class="route-point">
                    <div class="dot drop"></div>
                    <div>
                        <div class="label">Drop</div>
                        <div class="text"><?= e($ride['drop_location'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <i class="bi bi-signpost-2"></i>
                <div class="value"><?= number_format($ride['distance_km'] ?? 0, 1) ?> km</div>
                <div class="label">Distance</div>
            </div>
            <div class="info-item">
                <i class="bi bi-wallet2"></i>
                <div class="value"><?= e(ucfirst($ride['payment_method'] ?? 'Cash')) ?></div>
                <div class="label">Payment</div>
            </div>
            <div class="info-item">
                <i class="bi bi-calendar-check"></i>
                <div class="value"><?= $ride['duration_days'] ?? 1 ?> day</div>
                <div class="label">Duration</div>
            </div>
            <div class="info-item">
                <i class="bi bi-clock"></i>
                <div class="value"><?= date('h:i A', strtotime($ride['created_at'])) ?></div>
                <div class="label">Booked At</div>
            </div>
        </div>

        <div class="card">
            <h3><i class="bi bi-person"></i> Customer</h3>
            <div class="user-card">
                <div class="avatar"><?= strtoupper(substr($ride['user_name'], 0, 1)) ?></div>
                <div class="info">
                    <h6><?= e($ride['user_name']) ?></h6>
                    <p><i class="bi bi-phone" style="margin-right:4px;"></i><?= e($ride['user_phone'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($ride['user_phone'])): ?>
                <a href="tel:<?= e($ride['user_phone']) ?>" class="call-btn"><i class="bi bi-telephone-fill"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3><i class="bi bi-receipt"></i> Fare Details</h3>
            <div class="fare-row">
                <span>Base Fare</span>
                <span>₹<?= number_format($ride['base_fare'] ?? 0) ?></span>
            </div>
            <?php if (($ride['tax'] ?? 0) > 0): ?>
            <div class="fare-row">
                <span>Tax</span>
                <span>₹<?= number_format($ride['tax']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (($ride['discount'] ?? 0) > 0): ?>
            <div class="fare-row" style="color: var(--primary);">
                <span>Discount</span>
                <span>-₹<?= number_format($ride['discount']) ?></span>
            </div>
            <?php endif; ?>
            <div class="fare-row total">
                <span>Total</span>
                <span>₹<?= number_format($ride['total_fare'] ?? 0) ?></span>
            </div>
        </div>

        <?php if ($ride['status'] === 'accepted' || $ride['status'] === 'confirmed'): ?>
        <form method="POST"><input type="hidden" name="start_ride" value="1">
            <button type="submit" class="btn-action btn-start"><i class="bi bi-play-fill" style="margin-right:6px;"></i>Start Ride</button>
        </form>
        <form method="POST"><input type="hidden" name="cancel_ride" value="1">
            <button type="submit" class="btn-action btn-cancel" onclick="return confirm('Cancel this ride?')"><i class="bi bi-x-lg" style="margin-right:6px;"></i>Cancel Ride</button>
        </form>
        <?php elseif ($ride['status'] === 'ongoing'): ?>
        <form method="POST"><input type="hidden" name="complete_ride" value="1">
            <button type="submit" class="btn-action btn-complete"><i class="bi bi-check-circle" style="margin-right:6px;"></i>Complete Ride</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
