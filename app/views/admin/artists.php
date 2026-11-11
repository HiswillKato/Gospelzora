<?php
require_once __DIR__ . '/../../bootstrap.php';
$page_title = "Artists";
$user->guard('admin');
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'delete') {
        $user->delete('artist', (int)$_POST['id']);
        $msg = "Artist Deleted";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = "Invalid security token. Please try again.";
}
$artists = $user->roster('artist', true);
$header_actions = '<a class="btn btn-gold btn-sm" href="' . url('dashboard/admin/artists/add') . '">' . icon('plus') . "Add Artist" . '</a>';
?>
<?php $alert_type = 'success'; $alert_text = $msg;
require __DIR__ . '/../shared/partials/alert.php'; ?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../shared/partials/alert.php'; ?>
<div class="card tile"><div class="table-responsive">
<table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th class="d-none d-md-table-cell">Country</th><th>Songs</th><th></th></tr></thead><tbody>
<?php if (empty($artists)) { ?><tr><td colspan="4" class="text-center text-body-secondary py-4">No artists yet</td></tr>
<?php } else { foreach ($artists as $a) { ?><tr><td><?= e($a['name']) ?></td><td class="d-none d-md-table-cell"><?= e(country($a['country']) ?: '-') ?></td><td><?= $a['song_count'] ?></td>
<td class="text-end"><div class="d-inline-flex gap-1">
<a href="<?= url('dashboard/admin/artists/edit') ?>?edit=<?= $a['id'] ?>" class="btn btn-ghost btn-sm rounded-circle" title="Edit"><?= icon('pen') ?></a>
<?php $delete_id = (int)$a['id']; $delete_confirm = 'Delete this?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../shared/partials/delete-button.php'; ?></div></td></tr>
<?php } } ?></tbody></table></div></div>
