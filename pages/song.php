<?php
require_once __DIR__ . '/../app/bootstrap.php';

$slug = $_GET['slug'] ?? '';
$song = $music->song($slug);

if (!$song) {
    header('HTTP/1.0 404 Not Found');
    require __DIR__ . '/404.php';
    exit;
}

$categories = $music->categories($song['id']);

// Related songs
$related = $music->related($song['id'], (int)$song['artist']);

$is_fav = $music->favorited($song['id']);

$page_title = e($song['title']);
require_once __DIR__ . '/../app/includes/public/header.php';
?>

<section class="song-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-md-4 text-center">
                <img src="<?= e(image($song['artwork'], 'song')) ?>" class="song-artwork" alt="<?= e($song['title']) ?>">
            </div>
            <div class="col-md-8">
                <h1 class="display-6 fw-bold mb-2"><?= e($song['title']) ?></h1>
                <p class="fs-5 mb-1">
                    <a href="<?= url('artist/' . $song['artist_slug']) ?>" class="text-gold text-decoration-none"><?= e($song['artist_name']) ?></a>
                </p>
                
                <div class="d-flex flex-wrap gap-3 mb-3 small">
                    <span><?= icon('clock', 'me-1') ?><?= duration($song['duration']) ?></span>
                    <span><?= icon('circle-play', 'me-1') ?><?= e(abbrev($song['plays'])) ?> Plays</span>
                    <span><?= icon('download', 'me-1') ?><?= e(abbrev($song['downloads'])) ?> Downloads</span>
                </div>
                
                <?php if ($categories) { ?>
                <div class="mb-3">
                    <?php foreach ($categories as $c) { ?>
                    <a href="<?= url('category/' . $c['slug']) ?>" class="badge bg-gold text-dark me-1 text-decoration-none"><?= e($c['name']) ?></a>
                    <?php } ?>
                </div>
                <?php } ?>
                
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-gold btn-lg btn-play-song"
                            data-id="<?= $song['id'] ?>"
                            data-title="<?= e($song['title']) ?>"
                            data-artist="<?= e($song['artist_name']) ?>"
                            data-cover="<?= e(image($song['artwork'], 'song')) ?>"
                            data-audio="<?= e(asset($song['audio'])) ?>"
                            data-duration="<?= $song['duration'] ?>">
                        <?= icon('play', 'me-2') ?>Play
                    </button>
                    <button class="btn btn-outline-light btn-lg btn-favorite <?= $is_fav ? 'active' : '' ?>"
                            data-id="<?= $song['id'] ?>" data-logged-in="<?= $user->authed() ? '1' : '0' ?>"
                            aria-label="<?= e($is_fav ? "Remove from favorites" : "Add to favorites") ?>">
                        <?= icon('heart', $is_fav ? 'solid' : 'regular', 'me-1') ?>Favorite
                    </button>
                    <a href="<?= url('download/' . $song['slug']) ?>" class="song-download btn btn-outline-light btn-lg">
                        <?= icon('download', 'me-1') ?>Download
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($song['description']) { ?>
<section class="py-4">
    <div class="container">
        <h5 class="fw-semibold">About this song</h5>
        <p class="text-muted"><?= nl2br(e($song['description'])) ?></p>
    </div>
</section>
<?php } ?>

<?php if ($related) { ?>
<section class="py-5 bg-purple-soft">
    <div class="container">
        <h3 class="section-title mb-4"><?= e(sprintf("More from %s", $song['artist_name'])) ?></h3>
        <div class="row g-4">
            <?php foreach ($related as $song) {
                $card_opt = ['compact' => true];
                require __DIR__ . '/../app/includes/partials/song-card.php';
            } ?>
        </div>
    </div>
</section>
<?php } ?>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 