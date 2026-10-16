<?php
/**
 * Favorites API
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

$song_id = (int)($_POST['song'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if ($song_id <= 0) {
    json(['success' => false, 'message' => 'That song could not be found.']);
}

$result = $music->toggle($user_id, $song_id);
json($result);