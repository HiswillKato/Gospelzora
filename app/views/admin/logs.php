<?php
require_once __DIR__ . '/../../bootstrap.php';
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

require __DIR__ . '/../shared/header.php';
?>
<div class="card tile mb-4"><div class="card-body">
<form method="GET" class="row g-2 align-items-end">
<div class="col-12 col-md-4 col-lg-3"><label class="form-label small mb-1">Action</label>
<select name="action" class="form-select">
<option value="">All actions</option>
<?php foreach ($actions as $a) { ?><option value="<?= e($a['action']) ?>" <?= $filters['action'] === $a['action'] ? 'selected' : '' ?>><?= e($analytics->label((string)$a['action'])) ?></option><?php } ?>
</select></div>
<div class="col-12 col-md-4 col-lg-3"><label class="form-label small mb-1">Search actor / details / IP</label>
<input type="text" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="e.g. john, song #5, 192.168..."></div>
<div class="col-12 col-md-auto"><button type="submit" class="btn btn-primary">Filter</button>
<a href="<?= url('dashboard/admin/logs') ?>" class="btn btn-ghost">Reset</a></div>
</form>
<div class="d-flex flex-wrap gap-2 mt-3">
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete activity log entries older than 30 days?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><input type="hidden" name="older_only" value="1"><button type="submit" class="btn btn-ghost btn-sm">Clear older than 30 days</button></form>
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete ALL activity log entries?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><button type="submit" class="btn btn-ghost btn-sm text-danger">Clear all</button></form>
</div>
</div></div>

<div class="card tile">
<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
<span class="fw-semibold">Activity</span>
<span class="small text-body-secondary"><?= e(sprintf("%s entries", number_format($pagination['total']))) ?></span>
</div>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead><tr><th>Time</th><th>User</th><th>Action</th><th class="d-none d-md-table-cell">Details</th><th class="d-none d-lg-table-cell">IP</th></tr></thead>
<tbody>
<?php if (empty($result['rows'])) { ?><tr><td colspan="5" class="text-body-secondary">No activity recorded yet.</td></tr>
<?php } else { foreach ($result['rows'] as $r) { ?>
<tr>
<td class="text-nowrap small text-body-secondary"><?= e(date('M j, Y H:i', strtotime($r['created']))) ?> <span class="d-block"><?= e(ago($r['created'])) ?></span></td>
<td><?= $r['name'] ? e($r['name']) : '<span class="text-body-secondary">' . "Guest" . '</span>' ?></td>
<td><span class="badge rounded-pill <?= $analytics->badge('action', $r['action']) ?>"><?= e($analytics->label((string)$r['action'])) ?></span></td>
<td class="small d-none d-md-table-cell"><?= e($analytics->detail($r)) ?></td>
<td class="text-nowrap small d-none d-lg-table-cell"><?= e($r['ip'] ?? '') ?></td>
</tr>
<?php } } ?>
</tbody></table>
</div>
<?php if ($pagination['total_pages'] > 1) { ?><div class="p-3 border-top"><?php require __DIR__ . '/../shared/partials/pagination.php'; ?></div><?php } ?>
</div>
<?php require __DIR__ . '/../shared/footer.php'; 