<?php
declare(strict_types=1);

namespace App\controllers;

final class Home
{
    public function index(): void
    {
        global $config, $db, $analytics, $mail, $settings, $user, $music, $pages, $request, $response;

        $page_title = sprintf('Welcome to %s', SITE_NAME);
        $page_description = 'Browse gospel music, top charts, artists and categories - free streaming and downloads.';

        $latest = $music->ranked('latest', 8);
        $popular = $music->ranked('popular', 8);
        $result = $user->artists(['limit' => 6, 'order' => 'songs']);
        $artists = $result['artists'];
        $categories = $music->categories();

        $view_file = BASE_PATH . '/app/views/public/home.php';
        $shell = 'public';
        require BASE_PATH . '/app/views/shared/layout.php';
    }
}
