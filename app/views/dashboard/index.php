<?php
require_once __DIR__ . '/../../bootstrap.php';

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
    require __DIR__ . '/../shared/admin/header.php';
    ?>
    <div class="row g-3 mb-3 mb-lg-4">
    <?php
    /* [label, value, glyph, colour, where the tile goes] — every tile is a
       shortcut, so each one names its page. */
    $cards = [
        ["Songs", $stats['songs'], 'music', 'primary', 'dashboard/admin/songs'],
        ["Artists", $stats['artists'], 'users', 'info', 'dashboard/admin/artists'],
        ["Users", $stats['users'], 'user', 'warning', 'dashboard/admin/users'],
        ["Downloads", $stats['downloads'], 'download', 'secondary', 'dashboard/admin/downloads'],
        ["Unread Messages", $stats['messages'], 'envelope', 'danger', 'dashboard/admin/messages'],
        ["Visits Today", $stats['visitsToday'], 'eye', 'primary', 'dashboard/admin/visits'],
        ["Unique Visitors (30d)", $stats['uniqueVisitors30d'], 'user-group', 'success', 'dashboard/admin/visits'],
        ["Failed Logins (24h)", $stats['failedLogin24h'], 'triangle-exclamation', 'danger', 'dashboard/admin/logins'],
    ];
    foreach ($cards as [$stat_label, $stat_value, $stat_icon, $stat_color, $stat_link]) {
        $stat_href = url($stat_link); ?>
    <div class="col-6 col-lg-3"><?php require __DIR__ . '/../shared/partials/stat-card.php'; ?></div>
    <?php } ?>
    </div>

    <div class="row g-3 g-lg-4">
    <div class="col-lg-8">
      <div class="card tile h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <span class="fw-semibold">Recent activity</span>
          <a class="small" href="<?= url('dashboard/admin/logs') ?>">See all logs <?= icon('arrow-right', '', 'ms-1') ?></a>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th class="d-none d-md-table-cell">Details</th></tr></thead>
        <tbody>
        <?php if (empty($recentLogs['rows'])) { ?><tr><td colspan="4" class="text-body-secondary">No activity recorded yet.</td></tr>
        <?php } else { foreach ($recentLogs['rows'] as $r) { ?>
        <tr>
        <td class="text-nowrap small text-body-secondary"><?= e(ago($r['created'])) ?></td>
        <td><?= $r['name'] ? e($r['name']) : '<span class="text-body-secondary">' . "Guest" . '</span>' ?></td>
        <td><span class="badge rounded-pill <?= $analytics->badge('action', $r['action']) ?>"><?= e($analytics->label((string)$r['action'])) ?></span></td>
        <td class="small d-none d-md-table-cell"><?= e($analytics->detail($r)) ?></td>
        </tr>
        <?php } } ?>
        </tbody></table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card tile h-100">
        <div class="card-header fw-semibold">Quick actions</div>
        <div class="card-body d-grid gap-2">
          <a class="btn btn-gold" href="<?= url('dashboard/admin/songs/add') ?>"><?= icon('plus') ?>Add a song</a>
          <a class="btn btn-ghost" href="<?= url('dashboard/admin/artists/add') ?>"><?= icon('user-plus') ?>Add an artist</a>
          <a class="btn btn-ghost" href="<?= url('dashboard/admin/categories/add') ?>"><?= icon('tags') ?>Add a category</a>
          <a class="btn btn-ghost" href="<?= url('dashboard/admin/messages') ?>"><?= icon('envelope') ?>Read messages</a>
          <a class="btn btn-ghost" href="<?= url('dashboard/admin/pages') ?>"><?= icon('file-lines') ?>Edit page metadata</a>
          <a class="btn btn-ghost" href="<?= url('dashboard/admin/maintenance') ?>"><?= icon('screwdriver-wrench') ?>Maintenance</a>
          <a class="btn btn-ghost" href="<?= url() ?>" target="_blank" rel="noopener"><?= icon('arrow-up-right-from-square') ?>View the live site</a>
        </div>
      </div>
    </div>
    </div>
    <?php
    require __DIR__ . '/../shared/admin/footer.php';
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

require __DIR__ . '/../shared/header.php';
define('DASHBOARD_PARTIAL_GUARD', true);
require __DIR__ . '/includes/overview.php';
require __DIR__ . '/../shared/footer.php';