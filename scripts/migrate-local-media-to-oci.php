<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Usage: php scripts/migrate-local-media-to-oci.php --apply\n");
    fwrite(STDERR, "Uploads local media to OCI and keeps the original files as a rollback copy.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $storage = \\App\\Services\\ObjectStorage::instance();
    $audio = $storage->uploadLocalTree(AUDIO_PATH, 'audio');
    $images = $storage->uploadLocalTree(IMAGE_PATH, 'images');
    fwrite(STDOUT, sprintf("Uploaded %d audio file(s) and %d image file(s). Local copies were retained.\n", $audio, $images));
} catch (\\Throwable $error) {
    fwrite(STDERR, "Media migration failed: " . $error->getMessage() . "\n");
    exit(1);
}
