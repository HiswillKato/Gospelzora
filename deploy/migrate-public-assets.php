<?php
declare(strict_types=1);

/**
 * One-time deployment migration for legacy assets.
 *
 * Run from the project root:
 *   php deploy/migrate-public-assets.php
 *   php deploy/migrate-public-assets.php --move
 *
 * The default copies and verifies files, leaving the source untouched. --move
 * removes the old assets directory only after every file has copied successfully.
 */
$root = dirname(__DIR__);
$source = $root . '/assets';
$destination = $root . '/public/assets';
$removeSource = in_array('--move', $argv ?? [], true);

if (!is_dir($source)) {
    fwrite(STDOUT, "No legacy assets directory found; nothing to migrate.\n");
    exit(0);
}
if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
    fwrite(STDERR, "Cannot create public/assets.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
$copied = 0;
$failed = [];

foreach ($iterator as $item) {
    $relative = substr($item->getPathname(), strlen($source) + 1);
    $target = $destination . DIRECTORY_SEPARATOR . $relative;
    if ($item->isLink()) {
        $failed[] = $relative . ' (symbolic links are not migrated)';
        continue;
    }
    if ($item->isDir()) {
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
            $failed[] = $relative . ' (directory creation failed)';
        }
        continue;
    }
    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        $failed[] = $relative . ' (parent directory creation failed)';
        continue;
    }
    $temporary = $target . '.migrating';
    if (!copy($item->getPathname(), $temporary) || filesize($temporary) !== $item->getSize()) {
        @unlink($temporary);
        $failed[] = $relative . ' (copy or size verification failed)';
        continue;
    }
    if (!rename($temporary, $target)) {
        @unlink($temporary);
        $failed[] = $relative . ' (atomic replacement failed)';
        continue;
    }
    $copied++;
}

if ($failed !== []) {
    fwrite(STDERR, "Migration incomplete; original assets were retained:\n - " . implode("\n - ", $failed) . "\n");
    exit(1);
}

if ($removeSource) {
    $remove = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($remove as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($source);
}

fwrite(STDOUT, "Migrated {$copied} files into public/assets." . ($removeSource ? " Removed legacy assets directory." : " Original assets retained.") . "\n");
