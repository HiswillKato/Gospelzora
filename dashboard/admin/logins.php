<?php
require_once __DIR__ . '/../../app/bootstrap.php';
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

require __DIR__ . '/../../app/includes/admin/header.php';
?>
<div class="card border-0 shadow mb-4" style="border-radius:12px"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
<form method="GET" class="row g-2 align-items-end flex-grow-1">
<div class="col-md-3 col-lg-2"><label class="form-label small mb-1">Outcome</label>
<select name="outcome" class="form-select">
<option value="">All attempts</option>
<option value="ok" <?= $filters['outcome'] === 'ok' ? 'selected' : '' ?>>Successful</option>
<option value="failed" <?= $filters['outcome'] === 'failed' ? 'selected' : '' ?>>Failed</option>
</select></div>
<div class="col-md-4 col-lg-3"><label class="form-label small mb-1">Search email / IP</label>
<input type="text" name="q" class="form-control" value="<?= e($filters['q']) ?>"></div>
<div class="col-auto"><button type="submit" class="btn btn-primary">Filter</button>
<a href="<?= url('dashboard/admin/logins') ?>" class="btn btn-outline-secondary">Reset</a></div>
</form>
<div class="d-flex gap-2">
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete login records older than 30 days?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><input type="hidden" name="older_only" value="1"><button type="submit" class="btn btn-outline-secondary">Clear older than 30 days</button></form>
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete ALL login records?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><button type="submit" class="btn btn-outline-danger">Clear all</button></form>
</div>
</div></div></div>

<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<p class="text-muted small mb-2"><?= e(sprintf("%s attempts", number_format($pagination['total']))) ?></p>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Time</th><th>Email</th><th>Method</th><th>Outcome</th><th>IP</th><th>User Agent</th></tr></thead>
<tbody>
<?php if (empty($result['rows'])) { ?><tr><td colspan="6" class="text-muted">No login attempts recorded yet.</td></tr>
<?php } else { foreach ($result['rows'] as $r) { ?>
<tr>
<td class="text-nowrap small text-muted"><?= e(date('M j, Y H:i', strtotime($r['created']))) ?></td>
<td><?= e($r['email'] ?? '-') ?></td>
<td><?= e($method_labels[$r['method'] ?? ''] ?? ucfirst((string)($r['method'] ?? 'site'))) ?></td>
<td><span class="badge <?= $r['success'] ? 'bg-success' : 'bg-danger' ?>"><?= $r['success'] ? "Success" : "Failed" ?></span></td>
<td class="text-nowrap small"><?= e($r['ip'] ?? '-') ?></td>
<td class="small text-truncate" style="max-width:300px"><?= e($r['useragent'] ?? '-') ?></td>
</tr>
<?php } } ?>
</tbody></table></div>
<?php require __DIR__ . '/../../app/includes/partials/pagination.php'; ?>
</div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 