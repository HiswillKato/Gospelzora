<?php
require_once __DIR__ . '/../app/bootstrap.php';

$page_title = "Music";
$page_description = "Stream and download the full gospel music catalog - search, listen and add favorites.";

$q = trim($_GET['q'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$artist = (int)($_GET['artist'] ?? 0);
$sort = $_GET['sort'] ?? 'latest';
$page = max(1, (int)($_GET['page'] ?? 1));

$catalog = $music->catalog([
    'q' => $q,
    'category' => $category,
    'artist' => $artist,
    'sort' => $sort,
    'page' => $page,
]);
$songs = $catalog['songs'];
$pagination = $catalog['pagination'];

$categories = $music->categories();
$artists_list = $user->options();

require_once __DIR__ . '/../app/includes/public/header.php';
?>

<?php
$page_header_title = "Music";
$page_header_subtitle = "Explore our collection of gospel songs";
$page_header_content = '';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>

<section class="py-4">
    <div class="container">
        <div class="filter-bar">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Song or artist...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($categories as $c) { ?>
                        <option value="<?= $c['id'] ?>" <?= $category == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Artist</label>
                    <select name="artist" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($artists_list as $a) { ?>
                        <option value="<?= $a['id'] ?>" <?= $artist == $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Sort</label>
                    <select name="sort" class="form-select">
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popular</option>
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-gold w-100">Filter</button>
                </div>
            </form>
        </div>
        
        <?php
        $grid_items = $songs;
        $grid_card_opt = ['music' => $music];
        $grid_empty_icon = 'list'; $grid_empty_title = "No songs found"; $grid_empty_text = "Try adjusting your filters"; $grid_empty_cta = '';
        require __DIR__ . '/../app/includes/partials/song-grid.php';
        ?>

        <?php if ($songs) { ?>
        <div class="mt-5">
            <?php
            $filter_qs = http_build_query(array_filter(['q' => $q, 'category' => $category, 'artist' => $artist, 'sort' => $sort]));
            $base_url = url('music') . ($filter_qs !== '' ? '?' . $filter_qs : '');
            require __DIR__ . '/../app/includes/partials/pagination.php';
            ?>
        </div>
        <?php } ?>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 