<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO coupons (code, description, discount_percent, discount_amount, min_booking_amount, max_uses, valid_from, valid_to, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            strtoupper($_POST['code'] ?? ''),
            $_POST['description'] ?? '',
            $_POST['discount_percent'] ?? 0,
            $_POST['discount_amount'] ?? 0,
            $_POST['min_booking_amount'] ?? 0,
            $_POST['max_uses'] ?? 100,
            $_POST['valid_from'] ?? date('Y-m-d'),
            $_POST['valid_to'] ?? date('Y-m-d', strtotime('+30 days')),
            isset($_POST['active']) ? 1 : 0
        ]);
        header("Location: coupons.php?success=added");
        exit;
    }
    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([$_POST['id']]);
        header("Location: coupons.php?success=deleted");
        exit;
    }
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Coupons';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Coupons</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'includes/navbar.php'; ?>
        <div class="content-wrapper">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i>Coupon <?= ucfirst($_GET['success']) ?> successfully!</div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h2 class="page-title">Coupons</h2>
                    <p class="page-subtitle">Manage discount coupons for customers</p>
                </div>
                <button class="btn-premium btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                    <i class="bi bi-plus-lg"></i> Add Coupon
                </button>
            </div>

            <div class="card-premium">
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Discount %</th>
                                    <th>Discount ₹</th>
                                    <th>Min Amount</th>
                                    <th>Uses</th>
                                    <th>Valid From</th>
                                    <th>Valid To</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coupons as $c): ?>
                                <tr>
                                    <td>#<?= e($c['id']) ?></td>
                                    <td><span style="font-family:monospace;font-size:0.9375rem;font-weight:700;color:var(--primary);background:rgba(37,99,235,0.1);padding:0.25rem 0.5rem;border-radius:6px;"><?= e($c['code']) ?></span></td>
                                    <td><?= $c['discount_percent'] > 0 ? $c['discount_percent'].'%' : '—' ?></td>
                                    <td><?= $c['discount_amount'] > 0 ? '₹'.$c['discount_amount'] : '—' ?></td>
                                    <td>₹<?= number_format($c['min_booking_amount'] ?? 0) ?></td>
                                    <td><span class="badge-premium badge-info"><?= $c['used_count'] ?? 0 ?> / <?= $c['max_uses'] ?? '∞' ?></span></td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);"><?= e($c['valid_from'] ?? '') ?></td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);"><?= e($c['valid_to'] ?? '') ?></td>
                                    <td><span class="badge-premium badge-<?= $c['active'] ? 'active' : 'inactive' ?>"><?= $c['active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this coupon?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn-premium btn-danger btn-xs"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($coupons)): ?>
                                <tr><td colspan="10"><div class="empty-state"><i class="bi bi-ticket-perforated"></i><h6>No coupons found</h6><p>Create your first coupon to get started</p></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:520px;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-ticket-perforated" style="color:var(--primary);"></i> Add Coupon</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Coupon Code *</label>
                    <input type="text" name="code" class="form-input" placeholder="e.g., SAVE20" style="text-transform:uppercase;" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input" placeholder="Short description of the coupon">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" name="discount_percent" class="form-input" min="0" max="100" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount (₹)</label>
                        <input type="number" name="discount_amount" class="form-input" min="0" value="0">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Min Booking Amount (₹)</label>
                        <input type="number" name="min_booking_amount" class="form-input" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Uses</label>
                        <input type="number" name="max_uses" class="form-input" value="100">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Valid From</label>
                        <input type="date" name="valid_from" class="form-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid To</label>
                        <input type="date" name="valid_to" class="form-input" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-check"><input type="checkbox" name="active" checked> Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-plus-circle"></i> Add Coupon</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
