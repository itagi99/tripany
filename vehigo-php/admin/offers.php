<?php
session_start();
require '../db.php';
require '../helpers.php';
require 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO offers (title, description, discount_percent, valid_from, valid_to, active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'] ?? '',
            $_POST['description'] ?? '',
            $_POST['discount_percent'] ?? 0,
            $_POST['valid_from'] ?? date('Y-m-d'),
            $_POST['valid_to'] ?? date('Y-m-d', strtotime('+30 days')),
            isset($_POST['active']) ? 1 : 0
        ]);
        header("Location: offers.php?success=added");
        exit;
    }
    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM offers WHERE id=?")->execute([$_POST['id']]);
        header("Location: offers.php?success=deleted");
        exit;
    }
}

$offers = $pdo->query("SELECT * FROM offers ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Offers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Offers</title>
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
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i>Offer <?= ucfirst($_GET['success']) ?> successfully!</div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h2 class="page-title">Offers</h2>
                    <p class="page-subtitle">Manage promotional offers and deals</p>
                </div>
                <button class="btn-premium btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                    <i class="bi bi-plus-lg"></i> Add Offer
                </button>
            </div>

            <div class="card-premium">
                <div class="card-body-premium">
                    <div class="table-scroll">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Discount</th>
                                    <th>Valid From</th>
                                    <th>Valid To</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($offers as $o): ?>
                                <tr>
                                    <td>#<?= e($o['id']) ?></td>
                                    <td class="cell-primary"><?= e($o['title']) ?></td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);max-width:200px;"><?= e($o['description'] ?? '') ?></td>
                                    <td><span class="badge-premium badge-success" style="font-size:0.8125rem;padding:0.35rem 0.75rem;"><?= $o['discount_percent'] ?>% OFF</span></td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);"><?= e($o['valid_from'] ?? '') ?></td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);"><?= e($o['valid_to'] ?? '') ?></td>
                                    <td><span class="badge-premium badge-<?= $o['active'] ? 'active' : 'inactive' ?>"><?= $o['active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this offer?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                            <button type="submit" class="btn-premium btn-danger btn-xs"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($offers)): ?>
                                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-percent"></i><h6>No offers found</h6><p>Create your first offer to get started</p></div></td></tr>
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
            <h5 class="modal-title"><i class="bi bi-percent" style="color:var(--primary);"></i> Add Offer</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g., Monsoon Special" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Describe the offer..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount (%) *</label>
                    <input type="number" name="discount_percent" class="form-input" min="1" max="100" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Valid From *</label>
                        <input type="date" name="valid_from" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid To *</label>
                        <input type="date" name="valid_to" class="form-input" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-check"><input type="checkbox" name="active" checked> Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-plus-circle"></i> Add Offer</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
