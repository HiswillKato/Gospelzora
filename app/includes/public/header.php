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

    <!-- Masthead -->
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <!-- Flash Messages -->
    <?php $flash = flash(); if ($flash) { ?>
    <div class="container mt-3">
        <?php require __DIR__ . '/../partials/flash.php'; ?>
    </div>
    <?php } ?>

    <!-- Main Content -->
    <main class="flex-grow-1">
