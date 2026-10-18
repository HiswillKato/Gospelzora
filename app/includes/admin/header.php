<?php
$user->guard('staff');
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'User';
if ($user->check('artist') && ($page_title ?? '') !== "Songs") {
    redirect('dashboard/admin/songs');
}

/* One menu, two groups, filtered by role — the sidebar renders from this and
 * nothing else, so a link is added in exactly one place. 'admin' rows are for
 * full admins, 'staff' rows for artists too. */
$admin_here = (string)($page_title ?? '');
$admin_menu = [
    ['Dashboard', 'dashboard', 'gauge-high', 'admin', 'staff'],
    ['Songs', 'dashboard/admin/songs', 'music', 'staff', 'staff'],
    ['Artists', 'dashboard/admin/artists', 'users', 'admin', 'admin'],
    ['Categories', 'dashboard/admin/categories', 'tags', 'admin', 'admin'],
    ['Users', 'dashboard/admin/users', 'user', 'admin', 'admin'],
    ['Admins', 'dashboard/admin/admins', 'user-shield', 'admin', 'admin'],
    ['Messages', 'dashboard/admin/messages', 'envelope', 'admin', 'admin'],
    ['Pages', 'dashboard/admin/pages', 'file-lines', 'admin', 'admin'],
];
$admin_system = [
    ['Activity Logs', 'dashboard/admin/logs', 'clipboard-list'],
    ['Login Records', 'dashboard/admin/logins', 'right-to-bracket'],
    ['Visitor Stats', 'dashboard/admin/visits', 'chart-column'],
    ['Download Stats', 'dashboard/admin/downloads', 'download'],
    ['Maintenance settings', 'dashboard/admin/maintenance', 'screwdriver-wrench'],
];
$admin_unread = $user->check('admin') ? (int)$db->scalar("SELECT COUNT(*) FROM messages WHERE seen = 0") : 0;
$admin_link = static function (string $label, string $href, string $glyph, string $badge = '') use ($admin_here): string {
    $active = $admin_here === $label ? ' active' : '';
    return '<a class="admin-link' . $active . '" href="' . e(url($href)) . '">'
        . icon($glyph) . '<span class="text-truncate">' . e($label) . '</span>' . $badge . '</a>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="<?= asset('images/logo.svg') ?>" type="image/svg+xml">
<link rel="icon" href="<?= asset('images/favicon.ico') ?>" sizes="any">
<title><?= isset($page_title) ? $page_title.' • ' : '' ?><?= SITE_NAME ?> Admin</title>
<?php require __DIR__ . '/../partials/theme-script.php'; require __DIR__ . '/../partials/css-links.php'; ?>
</head>
<body data-base-url="<?= e(rtrim(BASE_URL, '/')) ?>">
<div class="admin-shell d-flex">
  <aside class="offcanvas-lg offcanvas-start admin-side text-body" tabindex="-1" id="adminOffcanvas" aria-labelledby="adminOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
      <span class="eyebrow mb-0" id="adminOffcanvasLabel">Admin panel</span>
      <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminOffcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3">
      <nav class="d-grid gap-1">
        <?php foreach ($admin_menu as [$label, $href, $glyph, $for]) {
            if ($for === 'admin' && !$user->check('admin')) { continue; }
            $badge = $label === 'Messages' && $admin_unread > 0
                ? '<span class="badge rounded-pill text-bg-warning ms-auto">' . $admin_unread . '</span>' : '';
            echo $admin_link($label, $href, $glyph, $badge);
        } ?>
        <?php if ($user->check('admin')) { ?>
        <hr class="my-2 text-body-secondary">
        <div class="eyebrow px-1 mb-1">System</div>
        <?php foreach ($admin_system as [$label, $href, $glyph]) { echo $admin_link($label, $href, $glyph); } ?>
        <?php } ?>
      </nav>
      <div class="mt-auto pt-3">
        <hr class="my-2 text-body-secondary">
        <nav class="d-grid gap-1">
          <a class="admin-link" href="<?= url() ?>" target="_blank" rel="noopener"><?= icon('arrow-up-right-from-square', '', 'flex-shrink-0') ?><span>View site</span></a>
          <form method="POST" action="<?= url('dashboard/admin/logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
          <button type="submit" class="admin-link border-0 bg-transparent w-100 text-start"><?= icon('right-from-bracket', '', 'flex-shrink-0') ?><span>Sign out</span></button></form>
        </nav>
        <p class="small text-body-secondary mb-0 px-1 mt-3">Signed in as<br><span class="fw-semibold text-body"><?= e($admin_name) ?></span></p>
      </div>
    </div>
  </aside>

  <div class="flex-grow-1 min-width-0 d-flex flex-column">
    <header class="admin-topbar d-flex align-items-center gap-2 gap-lg-3 px-2 px-sm-3 px-lg-4 py-2 py-lg-3">
      <button class="btn btn-ghost rounded-circle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminOffcanvas" aria-controls="adminOffcanvas" aria-label="Open navigation">
        <?= icon('bars') ?>
      </button>
      <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="<?= url('dashboard') ?>">
        <span class="brand-mark" aria-hidden="true"><img src="<?= e(asset('images/logo.svg')) ?>" alt=""></span>
        <span class="brand-name text-truncate"><?= e(SITE_NAME) ?></span>
      </a>
      <div class="ms-auto d-flex align-items-center gap-2">
        <button class="theme-toggle btn btn-ghost rounded-circle d-inline-flex align-items-center justify-content-center"
                type="button" id="themeToggle" aria-label="Toggle theme" title="Toggle theme">
          <?= icon('sun') ?><?= icon('moon') ?>
        </button>
        <a class="btn btn-ghost rounded-circle" href="<?= url('dashboard/profile') ?>" aria-label="Your profile" title="Your profile">
          <?= icon('user') ?>
        </a>
        <span class="d-none d-md-inline small text-body-secondary"><?= e(sprintf("Hello, %s", $admin_name)) ?></span>
      </div>
    </header>
    <main class="container-fluid p-3 p-lg-4 flex-grow-1">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 mb-0"><?= e($page_title ?? "Admin") ?></h1>
        <?php if (!empty($header_actions)) { ?><div class="d-flex flex-wrap gap-2"><?= $header_actions ?></div><?php } ?>
      </div>
      <?php $flash = flash(); if ($flash) { require __DIR__ . '/../partials/flash.php'; } ?>
