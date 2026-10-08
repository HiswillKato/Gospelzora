<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
if (!$user->check('admin') && !$user->check('artist')) {
    if ($isAjax) {
        json(['success' => false, 'message' => "Your session has expired. Please log in again."], 401);
    }
    redirect('dashboard/admin/login');
}
$artist = $user->check('artist');
$page_title = $artist ? "Songs" : (isset($_GET['edit']) ? "Edit Song" : "Add Song");
$artist_user = $user->current();
$artist_mode = $user->check('artist');
$err = '';

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $music->find((int)$_GET['edit'], $artist_mode ? (int)$artist_user['id'] : null);
    if (!$edit) $err = "Song not found.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'save') {
        $result = $music->save('song', [
            'id' => (int)($_POST['id'] ?? 0),
            'title' => trim($_POST['title'] ?? ''),
            'artist' => (int)($_POST['artist'] ?? 0),
            'category' => (int)($_POST['category'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'duration' => duration($_POST['duration'] ?? '', true),
            'status' => $_POST['status'] ?? '',
            'existing_audio' => $_POST['existing_audio'] ?? '',
            'existing_artwork' => $_POST['existing_artwork'] ?? '',
            'audio' => $_FILES['audio'] ?? null,
            'artwork' => $_FILES['artwork'] ?? null,
        ], $artist_mode ? (int)$artist_user['id'] : null);
        if ($isAjax) {
            if ($result['success']) flash('success', $result['message']);
            json($result);
        }
        if ($result['success']) {
            flash('success', $result['message']);
            redirect('dashboard/admin/songs');
        }
        $err = $result['message'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAjax) {
        json(['success' => false, 'message' => "Invalid security token."], 403);
    }
    $err = "Invalid security token. Please try again.";
}

$artists = $artist_mode ? [$artist_user] : $user->options();
$categories = $music->categories();
require __DIR__ . '/../../app/includes/admin/header.php';
?>
<?php $alert_type = 'danger'; $alert_text = $err;
require __DIR__ . '/../../app/includes/partials/alert.php'; ?>
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<h5 class="fw-semibold mb-3"><?= e($edit ? "Edit Song" : "Add Song") ?></h5>
<form method="POST" action="<?= $edit ? url('dashboard/admin/songs/edit') . '?edit=' . (int)$edit['id'] : url('dashboard/admin/songs/add') ?>" enctype="multipart/form-data" id="songForm" data-success-url="<?= url('dashboard/admin/songs') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save">
<?php if ($edit) { ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><input type="hidden" name="existing_audio" value="<?= e($edit['audio']) ?>"><input type="hidden" name="existing_artwork" value="<?= e($edit['artwork']??'') ?>"><?php } ?>
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required maxlength="200" value="<?= e($edit['title']??'') ?>"></div>
<?php if (!$artist_mode) { ?><?php
$editArtistName = '';
if ($edit) {
    foreach ($artists as $a) {
        if ((int)$a['id'] === (int)$edit['artist']) { $editArtistName = $a['name']; break; }
    }
}
?>
<div class="col-md-3"><label class="form-label">Artist</label>
<div class="artist-search" data-artist-search data-target="songArtistId">
<input type="hidden" name="artist" id="songArtistId" value="<?= (int)($edit['artist'] ?? 0) ?>">
<input type="text" class="form-control" data-artist-input autocomplete="off" placeholder="Type to search artists..." value="<?= e($editArtistName) ?>" required>
<div class="artist-search__results" data-artist-results hidden></div>
</div>
<p class="form-text mb-0 mt-1">Start typing to find and select an artist.</p>
</div><?php } else { ?><input type="hidden" name="artist" value="<?= $artist_user['id'] ?>"><div class="col-md-3"><label class="form-label">Artist</label><input class="form-control" value="<?= e($artist_user['name']) ?>" readonly></div><?php } ?>
<div class="col-12"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="6" required><?= e($edit['description']??'') ?></textarea></div>
<div class="col-md-6">
<label class="form-label">Audio File <?= $edit ? "(leave empty to keep)" : '*' ?></label>
<div class="modern-file-upload" data-file-dropzone>
    <input type="file" name="audio" id="songAudio" class="visually-hidden" accept=".mp3,.wav,.ogg,.oga,.aac,.m4a,.flac,.webm,audio/*" <?= $edit?'':'required' ?>>
    <label for="songAudio" class="modern-file-upload__surface">
        <?= icon('cloud-arrow-up') ?>
        <span class="modern-file-upload__title">Choose an audio file or drag it here</span>
        <span class="modern-file-upload__meta">MP3, WAV, OGG, AAC, M4A, FLAC or WebM · up to 100MB</span>
        <span class="modern-file-upload__filename" data-file-name><?= $edit && !empty($edit['audio']) ? e(basename($edit['audio'])) : "No file selected" ?></span>
    </label>
</div>
</div>
<div class="col-md-6">
<label class="form-label">Artwork <?= $edit ? "(leave empty to keep)" : '' ?></label>
<div class="modern-file-upload modern-file-upload--image" data-file-dropzone data-file-type="image">
    <input type="file" name="artwork" id="songArtwork" class="visually-hidden" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
    <label for="songArtwork" class="modern-file-upload__surface">
        <?= icon('image') ?>
        <span class="modern-file-upload__title">Choose artwork or drag it here</span>
        <span class="modern-file-upload__meta">JPG, PNG, WEBP or GIF · up to 5MB</span>
        <span class="modern-file-upload__filename" data-file-name><?= $edit && !empty($edit['artwork']) ? e(basename($edit['artwork'])) : "No image selected" ?></span>
    </label>
</div>
</div>
<div class="col-md-4"><label class="form-label">Category</label><select name="category" class="form-select"><option value="0">None</option><?php foreach ($categories as $c) { ?><option value="<?= $c['id'] ?>" <?= ($edit['category']??0)==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php } ?></select></div>
<div class="col-md-4"><label class="form-label">Duration</label><input type="text" name="duration" id="songDuration" class="form-control" inputmode="numeric" placeholder="0:00" pattern="[0-9]+(:[0-9]{1,2})?" value="<?= e(duration($edit['duration']??0)) ?>"><p class="form-text mb-0 mt-1">Auto-filled from the audio; editable.</p></div>
<?php if (!$artist_mode) { ?><div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="pending" <?= ($edit['status']??'')==='pending'?'selected':'' ?>>Pending</option><option value="published" <?= ($edit['status']??'')==='published'?'selected':'' ?>>Published</option><option value="draft" <?= ($edit['status']??'')==='draft'?'selected':'' ?>>Draft</option><option value="requested" <?= ($edit['status']??'')==='requested'?'selected':'' ?>>Requested</option><option value="approved" <?= ($edit['status']??'')==='approved'?'selected':'' ?>>Approved</option></select></div><?php } else { ?><div class="col-md-4"><label class="form-label">Status</label><input class="form-control" value="Pending verification" readonly></div><?php } ?>
<div class="col-12">
<div class="upload-progress d-none" data-upload-progress>
<div class="d-flex justify-content-between small text-muted mb-1"><span data-upload-status>Uploading…</span><span data-upload-percent>0%</span></div>
<div class="progress" style="height:8px" role="progressbar" aria-label="Upload progress"><div class="progress-bar bg-gold" data-upload-bar role="presentation" style="width:0%"></div></div>
</div>
<button type="submit" class="btn btn-gold" data-save-btn><?= icon('floppy-disk', 'me-1') ?>Save Song</button> <a href="<?= url('dashboard/admin/songs') ?>" class="btn btn-outline-secondary">Cancel</a>
</div>
</div></form></div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 