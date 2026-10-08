<?php
/**
 * Analytics — activity log, login records and visitor stats.
 *
 * Tables: `logs` (activity), `logins` (successful + failed attempts), `visitors`
 * (page views incl. 404s, any method, deduped per ip+method+path+useragent
 * 15 min). Instance-driven: the shared `$analytics` built in app/bootstrap.php — `log(...)`,
 * `stats('visits')`.
 *
 * Retention: `visit()` lazily purges rows older than 90 days once the
 * visitors table exceeds ~50k rows.
 */

class Analytics {

    /**
     * Cleared log tables: logical name => [physical table, extra WHERE]. `clear()` takes the
     * logical name and looks the table up here, so no caller-supplied string is ever interpolated
     * into the DELETE.
     */
    private const CLEAR_TABLES = [
        'activity' => ['logs', "type = 'activity'"],
        'logins'     => ['logins', ''],
        'visitors' => ['visitors', ''],
    ];

    private DB $db;

    /**
     * Canonical registry of every activity-log action. Single source of truth for the raw
     * `logs.action` keys (persisted) and their human-friendly display labels / badge styles (admin
     * UI). Keep in sync with README.md when adding an action.
     */
    public const ACTIONS = [
        'user.register'   => ['label' => 'User Registered', 'badge' => 'bg-warning text-dark'],
        'user.verify'     => ['label' => 'Email Verified', 'badge' => 'bg-success text-white'],
        'user.verify.resent' => ['label' => 'Verification Resent', 'badge' => 'bg-info text-dark'],
        'user.login'      => ['label' => 'User Login', 'badge' => 'bg-dark text-white'],
        'user.logout'     => ['label' => 'User Logout', 'badge' => 'bg-dark text-white'],
        'user.status'     => ['label' => 'User Status Changed', 'badge' => 'bg-warning text-dark'],
        'user.create'     => ['label' => 'User Created', 'badge' => 'bg-success text-white'],
        'user.update'     => ['label' => 'User Updated', 'badge' => 'bg-info text-dark'],
        'user.delete'     => ['label' => 'User deleted.', 'badge' => 'bg-warning text-dark'],
        'admin.create'    => ['label' => 'Admin Created', 'badge' => 'bg-success text-white'],
        'admin.update'    => ['label' => 'Admin Updated', 'badge' => 'bg-info text-dark'],
        'admin.status'    => ['label' => 'Admin Status Changed', 'badge' => 'bg-warning text-dark'],
        'admin.delete'    => ['label' => 'Admin deleted.', 'badge' => 'bg-warning text-dark'],
        'artist.create'   => ['label' => 'Artist Created', 'badge' => 'bg-success text-white'],
        'artist.update'   => ['label' => 'Artist Updated', 'badge' => 'bg-success text-white'],
        'artist.delete'   => ['label' => 'Artist Deleted', 'badge' => 'bg-success text-white'],
        'admin.login'     => ['label' => 'Admin Login', 'badge' => 'bg-success text-white'],
        'admin.login.failed' => ['label' => 'Admin Login Failed', 'badge' => 'bg-danger text-white'],
        'admin.logout'    => ['label' => 'Admin Logout', 'badge' => 'bg-dark text-white'],
        'song.create'     => ['label' => 'Song Added', 'badge' => 'bg-primary text-white'],
        'song.update'     => ['label' => 'Song Updated', 'badge' => 'bg-primary text-white'],
        'song.status'     => ['label' => 'Song Status Changed', 'badge' => 'bg-primary text-white'],
        'song.request'    => ['label' => 'Song requested', 'badge' => 'bg-info text-dark'],
        'song.request.approve' => ['label' => 'Request approved', 'badge' => 'bg-success text-white'],
        'song.request.upload' => ['label' => 'Artist uploaded request', 'badge' => 'bg-info text-dark'],
        'song.delete'     => ['label' => 'Song Deleted', 'badge' => 'bg-primary text-white'],
        'settings.maintenance' => ['label' => 'Maintenance toggled', 'badge' => 'bg-primary text-white'],
        'category.create' => ['label' => 'Category Created', 'badge' => 'bg-info text-dark'],
        'category.update' => ['label' => 'Category Updated', 'badge' => 'bg-info text-dark'],
        'category.delete' => ['label' => 'Category Deleted', 'badge' => 'bg-info text-dark'],
        'category.enable' => ['label' => 'Category Enabled', 'badge' => 'bg-success text-white'],
        'category.disable' => ['label' => 'Category Disabled', 'badge' => 'bg-warning text-dark'],
        'contact.message' => ['label' => 'Contact Message', 'badge' => 'bg-success text-white'],
        'contact.read'    => ['label' => 'Message Marked Read', 'badge' => 'bg-success text-white'],
        'contact.delete'  => ['label' => 'Message Deleted', 'badge' => 'bg-success text-white'],
        'search.search'   => ['label' => 'Site Search', 'badge' => 'bg-secondary text-white'],
        'user.password.reset.request' => ['label' => 'Password Reset Requested', 'badge' => 'bg-warning text-dark'],
        'user.password.reset.done'    => ['label' => 'Password Reset', 'badge' => 'bg-success text-white'],
        'email.failed'   => ['label' => 'Email Sending Failed', 'badge' => 'bg-danger text-white'],
        'song.download'   => ['label' => 'Song Downloaded', 'badge' => 'bg-success text-white'],
        'song.download.failed' => ['label' => 'Song Download Failed', 'badge' => 'bg-danger text-white'],
        'page.save'        => ['label' => 'Page saved.', 'badge' => 'bg-primary text-white'],
        'page.delete'      => ['label' => 'Page deleted.', 'badge' => 'bg-warning text-dark'],
    ];

    /**
     * Action label for display — ACTIONS already holds the finished English text. Registered
     * actions always resolve; unknown/empty keys stay empty or return the raw action key.
     */
    public function label(string $action): string {
        return $action === '' ? '' : (self::ACTIONS[$action]['label'] ?? $action);
    }

    /**
     * Display-ready log details row. `details` is stored as finished text — callers log the final
     * sentence via log(), so there is nothing to resolve here. The legacy `args` column is
     * still read, so any row written before the migration still renders its placeholders.
     */
    public function detail(array $row): string {
        $details = (string)($row['details'] ?? '');
        if ($details === '') return '';
        $args = json_decode((string)($row['args'] ?? ''), true);
        if (is_array($args) && $args) {
            return vsprintf($details, array_values(array_map('strval', $args)));
        }
        return $details;
    }

    /** Bootstrap badge class for a logs.action key (falls back to neutral). */

    /**
     * Bootstrap badge class. $subject is 'action' (a logs.action key, from the ACTIONS registry) or
     * 'status' (an HTTP status code: 2xx success, 403/404/500 danger, other 5xx warning, 3xx info,
     * else neutral).
     */
    public function badge(string $subject, int|string $value): string {
        if ($subject === 'status') {
            $code = (int)$value;
            if ($code >= 200 && $code < 300) return 'bg-success text-white';
            if (in_array($code, [403, 404, 500], true)) return 'bg-danger text-white';
            if ($code >= 500) return 'bg-warning text-dark';
            if ($code >= 300 && $code < 400) return 'bg-info text-white';
            return 'bg-secondary text-white';
        }
        return self::ACTIONS[(string)$value]['badge'] ?? 'bg-secondary text-white';
    }

    public function __construct(DB $db) {
        $this->db = $db;
    }

    /* ---------------------------------------------------------- */
    /* Logs (activity audit trail)                                */
    /* ---------------------------------------------------------- */

    /**
     * Record an activity row. $details is the finished display text (build it with sprintf() when
     * parameterized) and is stored verbatim — nothing is resolved at read time. The legacy
     * `args` column is left NULL; detail() still reads it so pre-migration rows keep
     * their placeholders.
     */
    public function log(string $action, $details = '', ?int $actorId = null, ?string $actorName = null): void {
        $actorId = $actorId ?? ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null);
        if ($actorName === null && $actorId) {
            $actorName = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'User #' . $actorId;
        }
        $this->db->execute(
            "INSERT INTO logs (type, actor, name, action, details, args, ip)
             VALUES ('activity', ?, ?, ?, ?, NULL, ?)",
            [$actorId, $actorName, $action, (string)$details, ip()]
        );
    }

    /** Paginated activity log. Filters: action, q (actor/details/ip), page. */
    public function activity(array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['per_page'] ?? 25);
        $where = ["type = 'activity'"];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        $q = trim($filters['q'] ?? '');
        if ($q) {
            $where[] = '(name LIKE ? OR details LIKE ? OR args LIKE ? OR ip LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        return $this->paginated('logs', $where, $params, $page, $perPage);
    }

        /**
         * Empty one of the log tables. `$target` is a logical name from CLEAR_TABLES
         * (activity|logins|visitors); anything else is a no-op. `$olderThan30d` keeps the most
         * recent 30 days.
     */
    public function clear(string $target, bool $olderThan30d = false): void {
        if (!isset(self::CLEAR_TABLES[$target])) return;
        [$table, $extraWhere] = self::CLEAR_TABLES[$target];

        $where = $extraWhere !== '' ? " WHERE $extraWhere" : '';
        $older = $olderThan30d
            ? ($extraWhere !== '' ? ' AND created < NOW() - INTERVAL 30 DAY' : ' WHERE created < NOW() - INTERVAL 30 DAY')
            : '';
        $this->db->execute("DELETE FROM $table$where$older");
    }

    /* ---------------------------------------------------------- */
    /* Logins (successful and failed login attempts)               */
    /* ---------------------------------------------------------- */

    /** Record a single login attempt in `logins`. method: site | admin | blocked. */
    public function login(string $email, bool $success, string $method = 'site'): void {
        $method = in_array($method, ['site', 'admin', 'blocked'], true) ? $method : 'site';
        $this->db->execute(
            "INSERT INTO logins (email, success, method, useragent, ip)
             VALUES (?, ?, ?, ?, ?)",
            [$email, $success ? 1 : 0, $method, agent(), ip()]
        );
    }

    /** Paginated login records. Filters: outcome (all|ok|failed), q, page. */
    public function attempts(array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['per_page'] ?? 25);
        $where = [];
        $params = [];

        if (($filters['outcome'] ?? '') === 'ok') $where[] = 'success = 1';
        if (($filters['outcome'] ?? '') === 'failed') $where[] = 'success = 0';

        $q = trim($filters['q'] ?? '');
        if ($q) {
            $where[] = '(email LIKE ? OR ip LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        return $this->paginated('logins', $where, $params, $page, $perPage);
    }

    /* ---------------------------------------------------------- */
    /* Visitors (public GET page views, incl. 404s)                */
    /* ---------------------------------------------------------- */

    /**
     * Record the current request as a page view with its HTTP method and
     * final status code, deduped per ip+method+path+useragent within a
     * 15-minute window. All public page renders (any method, incl. 404s)
     * are tracked; asset/admin/api paths and installer.php are skipped.
     * Legacy rows without a stored method default to GET.
     *
     * Also enforces the retention policy: once the visitors table passes
     * ~50k rows it deletes visitors/logs/logins older than 90 days.
     */
    public function visit(): bool {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
        $path = substr($path, 0, 255);
        if (preg_match('~^/(?:assets|admin|api)/~', $path)) return false;
        if ($path === '/installer.php') return false;

        $method = strtoupper(substr($_SERVER['REQUEST_METHOD'] ?? 'GET', 0, 10));
        $status = (int)http_response_code();
        if ($status < 100 || $status > 599) $status = 200;

        $this->db->execute(
            "INSERT INTO visitors (path, method, status, ip, useragent, referer)
             SELECT ?, ?, ?, ?, ?, ? FROM DUAL
             WHERE NOT EXISTS (
                 SELECT 1 FROM visitors
                 WHERE ip = ? AND method = ? AND path = ? AND useragent = ? AND created > NOW() - INTERVAL 15 MINUTE
             )",
            [$path, $method, $status, ip(), agent(), referer(), ip(), $method, $path, agent()]
        );
        $this->prune();
        return $this->db->errno() === 0;
    }

    /** Paginated recent page views (includes method + status). Filter: method, page. */
    public function visitors(array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['per_page'] ?? 25);
        $where = [];
        $params = [];
        $method = strtoupper(trim($filters['method'] ?? ''));
        if (in_array($method, ['GET', 'POST'], true)) {
            $where[] = 'method = ?';
            $params[] = $method;
        }
        return $this->paginated('visitors', $where, $params, $page, $perPage);
    }

    /* ---------------------------------------------------------- */
    /* Downloads (success/failed outcomes from the activity log)  */
    /* ---------------------------------------------------------- */

    private function tally(string $where): int {
        return (int)$this->db->scalar("SELECT COUNT(*) FROM logs WHERE type = 'activity' AND $where");
    }

    /** Songs ranked by downloads (successful downloads only). */
    public function top(int $limit = 10): array {
        return $this->db->select(
            "SELECT s.id, s.title, s.slug, s.downloads, s.plays, u.name AS artist_name
             FROM songs s LEFT JOIN users u ON u.id = s.artist
             ORDER BY s.downloads DESC LIMIT $limit"
        );
    }

    /** Paginated download events (success + failed) from the activity log. */
    public function downloads(array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['per_page'] ?? 25);
        return $this->paginated(
            'logs',
            ["action IN ('song.download', 'song.download.failed')"],
            [],
            $page,
            $perPage
        );
    }

    /**
     * The admin stat panels, one method per kind:
     *   stats('login')      login attempt counters
     *   stats('visits')     visitor totals, status codes, daily trend, top pages/referrers
     *   stats('downloads')  download counters and daily trend
     */
    public function stats(string $kind): array {
        switch ($kind) {
            case 'login':
                return [
                    'attemptsToday' => (int)$this->db->scalar("SELECT COUNT(*) FROM logins WHERE DATE(created) = CURDATE()"),
                    'failedToday' => (int)$this->db->scalar("SELECT COUNT(*) FROM logins WHERE success = 0 AND DATE(created) = CURDATE()"),
                    'failed24h' => (int)$this->db->scalar("SELECT COUNT(*) FROM logins WHERE success = 0 AND created >= NOW() - INTERVAL 24 HOUR"),
                    'successToday' => (int)$this->db->scalar("SELECT COUNT(*) FROM logins WHERE success = 1 AND DATE(created) = CURDATE()"),
                    'uniqueIpsToday' => (int)$this->db->scalar("SELECT COUNT(DISTINCT ip) FROM logins WHERE DATE(created) = CURDATE()"),
                ];
                break;
            case 'visits':
                $v = 'visitors';
                return [
                    'total' => (int)$this->db->scalar("SELECT COUNT(*) FROM $v"),
                    'today' => (int)$this->db->scalar("SELECT COUNT(*) FROM $v WHERE DATE(created) = CURDATE()"),
                    'yesterday' => (int)$this->db->scalar("SELECT COUNT(*) FROM $v WHERE DATE(created) = CURDATE() - INTERVAL 1 DAY"),
                    'last7d' => (int)$this->db->scalar("SELECT COUNT(*) FROM $v WHERE created >= NOW() - INTERVAL 7 DAY"),
                    'last30d' => (int)$this->db->scalar("SELECT COUNT(*) FROM $v WHERE created >= NOW() - INTERVAL 30 DAY"),
                    'uniqueIps30d' => (int)$this->db->scalar("SELECT COUNT(DISTINCT ip) FROM $v WHERE created >= NOW() - INTERVAL 30 DAY"),
                    'activeIps' => (int)$this->db->scalar("SELECT COUNT(DISTINCT ip) FROM $v WHERE created >= NOW() - INTERVAL 24 HOUR"),
                    'notFound30d' => (int)$this->db->scalar("SELECT COUNT(*) FROM $v WHERE status = 404 AND created >= NOW() - INTERVAL 30 DAY"),
                    'statusCodes' => $this->db->select(
                        "SELECT status, COUNT(*) AS views FROM $v
                         GROUP BY status ORDER BY views DESC"
                    ),
                    'daily' => $this->db->select(
                        "SELECT DATE(created) AS day,
                                COUNT(*) AS views,
                                COUNT(DISTINCT ip) AS visitors
                         FROM $v WHERE created >= CURDATE() - INTERVAL 13 DAY
                         GROUP BY DATE(created) ORDER BY day"
                    ),
                    'topPages' => $this->db->select(
                        "SELECT path, COUNT(*) AS views FROM $v
                         GROUP BY path ORDER BY views DESC, MAX(created) DESC LIMIT 10"
                    ),
                    'topReferrers' => $this->db->select(
                        "SELECT referer, COUNT(*) AS views FROM $v
                         WHERE referer IS NOT NULL AND referer != ''
                         GROUP BY referer ORDER BY views DESC LIMIT 10"
                    ),
                ];
                break;
            case 'downloads':
                return [
                    'totalDownloads' => (int)$this->db->scalar("SELECT COALESCE(SUM(downloads), 0) FROM songs"),
                    'downloadsToday' => $this->tally("action = 'song.download' AND DATE(created) = CURDATE()"),
                    'failedTotal' => (int)$this->db->scalar("SELECT COUNT(*) FROM logs WHERE type = 'activity' AND action = 'song.download.failed'"),
                    'failedToday' => $this->tally("action = 'song.download.failed' AND DATE(created) = CURDATE()"),
                    'daily' => $this->db->select(
                        "SELECT DATE(created) AS day,
                                SUM(action = 'song.download') AS downloads,
                                SUM(action = 'song.download.failed') AS failed
                         FROM logs
                         WHERE action IN ('song.download', 'song.download.failed')
                           AND created >= CURDATE() - INTERVAL 13 DAY
                         GROUP BY DATE(created) ORDER BY day"
                    ),
                ];
                break;
        }
        return [];
    }

    /* ---------------------------------------------------------- */
    /* Internals                                                  */
    /* ---------------------------------------------------------- */

    /**
     * Shared paginated list query used by activity(), attempts() and visitors(): COUNT +
     * paginate() + SELECT ordered by created DESC, id DESC.
     */
    private function paginated(string $table, array $where, array $params, int $page, int $perPage): array {
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int)$this->db->scalar("SELECT COUNT(*) FROM $table $whereSql", $params);
        $pagination = paginate($total, $page, $perPage);
        $rows = $this->db->select(
            "SELECT * FROM $table $whereSql
             ORDER BY created DESC, id DESC
             LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'pagination' => $pagination];
    }

    /**
     * Retention policy: once the visitors table exceeds $threshold rows, delete
     * visitors/logs/logins older than $days. Keeps the tables from growing unboundedly without a
     * CRON job.
     */
    private function prune(int $days = 90, int $threshold = 50000): void {
        $count = (int)$this->db->scalar("SELECT COUNT(*) FROM visitors");
        if ($count >= $threshold) {
            $this->db->execute("DELETE FROM visitors WHERE created < NOW() - INTERVAL ? DAY", [$days]);
            $this->db->execute("DELETE FROM logs WHERE created < NOW() - INTERVAL ? DAY", [$days]);
            $this->db->execute("DELETE FROM logins WHERE created < NOW() - INTERVAL ? DAY", [$days]);
        }
        // events keeps 400 days (past 365 so the yearly chart stays
        // meaningful); prune once the table grows large enough to matter.
        $events = (int)$this->db->scalar("SELECT COUNT(*) FROM events");
        if ($events >= 250000) {
            $this->db->execute("DELETE FROM events WHERE created < NOW() - INTERVAL 400 DAY");
        }
    }

}
