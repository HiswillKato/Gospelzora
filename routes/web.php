<?php
declare(strict_types=1);

/** URL map for the single public front controller. */
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
$route = static function (string $method, string $path, callable $handler) use ($router): void {
    $router->add($method, $path, $handler);
};
$get = static function (string $path, callable $handler) use ($route): void { $route('GET', $path, $handler); };
$getPost = static function (string $path, callable $handler) use ($route): void {
    $route('GET', $path, $handler); $route('POST', $path, $handler);
};

$get('/', static function (): void { (new \App\controllers\Home())->index(); });

$public = [
    '/music'=>'app/views/music/index.php','/artists'=>'app/views/artists/index.php',
    '/categories'=>'app/views/categories/index.php','/charts'=>'app/views/charts/index.php',
    '/search'=>'app/views/search/index.php','/about'=>'app/views/public/about.php',
    '/terms'=>'app/views/public/terms.php','/privacy'=>'app/views/public/privacy.php',
    '/copyright'=>'app/views/public/copyright.php','/favorites'=>'app/views/music/favorites.php',
];
foreach ($public as $path=>$file) $get($path,$render($file));
$getPost('/contact',$render('app/views/public/contact.php'));
$getPost('/login',$render('app/views/auth/login.php'));
$getPost('/register',$render('app/views/auth/register.php'));
$route('POST','/logout',$render('app/views/auth/logout.php'));
foreach (['/forgot'=>'forgot.php','/reset'=>'reset.php','/verify-email'=>'verify-email.php'] as $path=>$file) {
    $getPost($path,$render('app/views/auth/'.$file));
}
$getPost('/playlists',$render('app/views/playlists/index.php'));

$slugRoutes=[
    '/song/{slug}'=>['app/views/music/show.php','slug'],
    '/artist/{slug}'=>['app/views/artists/show.php','slug'],
    '/category/{slug}'=>['app/views/categories/show.php','slug'],
    '/playlist/{slug}'=>['app/views/playlists/show.php','slug'],
    '/download/{slug}'=>['app/views/music/download.php','slug'],
];
foreach($slugRoutes as $path=>[$file,$key]) $get($path,$render($file,[$key]));

$dashboard=[
    '/dashboard'=>'index.php','/dashboard/profile'=>'profile.php','/dashboard/songs'=>'songs.php',
    '/dashboard/song-requests'=>'song-requests.php','/dashboard/add-song'=>'add-song.php',
    '/dashboard/playlists'=>'playlists.php','/dashboard/favorites'=>'favorites.php',
];
foreach($dashboard as $path=>$file) {
    $handler=$render('app/views/dashboard/'.$file);
    if(in_array($path,['/dashboard/profile','/dashboard/add-song','/dashboard/playlists'],true)) $getPost($path,$handler);
    else $get($path,$handler);
}

$admin=[
    '/dashboard/admin'=>'index.php','/dashboard/admin/dashboard'=>'dashboard.php',
    '/dashboard/admin/songs'=>'songs.php','/dashboard/admin/artists'=>'artists.php',
    '/dashboard/admin/categories'=>'categories.php','/dashboard/admin/users'=>'users.php',
    '/dashboard/admin/admins'=>'admins.php','/dashboard/admin/messages'=>'messages.php',
    '/dashboard/admin/logs'=>'logs.php','/dashboard/admin/logins'=>'logins.php',
    '/dashboard/admin/visits'=>'visits.php','/dashboard/admin/downloads'=>'downloads.php',
    '/dashboard/admin/pages'=>'pages.php','/dashboard/admin/maintenance'=>'maintenance.php',
    '/dashboard/admin/songs/add'=>'add-song.php','/dashboard/admin/songs/edit'=>'add-song.php',
    '/dashboard/admin/artists/add'=>'add-artist.php','/dashboard/admin/artists/edit'=>'add-artist.php',
    '/dashboard/admin/categories/add'=>'add-category.php','/dashboard/admin/categories/edit'=>'add-category.php',
    '/dashboard/admin/users/add'=>'add-user.php','/dashboard/admin/users/edit'=>'add-user.php',
    '/dashboard/admin/admins/add'=>'add-admin.php','/dashboard/admin/admins/edit'=>'add-admin.php',
    '/dashboard/admin/pages/add'=>'page-edit.php','/dashboard/admin/pages/edit'=>'page-edit.php',
];
foreach($admin as $path=>$file) {
    $handler=$render('app/views/admin/'.$file);
    if(str_ends_with($path,'/add')||str_ends_with($path,'/edit')||in_array($path,['/dashboard/admin/pages','/dashboard/admin/maintenance'],true)) $getPost($path,$handler);
    else $get($path,$handler);
}
$get('/dashboard/admin/login',$render('app/views/admin/login.php'));
$route('POST','/dashboard/admin/logout',$render('app/views/admin/logout.php'));

$get('/admin',static function() use($response):void {$response->redirect(url('dashboard/admin'),301);});
$get('/admin/{section}',static function(string $section) use($response):void {
    $allowed=['login','logout','dashboard','songs','artists','categories','users','admins','messages','logs','logins','visits','downloads','pages','maintenance'];
    if(!in_array($section,$allowed,true)) {$response->error(404);return;}
    $response->redirect(url('dashboard/admin/'.$section),301);
});

$api=new \App\controllers\Api();
$get('/api/icons',[$api,'icons']); $route('POST','/api/player',[$api,'player']);
$get('/api/search',[$api,'search']); $get('/api/artists',[$api,'artists']);
$route('POST','/api/favorite',[$api,'favorite']); $route('POST','/api/playlist',[$api,'playlist']);
