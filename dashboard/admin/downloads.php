<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$page_title = "Download Stats";
$user->guard('admin');

$stats = $analytics->stats('downloads');
$top = $analytics->top(10);
$events = $analytics->downloads(['page' => (int)($_GET['page'] ?? 1)]);
$pagination = $events['pagination'];

require __DIR__ . '/../../app/includes/admin/header.php';
?>
<div class="row g-3 mb-4">
<?php
$cards = [
    ["Total Downloads", $stats['totalDownloads'], 'download', 'success'],
    ["Downloads Today", $stats['downloadsToday'], 'arrow-down', 'primary'],
    ["Failed Downloads", $stats['failedTotal'], 'triangle-exclamation', 'danger'],
    ["Failed Today", $stats['failedToday'], 'xmark', 'danger'],
];
foreach ($cards as $c) {
    ?>
<div class="col-md-6 col-lg-3"><div class="card stat-card">
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
<h6 class="fw-bold mb-3">Daily downloads — last 14 days</h6>
<?php if (empty($stats['daily'])) { ?>
<p class="text-muted small">No download activity yet.</p>
<?php } else {
$maxDownloads = max(array_column($stats['daily'], 'downloads'));
foreach ($stats['daily'] as $d) { ?>
<div class="d-flex align-items-center gap-2 mb-2">
<span class="text-muted small" style="width:80px"><?= e(date('M j', strtotime($d['day']))) ?></span>
<div class="progress flex-grow-1" style="height:16px"><div class="progress-bar bg-success" role="progressbar" style="width:<?= $maxDownloads ? max(2, round($d['downloads'] / $maxDownloads * 100)) : 100 ?>%"><?= $d['downloads'] ?></div></div>
<span class="small text-muted" style="width:96px;text-align:right"><?= e(sprintf("%s failed", $d['failed'])) ?></span>
</div>
<?php } } ?>
</div></div></div>

<div class="col-lg-5"><div class="card border-0 shadow h-100" style="border-radius:12px"><div class="card-body">
<h6 class="fw-bold mb-3">Top downloaded songs</h6>
<?php if (empty($top)) { ?><p class="text-muted small">No songs yet.</p>
<?php } else { $max = max(array_column($top, 'downloads')); ?>
<div class="list-group list-group-flush">
<?php foreach ($top as $t) { ?>
<div class="list-group-item px-0 d-flex justify-content-between align-items-center">
<div class="small text-truncate me-2" style="max-width:70%">
<a class="text-decoration-none" href="<?= url('dashboard/admin/songs?edit=' . $t['id']) ?>"><?= e($t['title']) ?></a>
<div class="text-muted"><?= e($t['artist_name'] ?? "Unknown") ?></div>
</div>
<span class="badge bg-success bg-opacity-10 text-success text-nowrap"><?= number_format($t['downloads']) ?> <?= $max ? '· ' . round($t['downloads'] / $max * 100) . '%' : '' ?></span>
</div>
<?php } ?></div><?php } ?>
</div></div></div>
</div>

<div class="card border-0 shadow mb-4" style="border-radius:12px"><div class="card-body">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
<h6 class="fw-bold mb-0">Recent download events</h6>
<a href="<?= url('dashboard/admin/logs') ?>" class="btn btn-sm btn-outline-secondary">View all logs</a>
</div>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Time</th><th>Outcome</th><th>Details</th><th>User</th><th>IP</th></tr></thead>
<tbody>
<?php if (empty($events['rows'])) { ?><tr><td colspan="5" class="text-muted">No download events yet.</td></tr>
<?php } else { foreach ($events['rows'] as $r) { ?>
<tr>
<td class="text-nowrap small text-muted"><?= e(date('M j, Y H:i', strtotime($r['created']))) ?></td>
<td><span class="badge <?= $analytics->badge('action', $r['action']) ?>"><?= e($analytics->label((string)$r['action'])) ?></span></td>
<td class="small"><?= e($analytics->detail($r)) ?></td>
<td class="small"><?= $r['name'] ? e($r['name']) : '<span class="text-muted">' . "Guest" . '</span>' ?></td>
<td class="text-nowrap small"><?= e($r['ip'] ?? '-') ?></td>
</tr>
<?php } } ?>
</tbody></table></div>
<?php $base_url = url('dashboard/admin/downloads');
require __DIR__ . '/../../app/includes/partials/pagination.php'; ?>
</div></div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 