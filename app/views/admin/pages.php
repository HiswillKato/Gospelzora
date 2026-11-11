<?php
require_once __DIR__ . '/../../bootstrap.php';
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

?>
<div class="card tile">
<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
<span class="fw-semibold"><?= icon('file-lines', '', 'me-2') ?>Pages</span>
<a href="<?= e(url('dashboard/admin/pages/add')) ?>" class="btn btn-gold btn-sm text-nowrap"><?= icon('plus') ?>Add page</a>
</div>
<p class="small text-body-secondary px-3 pt-3 mb-0">SEO metadata for the public pages. Empty fields fall back to the site default.</p>

<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead>
<tr>
<th class="d-none d-md-table-cell" style="min-width:140px">Page key</th>
<th>Page title</th>
<th class="d-none d-lg-table-cell">Page description</th>
<th class="d-none d-xl-table-cell">Page keywords</th>
<th style="width:90px"></th>
</tr>
</thead>
<tbody>
<?php if ($rows) { foreach ($rows as $r) { ?>
<tr>
<td class="d-none d-md-table-cell"><code class="small text-break"><?= e($r['slug']) ?></code></td>
<td class="small"><?= e((string)($r['title'] ?? '')) ?><span class="d-block text-body-secondary mono d-md-none"><?= e($r['slug']) ?></span></td>
<td class="small d-none d-lg-table-cell"><?= e((string)($r['description'] ?? '')) ?></td>
<td class="small text-body-secondary keywords-cell d-none d-xl-table-cell"><?php $kws = trim((string)($r['keywords'] ?? '')); if ($kws !== '') { ?><span title="<?= e($kws) ?>"><?= e($pages->keywords($kws)) ?></span><?php } ?></td>
<td class="text-end text-nowrap">
<div class="d-inline-flex gap-1">
<a href="<?= e(url('dashboard/admin/pages/edit') . '?edit=' . rawurlencode($r['slug'])) ?>" class="btn btn-ghost btn-sm rounded-circle" title="Edit Page"><?= icon('pen') ?></a>
<form method="POST" class="d-inline" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete this page?")) ?>');"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<input type="hidden" name="action" value="delete_page">
<input type="hidden" name="slug" value="<?= e($r['slug']) ?>">
<button type="submit" class="btn btn-ghost btn-sm rounded-circle text-danger" title="Delete"><?= icon('trash') ?></button>
</form>
</div>
</td>
</tr>
<?php } } else { ?>
<tr><td colspan="5" class="text-body-secondary">No pages found.</td></tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
