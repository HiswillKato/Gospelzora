<?php
require_once __DIR__ . '/../app/bootstrap.php';
$q = trim($_GET['q'] ?? '');
$page_title = $q ? e(sprintf("Search: %s", $q)) : "Search";
$page_description = "Search gospel songs, artists and categories.";
$songs = $artists = $categories = [];

if ($q !== '') {
    $result = $music->search($q);
    $songs = $result['songs'];
    $artists = $result['artists'];
    $categories = $result['categories'];
}
require_once __DIR__ . '/../app/includes/public/header.php';
?>
<?php
$page_header_title = "Search";
$page_header_subtitle = '';
$page_header_content = '
<form method="GET" class="mt-3"><div class="input-group" style="max-width:500px">
<input type="search" name="q" class="form-control form-control-lg" placeholder="' . "Songs or artists..." . '" aria-label="' . "Search songs or artists" . '" value="' . e($q) . '" autofocus>
<button class="btn btn-gold btn-lg" type="submit">' . icon('magnifying-glass') . '</button></div></form>';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>
<section class="py-5"><div class="container">
<?php if ($q === '') {
    $empty_icon = 'magnifying-glass'; $empty_title = "Enter a search term"; $empty_text = ''; $empty_cta = '';
    require __DIR__ . '/../app/includes/partials/empty-state.php';
} elseif (empty($songs) && empty($artists) && empty($categories)) {
    $empty_icon = 'magnifying-glass'; $empty_title = e(sprintf("No results for \"%s\"", $q)); $empty_text = ''; $empty_cta = '';
    require __DIR__ . '/../app/includes/partials/empty-state.php';
} else { ?>
<?php if ($songs) { ?><h3 class="section-title mb-3">Songs</h3><div class="row g-4 mb-5"><?php
$grid_items = $songs; $grid_row_open = true;
$grid_card_opt = ['favorite' => false, 'download' => false];
require __DIR__ . '/../app/includes/partials/song-grid.php';
?></div><?php } ?>
<?php if ($artists) { ?><h3 class="section-title mb-3">Artists</h3><div class="row g-4 mb-5"><?php foreach ($artists as $artist) {
    $card_opt = ['compact' => true, 'cell' => 'col-6 col-md-3 col-lg-2'];
    require __DIR__ . '/../app/includes/partials/artist-card.php';
} ?></div><?php } ?>
<?php if ($categories) { ?><h3 class="section-title mb-3">Categories</h3><div class="d-flex flex-wrap gap-2"><?php foreach ($categories as $c) { ?>
<a href="<?= url('category/'.$c['slug']) ?>" class="category-pill"><?= e($c['name']) ?></a>
<?php } ?></div><?php } ?>
<?php } ?></div></section>
<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 