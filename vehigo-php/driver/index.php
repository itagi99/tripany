<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
requireDriverLogin();

$driverId = $_SESSION['driver_id'];

$stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
$stmt->execute([$driverId]);
$driver = $stmt->fetch();
$driverName = $driver ? $driver['name'] : 'Driver';
$driverStatus = $driver['status'] ?? 'offline';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $newStatus = $driverStatus === 'online' ? 'offline' : 'online';
    if ($driverStatus === 'busy') $newStatus = 'busy';
    if ($driverStatus !== 'busy') {
        $stmt = $pdo->prepare("UPDATE drivers SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $driverId]);
        $driverStatus = $newStatus;
    }
}

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_fare), 0) as earnings FROM bookings WHERE driver_id = ? AND status = 'completed' AND DATE(completed_at) = DATE('now')");
$stmt->execute([$driverId]);
$todayEarnings = $stmt->fetch()['earnings'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE driver_id = ? AND status = 'completed'");
$stmt->execute([$driverId]);
$totalRides = $stmt->fetch()['total'];

$rating = $driver['rating'] ?? 5.0;

$activeRide = null;
$stmt = $pdo->prepare("SELECT b.*, u.name as user_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.driver_id = ? AND b.status IN ('confirmed', 'ongoing') ORDER BY b.created_at DESC LIMIT 1");
$stmt->execute([$driverId]);
$activeRide = $stmt->fetch();

$thisWeekEarnings = $pdo->prepare("SELECT COALESCE(SUM(total_fare), 0) FROM bookings WHERE driver_id = ? AND status = 'completed' AND completed_at >= date('now', '-7 days')");
$thisWeekEarnings->execute([$driverId]);
$thisWeekEarnings = $thisWeekEarnings->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>TripAny - Driver Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --bg: #F8FAFC; --card: #FFFFFF; --text: #1E293B; --text-secondary: #64748B; --border: #E2E8F0; --radius: 16px; --shadow: 0 2px 12px rgba(56,189,248,0.08); }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); max-width: 480px; margin: 0 auto; min-height: 100vh; padding-bottom: 80px; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }

        .header { background: linear-gradient(135deg, #38BDF8 0%, #7DD3FC 50%, #BAE6FD 100%); padding: 16px 16px 20px; border-radius: 0 0 28px 28px; position: relative; overflow: hidden; }
        .header::before { content: ''; position: absolute; right: -30px; top: -30px; width: 120px; height: 120px; background: rgba(255,255,255,0.08); border-radius: 50%; }
        .header-top { display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 2; }
        .brand { color: #fff; }
        .brand .brand-name { font-size: 20px; font-weight: 800; letter-spacing: -0.3px; }
        .brand .brand-sub { font-size: 12px; color: rgba(255,255,255,0.7); font-weight: 500; }
        .avatar { width: 42px; height: 42px; border-radius: 50%; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 16px; transition: transform 0.2s; }
        .avatar:active { transform: scale(0.95); }

        .content { padding: 16px; }

        .status-toggle { background: var(--card); border-radius: var(--radius); padding: 16px 18px; box-shadow: var(--shadow); display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; border: 1px solid var(--border); }
        .status-info .label { font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 6px; }
        .status-info .sub { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .pulse-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; animation: pulse 2s infinite; }
        .pulse-dot.online { background: #16a34a; }
        .pulse-dot.offline { background: #dc2626; }
        .pulse-dot.busy { background: #f59e0b; }
        @keyframes pulse { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:0.5; transform:scale(1.4); } }

        .toggle-switch { width: 56px; height: 30px; border-radius: 15px; background: #e2e8f0; position: relative; cursor: pointer; transition: background 0.3s; border: none; padding: 0; flex-shrink: 0; }
        .toggle-switch.active { background: #38BDF8; }
        .toggle-switch.busy { background: #f59e0b; cursor: not-allowed; }
        .toggle-switch .knob { width: 26px; height: 26px; border-radius: 50%; background: #fff; position: absolute; top: 2px; left: 2px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); }
        .toggle-switch.active .knob { transform: translateX(26px); }
        .toggle-switch.busy .knob { transform: translateX(13px); }

        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
        .stat-card { background: var(--card); border-radius: 16px; padding: 16px 10px; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border); }
        .stat-card .icon { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 8px; }
        .stat-card .icon.earnings { background: #dcfce7; color: #16a34a; }
        .stat-card .icon.rides { background: #dbeafe; color: #2563eb; }
        .stat-card .icon.rating { background: #fef3c7; color: #d97706; }
        .stat-card .value { font-size: 18px; font-weight: 800; color: var(--text); letter-spacing: -0.3px; }
        .stat-card .label { font-size: 11px; color: var(--text-secondary); margin-top: 2px; font-weight: 500; }

        .active-ride-card { background: linear-gradient(135deg, #38BDF8, #7DD3FC); border-radius: 20px; padding: 20px; color: #fff; box-shadow: 0 8px 32px rgba(56,189,248,0.3); margin-bottom: 14px; }
        .active-ride-card h6 { font-weight: 700; margin: 0 0 14px; font-size: 15px; display: flex; align-items: center; gap: 6px; }
        .active-ride-card .pulse-live { width: 8px; height: 8px; background: #4ade80; border-radius: 50%; animation: pulse 1.5s infinite; }
        .route-visual .route-point { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
        .route-visual .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .route-visual .dot.pickup { background: #4ade80; }
        .route-visual .dot.drop { background: #fbbf24; }
        .route-visual .connector { width: 2px; height: 16px; background: rgba(255,255,255,0.25); margin-left: 4px; margin-bottom: 4px; }
        .route-visual .route-text { font-size: 13px; opacity: 0.9; }
        .active-ride-card .ride-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.2); }
        .active-ride-card .fare { font-size: 22px; font-weight: 800; }
        .active-ride-card .fare span { font-size: 13px; font-weight: 500; opacity: 0.8; }
        .active-ride-card .btn-view { background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 9px 18px; border-radius: 12px; font-weight: 600; font-size: 13px; text-decoration: none; transition: background 0.2s; }
        .active-ride-card .btn-view:active { background: rgba(255,255,255,0.3); }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .section-header h3 { font-size: 16px; font-weight: 700; }
        .section-header a { font-size: 13px; color: var(--primary); font-weight: 600; }

        .vehicle-card { background: var(--card); border-radius: var(--radius); padding: 16px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 14px; margin-bottom: 14px; border: 1px solid var(--border); }
        .vehicle-icon-box { width: 52px; height: 52px; border-radius: 14px; background: #F0F9FF; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; }
        .vehicle-card .info h6 { margin: 0; font-weight: 600; font-size: 14px; }
        .vehicle-card .info p { margin: 3px 0 0; font-size: 12px; color: var(--text-secondary); }

        .weekly-card { background: var(--card); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); margin-bottom: 14px; border: 1px solid var(--border); }
        .weekly-card h4 { font-size: 14px; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .weekly-card h4 i { color: var(--primary); }
        .weekly-amount { font-size: 28px; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; }
        .weekly-amount span { font-size: 13px; color: var(--text-secondary); font-weight: 500; }

        .bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 480px; background: var(--card); border-top: 1px solid var(--border); display: flex; justify-content: space-around; padding: 6px 0 max(6px, env(safe-area-inset-bottom)); z-index: 200; box-shadow: 0 -4px 20px rgba(0,0,0,0.05); }
        .bottom-nav a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 16px; color: var(--text-secondary); font-size: 10px; font-weight: 500; transition: color 0.2s; position: relative; }
        .bottom-nav a.active { color: var(--primary); }
        .bottom-nav a.active::before { content: ''; position: absolute; top: -1px; left: 50%; transform: translateX(-50%); width: 20px; height: 3px; background: var(--primary); border-radius: 0 0 4px 4px; }
        .bottom-nav a i { font-size: 22px; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .content > * { animation: fadeInUp 0.3s ease-out backwards; }
        .content > *:nth-child(2) { animation-delay: 0.05s; }
        .content > *:nth-child(3) { animation-delay: 0.1s; }
        .content > *:nth-child(4) { animation-delay: 0.15s; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <div class="brand">
                <div class="brand-name"><i class="bi bi-steering-wheel" style="margin-right:4px;"></i>TripAny</div>
                <div class="brand-sub">Driver Portal</div>
            </div>
            <a href="profile.php" class="avatar"><?= strtoupper(substr($driverName, 0, 1)) ?></a>
        </div>
    </div>

    <div class="content">
        <div class="status-toggle">
            <div class="status-info">
                <div class="label">
                    <span class="pulse-dot <?= e($driverStatus) ?>"></span>
                    <?= e(ucfirst($driverStatus)) ?>
                </div>
                <div class="sub"><?= $driverStatus === 'busy' ? 'Currently on a ride' : ($driverStatus === 'online' ? 'Ready to accept rides' : 'Not accepting rides') ?></div>
            </div>
            <?php if ($driverStatus !== 'busy'): ?>
            <form method="POST">
                <input type="hidden" name="toggle_status" value="1">
                <button type="submit" class="toggle-switch <?= $driverStatus === 'online' ? 'active' : '' ?>">
                    <div class="knob"></div>
                </button>
            </form>
            <?php else: ?>
            <div class="toggle-switch busy"><div class="knob"></div></div>
            <?php endif; ?>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="icon earnings"><i class="bi bi-currency-rupee"></i></div>
                <div class="value">₹<?= number_format($todayEarnings) ?></div>
                <div class="label">Today</div>
            </div>
            <div class="stat-card">
                <div class="icon rides"><i class="bi bi-car-front"></i></div>
                <div class="value"><?= $totalRides ?></div>
                <div class="label">Total Rides</div>
            </div>
            <div class="stat-card">
                <div class="icon rating"><i class="bi bi-star-fill"></i></div>
                <div class="value"><?= number_format($rating, 1) ?></div>
                <div class="label">Rating</div>
            </div>
        </div>

        <?php if ($activeRide): ?>
        <a href="ride.php?id=<?= $activeRide['id'] ?>" class="active-ride-card">
            <h6><span class="pulse-live"></span> Active Ride</h6>
            <div class="route-visual">
                <div class="route-point">
                    <div class="dot pickup"></div>
                    <span class="route-text"><?= e($activeRide['pickup_location'] ?? 'Pickup') ?></span>
                </div>
                <div class="connector"></div>
                <div class="route-point">
                    <div class="dot drop"></div>
                    <span class="route-text"><?= e($activeRide['drop_location'] ?? 'Drop') ?></span>
                </div>
            </div>
            <div class="ride-meta">
                <div class="fare">₹<?= number_format($activeRide['total_fare'] ?? 0) ?> <span>/ride</span></div>
                <span class="btn-view">View <i class="bi bi-arrow-right" style="margin-left:4px;"></i></span>
            </div>
        </a>
        <?php endif; ?>

        <div class="vehicle-card">
            <div class="vehicle-icon-box"><?= vehicleIcon($driver['vehicle_type'] ?? 'car') ?></div>
            <div class="info">
                <h6><?= e($driver['vehicle_model'] ?? 'Vehicle') ?></h6>
                <p><?= e($driver['vehicle_number'] ?? '') ?> &middot; <?= e(ucfirst($driver['vehicle_type'] ?? '')) ?></p>
            </div>
        </div>

        <div class="weekly-card">
            <h4><i class="bi bi-calendar-week"></i> This Week</h4>
            <div class="weekly-amount">₹<?= number_format($thisWeekEarnings) ?> <span>earned</span></div>
        </div>
    </div>

    <nav class="bottom-nav">
        <a href="index.php" class="active"><i class="bi bi-house-fill"></i>Home</a>
        <a href="rides.php"><i class="bi bi-bell"></i>Rides</a>
        <a href="history.php"><i class="bi bi-clock-history"></i>History</a>
        <a href="profile.php"><i class="bi bi-person"></i>Profile</a>
    </nav>
</body>
</html>
