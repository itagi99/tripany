<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

$pageTitle = 'Bookings';

$statusFilter = $_GET['status'] ?? '';
$query = "SELECT b.*, u.name as user_name, u.phone as user_phone, v.name as vehicle_name, v.type as vehicle_type
          FROM bookings b
          LEFT JOIN users u ON b.user_id = u.id
          LEFT JOIN vehicles v ON b.vehicle_id = v.id";
$params = [];
if ($statusFilter && $statusFilter !== 'all') {
    $query .= " WHERE b.status = ?";
    $params[] = $statusFilter;
}
$query .= " ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Bookings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'includes/navbar.php'; ?>
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h2 class="page-title">Bookings</h2>
                    <p class="page-subtitle">Manage all customer bookings</p>
                </div>
            </div>

            <div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
                <a href="bookings.php" class="filter-pill <?= !$statusFilter || $statusFilter === 'all' ? 'active' : '' ?>">All</a>
                <a href="bookings.php?status=pending" class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
                <a href="bookings.php?status=confirmed" class="filter-pill <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Confirmed</a>
                <a href="bookings.php?status=completed" class="filter-pill <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
                <a href="bookings.php?status=cancelled" class="filter-pill <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
            </div>

            <div class="card-premium">
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>Booking</th>
                                    <th>Customer</th>
                                    <th>Vehicle</th>
                                    <th>Pickup</th>
                                    <th>Drop</th>
                                    <th>Fare</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                <tr onclick="window.location='booking_detail.php?id=<?= $b['id'] ?>'" style="cursor:pointer;">
                                    <td>
                                        <div class="cell-primary">#<?= e($b['booking_ref'] ?? $b['id']) ?></div>
                                    </td>
                                    <td>
                                        <div class="cell-primary"><?= e($b['user_name'] ?? 'N/A') ?></div>
                                        <div class="cell-muted"><?= e($b['user_phone'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <div class="cell-primary"><?= e($b['vehicle_name'] ?? 'N/A') ?></div>
                                        <div class="cell-muted"><?= e($b['vehicle_type'] ?? '') ?></div>
                                    </td>
                                    <td><i class="bi bi-geo-alt" style="color:var(--success);font-size:0.75rem;"></i> <?= e($b['pickup_location'] ?? 'N/A') ?></td>
                                    <td><i class="bi bi-geo-alt-fill" style="color:var(--danger);font-size:0.75rem;"></i> <?= e($b['drop_location'] ?? 'N/A') ?></td>
                                    <td class="cell-primary">₹<?= number_format($b['total_fare'] ?? 0) ?></td>
                                    <td><span class="badge-premium badge-<?= $b['status'] ?? 'pending' ?>"><?= ucfirst($b['status'] ?? 'pending') ?></span></td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);"><?= e($b['created_at'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($bookings)): ?>
                                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-calendar2-check"></i><h6>No bookings found</h6><p>Bookings will appear here once customers start booking</p></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
