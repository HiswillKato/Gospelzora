<?php
require_once __DIR__ . '/../app/bootstrap.php';

$types = Music::CHARTS;
$type = $_GET['type'] ?? 'overall';
if (!isset($types[$type])) {
    $type = 'overall';
}
$songs = $music->chart($type, 50);

$page_title = "Top Charts";
$page_description = "See the most-played and most-downloaded gospel songs right now.";
require_once __DIR__ . '/../app/includes/public/header.php';
?>

<?php
$page_header_title = "Top Charts";
$page_header_subtitle = sprintf("The most popular gospel songs on %s", SITE_NAME);
$page_header_content = '';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>

<section class="py-4">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 mb-4" role="group" aria-label="Chart options">
            <?php foreach ($types as $key => $label) { ?>
            <a href="<?= e(url('charts?type=' . $key)) ?>"
               class="btn btn-sm rounded-pill px-3 <?= $key === $type ? 'btn-gold' : 'btn-outline-secondary' ?>"
               <?= $key === $type ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php } ?>
        </div>

        <?php $periodNote = Music::PERIOD_CHARTS[$type]['note'] ?? null;
        if ($periodNote) { ?>
        <p class="text-muted small mb-4"><?= e($periodNote) ?></p>
        <?php } ?>

        <?php if (empty($songs)) {
            if (isset(Music::PERIOD_CHARTS[$type])) {
                $empty_icon = 'chart-simple'; $empty_title = "No plays recorded yet"; $empty_text = "This chart fills up as soon as listeners start playing songs."; $empty_cta = '';
                require __DIR__ . '/../app/includes/partials/empty-state.php';
            } else {
                $empty_icon = 'chart-simple'; $empty_title = "No charted songs yet"; $empty_text = "Songs show up here as soon as they are published."; $empty_cta = '';
                require __DIR__ . '/../app/includes/partials/empty-state.php';
            }
        } else { ?>
        <div class="card border-0 shadow" style="border-radius:12px">
        <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:64px">#</th>
                    <th>Song</th>
                    <th class="d-none d-md-table-cell">Artist</th>
                    <th class="text-end">Plays</th>
                    <th class="text-end">Downloads</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($songs as $s) {
                $cover = image($s['artwork'], 'song'); ?>
                <tr>
                    <td class="fw-bold text-purple"><?= (int)$s['position'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= e($cover) ?>" alt="" width="40" height="40" class="rounded flex-shrink-0" style="object-fit:cover">
                            <div class="overflow-hidden">
                                <a href="<?= url('song/' . $s['slug']) ?>" class="text-decoration-none fw-semibold d-block text-truncate"><?= e($s['title']) ?></a>
                                <small class="text-muted d-md-none"><?= e($s['artist_name'] ?? "Unknown") ?></small>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell text-muted"><?= e($s['artist_name'] ?? "Unknown") ?></td>
                    <td class="text-end text-nowrap"><?= abbrev($s['plays']) ?></td>
                    <td class="text-end text-nowrap"><?= abbrev($s['downloads']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-gold btn-sm btn-play-song"
                                data-id="<?= $s['id'] ?>"
                                data-title="<?= e($s['title']) ?>"
                                data-artist="<?= e($s['artist_name'] ?? "Unknown") ?>"
                                data-cover="<?= e($cover) ?>"
                                data-audio="<?= e(asset($s['audio'])) ?>"
                                data-duration="<?= $s['duration'] ?>"
                                aria-label="<?= e(sprintf("Play %s", $s['title'])) ?>">
                            <?= icon('play') ?>
                        </button>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
        </div>
        </div>
        <?php } ?>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 