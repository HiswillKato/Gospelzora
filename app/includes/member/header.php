<?php
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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrf()) ?>">
<meta name="theme-color" content="#241440">
<title><?= $page_title ?> • <?= SITE_NAME ?></title>
<?php require __DIR__ . '/../partials/theme-script.php'; require __DIR__ . '/../partials/css-links.php'; ?>
</head>
<body class="bg-body-tertiary" data-base-url="<?= e(rtrim(BASE_URL, '/')) ?>">
<div class="admin-shell d-flex">
  <aside class="offcanvas-lg offcanvas-start bg-dark text-white border-0" tabindex="-1" id="memberOffcanvas" aria-labelledby="memberOffcanvasLabel">
    <div class="offcanvas-header border-bottom border-secondary">
      <h5 class="offcanvas-title d-flex align-items-center gap-2" id="memberOffcanvasLabel">
        <img src="<?= e(asset('images/logo.png')) ?>" alt="<?= SITE_NAME ?>" width="32" height="32" class="rounded">
        <span><?= SITE_NAME ?></span>
      </h5>
      <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#memberOffcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3">
      <nav class="nav nav-pills flex-column mb-auto gap-1">
        <?php foreach ($nav_main as $item) { ?>
        <a class="nav-link text-white-50 <?= $section === $item['href'] || ($section === 'overview' && $item['href'] === 'dashboard') ? 'active bg-white text-dark' : '' ?>" href="<?= url($item['href']) ?>"><?= icon($item['icon'], 'me-2') ?><?= $item['title'] ?></a>
        <?php } ?>
        <hr class="text-secondary">
        <a class="nav-link text-white-50" href="<?= url() ?>" target="_blank"><?= icon('arrow-up-right-from-square', 'me-2') ?>View Site</a>
      </nav>
      <div class="border-top border-secondary pt-3 mt-3">
        <form method="POST" action="<?= url('logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><button type="submit" class="btn btn-outline-light w-100"><?= icon('right-from-bracket', 'me-2') ?>Logout</button></form>
      </div>
    </div>
  </aside>
  <div class="flex-grow-1 min-vh-100 d-flex flex-column">
    <header class="navbar navbar-expand-lg bg-body border-bottom sticky-top">
      <div class="container-fluid">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberOffcanvas" aria-controls="memberOffcanvas" aria-label="Open navigation">
          <?= icon('bars') ?>
        </button>
        <h2 class="h5 mb-0"><?= $page_title ?></h2>
        <div class="ms-auto d-flex align-items-center gap-2">
          <span class="text-muted small"><?= e(sprintf("Hello, %s", $dash_user['name'])) ?></span>
        </div>
      </div>
    </header>
    <main class="container-fluid py-4 flex-grow-1">
      <?php $flash = flash(); if ($flash) { require __DIR__ . '/../partials/flash.php'; } ?>
