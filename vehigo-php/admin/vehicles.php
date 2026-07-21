<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

function handleVehicleUpload($field, $subdir, $default = '') {
    if (!empty($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) return $default;
        $targetDir = __DIR__ . '/../uploads/' . $subdir . '/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        move_uploaded_file($_FILES[$field]['tmp_name'], $targetDir . $filename);
        return '/uploads/' . $subdir . '/' . $filename;
    }
    return $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO vehicles (category_id, name, brand, model, year, type, fuel_type, transmission, seats, bags, price_per_day, price_per_km, image, description, features, inclusions, exclusions, facilities, terms, cancellation_policy, is_active, is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $_POST['category_id'] ?? 1,
            $_POST['name'] ?? '',
            $_POST['brand'] ?? '',
            $_POST['model'] ?? '',
            $_POST['year'] ?? date('Y'),
            $_POST['type'] ?? 'Sedan',
            $_POST['fuel_type'] ?? 'Petrol',
            $_POST['transmission'] ?? 'Manual',
            $_POST['seats'] ?? 5,
            $_POST['bags'] ?? 2,
            $_POST['price_per_day'] ?? 0,
            $_POST['price_per_km'] ?? 0,
            $_POST['image'] ?? '',
            $_POST['description'] ?? '',
            $_POST['features'] ?? '',
            $_POST['inclusions'] ?? '',
            $_POST['exclusions'] ?? '',
            $_POST['facilities'] ?? '',
            $_POST['terms'] ?? '',
            $_POST['cancellation_policy'] ?? '',
            isset($_POST['is_active']) ? 1 : 0,
            isset($_POST['is_featured']) ? 1 : 0
        ]);
        $newId = $pdo->lastInsertId();
        if (!empty($_POST['gallery'])) {
            $urls = array_filter(array_map('trim', explode("\n", $_POST['gallery'])));
            $ins = $pdo->prepare("INSERT INTO vehicle_gallery (vehicle_id, image_url, sort_order) VALUES (?,?,?)");
            foreach ($urls as $i => $url) { $ins->execute([$newId, $url, $i]); }
        }
        header("Location: vehicles.php?success=added");
        exit;
    }
    if ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE vehicles SET category_id=?, name=?, brand=?, model=?, year=?, type=?, fuel_type=?, transmission=?, seats=?, bags=?, price_per_day=?, price_per_km=?, image=?, description=?, features=?, inclusions=?, exclusions=?, facilities=?, terms=?, cancellation_policy=?, is_active=?, is_featured=? WHERE id=?");
        $stmt->execute([
            $_POST['category_id'] ?? 1,
            $_POST['name'] ?? '',
            $_POST['brand'] ?? '',
            $_POST['model'] ?? '',
            $_POST['year'] ?? date('Y'),
            $_POST['type'] ?? 'Sedan',
            $_POST['fuel_type'] ?? 'Petrol',
            $_POST['transmission'] ?? 'Manual',
            $_POST['seats'] ?? 5,
            $_POST['bags'] ?? 2,
            $_POST['price_per_day'] ?? 0,
            $_POST['price_per_km'] ?? 0,
            $_POST['image'] ?? '',
            $_POST['description'] ?? '',
            $_POST['features'] ?? '',
            $_POST['inclusions'] ?? '',
            $_POST['exclusions'] ?? '',
            $_POST['facilities'] ?? '',
            $_POST['terms'] ?? '',
            $_POST['cancellation_policy'] ?? '',
            isset($_POST['is_active']) ? 1 : 0,
            isset($_POST['is_featured']) ? 1 : 0,
            $_POST['vehicle_id'] ?? 0
        ]);
        $editId = $_POST['vehicle_id'] ?? 0;
        if ($editId) {
            $pdo->prepare("DELETE FROM vehicle_gallery WHERE vehicle_id=?")->execute([$editId]);
            if (!empty($_POST['gallery'])) {
                $urls = array_filter(array_map('trim', explode("\n", $_POST['gallery'])));
                $ins = $pdo->prepare("INSERT INTO vehicle_gallery (vehicle_id, image_url, sort_order) VALUES (?,?,?)");
                foreach ($urls as $i => $url) { $ins->execute([$editId, $url, $i]); }
            }
        }
        header("Location: vehicles.php?success=updated");
        exit;
    }
    if ($action === 'delete' && isset($_POST['vehicle_id'])) {
        $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$_POST['vehicle_id']]);
        header("Location: vehicles.php?success=deleted");
        exit;
    }
}

$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';
$query = "SELECT v.*, vc.name as category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON v.category_id = vc.id WHERE 1=1";
$params = [];
if ($filter) { $query .= " AND v.type = ?"; $params[] = $filter; }
if ($search) { $query .= " AND (v.name LIKE ? OR v.model LIKE ? OR v.brand LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
$query .= " ORDER BY v.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$activeVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE is_active=1")->fetchColumn();
$featuredVehicles = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE is_featured=1")->fetchColumn();

$categories = $pdo->query("SELECT * FROM vehicle_categories WHERE active=1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Fleet Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Fleet Management</title>
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
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i>Vehicle <?= ucfirst($_GET['success']) ?> successfully!</div>
            <?php endif; ?>

            <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon blue"><i class="bi bi-car-front"></i></div></div><div class="stat-value"><?= $totalVehicles ?></div><div class="stat-label">Total Vehicles</div></div>
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="bi bi-check-circle"></i></div></div><div class="stat-value"><?= $activeVehicles ?></div><div class="stat-label">Active</div></div>
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon yellow"><i class="bi bi-star"></i></div></div><div class="stat-value"><?= $featuredVehicles ?></div><div class="stat-label">Featured</div></div>
            </div>

            <div class="page-header">
                <div>
                    <h2 class="page-title">Fleet Management</h2>
                    <p class="page-subtitle">Manage all vehicles in your fleet</p>
                </div>
                <button class="btn-premium btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                    <i class="bi bi-plus-lg"></i> Add Vehicle
                </button>
            </div>

            <div style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap;">
                <a href="vehicles.php" class="filter-pill <?= !$filter ? 'active' : '' ?>">All</a>
                <a href="vehicles.php?filter=SUV" class="filter-pill <?= $filter==='SUV' ? 'active' : '' ?>">SUV</a>
                <a href="vehicles.php?filter=Sedan" class="filter-pill <?= $filter==='Sedan' ? 'active' : '' ?>">Sedan</a>
                <a href="vehicles.php?filter=Hatchback" class="filter-pill <?= $filter==='Hatchback' ? 'active' : '' ?>">Hatchback</a>
                <a href="vehicles.php?filter=Bike" class="filter-pill <?= $filter==='Bike' ? 'active' : '' ?>">Bike</a>
                <a href="vehicles.php?filter=Scooter" class="filter-pill <?= $filter==='Scooter' ? 'active' : '' ?>">Scooter</a>
            </div>

            <div class="card-premium">
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Category</th>
                                    <th>Seats</th>
                                    <th>Price/Day</th>
                                    <th>Price/Km</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $v): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:0.75rem;">
                                            <?php if ($v['image']): ?>
                                                <img src="<?= e($v['image']) ?>" alt="" style="width:48px;height:36px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                                            <?php else: ?>
                                                <div style="width:48px;height:36px;border-radius:8px;background:var(--bg-input);display:flex;align-items:center;justify-content:center;border:1px solid var(--border);"><i class="bi bi-car-front" style="color:var(--text-muted);font-size:0.75rem;"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="cell-primary"><?= e($v['name']) ?></div>
                                                <div class="cell-muted"><?= e($v['brand']) ?> · <?= e($v['model']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge-premium badge-info"><?= e($v['category_name'] ?? $v['type']) ?></span></td>
                                    <td><?= e($v['seats']) ?> <i class="bi bi-person" style="font-size:0.625rem;color:var(--text-muted);"></i></td>
                                    <td class="cell-primary">₹<?= number_format($v['price_per_day']) ?></td>
                                    <td>₹<?= number_format($v['price_per_km'], 1) ?></td>
                                    <td><div class="stars"><?php for($i=1;$i<=5;$i++): ?><i class="bi bi-star-fill <?= $i > round($v['rating']) ? 'empty' : '' ?>"></i><?php endfor; ?></div></td>
                                    <td>
                                        <?php if ($v['is_active']): ?>
                                            <span class="badge-premium badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge-premium badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                        <?php if ($v['is_featured']): ?>
                                            <span class="badge-premium badge-warning" style="margin-left:4px;"><i class="bi bi-star-fill" style="font-size:0.5rem;"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-premium btn-ghost btn-xs" onclick='editVehicle(<?= htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-pencil"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this vehicle?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                                            <button type="submit" class="btn-premium btn-danger btn-xs"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($vehicles)): ?>
                                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-car-front"></i><h6>No vehicles found</h6><p>Add your first vehicle to get started</p></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;overflow-y:auto;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:600px;margin:auto;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-car-front" style="color:var(--primary);"></i> Add Vehicle</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type *</label>
                        <select name="type" class="form-select" required>
                            <option>Hatchback</option><option>Sedan</option><option>SUV</option><option>Bike</option><option>Scooter</option><option>Auto Rickshaw</option><option>Mini Truck</option><option>Luxury Cars</option><option>Electric Cars</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" class="form-input" placeholder="e.g., Maruti Swift Dzire" required></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Brand</label><input type="text" name="brand" class="form-input" placeholder="e.g., Maruti"></div>
                    <div class="form-group"><label class="form-label">Model *</label><input type="text" name="model" class="form-input" placeholder="e.g., Swift" required></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Year</label><input type="number" name="year" class="form-input" value="<?= date('Y') ?>"></div>
                    <div class="form-group"><label class="form-label">Fuel Type</label><select name="fuel_type" class="form-select"><option>Petrol</option><option>Diesel</option><option>CNG</option><option>Electric</option></select></div>
                    <div class="form-group"><label class="form-label">Transmission</label><select name="transmission" class="form-select"><option>Manual</option><option>Automatic</option></select></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Seats</label><input type="number" name="seats" class="form-input" value="5"></div>
                    <div class="form-group"><label class="form-label">Bags</label><input type="number" name="bags" class="form-input" value="2"></div>
                    <div class="form-group"><label class="form-label">Price/Day (₹) *</label><input type="number" name="price_per_day" class="form-input" step="0.01" required></div>
                </div>
                <div class="form-group"><label class="form-label">Price/Km (₹)</label><input type="number" name="price_per_km" class="form-input" step="0.01" value="0"></div>
                <div class="form-group"><label class="form-label">Image URL</label><input type="url" name="image" class="form-input" placeholder="https://images.unsplash.com/photo-..."></div>
                <div class="form-group"><label class="form-label">Gallery Images (one URL per line)</label><textarea name="gallery" class="form-input" rows="3" placeholder="https://images.unsplash.com/photo-..."></textarea></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-input" rows="2" placeholder="Vehicle description..."></textarea></div>
                <div class="form-group"><label class="form-label">Features</label><input type="text" name="features" class="form-input" placeholder="AC|Power Windows|Bluetooth|..."></div>
                <div class="form-group"><label class="form-label">Inclusions</label><textarea name="inclusions" class="form-input" rows="2" placeholder="Insurance|Roadside Assistance|... (pipe | separated)"></textarea></div>
                <div class="form-group"><label class="form-label">Exclusions</label><textarea name="exclusions" class="form-input" rows="2" placeholder="Toll tax|Parking|... (pipe | separated)"></textarea></div>
                <div class="form-group"><label class="form-label">Facilities</label><textarea name="facilities" class="form-input" rows="2" placeholder="GPS|Music System|... (pipe | separated)"></textarea></div>
                <div class="form-group"><label class="form-label">Terms & Conditions</label><textarea name="terms" class="form-input" rows="3" placeholder="Valid driving license required|Fuel charges excluded|... (pipe | separated)"></textarea></div>
                <div class="form-group"><label class="form-label">Cancellation Policy</label><textarea name="cancellation_policy" class="form-input" rows="3" placeholder="Enter cancellation policy text..."></textarea></div>
                <div style="display:flex;gap:2rem;">
                    <label class="form-check"><input type="checkbox" name="is_active" checked> Active</label>
                    <label class="form-check"><input type="checkbox" name="is_featured"> Featured</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-plus-circle"></i> Add Vehicle</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;overflow-y:auto;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:600px;margin:auto;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil-square" style="color:var(--primary);"></i> Edit Vehicle</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=editModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Category</label><select name="category_id" id="edit_category_id" class="form-select"><?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Type</label><select name="type" id="edit_type" class="form-select"><option>Hatchback</option><option>Sedan</option><option>SUV</option><option>Bike</option><option>Scooter</option><option>Auto Rickshaw</option><option>Mini Truck</option><option>Luxury Cars</option><option>Electric Cars</option></select></div>
                </div>
                <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" id="edit_name" class="form-input"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Brand</label><input type="text" name="brand" id="edit_brand" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Model</label><input type="text" name="model" id="edit_model" class="form-input"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Year</label><input type="number" name="year" id="edit_year" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Fuel Type</label><select name="fuel_type" id="edit_fuel_type" class="form-select"><option>Petrol</option><option>Diesel</option><option>CNG</option><option>Electric</option></select></div>
                    <div class="form-group"><label class="form-label">Transmission</label><select name="transmission" id="edit_transmission" class="form-select"><option>Manual</option><option>Automatic</option></select></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Seats</label><input type="number" name="seats" id="edit_seats" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Bags</label><input type="number" name="bags" id="edit_bags" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Price/Day (₹)</label><input type="number" name="price_per_day" id="edit_price_per_day" class="form-input" step="0.01"></div>
                </div>
                <div class="form-group"><label class="form-label">Price/Km (₹)</label><input type="number" name="price_per_km" id="edit_price_per_km" class="form-input" step="0.01"></div>
                <div class="form-group"><label class="form-label">Image URL</label><input type="url" name="image" id="edit_image" class="form-input" placeholder="https://images.unsplash.com/photo-..."></div>
                <div class="form-group"><label class="form-label">Gallery Images (one URL per line)</label><textarea name="gallery" id="edit_gallery" class="form-input" rows="3" placeholder="https://images.unsplash.com/photo-..."></textarea><div id="edit-gallery-preview" style="display:flex;gap:0.375rem;margin-top:0.375rem;flex-wrap:wrap;"></div></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="edit_description" class="form-input" rows="2"></textarea></div>
                <div class="form-group"><label class="form-label">Features</label><input type="text" name="features" id="edit_features" class="form-input"></div>
                <div class="form-group"><label class="form-label">Inclusions</label><textarea name="inclusions" id="edit_inclusions" class="form-input" rows="2"></textarea></div>
                <div class="form-group"><label class="form-label">Exclusions</label><textarea name="exclusions" id="edit_exclusions" class="form-input" rows="2"></textarea></div>
                <div class="form-group"><label class="form-label">Facilities</label><textarea name="facilities" id="edit_facilities" class="form-input" rows="2"></textarea></div>
                <div class="form-group"><label class="form-label">Terms & Conditions</label><textarea name="terms" id="edit_terms" class="form-input" rows="3"></textarea></div>
                <div class="form-group"><label class="form-label">Cancellation Policy</label><textarea name="cancellation_policy" id="edit_cancellation_policy" class="form-input" rows="3"></textarea></div>
                <div style="display:flex;gap:2rem;">
                    <label class="form-check"><input type="checkbox" name="is_active" id="edit_is_active"> Active</label>
                    <label class="form-check"><input type="checkbox" name="is_featured" id="edit_is_featured"> Featured</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=editModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-check-circle"></i> Update Vehicle</button>
            </div>
        </form>
    </div>
</div>

<script>
function editVehicle(v) {
    document.getElementById('edit_vehicle_id').value = v.id;
    document.getElementById('edit_category_id').value = v.category_id || 1;
    document.getElementById('edit_type').value = v.type;
    document.getElementById('edit_name').value = v.name || '';
    document.getElementById('edit_brand').value = v.brand || '';
    document.getElementById('edit_model').value = v.model;
    document.getElementById('edit_year').value = v.year || '';
    document.getElementById('edit_fuel_type').value = v.fuel_type || 'Petrol';
    document.getElementById('edit_transmission').value = v.transmission || 'Manual';
    document.getElementById('edit_seats').value = v.seats;
    document.getElementById('edit_bags').value = v.bags || 2;
    document.getElementById('edit_price_per_day').value = v.price_per_day;
    document.getElementById('edit_price_per_km').value = v.price_per_km || 0;
    document.getElementById('edit_image').value = v.image || '';
    document.getElementById('edit_description').value = v.description || '';
    document.getElementById('edit_features').value = v.features || '';
    document.getElementById('edit_inclusions').value = v.inclusions || '';
    document.getElementById('edit_exclusions').value = v.exclusions || '';
    document.getElementById('edit_facilities').value = v.facilities || '';
    document.getElementById('edit_terms').value = v.terms || '';
    document.getElementById('edit_cancellation_policy').value = v.cancellation_policy || '';
    document.getElementById('edit_is_active').checked = v.is_active == 1;
    document.getElementById('edit_is_featured').checked = v.is_featured == 1;
    document.getElementById('editModal').style.display = 'flex';
    fetch('../api/index.php?action=vehicles/gallery&id=' + v.id)
        .then(r => r.json())
        .then(gallery => {
            const ta = document.getElementById('edit_gallery');
            const prev = document.getElementById('edit-gallery-preview');
            if (gallery && gallery.length) {
                ta.value = gallery.map(g => g.image_url).join('\n');
                prev.innerHTML = gallery.map(g =>
                    '<img src="' + g.image_url + '" style="width:60px;height:45px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">'
                ).join('');
            } else {
                ta.value = '';
                prev.innerHTML = '';
            }
        })
        .catch(() => {});
}
</script>
</body>
</html>
