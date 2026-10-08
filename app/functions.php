<?php
/**
 * Every general helper in the app, in ONE file, sectioned by concern.
 *
 * Do not re-split this file — it was one file before the 2026 restructure and is
 * being re-merged for readability. Add a new helper to the matching section.
 *
 * Almost nothing here touches the domain. The deliberate exceptions are
 * normalize()/cached() (pure session/role string mappers lifted out of the old
 * User statics), the icon catalogue (icons()/usage(), which read the `icons`
 * and `categories` tables), and slug() (which has to ask the table whether a
 * slug is taken). Those three reach for the shared $db with `global $db`:
 * this file is required by app/bootstrap.php BEFORE the classes are loaded and
 * before $db exists, so they cannot take it as a parameter, and every one of
 * them is only ever called long after bootstrap has finished wiring.
 */

/* ---------------------------------------------------------- */
/* Request & client address                                   */
/* ---------------------------------------------------------- */

/**
 * The real client IP address.
 *
 * Order matters and is the whole point of this helper: Cloudflare's
 * CF-Connecting-IP, then the FIRST entry of X-Forwarded-For (the original
 * client, before each proxy appended itself), then REMOTE_ADDR.
 *
 * Every candidate is validated as an actual IP address before being believed,
 * because all three headers are client-controllable unless a proxy overwrites
 * them — this returns something safe to store in the login and visit logs, not
 * something an attacker can type.
 */
function ip(): string
{
    $candidates = [];

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // "client, proxy1, proxy2" — the client is first.
        $first = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        $candidates[] = $first;
    }
    $candidates[] = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    foreach ($candidates as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

/**
 * The request's User-Agent, trimmed to what a log column can hold.
 *
 * Control characters are stripped rather than escaped: this is stored and shown
 * in tables, never parsed, and a raw escape sequence in a log viewer is noise.
 */
function agent(): string
{
    $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $agent = preg_replace('~[\x00-\x1F\x7F]~', '', $agent) ?? '';

    return mb_substr(trim($agent), 0, 255);
}

/**
 * The referring URL, or '' when there is none or it is not a real http(s) URL.
 *
 * Used for the visit log's referrer column, so it is validated rather than
 * echoed: a Referer of "javascript:..." is not a referrer.
 */
function referer(): string
{
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '' || !preg_match('~^https?://~i', $referer)) {
        return '';
    }

    return mb_substr($referer, 0, 500);
}

/* ---------------------------------------------------------- */
/* Session, redirects & flash messages                         */
/* ---------------------------------------------------------- */

/**
 * Send the visitor somewhere else and stop.
 *
 * Takes a site-relative path ('dashboard/songs'), not a full URL, so a link
 * built here cannot be pointed at another host by accident. An absolute URL is
 * passed through untouched for the rare case that genuinely needs it.
 *
 * @return never
 */
function redirect(string $path = ''): void
{
    $location = preg_match('~^https?://~i', $path) ? $path : url($path);

    if (!headers_sent()) {
        header('Location: ' . $location);
    }
    exit;
}

/**
 * Set, or read-and-clear, the one-shot flash message.
 *
 * Called with no arguments it RETURNS the pending message and removes it in the
 * same call, which is what makes it one-shot: the three shell headers each do
 * `if ($flash)` and then the partial renders it, so a refresh cannot replay it.
 *
 * @param string|null $type    bootstrap contextual name: success|danger|warning|info
 * @param string|null $message the text to show once
 * @return array|null ['type' => …, 'message' => …] when reading
 */
function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type === null) {
        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return null;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return $flash;
    }

    $_SESSION['flash'] = ['type' => $type, 'message' => (string)$message];

    return null;
}

/**
 * The canonical role name for an account type: 'user' | 'artist' | 'admin'.
 *
 * The single funnel every role string passes through, on the way into the
 * database as well as out of it, so an unexpected value can never reach a WHERE
 * clause or a session key. Anything not in User::SESSION_ROLES becomes 'user'.
 */
function normalize(mixed $role): string
{
    $role = strtolower(trim((string)$role));

    return in_array($role, User::SESSION_ROLES, true) ? $role : 'user';
}

/**
 * The role held in the session for this request, or '' when signed out.
 *
 * Consulted ONLY as a fallback while the database is unreachable — see
 * User::role(). The row, not this, decides who is signed in; that is the
 * documented session-only debt in AGENTS.md.
 */
function cached(): string
{
    return isset($_SESSION['user_role']) ? (string)$_SESSION['user_role'] : '';
}

/* ---------------------------------------------------------- */
/* Output                                                      */
/* ---------------------------------------------------------- */

/**
 * HTML-escape for text going into markup.
 *
 * ENT_QUOTES, because the shells put escaped values inside single-quoted
 * attributes too; ENT_SUBSTITUTE, so invalid UTF-8 renders as a replacement
 * character instead of returning an empty string and silently blanking a field.
 *
 * Note this is NOT applied to url() or asset() output at some call sites,
 * because those build their result from BASE_URL — which, unpinned, is derived
 * from the request. See those helpers for why.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * A Font Awesome icon element.
 *
 * Returns markup, so it is never escaped by the caller — every part it is given
 * is escaped here instead.
 *
 * @param string $name    the icon name without the `fa-` prefix ('play', 'heart')
 * @param string $style   extra classes; a bare Font Awesome style keyword
 *                        ('solid', 'regular', 'brands') is expanded to `fa-…`
 * @param string $classes further classes for the element
 * @param string $attrs   raw extra attributes, e.g. 'style="font-size:4rem"'.
 *                        Only ever passed static markup from the shells.
 */
function icon(string $name, string $style = '', string $classes = '', string $attrs = ''): string
{
    $name = preg_replace('~^(?:fa-(?:solid|regular|brands)\s+)?fa-~', '', strtolower(trim($name))) ?? '';
    $name = preg_replace('~[^a-z0-9-]~', '', $name) ?? '';
    if ($name === '') {
        return '';
    }

    $style = trim($style);
    if (in_array($style, ['solid', 'regular', 'brands', 'light', 'thin', 'duotone'], true)) {
        $style = 'fa-' . $style;
    }

    $class = trim('fa fa-' . $name . ' ' . $style . ' ' . $classes);

    return '<i class="' . e($class) . '"' . ($attrs !== '' ? ' ' . $attrs : '') . '></i>';
}

/**
 * Emit a JSON response and stop.
 *
 * Every api/*.php endpoint and every AJAX branch in dashboard/ ends here, so
 * this is the only place that sets the JSON content type and the only exit an
 * endpoint needs.
 *
 * @return never
 */
function json(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

/* ---------------------------------------------------------- */
/* Countries                                                   */
/* ---------------------------------------------------------- */

/**
 * An ISO 3166-1 alpha-2 code as its English country name — or back again.
 *
 * Reads the single COUNTRY_LIST in app/config.php, so the country partial and
 * this helper can never disagree about what is a country.
 *
 * @param string $code the alpha-2 code
 * @param string $mode 'code' returns the normalised upper-case code instead
 * @return string '' when the code is not in the list
 */
function country(string $code, string $mode = 'name'): string
{
    $code = strtoupper(trim($code));

    if ($mode === 'code') {
        return isset(COUNTRY_LIST[$code]) ? $code : '';
    }

    return COUNTRY_LIST[$code] ?? '';
}

/**
 * Validate one value against one kind of rule.
 *
 * @param string $value the candidate
 * @param string $type  'email' (the default) or 'country'
 */
function valid(mixed $value, string $type = 'email'): bool
{
    $value = trim((string)$value);

    return match ($type) {
        'country' => $value !== '' && isset(COUNTRY_LIST[strtoupper($value)]),
        default    => is_valid_email($value),
    };
}

/* ---------------------------------------------------------- */
/* URLs & slugs                                                */
/* ---------------------------------------------------------- */

/**
 * A site URL for a path, built from BASE_URL.
 *
 * The query string is part of $path and is deliberately NOT encoded: callers
 * pass things like 'charts?type=weekly'. Callers escape the result with e()
 * because an unpinned BASE_URL is derived from the request.
 */
function url(string $path = ''): string
{
    $base = rtrim((string)BASE_URL, '/');

    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

/**
 * A URL for a file under assets/.
 *
 * Used for markup attributes at some call sites WITHOUT e(), so the path is
 * reduced to a safe character set here and the result is safe to print as-is:
 * a stored filename can never close the attribute or inject a scheme.
 */
function asset(string $path): string
{
    $path = ltrim(trim($path), '/');
    // Drop anything that is not a plain relative asset path — this also kills
    // "..", protocol-relative "//host" and "javascript:" in one pass.
    $path = preg_replace('~[^A-Za-z0-9._/-]~', '', $path) ?? '';
    $path = preg_replace('~/{2,}~', '/', $path) ?? '';
    if ($path === '' || str_contains($path, '..')) {
        return url('assets/');
    }

    return url('assets/' . $path);
}

/**
 * Reduce a title or name to a URL slug: lowercase, ascii-ish, hyphen-separated.
 */
function slugify(string $text): string
{
    $text = trim($text);
    $text = mb_strtolower($text, 'UTF-8');

    // Keep the letters and digits every locale agrees on, drop the rest.
    $text = preg_replace('~[^\p{L}\p{N}]+~u', '-', $text) ?? '';
    $text = preg_replace('~-+~', '-', $text) ?? '';

    return trim($text, '-');
}

/**
 * A slug for $name that is unique within $table.
 *
 * Appends -2, -3, … until the slug is free. $id is the row being updated, so
 * re-saving a record keeps its own slug instead of colliding with itself.
 *
 * The $table argument is interpolated into SQL and therefore must be one of the
 * tables below — never anything a request supplied.
 */
function slug(string $name, string $table, int $id = 0): string
{
    global $db;

    $allowed = ['songs', 'categories', 'playlists', 'users'];
    if (!in_array($table, $allowed, true)) {
        return slugify($name);
    }

    $base = slugify($name);
    if ($base === '') {
        $base = 'item';
    }
    $base = mb_substr($base, 0, 180);

    $slug = $base;
    $suffix = 1;

    while (true) {
        $clash = (int)$db->scalar(
            "SELECT COUNT(*) FROM $table WHERE slug = ? AND id <> ?",
            [$slug, $id]
        );
        if ($clash === 0) {
            return $slug;
        }
        $suffix++;
        $slug = $base . '-' . $suffix;
    }
}

/* ---------------------------------------------------------- */
/* Images                                                      */
/* ---------------------------------------------------------- */

/**
 * The URL for a stored image, or the right placeholder when there isn't one.
 *
 * $path is the value from an `artwork`/`image` column — relative to assets/,
 * e.g. 'images/artwork/amazing-grace.png'. $kind decides the fallback:
 * 'song' artwork and 'artist' photos have separate placeholders.
 */
function image(?string $path, string $kind = 'song'): string
{
    $fallbacks = [
        'song'   => 'images/artwork.png',
        'artist' => 'images/artist.png',
    ];
    $fallback = $fallbacks[$kind] ?? $fallbacks['song'];

    $path = trim((string)$path);
    if ($path === '') {
        return asset($fallback);
    }

    // Only serve a path that really resolves to a file inside assets/; a stale
    // database value must not turn into a broken image.
    if (!str_starts_with($path, 'images/') || str_contains($path, '..')) {
        return asset($fallback);
    }
    if (!is_file(ASSETS_PATH . '/' . $path)) {
        return asset($fallback);
    }

    return asset($path);
}

/* ---------------------------------------------------------- */
/* Uploads                                                     */
/* ---------------------------------------------------------- */

/**
 * A safe, unique filename for an uploaded file.
 *
 * Built from the caller's chosen name when there is one (the song slug, which
 * already carries the artist name) and from the original name otherwise, with a
 * short random suffix so two uploads of "cover.png" cannot collide. The
 * extension is taken from the ORIGINAL name and is re-checked by validate().
 */
function filename(string $original, string $kind = 'image'): string
{
    $original = basename(str_replace('\\', '/', $original));
    $extension = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));

    $base = slugify((string)pathinfo($original, PATHINFO_FILENAME));
    $base = $base === '' ? ($kind === 'audio' ? 'audio' : 'image') : mb_substr($base, 0, 100);

    return $base . '-' . bin2hex(random_bytes(4)) . ($extension === '' ? '' : '.' . $extension);
}

/**
 * Check an uploaded file, returning the finished display text for the problem
 * or null when the file is acceptable.
 *
 * Checks the size first and the extension second, because that is the order the
 * visitor needs them explained in, and it means an oversized file never reaches
 * the image decoder.
 *
 * @param string $kind 'audio' or 'image'
 * @param array  $file one entry of $_FILES
 */
function validate(string $kind, array $file): ?string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
            'The %s file is larger than the %s upload limit. Please choose a smaller file.',
            $kind,
            $kind === 'audio' ? ini_get('upload_max_filesize') : ini_get('post_max_size')
        ),
        UPLOAD_ERR_PARTIAL   => "The $kind file was only partially uploaded. Please try again.",
        UPLOAD_ERR_NO_FILE   => "No $kind file was uploaded.",
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => "The server could not store the $kind file.",
        UPLOAD_ERR_EXTENSION => "The $kind file was blocked by a PHP extension.",
        default              => "The $kind file could not be uploaded.",
    };
}

/**
 * Validate and store one uploaded file.
 *
 * VALIDATES FIRST, MOVES SECOND — nothing is written when validate() has already
 * rejected the file, so a rejected upload cannot leave a truncated or
 * attacker-named file behind.
 *
 * $directory is the destination with its trailing slash (the caller passes
 * AUDIO_PATH . '/' and friends); $kind is what to validate against; $field is
 * the word to use when complaining ('audio', 'artwork', 'image'); $name is the
 * filename to use, when the caller has already decided one.
 *
 * @return array{path: string, error: string|null} path is the bare filename
 *         relative to $directory, so the caller prefixes its own subdirectory
 */
function store(array $file, string $directory, string $kind, string $field, ?string $name = null): array
{
    $problem = validate($kind, $file);
    if ($problem !== null) {
        return ['path' => '', 'error' => $problem];
    }

    $original = basename(str_replace('\\', '/', (string)($file['name'] ?? '')));
    $extension = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));

    $allowed = $kind === 'audio'
        ? ['mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac']
        : ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($extension, $allowed, true)) {
        return [
            'path' => '',
            'error' => sprintf(
                'The %s file must be one of: %s.',
                $field,
                strtoupper(implode(', ', $allowed))
            ),
        ];
    }

    // An image has to actually BE an image, or the site would serve whatever
    // bytes were uploaded under an image URL.
    $temporary = (string)($file['tmp_name'] ?? '');
    if ($kind === 'image' && @getimagesize($temporary) === false) {
        return ['path' => '', 'error' => "The $field file is not a valid image."];
    }

    if ($temporary === '' || !is_uploaded_file($temporary)) {
        return ['path' => '', 'error' => "The $field file could not be verified as an upload."];
    }

    $name = $name === null || trim($name) === ''
        ? filename($original, $kind)
        : slugify((string)pathinfo($name, PATHINFO_FILENAME)) . '.' . $extension;
    $name = basename($name);

    $directory = rtrim($directory, '/') . '/';
    if (!is_dir($directory) || !is_writable($directory)) {
        return ['path' => '', 'error' => "The server cannot write to the $field folder."];
    }

    if (!@move_uploaded_file($temporary, $directory . $name)) {
        return ['path' => '', 'error' => "The $field file could not be saved."];
    }

    return ['path' => $name, 'error' => null];
}

/* ---------------------------------------------------------- */
/* Formatting                                                  */
/* ---------------------------------------------------------- */

/**
 * A whole number shortened for a counter: 999, 1.2K, 15K, 1.5M.
 */
function abbrev(mixed $number): string
{
    $number = (int)$number;
    if ($number < 0) {
        return '0';
    }
    if ($number < 1000) {
        return (string)$number;
    }

    $short = $number < 1000000
        ? $number / 1000
        : $number / 1000000;
    $text = rtrim(rtrim(number_format($short, 1, '.', ''), '0'), '.');

    return $text . ($number < 1000000 ? 'K' : 'M');
}

/**
 * How long ago a timestamp was, in words.
 *
 * Returns '' for an empty value so a caller can decide what "never" looks like
 * in its own markup. Deliberately coarse: these are list columns, and anything
 * older than a week is better as a date than as "63 days ago".
 */
function ago(mixed $datetime): string
{
    $datetime = trim((string)$datetime);
    if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '';
    }

    $then = strtotime($datetime);
    if ($then === false) {
        return '';
    }

    $seconds = max(0, time() - $then);

    return match (true) {
        $seconds < 60      => 'Just now',
        $seconds < 3600    => plural(intdiv($seconds, 60), 'minute') . ' ago',
        $seconds < 86400   => plural(intdiv($seconds, 3600), 'hour') . ' ago',
        $seconds < 604800  => plural(intdiv($seconds, 86400), 'day') . ' ago',
        default            => date('M j, Y', $then),
    };
}

/**
 * "1 minute" / "5 minutes" — the shared tail of ago().
 */
function plural(int $count, string $noun): string
{
    return $count . ' ' . $noun . ($count === 1 ? '' : 's');
}

/**
 * The human label for a status value, for accounts and songs alike.
 *
 * Both vocabularies reach this one helper — 'active'/'inactive' on an account,
 * 'published'/'pending'/'approved'/'requested'/'draft' on a song — so the badge
 * in the admin tables reads the same wherever it appears.
 */
function status(mixed $value): string
{
    $value = strtolower(trim((string)$value));

    $labels = [
        'active'    => 'Active',
        'inactive'  => 'Inactive',
        'published' => 'Published',
        'pending'   => 'Pending',
        'approved'  => 'Approved',
        'requested' => 'Requested',
        'draft'     => 'Draft',
    ];

    return $labels[$value] ?? ucfirst($value);
}

/**
 * A track length, and — with $strict — the other direction.
 *
 * WITHOUT $strict this FORMATS: 225 becomes "3:45", and anything past an hour
 * grows an hours field ("1:02:03"). Used for display and for the value of the
 * duration input.
 *
 * WITH $strict it PARSES instead, turning what the form submitted back into
 * seconds: "225" and "3:45" both mean 225, and anything unparseable becomes 0
 * rather than a nonsense length. Both directions are needed because the same
 * field is rendered by one and submitted to the other.
 *
 * @return string|int formatted text, or seconds when $strict
 */
function duration(mixed $value, bool $strict = false): string|int
{
    $value = trim((string)$value);

    if ($strict) {
        if ($value === '') {
            return 0;
        }
        if (ctype_digit($value)) {
            return (int)$value;
        }
        $parts = explode(':', $value);
        if (count($parts) < 2 || count($parts) > 3) {
            return 0;
        }
        foreach ($parts as $part) {
            if ($part === '' || !ctype_digit($part)) {
                return 0;
            }
        }

        $seconds = 0;
        foreach ($parts as $part) {
            $seconds = $seconds * 60 + (int)$part;
        }

        return $seconds;
    }

    $seconds = (int)$value;
    if ($seconds < 0) {
        $seconds = 0;
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $rest = $seconds % 60;

    return $hours > 0
        ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
        : sprintf('%d:%02d', $minutes, $rest);
}

/* ---------------------------------------------------------- */
/* Pagination                                                  */
/* ---------------------------------------------------------- */

/**
 * Turn a row count into everything a list view and the pagination partial need.
 *
 * $page is clamped into range rather than trusted: a hand-edited ?page=9999 on
 * an empty result set would otherwise produce an OFFSET far past the end.
 *
 * @return array{total:int, per_page:int, total_pages:int, current_page:int,
 *               has_prev:bool, has_next:bool, offset:int}
 */
function paginate(int $total, int $page = 1, ?int $perPage = null): array
{
    $perPage = $perPage ?? ITEMS_PER_PAGE;
    if ($perPage < 1) {
        $perPage = ITEMS_PER_PAGE;
    }

    $total = max(0, $total);
    $totalPages = (int)ceil($total / $perPage);
    $totalPages = max(1, $totalPages);

    $current = max(1, min($page, $totalPages));

    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'total_pages'  => $totalPages,
        'current_page' => $current,
        'has_prev'     => $current > 1,
        'has_next'     => $current < $totalPages,
        'offset'       => ($current - 1) * $perPage,
    ];
}

/* ---------------------------------------------------------- */
/* CSRF                                                        */
/* ---------------------------------------------------------- */

/**
 * Read the CSRF token, or check one.
 *
 * Called with no argument it returns the token for this session, creating it on
 * first use. Called with a token it verifies it — the form side of every POST in
 * the app, and the reason those forms carry a csrf_token field at all.
 */
function csrf(?string $token = null): string|bool
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if ($token === null) {
        return $_SESSION['csrf_token'];
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------------------------------------------------------- */
/* Validation                                                  */
/* ---------------------------------------------------------- */

/**
 * A syntactically valid email address.
 *
 * filter_var() with FILTER_VALIDATE_EMAIL, plus a length cap: the column is
 * VARCHAR(254), and letting a 4000-character string through would be rejected
 * by MySQL as a duplicate-key-free truncation rather than as the bad input it is.
 */
function is_valid_email(string $email): bool
{
    $email = trim($email);
    if ($email === '' || mb_strlen($email) > 254) {
        return false;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/* ---------------------------------------------------------- */
/* Lookups shared by the domain classes                        */
/* ---------------------------------------------------------- */

/**
 * Decide whether a lookup key is a numeric id or a slug.
 *
 * The result is split into the COLUMN (safe to interpolate — it can only ever
 * be 'id' or 'slug') and the PARAM (always bound), which is what lets a public
 * URL like song/amazing-grace-hiswill and a numeric id reach the same query
 * without either one being concatenated into SQL.
 *
 * @return array{column: string, param: mixed}
 */
function resolve(mixed $value): array
{
    $value = trim((string)$value);

    return ctype_digit($value)
        ? ['column' => 'id', 'param' => (int)$value]
        : ['column' => 'slug', 'param' => $value];
}

/* ---------------------------------------------------------- */
/* Icon catalogue                                              */
/* ---------------------------------------------------------- */

/**
 * The whole icon catalogue, keyed by icon name.
 *
 * Backs the icon picker: api/icons.php serves this to the browser, which is why
 * the `css` column is re-exposed to the client under the `fa_class` key — that is
 * the response contract assets/js/app.js reads.
 *
 * @return array<string, array{css: string, uses: string}>
 */
function icons(): array
{
    global $db;

    $catalogue = [];
    foreach ($db->select("SELECT name, css, uses FROM icons ORDER BY name") as $row) {
        $catalogue[(string)$row['name']] = [
            'css'  => (string)$row['css'],
            'uses' => (string)$row['uses'],
        ];
    }

    return $catalogue;
}

/**
 * Where an icon is currently in use, by category name.
 *
 * Answers the question the picker actually asks — "what breaks if I drop this
 * icon?" — so an admin can see that a category already uses it before removing
 * it from the catalogue. Accepts either the bare name ('music') or the stored
 * form ('fa-music'), because the picker holds the stored form.
 *
 * @return string[] empty when the icon is unused
 */
function usage(string $name): array
{
    global $db;

    $name = preg_replace('~^(?:fa-(?:solid|regular|brands)\s+)?fa-~', '', strtolower(trim($name))) ?? '';
    if ($name === '') {
        return [];
    }

    $rows = $db->select(
        "SELECT name FROM categories WHERE icon = ? OR icon = ? ORDER BY name",
        [$name, 'fa-' . $name]
    );

    return array_map(static fn(array $row): string => (string)$row['name'], $rows);
}

/* ---------------------------------------------------------- */
/* Static legal copy                                           */
/* ---------------------------------------------------------- */

/**
 * Wrap the long-form legal pages (terms, privacy, copyright).
 *
 * The input is the page's own markup with its placeholders filled in, NOT user
 * input, so it is passed through untouched — escaping it here would print the
 * headings and lists as literal tags. That is the one deliberate exception to
 * escaping on output in this file, and it is why the only three call sites are
 * the three static legal pages.
 */
function legal(string $html): string
{
    return '<div class="legal-body">' . $html . '</div>';
}