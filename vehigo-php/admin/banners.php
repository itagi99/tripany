<?php
session_start();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO banners (title, subtitle, image_url, link, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'] ?? '',
            $_POST['subtitle'] ?? '',
            handleBannerUpload('image_file', 'banners', $_POST['image_url'] ?? ''),
            $_POST['link'] ?? '#',
            isset($_POST['active']) ? 1 : 0
        ]);
        header("Location: banners.php?success=added");
        exit;
    }
    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM banners WHERE id=?")->execute([$_POST['id']]);
        header("Location: banners.php?success=deleted");
        exit;
    }
}

$banners = $pdo->query("SELECT * FROM banners ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Banners';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripAny Admin — Banners</title>
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
                <div class="alert-premium alert-success"><i class="bi bi-check-circle-fill"></i>Banner <?= ucfirst($_GET['success']) ?> successfully!</div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h2 class="page-title">Banners</h2>
                    <p class="page-subtitle">Manage homepage and promotional banners</p>
                </div>
                <button class="btn-premium btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                    <i class="bi bi-plus-lg"></i> Add Banner
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
                                    <th>Subtitle</th>
                                    <th>Image</th>
                                    <th>Link</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($banners as $b): ?>
                                <tr>
                                    <td>#<?= e($b['id']) ?></td>
                                    <td class="cell-primary"><?= e($b['title']) ?></td>
                                    <td style="font-size:0.8125rem;color:var(--text-muted);max-width:200px;"><?= e($b['subtitle'] ?? '') ?></td>
                                    <td>
                                        <?php if ($b['image_url']): ?>
                                            <img src="<?= e($b['image_url']) ?>" alt="" style="width:80px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:0.8125rem;">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b['link']): ?>
                                            <a href="<?= e($b['link']) ?>" target="_blank" style="color:var(--primary);font-size:0.8125rem;text-decoration:none;"><i class="bi bi-box-arrow-up-right"></i> <?= e($b['link']) ?></a>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge-premium badge-<?= $b['active'] ? 'active' : 'inactive' ?>"><?= $b['active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this banner?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn-premium btn-danger btn-xs"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($banners)): ?>
                                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-image"></i><h6>No banners found</h6><p>Create your first banner to get started</p></div></td></tr>
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
            <h5 class="modal-title"><i class="bi bi-image" style="color:var(--primary);"></i> Add Banner</h5>
            <button type="button" class="btn-premium btn-ghost btn-xs" onclick="this.closest('[id=addModal]').style.display='none'" style="font-size:1.25rem;line-height:1;">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g., Summer Sale" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Subtitle</label>
                    <input type="text" name="subtitle" class="form-input" placeholder="Short description">
                </div>
                <div class="form-group">
                    <label class="form-label">Image URL *</label>
                    <input type="url" name="image_url" class="form-input" placeholder="https://example.com/image.jpg" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Link URL</label>
                    <input type="url" name="link" class="form-input" placeholder="https://example.com" value="#">
                </div>
                <div class="form-group">
                    <label class="form-check"><input type="checkbox" name="active" checked> Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-premium btn-secondary" onclick="this.closest('[id=addModal]').style.display='none'">Cancel</button>
                <button type="submit" class="btn-premium btn-primary"><i class="bi bi-plus-circle"></i> Add Banner</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
