<?php
declare(strict_types=1);

namespace App\core;

use RuntimeException;
use Throwable;

final class ExceptionHandler
{
    public function handle(Throwable $exception): void
    {
        error_log('Uncaught exception: ' . $exception);

        $isDatabaseFailure = $exception instanceof \mysqli_sql_exception
            || ($exception instanceof RuntimeException
                && str_contains(strtolower($exception->getMessage()), 'technical difficulties'));

        $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $isApiRequest = str_starts_with($requestPath, '/api/');

        if ($isDatabaseFailure) {
            if ($isApiRequest) {
                if (!headers_sent()) {
                    http_response_code(503);
                }
                json(['success' => false, 'message' => 'Technical difficulties'], 503);
                return;
            }

            if (!headers_sent()) {
                http_response_code(503);
            }
            require ROOT_PATH . '/app/views/shared/errors/db-error.php';
            exit;
        }

        if (!headers_sent()) {
            http_response_code(500);
        }
        exit;
    }
}
