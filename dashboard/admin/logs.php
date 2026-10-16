<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Activity Logs";
$user->guard('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'clear') {
        $analytics->clear('activity', !empty($_POST['older_only']));
        flash('success', 'Activity log cleared.');
        redirect('dashboard/admin/logs');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('danger', 'Invalid security token. Please try again.');
    redirect('dashboard/admin/logs');
}

$filters = [
    'action' => trim($_GET['action'] ?? ''),
    'q' => trim($_GET['q'] ?? ''),
    'page' => (int)($_GET['page'] ?? 1),
];
$result = $analytics->activity($filters);
$actions = $db->select("SELECT DISTINCT action FROM logs WHERE type = 'activity' ORDER BY action");
$pagination = $result['pagination'];
$filter_qs = http_build_query(array_filter(['action' => $filters['action'], 'q' => $filters['q']]));
$base_url = url('dashboard/admin/logs') . ($filter_qs !== '' ? '?' . $filter_qs : '');

require __DIR__ . '/../../app/includes/admin/header.php';
?>
<div class="card border-0 shadow mb-4" style="border-radius:12px"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
<form method="GET" class="row g-2 align-items-end flex-grow-1">
<div class="col-md-4 col-lg-3"><label class="form-label small mb-1">Action</label>
<select name="action" class="form-select">
<option value="">All actions</option>
<?php foreach ($actions as $a) { ?><option value="<?= e($a['action']) ?>" <?= $filters['action'] === $a['action'] ? 'selected' : '' ?>><?= e($analytics->label((string)$a['action'])) ?></option><?php } ?>
</select></div>
<div class="col-md-4 col-lg-3"><label class="form-label small mb-1">Search actor / details / IP</label>
<input type="text" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="e.g. john, song #5, 192.168..."></div>
<div class="col-auto"><button type="submit" class="btn btn-primary">Filter</button>
<a href="<?= url('dashboard/admin/logs') ?>" class="btn btn-outline-secondary">Reset</a></div>
</form>
<div class="d-flex gap-2">
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete activity log entries older than 30 days?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><input type="hidden" name="older_only" value="1"><button type="submit" class="btn btn-outline-secondary">Clear older than 30 days</button></form>
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete ALL activity log entries?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><button type="submit" class="btn btn-outline-danger">Clear all</button></form>
</div>
</div></div></div>

<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<p class="text-muted small mb-2"><?= e(sprintf("%s entries", number_format($pagination['total']))) ?></p>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
<tbody>
<?php if (empty($result['rows'])) { ?><tr><td colspan="5" class="text-muted">No activity recorded yet.</td></tr>
<?php } else { foreach ($result['rows'] as $r) { ?>
<tr>
<td class="text-nowrap small text-muted"><?= e(date('M j, Y H:i', strtotime($r['created']))) ?> <span class="d-block"><?= e(ago($r['created'])) ?></span></td>
<td><?= $r['name'] ? e($r['name']) : '<span class="text-muted">' . "Guest" . '</span>' ?></td>
<td><span class="badge <?= $analytics->badge('action', $r['action']) ?>"><?= e($analytics->label((string)$r['action'])) ?></span></td>
<td class="small"><?= e($analytics->detail($r)) ?></td>
<td class="text-nowrap small"><?= e($r['ip'] ?? '') ?></td>
</tr>
<?php } } ?>
</tbody></table></div>
<?php require __DIR__ . '/../../app/includes/partials/pagination.php'; ?>
</div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 