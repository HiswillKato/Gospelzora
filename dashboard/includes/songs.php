<?php
if (!defined('DASHBOARD_PARTIAL_GUARD')) { http_response_code(404); exit; }
/** Requires from dashboard/songs.php: $my_songs, $music, $dash_id, $dash_user, $isAjax. */

$songs_active_tab = 'dashboard-songs';
?>
<div class="card tile">
<?php if (empty($my_songs)) { ?>
<div class="p-5 text-center">
<?= icon('music', '', 'fa-2x text-primary d-block mb-3') ?>
<p class="text-body-secondary mb-3">No songs yet. Upload your first track to get it reviewed and published.</p>
<a class="btn btn-gold" href="<?= url('dashboard/add-song') ?>"><?= icon('plus') ?>Add your first song</a>
</div>
<?php } else { ?>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr>
<th>Title</th><th>Plays</th><th>Downloads</th><th>Status</th><th class="text-end">Actions</th>
</tr></thead><tbody>
<?php foreach ($my_songs as $s) { ?>
<tr>
<td><a href="<?= url('song/' . $s['slug']) ?>"><?= e($s['title']) ?></a></td>
<td><?= abbrev($s['plays']) ?></td>
<td><?= abbrev($s['downloads'] ?? 0) ?></td>
<td><span class="badge rounded-pill bg-<?= match($s['status']) {
    'published' => 'success',
    'approved' => 'primary',
    'requested' => 'info',
    'pending' => 'warning',
    default => 'secondary',
} ?>"><?= e(status((string)$s['status'])) ?></span></td>
<td class="text-end text-nowrap">
<?php if ($s['status'] === 'approved') { ?>
<form method="POST" class="d-inline-flex align-items-center gap-2" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="upload_approved"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
<input type="file" name="audio" class="form-control form-control-sm" style="max-width:190px" accept=".mp3,.m4a,.aac,.ogg,.oga,.wav,.flac,.webm" required>
<button class="btn btn-gold btn-sm" title="Upload audio"><?= icon('cloud-arrow-up') ?>Upload audio</button>
</form>
<?php } elseif ($s['status'] === 'requested') { ?>
<span class="badge rounded-pill bg-info text-dark">Awaiting approval</span>
<?php } else { ?>
<form method="POST" class="d-inline" title="Published"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="published"><button class="btn btn-ghost btn-sm rounded-circle text-success" <?= $s['status'] === 'published' ? 'disabled' : '' ?>><?= icon('circle-check') ?></button></form>
<form method="POST" class="d-inline" title="Pending"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="pending"><button class="btn btn-ghost btn-sm rounded-circle text-warning" <?= $s['status'] === 'pending' ? 'disabled' : '' ?>><?= icon('hourglass-half') ?></button></form>
<form method="POST" class="d-inline" title="Draft"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="draft"><button class="btn btn-ghost btn-sm rounded-circle" <?= $s['status'] === 'draft' ? 'disabled' : '' ?>><?= icon('pause') ?></button></form>
<a href="<?= url('dashboard/add-song') ?>?id=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm rounded-circle text-primary" title="Edit"><?= icon('pen') ?></a>
<?php $delete_id = (int)$s['id']; $delete_confirm = 'Delete this song?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../../app/includes/partials/delete-button.php'; ?>
<?php } ?>
</td></tr>
<?php } ?>
</tbody></table></div>
<?php } ?>
</div>