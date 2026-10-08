<?php
if (!defined('DASHBOARD_PARTIAL_GUARD')) { http_response_code(404); exit; }
/** @var array   $overview   computed in the section page (dashboard/index.php) */
/** @var string  $dash_type  'user' | 'artist' */

$cards = [
    ["Favorites", $overview['favorites'], 'heart', 'danger'],
];
if ($dash_type === 'user') {
    $cards[] = ["Song Requests", $overview['song_requests'], 'headphones', 'primary'];
}
if ($dash_type === 'artist') {
    $cards = [];
    $cards[] = ["My Songs", $overview['songs_total'], 'music', 'purple'];
    $cards[] = ["Published Songs", $overview['songs_published'], 'circle-check', 'success'];
    $cards[] = ["Total Plays", $overview['plays'], 'play', 'primary'];
    $cards[] = ["Total Downloads", $overview['downloads'], 'download', 'secondary'];
}
?>
<div class="row g-4">
<?php foreach ($cards as $c) { ?>
<div class="col-md-6 col-lg-3"><div class="card stat-card">
<div class="card-body d-flex align-items-center gap-3">
<div class="stat-icon bg-<?= e($c[3]) ?> bg-opacity-10 text-<?= e($c[3]) ?>"><?= icon($c[2]) ?></div>
<div><div class="text-muted small"><?= $c[0] ?></div><div class="fs-4 fw-bold"><?= number_format($c[1]) ?></div></div>
</div></div></div>
<?php } ?>
</div>

<div class="card border-0 shadow mt-4" style="border-radius:12px"><div class="card-body">
<div class="d-flex flex-wrap gap-2">
<a href="<?= url('dashboard/favorites') ?>" class="btn btn-outline-primary"><?= icon('heart', 'me-1') ?>My Favorites</a>
<?php if ($dash_type === 'artist') { ?>
<a href="<?= url('dashboard/songs') ?>" class="btn btn-outline-primary"><?= icon('music', 'me-1') ?>My Songs</a>
<?php } ?>
<?php if ($dash_type === 'user') { ?>
<a href="<?= url('dashboard/song-requests') ?>" class="btn btn-outline-primary"><?= icon('headphones', 'me-1') ?>Request a song</a>
<?php } ?>
<a href="<?= url('dashboard/profile') ?>" class="btn btn-outline-secondary"><?= icon('gear', 'me-1') ?>Profile</a>
</div>
<p class="small text-muted mt-4 mb-0"><?= e(sprintf("Member since %s", isset($overview['since']) && $overview['since'] ? date('M Y', strtotime($overview['since'])) : date('M Y'))) ?></p>
</div></div>