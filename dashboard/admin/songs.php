<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Songs";
$user->guard('staff');
$msg = $err = '';
$artist_user = $user->current();
$artist_mode = $user->check('artist');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'status' && $user->check('admin')) {
        $id = (int)($_POST['id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
        $music->status('song', $id, $status);
        $msg = $status === 'published' ? "Song approved and published." : "Song returned to draft.";
    } elseif ($action === 'approve' && $user->check('admin')) {
        $result = $music->approve((int)($_POST['id'] ?? 0));
        if ($result['success']) {
            $msg = "Request approved. The artist can now upload the audio.";
        } else {
            $err = (string)$result['message'];
        }
    } elseif ($action === 'delete') {
        $music->delete('song', (int)$_POST['id'], $artist_mode ? (int)$artist_user['id'] : null);
        $msg = "Song Deleted";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $err = "Invalid security token. Please try again.";
}

$songs = $music->manage($artist_mode ? (int)$artist_user['id'] : null);
$header_actions = '<a class="btn btn-gold btn-sm" href="' . url('dashboard/admin/songs/add') . '">' . icon('plus', 'me-1') . "Add Song" . '</a>';
require __DIR__ . '/../../app/includes/admin/header.php';
?>
<?php $alert_type = 'success'; $alert_text = $msg;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Title</th><th>Artist</th><th>Plays</th><th>Downloads</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (empty($songs)) { ?><tr><td colspan="6" class="text-center text-muted py-4">No songs found</td></tr>
<?php } else { foreach ($songs as $s) { ?><tr>
<td><?= e($s['title']) ?></td><td><?= e($s['artist_name']) ?></td><td><?= abbrev($s['plays']) ?></td><td><?= abbrev($s['downloads'] ?? 0) ?></td>
<td><span class="badge bg-<?= match($s['status']) {
    'published' => 'success',
    'requested' => 'info',
    'approved' => 'primary',
    'pending' => 'warning',
    default => 'secondary',
} ?>"><?= e(status((string)$s['status'])) ?></span></td>
<td class="text-end">
<?php if ($user->check('admin') && $s['status'] === 'pending') { ?><form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="published"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-success" title="Approve"><?= icon('check') ?></button></form><?php } ?>
<?php if ($user->check('admin') && $s['status'] === 'requested') { ?><form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-primary" title="Approve request"><?= icon('check') ?>Approve</button></form><?php } ?>
<a href="<?= url('dashboard/admin/songs/edit') ?>?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><?= icon('pen') ?></a>
<?php $delete_id = (int)$s['id']; $delete_confirm = 'Delete this song?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../../app/includes/partials/delete-button.php'; ?>
</td></tr><?php } } ?>
</tbody></table></div></div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 