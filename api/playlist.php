<?php
/**
 * Playlists API — the caller's own playlists, used by the "add to playlist"
 * picker on a song card.
 */
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!$user->authed()) {
    json(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

if (!csrf($_POST['csrf_token'] ?? '')) {
    json(['success' => false, 'message' => 'Your session has expired. Please log in again.'], 403);
}

$action = (string)($_POST['action'] ?? 'list');
$user_id = (int)$_SESSION['user_id'];

if ($action === 'list') {
    $rows = $music->playlists($user_id, $user->role());
    $song_id = (int)($_POST['song'] ?? 0);
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'songs' => (int)$row['songs'],
            'privacy' => $row['privacy'],
            // The picker greys out a playlist the song is already in, so the
            // normal case never reaches the "already in this playlist" error.
            'has' => $song_id > 0 ? $music->holds((int)$row['id'], $song_id) : false,
        ];
    }
    json(['success' => true, 'playlists' => $out]);
}

if ($action === 'create') {
    $result = $music->compose(
        $user_id,
        null,
        (string)($_POST['name'] ?? ''),
        trim((string)($_POST['description'] ?? '')),
        (string)($_POST['privacy'] ?? 'private'),
        $user->role()
    );
    json($result, $result['success'] ? 200 : 422);
}

if ($action === 'add') {
    $playlist_id = (int)($_POST['playlist'] ?? 0);
    $song_id = (int)($_POST['song'] ?? 0);

    if ($playlist_id <= 0 || $song_id <= 0) {
        json(['success' => false, 'message' => "That playlist or song could not be found."], 422);
    }
    // Ownership is checked here so an id from the console cannot append to
    // somebody else's playlist.
    if (!$music->owned($playlist_id, $user_id, $user->role())) {
        json(['success' => false, 'message' => "That playlist could not be found."], 404);
    }

    $result = $music->attach($playlist_id, $song_id);
    json($result, $result['success'] ? 200 : 422);
}

json(['success' => false, 'message' => "That action is not recognized."], 400);