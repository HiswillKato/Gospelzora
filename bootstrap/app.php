<?php
declare(strict_types=1);

/**
 * Application foundation bootstrap.
 * The legacy domain bootstrap remains in place during the incremental migration.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

$autoloadFile = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}

require_once BASE_PATH . '/app/core/Environment.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

\App\core\Environment::load(BASE_PATH . '/.env');
require_once BASE_PATH . '/app/config.php';

$config = require BASE_PATH . '/config/config.php';
error_reporting(E_ALL);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
date_default_timezone_set($config['app']['timezone']);
