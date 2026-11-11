<?php
require_once __DIR__ . '/../../bootstrap.php';
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
$header_actions = '<a class="btn btn-gold btn-sm" href="' . url('dashboard/admin/songs/add') . '">' . icon('plus') . "Add Song" . '</a>';
?>
<?php $alert_type = 'success'; $alert_text = $msg;
require __DIR__ . '/../shared/partials/alert.php'; ?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../shared/partials/alert.php'; ?>
<div class="card tile">
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Title</th><th class="d-none d-md-table-cell">Artist</th><th>Plays</th><th class="d-none d-lg-table-cell">Downloads</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (empty($songs)) { ?><tr><td colspan="6" class="text-center text-body-secondary py-4">No songs found</td></tr>
<?php } else { foreach ($songs as $s) { ?><tr>
<td><?= e($s['title']) ?></td><td class="d-none d-md-table-cell"><?= e($s['artist_name']) ?></td><td><?= abbrev($s['plays']) ?></td><td class="d-none d-lg-table-cell"><?= abbrev($s['downloads'] ?? 0) ?></td>
<td><span class="badge rounded-pill bg-<?= match($s['status']) {
    'published' => 'success',
    'requested' => 'info',
    'approved' => 'primary',
    'pending' => 'warning',
    default => 'secondary',
} ?>"><?= e(status((string)$s['status'])) ?></span></td>
<td class="text-end">
<div class="d-inline-flex gap-1">
<?php if ($user->check('admin') && $s['status'] === 'pending') { ?><form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="published"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-ghost btn-sm rounded-circle text-success" title="Approve"><?= icon('check') ?></button></form><?php } ?>
<?php if ($user->check('admin') && $s['status'] === 'requested') { ?><form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-primary" title="Approve request"><?= icon('check') ?>Approve</button></form><?php } ?>
<a href="<?= url('dashboard/admin/songs/edit') ?>?edit=<?= $s['id'] ?>" class="btn btn-ghost btn-sm rounded-circle" title="Edit"><?= icon('pen') ?></a>
<?php $delete_id = (int)$s['id']; $delete_confirm = 'Delete this song?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../shared/partials/delete-button.php'; ?>
</div>
</td></tr><?php } } ?>
</tbody></table></div>
</div>
