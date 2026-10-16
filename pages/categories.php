<?php
require_once __DIR__ . '/../app/bootstrap.php';
$page_title = "Categories";
$page_description = "Explore gospel music by category and find worship that resonates.";
$categories = $music->all();
require_once __DIR__ . '/../app/includes/public/header.php';
?>
<?php
$page_header_title = "Categories";
$page_header_subtitle = "Browse music by style and mood";
$page_header_content = '';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>
<section class="py-5"><div class="container">
<?php if (empty($categories)) {
    $empty_icon = 'tags'; $empty_title = "No categories yet"; $empty_text = "Categories will appear here once they're added."; $empty_cta = '';
    require __DIR__ . '/../app/includes/partials/empty-state.php';
} else { ?><div class="row g-4">
<?php foreach ($categories as $c) { ?>
<div class="col-6 col-md-4 col-lg-3"><a href="<?= url('category/'.$c['slug']) ?>" class="card category-card text-decoration-none text-dark h-100">
<div class="card-body text-center py-4"><?= icon($c['icon'] ?: 'music', 'text-gold', '', 'style="font-size:2.5rem"') ?>
<h5 class="mt-3 mb-1"><?= e($c['name']) ?></h5>
<p class="small text-muted mb-0"><?= e(sprintf("%d songs", (int)$c['song_count'])) ?></p></div></a></div>
<?php } ?></div><?php } ?></div></section>
<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 