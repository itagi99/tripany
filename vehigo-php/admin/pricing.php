<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_pricing' && isset($_POST['vehicle_id'])) {
        $vid = (int)$_POST['vehicle_id'];
        $existing = $pdo->prepare("SELECT id FROM vehicle_pricing WHERE vehicle_id=?")->execute([$vid]);
        $row = $pdo->prepare("SELECT id FROM vehicle_pricing WHERE vehicle_id=?");
        $row->execute([$vid]);
        $found = $row->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            $pdo->prepare("UPDATE vehicle_pricing SET base_rate=?, min_km=?, extra_km_rate=?, min_km_charge=?, security_deposit=?, cancellation_fee=? WHERE vehicle_id=?")
                ->execute([$_POST['base_rate']??0, $_POST['min_km']??300, $_POST['extra_km_rate']??0, $_POST['min_km_charge']??0, $_POST['security_deposit']??0, $_POST['cancellation_fee']??0, $vid]);
        } else {
            $pdo->prepare("INSERT INTO vehicle_pricing (vehicle_id, base_rate, min_km, extra_km_rate, min_km_charge, security_deposit, cancellation_fee, name) VALUES (?,?,?,?,?,?,?,'Standard')")
                ->execute([$vid, $_POST['base_rate']??0, $_POST['min_km']??300, $_POST['extra_km_rate']??0, $_POST['min_km_charge']??0, $_POST['security_deposit']??0, $_POST['cancellation_fee']??0]);
        }
        $msg = 'Pricing updated';
    }

    if ($action === 'add_package') {
        $pdo->prepare("INSERT INTO pricing_packages (vehicle_id, name, hours, km_limit, price) VALUES (?,?,?,?,?)")
            ->execute([$_POST['vehicle_id'], $_POST['name'], $_POST['hours'], $_POST['km_limit'], $_POST['price']]);
        $msg = 'Package added';
    }

    if ($action === 'delete_package' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM pricing_packages WHERE id=?")->execute([$_POST['id']]);
        $msg = 'Package deleted';
    }
}

$vehicles = $pdo->query("SELECT v.*, c.name as category_name FROM vehicles v LEFT JOIN vehicle_categories c ON v.category_id=c.id ORDER BY v.name")->fetchAll(PDO::FETCH_ASSOC);
$pricingRows = $pdo->query("SELECT * FROM vehicle_pricing")->fetchAll(PDO::FETCH_ASSOC);
$pricingByVehicle = [];
foreach ($pricingRows as $pr) { $pricingByVehicle[$pr['vehicle_id']] = $pr; }
$allPackages = $pdo->query("SELECT p.*, v.name as vehicle_name FROM pricing_packages p LEFT JOIN vehicles v ON p.vehicle_id=v.id ORDER BY v.name, p.hours")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Pricing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Pricing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'includes/navbar.php'; ?>
        <div class="content-wrapper">
            <?php if ($msg): ?>
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i><?= $msg ?></div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h2 class="page-title">Vehicle Pricing</h2>
                    <p class="page-subtitle">Manage per-vehicle pricing (per-km), min km charge, and outstation packages</p>
                </div>
                <button class="btn-premium btn-primary" onclick="document.getElementById('addPkgModal').style.display='flex'">
                    <i class="bi bi-plus-lg"></i> Add Package
                </button>
            </div>

            <!-- Per-Vehicle Pricing -->
            <div class="card-premium">
                <div class="card-header-premium"><h5><i class="bi bi-currency-rupee"></i> Per-Kilometer Pricing</h5></div>
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Category</th>
                                    <th>Base Rate (₹)</th>
                                    <th>Min KM</th>
                                    <th>Min KM Charge (₹)</th>
                                    <th>Extra KM Rate (₹)</th>
                                    <th>Security (₹)</th>
                                    <th>Cancellation Fee (₹)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $v):
                                    $p = $pricingByVehicle[$v['id']] ?? null;
                                ?>
                                <tr>
                                    <td class="cell-primary"><?= e($v['name']) ?></td>
                                    <td style="font-size:0.8125rem;"><?= e($v['category_name'] ?? $v['type']) ?></td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_pricing">
                                        <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                                        <td><input type="number" name="base_rate" class="form-input" style="width:80px;padding:0.35rem 0.5rem;" step="0.01" value="<?= $p['base_rate'] ?? $v['price_per_km'] * 100 ?>" required></td>
                                        <td><input type="number" name="min_km" class="form-input" style="width:70px;padding:0.35rem 0.5rem;" value="<?= $p['min_km'] ?? 300 ?>"></td>
                                        <td><input type="number" name="min_km_charge" class="form-input" style="width:90px;padding:0.35rem 0.5rem;" step="0.01" value="<?= $p['min_km_charge'] ?? 0 ?>"></td>
                                        <td><input type="number" name="extra_km_rate" class="form-input" style="width:80px;padding:0.35rem 0.5rem;" step="0.01" value="<?= $p['extra_km_rate'] ?? $v['price_per_km'] ?>"></td>
                                        <td><input type="number" name="security_deposit" class="form-input" style="width:80px;padding:0.35rem 0.5rem;" step="0.01" value="<?= $p['security_deposit'] ?? 0 ?>"></td>
                                        <td><input type="number" name="cancellation_fee" class="form-input" style="width:80px;padding:0.35rem 0.5rem;" step="0.01" value="<?= $p['cancellation_fee'] ?? 0 ?>"></td>
                                        <td><button type="submit" class="btn-premium btn-primary btn-xs"><i class="bi bi-check-lg"></i></button></td>
                                    </form>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pricing Logic Info -->
            <div class="card-premium" style="margin-top:1rem;">
                <div class="card-header-premium"><h5><i class="bi bi-info-circle"></i> How Pricing Works</h5></div>
                <div class="card-body-premium">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;">
                        <div style="padding:1rem;background:rgba(37,99,235,0.05);border-radius:14px;border:1px solid rgba(37,99,235,0.1);">
                            <h6 style="font-size:0.875rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem;"><i class="bi bi-speedometer2" style="color:var(--primary);"></i> Per-KM Pricing</h6>
                            <p style="font-size:0.75rem;color:var(--text-muted);line-height:1.5;">
                                <strong>Base Rate</strong> = minimum booking charge<br>
                                <strong>Min KM</strong> = minimum km charged (default 300km)<br>
                                If distance &lt; Min KM: charge = max(Base Rate, Min KM × Extra KM Rate)<br>
                                If distance &ge; Min KM: charge = Base Rate + (distance - Min KM) × Extra KM Rate
                            </p>
                        </div>
                        <div style="padding:1rem;background:rgba(34,197,94,0.05);border-radius:14px;border:1px solid rgba(34,197,94,0.1);">
                            <h6 style="font-size:0.875rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem;"><i class="bi bi-clock" style="color:var(--success);"></i> Outstation Packages</h6>
                            <p style="font-size:0.75rem;color:var(--text-muted);line-height:1.5;">
                                Fixed-price packages with time &amp; km limits.<br>
                                e.g., 4hrs / 40km for ₹800<br>
                                Customer sees these as alternative pricing options.
                            </p>
                        </div>
                        <div style="padding:1rem;background:rgba(245,158,11,0.05);border-radius:14px;border:1px solid rgba(245,158,11,0.1);">
                            <h6 style="font-size:0.875rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem;"><i class="bi bi-shield-check" style="color:var(--warning);"></i> Security & Cancellation</h6>
                            <p style="font-size:0.75rem;color:var(--text-muted);line-height:1.5;">
                                Security deposit is refundable.<br>
                                Cancellation fee deducted if cancelled after confirmation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstation Packages -->
            <div class="card-premium" style="margin-top:1rem;">
                <div class="card-header-premium"><h5><i class="bi bi-box-seam"></i> Outstation Packages</h5></div>
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vehicle</th>
                                    <th>Package Name</th>
                                    <th>Hours</th>
                                    <th>KM Limit</th>
                                    <th>Price (₹)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allPackages as $pkg): ?>
                                <tr>
                                    <td>#<?= $pkg['id'] ?></td>
                                    <td class="cell-primary"><?= e($pkg['vehicle_name']) ?></td>
                                    <td><?= e($pkg['name']) ?></td>
                                    <td><?= $pkg['hours'] ?></td>
                                    <td><?= $pkg['km_limit'] ?> km</td>
                                    <td><strong>₹<?= number_format($pkg['price']) ?></strong></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this package?')">
                                            <input type="hidden" name="action" value="delete_package">
                                            <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                            <button type="submit" class="btn-premium btn-danger btn-xs"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($allPackages)): ?>
                                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-box-seam"></i><h6>No packages yet</h6><p>Add outstation packages for your vehicles</p></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Package Modal -->
<div id="addPkgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:520px;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-plus-circle" style="color:var(--primary);"></i> Add Outstation Package</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addPkgModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_package">
                <div class="form-group">
                    <label class="form-label">Vehicle *</label>
                    <select name="vehicle_id" class="form-input" required>
                        <option value="">Select vehicle...</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= e($v['name']) ?> (<?= e($v['type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Package Name *</label>
                    <input type="text" name="name" class="form-input" placeholder="e.g., City Local 4hr" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Hours *</label>
                        <input type="number" name="hours" class="form-input" step="0.5" placeholder="4" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">KM Limit *</label>
                        <input type="number" name="km_limit" class="form-input" placeholder="40" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (₹) *</label>
                        <input type="number" name="price" class="form-input" step="0.01" placeholder="800" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addPkgModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-plus-circle"></i> Add Package</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
