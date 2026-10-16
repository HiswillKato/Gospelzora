<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = isset($_GET['edit']) ? "Edit Artist" : "Add Artist";
$user->guard('admin');
$err = '';

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $user->find((int)$_GET['edit'], 'artist');
    if (!$edit) $err = "Artist not found.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $result = $user->save('artist', [
            'name' => trim($_POST['name'] ?? ''),
            'bio' => trim($_POST['bio'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'existing_image' => $_POST['existing_image'] ?? '',
            'image' => $_FILES['image'] ?? null,
        ], $id);
        if ($result['success']) {
            flash('success', $result['message']);
            redirect('dashboard/admin/artists');
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
<h5 class="fw-semibold mb-3"><?= e($edit ? "Edit Artist" : "Add Artist") ?></h5>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save">
<?php if ($edit) { ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><input type="hidden" name="existing_image" value="<?= e($edit['image']??'') ?>"><?php } ?>
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required maxlength="100" value="<?= e($edit['name']??'') ?>"></div>
<div class="col-md-3"><label class="form-label">Country</label><select name="country" class="form-select"><?php $country_selected = country($edit['country'] ?? '', 'code'); $country_required = false;
require __DIR__ . '/../../app/includes/partials/country-options.php'; ?></select></div>
<div class="col-md-3"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
<div class="col-12"><label class="form-label">Biography</label><textarea name="bio" class="form-control" rows="3"><?= e($edit['bio']??'') ?></textarea></div>
<div class="col-12"><button type="submit" class="btn btn-gold">Save</button> <a href="<?= url('dashboard/admin/artists') ?>" class="btn btn-outline-secondary">Cancel</a></div>
</div></form></div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 