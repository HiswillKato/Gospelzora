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
foreach ($cards as [$stat_label, $stat_value, $stat_icon, $stat_color]) {
    $stat_href = '';
    ?>
<div class="col-md-6 col-lg-3"><?php require __DIR__ . '/../../app/includes/partials/stat-card.php'; ?></div>
    <?php
}
?>
</div>

<div class="row g-4 mb-4">
<div class="col-lg-7"><div class="card tile h-100">
<div class="card-header"><span class="fw-semibold">Daily downloads — last 14 days</span></div>
<div class="card-body">
<?php if (empty($stats['daily'])) { ?>
<p class="text-body-secondary small mb-0">No download activity yet.</p>
<?php } else {
$maxDownloads = max(array_column($stats['daily'], 'downloads'));
foreach ($stats['daily'] as $d) { ?>
<div class="d-flex align-items-center gap-2 mb-2">
<span class="text-body-secondary small" style="width:80px"><?= e(date('M j', strtotime($d['day']))) ?></span>
<div class="progress flex-grow-1" style="height:16px"><div class="progress-bar bg-success" role="progressbar" style="width:<?= $maxDownloads ? max(2, round($d['downloads'] / $maxDownloads * 100)) : 100 ?>%"><?= $d['downloads'] ?></div></div>
<span class="small text-body-secondary" style="width:96px;text-align:right"><?= e(sprintf("%s failed", $d['failed'])) ?></span>
</div>
<?php } } ?>
</div></div></div>

<div class="col-lg-5"><div class="card tile h-100">
<div class="card-header"><span class="fw-semibold">Top downloaded songs</span></div>
<div class="card-body pb-0">
<?php if (empty($top)) { ?><p class="text-body-secondary small mb-0">No songs yet.</p>
<?php } else { $max = max(array_column($top, 'downloads')); ?>
<div class="list-group list-group-flush">
<?php foreach ($top as $t) { ?>
<div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2 min-width-0">
<div class="small text-truncate min-width-0">
<a href="<?= url('dashboard/admin/songs?edit=' . $t['id']) ?>"><?= e($t['title']) ?></a>
<div class="text-body-secondary"><?= e($t['artist_name'] ?? "Unknown") ?></div>
</div>
<span class="badge rounded-pill bg-success bg-opacity-10 text-success text-nowrap"><?= number_format($t['downloads']) ?> <?= $max ? '· ' . round($t['downloads'] / $max * 100) . '%' : '' ?></span>
</div>
<?php } ?></div><?php } ?>
</div></div></div>
</div>

<div class="card tile">
<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
<span class="fw-semibold">Recent download events</span>
<a href="<?= url('dashboard/admin/logs') ?>" class="btn btn-ghost btn-sm">View all logs</a>
</div>
<?php if (empty($events['rows'])) { ?>
<div class="p-5 text-center">
<?= icon('download', '', 'fa-2x text-primary d-block mb-3') ?>
<p class="text-body-secondary mb-0">No download events yet.</p>
</div>
<?php } else { ?>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead><tr><th>Time</th><th>Outcome</th><th>Details</th><th>User</th><th class="d-none d-lg-table-cell">IP</th></tr></thead>
<tbody>
<?php foreach ($events['rows'] as $r) { ?>
<tr>
<td class="text-nowrap small text-body-secondary"><?= e(date('M j, Y H:i', strtotime($r['created']))) ?> <span class="d-block"><?= e(ago($r['created'])) ?></span></td>
<td><span class="badge rounded-pill <?= $analytics->badge('action', $r['action']) ?>"><?= e($analytics->label((string)$r['action'])) ?></span></td>
<td class="small"><?= e($analytics->detail($r)) ?></td>
<td class="small"><?= $r['name'] ? e($r['name']) : '<span class="text-body-secondary">' . "Guest" . '</span>' ?></td>
<td class="text-nowrap small d-none d-lg-table-cell"><?= e($r['ip'] ?? '-') ?></td>
</tr>
<?php } ?>
</tbody></table>
</div>
<?php } ?>
<?php if ($pagination['total_pages'] > 1) { $base_url = url('dashboard/admin/downloads'); ?><div class="p-3 border-top"><?php require __DIR__ . '/../../app/includes/partials/pagination.php'; ?></div><?php } ?>
</div>
<?php require __DIR__ . '/../../app/includes/admin/footer.php'; 