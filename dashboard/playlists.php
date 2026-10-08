<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user->guard('login');

$dash_type = $user->role();
$dash_user = $user->current();
$dash_id = (int)$dash_user['id'];

$section = 'playlists';
$page_title = "My Playlists";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid security token. Please try again.');
        redirect('dashboard/playlists');
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'delete') {
        $r = $music->erase((int)($_POST['id'] ?? 0), $dash_id, $dash_type);
        flash($r['success'] ? 'success' : 'danger', $r['message']);
    }
    redirect('dashboard/playlists');
}

$playlists = $music->playlists($dash_id, $dash_type);

require __DIR__ . '/../app/includes/member/header.php';
?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0"><?= icon('list-music', 'me-2') ?>My Playlists</h5>
    <a href="<?= url('playlists') ?>" class="btn btn-sm btn-gold"><?= icon('plus', 'me-1') ?>Manage playlists</a>
</div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Songs</th><th>Visibility</th><th>Created</th><th></th></tr></thead><tbody>
<?php if (empty($playlists)) { ?>
<tr><td colspan="5" class="text-center text-muted py-4">No playlists yet</td></tr>
<?php } else { foreach ($playlists as $p) { ?>
<tr>
<td><a href="<?= url('playlist/' . $p['slug']) ?>" class="text-decoration-none"><?= e($p['name']) ?></a></td>
<td><?= (int)$p['songs'] ?></td>
<td><span class="badge <?= $p['privacy'] === 'public' ? 'bg-success' : 'bg-secondary' ?>"><?= $p['privacy'] === 'public' ? 'Public' : 'Private' ?></span></td>
<td><?= ago($p['created']) ?></td>
<td class="text-end">
<a href="<?= url('playlist/' . $p['slug']) ?>" class="btn btn-sm btn-outline-secondary" title="Open"><?= icon('eye') ?></a>
<form method="POST" class="d-inline" onsubmit="return confirm('Delete this playlist? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
<button class="btn btn-sm btn-outline-danger" title="Delete"><?= icon('trash') ?></button></form>
</td></tr>
<?php } } ?>
</tbody></table></div>
</div></div>
<?php require __DIR__ . '/../app/includes/member/footer.php'; ?>