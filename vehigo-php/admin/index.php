<?php
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

$pageTitle = 'Dashboard';

$totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total_fare), 0) FROM bookings WHERE status='completed'")->fetchColumn();
$activeDrivers = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='online'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE is_active=1")->fetchColumn();
$bookedVehicles = $totalVehicles - $pdo->query("SELECT COUNT(*) FROM vehicles WHERE is_active=1 AND id NOT IN (SELECT vehicle_id FROM bookings WHERE status='confirmed')")->fetchColumn();
$pendingBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$completedToday = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='completed' AND date(created_at) = date('now')")->fetchColumn();

$monthlyRevenue = $pdo->query("
    SELECT strftime('%Y-%m', created_at) as month, SUM(total_fare) as revenue 
    FROM bookings 
    WHERE status='completed' AND created_at >= date('now', '-6 months')
    GROUP BY month 
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

$months = [];
$revenues = [];
foreach ($monthlyRevenue as $row) {
    $months[] = date('M', strtotime($row['month'] . '-01'));
    $revenues[] = $row['revenue'];
}
if (empty($months)) {
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $revenues = [0, 0, 0, 0, 0, 0];
}

$recentBookings = $pdo->query("
    SELECT b.*, u.name as user_name, v.name as vehicle_name, v.type as vehicle_type
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN vehicles v ON b.vehicle_id = v.id
    ORDER BY b.created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$topDrivers = $pdo->query("SELECT * FROM drivers ORDER BY rating DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
$onlineDrivers = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='online'")->fetchColumn();
$offlineDrivers = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='offline'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'includes/navbar.php'; ?>
            <div class="content-wrapper">
                <div class="stats-grid">
                    <div class="stat-card" style="--card-accent: #2563EB;">
                        <div class="stat-card-header">
                            <div class="stat-icon blue"><i class="bi bi-currency-rupee"></i></div>
                            <span class="stat-change up"><i class="bi bi-arrow-up-short"></i>12.5%</span>
                        </div>
                        <div class="stat-value">₹<?= number_format($totalRevenue, 0) ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-mini-chart">
                            <div class="bar" style="height:40%"></div><div class="bar" style="height:65%"></div>
                            <div class="bar" style="height:45%"></div><div class="bar" style="height:80%"></div>
                            <div class="bar" style="height:60%"></div><div class="bar" style="height:90%"></div>
                            <div class="bar" style="height:70%"></div>
                        </div>
                    </div>
                    <div class="stat-card" style="--card-accent: #06B6D4;">
                        <div class="stat-card-header">
                            <div class="stat-icon cyan"><i class="bi bi-calendar2-check"></i></div>
                            <span class="stat-change up"><i class="bi bi-arrow-up-short"></i>8.2%</span>
                        </div>
                        <div class="stat-value"><?= $totalBookings ?></div>
                        <div class="stat-label">Total Bookings</div>
                        <div class="stat-mini-chart">
                            <div class="bar" style="height:55%"></div><div class="bar" style="height:70%"></div>
                            <div class="bar" style="height:50%"></div><div class="bar" style="height:85%"></div>
                            <div class="bar" style="height:65%"></div><div class="bar" style="height:75%"></div>
                            <div class="bar" style="height:90%"></div>
                        </div>
                    </div>
                    <div class="stat-card" style="--card-accent: #22C55E;">
                        <div class="stat-card-header">
                            <div class="stat-icon green"><i class="bi bi-person-badge"></i></div>
                            <span class="stat-change up"><i class="bi bi-arrow-up-short"></i>3</span>
                        </div>
                        <div class="stat-value"><?= $activeDrivers ?></div>
                        <div class="stat-label">Drivers Online</div>
                        <div class="stat-mini-chart">
                            <div class="bar" style="height:70%"></div><div class="bar" style="height:85%"></div>
                            <div class="bar" style="height:60%"></div><div class="bar" style="height:95%"></div>
                            <div class="bar" style="height:80%"></div><div class="bar" style="height:75%"></div>
                            <div class="bar" style="height:90%"></div>
                        </div>
                    </div>
                    <div class="stat-card" style="--card-accent: #F59E0B;">
                        <div class="stat-card-header">
                            <div class="stat-icon yellow"><i class="bi bi-car-front"></i></div>
                            <span class="stat-change up"><i class="bi bi-arrow-up-short"></i><?= $totalVehicles ?></span>
                        </div>
                        <div class="stat-value"><?= $totalVehicles ?></div>
                        <div class="stat-label">Active Vehicles</div>
                        <div class="stat-mini-chart">
                            <div class="bar" style="height:60%"></div><div class="bar" style="height:75%"></div>
                            <div class="bar" style="height:55%"></div><div class="bar" style="height:85%"></div>
                            <div class="bar" style="height:70%"></div><div class="bar" style="height:65%"></div>
                            <div class="bar" style="height:80%"></div>
                        </div>
                    </div>
                    <div class="stat-card" style="--card-accent: #EF4444;">
                        <div class="stat-card-header">
                            <div class="stat-icon red"><i class="bi bi-hourglass-split"></i></div>
                        </div>
                        <div class="stat-value"><?= $pendingBookings ?></div>
                        <div class="stat-label">Pending Approvals</div>
                        <div class="stat-mini-chart">
                            <div class="bar" style="height:30%"></div><div class="bar" style="height:45%"></div>
                            <div class="bar" style="height:35%"></div><div class="bar" style="height:50%"></div>
                            <div class="bar" style="height:40%"></div><div class="bar" style="height:25%"></div>
                            <div class="bar" style="height:55%"></div>
                        </div>
                    </div>
                    <div class="stat-card" style="--card-accent: #A855F7;">
                        <div class="stat-card-header">
                            <div class="stat-icon purple"><i class="bi bi-people"></i></div>
                            <span class="stat-change up"><i class="bi bi-arrow-up-short"></i>24</span>
                        </div>
                        <div class="stat-value"><?= $totalUsers ?></div>
                        <div class="stat-label">Total Customers</div>
                        <div class="stat-mini-chart">
                            <div class="bar" style="height:45%"></div><div class="bar" style="height:60%"></div>
                            <div class="bar" style="height:50%"></div><div class="bar" style="height:75%"></div>
                            <div class="bar" style="height:65%"></div><div class="bar" style="height:80%"></div>
                            <div class="bar" style="height:90%"></div>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.5rem;">
                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-graph-up"></i> Revenue Overview</h5>
                            <span style="font-size:0.75rem;color:var(--text-muted);">Last 6 Months</span>
                        </div>
                        <div class="card-body-premium">
                            <div class="chart-container">
                                <canvas id="revenueChart" height="220"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-lightning"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body-premium" style="display:flex;flex-direction:column;gap:0.75rem;">
                            <a href="vehicles.php" class="quick-action-card">
                                <div class="action-icon" style="background:rgba(37,99,235,0.12);color:#2563EB;"><i class="bi bi-car-front"></i></div>
                                <div><div style="font-weight:600;font-size:0.875rem;">Fleet Management</div><div style="font-size:0.75rem;color:var(--text-muted);">Manage all vehicles</div></div>
                            </a>
                            <a href="drivers.php" class="quick-action-card">
                                <div class="action-icon" style="background:rgba(34,197,94,0.12);color:#22C55E;"><i class="bi bi-person-badge"></i></div>
                                <div><div style="font-weight:600;font-size:0.875rem;">Manage Drivers</div><div style="font-size:0.75rem;color:var(--text-muted);">View driver status</div></div>
                            </a>
                            <a href="bookings.php" class="quick-action-card">
                                <div class="action-icon" style="background:rgba(245,158,11,0.12);color:#F59E0B;"><i class="bi bi-calendar2-check"></i></div>
                                <div><div style="font-weight:600;font-size:0.875rem;">All Bookings</div><div style="font-size:0.75rem;color:var(--text-muted);">View and manage</div></div>
                            </a>
                            <a href="coupons.php" class="quick-action-card">
                                <div class="action-icon" style="background:rgba(168,85,247,0.12);color:#a855f7;"><i class="bi bi-ticket"></i></div>
                                <div><div style="font-weight:600;font-size:0.875rem;">Coupons & Offers</div><div style="font-size:0.75rem;color:var(--text-muted);">Create discounts</div></div>
                            </a>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:1.5rem;">
                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-map"></i> Live Fleet Tracking</h5>
                            <div style="display:flex;gap:0.5rem;">
                                <span class="badge-premium badge-online"> <?= $onlineDrivers ?> Online</span>
                                <span class="badge-premium badge-offline"> <?= $offlineDrivers ?> Offline</span>
                            </div>
                        </div>
                        <div class="map-placeholder">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>Live vehicle tracking — Google Maps integration ready</span>
                            <div style="position:absolute;bottom:1rem;left:1rem;display:flex;gap:0.5rem;">
                                <span class="badge-premium badge-active">Live</span>
                                <span class="badge-premium badge-info">Last updated: just now</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-clock-history"></i> Recent Bookings</h5>
                            <a href="bookings.php" class="btn-premium btn-ghost btn-sm">View All <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="table-scroll" style="max-height:360px;overflow-y:auto;">
                            <table class="table-premium">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Vehicle</th>
                                        <th>Fare</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentBookings)): ?>
                                    <tr><td colspan="4" class="empty-state"><i class="bi bi-inbox"></i><p>No bookings yet</p></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($recentBookings as $b): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-primary"><?= e($b['user_name'] ?? 'N/A') ?></div>
                                            <div class="cell-muted">#<?= e($b['booking_ref'] ?? $b['id']) ?></div>
                                        </td>
                                        <td>
                                            <div class="cell-primary"><?= e($b['vehicle_name'] ?? 'N/A') ?></div>
                                            <div class="cell-muted"><?= e($b['vehicle_type'] ?? '') ?></div>
                                        </td>
                                        <td class="cell-primary">₹<?= number_format($b['total_fare'] ?? 0) ?></td>
                                        <td><span class="badge-premium badge-<?= $b['status'] ?? 'pending' ?>"><?= ucfirst($b['status'] ?? 'pending') ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-premium">
                        <div class="card-header-premium">
                            <h5><i class="bi bi-star-fill"></i> Top Drivers</h5>
                            <a href="drivers.php" class="btn-premium btn-ghost btn-sm">View All <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="card-body-premium" style="display:flex;flex-direction:column;gap:0.75rem;">
                            <?php if (empty($topDrivers)): ?>
                            <div class="empty-state"><i class="bi bi-person-badge"></i><p>No drivers yet</p></div>
                            <?php else: ?>
                            <?php foreach ($topDrivers as $d): ?>
                            <div style="display:flex;align-items:center;gap:1rem;padding:0.75rem;border-radius:12px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
                                <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#06B6D4);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.875rem;flex-shrink:0;"><?= strtoupper(substr($d['name'],0,1)) ?></div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;font-size:0.875rem;color:var(--text-primary);"><?= e($d['name']) ?></div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= e($d['vehicle_type'] ?? '') ?> · <?= e($d['vehicle_number'] ?? '') ?></div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="display:flex;align-items:center;gap:4px;font-weight:600;font-size:0.875rem;color:#F59E0B;"><i class="bi bi-star-fill" style="font-size:0.75rem;"></i><?= number_format($d['rating'] ?? 0, 1) ?></div>
                                    <span class="badge-premium badge-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?= json_encode($revenues) ?>,
                    borderColor: '#2563EB',
                    backgroundColor: (ctx) => {
                        const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220);
                        g.addColorStop(0, 'rgba(37,99,235,0.15)');
                        g.addColorStop(1, 'rgba(37,99,235,0)');
                        return g;
                    },
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointBackgroundColor: '#2563EB',
                    pointBorderColor: '#111827',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                        ticks: { color: '#64748B', font: { family: 'Inter', size: 11 }, callback: v => '₹' + v }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748B', font: { family: 'Inter', size: 11 } }
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }
    </script>
</body>
</html>
