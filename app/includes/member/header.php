<?php
/* Dashboard shell (top half). Each dashboard section page sets $section and
 * $page_title before requiring this file. $dash_user / $dash_type are resolved
 * here when the page hasn't built them yet. */
$dash_user = $dash_user ?? $user->current();
$dash_type = $dash_type ?? $user->role();
$section = $section ?? 'overview';

$nav_main = [
    'overview'   => ['title' => "Dashboard",    'icon' => 'fa-gauge-high', 'href' => 'dashboard'],
    'favorites'  => ['title' => "My Favorites", 'icon' => 'fa-heart',      'href' => 'dashboard/favorites'],
    'playlists'  => ['title' => "My Playlists", 'icon' => 'fa-list-music', 'href' => 'dashboard/playlists'],
];
if ($dash_type === 'artist') {
    $nav_main['songs'] = ['title' => "My Songs", 'icon' => 'fa-music', 'href' => 'dashboard/songs'];
    $nav_main['add-song'] = ['title' => "Add Song", 'icon' => 'fa-plus', 'href' => 'dashboard/add-song'];
}
if ($dash_type === 'user') {
    $nav_main['song-requests'] = ['title' => "Song Requests", 'icon' => 'fa-headphones', 'href' => 'dashboard/song-requests'];
}
$nav_main['profile']     = ['title' => "Profile",           'icon' => 'fa-user', 'href' => 'dashboard/profile'];
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrf()) ?>">
<meta name="description" content="<?= isset($page_description) ? e($page_description) : '' ?>">
<link rel="icon" href="/favicon.ico">
<meta name="theme-color" content="#241440">
<title><?= $page_title ?> • <?= SITE_NAME ?></title>
<?php require __DIR__ . '/../partials/theme-script.php';
require __DIR__ . '/../partials/css-links.php'; ?>
</head><body class="admin" data-base-url="<?= e(rtrim(BASE_URL, '/')) ?>">
<div class="d-flex">
<aside class="admin-sidebar p-3" id="adminSidebar" style="width:260px;min-height:100vh">
<a href="<?= url('dashboard') ?>" class="d-flex align-items-center text-white text-decoration-none mb-4">
<img src="<?= e(asset('images/logo.png')) ?>" alt="<?= SITE_NAME ?>" class="site-logo me-2" width="32" height="32"><span class="fw-bold"><?= SITE_NAME ?></span></a>
<button type="button" class="admin-sidebar-close btn btn-link text-white d-md-none" id="adminSidebarClose" aria-label="Close navigation">
<?= icon('xmark') ?>
</button>
<nav class="nav flex-column">
<?php foreach ($nav_main as $item) { ?>
<a class="nav-link <?= $section === $item['href'] || ($section === 'overview' && $item['href'] === 'dashboard') ? 'active' : '' ?>" href="<?= url($item['href']) ?>"><?= icon($item['icon'], 'me-2') ?><?= $item['title'] ?></a>
<?php } ?>
<hr class="border-secondary">
<a class="nav-link" href="<?= url() ?>" target="_blank"><?= icon('arrow-up-right-from-square', 'me-2') ?>View Site</a>
<form method="POST" action="<?= url('logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><button type="submit" class="nav-link text-danger sidebar-logout"><?= icon('right-from-bracket', 'me-2') ?>Logout</button></form>
</nav></aside>
<div class="admin-sidebar-backdrop" id="adminSidebarBackdrop" aria-hidden="true"></div>
<main class="admin-main flex-grow-1 p-4" style="background:#f8f7fc;min-height:100vh">
<div class="d-flex justify-content-between align-items-center mb-4">
<div class="d-flex align-items-center gap-2">
<button type="button" class="admin-sidebar-toggle btn btn-outline-secondary d-md-none" id="adminSidebarToggle" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false">
<?= icon('bars') ?>
</button>
<h2 class="fw-bold mb-0"><?= $page_title ?></h2>
</div>
<div class="d-flex align-items-center gap-3">
<button type="button" class="theme-toggle btn btn-outline-secondary btn-sm rounded-circle" id="themeToggle" aria-label="Toggle theme" title="Toggle theme">
<?= icon('sun', 'solid', 'theme-icon theme-icon--sun') ?>
                        <?= icon('moon', 'solid', 'theme-icon theme-icon--moon') ?>
</button>
<span class="text-muted"><?= e(sprintf("Hello, %s", $dash_user['name'])) ?></span>
</div>
</div>
<?php $flash = flash(); if ($flash) { require __DIR__ . '/../partials/flash.php'; }