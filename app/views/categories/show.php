<?php
require_once __DIR__ . '/../../bootstrap.php';
$slug = $_GET['slug'] ?? '';
$cat = $music->category($slug);
if (!$cat || ($cat['status'] ?? 'active') !== 'active') { require dirname(__DIR__, 3) . '/pages/404.php'; exit; }
$id = (int)$cat['id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = $music->browse($id, $page);
$songs = $result['songs'];
$pagination = $result['pagination'];
$page_title = e($cat['name']);
require_once __DIR__ . '/../shared/header.php';
?>
<?php
$page_header_title = e($cat['name']);
$page_header_subtitle = e($cat['description'] ?? '');
$page_header_content = '';
require __DIR__ . '/../shared/partials/page-header.php';
?>
<section class="py-4"><div class="container">
<?php
$grid_items = $songs;
$grid_card_opt = ['favorite' => false];
$grid_empty_icon = 'music'; $grid_empty_title = "No songs in this category"; $grid_empty_text = ''; $grid_empty_cta = '';
require __DIR__ . '/../shared/partials/song-grid.php';
if ($songs) { ?>
<div class="mt-4"><?php
$base_url = url('category/'.$cat['slug']);
require __DIR__ . '/../shared/partials/pagination.php';
?></div>
<?php } ?></div></section>
<?php require_once __DIR__ . '/../shared/footer.php'; 