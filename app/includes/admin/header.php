<?php
$user->guard('staff');
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'User';
if ($user->check('artist') && ($page_title ?? '') !== "Songs") {
    redirect('dashboard/admin/songs');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="<?= asset('images/logo.png') ?>">
<title><?= isset($page_title) ? $page_title.' • ' : '' ?><?= SITE_NAME ?> Admin</title>
<?php require __DIR__ . '/../partials/theme-script.php'; require __DIR__ . '/../partials/css-links.php'; ?>
</head>
<body class="bg-body-tertiary" data-base-url="<?= e(rtrim(BASE_URL, '/')) ?>">
<div class="admin-shell d-flex">
  <aside class="offcanvas-lg offcanvas-start bg-dark text-white border-0" tabindex="-1" id="adminOffcanvas" aria-labelledby="adminOffcanvasLabel">
    <div class="offcanvas-header border-bottom border-secondary">
      <h5 class="offcanvas-title d-flex align-items-center gap-2" id="adminOffcanvasLabel">
        <img src="<?= e(asset('images/logo.png')) ?>" alt="<?= SITE_NAME ?>" width="32" height="32" class="rounded">
        <span><?= SITE_NAME ?></span>
      </h5>
      <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminOffcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3">
      <nav class="nav nav-pills flex-column mb-auto gap-1">
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Dashboard'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard') ?>"><?= icon('gauge-high', 'me-2') ?>Dashboard</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Songs'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/songs') ?>"><?= icon('music', 'me-2') ?>Songs</a>
        <?php if ($user->check('admin')) { ?>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Artists'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/artists') ?>"><?= icon('users', 'me-2') ?>Artists</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Categories'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/categories') ?>"><?= icon('tags', 'me-2') ?>Categories</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Users'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/users') ?>"><?= icon('user', 'me-2') ?>Users</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Admins'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/admins') ?>"><?= icon('user-shield', 'me-2') ?>Admins</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Messages'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/messages') ?>"><?= icon('envelope', 'me-2') ?>Messages</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Pages'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/pages') ?>"><?= icon('file-lines', 'me-2') ?>Pages</a>
        <hr class="text-secondary">
        <div class="text-uppercase text-secondary small px-1 mb-1">System</div>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Activity Logs'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/logs') ?>"><?= icon('clipboard-list', 'me-2') ?>Activity Logs</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Login Records'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/logins') ?>"><?= icon('right-to-bracket', 'me-2') ?>Login Records</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Visitor Stats'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/visits') ?>"><?= icon('chart-column', 'me-2') ?>Visitor Stats</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Download Stats'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/downloads') ?>"><?= icon('download', 'me-2') ?>Download Stats</a>
        <a class="nav-link text-white-50 <?= ($page_title??'')==='Maintenance settings'?'active bg-white text-dark':'' ?>" href="<?= url('dashboard/admin/maintenance') ?>"><?= icon('screwdriver-wrench', 'me-2') ?>Maintenance settings</a>
        <?php } ?>
      </nav>
      <div class="border-top border-secondary pt-3 mt-3">
        <form method="POST" action="<?= url('dashboard/admin/logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><button type="submit" class="btn btn-outline-light w-100"><?= icon('right-from-bracket', 'me-2') ?>Logout</button></form>
      </div>
    </div>
  </aside>
  <div class="flex-grow-1 min-vh-100 d-flex flex-column">
    <header class="navbar navbar-expand-lg bg-body border-bottom sticky-top">
      <div class="container-fluid">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminOffcanvas" aria-controls="adminOffcanvas" aria-label="Open navigation">
          <?= icon('bars') ?>
        </button>
        <h2 class="h5 mb-0"><?= $page_title ?? "Admin" ?></h2>
        <div class="ms-auto d-flex align-items-center gap-2">
          <?php if (!empty($header_actions)) echo $header_actions; ?>
          <span class="text-muted small"><?= e(sprintf("Hello, %s", $admin_name)) ?></span>
        </div>
      </div>
    </header>
    <main class="container-fluid py-4 flex-grow-1">
      <?php $flash = flash(); if ($flash) { require __DIR__ . '/../partials/flash.php'; } ?>
