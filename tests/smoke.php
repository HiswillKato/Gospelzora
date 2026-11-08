<?php
declare(strict_types=1);

ob_start();

require_once dirname(__DIR__) . '/app/core/Request.php';
require_once dirname(__DIR__) . '/app/core/Response.php';
require_once dirname(__DIR__) . '/app/core/Router.php';

use App\core\Request;
use App\core\Response;
use App\core\Router;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function dispatch(Router $router, string $method, string $uri): array
{
    $request = new Request(
        ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
        [],
        [],
        []
    );
    http_response_code(200);
    ob_start();
    $router->dispatch($request);
    $body = ob_get_clean();
    return [$body, http_response_code()];
}

$router = new Router(new Response());
$router->get('/songs/{slug}', static function (string $slug): void { echo $slug; });
$router->get('/artists/{artist}/songs/{song}', static function (string $artist, string $song): void { echo $artist . ':' . $song; });
$router->get('/file.json', static function (): void { echo 'json'; });
$router->post('/songs', static function (): void { echo 'created'; });

check(dispatch($router, 'GET', '/songs/amazing-grace?source=home') === ['amazing-grace', 200], 'dynamic route ignores query string');
check(dispatch($router, 'GET', '/artists/cece/songs/grace') === ['cece:grace', 200], 'multiple named route parameters');
check(dispatch($router, 'GET', '/file.json') === ['json', 200], 'literal dot in route');
check(dispatch($router, 'GET', '/fileXjson') === ['<h1>404</h1><p>Page Not Found</p>', 404], 'literal dot is not a wildcard');
check(dispatch($router, 'POST', '/songs') === ['created', 200], 'POST route');
check(dispatch($router, 'GET', '/songs') === ['<h1>405</h1><p>Method Not Allowed</p>', 405], 'method mismatch returns 405');
check(dispatch($router, 'GET', '/songs/a/b') === ['<h1>404</h1><p>Page Not Found</p>', 404], 'route parameter cannot span slash');
check(dispatch($router, 'HEAD', '/songs/amazing-grace') === ['', 200], 'HEAD response has no body');

echo 'All architecture smoke tests passed.' . PHP_EOL;
ob_end_flush();
