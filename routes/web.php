<?php
declare(strict_types=1);

use App\core\Router;
use App\Controllers\Home;

return static function (Router $router): void {
    $home = new Home();
    $router->get('/', [$home, 'index']);
};
