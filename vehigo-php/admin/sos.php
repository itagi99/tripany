<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve' && isset($_POST['id'])) {
    $pdo->prepare("UPDATE sos_alerts SET status='resolved' WHERE id=?")->execute([$_POST['id']]);
    header("Location: sos.php?success=resolved");
    exit;
}

$alerts = $pdo->query("
    SELECT s.*, b.booking_ref, u.name as user_name, u.phone as user_phone, d.name as driver_name, d.phone as driver_phone
    FROM sos_alerts s
    LEFT JOIN bookings b ON s.booking_id = b.id
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN drivers d ON s.driver_id = d.id
    ORDER BY s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'SOS Alerts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — SOS Alerts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .sos-pulse { animation: sosPulse 1.5s ease-in-out infinite; display:inline-block; }
        @keyframes sosPulse { 0%,100% { opacity:1; } 50% { opacity:0.3; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'includes/navbar.php'; ?>
        <div class="content-wrapper">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i>Alert resolved!</div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h2 class="page-title">SOS Alerts</h2>
                    <p class="page-subtitle">Emergency alerts from customers and drivers</p>
                </div>
            </div>

            <div class="card-premium">
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>From</th>
                                    <th>Message</th>
                                    <th>Booking</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $a): ?>
                                <tr style="<?= $a['status'] === 'pending' ? 'background:rgba(239,68,68,0.03);' : '' ?>">
                                    <td>#<?= $a['id'] ?></td>
                                    <td>
                                        <?php if ($a['status'] === 'pending'): ?>
                                            <span class="sos-pulse"><i class="bi bi-exclamation-triangle-fill" style="color:#EF4444;font-size:1.25rem;"></i></span>
                                        <?php endif; ?>
                                        <span class="badge-premium badge-<?= $a['alert_type'] === 'emergency' ? 'danger' : 'warning' ?>">
                                            <?= ucfirst($a['alert_type'] ?? 'Emergency') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;font-size:0.8125rem;"><?= e($a['user_name'] ?? $a['driver_name'] ?? 'Unknown') ?></div>
                                        <div style="font-size:0.6875rem;color:var(--text-muted);"><?= e($a['user_phone'] ?? $a['driver_phone'] ?? '') ?></div>
                                    </td>
                                    <td style="font-size:0.8125rem;max-width:250px;"><?= e($a['message'] ?? 'No message') ?></td>
                                    <td style="font-size:0.8125rem;"><?= e($a['booking_ref'] ?? 'N/A') ?></td>
                                    <td style="font-size:0.75rem;color:var(--text-muted);"><?= e($a['created_at']) ?></td>
                                    <td>
                                        <span class="badge-premium badge-<?= $a['status'] === 'resolved' ? 'success' : ($a['status'] === 'pending' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($a['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($a['status'] === 'pending'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn-premium btn-success btn-xs"><i class="bi bi-check2-circle"></i> Resolve</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($alerts)): ?>
                                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-shield-check" style="color:var(--success);font-size:2.5rem;"></i><h6>No SOS Alerts</h6><p>All clear! No emergencies reported.</p></div></td></tr>
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
