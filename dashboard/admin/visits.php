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
foreach ($cards as [$stat_label, $stat_value, $stat_icon, $stat_color]) {
    $stat_href = '';
    ?>
<div class="col-md-6 col-lg-4"><?php require __DIR__ . '/../../app/includes/partials/stat-card.php'; ?></div>
    <?php
}
?>
</div>

<div class="row g-4 mb-4">
<div class="col-lg-7"><div class="card tile h-100">
<div class="card-header"><span class="fw-semibold">Daily page views — last 14 days</span></div>
<div class="card-body">
<?php if (empty($summary['daily'])) { ?>
<p class="text-body-secondary small mb-0">No visit data yet.</p>
<?php } else {
$maxViews = max(array_column($summary['daily'], 'views'));
foreach ($summary['daily'] as $d) { ?>
<div class="d-flex align-items-center gap-2 mb-2">
<span class="text-body-secondary small" style="width:80px"><?= e(date('M j', strtotime($d['day']))) ?></span>
<div class="progress flex-grow-1" style="height:16px"><div class="progress-bar" role="progressbar" style="width:<?= $maxViews ? max(2, round($d['views'] / $maxViews * 100)) : 100 ?>%"><?= $d['views'] ?></div></div>
<span class="small text-body-secondary" style="width:70px;text-align:right"><?= e(sprintf("%s visit", $d['visitors'])) ?></span>
</div>
<?php } } ?>
</div></div></div>

<div class="col-lg-5"><div class="card tile h-100">
<div class="card-header"><span class="fw-semibold">Top pages</span></div>
<div class="card-body pb-0">
<?php if (empty($summary['topPages'])) { ?><p class="text-body-secondary small">No page views yet.</p>
<?php } else { $max = max(array_column($summary['topPages'], 'views')); ?>
<div class="list-group list-group-flush">
<?php foreach ($summary['topPages'] as $p) { ?>
<div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2 min-width-0">
<span class="small text-truncate"><?= e($p['path']) ?></span>
<span class="badge rounded-pill bg-primary bg-opacity-10 text-primary text-nowrap"><?= number_format($p['views']) ?> × <?= $max ? round($p['views'] / $max * 100) : 0 ?>%</span>
</div>
<?php } ?></div><?php } ?>
</div>
<div class="card-header border-top"><span class="fw-semibold">Top referrers</span></div>
<div class="card-body pt-2">
<?php if (empty($summary['topReferrers'])) { ?><p class="text-body-secondary small mb-0">No referrer data yet.</p>
<?php } else { ?>
<div class="list-group list-group-flush">
<?php foreach ($summary['topReferrers'] as $ref) { ?>
<div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2 min-width-0">
<span class="small text-truncate"><?= e($ref['referer']) ?></span>
<span class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary text-nowrap"><?= number_format($ref['views']) ?></span>
</div>
<?php } ?></div><?php } ?>
</div></div></div>
</div>

<div class="card tile mb-4"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
<span class="text-body-secondary small"><?= e(sprintf("%s views recorded since tracking began.", number_format($summary['total']))) ?></span>
<div class="d-flex flex-wrap gap-2">
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete visit entries older than 30 days?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><input type="hidden" name="older_only" value="1"><button type="submit" class="btn btn-ghost btn-sm">Clear older than 30 days</button></form>
<form method="POST" class="mb-0" onsubmit="return confirm('<?= e(str_replace("'", "\\'", "Delete ALL visit history?")) ?>')"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><button type="submit" class="btn btn-ghost btn-sm text-danger">Clear all</button></form>
</div></div></div>

<div class="card tile">
<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
<span class="fw-semibold">Recent page views</span>
<form method="GET" class="d-flex gap-2 align-items-center">
<select name="method" class="form-select form-select-sm" onchange="this.form.submit()">
<option value="">All methods</option>
<option value="GET" <?= $method === 'GET' ? 'selected' : '' ?>>GET</option>
<option value="POST" <?= $method === 'POST' ? 'selected' : '' ?>>POST</option>
</select>
</form>
</div>
<?php if (empty($recent['rows'])) { ?>
<div class="p-5 text-center">
<?= icon('chart-line', '', 'fa-2x text-primary d-block mb-3') ?>
<p class="text-body-secondary mb-0">No visits recorded yet.</p>
</div>
<?php } else { ?>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead><tr><th>Time</th><th>Status</th><th>Method</th><th>Path</th><th>IP</th><th class="d-none d-lg-table-cell">User agent</th><th class="d-none d-xl-table-cell">Referrer</th></tr></thead>
<tbody>
<?php foreach ($recent['rows'] as $v) { ?>
<tr>
<td class="text-nowrap small text-body-secondary"><?= e(date('M j, Y H:i', strtotime($v['created']))) ?> <span class="d-block"><?= e(ago($v['created'])) ?></span></td>
<td><span class="badge rounded-pill <?= $analytics->badge('status', (int)($v['status'] ?? 200)) ?>"><?= (int)($v['status'] ?? 200) ?></span></td>
<td><span class="badge rounded-pill <?= ($v['method'] ?? 'GET') === 'POST' ? 'bg-info text-white' : 'bg-secondary text-white' ?>"><?= e(strtoupper($v['method'] ?? 'GET')) ?></span></td>
<td class="small"><?= e($v['path']) ?></td>
<td class="text-nowrap small"><?= e($v['ip'] ?? '') ?></td>
<td class="small text-truncate d-none d-lg-table-cell keywords-cell" title="<?= e($v['useragent'] ?? '') ?>"><?= e($v['useragent'] ?? '-') ?></td>
<td class="small text-truncate d-none d-xl-table-cell keywords-cell" title="<?= e($v['referer'] ?? '') ?>"><?= e($v['referer'] ?? '-') ?></td>
</tr>
<?php } ?>
</tbody></table>
</div>
<?php } ?>
<?php if ($pagination['total_pages'] > 1) { ?><div class="p-3 border-top"><?php require __DIR__ . '/../../app/includes/partials/pagination.php'; ?></div><?php } ?>
</div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 