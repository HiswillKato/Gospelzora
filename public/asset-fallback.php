<?php
declare(strict_types=1);

/**
 * Temporary compatibility fallback while deploy/migrate-public-assets.php is
 * moving the former root/assets tree into public/assets. New installs and new
 * uploads use public/assets directly.
 */
$relative = (string)($_GET['path'] ?? '');
if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\\\')) {
    http_response_code(404);
    exit;
}
$segments = explode('/', $relative);
if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
    http_response_code(404);
    exit;
}

$sourceRoot = realpath(dirname(__DIR__) . '/assets');
$file = $sourceRoot === false ? false : realpath($sourceRoot . DIRECTORY_SEPARATOR . $relative);
if ($sourceRoot === false || $file === false || !str_starts_with($file, $sourceRoot . DIRECTORY_SEPARATOR) || !is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
readfile($file);
