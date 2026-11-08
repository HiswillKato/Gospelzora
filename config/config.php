<?php
declare(strict_types=1);

use App\core\Environment;

return [
    'app' => [
        'name' => SITE_NAME,
        'url' => BASE_URL,
        'debug' => filter_var(Environment::get('APP_DEBUG', APP_ENV !== 'production'), FILTER_VALIDATE_BOOL),
        'timezone' => APP_TIMEZONE,
    ],
    'database' => [
        'host' => DB_HOST,
        'port' => DB_PORT,
        'user' => DB_USER,
        'password' => DB_PASS,
        'name' => DB_NAME,
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host' => MAIL_HOST,
        'port' => MAIL_PORT,
        'user' => MAIL_USER,
        'password' => MAIL_PASS,
        'from' => MAIL_FROM,
        'from_name' => MAIL_FROM_NAME,
        'admin' => MAIL_ADMIN,
    ],
];
