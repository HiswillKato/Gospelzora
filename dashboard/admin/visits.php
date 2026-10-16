<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Visitor Stats";
$user->guard('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'clear') {
        $analytics->clear('visitors', !empty($_POST['older_only']));
        flash('success', 'Visitor records cleared.');
        redirect('dashboard/admin/visits');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    flash('danger', 'Invalid security token. Please try again.');
    redirect('dashboard/admin/visits');
}

$summary = $analytics->stats('visits');
$method = strtoupper(trim($_GET['method'] ?? ''));
$filters = ['page' => (int)($_GET['page'] ?? 1), 'method' => $method];
$recent = $analytics->visitors($filters);
$pagination = $recent['pagination'];
$base_url = url('dashboard/admin/visits') . ($method ? '?' . http_build_query(['method' => $method]) : '');

require __DIR__ . '/../../app/includes/admin/header.php';
?>
<div class="row g-3 mb-4">
<?php
$cards = [
    ["Page views — today", $summary['today'], 'fa-eye', 'primary'],
    ["Page views — yesterday", $summary['yesterday'], 'fa-eye', 'secondary'],
    ["Page views — last 7 days", $summary['last7d'], 'fa-chart-line', 'info'],
    ["Page views — last 30 days", $summary['last30d'], 'fa-clock', 'warning'],
    ["Unique visitors (30 days)", $summary['uniqueIps30d'], 'fa-user', 'success'],
    ["Active IPs (24h)", $summary['activeIps'], 'fa-fire', 'danger'],
    ["404 errors (30 days)", $summary['notFound30d'], 'fa-triangle-exclamation', 'danger'],
];
foreach ($cards as $c) {
    ?>
<div class="col-md-6 col-lg-4"><div class="card border-0 shadow stat-card">
<div class="card-body d-flex align-items-center gap-3">
<div class="stat-icon bg-<?= e($c[3]) ?> bg-opacity-10 text-<?= e($c[3]) ?>"><?= icon($c[2]) ?></div>
<div><div class="text-muted small"><?= $c[0] ?></div><div class="fs-4 fw-bold"><?= number_format($c[1]) ?></div></div>
</div></div></div>
    <?php
}
?>
</div>

<div class="row g-4 mb-4">
<div class="col-lg-7"><div class="card border-0 shadow h-100" style="border-radius:12px"><div class="card-body">
<h6 class="fw-bold mb-3">Daily page views — last 14 days</h6>
<?php if (empty($summary['daily'])) { ?>
<p class="text-muted small">No visit data yet.</p>
<?php } else {
$maxViews = max(array_column($summary['daily'], 'views'));
foreach ($summary['daily'] as $d) { ?>
<div class="d-flex align-items-center gap-2 mb-2">
<span class="text-muted small" style="width:80px"><?= e(date('M j', strtotime($d['day']))) ?></span>
<div class="progress flex-grow-1" style="height:16px"><div class="progress-bar" role="progressbar" style="width:<?= $maxViews ? max(2, round($d['views'] / $maxViews * 100)) : 100 ?>%"><?= $d['views'] ?></div></div>
<span class="small text-muted" style="width:70px;text-align:right"><?= e(sprintf("%s visit", $d['visitors'])) ?></span>
</div>
<?php } } ?>
</div></div></div>

<div class="col-lg-5"><div class="card border-0 shadow h-100" style="border-radius:12px"><div class="card-body">
<h6 class="fw-bold mb-3">Top pages</h6>
<?php if (empty($summary['topPages'])) { ?><p class="text-muted small">No page views yet.</p>
<?php } else { $max = max(array_column($summary['topPages'], 'views')); ?>
<div class="list-group list-group-flush">
<?php foreach ($summary['topPages'] as $p) { ?>
<div class="list-group-item px-0 d-flex justify-content-between align-items-center">
<span class="small text-truncate" style="max-width:75%"><?= e($p['path']) ?></span>
<span class="badge bg-primary bg-opacity-10 text-primary"><?= number_format($p['views']) ?> × <?= $max ? round($p['views'] / $max * 100) : 0 ?>%</span>
</div>
<?php } ?></div><?php } ?>

<h6 class="fw-bold mt-4 mb-2">Top referrers</h6>
<?php if (empty($summary['topReferrers'])) { ?><p class="text-muted small">No referrer data yet.</p>
<?php } else { ?>
<div class="list-group list-group-flush">
<?php foreach ($summary['topReferrers'] as $ref) { ?>
<div class="list-group-item px-0 d-flex justify-content-between align-items-center">
<span class="small text-truncate" style="max-width:75%"><?= e($ref['referer']) ?></span>
<span class="badge bg-secondary bg-opacity-10 text-secondary"><?= number_format($ref['views']) ?></span>
</div>
<?php } ?></div><?php } ?>
</div></div></div>
</div>

<div class="card border-0 shadow mb-4" style="border-radius:12px"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
<span class="text-muted small"><?= e(sprintf("%s views recorded since tracking began.", number_format($summary['total']))) ?></span>
<div class="d-flex gap-2">
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete visit entries older than 30 days?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><input type="hidden" name="older_only" value="1"><button type="submit" class="btn btn-outline-secondary">Clear older than 30 days</button></form>
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete ALL visit history?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><button type="submit" class="btn btn-outline-danger">Clear all</button></form>
</div></div></div>

<div class="card border-0 shadow" style="border-radius:12px"><div class="card-body">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
<h6 class="fw-bold mb-0">Recent page views</h6>
<form method="GET" class="d-flex gap-2 align-items-center">
<select name="method" class="form-select form-select-sm" onchange="this.form.submit()">
<option value="">All methods</option>
<option value="GET" <?= $method === 'GET' ? 'selected' : '' ?>>GET</option>
<option value="POST" <?= $method === 'POST' ? 'selected' : '' ?>>POST</option>
</select>
</form>
</div>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Time</th><th>Status</th><th>Method</th><th>Path</th><th>IP</th><th>User Agent</th><th>Referrer</th></tr></thead>
<tbody>
<?php if (empty($recent['rows'])) { ?><tr><td colspan="7" class="text-muted">No visits recorded yet.</td></tr>
<?php } else { foreach ($recent['rows'] as $v) { ?>
<tr>
<td class="text-nowrap small text-muted"><?= e(date('M j, Y H:i', strtotime($v['created']))) ?></td>
<td><span class="badge <?= $analytics->badge('status', (int)($v['status'] ?? 200)) ?>"><?= (int)($v['status'] ?? 200) ?></span></td>
<td><span class="badge <?= ($v['method'] ?? 'GET') === 'POST' ? 'bg-info text-white' : 'bg-secondary text-white' ?>"><?= e(strtoupper($v['method'] ?? 'GET')) ?></span></td>
<td class="small"><?= e($v['path']) ?></td>
<td class="text-nowrap small"><?= e($v['ip'] ?? '') ?></td>
<td class="small text-truncate" style="max-width:260px" title="<?= e($v['useragent'] ?? '') ?>"><?= e($v['useragent'] ?? '-') ?></td>
<td class="small text-truncate" style="max-width:220px" title="<?= e($v['referer'] ?? '') ?>"><?= e($v['referer'] ?? '-') ?></td>
</tr>
<?php } } ?>
</tbody></table></div>
<?php require __DIR__ . '/../../app/includes/partials/pagination.php'; ?>
</div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 