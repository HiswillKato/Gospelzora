<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_user = $user->current();
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf()) ?>">
    <link rel="icon" href="/favicon.ico">
    <meta name="theme-color" content="#241440">
    <?php
    $meta_description = isset($page_description) && $page_description !== ''
        ? $page_description
        : "Stream and download uplifting gospel music, discover new artists and build your favorites list.";
    $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $page_url = rtrim(BASE_URL, '/') . ($current_path ?: '/');
    $page_title_tag = isset($page_title) && $page_title !== '' ? $page_title : SITE_NAME;
    ?>
    <meta name="description" content="<?= $meta_description ?>">
    <link rel="canonical" href="<?= e($page_url) ?>">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $page_title_tag ?>">
    <meta property="og:description" content="<?= $meta_description ?>">
    <meta property="og:url" content="<?= e($page_url) ?>">
    <meta property="og:image" content="<?= e(asset('images/artwork.png')) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= $page_title_tag ?>">
    <meta name="twitter:description" content="<?= $meta_description ?>">
    <meta name="twitter:image" content="<?= e(asset('images/artwork.png')) ?>">
    <title><?= isset($page_title) ? $page_title . ' • ' : '' ?><?= SITE_NAME ?></title>

    <?php require __DIR__ . '/../partials/theme-script.php'; ?>

    <!-- Bootstrap CSS (local) -->
    <?php require __DIR__ . '/../partials/css-links.php'; ?>

    <?php if (isset($extra_css)) { ?>
        <?= $extra_css ?>
    <?php } ?>
</head>
<body class="d-flex flex-column min-vh-100" data-base-url="<?= e(rtrim(BASE_URL, '/')) ?>">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top gospel-navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= url() ?>">
                <img src="<?= e(asset('images/logo.png')) ?>" alt="<?= SITE_NAME ?>" class="site-logo me-2" width="32" height="32">
                <span class="fw-bold"><?= SITE_NAME ?></span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($current_path, '/') || $current_path === '/' ? 'active' : '' ?>" href="<?= url() ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($current_path, '/music') !== false ? 'active' : '' ?>" href="<?= url('music') ?>">Music</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($current_path, '/artists') !== false ? 'active' : '' ?>" href="<?= url('artists') ?>">Artists</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($current_path, '/categories') !== false ? 'active' : '' ?>" href="<?= url('categories') ?>">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($current_path, '/charts') !== false ? 'active' : '' ?>" href="<?= url('charts') ?>">Top Charts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($current_path, '/playlists') !== false ? 'active' : '' ?>" href="<?= url('playlists') ?>">Playlists</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    <!-- Search -->
                    <form class="navbar-search search-form d-none d-md-flex" action="<?= e(url('search')) ?>" method="GET">
                        <div class="input-group input-group-sm">
                            <input type="search" name="q" class="form-control form-control-sm search-input" placeholder="Search songs or artists" aria-label="Search songs or artists" value="<?= e($_GET['q'] ?? '') ?>">
                            <button class="btn btn-gold btn-sm" type="submit"><?= icon('magnifying-glass') ?></button>
                        </div>
                    </form>
                    <button type="button" class="theme-toggle btn btn-outline-light btn-sm rounded-circle" id="themeToggle" aria-label="Toggle theme" title="Toggle theme">
                        <?= icon('sun', 'solid', 'theme-icon theme-icon--sun') ?>
                        <?= icon('moon', 'solid', 'theme-icon theme-icon--moon') ?>
                    </button>
                    <?php if ($current_user) { ?>
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= icon('circle-user', 'me-1 fs-5') ?>
                                <span class="d-none d-sm-inline"><?= e($current_user['name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item" href="<?= url('dashboard/profile') ?>">                                <?= icon('user', 'me-2') ?>Profile</a></li>
                                <li><a class="dropdown-item" href="<?= url('dashboard') ?>">                                <?= icon('gauge-high', 'me-2') ?>Dashboard</a></li>
                                <?php if (($current_user['role'] ?? '') === 'artist') { ?>
                                <li><a class="dropdown-item" href="<?= url('dashboard/songs') ?>">                                <?= icon('cloud-arrow-up', 'me-2') ?>Upload Music</a></li>
                                <?php } ?>
                                <?php if (($current_user['role'] ?? '') === 'user') { ?>
                                <li><a class="dropdown-item" href="<?= url('dashboard/song-requests') ?>">                                <?= icon('headphones', 'me-2') ?>Request a song</a></li>
                                <?php } ?>
                                <li><a class="dropdown-item" href="<?= url('favorites') ?>">                                <?= icon('heart', 'me-2') ?>Favorites</a></li>
                                <li><a class="dropdown-item" href="<?= url('playlists') ?>">                                <?= icon('list', 'me-2') ?>Playlists</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="<?= url('logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                <button type="submit" class="dropdown-item text-danger"><?= icon('right-from-bracket', 'me-2') ?>Logout</button>
                                </form></li>
                            </ul>
                        </div>
                    <?php } else { ?>
                        <a href="<?= url('login') ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">Login</a>
                        <a href="<?= url('register') ?>" class="btn btn-gold btn-sm rounded-pill px-3">Register</a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Search -->
    <div class="d-md-none bg-dark py-2 position-relative z-3">
        <div class="container">
            <form class="search-form mobile-search" action="<?= e(url('search')) ?>" method="GET">
                <div class="input-group">
                    <input type="search" name="q" class="form-control" placeholder="Search songs or artists" aria-label="Search songs or artists" value="<?= e($_GET['q'] ?? '') ?>">
                    <button class="btn btn-gold" type="submit"><?= icon('magnifying-glass') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = flash(); if ($flash) { ?>
    <div class="container mt-3">
        <?php require __DIR__ . '/../partials/flash.php'; ?>
    </div>
    <?php } ?>

    <!-- Main Content -->
    <main class="flex-grow-1">
