<?php
require_once __DIR__ . '/../app/bootstrap.php';
$slug = $_GET['slug'] ?? '';
$artist = $user->artist($slug);
if (!$artist) { require __DIR__ . '/404.php'; exit; }
$id = (int)$artist['id'];

$songs = $music->songs($id);
$page_title = e($artist['name']);
require_once __DIR__ . '/../app/includes/public/header.php';
?>
<?php
$page_header_title = '';
$page_header_subtitle = '';
$page_header_content = '
<div class="d-flex flex-column flex-md-row align-items-center gap-4">
<img src="' . e(image($artist['image'] ?? '', 'artist')) . '" class="rounded-3" style="width:120px;height:120px;object-fit:cover" alt="' . e($artist['name']) . '">
<div class="text-center text-md-start">
<h1 class="fw-bold mb-1">' . e($artist['name']) . '</h1>
' . (!empty($artist['country']) ? '<p class="text-muted mb-1">' . icon('location-dot', 'me-1') . e(country($artist['country'])) . '</p>' : '') . '
' . (!empty($artist['bio']) ? '<p class="mb-0" style="max-width:640px">' . e($artist['bio']) . '</p>' : '') . '
</div></div>';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>
<section class="py-5"><div class="container">
<h2 class="section-title mb-4"><?= e(sprintf("Songs by %s", $artist['name'])) ?></h2>
<?php
$grid_items = $songs;
$grid_card_opt = ['favorite' => false];
$grid_empty_icon = 'music'; $grid_empty_title = "No songs yet."; $grid_empty_text = "This artist hasn't published any songs yet."; $grid_empty_cta = '';
require __DIR__ . '/../app/includes/partials/song-grid.php';
?>
<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 