<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if (isDriverLoggedIn()) { header("Location: index.php"); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $vehicleType = $_POST['vehicle_type'] ?? '';
    $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
    $vehicleModel = trim($_POST['vehicle_model'] ?? '');
    $licenseNumber = trim($_POST['license_number'] ?? '');

    if (empty($name) || empty($phone) || empty($password) || empty($confirm) || empty($vehicleType) || empty($vehicleNumber)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($phone) !== 10 || !ctype_digit($phone)) {
        $error = 'Please enter a valid 10-digit phone number.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM drivers WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $error = 'Phone number already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO drivers (name, phone, email, password_hash, vehicle_type, vehicle_number, vehicle_model, license_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $hash, $vehicleType, $vehicleNumber, $vehicleModel, $licenseNumber]);
            $_SESSION['driver_id'] = $pdo->lastInsertId();
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Driver Registration - TripAny</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --bg: #f8fafc; --card: #ffffff; --text: #1e293b; --text-secondary: #64748b; --border: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); min-height: 100vh; max-width: 480px; margin: 0 auto; -webkit-font-smoothing: antialiased; }

        .reg-header { background: linear-gradient(135deg, #38BDF8 0%, #7DD3FC 50%, #BAE6FD 100%); padding: 40px 20px 60px; text-align: center; border-radius: 0 0 40px 40px; position: relative; overflow: hidden; }
        .reg-header::before { content: ''; position: absolute; right: -30px; top: -30px; width: 120px; height: 120px; background: rgba(255,255,255,0.08); border-radius: 50%; }
        .reg-header .logo { width: 64px; height: 64px; background: rgba(255,255,255,0.18); backdrop-filter: blur(10px); border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; border: 2px solid rgba(255,255,255,0.2); }
        .reg-header .logo i { font-size: 32px; color: #fff; }
        .reg-header h1 { color: #fff; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .reg-header p { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; }

        .reg-card { background: var(--card); border-radius: 24px; padding: 24px 20px; margin: -32px 16px 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); position: relative; z-index: 2; }

        .input-group { position: relative; margin-bottom: 12px; }
        .input-group input, .input-group select { width: 100%; padding: 13px 16px 13px 46px; border: 2px solid var(--border); border-radius: 14px; font-size: 14px; background: #f8fafc; transition: all 0.2s; outline: none; font-family: inherit; color: var(--text); appearance: none; -webkit-appearance: none; }
        .input-group input:focus, .input-group select:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(56,189,248,0.1); }
        .input-group .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; z-index: 3; }
        .input-group .toggle-pass { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; z-index: 3; border: none; background: none; font-size: 16px; padding: 4px; }
        .input-group select { padding-left: 46px; cursor: pointer; }
        .input-group .select-arrow { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; pointer-events: none; z-index: 3; }

        .section-divider { display: flex; align-items: center; gap: 10px; margin: 18px 0 14px; font-size: 13px; font-weight: 700; color: var(--primary); }
        .section-divider::before, .section-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .btn-register { width: 100%; padding: 14px; background: linear-gradient(135deg, #38BDF8, #7DD3FC); color: #fff; border: none; border-radius: 14px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: inherit; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 16px rgba(56,189,248,0.3); margin-top: 4px; }
        .btn-register:active { transform: scale(0.98); }

        .login-link { text-align: center; margin-top: 18px; font-size: 14px; color: var(--text-secondary); }
        .login-link a { color: var(--primary); font-weight: 600; text-decoration: none; }

        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; border-radius: 12px; padding: 12px 16px; font-size: 13px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <div class="reg-header">
        <div class="logo"><i class="bi bi-steering-wheel"></i></div>
        <h1>Join as Driver</h1>
        <p>Start earning with TripAny</p>
    </div>

    <div class="reg-card">
        <?php if ($error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-circle"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <i class="bi bi-person input-icon"></i>
                <input type="text" name="name" placeholder="Full Name" value="<?= e($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="input-group">
                <i class="bi bi-phone input-icon"></i>
                <input type="tel" name="phone" placeholder="Phone Number" value="<?= e($_POST['phone'] ?? '') ?>" maxlength="10" required>
            </div>
            <div class="input-group">
                <i class="bi bi-envelope input-icon"></i>
                <input type="email" name="email" placeholder="Email (optional)" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="input-group">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" name="password" id="password" placeholder="Password (min 6 chars)" required>
                <button type="button" class="toggle-pass" onclick="togglePass('password','eye1')"><i class="bi bi-eye" id="eye1"></i></button>
            </div>
            <div class="input-group">
                <i class="bi bi-lock-fill input-icon"></i>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                <button type="button" class="toggle-pass" onclick="togglePass('confirm_password','eye2')"><i class="bi bi-eye" id="eye2"></i></button>
            </div>

            <div class="section-divider">Vehicle Details</div>

            <div class="input-group">
                <i class="bi bi-car-front input-icon"></i>
                <select name="vehicle_type" required>
                    <option value="" disabled selected>Select Vehicle Type</option>
                    <option value="car" <?= ($_POST['vehicle_type'] ?? '') === 'car' ? 'selected' : '' ?>>Car / Sedan</option>
                    <option value="suv" <?= ($_POST['vehicle_type'] ?? '') === 'suv' ? 'selected' : '' ?>>SUV</option>
                    <option value="bike" <?= ($_POST['vehicle_type'] ?? '') === 'bike' ? 'selected' : '' ?>>Bike</option>
                    <option value="auto" <?= ($_POST['vehicle_type'] ?? '') === 'auto' ? 'selected' : '' ?>>Auto Rickshaw</option>
                    <option value="truck" <?= ($_POST['vehicle_type'] ?? '') === 'truck' ? 'selected' : '' ?>>Mini Truck</option>
                </select>
                <span class="select-arrow"><i class="bi bi-chevron-down"></i></span>
            </div>
            <div class="input-group">
                <i class="bi bi-upc-scan input-icon"></i>
                <input type="text" name="vehicle_number" placeholder="Vehicle Number (e.g. DL-01-AB-1234)" value="<?= e($_POST['vehicle_number'] ?? '') ?>" required>
            </div>
            <div class="input-group">
                <i class="bi bi-truck input-icon"></i>
                <input type="text" name="vehicle_model" placeholder="Vehicle Model (optional)" value="<?= e($_POST['vehicle_model'] ?? '') ?>">
            </div>
            <div class="input-group">
                <i class="bi bi-card-heading input-icon"></i>
                <input type="text" name="license_number" placeholder="License Number (optional)" value="<?= e($_POST['license_number'] ?? '') ?>">
            </div>

            <button type="submit" class="btn-register">Register as Driver</button>
        </form>

        <div class="login-link">
            Already a driver? <a href="login.php">Login</a>
        </div>
    </div>

    <script>
        function togglePass(id, iconId) {
            const p = document.getElementById(id);
            const i = document.getElementById(iconId);
            if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
            else { p.type = 'password'; i.className = 'bi bi-eye'; }
        }
    </script>
</body>
</html>
