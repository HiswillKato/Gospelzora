<?php
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = sprintf("Welcome to %s", SITE_NAME);
$page_description = "Browse gospel music, top charts, artists and categories - free streaming and downloads.";

// Latest songs
$latest = $music->ranked('latest', 8);

// Popular songs
$popular = $music->ranked('popular', 8);

// Featured artists
$result = $user->artists(['limit' => 6, 'order' => 'songs']);
$artists = $result['artists'];

// Categories
$categories = $music->categories();

require_once __DIR__ . '/../app/includes/public/header.php';
?>

<!-- Hero -->
<section class="hero-section">
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="hero-title"><?= sprintf("Welcome to %s", SITE_NAME) ?></h1>
                <p class="hero-tagline"><?= SITE_TAGLINE ?></p>
                <p class="lead text-muted mb-4">Welcome! Dive in and stream anointed gospel music that inspires worship, strengthens faith, and transforms lives — from gifted artists around the world.</p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="<?= url('music') ?>" class="btn btn-gold btn-lg">
                        <?= icon('list', 'me-2') ?>Explore Music
                    </a>
                    <a href="<?= url('artists') ?>" class="btn btn-outline-purple btn-lg">
                        <?= icon('users', 'me-2') ?>Browse Artists
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Latest Releases -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4"><div>
            <h2 class="section-title">Latest Releases</h2>
            <p class="section-subtitle mb-0">Fresh songs added to the platform</p>
        </div>
            <a href="<?= url('music') ?>" class="btn btn-outline-purple btn-sm d-none d-md-inline-flex">View all <?= icon('arrow-right', 'ms-1') ?></a>
        </div>
        <div class="row g-4">
            <?php
            $grid_items = $latest; $grid_row_open = true;
            $grid_card_opt = ['music' => $music];
            $grid_empty_icon = 'music'; $grid_empty_title = "No songs yet."; $grid_empty_text = "New releases will appear here as songs are added."; $grid_empty_cta = '';
            require __DIR__ . '/../app/includes/partials/song-grid.php';
            ?>
        </div>
    </div>
</section>

<!-- Popular Songs -->
<section class="py-5 bg-purple-soft">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4"><div>
            <h2 class="section-title">Popular Songs</h2>
            <p class="section-subtitle mb-0">Most played tracks on Gospelzora</p>
        </div>
        </div>
        <div class="row g-4">
            <?php
            $grid_items = $popular; $grid_row_open = true;
            $grid_card_opt = ['music' => $music, 'plays' => true];
            $grid_empty_icon = 'fire'; $grid_empty_title = "No popular songs yet"; $grid_empty_text = "Most-played tracks will show up here once songs get some plays."; $grid_empty_cta = '';
            require __DIR__ . '/../app/includes/partials/song-grid.php';
            ?>
        </div>
    </div>
</section>

<!-- Featured Artists -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4"><div>
            <h2 class="section-title">Featured Artists</h2>
            <p class="section-subtitle mb-0">Gifted voices leading worship</p>
        </div>
            <a href="<?= url('artists') ?>" class="btn btn-outline-purple btn-sm d-none d-md-inline-flex">View all <?= icon('arrow-right', 'ms-1') ?></a>
        </div>
        <div class="row g-4">
            <?php if (empty($artists)) {
                $empty_icon = 'user-group'; $empty_title = "No artists yet"; $empty_text = "Featured artists will appear here as they join the platform."; $empty_cta = '';
                require __DIR__ . '/../app/includes/partials/empty-state.php';
            } else { foreach ($artists as $artist) {
                $card_opt = [];
                require __DIR__ . '/../app/includes/partials/artist-card.php';
            } } ?>
        </div>
    </div>
</section>

<!-- Categories -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title text-center mb-2">Music Categories</h2>
        <p class="section-subtitle text-center">Find the perfect sound for every moment of worship</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <?php if (empty($categories)) { ?>
            <p class="text-muted">No categories yet.</p>
            <?php } else { foreach ($categories as $cat) { ?>
            <a href="<?= url('category/' . $cat['slug']) ?>" class="category-pill">
                <?= icon($cat['icon'] ?: 'music', 'me-1') ?><?= e($cat['name']) ?>
            </a>
            <?php } } ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5" style="background: var(--navy);">
    <div class="container text-center text-white">
        <h2 class="fw-bold mb-3">Start Your Worship Journey Today</h2>
        <p class="lead mb-4 opacity-75">Explore thousands of gospel songs that will inspire, encourage, and draw you closer to God.</p>
        <a href="<?= url('music') ?>" class="btn btn-gold btn-lg">
            <?= icon('headphones', 'me-2') ?>Browse the Collection
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 