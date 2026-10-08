<?php
/**
 * User — accounts, auth, profile and admin management. One `users` table; the
 * `role` column ('user'|'artist'|'admin'|'promoter') is the account type.
 *
 * The DATABASE ROW is the source of truth for the signed-in account: `current()`
 * reads it once per request and every guard derives the role from that row, so a
 * disable/role/email/password change takes effect at once rather than lingering in
 * the session. `$_SESSION` only caches a copy; `current()` re-syncs it and rotates
 * the session id when the row changed underneath.
 *
 * There are no static members: app/bootstrap.php builds ONE User per request and
 * every page, shell and partial works through that instance, so the account,
 * session and guard helpers read alike (`$user->authed()`).
 */

class User {

    /** Roles a session may hold ('promoter' is normalized to 'user'). */
    public const SESSION_ROLES = ['user', 'artist', 'admin'];

    /** Per-role delete metadata: log action, log noun, and the column holding
     *  the human-readable label used in that log line. */
    private const DELETE_META = [
        'user'   => ['action' => 'user.delete',   'noun' => 'User',   'label' => 'email'],
        'artist' => ['action' => 'artist.delete', 'noun' => 'Artist', 'label' => 'name'],
        'admin'  => ['action' => 'admin.delete',  'noun' => 'Admin',  'label' => 'email'],
    ];

    /* Rate limits for the public auth flows. Deliberately generous: they exist
     * to stop scripted abuse without locking a real person out. */
    private const COOLDOWN_WINDOW_MINUTES = 15;
    private const RESEND_MAX_ATTEMPTS = 3;
    private const RESEND_SESSION_SECONDS = 60;
    private const RESET_MAX_ATTEMPTS = 3;
    private const RESET_IP_MAX_ATTEMPTS = 30;
    private const REGISTER_IP_MAX_ATTEMPTS = 20;
    private const REGISTER_WINDOW_MINUTES = 60;
    private const REGISTER_SESSION_SECONDS = 30;

    private DB $db;
    private Analytics $analytics;
    private Settings $settings;
    private Mail $mail;

    /** The signed-in account for this request (null = nobody signed in). */
    private ?array $currentUser = null;

    /** Whether the current() lookup already ran for this request. */
    private bool $currentResolved = false;

    /** Set when the account row could not be read (guards fall back to the session). */
    private bool $dbUnavailable = false;

    public function __construct(DB $db, Analytics $analytics, Settings $settings, Mail $mail) {
        $this->db = $db;
        $this->analytics = $analytics;
        $this->settings = $settings;
        $this->mail = $mail;
    }

    /* ---------------------------------------------------------- */
    /* Account-type helpers                                        */
    /* ---------------------------------------------------------- */

    /**
     * The canonical account role for this request ('user' | 'artist' | 'admin'), taken from the
     * database row behind current(). The session copy is only consulted while the database
     * cannot be reached.
     */
    public function role(): string {
        $user = $this->current();
        if (is_array($user)) {
            return normalize((string)($user['role'] ?? 'user'));
        }
        return $this->dbUnavailable ? (cached() ?: 'user') : 'user';
    }

    /* ---------------------------------------------------------- */
    /* Session & guard helpers                                     */
    /* ---------------------------------------------------------- */

    public function authed(): bool {
        if ($this->current() !== null) return true;
        return $this->dbUnavailable && cached() !== '';
    }

    /**
     * The signed-in account for this request, read from `users` (once per
     * request). The row — not the session — decides who this is: a missing or
     * non-active row revokes the session identity outright, and any change made
     * to the row while the session was open (role, status, email, password)
     * re-syncs the session and rotates the session id.
     *
     * An unreachable database never revokes anything (a blip must not sign
     * anyone out): the lookup degrades to $dbUnavailable and the guards fall
     * back to the role cached in the session for that request.
     */
    public function current(bool $refresh = false): ?array {
        if ($this->currentResolved && !$refresh) {
            return $this->currentUser;
        }
        $this->currentResolved = true;
        $this->currentUser = null;
        $this->dbUnavailable = false;

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $rebuild = false;
        if ($userId <= 0 && isset($_SESSION['admin_id'])) {
            $userId = (int)$_SESSION['admin_id'];
            $rebuild = $userId > 0;
        }
        if ($userId <= 0) return null;

        try {
            $db = $this->db;
            $user = $db->row(
                "SELECT id, name, email, role, status, slug, bio, image, country, verified, created, updated
                 FROM users WHERE id = ? LIMIT 1",
                [$userId]
            );
        } catch (Throwable $e) {
            $this->dbUnavailable = true;
            return $this->currentUser;
        }

        if (!$user || (string)($user['status'] ?? '') !== 'active') {
            $this->revoke();
            return null;
        }

        $this->sync($user, $rebuild);
        return $this->currentUser = $user;
    }

    /**
     * Does the signed-in account hold $role? Accepts 'admin' or 'artist'. Admins are the only role
     * exempt from the verified gate, so an artist is an unverified account that cannot log
     * in at all and cannot be mistaken for an admin here.
     */
    public function check(string $role): bool {
        if ($role === 'admin') {
            return $this->role() === 'admin';
        }
        if ($role === 'artist') {
            return $this->authed() && $this->role() === 'artist';
        }
        return false;
    }

    /**
     * Stop the request unless the visitor may continue. $level is:
     *   'login'  any signed-in account (the only level that explains itself first)
     *   'admin'  admins only
     *   'staff'  admins or artists
     * An unknown level is treated as the strictest one, 'admin'.
     */
    public function guard(string $level = 'login'): void {
        if ($level === 'login') {
            if (!$this->authed()) {
                flash('warning', 'Please log in to continue.');
                redirect('login');
            }
            return;
        }
        if ($level === 'staff') {
            if (!$this->check('admin') && !$this->check('artist')) {
                redirect('login');
            }
            return;
        }
        if (!$this->check('admin')) {
            redirect('login');
        }
    }

    /* ---------------------------------------------------------- */
    /* Session identity                                            */
    /* ---------------------------------------------------------- */

    /**
     * Copy the database row into the session. The session id is rotated when anything about the
     * account changed while the session was open, so a stale session (demoted, disabled,
     * re-emailed, re-passworded) cannot keep using its old id.
     */
    private function sync(array $user, bool $rebuild = false): void {
        $role = normalize((string)($user['role'] ?? 'user'));
        $id = (int)($user['id'] ?? 0);
        $name = (string)($user['name'] ?? '');
        $email = (string)($user['email'] ?? '');
        $stamp = (string)($user['updated'] ?? '');

        $changed = $rebuild
            || cached() !== $role
            || (isset($_SESSION['user_email']) && (string)$_SESSION['user_email'] !== $email)
            || (isset($_SESSION['account_updated_at']) && (string)$_SESSION['account_updated_at'] !== $stamp);

        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        $_SESSION['account_type'] = $role;
        $_SESSION['account_updated_at'] = $stamp;
        if ($role === 'admin') {
            $_SESSION['admin_id'] = $id;
            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_role'] = 'admin';
        } else {
            unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email'], $_SESSION['admin_role']);
        }

        if ($changed) {
            $this->rotate();
        }
    }

    /** Drop every trace of the signed-in identity and rotate the session id. */
    public function revoke(): void {
        unset(
            $_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'],
            $_SESSION['user_role'], $_SESSION['account_type'], $_SESSION['account_updated_at'],
            $_SESSION['admin_id'], $_SESSION['admin_name'],
            $_SESSION['admin_email'], $_SESSION['admin_role']
        );
        $this->currentUser = null;
        $this->currentResolved = true;
        $this->rotate();
    }

    /**
     * Re-read the account row into the session after a self-service change (password/email/profile
     * edit) so the current request works with the freshly written data instead of the pre-change
     * copy.
     */
    public function refresh(): void {
        $this->currentUser = null;
        $this->currentResolved = false;
        $this->current(true);
    }

    private function rotate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /* ---------------------------------------------------------- */
    /* Account lifecycle                                          */
    /* ---------------------------------------------------------- */

    public function register(array $data): array {
        $ipSubject = 'ip:' . ip();
        if ($this->blocked('register', $ipSubject, self::REGISTER_IP_MAX_ATTEMPTS, self::REGISTER_SESSION_SECONDS)) {
            return ['success' => false, 'message' => "Too many attempts. Please wait a few minutes and try again."];
        }

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');
        $role = in_array($data['role'] ?? '', ['artist', 'user'], true) ? $data['role'] : 'user';
        $country = trim($data['country'] ?? '');

        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            return ['success' => false, 'message' => sprintf("Password must be at least %s characters long.", MIN_PASSWORD_LENGTH)];
        }
        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => "Name must be 100 characters or fewer."];
        }
        if (mb_strlen($email) > 150) {
            return ['success' => false, 'message' => "Email address must be 150 characters or fewer."];
        }
        if (!valid($country, 'country')) {
            return ['success' => false, 'message' => "Please select a valid country."];
        }
        if ($this->taken($email)) {
            return ['success' => false, 'message' => "That email address is already registered."];
        }

        $this->hit('register', $ipSubject, self::REGISTER_WINDOW_MINUTES, self::REGISTER_SESSION_SECONDS);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $slug = null;
        if ($role === 'artist') {
            $slug = slug($name, 'users');
        }

        $userId = $this->db->insert(
            "INSERT INTO users (name, email, password, role, country, slug, status, created)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())",
            [$name, $email, $hash, $role, $country, $slug]
        );
        if ($userId <= 0 || $this->db->errno()) {
            return ['success' => false, 'message' => "Something went wrong. Please try again."];
        }

        $this->analytics->log('user.register', sprintf("User registered as %s: %s", $role, $email), (int)$userId, $name);

        $mailed = false;
        try {
            $token = $this->issue((int)$userId, 'email_verify', (int)MAIL_VERIFY_EXPIRY_HOURS, $role);
            $mailed = $this->mail->send('verify', [['email' => $email, 'name' => $name], $token]);
        } catch (Throwable $e) {
            $mailed = false;
        }
        if (!$mailed) {
            $this->analytics->log('email.failed', sprintf("Verification email could not be sent right now for: %s", $email), (int)$userId, $name);
        }

        $message = $mailed
            ? sprintf("Your account is ready. We sent a verification link to %s.", $email)
            : sprintf("Your account was created, but the verification email could not be sent. Please contact support to verify %s.", $email);

        return ['success' => true, 'message' => $message, 'user' => $userId];
    }

    /** Public login — matches any account in `users` (admins included). */
    public function login(string $email, string $password): array {
        $emailKey = strtolower(trim($email));
        if ($this->locked($emailKey)) {
            $this->attempt($emailKey, false, 'blocked');
            return ['success' => false, 'message' => "Too many attempts. Please wait a few minutes and try again."];
        }

        $account = $this->db->row("SELECT * FROM users WHERE email = ? LIMIT 1", [$emailKey]);
        $type = normalize((string)($account['role'] ?? 'user'));

        $fail = function () use ($emailKey) {
            $this->strike($emailKey);
            $this->attempt($emailKey, false, 'site');
            return ['success' => false, 'message' => "Incorrect email or password."];
        };

        if (!$account || $account['status'] !== 'active') return $fail();
        if (!password_verify($password, $account['password'])) return $fail();
        // Maintenance mode: the site is locked except for administrators.
        if ($type !== 'admin' && $this->settings->maintenance()) {
            $this->clear('email', $emailKey);
            $this->attempt($emailKey, false, 'blocked');
            return ['success' => false, 'message' => "The site is under maintenance. Please try again later."];
        }
        // Admins may sign in before email verification (same as the old admin login).
        if ($type !== 'admin' && empty($account['verified'])) {
            $this->clear('email', $emailKey);
            $this->attempt($emailKey, false, 'blocked');
            return [
                'success' => false,
                'unverified' => true,
                'message' => "Please verify your email address first. Check your inbox for the verification link.",
            ];
        }

        $this->clear('email', $emailKey);
        $this->clear('ip');
        $this->attempt($emailKey, true, 'site');
        $this->establish($account, $type);
        $this->analytics->log('user.login', sprintf("User login: %s (%s)", $type, $emailKey), (int)$account['id'], $account['name']);
        return ['success' => true, 'message' => sprintf("Welcome back, %s!", $account['name'])];
    }

    public function logout(): void {
        if (cached() === 'admin') {
            $name = $_SESSION['admin_name'] ?? 'Admin';
            $this->analytics->log('admin.logout', sprintf("Admin logged out: %s", $name), (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0), $name);
        } elseif (isset($_SESSION['user_id'])) {
            $name = $_SESSION['user_name'] ?? 'User';
            $this->analytics->log('user.logout', sprintf("User logged out: %s", $name), (int)$_SESSION['user_id'], $name);
        }
        $this->revoke();
    }

    /* ---------------------------------------------------------- */
    /* Profile                                                    */
    /* ---------------------------------------------------------- */

    /**
     * Update profile (name / email / country). `$role` is ignored (single table).
     *
     * Changing the email address is a security action, so it additionally
     * requires the CURRENT PASSWORD, clears `verified` (verification
     * is never skipped), throws away every outstanding auth token, ends the
     * session and mails a fresh verification link to the new address. The
     * password is checked before anything account-revealing, so a session
     * holder who cannot produce it learns nothing about other accounts.
     * The result then carries `reverify` so the caller can send the user to
     * login. Admins stay exempt from verification, as everywhere else.
     */
    public function update(int $id, array $data, ?string $role = null): array {
        $account = $this->db->row(
            "SELECT id, name, email, password, role, status FROM users WHERE id = ? LIMIT 1",
            [$id]
        );
        if (!$account) {
            return ['success' => false, 'message' => "That account could not be found."];
        }

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $country = trim($data['country'] ?? '');

        if (!valid($country, 'country')) {
            return ['success' => false, 'message' => "Please select a valid country."];
        }
        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => "Name must be 100 characters or fewer."];
        }
        if (mb_strlen($email) > 150) {
            return ['success' => false, 'message' => "Email address must be 150 characters or fewer."];
        }

        $type = normalize((string)($account['role'] ?? 'user'));
        $emailChanged = strtolower($email) !== strtolower((string)$account['email']);
        $reverify = false;

        if ($emailChanged) {
            $currentPassword = (string)($data['current_password'] ?? '');
            if ($currentPassword === '' || !password_verify($currentPassword, (string)$account['password'])) {
                return ['success' => false, 'message' => "Your current password is incorrect."];
            }
            $reverify = $type !== 'admin';
        }

        if ($this->taken($email, $id)) {
            return ['success' => false, 'message' => "That email address is already registered."];
        }

        $sql = "UPDATE users SET name = ?, email = ?, country = ?, updated = NOW()";
        $params = [$name, $email, $country];
        if ($reverify) {
            $sql .= ", verified = NULL";
        }
        $sql .= " WHERE id = ?";
        $params[] = $id;

        if (!$this->db->execute($sql, $params) || $this->db->errno()) {
            return ['success' => false, 'message' => "Something went wrong. Please try again."];
        }

        $this->analytics->log('user.update', sprintf("User updated: %s", $email), $id, $name);

        if (!$emailChanged) {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $this->refresh();
            return ['success' => true, 'message' => "Profile updated."];
        }

        $this->burn($id);

        if (!$reverify) {
            $this->refresh();
            return ['success' => true, 'message' => "Profile updated."];
        }

        $mailed = false;
        try {
            $token = $this->issue($id, 'email_verify', (int)MAIL_VERIFY_EXPIRY_HOURS, $type);
            $mailed = $this->mail->send('verify', [['email' => $email, 'name' => $name], $token]);
        } catch (Throwable $e) {
            $mailed = false;
        }
        if ($mailed) {
            $this->analytics->log('user.verify.resent', sprintf("Verification email resent: %s", $email), $id, $name);
        } else {
            $this->analytics->log('email.failed', sprintf("Verification email could not be sent right now for: %s", $email), $id, $name);
        }

        $this->revoke();

        return [
            'success' => true,
            'reverify' => true,
            'mailed' => $mailed,
            'message' => $mailed ? 'verification_sent' : 'email_send_failed',
        ];
    }

    /**
     * Change the account password from a signed-in session. Requires the current password,
     * invalidates every outstanding auth token and drops the cached session copy so the account is
     * re-read on the next request. The "password changed" notice is best-effort, like every other
     * mail here.
     */
    public function password(int $id, string $current, string $new, ?string $role = null): array {
        if (strlen($new) < MIN_PASSWORD_LENGTH) {
            return ['success' => false, 'message' => sprintf("Password must be at least %s characters long.", MIN_PASSWORD_LENGTH)];
        }

        $row = $this->db->row("SELECT id, name, email, password FROM users WHERE id = ? LIMIT 1", [$id]);
        if (!$row || !password_verify($current, (string)$row['password'])) {
            return ['success' => false, 'message' => "Your current password is incorrect."];
        }

        if (!$this->db->execute(
            "UPDATE users SET password = ?, updated = NOW() WHERE id = ?",
            [password_hash($new, PASSWORD_DEFAULT), $id]
        ) || $this->db->errno()) {
            return ['success' => false, 'message' => "Something went wrong. Please try again."];
        }

        $this->burn($id);
        $this->refresh();

        $this->analytics->log('user.update', sprintf("User updated: %s", (string)$row['email']), $id, (string)$row['name']);
        try {
            $this->mail->send('changed', [['id' => $id, 'name' => (string)$row['name'], 'email' => (string)$row['email']]]);
        } catch (Throwable $e) {
        }

        return ['success' => true, 'message' => "Password changed."];
    }

    public function find(int $id, string $role = 'user'): ?array {
        return $this->db->row(
            "SELECT * FROM users WHERE id = ? AND role = ?",
            [$id, $role]
        );
    }

    /* ---------------------------------------------------------- */
    /* Public artist discovery (users with role='artist')          */
    /* ---------------------------------------------------------- */

    /**
     * Public artist list — active artists who have at least one published song. Pass `limit` for
     * the homepage list (order 'songs' sorts by song count), otherwise supports pagination. Artists
     * without published music stay hidden from the public surfaces.
     */
    public function artists(array $options = []): array {
        $q = trim($options['q'] ?? '');
        $page = max(1, (int)($options['page'] ?? 1));
        $limit = isset($options['limit']) ? (int)$options['limit'] : null;
        $order = $options['order'] ?? 'name';

        $where = "a.status = 'active' AND a.role = 'artist'
                  AND EXISTS (SELECT 1 FROM songs s WHERE s.artist = a.id AND s.status = 'published')";
        $params = [];
        if ($q) {
            $where .= " AND a.name LIKE ?";
            $params[] = "%$q%";
        }

        $total = (int)$this->db->scalar("SELECT COUNT(*) FROM users a WHERE $where", $params);
        $orderSql = $order === 'songs' ? 'song_count DESC' : 'a.name ASC';
        $selectRoster =
            "SELECT a.*, (SELECT COUNT(*) FROM songs WHERE artist = a.id AND status = 'published') as song_count
             FROM users a WHERE $where";

        if ($limit !== null) {
            $rows = $this->db->select("$selectRoster ORDER BY $orderSql LIMIT $limit", $params);
            return ['artists' => $rows, 'total' => $total];
        }

        $pagination = paginate($total, $page, $options['per_page'] ?? ITEMS_PER_PAGE);
        $rows = $this->db->select(
            "$selectRoster ORDER BY $orderSql LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
            $params
        );
        return ['artists' => $rows, 'total' => $total, 'pagination' => $pagination];
    }

    /** Count accounts of one role. */
    public function total(string $role): int {
        return (int)$this->db->scalar("SELECT COUNT(*) FROM users WHERE role = ?", [normalize($role)]);
    }

    /**
     * Public artist lookup by slug or numeric id — active artists with at least one published song
     * only.
     */
    public function artist(mixed $value): ?array {
        ['column' => $column, 'param' => $param] = resolve($value);
        return $this->db->row(
            "SELECT * FROM users WHERE $column = ? AND role = 'artist' AND status = 'active'
             AND EXISTS (SELECT 1 FROM songs s WHERE s.artist = users.id AND s.status = 'published') LIMIT 1",
            [$param]
        );
    }

    /** id/name pairs for <select> dropdowns (active artists). */
    public function options(): array {
        return $this->db->select("SELECT id, name FROM users WHERE role = 'artist' AND status = 'active' ORDER BY name");
    }

    /** Live-search artists (id/name) for the admin song-form artist picker. */
    public function search(string $q, int $limit = 10): array {
        $like = '%' . $q . '%';
        return $this->db->select(
            "SELECT id, name FROM users WHERE role = 'artist' AND status = 'active' AND name LIKE ? ORDER BY name LIMIT ?",
            [$like, $limit]
        );
    }

    /* ---------------------------------------------------------- */
    /* Admin: listener management (users role='user')             */
    /* ---------------------------------------------------------- */

    /** Admin account list for one role (listeners or admins). */
    /**
     * Every account in one role, newest first. $withCount is the admin artist list's variant: it
     * selects the full row and adds a song_count, and sorts by name instead of signup date.
     */
    public function roster(string $role, bool $withCount = false): array {
        $role = normalize($role);
        if ($withCount) {
            return $this->db->select(
                "SELECT a.*, (SELECT COUNT(*) FROM songs WHERE artist = a.id) as song_count
                 FROM users a WHERE a.role = ? ORDER BY a.name",
                [$role]
            );
        }
        return $this->db->select(
            "SELECT id, name, email, status, created, login FROM users
             WHERE role = ? ORDER BY created DESC",
            [$role]
        );
    }

    /** Flip an account between 'active' and 'disabled' within one role. */
    public function toggle(int $id, string $role = 'user'): void {
        $role = normalize($role);
        $row = $this->db->row("SELECT name, email, status FROM users WHERE id = ? AND role = ?", [$id, $role]);
        if (!$row) return;
        $newStatus = $row['status'] === 'active' ? 'disabled' : 'active';
        $this->db->execute("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $id]);
        $this->analytics->log(
            $role . '.status',
            sprintf("%s status changed: %s -> %s", ucfirst($role), $row['email'], $newStatus)
        );
    }

    /**
     * Delete an account of one role, together with its favorites and any outstanding auth tokens,
     * and log it. $meta maps the role to the column holding the human-readable label and to the log
     * action.
     */
    public function delete(string $role, int $id): void {
        $meta = self::DELETE_META[$role] ?? null;
        if (!$meta) return;
        $row = $this->db->row("SELECT {$meta['label']} FROM users WHERE id = ? AND role = ?", [$id, $role]);
        $this->db->execute("DELETE FROM users WHERE id = ? AND role = ?", [$id, $role]);
        $this->db->execute("DELETE FROM favorites WHERE user = ? AND type = ?", [$id, $role]);
        $this->db->execute("DELETE FROM tokens WHERE user = ? AND type = ?", [$id, $role]);
        if ($row) {
            $this->analytics->log($meta['action'], sprintf("%s deleted: %s", $meta['noun'], $row[$meta['label']]));
        }
    }

    /* ---------------------------------------------------------- */
    /* Admin: artist management (users role='artist')             */
    /* ---------------------------------------------------------- */

    /** All artists (any status) with song counts, for the admin panel. */
    /** Artists with their song count, for the admin artist table. */

    /* ---------------------------------------------------------- */
    /* Admin: staff management (users role='admin')                */
    /* ---------------------------------------------------------- */

    /**
     * Shared create/update for a listener or admin account (single users table). Changing the
     * address or the password burns the account's outstanding auth tokens; a live session of that
     * account is re-synced by $user->current() on its next request.
     */

    /* ---------------------------------------------------------- */
    /* Account security: email verification & password reset      */
    /* ---------------------------------------------------------- */

    /**
     * Create a one-time auth token for a purpose ('email_verify' |
     * 'password_reset') and return the RAW token (only its SHA-256 hash is
     * stored, so leaked DB rows are useless). Expiry in hours.
     * $role ('user'|'artist'|'admin') is stored so legacy token rows stay
     * unambiguous; every account lives in `users` though.
     *
     * Any earlier unused token of the same purpose is burned first, so only the
     * newest link ever works. Throws when the token cannot be stored — callers
     * treat a token failure as "no mail was sent".
     */
    public function issue(int $userId, string $purpose, int $expiryHours, string $role = 'user'): string {
        $role = normalize($role);
        $this->burn($userId, $purpose);

        $token = bin2hex(random_bytes(32));
        $this->db->execute(
            "INSERT INTO tokens (type, user, purpose, hash, expires)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))",
            [$role, $userId, $purpose, hash('sha256', $token), $expiryHours]
        );
        if ($this->db->errno()) {
            throw new RuntimeException('Could not store the authentication token.');
        }
        return $token;
    }

    /**
     * Invalidate outstanding (unused) auth tokens of an account — all of them, or just one purpose.
     * Used when a password or email address changes, so a link mailed to the old address (or minted
     * before a password change) can never be used.
     */
    public function burn(int $userId, ?string $purpose = null): void {
        if ($purpose === null) {
            $this->db->execute(
                "UPDATE tokens SET used = NOW() WHERE user = ? AND used IS NULL",
                [$userId]
            );
            return;
        }
        $this->db->execute(
            "UPDATE tokens SET used = NOW() WHERE user = ? AND purpose = ? AND used IS NULL",
            [$userId, $purpose]
        );
    }

    /**
     * Validate + atomically consume a one-time token. Returns the owning
     * account (id, email, name, type) or null when the token is unknown,
     * already used, expired or the database refused the claim.
     *
     * The claim is a single conditional UPDATE (`used IS NULL AND
     * expires > NOW()`) and only the request that actually changed a row
     * gets the token back, so two clicks on the same link (or two parallel
     * requests) can never both consume it.
     */
    public function consume(string $purpose, string $token): ?array {
        $token = trim($token);
        if ($token === '') return null;
        $hash = hash('sha256', $token);

        try {
            $claimed = $this->db->affected(
                "UPDATE tokens SET used = NOW()
                 WHERE purpose = ? AND hash = ? AND used IS NULL AND expires > NOW()",
                [$purpose, $hash]
            );
            if ($claimed !== 1) return null;

            $row = $this->db->row(
                "SELECT type, user FROM tokens WHERE purpose = ? AND hash = ? LIMIT 1",
                [$purpose, $hash]
            );
            if (!$row) return null;

            $account = $this->db->row("SELECT id, email, name FROM users WHERE id = ? LIMIT 1", [(int)$row['user']]);
        } catch (Throwable $e) {
            return null;
        }
        if (!$account) return null;

        return [
            'id' => (int)$account['id'],
            'email' => $account['email'],
            'name' => $account['name'],
            'type' => $row['type'],
        ];
    }

    /** Mark an account's email as verified. Returns whether the write landed. */
    public function verify(int $userId, string $type = 'user'): bool {
        return $this->db->execute(
            "UPDATE users SET verified = COALESCE(verified, NOW()) WHERE id = ?",
            [$userId]
        );
    }

    /**
     * (Re)send the verification email for an account. Returns result array.
     *
     * Blocked while maintenance mode is on (the login page stays reachable but
     * must not become a mail relay) and rate-limited per address, in the session
     * and in `settings`, so the same generic throttle message is returned before
     * the account is even looked up.
     */
    public function resend(string $email): array {
        $emailKey = strtolower(trim($email));
        if ($emailKey === '') {
            return ['success' => false, 'message' => "No account was found for that email address."];
        }
        if ($this->settings->maintenance()) {
            return ['success' => false, 'message' => "The site is under maintenance. Please try again later."];
        }
        if ($this->blocked('resend', $emailKey, self::RESEND_MAX_ATTEMPTS, self::RESEND_SESSION_SECONDS)) {
            return ['success' => false, 'message' => "Too many attempts. Please wait a few minutes and try again."];
        }
        $this->hit('resend', $emailKey, self::COOLDOWN_WINDOW_MINUTES, self::RESEND_SESSION_SECONDS);

        $user = $this->db->row(
            "SELECT id, name, email, verified, role FROM users
             WHERE email = ? AND status = 'active' AND role != 'admin' LIMIT 1",
            [$emailKey]
        );
        if (!$user) {
            return ['success' => false, 'message' => "No account was found for that email address."];
        }
        if (!empty($user['verified'])) {
            return ['success' => false, 'message' => "That email address is already verified."];
        }

        try {
            $token = $this->issue((int)$user['id'], 'email_verify', (int)MAIL_VERIFY_EXPIRY_HOURS, (string)($user['role'] ?? 'user'));
            $mailed = $this->mail->send('verify', [$user, $token]);
        } catch (Throwable $e) {
            $mailed = false;
        }
        $this->analytics->log(
            $mailed ? 'user.verify.resent' : 'email.failed',
            $mailed ? sprintf("Verification email resent: %s", $email) : sprintf("Could not resend verification email to %s", $email),
            (int)$user['id'],
            $user['name']
        );
        return [
            'success' => $mailed,
            'message' => $mailed ? 'verification_sent' : 'email_send_failed',
        ];
    }

    /**
     * Request a password reset for an active account. Always returns without revealing whether the
     * account exists (no user enumeration); the boolean result refers to whether a reset email was
     * queued. Requests are rate limited per address and per client IP, so the endpoint cannot be
     * used to spray mail or to probe for valid addresses.
     */
    public function recover(string $email): bool {
        $emailKey = strtolower(trim($email));
        if ($emailKey === '') return false;

        $ipSubject = 'ip:' . ip();
        if ($this->blocked('reset', $emailKey, self::RESET_MAX_ATTEMPTS)
            || $this->blocked('reset', $ipSubject, self::RESET_IP_MAX_ATTEMPTS)) {
            return false;
        }
        $this->hit('reset', $emailKey, self::COOLDOWN_WINDOW_MINUTES);
        $this->hit('reset', $ipSubject, self::COOLDOWN_WINDOW_MINUTES);

        $user = $this->db->row(
            "SELECT id, name, email, role FROM users WHERE email = ? AND status = 'active' LIMIT 1",
            [$emailKey]
        );
        if (!$user) return false;

        try {
            $token = $this->issue((int)$user['id'], 'password_reset', (int)MAIL_RESET_EXPIRY_HOURS, (string)($user['role'] ?? 'user'));
            $mailed = $this->mail->send('reset', [$user, $token]);
        } catch (Throwable $e) {
            $mailed = false;
        }
        $this->analytics->log('user.password.reset.request', sprintf("Reset requested: %s", $email), (int)$user['id'], $user['name']);
        if (!$mailed) {
            $this->analytics->log('email.failed', sprintf("Reset email not sent to %s", $email), (int)$user['id'], $user['name']);
        }
        return $mailed;
    }

    /** Set a new password via a consumed reset token. Returns result array. */
    public function reset(string $token, string $newPassword): array {
        if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
            return ['success' => false, 'message' => sprintf("Password must be at least %s characters long.", MIN_PASSWORD_LENGTH)];
        }

        $row = $this->consume('password_reset', $token);
        if (!$row) {
            return ['success' => false, 'message' => "This password reset link is invalid or has expired."];
        }

        if (!$this->db->execute(
            "UPDATE users SET password = ?, verified = COALESCE(verified, NOW()),
             updated = NOW() WHERE id = ?",
            [password_hash($newPassword, PASSWORD_DEFAULT), (int)$row['id']]
        ) || $this->db->errno()) {
            return ['success' => false, 'message' => "Something went wrong. Please try again."];
        }
        $this->burn((int)$row['id']);

        $this->analytics->log(
            'user.password.reset.done',
            sprintf("Password reset via link: %s", $row['email']),
            (int)$row['id'],
            $row['name']
        );
        try {
            $this->mail->send('changed', [$row]);
        } catch (Throwable $e) {
            // Notification is best-effort; the reset itself already succeeded.
        }
        return ['success' => true, 'message' => "Your password has been reset. You can sign in now."];
    }

    /* ---------------------------------------------------------- */
    /* Internals                                                  */
    /* ---------------------------------------------------------- */

    public function count(int $userId, ?string $type = null): int {
        $type = $type ?? $this->role();
        return (int)$this->db->scalar(
            "SELECT COUNT(*) FROM favorites WHERE user = ? AND type = ?",
            [$userId, $type]
        );
    }

    /**
     * Is `$email` already in use by any account in `users`? Rows with `$exceptId` are ignored (for
     * edits).
     */
    private function taken(string $email, int $exceptId = 0): bool {
        return (bool)$this->db->row(
            "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1",
            [$email, $exceptId]
        );
    }

    /** Delete an account row (of exactly this role) plus its favorites and auth tokens, then log it. */
    private function locked(string $email = ''): bool {
        $state = $this->state('email', $email);
        $fails = (int)($state['fails'] ?? 0);
        $lockUntil = (int)($state['lock_until'] ?? 0);

        if ($fails >= LOGIN_MAX_ATTEMPTS && time() < $lockUntil) {
            return true;
        }

        if ($lockUntil && time() >= $lockUntil) {
            $this->clear('email', $email);
        }

        // Per-IP throttle: blocks spray/brute-force across many accounts from
        // one source address (email-keyed locks alone let a single IP try
        // unlimited distinct emails). Uses a higher threshold so users behind
        // a shared NAT are not locked out by a neighbour's mistakes.
        if ($email !== '') {
            $ip = $this->state('ip');
            $ipFails = (int)($ip['fails'] ?? 0);
            $ipLockUntil = (int)($ip['lock_until'] ?? 0);

            if ($ipFails >= LOGIN_MAX_ATTEMPTS_IP && time() < $ipLockUntil) {
                return true;
            }
            if ($ipLockUntil && time() >= $ipLockUntil) {
                $this->clear('ip');
            }
        }

        return false;
    }

    private function strike(string $email = ''): void {
        $state = $this->state('email', $email);
        $state['fails'] = (int)($state['fails'] ?? 0) + 1;
        if ($state['fails'] >= LOGIN_MAX_ATTEMPTS) {
            $state['lock_until'] = time() + LOGIN_LOCK_MINUTES * 60;
        }
        $this->store($email, $state);

        if ($email !== '') {
            $ip = $this->state('ip');
            $ip['fails'] = (int)($ip['fails'] ?? 0) + 1;
            if ($ip['fails'] >= LOGIN_MAX_ATTEMPTS_IP) {
                $ip['lock_until'] = time() + LOGIN_LOCK_MINUTES * 60;
            }
            $this->settings->set($this->key('ip'), json_encode($ip, JSON_THROW_ON_ERROR));
            $this->purge();
        }
    }

    /**
     * Clear a login throttle (called on a successful login). $scope is 'email' (which also drops
     * the session counters) or 'ip' (the caller's address only).
     */
    private function clear(string $scope, string $email = ''): void {
        if ($scope === 'ip') {
            $this->settings->set($this->key('ip'), null);
            return;
        }
        unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
        if ($email !== '') {
            $this->settings->set($this->key('login', $email), null);
        }
    }

    /**
     * A `login_lock_` settings key, so purge() reaps the whole family on one sweep. $scope is
     * 'login' (per address, hashed lowercased), 'ip' (per client address), or 'cooldown'
     * (a public auth action named by $subject, for $extra).
     */
    private function key(string $scope, string $subject = '', string $extra = ''): string {
        if ($scope === 'ip') {
            return 'login_lock_ip_' . hash('sha256', ip());
        }
        if ($scope === 'cooldown') {
            return 'login_lock_' . $subject . '_' . hash('sha256', $extra);
        }
        return 'login_lock_' . hash('sha256', strtolower(trim($subject)));
    }

    /**
     * Current login-throttle counters, keyed by $scope:
     *
     *   state('email', $email)  the session counters merged with the durable `settings` row for
     *                           that address (the session values are pushed back down afterwards,
     *                           so the stricter of the two wins for the rest of the request)
     *   state('ip')            the durable per-client-address row only, with no session fallback
     *
     * Both return ['fails' => int, 'lock_until' => int].
     */
    private function state(string $scope, string $email = ''): array {
        $empty = ['fails' => 0, 'lock_until' => 0];

        $raw = $this->settings->get($scope === 'ip' ? $this->key('ip') : $this->key('login', $email));
        if ($raw === false) {
            return $scope === 'ip' ? $empty : [
                'fails' => (int)($_SESSION['login_fails'] ?? 0),
                'lock_until' => (int)($_SESSION['login_lock_until'] ?? 0),
            ];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $scope === 'ip' ? $empty : [
                'fails' => (int)($_SESSION['login_fails'] ?? 0),
                'lock_until' => (int)($_SESSION['login_lock_until'] ?? 0),
            ];
        }

        if ($scope === 'ip') {
            return [
                'fails' => (int)($decoded['fails'] ?? 0),
                'lock_until' => (int)($decoded['lock_until'] ?? 0),
            ];
        }

        $state = [
            'fails' => max((int)($_SESSION['login_fails'] ?? 0), (int)($decoded['fails'] ?? 0)),
            'lock_until' => max((int)($_SESSION['login_lock_until'] ?? 0), (int)($decoded['lock_until'] ?? 0)),
        ];
        if ($state['fails'] > 0 || $state['lock_until'] > 0) {
            $_SESSION['login_fails'] = $state['fails'];
            $_SESSION['login_lock_until'] = $state['lock_until'];
        }
        return $state;
    }

    private function store(string $email, array $state): void {
        if ($email === '') {
            $_SESSION['login_fails'] = (int)($state['fails'] ?? 0);
            $_SESSION['login_lock_until'] = (int)($state['lock_until'] ?? 0);
            return;
        }

        $_SESSION['login_fails'] = (int)($state['fails'] ?? 0);
        $_SESSION['login_lock_until'] = (int)($state['lock_until'] ?? 0);
        $this->settings->set($this->key('login', $email), json_encode($state, JSON_THROW_ON_ERROR));
    }

    /**
     * Forget login-throttle rows whose lock window has passed (best-effort hygiene so abandoned
     * `settings` rows don't accumulate). Keeps rows with an active lock_until; expired ones will
     * only ever block nobody.
     */
    private function purge(): void {
        try {
            $db = $this->db;
            $now = time();
            foreach ($db->select("SELECT name, value FROM settings WHERE name LIKE 'login\\_lock\\_%'") as $r) {
                $decoded = json_decode($r['value'] ?? '', true);
                $lockUntil = is_array($decoded) ? (int)($decoded['lock_until'] ?? 0) : 0;
                if ($lockUntil <= 0 || $now >= $lockUntil) {
                    $db->execute('DELETE FROM settings WHERE name = ?', [$r['name']]);
                }
            }
        } catch (Throwable $e) {
            // Sweep is best-effort; never fail a request over it.
        }
    }

    private function attempt(string $email, bool $success, string $method = 'site'): void {
        $this->analytics->login($email, $success, $method);
    }

    /* ---------------------------------------------------------- */
    /* Cooldowns for the public auth flows (register/resend/reset)  */
    /* ---------------------------------------------------------- */

    /**
     * Cooldown counter for a public auth action, stored in `settings` under the `login_lock_`
     * family so purge() reaps it like the login throttle. An expired window counts as
     * zero.
     */
    private function cooldown(string $action, string $subject): array {
        $empty = ['fails' => 0, 'lock_until' => 0];
        try {
            $raw = $this->settings->get($this->key('cooldown', $action, $subject));
        } catch (Throwable $e) {
            return $empty;
        }
        if ($raw === false) return $empty;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return $empty;
        $lockUntil = (int)($decoded['lock_until'] ?? 0);
        if ($lockUntil <= 0 || time() >= $lockUntil) return $empty;
        return ['fails' => (int)($decoded['fails'] ?? 0), 'lock_until' => $lockUntil];
    }

    /**
     * Is this action currently cooled down for `$subject`? Two independent limits: a short session
     * gap (survives nothing, always available) and a durable counter in `settings` (survives losing
     * the session/cookies).
     */
    private function blocked(string $action, string $subject, int $max, int $sessionSeconds = 0): bool {
        if ($sessionSeconds > 0) {
            $last = (int)($_SESSION['cooldown_' . $action] ?? 0);
            if ($last > 0 && (time() - $last) < $sessionSeconds) return true;
        }
        return $this->cooldown($action, $subject)['fails'] >= $max;
    }

    /**
     * Record one attempt and (re)open the cooldown window for `$subject`. The durable write is
     * best-effort: rate limiting never fails a request.
     */
    private function hit(string $action, string $subject, int $windowMinutes, int $sessionSeconds = 0): void {
        if ($sessionSeconds > 0) {
            $_SESSION['cooldown_' . $action] = time();
        }
        $state = $this->cooldown($action, $subject);
        $state['fails']++;
        $state['lock_until'] = time() + max(1, $windowMinutes) * 60;
        try {
            $this->settings->set(
                $this->key('cooldown', $action, $subject),
                json_encode($state, JSON_THROW_ON_ERROR)
            );
        } catch (Throwable $e) {
        }
        $this->purge();
    }

    /**
     * Establish the session for a verified login and record the login time. The change stamp is
     * taken AFTER the login write, which itself bumps updated — otherwise the very next
     * request would read a "changed account" and rotate the fresh session id for nothing.
     */
    private function establish(array $account, string $role): void {
        $this->rotate();

        $role = normalize($role);

        $id = (int)$account['id'];
        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $account['name'];
        $_SESSION['user_email'] = $account['email'];
        $_SESSION['user_role'] = $role;
        $_SESSION['account_type'] = $role;

        if ($role === 'admin') {
            $_SESSION['admin_id'] = $id;
            $_SESSION['admin_name'] = $account['name'];
            $_SESSION['admin_email'] = $account['email'];
            $_SESSION['admin_role'] = 'admin';
        } else {
            unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email'], $_SESSION['admin_role']);
        }

        if ($this->db->execute("UPDATE users SET login = NOW() WHERE id = ?", [$id]) && !$this->db->errno()) {
            $fresh = $this->db->row("SELECT updated FROM users WHERE id = ? LIMIT 1", [$id]);
            $_SESSION['account_updated_at'] = (string)($fresh['updated'] ?? '');
        } else {
            $_SESSION['account_updated_at'] = (string)($account['updated'] ?? '');
        }

        $this->refresh();
    }

    /**
     * Create or update an account, one method per kind of thing saved:
     *
     *   save('artist', $data, $id)  the artist profile — bio and the $_FILES['image'] upload,
     *                                for the account whose role is already 'artist'
     *   save($role,    $data, $id)  a plain user or admin account, keyed by $role
     *
     * $id is 0 to create. Every branch returns ['success' => bool, 'message' => display text], plus
     * 'user' on a successful create.
     */
    public function save(string $role, array $data, int $id = 0): array {
        if ($role === 'artist') {
            $name = trim($data['name'] ?? '');
            $bio = trim($data['bio'] ?? '');
            $country = trim($data['country'] ?? '');
            $image = $data['existing_image'] ?? '';
            $err = '';

            if (!valid($country, 'country')) {
                $err = 'Please select a valid country.';
            }
            if (!$err && mb_strlen($name) > 100) {
                $err = 'Name must be 100 characters or fewer.';
            }

            // Artist image upload (keeps the existing image when none was uploaded)
            $file = $data['image'] ?? null;
            if (is_array($file) && !empty($file['name'])) {
                $upload = store($file, IMAGE_PATH . '/', 'image', 'image');
                if ($upload['error'] === null) {
                    $image = 'images/' . $upload['path'];
                } else {
                    $err = $upload['error'];
                }
            }

            if (!$name && !$err) $err = 'Name required.';
            if ($err) return ['success' => false, 'message' => $err];

            $slug = slug($name, 'users', $id ?: 0);

            if ($id) {
                $this->db->execute(
                    "UPDATE users SET name = ?, slug = ?, bio = ?, image = ?, country = ?, updated = NOW()
                     WHERE id = ? AND role = 'artist'",
                    [$name, $slug, $bio, $image, $country, $id]
                );
            } else {
                $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $email = strtolower($slug) . '@artist.local';
                $this->db->execute(
                    "INSERT INTO users (name, email, password, role, slug, bio, image, country, status)
                     VALUES (?, ?, ?, 'artist', ?, ?, ?, ?, 'active')",
                    [$name, $email, $password, $slug, $bio, $image, $country]
                );
            }

            if ($this->db->errno() === 1062) {
                return ['success' => false, 'message' => "An artist with that name already exists."];
            }
            if ($this->db->errno()) {
                return ['success' => false, 'message' => "The artist could not be saved. Please try again."];
            }
            $this->analytics->log(
                $id ? 'artist.update' : 'artist.create',
                sprintf("Artist %s: %s", $id ? 'updated' : 'created', $name)
            );
            return ['success' => true, 'message' => "Artist saved."];
        }

        $role = normalize($role);
            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            $status = ($data['status'] ?? '') === 'disabled' ? 'disabled' : 'active';
            $country = trim($data['country'] ?? '');
            $password = (string)($data['password'] ?? '');

            if (mb_strlen($name) > 100) {
                return ['success' => false, 'message' => "Name must be 100 characters or fewer."];
            }
            if (mb_strlen($email) > 150) {
                return ['success' => false, 'message' => "Email address must be 150 characters or fewer."];
            }
            if ($this->taken($email, $id)) {
                return ['success' => false, 'message' => "That email address is already registered."];
            }

            if ($id) {
                $existing = $this->db->row("SELECT id, email FROM users WHERE id = ? AND role = ?", [$id, $role]);
                if (!$existing) return ['success' => false, 'message' => "That account could not be found."];

                if ($password !== '') {
                    if (strlen($password) < MIN_PASSWORD_LENGTH) {
                        return ['success' => false, 'message' => sprintf("Password must be at least %s characters long.", MIN_PASSWORD_LENGTH)];
                    }
                    $ok = $this->db->execute(
                        "UPDATE users SET name = ?, email = ?, status = ?, country = ?, password = ?, updated = NOW() WHERE id = ?",
                        [$name, $email, $status, $country, password_hash($password, PASSWORD_DEFAULT), $id]
                    );
                } else {
                    $ok = $this->db->execute(
                        "UPDATE users SET name = ?, email = ?, status = ?, country = ?, updated = NOW() WHERE id = ?",
                        [$name, $email, $status, $country, $id]
                    );
                }
                if (!$ok || $this->db->errno()) {
                    return ['success' => false, 'message' => "Something went wrong. Please try again."];
                }

                if ($password !== '' || strtolower($email) !== strtolower((string)$existing['email'])) {
                    $this->burn($id);
                }

                $this->analytics->log(
                    $role === 'admin' ? 'admin.update' : 'user.update',
                    sprintf("%s account updated: %s", ucfirst($role), $email)
                );
                return ['success' => true, 'message' => "Account updated."];
            }

            if ($password === '') {
                return ['success' => false, 'message' => "A password is required."];
            }
            if (strlen($password) < MIN_PASSWORD_LENGTH) {
                return ['success' => false, 'message' => sprintf("Password must be at least %s characters long.", MIN_PASSWORD_LENGTH)];
            }

            $accountId = $this->db->insert(
                "INSERT INTO users (name, email, password, role, country, status, verified, created)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $country, $status]
            );
            if ($accountId <= 0 || $this->db->errno()) {
                return ['success' => false, 'message' => "Something went wrong. Please try again."];
            }

            $this->analytics->log(
                $role === 'admin' ? 'admin.create' : 'user.create',
                sprintf("%s account created: %s", ucfirst($role), $email),
                $accountId,
                $name
            );
            return ['success' => true, 'message' => "Account created.", 'user' => $accountId];
    }
}
