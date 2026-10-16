<?php
/**
 * A single playlist. Public playlists are open to everyone; a private one is only
 * readable by the account that owns it.
 */
require_once __DIR__ . '/../app/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$playlist = $slug !== '' ? $music->playlist($slug) : null;

$viewer_id = $user->authed() ? (int)$_SESSION['user_id'] : null;
$viewer_role = $viewer_id !== null ? $user->role() : null;

if (!$playlist || !$music->visible($playlist, $viewer_id, $viewer_role)) {
    http_response_code(404);
    require __DIR__ . '/../app/includes/public/header.php';
    $page_header_title = '';
    $page_header_subtitle = '';
    $page_header_content = '<h1 class="fw-bold mb-2">' . "Playlist not found" . '</h1>'
        . '<p class="text-muted mb-0">This playlist does not exist, or it is private.</p>';
    require __DIR__ . '/../app/includes/partials/page-header.php';
    require __DIR__ . '/../app/includes/public/footer.php';
    exit;
}

$owner_id = (int)$playlist['user'];
$is_owner = $viewer_id !== null
    && $viewer_id === $owner_id
    && ($playlist['type'] ?? '') === ($viewer_role ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Your session has expired. Please try again.');
    } elseif (!$is_owner) {
        flash('danger', 'Only the owner of a playlist can change it.');
    } elseif ($action === 'remove') {
        $result = $music->detach((int)$playlist['id'], (int)($_POST['song'] ?? 0));
        flash($result['success'] ? 'success' : 'danger', $result['message']);
    } elseif ($action === 'move') {
        $result = $music->move((int)$playlist['id'], (int)($_POST['song'] ?? 0), (string)($_POST['direction'] ?? ''));
        flash($result['success'] ? 'success' : 'danger', $result['message']);
    } else {
        flash('danger', "That action is not recognized.");
    }
    redirect('playlist/' . $playlist['slug']);
}

$songs = $playlist['songs_list'];

$page_title = $playlist['name'];
$page_description = $playlist['description'] ?: sprintf('%d song playlist on %s.', (int)$playlist['songs'], SITE_NAME);

require_once __DIR__ . '/../app/includes/public/header.php';
?>
<?php
$page_header_title = '';
$page_header_subtitle = '';
$page_header_content = '
<div class="d-flex flex-column flex-md-row align-items-center gap-4">
<div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:140px;height:140px;background:var(--navy);color:var(--gold);font-size:3rem">' . icon('list-music') . '</div>
<div class="text-center text-md-start flex-grow-1">
<h1 class="fw-bold mb-1">' . e($playlist['name']) . '</h1>
<p class="text-muted mb-1">' . sprintf("%d song%s", count($songs), count($songs) === 1 ? '' : 's') . '</p>
<p class="small text-muted mb-2">' . icon($playlist['privacy'] === 'public' ? 'globe' : 'lock', 'me-1') . ($playlist['privacy'] === 'public' ? 'Public playlist' : 'Private playlist') . '</p>
' . (!empty($playlist['description']) ? '<p class="mb-3" style="max-width:640px">' . e($playlist['description']) . '</p>' : '') . '
<div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
<button type="button" class="btn btn-gold" id="playAllPlaylist" data-songs="' . e(json_encode(array_map(
    static fn($s) => [
        'id' => (int)$s['id'],
        'title' => $s['title'],
        'artist' => $s['artist_name'],
        'cover' => image($s['artwork'] ?? '', 'song'),
        'audio' => asset($s['audio'] ?? ''),
        'duration' => (int)($s['duration'] ?? 0),
    ],
    $songs
))) . '">' . icon('play', 'me-1') . 'Play all</button>
' . ($is_owner ? '<a class="btn btn-outline-secondary" href="' . e(url('playlists?edit=' . (int)$playlist['id'])) . '">' . icon('pen', 'me-1') . 'Edit details</a>' : '') . '
</div>
</div></div>';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>

<section class="py-5"><div class="container">
<?php if ($songs === []) { ?>
    <?php
    $empty_icon = 'list-music';
    $empty_title = "This playlist is empty";
    $empty_text = "Add songs from the Music page using the playlist button on any song card.";
    $empty_cta = '<a href="' . e(url('music')) . '" class="btn btn-gold">' . "Browse Music" . '</a>';
    require __DIR__ . '/../app/includes/partials/empty-state.php';
    ?>
<?php } else { ?>
    <div class="row g-3">
        <?php foreach ($songs as $i => $song) { ?>
        <div class="col-12">
            <div class="card song-card h-100" data-href="<?= e(url('song/' . ($song['slug'] ?? ''))) ?>">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="position-relative flex-shrink-0">
                        <img src="<?= e(image($song['artwork'] ?? '', 'song')) ?>" class="rounded" alt="<?= e($song['title'] ?? '') ?>" style="width:64px;height:64px;object-fit:cover">
                        <span class="position-absolute top-0 start-0 translate-middle badge bg-dark bg-opacity-75"><?= $i + 1 ?></span>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <h6 class="card-title text-truncate mb-1"><?= e($song['title'] ?? '') ?></h6>
                        <p class="card-text small text-muted text-truncate mb-1"><?= e($song['artist_name'] ?? '') ?></p>
                        <?php if ((int)($song['duration'] ?? 0) > 0) { ?>
                        <p class="small text-muted mb-0"><?= icon('clock', 'me-1') ?><?= duration((int)$song['duration']) ?></p>
                        <?php } ?>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0 song-card-controls">
                        <button class="btn btn-gold btn-sm btn-play-song"
                         data-id="<?= (int)$song['id'] ?>" data-title="<?= e($song['title'] ?? '') ?>" data-artist="<?= e($song['artist_name'] ?? '') ?>"
                         data-cover="<?= e(image($song['artwork'] ?? '', 'song')) ?>" data-audio="<?= e(asset($song['audio'] ?? '')) ?>"
                         data-duration="<?= e($song['duration'] ?? '') ?>"
                         aria-label="Play <?= e($song['title'] ?? '') ?>"><?= icon('play') ?></button>
                        <a class="btn btn-outline-secondary btn-sm song-download" href="<?= e(url('download/' . ($song['slug'] ?? ''))) ?>" title="Download" aria-label="Download <?= e($song['title'] ?? '') ?>"><?= icon('download') ?></a>
                        <?php if ($is_owner) { ?>
                        <div class="d-flex gap-1">
                            <form method="POST" action="<?= e(url('playlist/' . $playlist['slug'])) ?>" class="d-flex gap-1">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="song" value="<?= (int)$song['id'] ?>">
                                <button class="btn btn-outline-secondary btn-sm" name="direction" value="up" title="Move up" aria-label="Move <?= e($song['title'] ?? '') ?> up" <?= $i === 0 ? 'disabled' : '' ?>><?= icon('arrow-up') ?></button>
                                <button class="btn btn-outline-secondary btn-sm" name="direction" value="down" title="Move down" aria-label="Move <?= e($song['title'] ?? '') ?> down" <?= $i === count($songs) - 1 ? 'disabled' : '' ?>><?= icon('arrow-down') ?></button>
                            </form>
                            <form method="POST" action="<?= e(url('playlist/' . $playlist['slug'])) ?>" onsubmit="return confirm('Remove this song from the playlist?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="song" value="<?= (int)$song['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm" title="Remove from playlist" aria-label="Remove <?= e($song['title'] ?? '') ?> from playlist"><?= icon('xmark') ?></button>
                            </form>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
<?php } ?>
</div></section>

<?php
$extra_js = <<<'HTML'
<script>
(function () {
    var btn = document.getElementById('playAllPlaylist');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var songs;
        try { songs = JSON.parse(btn.getAttribute('data-songs') || '[]'); }
        catch (e) { return; }
        if (!songs.length || typeof GospelPlayer === 'undefined') return;
        GospelPlayer.playPlaylist(songs);
    });
})();
</script>
HTML;
require_once __DIR__ . '/../app/includes/public/footer.php'; ?>