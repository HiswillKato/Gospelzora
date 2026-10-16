<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user->guard('login');
if ($user->check('admin') || $user->role() !== 'artist') redirect('dashboard');

$dash_type = 'artist';
$dash_user = $user->current();
$dash_id = (int)$dash_user['id'];

$section = 'songs';
$page_title = "My Songs";

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        if ($isAjax) {
            json(['success' => false, 'message' => "Invalid security token. Please try again."], 403);
        }
        flash('danger', 'Invalid security token. Please try again.');
        redirect('dashboard/songs');
    }
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload_approved') {
        $id = (int)($_POST['id'] ?? 0);
        $result = $music->publish($id, $dash_id, $_FILES['audio'] ?? []);
        if ($isAjax) {
            json($result);
        }
        flash($result['success'] ? 'success' : 'danger', $result['message']);
        redirect('dashboard/songs');
    }

    if ($action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = in_array(($_POST['status'] ?? ''), ['published', 'pending', 'draft'], true) ? $_POST['status'] : '';
        if ($status !== '' && $music->find($id, $dash_id)) {
            $music->status('song', $id, $status);
        }
        redirect('dashboard/songs');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($music->find($id, $dash_id)) {
            $music->delete('song', $id, $dash_id);
            flash('success', 'Song deleted.');
        }
        redirect('dashboard/songs');
    }

    flash('danger', 'Invalid request.');
    redirect('dashboard/songs');
}

$my_songs = $music->manage($dash_id);

require __DIR__ . '/../app/includes/member/header.php';
define('DASHBOARD_PARTIAL_GUARD', true);
require __DIR__ . '/includes/songs.php';
require __DIR__ . '/../app/includes/member/footer.php';