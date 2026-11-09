<?php
require_once __DIR__ . '/../app/bootstrap.php';
$slug = $_GET['slug'] ?? '';
if ($slug === '') { flash('danger', 'No song was specified for download.'); redirect('music'); }

$song = $music->download($slug);

if (!$song) {
    $analytics->log('song.download.failed', sprintf("Download failed (invalid song): %s", substr($slug, 0, 80)));
    flash('danger', 'That song could not be found.');
    redirect('music');
}
$id = (int)$song['id'];

$file = AUDIO_PATH . '/' . basename($song['audio']);

if (file_exists($file) && is_readable($file)) {
    // Increment download count (only after a successful serve)
    $music->increment($id, 'download');
    $analytics->log('song.download', sprintf("Song: %s", $song['title']));

    $filename = $music->filename($song);
    $fallbackFilename = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?: 'song';
    $fallbackFilename = str_replace(['"', '\\', '/'], '_', $fallbackFilename);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $fallbackFilename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: no-cache');
    readfile($file);
    exit;
}

// File missing - graceful fallback
$analytics->log('song.download.failed', sprintf("Download failed (audio missing): %s", $song['title']));
flash('warning', 'The audio file for that song is not available yet.');
redirect('song/' . $song['slug']);