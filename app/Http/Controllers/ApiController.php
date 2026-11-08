<?php
declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * JSON endpoints extracted from the legacy api/*.php scripts.
 *
 * Existing request methods, CSRF checks, authorization rules and response
 * payloads are intentionally preserved during this migration.
 */
final class ApiController
{
    public function player(): void
    {
        global $music;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!csrf($_POST['csrf_token'] ?? '')) {
            json(['success' => false, 'message' => 'Your session has expired. Please log in again.'], 403);
        }
        $action = $_POST['action'] ?? '';
        $songId = (int)($_POST['song'] ?? 0);
        if ($action === 'play' && $songId > 0) {
            $music->increment($songId);
            json(['success' => true]);
        }
        json(['success' => false, 'message' => 'Invalid request.']);
    }

    public function search(): void
    {
        global $music;
        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            json(['songs' => [], 'artists' => []]);
        }
        json($music->search($query, true));
    }

    public function favorite(): void
    {
        global $user, $music;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json(['success' => false, 'message' => 'Method not allowed.'], 405);
        }
        if (!$user->authed()) {
            json(['success' => false, 'message' => 'Please log in to continue.'], 401);
        }
        if (!csrf($_POST['csrf_token'] ?? '')) {
            json(['success' => false, 'message' => 'Your session has expired. Please log in again.'], 403);
        }
        $songId = (int)($_POST['song'] ?? 0);
        $userId = (int)$_SESSION['user_id'];
        if ($songId <= 0) {
            json(['success' => false, 'message' => 'That song could not be found.']);
        }
        json($music->toggle($userId, $songId));
    }

    public function artists(): void
    {
        global $user;
        if (!$user->check('admin') && !$user->check('artist')) {
            json(['artists' => []], 403);
        }
        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            json(['artists' => []]);
        }
        json(['artists' => $user->search($query)]);
    }

    public function icons(): void
    {
        $query = strtolower(trim($_GET['q'] ?? ''));
        $rows = icons();
        $result = [];
        foreach ($rows as $name => $meta) {
            if ($query !== '') {
                $haystack = $name . ' ' . $meta['css'] . ' ' . str_replace(',', ' ', $meta['uses']);
                if (strpos(strtolower($haystack), $query) === false) {
                    continue;
                }
            }
            $result[$name] = [
                'fa_class' => $meta['css'],
                'uses' => usage($name) ?? [],
            ];
        }
        json(['icons' => $result, 'query' => $query]);
    }

    public function playlist(): void
    {
        global $user, $music;
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
        $userId = (int)$_SESSION['user_id'];
        if ($action === 'list') {
            $rows = $music->playlists($userId, $user->role());
            $playlists = [];
            foreach ($rows as $row) {
                $songId = (int)($_POST['song'] ?? 0);
                $playlists[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'songs' => (int)$row['songs'],
                    'privacy' => $row['privacy'],
                    'has' => $songId > 0 ? $music->holds((int)$row['id'], $songId) : false,
                ];
            }
            json(['success' => true, 'playlists' => $playlists]);
        }
        if ($action === 'create') {
            $result = $music->compose(
                $userId,
                null,
                (string)($_POST['name'] ?? ''),
                trim((string)($_POST['description'] ?? '')),
                (string)($_POST['privacy'] ?? 'private'),
                $user->role()
            );
            json($result, $result['success'] ? 200 : 422);
        }
        if ($action === 'add') {
            $playlistId = (int)($_POST['playlist'] ?? 0);
            $songId = (int)($_POST['song'] ?? 0);
            if ($playlistId <= 0 || $songId <= 0) {
                json(['success' => false, 'message' => 'That playlist or song could not be found.'], 422);
            }
            if (!$music->owned($playlistId, $userId, $user->role())) {
                json(['success' => false, 'message' => 'That playlist could not be found.'], 404);
            }
            $result = $music->attach($playlistId, $songId);
            json($result, $result['success'] ? 200 : 422);
        }
        json(['success' => false, 'message' => 'That action is not recognized.'], 400);
    }
}
