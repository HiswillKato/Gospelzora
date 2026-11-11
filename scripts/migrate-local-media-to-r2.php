<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Usage: php scripts/migrate-local-media-to-r2.php --apply\n");
    fwrite(STDERR, "Uploads local media to R2 and keeps the original files as a rollback copy.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';
require_once BASE_PATH . '/app/services/ObjectStorage.php';

try {
    $storage = \App\Services\ObjectStorage::instance();
    $isMedia = static fn(string $key): bool => \App\Services\ObjectStorage::isMediaPath($key);
    $audio = $storage->uploadLocalTree(AUDIO_PATH, 'audio', $isMedia);
    $artwork = $storage->uploadLocalTree(ARTWORK_PATH, 'images/artwork', $isMedia);
    $artists = defined('ARTIST_IMAGE_PATH') ? $storage->uploadLocalTree(ARTIST_IMAGE_PATH, 'images/artists', $isMedia) : 0;
    // Legacy artist images in images/ root.
    $legacy = $storage->uploadLocalTree(IMAGE_PATH, 'images', static function (string $key) use ($isMedia): bool {
        return $isMedia($key) && !str_starts_with($key, 'images/artwork/') && !str_starts_with($key, 'images/artists/');
    });
    fwrite(STDOUT, sprintf(
        "Uploaded %d audio file(s), %d artwork image(s), %d artist image(s), and %d legacy image file(s). Local copies were retained.\n",
        $audio, $artwork, $artists, $legacy
    ));
} catch (\Throwable $error) {
    fwrite(STDERR, "Media migration failed: " . $error->getMessage() . "\n");
    exit(1);
}
