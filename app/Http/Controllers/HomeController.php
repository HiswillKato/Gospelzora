<?php
declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * Homepage controller.
 *
 * Keeps data preparation out of the template while the legacy bootstrap and
 * shared domain services are migrated incrementally.
 */
final class HomeController
{
    public function index(): void
    {
        global $music, $user;

        $page_title = sprintf('Welcome to %s', SITE_NAME);
        $page_description = 'Browse gospel music, top charts, artists and categories - free streaming and downloads.';

        $latest = $music->ranked('latest', 8);
        $popular = $music->ranked('popular', 8);

        $result = $user->artists(['limit' => 6, 'order' => 'songs']);
        $artists = $result['artists'];
        $categories = $music->categories();

        require BASE_PATH . '/app/Views/home/index.php';
    }
}
