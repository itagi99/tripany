<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if (isDriverLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($phone) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM drivers WHERE phone = ?");
        $stmt->execute([$phone]);
        $driver = $stmt->fetch();

        if ($driver && password_verify($password, $driver['password_hash'])) {
            $_SESSION['driver_id'] = $driver['id'];
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid phone number or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Driver Login - TripAny</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --bg: #f8fafc; --card: #ffffff; --text: #1e293b; --text-secondary: #64748b; --border: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); min-height: 100vh; max-width: 480px; margin: 0 auto; -webkit-font-smoothing: antialiased; }

        .login-header { background: linear-gradient(135deg, #38BDF8 0%, #7DD3FC 50%, #BAE6FD 100%); padding: 48px 20px 72px; text-align: center; border-radius: 0 0 40px 40px; position: relative; overflow: hidden; }
        .login-header::before { content: ''; position: absolute; right: -30px; top: -30px; width: 120px; height: 120px; background: rgba(255,255,255,0.08); border-radius: 50%; }
        .login-header .logo { width: 72px; height: 72px; background: rgba(255,255,255,0.18); backdrop-filter: blur(10px); border-radius: 22px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; border: 2px solid rgba(255,255,255,0.2); }
        .login-header .logo i { font-size: 36px; color: #fff; }
        .login-header h1 { color: #fff; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .login-header p { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; font-weight: 500; }

        .login-card { background: var(--card); border-radius: 24px; padding: 28px 24px; margin: -36px 16px 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); position: relative; z-index: 2; }
        .login-card h2 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .login-card .subtitle { font-size: 14px; color: var(--text-secondary); margin-bottom: 20px; }

        .input-group { position: relative; margin-bottom: 14px; }
        .input-group input { width: 100%; padding: 14px 48px 14px 48px; border: 2px solid var(--border); border-radius: 14px; font-size: 15px; background: #f8fafc; transition: all 0.2s; outline: none; font-family: inherit; color: var(--text); }
        .input-group input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(56,189,248,0.1); }
        .input-group .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 18px; z-index: 3; }
        .input-group .toggle-pass { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; z-index: 3; border: none; background: none; font-size: 18px; padding: 4px; }

        .btn-login { width: 100%; padding: 15px; background: linear-gradient(135deg, #38BDF8, #7DD3FC); color: #fff; border: none; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; font-family: inherit; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 16px rgba(56,189,248,0.3); margin-top: 6px; }
        .btn-login:active { transform: scale(0.98); }

        .register-link { text-align: center; margin-top: 20px; font-size: 14px; color: var(--text-secondary); }
        .register-link a { color: var(--primary); font-weight: 600; text-decoration: none; }

        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; border-radius: 12px; padding: 12px 16px; font-size: 13px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <div class="login-header">
        <div class="logo"><i class="bi bi-steering-wheel"></i></div>
        <h1>TripAny</h1>
        <p>Driver Portal</p>
    </div>

    <div class="login-card">
        <h2>Welcome Back!</h2>
        <p class="subtitle">Login to start accepting rides</p>

        <?php if ($error): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-circle"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <i class="bi bi-phone input-icon"></i>
                <input type="tel" name="phone" placeholder="Phone Number" value="<?= e($_POST['phone'] ?? '') ?>" maxlength="10" required>
            </div>
            <div class="input-group">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" name="password" id="password" placeholder="Password" required>
                <button type="button" class="toggle-pass" onclick="togglePass()"><i class="bi bi-eye" id="eyeIcon"></i></button>
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="register-link">
            New driver? <a href="register.php">Register Now</a>
        </div>
    </div>

    <script>
        function togglePass() {
            const p = document.getElementById('password');
            const i = document.getElementById('eyeIcon');
            if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
            else { p.type = 'password'; i.className = 'bi bi-eye'; }
        }
    </script>
</body>
</html>
