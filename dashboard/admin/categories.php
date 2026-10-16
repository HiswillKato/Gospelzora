<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Categories";
$user->guard('admin');
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'delete') {
        $music->delete('category', (int)$_POST['id']);
        $msg = "Category Deleted";
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        if ($music->status('category', (int)$_POST['id'], $status)) {
            $msg = $status === 'active' ? "Category Enabled" : "Category Disabled";
        } else {
            $err = "Could not update the category status.";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = "Invalid security token. Please try again.";
}
$categories = $music->all(false, true);
$header_actions = '<a class="btn btn-gold btn-sm" href="' . url('dashboard/admin/categories/add') . '">' . icon('plus', 'me-1') . "Add Category" . '</a>';
require __DIR__ . '/../../app/includes/admin/header.php';
?>
<?php $alert_type = 'success'; $alert_text = $msg;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body"><div class="table-responsive">
<table class="table table-hover align-middle"><thead><tr><th>Name</th><th class="text-center">Icon</th><th>Songs</th><th class="text-center">Status</th><th></th></tr></thead><tbody>
<?php if (empty($categories)) { ?><tr><td colspan="5" class="text-center text-muted py-4">No categories yet</td></tr>
<?php } else { foreach ($categories as $c) {
    $usesList = usage($c['icon']);
    $usesTxt = $usesList ? implode(', ', $usesList) : ($c['icon'] ?: '');
    $inactive = ($c['status'] ?? 'active') !== 'active';
    ?><tr>
<td class="fw-semibold<?= $inactive ? ' text-muted' : '' ?>"><?= e($c['name']) ?></td>
<td class="text-center"><?= icon($c['icon'] ?: 'music', 'cell-icon') ?><span class="visually-hidden"><?= e($usesTxt) ?></span></td>
<td><?= $c['song_count'] ?></td>
<td class="text-center"><?= $inactive ? '<span class="badge bg-secondary">' . "Disabled" . '</span>' : '<span class="badge bg-success">' . "Active" . '</span>' ?></td>
<td class="text-end">
<form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="status" value="<?= $inactive ? 'active' : 'inactive' ?>"><button class="btn btn-sm btn-outline-warning" title="<?= $inactive ? "Enable Category" : "Disable Category" ?>" aria-label="<?= $inactive ? "Enable" : "Disable" ?> <?= e($c['name']) ?>"><?= icon('toggle-on') ?></button></form>
<a href="<?= url('dashboard/admin/categories/edit') ?>?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><?= icon('pen') ?></a>
<?php $delete_id = (int)$c['id']; $delete_confirm = 'Delete this?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../../app/includes/partials/delete-button.php'; ?></td></tr>
<?php } } ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 