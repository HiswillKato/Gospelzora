<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Pages";
$user->guard('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf($_POST['csrf_token'] ?? '')) {
        if (($_POST['action'] ?? '') === 'delete_page') {
            $key = trim((string)($_POST['slug'] ?? ''));
            if ($key !== '' && $pages->delete($key)) {
                $analytics->log(
                    'page.delete',
                    sprintf("Page %s deleted.", $key)
                );
                flash('success', 'Page deleted.');
            }
        } else {
            flash('danger', 'Invalid request.');
        }
    } else {
        flash('danger', 'Invalid security token. Please try again.');
    }
    redirect('dashboard/admin/pages');
}

$rows = $pages->all();

require __DIR__ . '/../../app/includes/admin/header.php';
?>
<div class="row g-4">
<div class="col-12">
<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3">
<div class="d-flex align-items-center gap-2">
<h5 class="fw-bold mb-0"><?= icon('file-lines', 'me-2') ?>Pages</h5>
</div>
<div class="d-flex gap-2">
<a href="<?= e(url('dashboard/admin/pages/add')) ?>" class="btn btn-sm btn-primary text-nowrap"><?= icon('plus', 'me-1') ?>Add page</a>
</div>
</div>
<p class="text-muted small">SEO metadata for the public pages. Empty fields fall back to the site default.</p>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th style="min-width:140px">Page key</th>
<th>Page title</th>
<th>Page description</th>
<th>Page keywords</th>
<th style="width:90px"></th>
</tr>
</thead>
<tbody>
<?php if ($rows) { foreach ($rows as $r) { ?>
<tr>
<td><code class="small text-break"><?= e($r['slug']) ?></code></td>
<td class="small"><?= e((string)($r['title'] ?? '')) ?></td>
<td class="small"><?= e((string)($r['description'] ?? '')) ?></td>
<td class="small text-muted keywords-cell"><?php $kws = trim((string)($r['keywords'] ?? '')); if ($kws !== '') { ?><span title="<?= e($kws) ?>"><?= e($pages->keywords($kws)) ?></span><?php } ?></td>
<td class="text-end text-nowrap">
<a href="<?= e(url('dashboard/admin/pages/edit') . '?edit=' . rawurlencode($r['slug'])) ?>" class="btn btn-sm btn-outline-primary" title="Edit Page"><?= icon('pen') ?></a>
<form method="POST" class="d-inline" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete this page?")) ?>');"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<input type="hidden" name="action" value="delete_page">
<input type="hidden" name="slug" value="<?= e($r['slug']) ?>">
<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><?= icon('trash') ?></button>
</form>
</td>
</tr>
<?php } } else { ?>
<tr><td colspan="5" class="text-muted">No pages found.</td></tr>
<?php } ?>
</tbody>
</table>
</div>
</div></div>
</div>
</div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 