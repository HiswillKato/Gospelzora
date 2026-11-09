<?php
declare(strict_types=1);

/**
 * Route definitions for the single public front controller.
 * Existing page handlers stay in app/views; they are never directly exposed.
 */
$render = static function (string $file, array $queryKeys = []): callable {
    return static function (...$params) use ($file, $queryKeys): void {
        global $config, $db, $analytics, $mail, $settings, $user, $music, $pages, $request, $response;
        foreach ($queryKeys as $index => $key) {
            if (array_key_exists($index, $params)) {
                $_GET[$key] = $params[$index];
            }
        }
        require BASE_PATH . '/' . $file;
    };
};

$methods = static function (string $path, callable $handler) use ($router): void {
    foreach (['GET', 'POST'] as $method) {
        $router->add($method, $path, $handler);
    }
};

$methods('/', static function (): void {
    (new \\App\\controllers\\Home())->index();
});

$publicViews = [
    '/music' => 'app/views/music/index.php',
    '/artists' => 'app/views/artists/index.php',
    '/categories' => 'app/views/categories/index.php',
    '/charts' => 'app/views/charts/index.php',
    '/search' => 'app/views/search/index.php',
    '/about' => 'app/views/public/about.php',
    '/contact' => 'app/views/public/contact.php',
    '/terms' => 'app/views/public/terms.php',
    '/privacy' => 'app/views/public/privacy.php',
    '/copyright' => 'app/views/public/copyright.php',
    '/login' => 'app/views/auth/login.php',
    '/register' => 'app/views/auth/register.php',
    '/logout' => 'app/views/auth/logout.php',
    '/forgot' => 'app/views/auth/forgot.php',
    '/reset' => 'app/views/auth/reset.php',
    '/verify-email' => 'app/views/auth/verify-email.php',
    '/favorites' => 'app/views/music/favorites.php',
    '/playlists' => 'app/views/playlists/index.php',
];
foreach ($publicViews as $path => $file) {
    $methods($path, $render($file));
}

$slugRoutes = [
    '/song/{slug}' => ['app/views/music/show.php', 'slug'],
    '/artist/{slug}' => ['app/views/artists/show.php', 'slug'],
    '/category/{slug}' => ['app/views/categories/show.php', 'slug'],
    '/playlist/{slug}' => ['app/views/playlists/show.php', 'slug'],
    '/download/{slug}' => ['app/views/music/download.php', 'slug'],
];
foreach ($slugRoutes as $path => [$file, $queryKey]) {
    $methods($path, $render($file, [$queryKey]));
}

$dashboardViews = [
    '/dashboard' => 'app/views/dashboard/index.php',
    '/dashboard/profile' => 'app/views/dashboard/profile.php',
    '/dashboard/songs' => 'app/views/dashboard/songs.php',
    '/dashboard/song-requests' => 'app/views/dashboard/song-requests.php',
    '/dashboard/add-song' => 'app/views/dashboard/add-song.php',
    '/dashboard/playlists' => 'app/views/dashboard/playlists.php',
    '/dashboard/favorites' => 'app/views/dashboard/favorites.php',
];
foreach ($dashboardViews as $path => $file) {
    $methods($path, $render($file));
}

$adminViews = [
    '/dashboard/admin' => 'app/views/admin/index.php',
    '/dashboard/admin/login' => 'app/views/admin/login.php',
    '/dashboard/admin/logout' => 'app/views/admin/logout.php',
    '/dashboard/admin/dashboard' => 'app/views/admin/dashboard.php',
    '/dashboard/admin/songs' => 'app/views/admin/songs.php',
    '/dashboard/admin/artists' => 'app/views/admin/artists.php',
    '/dashboard/admin/categories' => 'app/views/admin/categories.php',
    '/dashboard/admin/users' => 'app/views/admin/users.php',
    '/dashboard/admin/admins' => 'app/views/admin/admins.php',
    '/dashboard/admin/messages' => 'app/views/admin/messages.php',
    '/dashboard/admin/logs' => 'app/views/admin/logs.php',
    '/dashboard/admin/logins' => 'app/views/admin/logins.php',
    '/dashboard/admin/visits' => 'app/views/admin/visits.php',
    '/dashboard/admin/downloads' => 'app/views/admin/downloads.php',
    '/dashboard/admin/pages' => 'app/views/admin/pages.php',
    '/dashboard/admin/maintenance' => 'app/views/admin/maintenance.php',
    '/dashboard/admin/songs/add' => 'app/views/admin/add-song.php',
    '/dashboard/admin/songs/edit' => 'app/views/admin/add-song.php',
    '/dashboard/admin/artists/add' => 'app/views/admin/add-artist.php',
    '/dashboard/admin/artists/edit' => 'app/views/admin/add-artist.php',
    '/dashboard/admin/categories/add' => 'app/views/admin/add-category.php',
    '/dashboard/admin/categories/edit' => 'app/views/admin/add-category.php',
    '/dashboard/admin/users/add' => 'app/views/admin/add-user.php',
    '/dashboard/admin/users/edit' => 'app/views/admin/add-user.php',
    '/dashboard/admin/admins/add' => 'app/views/admin/add-admin.php',
    '/dashboard/admin/admins/edit' => 'app/views/admin/add-admin.php',
    '/dashboard/admin/pages/add' => 'app/views/admin/page-edit.php',
    '/dashboard/admin/pages/edit' => 'app/views/admin/page-edit.php',
];
foreach ($adminViews as $path => $file) {
    $methods($path, $render($file));
}

$router->get('/admin', static function () use ($response): void {
    $response->redirect(url('dashboard/admin'), 301);
});
$router->get('/admin/{section}', static function (string $section) use ($response): void {
    $allowed = ['login', 'logout', 'dashboard', 'songs', 'artists', 'categories', 'users', 'admins', 'messages', 'logs', 'logins', 'visits', 'downloads', 'pages', 'maintenance'];
    if (!in_array($section, $allowed, true)) {
        $response->error(404);
        return;
    }
    $response->redirect(url('dashboard/admin/' . $section), 301);
});

$api = new \\App\\controllers\\Api();
$router->get('/api/icons', [$api, 'icons']);
$router->post('/api/player', [$api, 'player']);
$router->get('/api/search', [$api, 'search']);
$router->get('/api/artists', [$api, 'artists']);
$router->post('/api/favorite', [$api, 'favorite']);
$router->post('/api/playlist', [$api, 'playlist']);
