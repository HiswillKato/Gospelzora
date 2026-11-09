<?php
require_once __DIR__ . '/../../bootstrap.php';
$user->guard('login');
if ($user->check('admin')) redirect('dashboard');

$dash_type = $user->role();
$dash_user = $user->current();
$dash_id = (int)$dash_user['id'];

$section = 'favorites';
$page_title = "My Favorites";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid security token. Please try again.');
        redirect('dashboard/favorites');
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'unfavorite') {
        $songId = (int)($_POST['id'] ?? 0);
        if ($songId) {
            $r = $music->toggle($dash_id, $songId, $dash_type);
            flash($r['success'] ? 'success' : 'danger', $r['message']);
        }
    }
    redirect('dashboard/favorites');
}

$favorites = $music->favorites($dash_id, $dash_type);

require __DIR__ . '/../shared/member/header.php';
?>
<div class="card tile">
<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
<span class="fw-semibold">My Favorites</span>
<a href="<?= url('favorites') ?>" class="btn btn-ghost btn-sm">View on site</a>
</div>
<?php if (empty($favorites)) { ?>
<div class="p-5 text-center">
<?= icon('heart', '', 'fa-2x text-primary d-block mb-3') ?>
<p class="text-body-secondary mb-0">No favorites yet. Tap the heart on any song to save it here.</p>
</div>
<?php } else { ?>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Title</th><th>Artist</th><th>Plays</th><th></th></tr></thead><tbody>
<?php foreach ($favorites as $f) { ?>
<tr>
<td><a href="<?= url('song/' . $f['slug']) ?>"><?= e($f['title']) ?></a></td>
<td><?= e($f['artist_name']) ?></td>
<td><?= abbrev($f['plays']) ?></td>
<td class="text-end">
<form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="unfavorite"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
<button class="btn btn-ghost btn-sm rounded-circle text-danger" title="Remove from favorites"><?= icon('heart') ?></button></form>
</td></tr>
<?php } ?>
</tbody></table></div>
<?php } ?>
</div>
<?php require __DIR__ . '/../shared/member/footer.php'; 