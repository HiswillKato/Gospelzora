<?php
if (!defined('DASHBOARD_PARTIAL_GUARD')) { http_response_code(404); exit; }
/** Requires from dashboard/songs.php: $my_songs, $music, $dash_id, $dash_user, $isAjax. */

$songs_active_tab = 'dashboard-songs';
?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3">
<h5 class="fw-bold mb-0">My Songs</h5>
<a class="btn btn-gold btn-sm" href="<?= url('dashboard/add-song') ?>"><?= icon('plus', 'me-1') ?>Add Song</a>
</div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr>
<th>Title</th><th>Plays</th><th>Downloads</th><th>Status</th><th class="text-end">Actions</th>
</tr></thead><tbody>
<?php if (empty($my_songs)) { ?>
<tr><td colspan="5" class="text-center text-muted py-4">No songs yet. — <a href="<?= url('dashboard/add-song') ?>" class="text-decoration-none">Add your first song</a></td></tr>
<?php } else { foreach ($my_songs as $s) { ?>
<tr>
<td><a href="<?= url('song/' . $s['slug']) ?>" class="text-decoration-none"><?= e($s['title']) ?></a></td>
<td><?= abbrev($s['plays']) ?></td>
<td><?= abbrev($s['downloads'] ?? 0) ?></td>
<td><span class="badge bg-<?= match($s['status']) {
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
<input type="file" name="audio" class="form-control form-control-sm" style="max-width:190px" accept=".mp3,.wav,.ogg,.oga,.aac,.m4a,.flac,.webm,audio/*" required>
<button class="btn btn-sm btn-gold" title="Upload audio"><?= icon('cloud-arrow-up') ?>Upload audio</button>
</form>
<?php } elseif ($s['status'] === 'requested') { ?>
<span class="badge bg-info text-dark">Awaiting approval</span>
<?php } else { ?>
<form method="POST" class="d-inline" title="Published"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="published"><button class="btn btn-sm btn-outline-success" <?= $s['status'] === 'published' ? 'disabled' : '' ?>><?= icon('circle-check') ?></button></form>
<form method="POST" class="d-inline" title="Pending"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="pending"><button class="btn btn-sm btn-outline-warning" <?= $s['status'] === 'pending' ? 'disabled' : '' ?>><?= icon('hourglass-half') ?></button></form>
<form method="POST" class="d-inline" title="Draft"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="status" value="draft"><button class="btn btn-sm btn-outline-secondary" <?= $s['status'] === 'draft' ? 'disabled' : '' ?>><?= icon('pause') ?></button></form>
<a href="<?= url('dashboard/add-song') ?>?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><?= icon('pen') ?></a>
<?php $delete_id = (int)$s['id']; $delete_confirm = 'Delete this song?'; $delete_form_class = 'd-inline';
require __DIR__ . '/../../app/includes/partials/delete-button.php'; ?>
<?php } ?>
</td></tr>
<?php } } ?>
</tbody></table></div>
</div></div>