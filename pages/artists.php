<?php
require_once __DIR__ . '/../app/bootstrap.php';
$page_title = "Artists";
$page_description = "Meet the gospel artists behind the music and explore their songs.";
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$result = $user->artists(['q' => $q, 'page' => $page]);
$artists = $result['artists'];
$pagination = $result['pagination'];

require_once __DIR__ . '/../app/includes/public/header.php';
?>
<?php
$page_header_title = "Artists";
$page_header_subtitle = "Gifted voices leading worship";
$page_header_content = '
<form method="GET" class="mt-3"><div class="input-group" style="max-width:420px">
<input type="search" name="q" class="form-control" value="' . e($q) .
'" placeholder="' . "Search artists..." . '" aria-label="' . "Search artists" . '">
<button class="btn btn-gold" type="submit">' . icon('magnifying-glass') . '</button>
</div></form>';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>
<section class="py-5"><div class="container">
<?php if (empty($artists)) {
    $empty_icon = 'user-group'; $empty_title = "No artists found";
    $empty_text = $q ? "Try a different search term." : "No artists have joined yet. Check back soon."; $empty_cta = '';
    require __DIR__ . '/../app/includes/partials/empty-state.php';
} else { ?>
<div class="row g-4">
<?php foreach ($artists as $artist) {
    $card_opt = [];
    require __DIR__ . '/../app/includes/partials/artist-card.php';
} ?>
</div>
<?php
$filter_qs = http_build_query(array_filter(['q' => $q]));
$base_url = url('artists') . ($filter_qs !== '' ? '?' . $filter_qs : '');
?>
<div class="mt-5"><?php require __DIR__ . '/../app/includes/partials/pagination.php'; ?></div>
<?php } ?>
</div></section>
<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 