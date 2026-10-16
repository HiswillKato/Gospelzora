<?php
require_once __DIR__ . '/../app/bootstrap.php';
$slug = $_GET['slug'] ?? '';
$cat = $music->category($slug);
if (!$cat || ($cat['status'] ?? 'active') !== 'active') { require __DIR__ . '/404.php'; exit; }
$id = (int)$cat['id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = $music->browse($id, $page);
$songs = $result['songs'];
$pagination = $result['pagination'];
$page_title = e($cat['name']);
require_once __DIR__ . '/../app/includes/public/header.php';
?>
<?php
$page_header_title = e($cat['name']);
$page_header_subtitle = e($cat['description'] ?? '');
$page_header_content = '';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>
<section class="py-4"><div class="container">
<?php
$grid_items = $songs;
$grid_card_opt = ['favorite' => false];
$grid_empty_icon = 'music'; $grid_empty_title = "No songs in this category"; $grid_empty_text = ''; $grid_empty_cta = '';
require __DIR__ . '/../app/includes/partials/song-grid.php';
if ($songs) { ?>
<div class="mt-4"><?php
$base_url = url('category/'.$cat['slug']);
require __DIR__ . '/../app/includes/partials/pagination.php';
?></div>
<?php } ?></div></section>
<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 