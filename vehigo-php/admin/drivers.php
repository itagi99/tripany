<?php
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

function handleBannerUpload($field, $subdir, $default = '') {
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

$pageTitle = 'Drivers';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO drivers (name, email, phone, password_hash, vehicle_type, vehicle_number, vehicle_model, license_number, status) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['phone'] ?? '',
            password_hash($_POST['password'] ?? 'driver123', PASSWORD_DEFAULT),
            $_POST['vehicle_type'] ?? '', $_POST['vehicle_number'] ?? '', $_POST['vehicle_model'] ?? '',
            $_POST['license_number'] ?? '', 'offline'
        ]);
        header("Location: drivers.php?success=added");
        exit;
    }
    if ($action === 'add_doc') {
        $stmt = $pdo->prepare("INSERT INTO driver_documents (driver_id, doc_type, doc_number, doc_file, expiry_date) VALUES (?,?,?,?,?)");
        $stmt->execute([
            $_POST['driver_id'], $_POST['doc_type'] ?? '', $_POST['doc_number'] ?? '',
            handleBannerUpload('doc_file', 'drivers', $_POST['doc_file'] ?? ''), $_POST['expiry_date'] ?? null
        ]);
        header("Location: drivers.php?tab=" . $_POST['driver_id'] . "&success=doc_added");
        exit;
    }
    if ($action === 'verify_doc') {
        $pdo->prepare("UPDATE driver_documents SET is_verified=? WHERE id=?")->execute([$_POST['verified'] ?? 1, $_POST['doc_id']]);
        header("Location: drivers.php?tab=" . $_POST['driver_id'] . "&success=doc_verified");
        exit;
    }
    if ($action === 'delete_doc') {
        $pdo->prepare("DELETE FROM driver_documents WHERE id=?")->execute([$_POST['doc_id']]);
        header("Location: drivers.php?tab=" . $_POST['driver_id'] . "&success=doc_deleted");
        exit;
    }
    if ($action === 'update_status') {
        $pdo->prepare("UPDATE drivers SET status=? WHERE id=?")->execute([$_POST['status'], $_POST['driver_id']]);
        header("Location: drivers.php?success=status_updated");
        exit;
    }
}

$search = $_GET['search'] ?? '';
$tab = $_GET['tab'] ?? '';
$searchParam = "%{$search}%";
$drivers = $pdo->prepare("SELECT d.* FROM drivers d WHERE d.name LIKE ? OR d.phone LIKE ? OR d.email LIKE ? ORDER BY d.created_at DESC");
$drivers->execute([$searchParam, $searchParam, $searchParam]);
$drivers = $drivers->fetchAll(PDO::FETCH_ASSOC);

$totalDrivers = $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
$onlineCount = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='online'")->fetchColumn();
$offlineCount = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='offline'")->fetchColumn();
$avgRating = $pdo->query("SELECT COALESCE(AVG(rating),0) FROM drivers")->fetchColumn();

$selectedDriver = null;
$driverDocs = [];
if ($tab) {
    $selectedDriver = $pdo->prepare("SELECT * FROM drivers WHERE id=?");
    $selectedDriver->execute([$tab]);
    $selectedDriver = $selectedDriver->fetch(PDO::FETCH_ASSOC);
    $driverDocs = $pdo->prepare("SELECT * FROM driver_documents WHERE driver_id=? ORDER BY doc_type");
    $driverDocs->execute([$tab]);
    $driverDocs = $driverDocs->fetchAll(PDO::FETCH_ASSOC);
}

$docTypes = ['Aadhaar Card', 'PAN Card', 'Driving License', 'Vehicle RC', 'Insurance', 'Bank Passbook', 'Agreement Bond', 'Address Proof', 'Photo', 'Medical Certificate'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Drivers</title>
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
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i><?= ucfirst(str_replace('_',' ',$_GET['success'])) ?> successfully!</div>
            <?php endif; ?>

            <?php if ($selectedDriver): ?>
            <!-- DRIVER DETAIL VIEW -->
            <div class="page-header">
                <div style="display:flex;align-items:center;gap:1rem;">
                    <a href="drivers.php" class="btn-premium btn-ghost btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:1.25rem;flex-shrink:0;box-shadow:0 0 20px var(--primary-glow);"><?= strtoupper(substr($selectedDriver['name'],0,1)) ?></div>
                    <div>
                        <h2 class="page-title" style="margin:0;"><?= e($selectedDriver['name']) ?></h2>
                        <p class="page-subtitle" style="margin:0;"><?= e($selectedDriver['phone']) ?> · <?= e($selectedDriver['email'] ?? '') ?></p>
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="driver_id" value="<?= $selectedDriver['id'] ?>">
                        <input type="hidden" name="status" value="<?= $selectedDriver['status'] === 'online' ? 'offline' : 'online' ?>">
                        <button type="submit" class="btn-premium <?= $selectedDriver['status'] === 'online' ? 'btn-danger' : 'btn-success' ?> btn-sm">
                            <i class="bi bi-<?= $selectedDriver['status'] === 'online' ? 'wifi-off' : 'wifi' ?>"></i> Go <?= $selectedDriver['status'] === 'online' ? 'Offline' : 'Online' ?>
                        </button>
                    </form>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon blue"><i class="bi bi-star-fill"></i></div></div><div class="stat-value"><?= number_format($selectedDriver['rating'],1) ?></div><div class="stat-label">Rating</div></div>
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon cyan"><i class="bi bi-car-front"></i></div></div><div class="stat-value" style="font-size:1.25rem;"><?= e($selectedDriver['vehicle_type'] ?? 'N/A') ?></div><div class="stat-label"><?= e($selectedDriver['vehicle_number'] ?? '') ?></div></div>
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="bi bi-shield-check"></i></div></div><div class="stat-value"><?= count(array_filter($driverDocs, fn($d)=>$d['is_verified'])) ?>/<?= count($docTypes) ?></div><div class="stat-label">Docs Verified</div></div>
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon yellow"><i class="bi bi-<?= $selectedDriver['status']==='online' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i></div></div><div class="stat-value" style="text-transform:capitalize;"><?= $selectedDriver['status'] ?></div><div class="stat-label">Current Status</div></div>
            </div>

            <!-- Documents Section -->
            <div class="card-premium" style="margin-bottom:1.5rem;">
                <div class="card-header-premium">
                    <h5><i class="bi bi-file-earmark-person"></i> Documents & KYC</h5>
                    <button class="btn-premium btn-primary btn-sm" onclick="document.getElementById('addDocModal').style.display='flex'"><i class="bi bi-plus"></i> Add Document</button>
                </div>
                <div class="card-body-premium">
                    <?php if (empty($driverDocs)): ?>
                        <div class="empty-state"><i class="bi bi-file-earmark-x"></i><h6>No documents uploaded</h6><p>Upload driver's documents for KYC verification</p></div>
                    <?php else: ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
                        <?php foreach ($driverDocs as $doc): ?>
                        <div style="background:rgba(255,255,255,0.02);border:1px solid <?= $doc['is_verified'] ? 'rgba(34,197,94,0.3)' : 'var(--border)' ?>;border-radius:16px;padding:1.25rem;position:relative;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                                <span class="badge-premium <?= $doc['is_verified'] ? 'badge-active' : 'badge-warning' ?>"><?= $doc['is_verified'] ? 'Verified' : 'Pending' ?></span>
                                <div style="display:flex;gap:0.25rem;">
                                    <?php if (!$doc['is_verified']): ?>
                                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="verify_doc"><input type="hidden" name="doc_id" value="<?= $doc['id'] ?>"><input type="hidden" name="driver_id" value="<?= $tab ?>"><input type="hidden" name="verified" value="1"><button type="submit" class="btn-premium btn-success btn-xs"><i class="bi bi-check-lg"></i></button></form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this document?')"><input type="hidden" name="action" value="delete_doc"><input type="hidden" name="doc_id" value="<?= $doc['id'] ?>"><input type="hidden" name="driver_id" value="<?= $tab ?>"><button type="submit" class="btn-premium btn-danger btn-xs"><i class="bi bi-trash3"></i></button></form>
                                </div>
                            </div>
                            <div style="font-size:0.8125rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.25rem;"><?= e($doc['doc_type']) ?></div>
                            <div style="font-weight:600;font-size:0.9375rem;color:var(--text-primary);margin-bottom:0.5rem;"><?= e($doc['doc_number'] ?: 'N/A') ?></div>
                            <?php if ($doc['expiry_date']): ?>
                                <div style="font-size:0.75rem;color:<?= strtotime($doc['expiry_date']) < time() ? 'var(--danger)' : 'var(--text-muted)' ?>;">Expires: <?= e($doc['expiry_date']) ?></div>
                            <?php endif; ?>
                            <?php if ($doc['doc_file']): ?>
                                <div style="margin-top:0.5rem;"><a href="<?= e($doc['doc_file']) ?>" target="_blank" style="color:var(--primary);font-size:0.8125rem;text-decoration:none;"><i class="bi bi-eye"></i> View Document</a></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- DRIVERS LIST VIEW -->
            <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon blue"><i class="bi bi-people"></i></div></div><div class="stat-value"><?= $totalDrivers ?></div><div class="stat-label">Total Drivers</div></div>
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon green"><i class="bi bi-wifi"></i></div></div><div class="stat-value"><?= $onlineCount ?></div><div class="stat-label">Online</div></div>
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon red"><i class="bi bi-wifi-off"></i></div></div><div class="stat-value"><?= $offlineCount ?></div><div class="stat-label">Offline</div></div>
                <div class="stat-card"><div class="stat-card-header"><div class="stat-icon yellow"><i class="bi bi-star-fill"></i></div></div><div class="stat-value"><?= number_format($avgRating,1) ?></div><div class="stat-label">Avg Rating</div></div>
            </div>

            <div class="page-header">
                <div>
                    <h2 class="page-title">Drivers</h2>
                    <p class="page-subtitle">Manage driver profiles, documents and KYC</p>
                </div>
                <button class="btn-premium btn-primary" onclick="document.getElementById('addDriverModal').style.display='flex'"><i class="bi bi-plus-lg"></i> Add Driver</button>
            </div>

            <div class="card-premium">
                <div class="card-header-premium">
                    <form method="GET" style="display:flex;gap:0.5rem;flex:1;max-width:400px;">
                        <input type="text" name="search" class="form-input" placeholder="Search drivers by name, phone, email..." value="<?= e($search) ?>" style="padding-left:2.5rem;">
                        <button type="submit" class="btn-premium btn-primary btn-sm"><i class="bi bi-search"></i></button>
                    </form>
                </div>
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Phone</th>
                                    <th>Vehicle</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                    <th>Docs</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($drivers as $d):
                                    $docCount = $pdo->prepare("SELECT COUNT(*) FROM driver_documents WHERE driver_id=?");
                                    $docCount->execute([$d['id']]);
                                    $docTotal = $docCount->fetchColumn();
                                    $verifiedDoc = $pdo->prepare("SELECT COUNT(*) FROM driver_documents WHERE driver_id=? AND is_verified=1");
                                    $verifiedDoc->execute([$d['id']]);
                                    $verifiedCount = $verifiedDoc->fetchColumn();
                                ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:0.75rem;">
                                            <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.875rem;flex-shrink:0;"><?= strtoupper(substr($d['name'],0,1)) ?></div>
                                            <div>
                                                <div class="cell-primary"><?= e($d['name']) ?></div>
                                                <div class="cell-muted"><?= e($d['email'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><i class="bi bi-phone" style="font-size:0.75rem;color:var(--text-muted);"></i> <?= e($d['phone']) ?></td>
                                    <td>
                                        <div class="cell-primary"><?= e($d['vehicle_type'] ?? 'N/A') ?></div>
                                        <div class="cell-muted"><?= e($d['vehicle_number'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = ['online'=>'badge-active','offline'=>'badge-inactive','busy'=>'badge-warning'];
                                        $cls = $statusColors[$d['status']] ?? 'badge-inactive';
                                        ?>
                                        <span class="badge-premium <?= $cls ?>"><?= ucfirst($d['status']) ?></span>
                                    </td>
                                    <td><div class="stars" style="display:inline-flex;"><?php for($i=1;$i<=5;$i++): ?><i class="bi bi-star-fill <?= $i > round($d['rating']) ? 'empty' : '' ?>" style="font-size:0.625rem;"></i><?php endfor; ?></div> <?= number_format($d['rating'],1) ?></td>
                                    <td>
                                        <?php if ($docTotal > 0): ?>
                                            <span class="badge-premium <?= $verifiedCount === $docTotal ? 'badge-active' : 'badge-warning' ?>"><?= $verifiedCount ?>/<?= $docTotal ?></span>
                                        <?php else: ?>
                                            <span class="badge-premium badge-inactive">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                                    <td>
                                        <a href="drivers.php?tab=<?= $d['id'] ?>" class="btn-premium btn-ghost btn-xs"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($drivers)): ?>
                                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-person-badge"></i><h6>No drivers found</h6></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Add Driver Modal -->
<div id="addDriverModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;overflow-y:auto;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:560px;margin:auto;">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus" style="color:var(--primary);"></i> Add Driver</h5><button class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addDriverModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-input" required></div>
                    <div class="form-group"><label class="form-label">Phone *</label><input type="tel" name="phone" class="form-input" required></div>
                </div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-input"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Vehicle Type</label><select name="vehicle_type" class="form-select"><option>Sedan</option><option>SUV</option><option>Hatchback</option><option>Bike</option><option>Scooter</option><option>Auto</option></select></div>
                    <div class="form-group"><label class="form-label">Vehicle Number</label><input type="text" name="vehicle_number" class="form-input" placeholder="DL-01-AB-1234"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group"><label class="form-label">Vehicle Model</label><input type="text" name="vehicle_model" class="form-input"></div>
                    <div class="form-group"><label class="form-label">License Number</label><input type="text" name="license_number" class="form-input"></div>
                </div>
                <div class="form-group"><label class="form-label">Password</label><input type="text" name="password" class="form-input" value="driver123"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addDriverModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-plus-circle"></i> Add Driver</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Document Modal -->
<div id="addDocModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:480px;">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-file-earmark-plus" style="color:var(--primary);"></i> Upload Document</h5><button class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addDocModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_doc">
                <input type="hidden" name="driver_id" value="<?= e($tab) ?>">
                <div class="form-group">
                    <label class="form-label">Document Type *</label>
                    <select name="doc_type" class="form-select" required>
                        <option value="">Select document type...</option>
                        <?php foreach ($docTypes as $dt): ?>
                            <option value="<?= $dt ?>"><?= $dt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Document Number</label><input type="text" name="doc_number" class="form-input" placeholder="e.g., ABCDE1234F"></div>
                <div class="form-group"><label class="form-label">Document File URL</label><input type="url" name="doc_file" class="form-input" placeholder="https://example.com/document.pdf"></div>
                <div class="form-group"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-input"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addDocModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-upload"></i> Upload Document</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
