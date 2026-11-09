<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'public/index.php', 'public/install.php', 'public/.htaccess', 'routes/web.php',
    'deploy/nginx.conf.example', 'app/core/Router.php', 'app/controllers/Home.php',
    'app/controllers/Api.php', 'app/views/public/home.php', 'app/views/public/about.php',
    'app/views/public/contact.php', 'app/views/public/terms.php', 'app/views/public/privacy.php',
    'app/views/public/copyright.php', 'app/views/music/index.php', 'app/views/music/show.php',
    'app/views/music/download.php', 'app/views/music/favorites.php', 'app/views/artists/index.php',
    'app/views/artists/show.php', 'app/views/categories/index.php', 'app/views/categories/show.php',
    'app/views/charts/index.php', 'app/views/search/index.php', 'app/views/playlists/index.php',
    'app/views/playlists/show.php', 'app/views/auth/login.php', 'app/views/auth/register.php',
    'app/views/auth/forgot.php', 'app/views/auth/logout.php', 'app/views/auth/reset.php',
    'app/views/auth/verify-email.php', 'app/views/dashboard/index.php', 'app/views/dashboard/profile.php',
    'app/views/dashboard/songs.php', 'app/views/dashboard/song-requests.php',
    'app/views/dashboard/add-song.php', 'app/views/dashboard/playlists.php',
    'app/views/dashboard/favorites.php', 'app/views/admin/index.php', 'app/views/admin/login.php',
    'app/views/admin/logout.php', 'app/views/admin/songs.php', 'app/views/admin/artists.php',
    'app/views/admin/categories.php', 'app/views/admin/users.php', 'app/views/admin/admins.php',
    'app/views/admin/messages.php', 'app/views/admin/logs.php', 'app/views/admin/logins.php',
    'app/views/admin/visits.php', 'app/views/admin/downloads.php', 'app/views/admin/pages.php',
    'app/views/admin/maintenance.php', 'app/views/admin/add-song.php', 'app/views/admin/add-artist.php',
    'app/views/admin/add-category.php', 'app/views/admin/add-user.php', 'app/views/admin/add-admin.php',
    'app/views/admin/page-edit.php', 'app/views/dashboard/partials/overview.php',
    'app/views/dashboard/partials/songs.php', 'app/views/admin/partials/account-form.php',
    'app/views/admin/partials/account-list.php', 'app/views/shared/member/header.php',
    'app/views/shared/member/footer.php', 'public/assets/css/style.css', 'public/assets/js/app.js',
    'public/assets/images/logo.svg',
];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) {
        fwrite(STDERR, "FAIL: missing required migration file {$path}\n");
        exit(1);
    }
}
foreach (['pages', 'dashboard', 'api', 'assets'] as $legacy) {
    if (is_dir($root . '/' . $legacy)) {
        fwrite(STDERR, "FAIL: legacy root {$legacy}/ remains\n");
        exit(1);
    }
}
foreach (['app/includes', 'dashboard/includes', 'dashboard/admin/includes'] as $legacy) {
    if (is_dir($root . '/' . $legacy)) {
        fwrite(STDERR, "FAIL: retired includes {$legacy}/ remains\n");
        exit(1);
    }
}
if (is_file($root . '/public/asset-fallback.php')) {
    fwrite(STDERR, "FAIL: transitional asset fallback remains\n");
    exit(1);
}
$publicPhp = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $publicPhp[] = substr($file->getPathname(), strlen($root) + 1);
    }
}
sort($publicPhp);
if ($publicPhp !== ['public/index.php', 'public/install.php']) {
    fwrite(STDERR, "FAIL: unexpected PHP entry points in public/: " . implode(', ', $publicPhp) . "\n");
    exit(1);
}
$routes = file_get_contents($root . '/routes/web.php');
if ($routes === false || !str_contains($routes, "\$router->add") || !str_contains($routes, "'/api/player'")) {
    fwrite(STDERR, "FAIL: front-controller route map is incomplete\n");
    exit(1);
}
$authContracts = [
    'login' => '$user->login(', 'register' => '$user->register(', 'forgot' => '$user->recover(',
    'logout' => '$user->logout(', 'reset' => '$user->reset(',
    'verify-email' => '$user->consume(\'email_verify\'',
];
foreach ($authContracts as $view => $contract) {
    $source = file_get_contents($root . '/app/views/auth/' . $view . '.php');
    if ($source === false || !str_contains($source, $contract)) {
        fwrite(STDERR, "FAIL: auth view {$view} lacks workflow\n");
        exit(1);
    }
}
$nginx = file_get_contents($root . '/deploy/nginx.conf.example');
foreach (['root /var/www/gospelzora/public;', 'try_files $uri $uri/ /index.php?$query_string;', 'location ~ \\.php$ { return 404; }'] as $directive) {
    if ($nginx === false || !str_contains($nginx, $directive)) {
        fwrite(STDERR, "FAIL: nginx example missing required public-root/security directive: {$directive}\n");
        exit(1);
    }
}
echo "PASS: public document root, route targets, canonical views, assets, and retired entry points verified\n";
