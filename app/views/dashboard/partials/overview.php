<?php
if (!defined('DASHBOARD_PARTIAL_GUARD')) { http_response_code(404); exit; }
/** @var array   $overview   computed in the section page (dashboard/index.php) */
/** @var string  $dash_type  'user' | 'artist' */

/* [label, value, glyph, colour, page it opens] */
$cards = [];
if ($dash_type === 'user') {
    $cards[] = ["Favorites", $overview['favorites'], 'heart', 'danger', 'dashboard/favorites'];
    $cards[] = ["Song Requests", $overview['song_requests'], 'headphones', 'primary', 'dashboard/song-requests'];
} else {
    $cards[] = ["My Songs", $overview['songs_total'], 'music', 'primary', 'dashboard/songs'];
    $cards[] = ["Published Songs", $overview['songs_published'], 'circle-check', 'success', 'dashboard/songs'];
    $cards[] = ["Total Plays", $overview['plays'], 'play', 'info', 'dashboard/songs'];
    $cards[] = ["Total Downloads", $overview['downloads'], 'download', 'secondary', 'dashboard/songs'];
}
?>
<div class="row g-3 mb-3 mb-lg-4">
<?php foreach ($cards as [$stat_label, $stat_value, $stat_icon, $stat_color, $stat_link]) { $stat_href = url($stat_link); ?>
<div class="col-6 col-lg-3"><?php require __DIR__ . '/../../shared/partials/stat-card.php'; ?></div>
<?php } ?>
</div>

<div class="card tile">
<div class="card-header fw-semibold">Quick actions</div>
<div class="card-body d-grid gap-2 d-sm-flex flex-sm-wrap">
<a href="<?= url('dashboard/favorites') ?>" class="btn btn-gold"><?= icon('heart') ?>My favorites</a>
<?php if ($dash_type === 'artist') { ?>
<a href="<?= url('dashboard/songs') ?>" class="btn btn-ghost"><?= icon('music') ?>My songs</a>
<a href="<?= url('dashboard/add-song') ?>" class="btn btn-ghost"><?= icon('plus') ?>Add a song</a>
<?php } else { ?>
<a href="<?= url('dashboard/song-requests') ?>" class="btn btn-ghost"><?= icon('headphones') ?>Request a song</a>
<?php } ?>
<a href="<?= url('dashboard/profile') ?>" class="btn btn-ghost"><?= icon('gear') ?>Profile</a>
<a href="<?= url() ?>" target="_blank" rel="noopener" class="btn btn-ghost"><?= icon('arrow-up-right-from-square') ?>View the live site</a>
</div>
</div>

<p class="small text-body-secondary mt-3 mb-0"><?= e(sprintf("Member since %s", isset($overview['since']) && $overview['since'] ? date('M Y', strtotime($overview['since'])) : date('M Y'))) ?></p>
