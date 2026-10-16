<?php
require_once __DIR__ . '/../app/bootstrap.php';

$user->guard('login');

/* Role-based dashboard: admins get the admin dashboard, artists & users get
 * the member overview. Content is chosen by the account's `role`. */
if ($user->check('admin')) {
    $page_title = "Dashboard";
    $visits = $analytics->stats('visits');
    $login = $analytics->stats('login');
    $stats = [
        'songs' => (int)$db->scalar("SELECT COUNT(*) FROM songs"),
        'artists' => $user->total('artist'),
        'users' => $user->total('user'),
        'downloads' => (int)$db->scalar("SELECT COALESCE(SUM(downloads), 0) FROM songs"),
        'messages' => (int)$db->scalar("SELECT COUNT(*) FROM messages WHERE seen = 0"),
        'visitsToday' => $visits['today'],
        'uniqueVisitors30d' => $visits['uniqueIps30d'],
        'failedLogin24h' => $login['failed24h'],
    ];
    $recentLogs = $analytics->activity(['per_page' => 8]);
    require __DIR__ . '/../app/includes/admin/header.php';
    ?>
    <div class="row g-4">
    <?php
    $cards = [
        ["Songs", $stats['songs'], 'music', 'purple'],
        ["Artists", $stats['artists'], 'users', 'info'],
        ["Users", $stats['users'], 'user', 'warning'],
        ["Downloads", $stats['downloads'], 'download', 'secondary'],
        ["Unread Messages", $stats['messages'], 'envelope', 'danger'],
        ["Visits Today", $stats['visitsToday'], 'eye', 'primary'],
        ["Unique Visitors (30d)", $stats['uniqueVisitors30d'], 'user-group', 'success'],
        ["Failed Logins (24h)", $stats['failedLogin24h'], 'triangle-exclamation', 'danger'],
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

    <div class="card border-0 shadow mt-4" style="border-radius:12px"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="fw-bold mb-0">Recent Activity</h5>
    <a href="<?= url('dashboard/admin/logs') ?>" class="btn btn-sm btn-outline-secondary">View all logs</a>
    </div>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
    <tbody>
    <?php if (empty($recentLogs['rows'])) { ?><tr><td colspan="4" class="text-muted">No activity recorded yet.</td></tr>
    <?php } else { foreach ($recentLogs['rows'] as $r) { ?>
    <tr>
    <td class="text-nowrap small text-muted"><?= e(ago($r['created'])) ?></td>
    <td><?= $r['name'] ? e($r['name']) : '<span class="text-muted">' . "Guest" . '</span>' ?></td>
    <td><span class="badge <?= $analytics->badge('action', $r['action']) ?>"><?= e($analytics->label((string)$r['action'])) ?></span></td>
    <td class="small"><?= e($analytics->detail($r)) ?></td>
    </tr>
    <?php } } ?>
    </tbody></table></div>
    </div></div>
    <?php
    require __DIR__ . '/../app/includes/admin/footer.php';
    exit;
}

$dash_type = $user->role(); // 'artist' | 'user'
$dash_user = $user->current();
$dash_id = (int)$dash_user['id'];

$section = 'overview';
$page_title = "Dashboard";
$page_description = "Your Gospelzora dashboard - songs, favorites and song requests.";

$overview = [
    'favorites' => $user->count($dash_id, $dash_type),
    'since' => $dash_user['created'] ?? null,
];
if ($dash_type === 'artist') {
    $own = (int)$db->scalar("SELECT COUNT(*) FROM songs WHERE artist = ?", [$dash_id]);
    $published = (int)$db->scalar("SELECT COUNT(*) FROM songs WHERE artist = ? AND status = 'published'", [$dash_id]);
    $sum = $db->row("SELECT COALESCE(SUM(plays),0) AS plays, COALESCE(SUM(downloads),0) AS downloads FROM songs WHERE artist = ?", [$dash_id]);
    $overview['songs_total'] = $own;
    $overview['songs_published'] = $published;
    $overview['plays'] = (int)($sum['plays'] ?? 0);
    $overview['downloads'] = (int)($sum['downloads'] ?? 0);
} else {
    $overview['song_requests'] = (int)$db->scalar(
        "SELECT COUNT(*) FROM songs WHERE requester = ? AND status = 'requested'",
        [$dash_id]
    );
}

require __DIR__ . '/../app/includes/member/header.php';
define('DASHBOARD_PARTIAL_GUARD', true);
require __DIR__ . '/includes/overview.php';
require __DIR__ . '/../app/includes/member/footer.php';