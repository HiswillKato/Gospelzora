<?php
declare(strict_types=1);

namespace App\core;

use InvalidArgumentException;

final class Router
{
    private array $routes = [];

    public function __construct(private Response $response)
    {
    }

    public function get(string $path, callable $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable $handler): void { $this->add('POST', $path, $handler); }
    public function put(string $path, callable $handler): void { $this->add('PUT', $path, $handler); }
    public function patch(string $path, callable $handler): void { $this->add('PATCH', $path, $handler); }
    public function delete(string $path, callable $handler): void { $this->add('DELETE', $path, $handler); }

    public function add(string $method, string $path, callable $handler): void
    {
        if (!str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Route paths must begin with /.');
        }
        $this->routes[strtoupper($method)][] = [$path, $handler];
    }

    public function dispatch(?Request $request = null): void
    {
        $request ??= new Request();
        $method = $request->method();
        $path = $request->path();
        $headRequest = $method === 'HEAD';
        $lookupMethod = $headRequest ? 'GET' : $method;

        foreach ($this->routes[$lookupMethod] ?? [] as [$route, $handler]) {
            $params = $this->match($route, $path);
            if ($params === null) {
                continue;
            }

            if ($headRequest) {
                ob_start();
                try {
                    $handler(...array_values($params));
                } finally {
                    ob_end_clean();
                }
            } else {
                $handler(...array_values($params));
            }
            return;
        }

        foreach ($this->routes as $registeredMethod => $routes) {
            if ($registeredMethod === $lookupMethod) {
                continue;
            }
            foreach ($routes as [$route]) {
                if ($this->match($route, $path) !== null) {
                    $this->response->error(405, 'Method Not Allowed');
                    return;
                }
            }
        }

        $this->response->error(404, 'Page Not Found');
    }

    private function match(string $route, string $path): ?array
    {
        $parts = preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/', $route, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '';
        $names = [];

        foreach ($parts as $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $match)) {
                $name = $match[1];
                if (isset($names[$name])) {
                    throw new InvalidArgumentException('Duplicate route parameter: ' . $name);
                }
                $names[$name] = true;
                $pattern .= '(?P<' . $name . '>[^/]+)';
            } else {
                $pattern .= preg_quote($part, '~');
            }
        }

        if (preg_match('~^' . $pattern . '$~D', $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($names as $name => $_) {
            $params[$name] = rawurldecode((string)$matches[$name]);
        }
        return $params;
    }
}
