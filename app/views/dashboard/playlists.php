<?php
require_once __DIR__ . '/../../bootstrap.php';
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

require __DIR__ . '/../shared/member/header.php';
?>
<div class="card tile">
<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
<span class="fw-semibold"><?= icon('list') ?>My Playlists</span>
<a href="<?= url('playlists') ?>" class="btn btn-gold btn-sm"><?= icon('plus') ?>Manage playlists</a>
</div>
<?php if (empty($playlists)) { ?>
<div class="p-5 text-center">
<?= icon('list', '', 'fa-2x text-primary d-block mb-3') ?>
<p class="text-body-secondary mb-0">No playlists yet. Build one from the site to see it here.</p>
</div>
<?php } else { ?>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th>Songs</th><th>Visibility</th><th>Created</th><th></th></tr></thead><tbody>
<?php foreach ($playlists as $p) { ?>
<tr>
<td><a href="<?= url('playlist/' . $p['slug']) ?>"><?= e($p['name']) ?></a></td>
<td><?= (int)$p['songs'] ?></td>
<td><span class="badge rounded-pill <?= $p['privacy'] === 'public' ? 'bg-success' : 'bg-secondary' ?>"><?= $p['privacy'] === 'public' ? 'Public' : 'Private' ?></span></td>
<td><?= ago($p['created']) ?></td>
<td class="text-end text-nowrap">
<a href="<?= url('playlist/' . $p['slug']) ?>" class="btn btn-ghost btn-sm rounded-circle" title="Open"><?= icon('eye') ?></a>
<form method="POST" class="d-inline" onsubmit="return confirm('Delete this playlist? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
<button class="btn btn-ghost btn-sm rounded-circle text-danger" title="Delete"><?= icon('trash') ?></button></form>
</td></tr>
<?php } ?>
</tbody></table></div>
<?php } ?>
</div>
<?php require __DIR__ . '/../shared/member/footer.php'; ?>