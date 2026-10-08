<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = isset($_GET['edit']) ? "Edit Category" : "Add Category";
$user->guard('admin');
$err = '';

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $music->category((int)$_GET['edit']);
    if (!$edit) $err = "Category not found.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $result = $music->save('category', [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'icon' => trim($_POST['icon'] ?? 'fa-music'),
        ], $id);
        if ($result['success']) {
            flash('success', $result['message']);
            redirect('dashboard/admin/categories');
        }
        $err = $result['message'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = "Invalid security token. Please try again.";
}

require __DIR__ . '/../../app/includes/admin/header.php';
?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<h5 class="fw-semibold mb-3"><?= e($edit ? "Edit Category" : "Add Category") ?></h5>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save">
<?php if ($edit) { ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php } ?>
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required maxlength="100" value="<?= e($edit['name']??'') ?>"></div>
<div class="col-md-3">
<label class="form-label">Icon (Font Awesome)</label>
<?php
$icon = trim($edit['icon'] ?? 'fa-music');
$iconName = preg_replace('/^(?:fa-(?:solid|regular|brands)\s+)?fa-/', '', strtolower($icon));
$iconTitle = implode('-', array_map('ucfirst', explode('-', $iconName)));
$uses = usage($icon);
$usesTxt = $uses ? implode(', ', $uses) : '';
?>
<input type="hidden" name="icon" value="<?= e($icon) ?>">
<div class="icon-picker" data-icon-picker data-value="<?= e($icon) ?>">
<button type="button" class="icon-picker-current icon-picker-toggle" aria-haspopup="listbox" aria-expanded="false">
<span class="icon-picker-glyph"><?= icon($icon) ?></span>
<span class="icon-picker-label">
<span class="icon-picker-name"><?= e($iconTitle) ?></span>
<span class="icon-picker-use"><?= e($usesTxt) ?></span>
</span>
<span class="ms-auto"><?= icon('chevron-down', 'text-muted') ?></span>
</button>
</div>
<div class="form-text">Click to choose an icon — search by name or its use.</div>
</div>
<div class="col-md-5"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= e($edit['description']??'') ?></textarea></div>
<div class="col-12"><button type="submit" class="btn btn-gold">Save</button> <a href="<?= url('dashboard/admin/categories') ?>" class="btn btn-outline-secondary">Cancel</a></div>
</div></form></div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 