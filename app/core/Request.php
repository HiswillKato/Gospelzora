<?php
declare(strict_types=1);

namespace App\core;

final class Request
{
    public function __construct(
        private array $server = [],
        private array $query = [],
        private array $form = [],
        private array $cookies = []
    ) {
        $this->server = $server ?: $_SERVER;
        $this->query = $query ?: $_GET;
        $this->form = $form ?: $_POST;
        $this->cookies = $cookies ?: $_COOKIE;
    }

    public function method(): string
    {
        return strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string)($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function form(string $key, mixed $default = null): mixed
    {
        return $this->form[$key] ?? $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (strcasecmp($name, 'Content-Type') === 0) {
            $key = 'CONTENT_TYPE';
        }
        return $this->server[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function allForm(): array
    {
        return $this->form;
    }
}
