<?php
/**
 * My Playlists — the signed-in account's own playlists, with inline create/edit/delete.
 */
require_once __DIR__ . '/../app/bootstrap.php';
$user->guard('login');

$user_id = (int)$_SESSION['user_id'];
$role = $user->role();

$edit_id = (int)($_GET['edit'] ?? 0);
$editing = $edit_id > 0 ? $music->owned($edit_id, $user_id, $role) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Your session has expired. Please log in again.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $result = $music->compose(
                $user_id,
                (int)($_POST['id'] ?? 0) ?: null,
                (string)($_POST['name'] ?? ''),
                trim((string)($_POST['description'] ?? '')),
                (string)($_POST['privacy'] ?? 'private'),
                $role
            );
            flash($result['success'] ? 'success' : 'danger', $result['message']);
        } elseif ($action === 'delete') {
            $result = $music->erase((int)($_POST['id'] ?? 0), $user_id, $role);
            flash($result['success'] ? 'success' : 'danger', $result['message']);
        } else {
            flash('danger', "That action is not recognized.");
        }
    }
    redirect('playlists');
}

$playlists = $music->playlists($user_id, $role);

require_once __DIR__ . '/../app/includes/public/header.php';
?>
<?php
$page_header_title = "My Playlists";
$page_header_subtitle = "Group your favourite songs into collections you control";
$page_header_content = '';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>

<section class="py-5"><div class="container">

    <div class="row g-4">
        <!-- Create / edit form -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm sticky-lg-top" style="top: 90px;">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <?= icon($editing ? 'pen' : 'plus', 'me-2') ?><?= $editing ? "Edit Playlist" : "New Playlist" ?>
                    </h5>
                    <?php if ($editing) { ?>
                        <div class="alert alert-info py-2 small mb-3"><?= icon('circle-info', 'me-1') ?>Editing &ldquo;<?= e($editing['name']) ?>&rdquo;.</div>
                    <?php } ?>
                    <form method="POST" action="<?= e(url('playlists')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                        <input type="hidden" name="action" value="save">
                        <?php if ($editing) { ?>
                        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                        <?php } ?>
                        <div class="mb-3">
                            <label class="form-label" for="playlist-name">Name</label>
                            <input type="text" class="form-control" id="playlist-name" name="name" maxlength="160" required
                                   placeholder="Sunday morning praise" value="<?= e($editing['name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="playlist-description">Description <span class="text-muted small">(optional)</span></label>
                            <textarea class="form-control" id="playlist-description" name="description" rows="3"
                                      placeholder="What is this collection for?"><?= e($editing['description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="playlist-privacy">Visibility</label>
                            <select class="form-select" id="playlist-privacy" name="privacy">
                                <?php $sel = $editing['privacy'] ?? 'private'; ?>
                                <option value="private" <?= $sel === 'private' ? 'selected' : '' ?>>Private &mdash; only you</option>
                                <option value="public" <?= $sel === 'public' ? 'selected' : '' ?>>Public &mdash; anyone can listen</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-gold flex-fill"><?= icon('floppy-disk', 'me-1') ?><?= $editing ? "Save Changes" : "Create Playlist" ?></button>
                            <?php if ($editing) { ?>
                            <a href="<?= e(url('playlists')) ?>" class="btn btn-outline-secondary">Cancel</a>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Playlist list -->
        <div class="col-12 col-lg-8">
            <?php if ($playlists === []) { ?>
                <?php
                $empty_icon = 'list-music';
                $empty_title = "No playlists yet";
                $empty_text = "Use the form to create your first playlist, then add songs to it from any song card.";
                $empty_cta = '';
                require __DIR__ . '/../app/includes/partials/empty-state.php';
                ?>
            <?php } else { ?>
                <div class="row g-4">
                    <?php foreach ($playlists as $playlist) { ?>
                        <div class="col-12 col-sm-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <h5 class="card-title mb-0 flex-grow-1">
                                            <a class="text-decoration-none text-dark" href="<?= e(url('playlist/' . $playlist['slug'])) ?>"><?= e($playlist['name']) ?></a>
                                        </h5>
                                        <span class="badge <?= $playlist['privacy'] === 'public' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $playlist['privacy'] === 'public' ? 'Public' : 'Private' ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($playlist['description'])) { ?>
                                    <p class="card-text small text-muted"><?= e($playlist['description']) ?></p>
                                    <?php } ?>
                                    <p class="small text-muted mb-3 mt-auto">
                                        <?= icon('music', 'me-1') ?><?= (int)$playlist['songs'] ?> song<?= (int)$playlist['songs'] === 1 ? '' : 's' ?>
                                    </p>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="<?= e(url('playlist/' . $playlist['slug'])) ?>" class="btn btn-sm btn-gold"><?= icon('play', 'me-1') ?>Open</a>
                                        <a href="<?= e(url('playlists?edit=' . (int)$playlist['id'])) ?>" class="btn btn-sm btn-outline-secondary"><?= icon('pen', 'me-1') ?>Edit</a>
                                        <form method="POST" action="<?= e(url('playlists')) ?>" class="ms-auto"
                                              onsubmit="return confirm('Delete this playlist? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$playlist['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash', 'me-1') ?>Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>

</div></section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; ?>