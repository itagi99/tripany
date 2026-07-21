<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
requireDriverLogin();

$driverId = $_SESSION['driver_id'];
$success = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
$stmt->execute([$driverId]);
$driver = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
        $vehicleModel = trim($_POST['vehicle_model'] ?? '');
        $licenseNumber = trim($_POST['license_number'] ?? '');

        if (empty($name)) {
            $error = 'Name is required.';
        } else {
            $pdo->prepare("UPDATE drivers SET name = ?, email = ?, vehicle_number = ?, vehicle_model = ?, license_number = ? WHERE id = ?")->execute([$name, $email, $vehicleNumber, $vehicleModel, $licenseNumber, $driverId]);
            $success = 'Profile updated successfully!';
            $stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
            $stmt->execute([$driverId]);
            $driver = $stmt->fetch();
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $error = 'Please fill in all password fields.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        } elseif (!password_verify($currentPass, $driver['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE drivers SET password_hash = ? WHERE id = ?")->execute([$hash, $driverId]);
            $success = 'Password changed successfully!';
        }
    }
}

$totalCompleted = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE driver_id = ? AND status = 'completed'");
$totalCompleted->execute([$driverId]);
$totalCompleted = $totalCompleted->fetchColumn();

$totalEarnings = $pdo->prepare("SELECT COALESCE(SUM(total_fare), 0) FROM bookings WHERE driver_id = ? AND status = 'completed'");
$totalEarnings->execute([$driverId]);
$totalEarnings = $totalEarnings->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>TripAny - Driver Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --bg: #F8FAFC; --card: #FFFFFF; --text: #1E293B; --text-secondary: #64748B; --border: #E2E8F0; --radius: 16px; --shadow: 0 2px 12px rgba(56,189,248,0.08); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); max-width: 480px; margin: 0 auto; min-height: 100vh; padding-bottom: 80px; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }

        .profile-header { background: linear-gradient(135deg, #38BDF8 0%, #7DD3FC 50%, #BAE6FD 100%); padding: 20px 16px 44px; text-align: center; position: relative; overflow: hidden; border-radius: 0 0 32px 32px; }
        .profile-header::before { content: ''; position: absolute; right: -30px; top: -30px; width: 120px; height: 120px; background: rgba(255,255,255,0.08); border-radius: 50%; }
        .profile-header .back-btn { position: absolute; left: 16px; top: 16px; color: #fff; width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 20px; text-decoration: none; }
        .profile-header .avatar { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 3px solid rgba(255,255,255,0.3); display: inline-flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 800; color: #fff; margin-bottom: 10px; }
        .profile-header h5 { color: #fff; font-weight: 700; margin: 0; font-size: 20px; }
        .profile-header p { color: rgba(255,255,255,0.8); font-size: 13px; margin: 4px 0 0; }
        .profile-header .status-badge { display: inline-flex; align-items: center; gap: 4px; margin-top: 8px; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .profile-header .status-badge.online { background: rgba(74,222,128,0.2); color: #fff; }
        .profile-header .status-badge.offline { background: rgba(248,113,113,0.3); color: #fff; }
        .profile-header .status-badge.busy { background: rgba(251,191,36,0.3); color: #fff; }

        .content { padding: 0 16px 16px; margin-top: -24px; position: relative; z-index: 2; }

        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
        .stat-item { background: var(--card); border-radius: var(--radius); padding: 16px; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border); }
        .stat-item .value { font-size: 20px; font-weight: 800; letter-spacing: -0.3px; }
        .stat-item .label { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }

        .card { background: var(--card); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); margin-bottom: 14px; border: 1px solid var(--border); }
        .card-title { font-size: 14px; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .card-title i { width: 32px; height: 32px; background: #dcfce7; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; color: var(--primary); font-size: 15px; }

        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f8fafc; font-size: 13px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .label { color: var(--text-secondary); }
        .detail-row .value { color: var(--text); font-weight: 600; }

        .input-group { position: relative; margin-bottom: 12px; }
        .input-group input { width: 100%; padding: 13px 14px 13px 44px; border: 2px solid var(--border); border-radius: 14px; font-size: 14px; background: #f8fafc; transition: all 0.2s; outline: none; font-family: inherit; color: var(--text); }
        .input-group input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(56,189,248,0.1); }
        .input-group .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; z-index: 3; }

        .btn-save { width: 100%; padding: 14px; background: linear-gradient(135deg, #38BDF8, #7DD3FC); border: none; border-radius: 14px; color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; font-family: inherit; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 12px rgba(56,189,248,0.2); }
        .btn-save:active { transform: scale(0.98); }

        .btn-logout { width: 100%; padding: 14px; background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 14px; color: #dc2626; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; margin-bottom: 14px; transition: background 0.2s; }
        .btn-logout:active { background: #fee2e2; }

        .alert { padding: 12px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

        .section-title { font-size: 14px; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .section-title i { color: var(--primary); }

        .bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 480px; background: var(--card); border-top: 1px solid var(--border); display: flex; justify-content: space-around; padding: 6px 0 max(6px, env(safe-area-inset-bottom)); z-index: 200; box-shadow: 0 -4px 20px rgba(0,0,0,0.05); }
        .bottom-nav a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 16px; color: var(--text-secondary); font-size: 10px; font-weight: 500; position: relative; }
        .bottom-nav a.active { color: var(--primary); }
        .bottom-nav a.active::before { content: ''; position: absolute; top: -1px; left: 50%; transform: translateX(-50%); width: 20px; height: 3px; background: var(--primary); border-radius: 0 0 4px 4px; }
        .bottom-nav a i { font-size: 22px; }
    </style>
</head>
<body>
    <div class="profile-header">
        <a href="index.php" class="back-btn"><i class="bi bi-arrow-left"></i></a>
        <div class="avatar"><?= strtoupper(substr($driver['name'] ?? 'D', 0, 1)) ?></div>
        <h5><?= e($driver['name'] ?? 'Driver') ?></h5>
        <p><i class="bi bi-phone" style="margin-right:3px;"></i><?= e($driver['phone'] ?? '') ?></p>
        <span class="status-badge <?= e($driver['status'] ?? 'offline') ?>">
            <i class="bi bi-circle-fill" style="font-size:6px;"></i>
            <?= e(ucfirst($driver['status'] ?? 'offline')) ?>
        </span>
    </div>

    <div class="content">
        <div class="stats-row">
            <div class="stat-item">
                <div class="value" style="color:var(--primary);"><?= $totalCompleted ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-item">
                <div class="value" style="color:#38BDF8;">₹<?= number_format($totalEarnings) ?></div>
                <div class="label">Earnings</div>
            </div>
            <div class="stat-item">
                <div class="value" style="color:#d97706;"><?= number_format($driver['rating'] ?? 5.0, 1) ?></div>
                <div class="label">Rating</div>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="bi bi-car-front-fill"></i> Vehicle Information</div>
            <div class="detail-row"><span class="label">Type</span><span class="value"><?= vehicleIcon($driver['vehicle_type'] ?? 'car') ?> <?= e(ucfirst($driver['vehicle_type'] ?? '')) ?></span></div>
            <div class="detail-row"><span class="label">Model</span><span class="value"><?= e($driver['vehicle_model'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="label">Number</span><span class="value"><?= e($driver['vehicle_number'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="label">License</span><span class="value"><?= e($driver['license_number'] ?? 'N/A') ?></span></div>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>

        <div class="section-title"><i class="bi bi-pencil-square"></i> Edit Profile</div>
        <div class="card">
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="input-group"><i class="bi bi-person input-icon"></i><input type="text" name="name" placeholder="Full Name" value="<?= e($driver['name'] ?? '') ?>" required></div>
                <div class="input-group"><i class="bi bi-envelope input-icon"></i><input type="email" name="email" placeholder="Email" value="<?= e($driver['email'] ?? '') ?>"></div>
                <div class="input-group"><i class="bi bi-upc-scan input-icon"></i><input type="text" name="vehicle_number" placeholder="Vehicle Number" value="<?= e($driver['vehicle_number'] ?? '') ?>"></div>
                <div class="input-group"><i class="bi bi-truck input-icon"></i><input type="text" name="vehicle_model" placeholder="Vehicle Model" value="<?= e($driver['vehicle_model'] ?? '') ?>"></div>
                <div class="input-group"><i class="bi bi-card-heading input-icon"></i><input type="text" name="license_number" placeholder="License Number" value="<?= e($driver['license_number'] ?? '') ?>"></div>
                <button type="submit" class="btn-save">Save Changes</button>
            </form>
        </div>

        <div class="section-title"><i class="bi bi-lock"></i> Change Password</div>
        <div class="card">
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                <div class="input-group"><i class="bi bi-lock input-icon"></i><input type="password" name="current_password" placeholder="Current Password" required></div>
                <div class="input-group"><i class="bi bi-lock-fill input-icon"></i><input type="password" name="new_password" placeholder="New Password (min 6 chars)" required></div>
                <div class="input-group"><i class="bi bi-shield-lock input-icon"></i><input type="password" name="confirm_password" placeholder="Confirm New Password" required></div>
                <button type="submit" class="btn-save">Change Password</button>
            </form>
        </div>

        <a href="logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to logout?')"><i class="bi bi-box-arrow-right" style="margin-right:6px;"></i>Logout</a>
    </div>

    <nav class="bottom-nav">
        <a href="index.php"><i class="bi bi-house"></i>Home</a>
        <a href="rides.php"><i class="bi bi-bell"></i>Rides</a>
        <a href="history.php"><i class="bi bi-clock-history"></i>History</a>
        <a href="profile.php" class="active"><i class="bi bi-person-fill"></i>Profile</a>
    </nav>
</body>
</html>
