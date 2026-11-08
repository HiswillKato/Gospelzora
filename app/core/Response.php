<?php
declare(strict_types=1);

namespace App\core;

final class Response
{
    public function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo $content;
    }

    public function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function redirect(string $location, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $location, true, $status);
        exit;
    }

    public function error(int $status, string $message = 'Page Not Found'): void
    {
        $escaped = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->html('<h1>' . $status . '</h1><p>' . $escaped . '</p>', $status);
    }
}
