<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
requireDriverLogin();

$driverId = $_SESSION['driver_id'];

$stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.phone as user_phone, v.name as vehicle_name, v.type as vehicle_type
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.driver_id = ? AND b.status IN ('confirmed', 'ongoing')
    ORDER BY b.created_at DESC
");
$stmt->execute([$driverId]);
$myRides = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>TripAny - My Rides</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --bg: #F8FAFC; --card: #FFFFFF; --text: #1E293B; --text-secondary: #64748B; --border: #E2E8F0; --radius: 16px; --shadow: 0 2px 12px rgba(56,189,248,0.08); }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); max-width: 480px; margin: 0 auto; min-height: 100vh; padding-bottom: 80px; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }

        .page-header { background: linear-gradient(135deg, #38BDF8, #7DD3FC); padding: 16px 16px 18px; display: flex; align-items: center; gap: 12px; border-radius: 0 0 28px 28px; }
        .btn-back { width: 40px; height: 40px; background: rgba(255,255,255,0.15); border: none; border-radius: 12px; color: #fff; font-size: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; flex-shrink: 0; transition: background 0.2s; }
        .btn-back:active { background: rgba(255,255,255,0.25); }
        .page-header .title { flex: 1; color: #fff; font-size: 18px; font-weight: 700; }

        .content { padding: 16px; }

        .ride-card { background: var(--card); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); margin-bottom: 14px; border: 1px solid var(--border); animation: fadeInUp 0.3s ease-out backwards; }

        .user-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .user-avatar { width: 42px; height: 42px; border-radius: 50%; background: #dcfce7; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #38BDF8; font-size: 16px; flex-shrink: 0; }
        .user-info { flex: 1; min-width: 0; }
        .user-name { font-weight: 600; font-size: 14px; }
        .user-time { font-size: 11px; color: var(--text-secondary); display: flex; align-items: center; gap: 3px; }

        .status-tag { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; }
        .status-tag.confirmed { background: #dbeafe; color: #1e40af; }
        .status-tag.ongoing { background: #dcfce7; color: #166534; }

        .route-visual { padding: 0 4px; margin-bottom: 14px; }
        .route-point { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
        .route-point .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .route-point .dot.pickup { background: #38BDF8; }
        .route-point .dot.drop { background: #f59e0b; }
        .route-point .location { font-size: 13px; color: #475569; font-weight: 500; }
        .route-point .connector { width: 2px; height: 16px; background: var(--border); margin-left: 4px; margin-bottom: 4px; }

        .ride-meta { display: flex; justify-content: space-around; padding: 10px 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); margin-bottom: 14px; }
        .meta-item { text-align: center; }
        .meta-item .value { font-weight: 700; font-size: 14px; color: var(--text); }
        .meta-item .label { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.3px; }

        .btn-view-ride { width: 100%; padding: 13px; border: none; border-radius: 14px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; background: linear-gradient(135deg, #38BDF8, #7DD3FC); color: #fff; box-shadow: 0 4px 12px rgba(56,189,248,0.2); transition: transform 0.2s; text-align: center; display: block; }
        .btn-view-ride:active { transform: scale(0.97); }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state .icon-wrap { width: 80px; height: 80px; background: #F0F9FF; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .empty-state i { font-size: 36px; color: #38BDF8; }
        .empty-state h3 { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
        .empty-state p { font-size: 13px; color: var(--text-secondary); line-height: 1.6; }

        .bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 480px; background: var(--card); border-top: 1px solid var(--border); display: flex; justify-content: space-around; padding: 6px 0 max(6px, env(safe-area-inset-bottom)); z-index: 200; box-shadow: 0 -4px 20px rgba(0,0,0,0.05); }
        .bottom-nav a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 16px; color: var(--text-secondary); font-size: 10px; font-weight: 500; position: relative; }
        .bottom-nav a.active { color: var(--primary); }
        .bottom-nav a.active::before { content: ''; position: absolute; top: -1px; left: 50%; transform: translateX(-50%); width: 20px; height: 3px; background: var(--primary); border-radius: 0 0 4px 4px; }
        .bottom-nav a i { font-size: 22px; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="page-header">
        <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i></a>
        <div class="title">My Assigned Rides</div>
    </div>

    <div class="content">
        <?php if ($myRides): ?>
            <?php foreach ($myRides as $ride): ?>
            <a href="ride.php?id=<?= $ride['id'] ?>" class="ride-card" style="display:block;">
                <div class="user-row">
                    <div class="user-avatar"><?= strtoupper(substr($ride['user_name'], 0, 1)) ?></div>
                    <div class="user-info">
                        <div class="user-name"><?= e($ride['user_name']) ?></div>
                        <div class="user-time"><i class="bi bi-clock"></i> <?= timeAgo($ride['created_at']) ?></div>
                    </div>
                    <div class="status-tag <?= e($ride['status']) ?>">
                        <i class="bi <?= $ride['status'] === 'ongoing' ? 'bi-geo-alt-fill' : 'bi-check-circle' ?>"></i>
                        <?= e(ucfirst($ride['status'])) ?>
                    </div>
                </div>

                <div class="route-visual">
                    <div class="route-point">
                        <div class="dot pickup"></div>
                        <div class="location"><?= e($ride['pickup_location'] ?? 'Pickup Location') ?></div>
                    </div>
                    <div class="connector"></div>
                    <div class="route-point">
                        <div class="dot drop"></div>
                        <div class="location"><?= e($ride['drop_location'] ?? 'Drop Location') ?></div>
                    </div>
                </div>

                <div class="ride-meta">
                    <div class="meta-item">
                        <div class="value"><?= number_format($ride['distance_km'] ?? 0, 1) ?> km</div>
                        <div class="label">Distance</div>
                    </div>
                    <div class="meta-item">
                        <div class="value"><?= e(ucfirst($ride['payment_method'] ?? 'Cash')) ?></div>
                        <div class="label">Payment</div>
                    </div>
                    <div class="meta-item">
                        <div class="value">₹<?= number_format($ride['total_fare'] ?? 0) ?></div>
                        <div class="label">Fare</div>
                    </div>
                </div>

                <div class="btn-view-ride">
                    <i class="bi <?= $ride['status'] === 'ongoing' ? 'bi-geo-alt' : 'bi-steering-wheel' ?>" style="margin-right:6px;"></i>
                    <?= $ride['status'] === 'ongoing' ? 'View Active Ride' : 'View Ride Details' ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-wrap"><i class="bi bi-car-front"></i></div>
                <h3>No Rides Assigned Yet</h3>
                <p>Once the admin assigns a ride to you,<br>it will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <nav class="bottom-nav">
        <a href="index.php"><i class="bi bi-house"></i>Home</a>
        <a href="rides.php" class="active"><i class="bi bi-list-check"></i>Rides</a>
        <a href="history.php"><i class="bi bi-clock-history"></i>History</a>
        <a href="profile.php"><i class="bi bi-person"></i>Profile</a>
    </nav>
</body>
</html>
