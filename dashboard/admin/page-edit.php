<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Pages";
$user->guard('admin');

/* ---------------------------------------------------------- */
/* POST: save                                                 */
/* ---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid security token. Please try again.');
        redirect('dashboard/admin/pages');
    }
    if (($_POST['action'] ?? '') === 'save_page') {
        $key = trim((string)($_POST['slug'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $keywords = trim((string)($_POST['keywords'] ?? ''));
        $existing = $key !== '' && $pages->page($key) !== null;

        $back = 'admin/pages/add';
        if ($existing) {
            $back = 'admin/pages/edit?edit=' . rawurlencode($key);
        }

        if (mb_strlen($title) > 200 || mb_strlen($description) > 300 || mb_strlen($keywords) > 300) {
            flash('danger', 'Page values are too long. Title max 200 characters, description and keywords max 300.');
            redirect($back);
        }
        $result = $pages->save($key, $title, $description, $keywords);
        if ($result['success']) {
            $analytics->log(
                'page.save',
                sprintf("Page %s saved (%s).", $key, $title)
            );
        }
        flash($result['success'] ? 'success' : 'danger', $result['message']);
        if ($result['success'] && !$existing) {
            $back = 'admin/pages/edit?edit=' . rawurlencode($key);
        }
        redirect($back);
    }
    flash('danger', 'Invalid request.');
    redirect('dashboard/admin/pages');
}

/* ---------------------------------------------------------- */
/* GET: add / edit                                            */
/* ---------------------------------------------------------- */
$editing = isset($_GET['edit']) && trim((string)$_GET['edit']) !== '';
$edit = null;
$edit_key = '';
if ($editing) {
    $edit_key = trim((string)$_GET['edit']);
    $edit = $pages->page($edit_key);
    if (!$edit) {
        flash('danger', 'That page could not be found.');
        redirect('dashboard/admin/pages');
    }
}
$page_title = $editing ? "Edit Page" : "Add page";

// Current field values.
$vals = ['title' => '', 'description' => '', 'keywords' => ''];
if ($edit) {
    foreach ($vals as $f => $_) {
        $vals[$f] = (string)($edit[$f] ?? '');
    }
}

require __DIR__ . '/../../app/includes/admin/header.php';
?>
<div class="row g-4">
<div class="col-lg-9">
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3">
<h5 class="fw-bold mb-0"><?= icon('file-lines', 'me-2') ?><?= e($page_title) ?></h5>
</div>

<?php if ($editing) { ?>
<p class="text-muted small"><code class="me-1"><?= e($edit_key) ?></code>Page key</p>
<?php } ?>

<form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<input type="hidden" name="action" value="save_page">
<?php if ($editing) { ?>
<input type="hidden" name="slug" value="<?= e($edit_key) ?>">
<?php } ?>

<div class="row g-3">
<?php if (!$editing) { ?>
<div class="col-md-6">
<label class="form-label">Page key</label>
<input type="text" name="slug" class="form-control" maxlength="100" placeholder="Page key · <code>about</code>" required>
</div>
<?php } ?>

<div class="col-md-4"><label class="form-label">Page title</label>
<input type="text" name="title" class="form-control" maxlength="200" <?= $editing ? '' : 'required' ?> value="<?= e($vals['title']) ?>"></div>
<div class="col-md-4"><label class="form-label">Page description</label>
<input type="text" name="description" class="form-control" maxlength="300" value="<?= e($vals['description']) ?>"></div>
<div class="col-md-4"><label class="form-label">Page keywords</label>
<input type="text" name="keywords" class="form-control" maxlength="300" value="<?= e($vals['keywords']) ?>"></div>

<div class="col-12">
<button type="submit" class="btn btn-gold">Save</button>
<a href="<?= url('dashboard/admin/pages') ?>" class="btn btn-outline-secondary">Cancel</a>
</div>
</div>
</form>
</div></div>
</div>
</div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 