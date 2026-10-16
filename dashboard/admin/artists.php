<?php
require_once __DIR__ . '/../../app/bootstrap.php';
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
$header_actions = '<a class="btn btn-gold btn-sm" href="' . url('dashboard/admin/artists/add') . '">' . icon('plus', 'me-1') . "Add Artist" . '</a>';
require __DIR__ . '/../../app/includes/admin/header.php';
?>
<?php $alert_type = 'success'; $alert_text = $msg;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body"><div class="table-responsive">
<table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Country</th><th>Songs</th><th></th></tr></thead><tbody>
<?php if (empty($artists)) { ?><tr><td colspan="4" class="text-center text-muted py-4">No artists yet</td></tr>
<?php } else { foreach ($artists as $a) { ?><tr><td><?= e($a['name']) ?></td><td><?= e(country($a['country']) ?: '-') ?></td><td><?= $a['song_count'] ?></td>
<td class="text-end"><a href="<?= url('dashboard/admin/artists/edit') ?>?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary"><?= icon('pen') ?></a>
<?php $delete_id = (int)$a['id']; $delete_confirm = 'Delete this?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../../app/includes/partials/delete-button.php'; ?></td></tr>
<?php } } ?></tbody></table></div></div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 