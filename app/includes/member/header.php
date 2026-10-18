<?php
$dash_user = $dash_user ?? $user->current();
$dash_type = $dash_type ?? $user->role();
$section = $section ?? 'overview';

/* This shell serves artists and listeners — and the signed-in admin who opens
 * their own profile — so what it calls the account follows the role instead of
 * the artist/listener pair it started with. Pages that mention the account
 * read the same map, so the wording lives in one place. */
$dash_role = [
    'artist' => ['label' => "Artist", 'place' => "Artist studio"],
    'admin'  => ['label' => "Admin",  'place' => "Admin account"],
][$dash_type] ?? ['label' => "Member", 'place' => "Member area"];

$nav_main = [
    'overview'   => ['title' => "Dashboard",    'icon' => 'fa-gauge-high', 'href' => 'dashboard'],
    'favorites'  => ['title' => "My Favorites", 'icon' => 'fa-heart',      'href' => 'dashboard/favorites'],
    'playlists'  => ['title' => "My Playlists", 'icon' => 'fa-list', 'href' => 'dashboard/playlists'],
];
if ($dash_type === 'artist') {
    $nav_main['songs'] = ['title' => "My Songs", 'icon' => 'fa-music', 'href' => 'dashboard/songs'];
    $nav_main['add-song'] = ['title' => "Add Song", 'icon' => 'fa-plus', 'href' => 'dashboard/add-song'];
}
if ($dash_type === 'user') {
    $nav_main['song-requests'] = ['title' => "Song Requests", 'icon' => 'fa-headphones', 'href' => 'dashboard/song-requests'];
}
$nav_main['profile'] = ['title' => "Profile", 'icon' => 'fa-user', 'href' => 'dashboard/profile'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrf()) ?>">
<meta name="theme-color" content="#241440">
<link rel="icon" href="<?= asset('images/logo.svg') ?>" type="image/svg+xml">
<link rel="icon" href="<?= asset('images/favicon.ico') ?>" sizes="any">
<title><?= e($page_title) ?> • <?= SITE_NAME ?></title>
<?php require __DIR__ . '/../partials/theme-script.php'; require __DIR__ . '/../partials/css-links.php'; ?>
</head>
<body data-base-url="<?= e(rtrim(BASE_URL, '/')) ?>">
<div class="admin-shell d-flex">
  <aside class="offcanvas-lg offcanvas-start admin-side text-body" tabindex="-1" id="memberOffcanvas" aria-labelledby="memberOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
      <span class="eyebrow mb-0" id="memberOffcanvasLabel"><?= e($dash_role['place']) ?></span>
      <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#memberOffcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3">
      <nav class="d-grid gap-1">
        <?php foreach ($nav_main as $item) {
            $active = $section === $item['href'] || ($section === 'overview' && $item['href'] === 'dashboard'); ?>
        <a class="admin-link<?= $active ? ' active' : '' ?>" href="<?= url($item['href']) ?>"><?= icon($item['icon']) ?><span class="text-truncate"><?= e($item['title']) ?></span></a>
        <?php } ?>
      </nav>
      <div class="mt-auto pt-3">
        <hr class="my-2 text-body-secondary">
        <nav class="d-grid gap-1">
          <a class="admin-link" href="<?= url() ?>" target="_blank" rel="noopener"><?= icon('arrow-up-right-from-square') ?><span>View site</span></a>
          <form method="POST" action="<?= url('logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
          <button type="submit" class="admin-link border-0 bg-transparent w-100 text-start"><?= icon('right-from-bracket') ?><span>Sign out</span></button></form>
        </nav>
        <p class="small text-body-secondary mb-0 px-1 mt-3">Signed in as<br><span class="fw-semibold text-body"><?= e($dash_user['name']) ?></span></p>
      </div>
    </div>
  </aside>

  <div class="flex-grow-1 min-width-0 d-flex flex-column">
    <header class="admin-topbar d-flex align-items-center gap-2 gap-lg-3 px-2 px-sm-3 px-lg-4 py-2 py-lg-3">
      <button class="btn btn-ghost rounded-circle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberOffcanvas" aria-controls="memberOffcanvas" aria-label="Open navigation">
        <?= icon('bars') ?>
      </button>
      <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="<?= url('dashboard') ?>">
        <span class="brand-mark" aria-hidden="true"><img src="<?= e(asset('images/logo.svg')) ?>" alt=""></span>
        <span class="brand-name text-truncate"><?= e(SITE_NAME) ?></span>
      </a>
      <div class="ms-auto d-flex align-items-center gap-2">
        <?php /* The topbar is the densest row in the app: below sm the live-site
               chip (the sidebar repeats it) gives way to the brand name. */ ?>
        <a class="btn btn-ghost rounded-circle d-none d-sm-inline-block" href="<?= url() ?>" target="_blank" rel="noopener" aria-label="View the live site" title="View the live site">
          <?= icon('arrow-up-right-from-square') ?>
        </a>
        <button class="theme-toggle btn btn-ghost rounded-circle d-inline-flex align-items-center justify-content-center"
                type="button" id="themeToggle" aria-label="Toggle theme" title="Toggle theme">
          <?= icon('sun') ?><?= icon('moon') ?>
        </button>
        <a class="btn btn-ghost rounded-circle" href="<?= url('dashboard/profile') ?>" aria-label="Your profile" title="Your profile">
          <?= icon('user') ?>
        </a>
        <span class="d-none d-md-inline small text-body-secondary"><?= e(sprintf("Hello, %s", $dash_user['name'])) ?></span>
      </div>
    </header>
    <main class="container-fluid p-3 p-lg-4 flex-grow-1">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 mb-0"><?= e($page_title) ?></h1>
        <?php if (!empty($header_actions)) { ?><div class="d-flex flex-wrap gap-2"><?= $header_actions ?></div><?php } ?>
      </div>
      <?php $flash = flash(); if ($flash) { require __DIR__ . '/../partials/flash.php'; } ?>
