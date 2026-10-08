<?php
/**
 * Application bootstrap — the single entry point. Every page, API endpoint and
 * view partial starts with `require_once __DIR__ . '/<rel>/app/bootstrap.php';`,
 * and that include is what makes the framework live: it loads config, makes every
 * helper and domain class available, builds the one shared instance of each class, then
 * runs the two request-wide gates (the maintenance gate and the exception handler) that
 * must precede any page logic.
 * Being `require_once`, including it from a view or partial is always safe.
 *
 * It contains no domain logic. Helpers live in app/functions.php; each domain
 * class in its own app/*.class.php file.
 */

/* ---------------------------------------------------------- */
/* Configuration                                              */
/* ---------------------------------------------------------- */
/* Constants, the secure session start and the security headers. */

require_once __DIR__ . '/config.php';

/* Marks the app as live. Every shared markup partial in app/includes/partials/ and both
 * standalone error pages in app/includes/ guard on this constant and 404 without it, so they
 * cannot render outside a bootstrapped request. app/ is already denied over HTTP twice
 * (.htaccess + zora.conf); this is the in-process guard. */
define('APP_BOOTSTRAPPED', true);

/* ---------------------------------------------------------- */
/* General helpers                                            */
/* ---------------------------------------------------------- */
/* One file: every general helper in the app, sectioned by concern. */

require_once __DIR__ . '/functions.php';

/* ---------------------------------------------------------- */
/* Domain classes                                             */
/* ---------------------------------------------------------- */
/* One class per file — no autoloader, so this list IS the load order. config.php
   and functions.php come first (the classes use them), then the classes, then the
   shared instances, then the gates below, which catch a missing DB and decide whether
   the user is an admin.

     DB        mysqli data layer; every query goes through
               select/row/scalar/execute/insert, always prepared.
     Settings  key/value site settings (the maintenance flag).
     User      accounts in ONE `users` table; the access-control vocabulary
               (authed/check('admin')/guard) lives here.
     Analytics activity log, login records, visitors, downloads.
     Mail      the single outbound-email path (PHPMailer/Postfix).
     Music     songs, categories, favorites, song requests.
     Pages     per-page SEO metadata. */

require_once __DIR__ . '/db.class.php';
require_once __DIR__ . '/settings.class.php';
require_once __DIR__ . '/user.class.php';
require_once __DIR__ . '/music.class.php';
require_once __DIR__ . '/analytics.class.php';
require_once __DIR__ . '/mail.class.php';
require_once __DIR__ . '/pages.class.php';

/* ---------------------------------------------------------- */
/* Shared instances                                            */
/* ---------------------------------------------------------- */
/* One object of each class per request, wired together here and never re-created.
   Because a page reaches this file with `require_once` at its own top level, these
   names land in the page's scope, which is what makes `$user`, `$music`, … available
   to the page, to the shells it requires afterwards and to the partials those shells
   pull in. A page must therefore never do `new User()` itself — it would shadow this
   instance and throw away the per-request memoization inside it.

   Order matters: Analytics needs DB, Mail needs Analytics, User needs all three, and
   Music needs User. Nothing connects here — DB opens its mysqli connection lazily on
   the first query, so a database outage still reaches the maintenance gate's own
   try/catch rather than failing while wiring. */

$db        = new DB();
$analytics = new Analytics($db);
$mail      = new Mail($analytics);
$settings  = new Settings($db);
$user      = new User($db, $analytics, $settings, $mail);
$music     = new Music($db, $analytics, $user);
$pages     = new Pages($db);

/* ---------------------------------------------------------- */
/* Deployment self-check                                       */
/* ---------------------------------------------------------- */
/* Runs only in production, and only for web requests.

   FATAL — the site would render visibly broken, so it is held back with a
   card instead of being served. Currently just the BASE_URL fallback: the
   real domain is not in GOSPELZORA_HOSTS, so every asset, link and API call
   points at the visitor's own machine. No error, no warning, just a site
   that renders unstyled with no CSS or JS.

   WARNING — logged, never fatal. An unwritable upload directory only breaks
   uploads, and a deliberately read-only deploy (immutable assets, no local
   uploads) is a legitimate way to run this, so it must not take the site
   down. It still means every upload will fail if uploads are used.

   deploy/check.php is the CLI equivalent and reports both plus TLS, mail,
   vendor/ and the installer lock. It lives here rather than in config.php
   because config.php runs before APP_BOOTSTRAPPED exists, which is the guard
   every standalone error page checks. */
if (APP_ENV === 'production' && PHP_SAPI !== 'cli') {
    $configProblems = [];
    $configWarnings = [];

    $requestHost = trim(preg_replace('~:\d{1,5}$~', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $requestHost = trim($requestHost, '[]');
    $localHost = in_array($requestHost, ['localhost', '127.0.0.1', '::1', ''], true);
    if (!$localHost && BASE_URL === 'http://localhost/') {
        $configProblems[] = sprintf(
            'The site URL resolved to <code>%s</code> for a request to <code>%s</code>, so every link, '
            . 'image and script points back at the visitor\'s own machine and the site renders unstyled. '
            . 'Add that domain to <code>GOSPELZORA_HOSTS</code> in <code>app/config.php</code>, or set '
            . '<code>BASE_URL</code> there explicitly.',
            e(BASE_URL),
            e($requestHost)
        );
    }
    if (!preg_match('~^https?://~i', BASE_URL)) {
        $configProblems[] = 'The site URL is not an absolute <code>http</code> or <code>https</code> address.';
    }

    foreach (['AUDIO_PATH' => 'song audio', 'IMAGE_PATH' => 'artist images', 'ARTWORK_PATH' => 'song artwork'] as $dirConst => $dirWhat) {
        $dir = constant($dirConst);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $configWarnings[] = sprintf(
                'The %s folder %s is not writable by the web server, so uploads will fail. Fix the '
                . 'ownership (see deploy/README.md) or point the path somewhere writable in '
                . 'app/config.php.',
                $dirWhat,
                $dir
            );
        }
    }

    if ($configWarnings !== []) {
        error_log('Gospelzora deployment warning: ' . implode(' | ', $configWarnings));
    }

    if ($configProblems !== []) {
        error_log('Gospelzora deployment self-check failed: ' . implode(' | ', array_map(
            static fn($p) => strip_tags($p),
            $configProblems
        )));
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
        }
        $page_title = "Site configuration needed";
        require __DIR__ . '/includes/config-error.php';
        exit;
    }
}


/* ---------------------------------------------------------- */
/* Maintenance mode                                            */
/* ---------------------------------------------------------- */
/* When an admin enables maintenance, every non-admin request is stopped
   right here: public pages and the member dashboard render a 503 notice,
   API endpoints answer a JSON 503, and non-admin attempts are rejected later
   in login(). The account that triggered the flag stays in; admins
   can use /dashboard/admin/maintenance to switch it off. */
if (PHP_SAPI !== 'cli') {
    try {
        $appMaintenance = $settings->maintenance();
    } catch (Throwable $e) {
        // DB unavailable — never lock the whole site behind the gate.
        $appMaintenance = false;
    }

    if ($appMaintenance && !$user->check('admin')) {
        if (str_contains((string)($_SERVER['SCRIPT_FILENAME'] ?? ''), '/api/')) {
            http_response_code(503);
            json(['success' => false, 'message' => "Sorry, this website is undergoing maintenance. We apologize for the inconvenience. Please try again later."], 503);
        }

        $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $scriptPath = strtr((string)($_SERVER['SCRIPT_FILENAME'] ?? ''), '\\', '/');
        // The login form stays reachable so an admin who signed out (or a
        // fresh one) can still authenticate; non-admins are rejected in
        // login(). Everything else — public browsing, dashboard,
        // register/forgot/reset/verify-email — is gated.
        if (!in_array($script, ['login.php'], true)) {
            // Admin-panel pages during maintenance go to /login instead of
            // the 503 card, so a signed-out admin can sign straight back in.
            if (str_contains($scriptPath, '/dashboard/admin/')) {
                redirect('login');
            }
            $page_title = "Website under Maintenance";
            $page_description = "Sorry, this website is undergoing maintenance. We apologize for the inconvenience. Please try again later.";
            http_response_code(503);
            require __DIR__ . '/includes/maintenance.php';
            exit;
        }
    }
}

/* A DB outage should still show the friendly "technical difficulties" card on
   ordinary pages. conn() surfaces a mysqli connection error (unknown
   database / refused) as either a mysqli_sql_exception or a RuntimeException;
   both must resolve to the friendly card, so match on type rather than the
   message text. Per-call try/catch guards already absorb most DB touches. */
set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught exception: ' . $e);
    if ($e instanceof mysqli_sql_exception
        || $e instanceof RuntimeException && str_contains($e->getMessage(), 'technical difficulties')) {
        if (str_contains((string)($_SERVER['SCRIPT_FILENAME'] ?? ''), '/api/')) {
            if (!headers_sent()) {
                http_response_code(503);
            }
            json(['success' => false, 'message' => "Technical difficulties"], 503);
        }
        if (!headers_sent()) {
            http_response_code(503);
        }
        require __DIR__ . '/includes/db-error.php';
        exit;
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    exit;
});
