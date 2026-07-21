<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_id'] = 1;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --secondary: #7DD3FC; --bg: #F8FAFC; --card: #FFFFFF; --border: #E2E8F0; --text: #1E293B; --muted: #64748B; --input: #F8FAFC; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #F0F9FF 0%, #BAE6FD 50%, #E0F2FE 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; -webkit-font-smoothing: antialiased; }
        body::before { content: ''; position: absolute; width: 800px; height: 800px; border-radius: 50%; background: radial-gradient(circle, rgba(56,189,248,0.1) 0%, transparent 70%); top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none; }
        body::after { content: ''; position: absolute; width: 600px; height: 600px; border-radius: 50%; background: radial-gradient(circle, rgba(125,211,252,0.08) 0%, transparent 70%); bottom: -200px; right: -200px; pointer-events: none; }
        .login-card { width: 100%; max-width: 420px; background: rgba(255,255,255,0.9); border: 1px solid rgba(56,189,248,0.15); border-radius: 24px; box-shadow: 0 8px 32px rgba(56,189,248,0.12); backdrop-filter: blur(20px); position: relative; overflow: hidden; margin: 1rem; }
        .login-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--primary), var(--secondary), var(--primary)); }
        .login-header { text-align: center; padding: 2.5rem 2rem 1.5rem; }
        .login-logo { width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; font-weight: 900; margin: 0 auto 1.25rem; box-shadow: 0 0 30px rgba(56,189,248,0.3); }
        .login-header h3 { font-size: 1.375rem; font-weight: 800; color: var(--text); margin-bottom: 0.25rem; letter-spacing: -0.02em; }
        .login-header p { font-size: 0.875rem; color: var(--muted); }
        .login-body { padding: 0 2rem 2rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.8125rem; font-weight: 600; color: #64748B; margin-bottom: 0.5rem; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1rem; transition: color 0.2s; }
        .input-wrap input { width: 100%; background: var(--input); border: 1px solid var(--border); border-radius: 12px; padding: 0.75rem 1rem 0.75rem 2.75rem; color: var(--text); font-size: 0.9375rem; font-family: inherit; transition: all 0.2s; outline: none; }
        .input-wrap input::placeholder { color: #94A3B8; }
        .input-wrap input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(56,189,248,0.2); }
        .input-wrap input:focus + i, .input-wrap input:focus ~ i { color: var(--primary); }
        .btn-login { width: 100%; padding: 0.8125rem; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 0.9375rem; font-weight: 700; font-family: inherit; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 0 20px rgba(56,189,248,0.3); }
        .btn-login:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 0 30px rgba(56,189,248,0.4); }
        .btn-login:active { transform: translateY(0); }
        .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #EF4444; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.875rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .login-footer { text-align: center; padding: 0.75rem 2rem 1.5rem; font-size: 0.75rem; color: var(--muted); display: flex; align-items: center; justify-content: center; gap: 0.375rem; }
        .login-footer i { color: #22C55E; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(16px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .login-card { animation: fadeIn 0.5s ease-out; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">T</div>
            <h3>TripAny Admin</h3>
            <p>Sign in to your fleet management dashboard</p>
        </div>
        <div class="login-body">
            <?php if (isset($error)): ?>
                <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i><?= $error ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div class="input-wrap">
                        <input type="text" name="username" placeholder="Enter your username" required autofocus>
                        <i class="bi bi-person"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrap">
                        <input type="password" name="password" placeholder="Enter your password" required>
                        <i class="bi bi-lock"></i>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    Sign In <i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>
        <div class="login-footer">
            <i class="bi bi-shield-check"></i>
            Secured with 256-bit encryption
        </div>
    </div>
</body>
</html>
