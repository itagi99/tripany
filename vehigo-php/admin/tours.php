<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    function handleTourImage() {
        if (!empty($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) return $_POST['image'] ?? '';
            $targetDir = __DIR__ . '/../uploads/tours/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($_FILES['image_file']['tmp_name'], $targetDir . $filename);
            return '/uploads/tours/' . $filename;
        }
        return $_POST['image'] ?? '';
    }

    if ($action === 'add') {
        $image = handleTourImage();
        $pdo->prepare("INSERT INTO tour_packages (title, description, destination, tour_date, price_per_person, max_participants, vehicle_type, includes, image) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$_POST['title'], $_POST['description'], $_POST['destination'], $_POST['tour_date'], $_POST['price_per_person'], $_POST['max_participants'], $_POST['vehicle_type'], $_POST['includes'], $image]);
        header("Location: tours.php?success=added");
        exit;
    }
    if ($action === 'edit' && isset($_POST['id'])) {
        $image = handleTourImage();
        $pdo->prepare("UPDATE tour_packages SET title=?, description=?, destination=?, tour_date=?, price_per_person=?, max_participants=?, vehicle_type=?, includes=?, image=?, is_active=? WHERE id=?")
            ->execute([$_POST['title'], $_POST['description'], $_POST['destination'], $_POST['tour_date'], $_POST['price_per_person'], $_POST['max_participants'], $_POST['vehicle_type'], $_POST['includes'], $image, $_POST['is_active'] ?? 1, $_POST['id']]);
        header("Location: tours.php?success=updated");
        exit;
    }
    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM tour_packages WHERE id=?")->execute([$_POST['id']]);
        header("Location: tours.php?success=deleted");
        exit;
    }
}

$tours = $pdo->query("SELECT * FROM tour_packages ORDER BY tour_date ASC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Tour Packages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Tour Packages</title>
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
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i>Tour package <?= ucfirst($_GET['success']) ?> successfully!</div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h2 class="page-title">Tour Packages</h2>
                    <p class="page-subtitle">Manage fixed-date group tour packages</p>
                </div>
                <button class="btn-premium btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                    <i class="bi bi-plus-lg"></i> Add Tour
                </button>
            </div>

            <div class="card-premium">
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Destination</th>
                                    <th>Date</th>
                                    <th>Price/Person</th>
                                    <th>Participants</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tours as $t): ?>
                                <tr>
                                    <td>#<?= $t['id'] ?></td>
                                    <td><?php if ($t['image']): ?><img src="<?= e($t['image']) ?>" style="width:40px;height:30px;border-radius:6px;object-fit:cover;"><?php else: ?><span style="color:var(--text-muted);font-size:0.6875rem;">No img</span><?php endif; ?></td>
                                    <td class="cell-primary"><?= e($t['title']) ?></td>
                                    <td style="font-size:0.8125rem;"><?= e($t['destination'] ?? '') ?></td>
                                    <td style="font-size:0.8125rem;"><?= e($t['tour_date']) ?></td>
                                    <td><strong>₹<?= number_format($t['price_per_person']) ?></strong></td>
                                    <td><?= $t['current_participants'] ?>/<?= $t['max_participants'] ?></td>
                                    <td><span class="badge-premium badge-<?= $t['is_active'] ? 'active' : 'inactive' ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td>
                                        <button class="btn-premium btn-primary btn-xs" onclick="editTour(<?= $t['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this tour?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn-premium btn-danger btn-xs"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tours)): ?>
                                <tr><td colspan="9"><div class="empty-state"><i class="bi bi-suitcase-lg-fill"></i><h6>No tours yet</h6><p>Create your first group tour package</p></div></td></tr>
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
    <div class="modal-content" style="width:100%;max-width:560px;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-suitcase-lg-fill" style="color:var(--primary);"></i> Add Tour Package</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g., Amboli Falls Getaway" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Describe the tour..."></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Destination *</label>
                        <input type="text" name="destination" class="form-input" placeholder="e.g., Amboli Falls" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tour Date *</label>
                        <input type="date" name="tour_date" class="form-input" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Price/Person (₹) *</label>
                        <input type="number" name="price_per_person" class="form-input" step="0.01" placeholder="499" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Participants *</label>
                        <input type="number" name="max_participants" class="form-input" placeholder="30" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vehicle Type</label>
                        <input type="text" name="vehicle_type" class="form-input" placeholder="e.g., SUV/Tempo">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Includes (comma separated)</label>
                    <input type="text" name="includes" class="form-input" placeholder="e.g., Breakfast, Lunch, Guide">
                </div>
                <div class="form-group">
                    <label class="form-label">Image</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <input type="file" name="image_file" accept="image/*" capture="environment" onchange="previewUpload(this, 'add-preview')" style="flex:1;padding:0.5rem;border:1px dashed var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:0.8125rem;">
                        <span style="color:var(--text-muted);font-size:0.75rem;">or</span>
                        <input type="url" name="image" class="form-input" placeholder="Paste URL..." style="flex:1;" onchange="document.getElementById('add-preview').src=this.value">
                    </div>
                    <img id="add-preview" style="display:none;width:80px;height:60px;object-fit:cover;border-radius:8px;margin-top:0.375rem;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-plus-circle"></i> Add Tour</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:560px;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit Tour Package</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=editModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST" id="editForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="edit-title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-desc" class="form-input" rows="3"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Destination *</label>
                        <input type="text" name="destination" id="edit-dest" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tour Date *</label>
                        <input type="date" name="tour_date" id="edit-date" class="form-input" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Price/Person (₹) *</label>
                        <input type="number" name="price_per_person" id="edit-price" class="form-input" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Participants *</label>
                        <input type="number" name="max_participants" id="edit-max" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vehicle Type</label>
                        <input type="text" name="vehicle_type" id="edit-vehicle" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Includes (comma separated)</label>
                    <input type="text" name="includes" id="edit-includes" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Image</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <input type="file" name="image_file" accept="image/*" capture="environment" onchange="previewUpload(this, 'edit-preview')" style="flex:1;padding:0.5rem;border:1px dashed var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:0.8125rem;">
                        <span style="color:var(--text-muted);font-size:0.75rem;">or</span>
                        <input type="url" name="image" id="edit-image" class="form-input" placeholder="Paste URL..." style="flex:1;" onchange="document.getElementById('edit-preview').src=this.value">
                    </div>
                    <img id="edit-preview" style="display:none;width:80px;height:60px;object-fit:cover;border-radius:8px;margin-top:0.375rem;">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" id="edit-active" class="form-input">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=editModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-check-circle"></i> Update Tour</button>
            </div>
        </form>
    </div>
</div>

<script>
const tours = <?= json_encode($tours) ?>;
function editTour(id) {
    const t = tours.find(x => x.id == id);
    if (!t) return;
    document.getElementById('edit-id').value = t.id;
    document.getElementById('edit-title').value = t.title;
    document.getElementById('edit-desc').value = t.description || '';
    document.getElementById('edit-dest').value = t.destination || '';
    document.getElementById('edit-date').value = t.tour_date;
    document.getElementById('edit-price').value = t.price_per_person;
    document.getElementById('edit-max').value = t.max_participants;
    document.getElementById('edit-vehicle').value = t.vehicle_type || '';
    document.getElementById('edit-includes').value = t.includes || '';
    document.getElementById('edit-image').value = t.image || '';
    document.getElementById('edit-active').value = t.is_active;
    const preview = document.getElementById('edit-preview');
    if (t.image) { preview.src = t.image; preview.style.display = 'block'; } else { preview.style.display = 'none'; }
    document.getElementById('editModal').style.display = 'flex';
}
function previewUpload(input, previewId) {
    const img = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { img.src = e.target.result; img.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
