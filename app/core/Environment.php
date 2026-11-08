<?php
declare(strict_types=1);

namespace App\core;

/**
 * Loads simple KEY=value environment files without overriding process-level
 * environment variables. Supports quoted values and comments; multiline values
 * and variable interpolation are intentionally not supported.
 */
final class Environment
{
    private static array $loaded = [];

    public static function load(string $path): void
    {
        $path = realpath($path) ?: $path;
        if (isset(self::$loaded[$path]) || !is_file($path) || !is_readable($path)) {
            return;
        }

        self::$loaded[$path] = true;

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $name = trim(substr($line, 0, $separator));
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name)) {
                continue;
            }

            if (getenv($name) !== false || array_key_exists($name, $_ENV)) {
                continue;
            }

            $value = trim(substr($line, $separator + 1));
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];

                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                    if ($first === '"') {
                        $value = stripcslashes($value);
                    }
                } else {
                    $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
                }
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, $_ENV)) {
            return $_ENV[$name];
        }

        $value = getenv($name);
        return $value === false ? $default : $value;
    }
}
