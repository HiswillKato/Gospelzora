<?php
declare(strict_types=1);

/**
 * Single public front controller.
 *
 * Apache serves real files (assets, uploads, robots.txt) directly. Every
 * application URL is dispatched here; application source remains outside the
 * document root.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$router = new \\App\\core\\Router($response);
require BASE_PATH . '/routes/web.php';
$router->dispatch($request);
