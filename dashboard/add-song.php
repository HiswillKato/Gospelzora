<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user->guard('login');
if ($user->check('admin') || $user->role() !== 'artist') redirect('dashboard');

$dash_type = 'artist';
$dash_user = $user->current();
$dash_id = (int)$dash_user['id'];

$section = 'add-song';
$page_title = "Add Song";

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        if ($isAjax) {
            json(['success' => false, 'message' => "Invalid security token. Please try again."], 403);
        }
        flash('danger', 'Invalid security token. Please try again.');
        redirect('dashboard/add-song');
    }
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $result = $music->save('song', [
            'id' => (int)($_POST['id'] ?? 0),
            'title' => trim($_POST['title'] ?? ''),
            'artist' => $dash_id,
            'category' => (int)($_POST['category'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'duration' => duration($_POST['duration'] ?? '', true),
            'status' => $_POST['status'] ?? 'pending',
            'existing_audio' => $_POST['existing_audio'] ?? '',
            'existing_artwork' => $_POST['existing_artwork'] ?? '',
            'audio' => $_FILES['audio'] ?? null,
            'artwork' => $_FILES['artwork'] ?? null,
        ], $dash_id);
        if ($isAjax) {
            json($result);
        }
        flash($result['success'] ? 'success' : 'danger', $result['message']);
        redirect('dashboard/songs');
    }

    flash('danger', 'Invalid request.');
    redirect('dashboard/add-song');
}

$edit_song = null;
if (isset($_GET['id'])) {
    $edit_song = $music->find((int)$_GET['id'], $dash_id);
    if (!$edit_song) {
        flash('danger', 'That song could not be found.');
        redirect('dashboard/add-song');
    }
    $page_title = "Edit Song";
}
$categories = $music->categories();

require __DIR__ . '/../app/includes/member/header.php';

$songs_active_tab = 'add-song';
?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<h5 class="fw-semibold mb-4"><?= e($edit_song ? "Edit Song" : "Add Song") ?></h5>
<form method="POST" action="<?= url('dashboard/add-song') ?>" enctype="multipart/form-data" id="songForm" data-success-url="<?= url('dashboard/songs') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save">
<?php if ($edit_song) { ?><input type="hidden" name="id" value="<?= (int)$edit_song['id'] ?>"><input type="hidden" name="existing_audio" value="<?= e($edit_song['audio']) ?>"><input type="hidden" name="existing_artwork" value="<?= e($edit_song['artwork'] ?? '') ?>"><?php } ?>
<input type="hidden" name="status" value="pending">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required maxlength="200" value="<?= e($edit_song['title'] ?? '') ?>"></div>
<div class="col-md-6"><label class="form-label">Artist</label><input class="form-control" value="<?= e($dash_user['name']) ?>" readonly></div>
<div class="col-12"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="6" required><?= e($edit_song['description'] ?? '') ?></textarea></div>
<div class="col-md-6">
<label class="form-label">Audio File <?= $edit_song ? "(leave empty to keep)" : '*' ?></label>
<div class="modern-file-upload" data-file-dropzone>
<input type="file" name="audio" id="songAudio" class="visually-hidden" accept=".mp3,.wav,.ogg,.oga,.aac,.m4a,.flac,.webm,audio/*" <?= $edit_song ? '' : 'required' ?>>
<label for="songAudio" class="modern-file-upload__surface">
<?= icon('cloud-arrow-up') ?>
<span class="modern-file-upload__title">Choose an audio file or drag it here</span>
<span class="modern-file-upload__meta">MP3, WAV, OGG, AAC, M4A, FLAC or WebM · up to 100MB</span>
<span class="modern-file-upload__filename" data-file-name><?= $edit_song && !empty($edit_song['audio']) ? e(basename($edit_song['audio'])) : "No file selected" ?></span>
</label>
</div>
</div>
<div class="col-md-6">
<label class="form-label">Artwork <?= $edit_song ? "(leave empty to keep)" : '' ?></label>
<div class="modern-file-upload modern-file-upload--image" data-file-dropzone data-file-type="image">
<input type="file" name="artwork" id="songArtwork" class="visually-hidden" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
<label for="songArtwork" class="modern-file-upload__surface">
<?= icon('image') ?>
<span class="modern-file-upload__title">Choose artwork or drag it here</span>
<span class="modern-file-upload__meta">JPG, PNG, WEBP or GIF · up to 5MB</span>
<span class="modern-file-upload__filename" data-file-name><?= $edit_song && !empty($edit_song['artwork']) ? e(basename($edit_song['artwork'])) : "No image selected" ?></span>
</label>
</div>
</div>
<div class="col-md-6"><label class="form-label">Category</label><select name="category" class="form-select"><option value="0">None</option><?php foreach ($categories as $c) { ?><option value="<?= (int)$c['id'] ?>" <?= (int)($edit_song['category'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php } ?></select></div>
<div class="col-md-3"><label class="form-label">Duration</label><input type="text" name="duration" id="songDuration" class="form-control" inputmode="numeric" placeholder="0:00" pattern="[0-9]+(:[0-9]{1,2})?" value="<?= e(duration($edit_song['duration'] ?? 0)) ?>"><p class="form-text mb-0 mt-1">Auto-filled from the audio; editable.</p></div>
<div class="col-md-3"><label class="form-label">Status</label><input class="form-control" value="Pending verification" readonly></div>
<div class="col-12">
<div class="upload-progress d-none" data-upload-progress>
<div class="d-flex justify-content-between small text-muted mb-1"><span data-upload-status>Uploading…</span><span data-upload-percent>0%</span></div>
<div class="progress" style="height:8px" role="progressbar" aria-label="Upload progress"><div class="progress-bar bg-gold" data-upload-bar role="presentation" style="width:0%"></div></div>
</div>
<button type="submit" class="btn btn-gold" data-save-btn><?= icon('floppy-disk', 'me-1') ?>Save Song</button>
<a href="<?= url('dashboard/songs') ?>" class="btn btn-outline-secondary">Cancel</a>
</div>
</div></form>
</div></div>
<?php require __DIR__ . '/../app/includes/member/footer.php'; 