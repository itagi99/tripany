<?php
session_start();
require_once 'db.php';
require_once 'helpers.php';

// Auto-initialize database
try {
    initDB($pdo);
    seedDB($pdo);
} catch (Exception $e) {
    // Already initialized
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny - On-Demand Vehicle Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #38BDF8; --primary-dark: #0EA5E9; --success: #22C55E; --dark: #0f172a; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #F0F9FF 0%, #BAE6FD 50%, #E0F2FE 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #1E293B; }
        .container { text-align: center; padding: 40px 20px; max-width: 800px; }
        .logo { font-size: 3rem; font-weight: 900; margin-bottom: 8px; background: linear-gradient(135deg, #38BDF8, #7DD3FC); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .tagline { font-size: 1.1rem; color: #64748B; margin-bottom: 48px; font-weight: 500; }
        .apps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .app-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 32px 24px; text-decoration: none; color: #1E293B; transition: all 0.3s; box-shadow: 0 4px 16px rgba(56,189,248,0.1); }
        .app-card:hover { transform: translateY(-6px); border-color: rgba(56,189,248,0.3); box-shadow: 0 12px 40px rgba(56,189,248,0.2); }
        .app-icon { font-size: 3rem; margin-bottom: 16px; }
        .app-title { font-size: 1.3rem; font-weight: 800; margin-bottom: 8px; color: #1E293B; }
        .app-desc { font-size: 0.85rem; color: #64748B; font-weight: 500; }
        .app-card.customer .app-icon { color: var(--primary); }
        .app-card.driver .app-icon { color: var(--warning); }
        .app-card.admin .app-icon { color: var(--primary-dark); }
        .test-accounts { background: #FFFFFF; border-radius: 12px; padding: 20px; border: 1px solid #E2E8F0; box-shadow: 0 2px 8px rgba(56,189,248,0.06); }
        .test-accounts h4 { font-size: 0.9rem; color: #64748B; margin-bottom: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .account-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; text-align: left; }
        .account-item { background: #F8FAFC; border-radius: 8px; padding: 12px; border: 1px solid #E2E8F0; }
        .account-item .role { font-size: 0.75rem; font-weight: 800; color: #64748B; text-transform: uppercase; margin-bottom: 4px; }
        .account-item .cred { font-size: 0.85rem; font-weight: 600; color: #1E293B; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">TripAny</div>
        <div class="tagline">On-Demand Vehicle Rental Platform</div>
        
        <div class="apps">
            <a href="mobile/" class="app-card customer">
                <div class="app-icon">🚗</div>
                <div class="app-title">Customer App</div>
                <div class="app-desc">Book rides, track trips, manage profile</div>
            </a>
            <a href="driver/" class="app-card driver">
                <div class="app-icon">🛵</div>
                <div class="app-title">Driver App</div>
                <div class="app-desc">Accept rides, manage earnings, go online</div>
            </a>
            <a href="admin/" class="app-card admin">
                <div class="app-icon">📊</div>
                <div class="app-title">Admin Panel</div>
                <div class="app-desc">Manage vehicles, drivers, bookings, offers</div>
            </a>
        </div>
        
        <div class="test-accounts">
            <h4>Test Accounts</h4>
            <div class="account-grid">
                <div class="account-item">
                    <div class="role">Customer</div>
                    <div class="cred">9876543210 / user123</div>
                </div>
                <div class="account-item">
                    <div class="role">Driver</div>
                    <div class="cred">8765432100 / driver123</div>
                </div>
                <div class="account-item">
                    <div class="role">Admin</div>
                    <div class="cred">admin / admin123</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
