<?php
require_once __DIR__ . '/../../bootstrap.php';
$page_title = "Login Records";
$user->guard('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'clear') {
        $analytics->clear('logins', !empty($_POST['older_only']));
        flash('success', 'Login records cleared.');
        redirect('dashboard/admin/logins');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('danger', 'Invalid security token. Please try again.');
    redirect('dashboard/admin/logins');
}

$filters = [
    'outcome' => $_GET['outcome'] ?? '',
    'q' => trim($_GET['q'] ?? ''),
    'page' => (int)($_GET['page'] ?? 1),
];
$result = $analytics->attempts($filters);
$pagination = $result['pagination'];
$filter_qs = http_build_query(array_filter(['outcome' => $filters['outcome'], 'q' => $filters['q']]));
$base_url = url('dashboard/admin/logins') . ($filter_qs !== '' ? '?' . $filter_qs : '');

$method_labels = ['site' => 'Site', 'admin' => 'Admin', 'blocked' => 'Blocked'];

?>
<div class="card tile mb-4"><div class="card-body">
<form method="GET" class="row g-2 align-items-end">
<div class="col-12 col-md-4 col-lg-3"><label class="form-label small mb-1">Outcome</label>
<select name="outcome" class="form-select">
<option value="">All attempts</option>
<option value="ok" <?= $filters['outcome'] === 'ok' ? 'selected' : '' ?>>Successful</option>
<option value="failed" <?= $filters['outcome'] === 'failed' ? 'selected' : '' ?>>Failed</option>
</select></div>
<div class="col-12 col-md-4 col-lg-4"><label class="form-label small mb-1">Search email / IP</label>
<input type="text" name="q" class="form-control" value="<?= e($filters['q']) ?>"></div>
<div class="col-12 col-md-auto"><button type="submit" class="btn btn-primary">Filter</button>
<a href="<?= url('dashboard/admin/logins') ?>" class="btn btn-ghost">Reset</a></div>
</form>
<div class="d-flex flex-wrap gap-2 mt-3">
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete login records older than 30 days?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><input type="hidden" name="older_only" value="1"><button type="submit" class="btn btn-ghost btn-sm">Clear older than 30 days</button></form>
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete ALL login records?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><button type="submit" class="btn btn-ghost btn-sm text-danger">Clear all</button></form>
</div>
</div></div>

<div class="card tile">
<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
<span class="fw-semibold">Login attempts</span>
<span class="small text-body-secondary"><?= e(sprintf("%s attempts", number_format($pagination['total']))) ?></span>
</div>
<?php if (empty($result['rows'])) { ?>
<div class="p-5 text-center">
<?= icon('right-to-bracket', '', 'fa-2x text-primary d-block mb-3') ?>
<p class="text-body-secondary mb-0">No login attempts recorded yet.</p>
</div>
<?php } else { ?>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead><tr><th>Time</th><th>Email</th><th>Method</th><th>Outcome</th><th>IP</th><th class="d-none d-lg-table-cell">User agent</th></tr></thead>
<tbody>
<?php foreach ($result['rows'] as $r) { ?>
<tr>
<td class="text-nowrap small text-body-secondary"><?= e(date('M j, Y H:i', strtotime($r['created']))) ?> <span class="d-block"><?= e(ago($r['created'])) ?></span></td>
<td><?= e($r['email'] ?? '-') ?></td>
<td><?= e($method_labels[$r['method'] ?? ''] ?? ucfirst((string)($r['method'] ?? 'site'))) ?></td>
<td><span class="badge rounded-pill <?= $r['success'] ? 'bg-success' : 'bg-danger' ?>"><?= $r['success'] ? "Success" : "Failed" ?></span></td>
<td class="text-nowrap small"><?= e($r['ip'] ?? '-') ?></td>
<td class="small text-truncate d-none d-lg-table-cell keywords-cell"><?= e($r['useragent'] ?? '-') ?></td>
</tr>
<?php } ?>
</tbody></table>
</div>
<?php } ?>
<?php if ($pagination['total_pages'] > 1) { ?><div class="p-3 border-top"><?php require __DIR__ . '/../shared/partials/pagination.php'; ?></div><?php } ?>
</div>
