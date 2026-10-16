<?php
/**
 * Player API - increment play count
 */
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!csrf($_POST['csrf_token'] ?? '')) {
    json(['success' => false, 'message' => 'Your session has expired. Please log in again.'], 403);
}

$action = $_POST['action'] ?? '';
$song_id = (int)($_POST['song'] ?? 0);

if ($action === 'play' && $song_id > 0) {
    $music->increment($song_id);
    json(['success' => true]);
}

json(['success' => false, 'message' => 'Invalid request.']);