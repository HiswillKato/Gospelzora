<?php
require_once __DIR__ . '/../../bootstrap.php';
$user->guard('login');
if ($user->check('admin') || $user->role() !== 'user') redirect('dashboard');

$dash_type = 'user';
$dash_user = $user->current();
$dash_id = (int)$dash_user['id'];

$section = 'song-requests';
$page_title = "Song Requests";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid security token. Please try again.');
        redirect('dashboard/song-requests');
    }
    if (($_POST['action'] ?? '') === 'song_request') {
        $result = $music->request([
            'title' => trim($_POST['title'] ?? ''),
            'artist' => (int)($_POST['artist'] ?? 0),
            'category' => (int)($_POST['category'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
        ], $dash_id);
        if ($result['success']) {
            redirect('dashboard/song-requests');
        }
        flash('danger', $result['message']);
        $keep = '?artist=' . (int)($_POST['artist'] ?? 0)
            . '&title=' . urlencode(trim($_POST['title'] ?? ''))
            . '&note=' . urlencode(trim($_POST['description'] ?? ''))
            . (isset($_POST['category']) ? '&category=' . (int)$_POST['category'] : '');
        redirect('dashboard/song-requests' . $keep);
    }
    flash('danger', 'Invalid request.');
    redirect('dashboard/song-requests');
}

$artists = $user->options();
$categories = $music->categories();
$my_requests = $music->requests($dash_id);

$keepArtist = max(0, (int)($_GET['artist'] ?? 0));
$keepTitle = trim($_GET['title'] ?? '');
$keepNote = trim($_GET['note'] ?? '');
$keepCategory = max(0, (int)($_GET['category'] ?? 0));

require __DIR__ . '/../shared/member/header.php';
?>
<div class="row g-4">
<div class="col-lg-5">
<div class="card tile">
<div class="card-header"><span class="fw-semibold">Request a song</span></div>
<div class="card-body">
<p class="text-body-secondary small">Found a song that is missing? Request it and an admin will add it when it is available.</p>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="song_request">
<div class="mb-3"><label class="form-label">Song title</label>
<input type="text" name="title" class="form-control" required minlength="2" maxlength="200" value="<?= e($keepTitle) ?>"></div>
<div class="mb-3"><label class="form-label">Artist</label>
<select name="artist" class="form-select" required>
<option value="">Select an artist</option>
<?php foreach ($artists as $a) { ?>
<option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === $keepArtist ? 'selected' : '' ?>><?= e($a['name']) ?></option>
<?php } ?></select></div>
<div class="mb-3"><label class="form-label">Category <span class="text-body-secondary small">(optional)</span></label>
<select name="category" class="form-select">
<option value="0">None</option>
<?php foreach ($categories as $c) { ?>
<option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $keepCategory ? 'selected' : '' ?>><?= e($c['name']) ?></option>
<?php } ?></select></div>
<div class="mb-3"><label class="form-label">Note (optional)</label>
<textarea name="description" class="form-control" rows="4" maxlength="2000" placeholder="Optional note for the admin (e.g. a specific version or what you have heard)."><?= e($keepNote) ?></textarea></div>
<button type="submit" class="btn btn-gold"><?= icon('paper-plane') ?>Submit request</button>
</form>
</div></div>
</div>

<div class="col-lg-7">
<div class="card tile">
<div class="card-header"><span class="fw-semibold">My song requests</span></div>
<?php if (empty($my_requests)) { ?>
<div class="p-5 text-center">
<?= icon('paper-plane', '', 'fa-2x text-primary d-block mb-3') ?>
<p class="text-body-secondary mb-0">You have not requested any songs yet.</p>
</div>
<?php } else { ?>
<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr>
<th>Song title</th><th>Artist</th><th>Submitted</th><th>Status</th></tr></thead><tbody>
<?php foreach ($my_requests as $r) { ?>
<tr>
<td><?= e($r['title']) ?><?= !empty($r['description']) ? ' <span class="text-body-secondary" title="' . e($r['description']) . '">' . icon('comment') . '</span>' : '' ?></td>
<td><?= e($r['artist_name']) ?></td>
<td class="text-body-secondary small text-nowrap"><?= e(date('M j, Y', strtotime($r['created']))) ?></td>
<td><span class="badge rounded-pill bg-info">Requested</span></td>
</tr><?php } ?>
</tbody></table></div>
<?php } ?>
</div>
</div>
</div>
<?php require __DIR__ . '/../shared/member/footer.php'; 