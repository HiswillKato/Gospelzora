<?php
require_once __DIR__ . '/../../bootstrap.php';
$user->guard('login');
$page_title = 'My Favorites';
$page_description = 'Your saved gospel songs, all in one place.';
$user_id = (int)$_SESSION['user_id'];
$songs = $music->favorites($user_id);
$page_header_title = 'My Favorites';
$page_header_subtitle = "Songs you've saved";
$page_header_content = '';
require BASE_PATH . '/app/views/shared/partials/page-header.php';
?>
<section class="py-5"><div class="container">
<?php
$grid_items = $songs;
$grid_card_opt = ['favorite' => true, 'download' => false, 'active' => true, 'danger' => true, 'logged_in' => true];
$grid_empty_icon = 'heart';
$grid_empty_title = 'No favorites yet';
$grid_empty_text = 'Click the heart icon on any song to save it here.';
$grid_empty_cta = '<a href="' . e(url('music')) . '" class="btn btn-gold">Browse Music</a>';
require BASE_PATH . '/app/views/shared/partials/song-grid.php';
?>
</div></section>
