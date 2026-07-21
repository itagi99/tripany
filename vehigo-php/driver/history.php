<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
requireDriverLogin();

$driverId = $_SESSION['driver_id'];

$stmt = $pdo->prepare("SELECT b.*, u.name as user_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.driver_id = ? AND b.status IN ('completed', 'cancelled') ORDER BY b.created_at DESC");
$stmt->execute([$driverId]);
$history = $stmt->fetchAll();

$totalEarnings = 0;
$totalCompleted = 0;
foreach ($history as $h) {
    if ($h['status'] === 'completed') {
        $totalEarnings += $h['total_fare'] ?? 0;
        $totalCompleted++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>TripAny - Ride History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --bg: #F8FAFC; --card: #FFFFFF; --text: #1E293B; --text-secondary: #64748B; --border: #E2E8F0; --radius: 16px; --shadow: 0 2px 12px rgba(56,189,248,0.08); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); max-width: 480px; margin: 0 auto; min-height: 100vh; padding-bottom: 80px; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }

        .page-header { background: linear-gradient(135deg, #38BDF8, #7DD3FC); padding: 16px 16px 18px; display: flex; align-items: center; gap: 12px; border-radius: 0 0 28px 28px; }
        .btn-back { width: 40px; height: 40px; background: rgba(255,255,255,0.15); border: none; border-radius: 12px; color: #fff; font-size: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; flex-shrink: 0; }
        .page-header .title { flex: 1; color: #fff; font-size: 18px; font-weight: 700; }

        .content { padding: 16px; }

        .summary-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
        .summary-card { background: var(--card); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); text-align: center; border: 1px solid var(--border); }
        .summary-card .value { font-size: 22px; font-weight: 800; letter-spacing: -0.3px; }
        .summary-card .value.green { color: #16a34a; }
        .summary-card .value.blue { color: #38BDF8; }
        .summary-card .label { font-size: 11px; color: var(--text-secondary); margin-top: 4px; font-weight: 500; }

        .history-card { background: var(--card); border-radius: var(--radius); padding: 16px; box-shadow: var(--shadow); margin-bottom: 10px; border: 1px solid var(--border); transition: transform 0.2s; }
        .history-card .top-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
        .history-card .user-name { font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 6px; }
        .history-card .date { font-size: 11px; color: var(--text-secondary); }
        .history-card .route { font-size: 13px; color: #475569; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .history-card .route i { color: var(--primary); font-size: 12px; }
        .history-card .meta { font-size: 11px; color: var(--text-secondary); }
        .history-card .bottom-row { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); }
        .history-card .fare { font-weight: 800; font-size: 16px; color: var(--primary); }
        .history-card .fare.cancelled { color: #dc2626; text-decoration: line-through; }
        .history-card .time { font-size: 11px; color: var(--text-secondary); }

        .status-pill { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pill.completed { background: #dcfce7; color: #16a34a; }
        .status-pill.cancelled { background: #fee2e2; color: #dc2626; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state .icon-wrap { width: 80px; height: 80px; background: #F0F9FF; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .empty-state i { font-size: 36px; color: var(--primary); }
        .empty-state h3 { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
        .empty-state p { font-size: 13px; color: var(--text-secondary); }

        .bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 480px; background: var(--card); border-top: 1px solid var(--border); display: flex; justify-content: space-around; padding: 6px 0 max(6px, env(safe-area-inset-bottom)); z-index: 200; box-shadow: 0 -4px 20px rgba(0,0,0,0.05); }
        .bottom-nav a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 16px; color: var(--text-secondary); font-size: 10px; font-weight: 500; position: relative; }
        .bottom-nav a.active { color: var(--primary); }
        .bottom-nav a.active::before { content: ''; position: absolute; top: -1px; left: 50%; transform: translateX(-50%); width: 20px; height: 3px; background: var(--primary); border-radius: 0 0 4px 4px; }
        .bottom-nav a i { font-size: 22px; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .content .summary-row, .content .history-card { animation: fadeInUp 0.3s ease-out backwards; }
    </style>
</head>
<body>
    <div class="page-header">
        <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i></a>
        <div class="title">Ride History</div>
    </div>

    <div class="content">
        <div class="summary-row">
            <div class="summary-card">
                <div class="value green">₹<?= number_format($totalEarnings) ?></div>
                <div class="label">Total Earnings</div>
            </div>
            <div class="summary-card">
                <div class="value blue"><?= $totalCompleted ?></div>
                <div class="label">Completed Rides</div>
            </div>
        </div>

        <?php if ($history): ?>
            <?php foreach ($history as $ride): ?>
            <div class="history-card">
                <div class="top-row">
                    <div class="user-name"><i class="bi bi-person-circle" style="color:var(--primary);"></i> <?= e($ride['user_name']) ?></div>
                    <span class="status-pill <?= $ride['status'] ?>"><?= e(ucfirst($ride['status'])) ?></span>
                </div>
                <div class="route">
                    <i class="bi bi-geo-alt-fill"></i>
                    <?= e($ride['pickup_location'] ?? 'N/A') ?>
                    <i class="bi bi-arrow-right" style="color:var(--text-secondary);"></i>
                    <?= e($ride['drop_location'] ?? 'N/A') ?>
                </div>
                <div class="meta"><?= number_format($ride['distance_km'] ?? 0, 1) ?> km &middot; <?= e($ride['payment_method'] ?? 'Cash') ?></div>
                <div class="bottom-row">
                    <div class="fare <?= $ride['status'] === 'cancelled' ? 'cancelled' : '' ?>">₹<?= number_format($ride['total_fare'] ?? 0) ?></div>
                    <div class="time"><?= date('d M, h:i A', strtotime($ride['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-wrap"><i class="bi bi-clock-history"></i></div>
                <h3>No Ride History</h3>
                <p>Your completed and cancelled rides will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <nav class="bottom-nav">
        <a href="index.php"><i class="bi bi-house"></i>Home</a>
        <a href="rides.php"><i class="bi bi-bell"></i>Rides</a>
        <a href="history.php" class="active"><i class="bi bi-clock-history"></i>History</a>
        <a href="profile.php"><i class="bi bi-person"></i>Profile</a>
    </nav>
</body>
</html>
