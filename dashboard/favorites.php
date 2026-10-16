<?php
require_once __DIR__ . '/../app/bootstrap.php';
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

require __DIR__ . '/../app/includes/member/header.php';
?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3"><h5 class="fw-bold mb-0">My Favorites</h5>
<a href="<?= url('favorites') ?>" class="btn btn-sm btn-outline-secondary">View on site</a></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Title</th><th>Artist</th><th>Plays</th><th></th></tr></thead><tbody>
<?php if (empty($favorites)) { ?>
<tr><td colspan="4" class="text-center text-muted py-4">No favorites yet</td></tr>
<?php } else { foreach ($favorites as $f) { ?>
<tr>
<td><a href="<?= url('song/' . $f['slug']) ?>" class="text-decoration-none"><?= e($f['title']) ?></a></td>
<td><?= e($f['artist_name']) ?></td>
<td><?= abbrev($f['plays']) ?></td>
<td class="text-end">
<form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="unfavorite"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
<button class="btn btn-sm btn-outline-danger" title="Remove from favorites"><?= icon('heart') ?></button></form>
</td></tr>
<?php } } ?>
</tbody></table></div></div></div>
<?php require __DIR__ . '/../app/includes/member/footer.php'; 