<?php
$user->guard('staff');
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'User';
if ($user->check('artist') && ($page_title ?? '') !== "Songs") {
    redirect('dashboard/admin/songs');
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="/favicon.ico">
<title><?= isset($page_title) ? $page_title.' • ' : '' ?><?= SITE_NAME ?> Admin</title>
<?php require __DIR__ . '/../partials/theme-script.php';
require __DIR__ . '/../partials/css-links.php'; ?>
</head><body class="admin" data-base-url="<?= e(rtrim(BASE_URL, '/')) ?>">
<div class="d-flex">
<aside class="admin-sidebar p-3" id="adminSidebar" style="width:260px;min-height:100vh">
<a href="<?= url('dashboard') ?>" class="d-flex align-items-center text-white text-decoration-none mb-4">
<img src="<?= e(asset('images/logo.png')) ?>" alt="<?= SITE_NAME ?>" class="site-logo me-2" width="32" height="32"><span class="fw-bold"><?= SITE_NAME ?></span></a>
<button type="button" class="admin-sidebar-close btn btn-link text-white d-md-none" id="adminSidebarClose" aria-label="Close admin navigation">
<?= icon('xmark') ?>
</button>
<nav class="nav flex-column">
<a class="nav-link <?= ($page_title??'')==="Dashboard"?'active':'' ?>" href="<?= url('dashboard') ?>"><?= icon('gauge-high', 'me-2') ?>Dashboard</a>
<a class="nav-link <?= ($page_title??'')==="Songs"?'active':'' ?>" href="<?= url('dashboard/admin/songs') ?>"><?= icon('music', 'me-2') ?>Songs</a>
<?php if ($user->check('admin')) { ?>
<a class="nav-link <?= ($page_title??'')==="Artists"?'active':'' ?>" href="<?= url('dashboard/admin/artists') ?>"><?= icon('users', 'me-2') ?>Artists</a>
<a class="nav-link <?= ($page_title??'')==="Categories"?'active':'' ?>" href="<?= url('dashboard/admin/categories') ?>"><?= icon('tags', 'me-2') ?>Categories</a>
<a class="nav-link <?= ($page_title??'')==="Users"?'active':'' ?>" href="<?= url('dashboard/admin/users') ?>"><?= icon('user', 'me-2') ?>Users</a>
<a class="nav-link <?= ($page_title??'')==="Admins"?'active':'' ?>" href="<?= url('dashboard/admin/admins') ?>"><?= icon('user-shield', 'me-2') ?>Admins</a>
<a class="nav-link <?= ($page_title??'')==="Messages"?'active':'' ?>" href="<?= url('dashboard/admin/messages') ?>"><?= icon('envelope', 'me-2') ?>Messages</a>
<a class="nav-link <?= ($page_title??'')==="Pages"?'active':'' ?>" href="<?= url('dashboard/admin/pages') ?>"><?= icon('file-lines', 'me-2') ?>Pages</a>
<hr class="border-secondary">
<div class="text-uppercase text-white-50 small px-1 mb-1">System</div>
<a class="nav-link <?= ($page_title??'')==="Activity Logs"?'active':'' ?>" href="<?= url('dashboard/admin/logs') ?>"><?= icon('clipboard-list', 'me-2') ?>Activity Logs</a>
<a class="nav-link <?= ($page_title??'')==="Login Records"?'active':'' ?>" href="<?= url('dashboard/admin/logins') ?>"><?= icon('right-to-bracket', 'me-2') ?>Login Records</a>
<a class="nav-link <?= ($page_title??'')==="Visitor Stats"?'active':'' ?>" href="<?= url('dashboard/admin/visits') ?>"><?= icon('chart-column', 'me-2') ?>Visitor Stats</a>
<a class="nav-link <?= ($page_title??'')==="Download Stats"?'active':'' ?>" href="<?= url('dashboard/admin/downloads') ?>"><?= icon('download', 'me-2') ?>Download Stats</a>
<a class="nav-link <?= ($page_title??'')==="Maintenance settings"?'active':'' ?>" href="<?= url('dashboard/admin/maintenance') ?>"><?= icon('screwdriver-wrench', 'me-2') ?>Maintenance settings</a>
<?php } ?>
<hr class="border-secondary">
<a class="nav-link" href="<?= url() ?>" target="_blank"><?= icon('arrow-up-right-from-square', 'me-2') ?>View Site</a>
<form method="POST" action="<?= url('dashboard/admin/logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><button type="submit" class="nav-link text-danger sidebar-logout"><?= icon('right-from-bracket', 'me-2') ?>Logout</button></form>
</nav></aside>
<div class="admin-sidebar-backdrop" id="adminSidebarBackdrop" aria-hidden="true"></div>
<main class="admin-main flex-grow-1 p-4" style="background:#f8f7fc;min-height:100vh">
<div class="d-flex justify-content-between align-items-center mb-4">
<div class="d-flex align-items-center gap-2">
<button type="button" class="admin-sidebar-toggle btn btn-outline-secondary d-md-none" id="adminSidebarToggle" aria-label="Open admin navigation" aria-controls="adminSidebar" aria-expanded="false">
<?= icon('bars') ?>
</button>
<h2 class="fw-bold mb-0"><?= $page_title ?? "Admin" ?></h2>
</div>
<div class="d-flex align-items-center gap-3">
<?php if (!empty($header_actions)) echo $header_actions; ?>
<button type="button" class="theme-toggle btn btn-outline-secondary btn-sm rounded-circle" id="themeToggle" aria-label="Toggle theme" title="Toggle theme">
<?= icon('sun', 'solid', 'theme-icon theme-icon--sun') ?>
                        <?= icon('moon', 'solid', 'theme-icon theme-icon--moon') ?>
</button>
<span class="text-muted"><?= e(sprintf("Hello, %s", $admin_name)) ?></span>
</div>
</div>
<?php $flash = flash(); if ($flash) { require __DIR__ . '/../partials/flash.php'; } ?>
<?php if ($settings->maintenance()) { ?>
<div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
<span><?= icon('screwdriver-wrench', 'me-2') ?>Maintenance mode is active — the public site is offline.</span>
<a href="<?= url('dashboard/admin/maintenance') ?>" class="btn btn-sm btn-outline-warning text-nowrap">Manage</a>
</div>
<?php }