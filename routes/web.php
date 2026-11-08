<?php
declare(strict_types=1);

use App\core\Router;
use App\Http\Controllers\HomeController;

/**
 * Register the first framework-managed web route.
 *
 * This route table is intentionally opt-in: the current .htaccess rules still
 * serve the legacy page scripts until each route has been migrated and tested.
 */
return static function (Router $router): void {
    $home = new HomeController();
    $router->get('/', [$home, 'index']);
};
