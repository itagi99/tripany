<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO tour_addons (name, description, icon, price, is_active) VALUES (?,?,?,?,1)")
            ->execute([$_POST['name'], $_POST['description'], $_POST['icon'] ?? '📦', $_POST['price']]);
        header("Location: addons.php?success=added");
        exit;
    }
    if ($action === 'edit' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE tour_addons SET name=?, description=?, icon=?, price=?, is_active=? WHERE id=?")
            ->execute([$_POST['name'], $_POST['description'], $_POST['icon'] ?? '📦', $_POST['price'], $_POST['is_active'] ?? 1, $_POST['id']]);
        header("Location: addons.php?success=updated");
        exit;
    }
    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM tour_addons WHERE id=?")->execute([$_POST['id']]);
        header("Location: addons.php?success=deleted");
        exit;
    }
}

$addons = $pdo->query("SELECT * FROM tour_addons ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Tour Add-ons';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Tour Add-ons</title>
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
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i>Add-on <?= ucfirst($_GET['success']) ?> successfully!</div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h2 class="page-title">Tour Add-ons</h2>
                    <p class="page-subtitle">Manage add-ons available for tour packages (water bottle, bed kit, etc.)</p>
                </div>
                <button class="btn-premium btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                    <i class="bi bi-plus-lg"></i> Add Add-on
                </button>
            </div>

            <div class="card-premium">
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Icon</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Price (₹)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($addons as $a): ?>
                                <tr>
                                    <td>#<?= $a['id'] ?></td>
                                    <td style="font-size:1.25rem;"><?= $a['icon'] ?? '📦' ?></td>
                                    <td class="cell-primary"><?= e($a['name']) ?></td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);"><?= e($a['description'] ?? '') ?></td>
                                    <td><strong>₹<?= number_format($a['price']) ?></strong></td>
                                    <td><span class="badge-premium badge-<?= $a['is_active'] ? 'active' : 'inactive' ?>"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td>
                                        <button class="btn-premium btn-primary btn-xs" onclick="editAddon(<?= $a['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this add-on?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn-premium btn-danger btn-xs"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($addons)): ?>
                                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-box-seam"></i><h6>No add-ons yet</h6><p>Click "Add Add-on" to create one</p></div></td></tr>
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
    <div class="modal-content" style="width:100%;max-width:480px;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-box-seam" style="color:var(--primary);"></i> Add Add-on</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div style="display:grid;grid-template-columns:80px 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Icon</label>
                        <input type="text" name="icon" class="form-input" placeholder="📦" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="e.g., Water Bottle" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="Brief description"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" name="price" class="form-input" step="0.01" placeholder="49" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-plus-circle"></i> Add Add-on</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="width:100%;max-width:480px;">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil" style="color:var(--primary);"></i> Edit Add-on</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=editModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div style="display:grid;grid-template-columns:80px 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Icon</label>
                        <input type="text" name="icon" id="edit-icon" class="form-input" placeholder="📦" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="edit-name" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-desc" class="form-input" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" name="price" id="edit-price" class="form-input" step="0.01" required>
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
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-check-circle"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
const addons = <?= json_encode($addons) ?>;
function editAddon(id) {
    const a = addons.find(x => x.id == id);
    if (!a) return;
    document.getElementById('edit-id').value = a.id;
    document.getElementById('edit-name').value = a.name;
    document.getElementById('edit-icon').value = a.icon || '📦';
    document.getElementById('edit-desc').value = a.description || '';
    document.getElementById('edit-price').value = a.price;
    document.getElementById('edit-active').value = a.is_active;
    document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>
</html>
