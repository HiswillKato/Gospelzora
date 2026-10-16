<?php
/**
 * Gospelzora Installer — step-by-step setup wizard, one self-contained file.
 *
 * Use only once, via /installer.php. Once the admin account is created a lock
 * file (install.lock) is written and the installer refuses to run again (delete
 * install.lock, or this file, after installation).
 *
 * The whole wizard lives here, in three sections, in the order they run:
 *
 *   1. helper functions  — session state, DB connection, applying the schema,
 *                          writing the mail block back into app/config.php,
 *                          and the requirements checklist
 *   2. request state     — the lock check, the step number, and the working
 *                          values pre-filled from the previous step's session
 *   3. step handling     — the POST handlers for steps 3-5 plus the read-only
 *                          re-entry state a half-finished install needs
 *   4. markup            — the HTML for all six steps
 *
 * Sections 3 and 4 share this file's scope, so the step number and the
 * $error / $notice / $success messages one sets are the ones the other renders.
 * They communicate only through $step and those three variables.
 *
 * Every write the installer can perform (CREATE DATABASE, applying the schema,
 * rewriting app/config.php, creating the admin, writing install.lock) sits behind
 * a POST. That is what makes the six steps renderable - and reviewable - without
 * side effects.
 */

require_once __DIR__ . '/app/bootstrap.php';

header('X-Robots-Tag: noindex');

/* ====================================================================== */
/*  1. Helper functions                                                 */
/* ====================================================================== */
/*
   Installer helper functions.

   Pure support code for the setup wizard: reading the wizard's session state,
   connecting to MySQL, applying database.sql, writing the mail block back into
   app/config.php, and the requirements checklist. Nothing here reads
   $_GET/$_POST or echoes - section 2 owns the request, and
   section 3 owns the request's side effects.

   Part of installer.php, not a separate entry point.
   */

/* ---------------- helpers ---------------- */

/* The file the wizard writes settings into. On a deployed server that is
   app/config.local.php — the gitignored override layer — so real credentials
   never land in the tracked app/config.php and a later `git pull` cannot
   clobber them. It only falls back to app/config.php when the override file
   does not exist yet, which is the case during a first-time install. */
function configfile(): string {
    $local = ROOT_PATH . '/app/config.local.php';
    return is_file($local) ? $local : ROOT_PATH . '/app/config.php';
}

function conf() {
    $s = $_SESSION['install']['db'] ?? [];
    return [
        'host' => $s['host'] ?? DB_HOST,
        'user' => $s['user'] ?? DB_USER,
        'pass' => $s['pass'] ?? DB_PASS,
        'name' => $s['name'] ?? DB_NAME,
    ];
}

function mailconf() {
    $s = $_SESSION['install']['mail'] ?? [];
    return [
        'host'      => $s['host'] ?? MAIL_HOST,
        'port'      => (int)($s['port'] ?? MAIL_PORT),
        'from'      => $s['from'] ?? MAIL_FROM,
        'from_name' => $s['from_name'] ?? MAIL_FROM_NAME,
        'user'      => $s['user'] ?? MAIL_USER,
        'pass'      => $s['pass'] ?? MAIL_PASS, // effective current password — never prefilled in the form
        'admin'     => $s['admin'] ?? MAIL_ADMIN,
    ];
}

function mailwrite(array $m, string $pass) {
    $config_path = configfile();
    $config = is_file($config_path) ? @file_get_contents($config_path) : false;
    if ($config === false) {
        return 'Could not read the configuration file. Check its permissions and retry.';
    }

    $block  = "define('MAIL_HOST', " . var_export($m['host'], true) . ");\n";
    $block .= "define('MAIL_PORT', " . (int)$m['port'] . ");\n";
    $block .= "define('MAIL_FROM', " . var_export($m['from'], true) . ");\n";
    $block .= "define('MAIL_FROM_NAME', " . var_export($m['from_name'], true) . ");\n";
    $block .= "define('MAIL_USER', " . var_export($m['user'], true) . ");\n";
    $block .= "define('MAIL_PASS', " . var_export($pass, true) . ");\n";
    $block .= "define('MAIL_ADMIN', " . var_export($m['admin'], true) . ");\n";
    $block .= "define('MAIL_SUBJECT_PREFIX', '[" . SITE_NAME . "] ');\n";
    $block .= "define('MAIL_VERIFY_EXPIRY_HOURS', 48);\n";
    $block .= "define('MAIL_RESET_EXPIRY_HOURS', 1);";

    $pattern = "~define\(\s*['\"]MAIL_HOST['\"]\s*,.*?define\(\s*['\"]MAIL_RESET_EXPIRY_HOURS['\"]\s*,.*?\);~s";
    /*
     * preg_replace_callback, NOT preg_replace: the replacement is a closure.
     * preg_replace()'s $replacement parameter is array|string only, so passing
     * fn() => $block raised
     *   TypeError: preg_replace(): Argument #2 ($replacement) must be of type
     *   array|string, Closure given
     * and took the whole installer down with a 500 on step 4. The callback is
     * also what makes the "literal" replacement safe — $block is full of
     * $ signs and backslashes (var_export escapes them) which preg_replace()
     * would interpret as backreferences.
     */
    $updated = preg_replace_callback($pattern, fn() => $block, $config, 1, $count);
    if ($updated === null) {
        return 'Could not update the configuration file. Check its permissions and retry.';
    }
    if ($count !== 1) {
        // An override file that exists but has no mail block yet (a first-time
        // install) gets the block appended instead of replacing one.
        $updated = rtrim($config) . "\n\n" . $block;
    }

    // app/config.local.php holds real passwords, so refuse to serve it even if
    // app/'s deny rules are ever removed or AllowOverride breaks. It sits in a
    // PHP file, so the guard only runs if the file is requested as PHP.
    if (basename($config_path) === 'config.local.php' && !str_contains($updated, 'http_response_code(404)')) {
        $updated = "<?php http_response_code(404); exit; ?>\n" . $updated;
    }

    $written = @file_put_contents($config_path, $updated, LOCK_EX);
    if ($written === false || $written !== strlen($updated)) {
        return sprintf('Could not write %s. Make it writable by the web server and retry.', basename($config_path));
    }
    if (basename($config_path) === 'config.local.php') {
        @chmod($config_path, 0640);
    }
    return null;
}

function connect($cfg, $with_db = true) {
    $prev = mysqli_report(MYSQLI_REPORT_OFF);
    $con = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $with_db ? $cfg['name'] : '');
    mysqli_report($prev);
    if ($con->connect_errno) {
        return [null, $con->connect_error];
    }
    $con->set_charset('utf8mb4');
    return [$con, null];
}

function exists($con, $name) {
    $stmt = $con->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

function missing($con) {
    $all = ['users', 'categories', 'songs', 'favorites', 'messages'];
    $stmt = $con->prepare("SHOW TABLES");
    $stmt->execute();
    $existing = array_map('current', $stmt->get_result()->fetch_all(MYSQLI_NUM));
    $stmt->close();
    return array_values(array_diff($all, $existing));
}

function ready($cfg) {
    [$con, $err] = connect($cfg, true);
    if ($err) {
        return false;
    }
    $missing = missing($con);
    $con->close();
    return empty($missing);
}

function schema($con) {
    $sql = @file_get_contents(ROOT_PATH . '/database.sql');
    if ($sql === false) {
        return 'Could not read database.sql.';
    }
    $sql = preg_replace('/^CREATE\s+DATABASE[^;]*;/mi', '', $sql);
    $sql = preg_replace('/^USE\s+[`\w]+\s*;/mi', '', $sql);
    $sql = trim($sql);
    if ($sql === '') {
        return 'database.sql is empty.';
    }
    if (!$con->multi_query($sql)) {
        return 'SQL error: ' . $con->error;
    }
    while ($con->next_result()) {
        if ($con->errno) {
            return 'SQL error: ' . $con->error;
        }
    }
    return null;
}

function checks() {
    $checks = [];
    $checks[] = ['PHP 8.1 or newer', PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION, false];
    $checks[] = ['mysqli extension', extension_loaded('mysqli'), 'required for the database', false];
    $checks[] = ['fileinfo extension', class_exists('finfo'), 'used to validate uploads', false];
    $checks[] = ['openssl extension', extension_loaded('openssl'), 'used for password hashing', false];
    $checks[] = ['mbstring extension', extension_loaded('mbstring'), 'used for string handling', false];
    $checks[] = ['session support', function_exists('session_start'), 'used for login & security tokens', false];
    foreach (['assets/audio', 'assets/images', 'assets/uploads'] as $d) {
        $checks[] = [$d . ' writable', is_writable(ROOT_PATH . '/' . $d), 'upload directory must be writable by the web server', false];
    }
    $checks[] = ['web root writable', is_writable(ROOT_PATH), 'needed to create install.lock', false];
    $configTarget = configfile();
    $checks[] = [basename($configTarget) . ' writable', is_writable($configTarget), 'needed to save mail settings', false];
    $checks[] = ['upload_max_filesize >= 100M', (int)ini_get('upload_max_filesize') >= 100, ini_get('upload_max_filesize') . ' current', false];
    $checks[] = ['post_max_size >= 100M', (int)ini_get('post_max_size') >= 100, ini_get('post_max_size') . ' current', false];
    $checks[] = ['.htaccess present (pretty URLs)', file_exists(ROOT_PATH . '/.htaccess'), 'recommended, can be added later', true];
    return $checks;
}

/* ====================================================================== */
/*  2. Request state                                                    */
/* ====================================================================== */

header('X-Robots-Tag: noindex');

$lock_file = ROOT_PATH . '/install.lock';
$installed = file_exists($lock_file);

$step = max(1, min(6, (int)($_GET['step'] ?? 1)));
$done = false;
$error = $notice = $success = '';

/* ---------------- working state ---------------- */
/* Defaults for the current step, pre-filled from the session the previous step
   wrote, falling back to the DB_* / MAIL_* constants in app/config.php. Both
   renders them) read these, so they are set up once here. */

$cfg = conf();
$mail_cfg = mailconf();
$db_ready = false;
$missing_tables = [];

/* ====================================================================== */
/*  3. Step handling (POST handlers + re-entry state)                    */
/* ====================================================================== */
/*
   Installer step handling.

   Everything the wizard does in response to a POST, plus the read-only state
   that the next step needs to render. Runs in this file's scope, so the
   step number and the $error / $notice / $success messages it sets are the ones
   section 4 renders.

   Two groups:
   - the POST handlers for steps 3, 4 and 5 (database, mail, admin account)
   - read-only re-entry handling, so returning to a half-finished install
   still shows the right step

   Every write the installer can perform (CREATE DATABASE, applying the schema,
   rewriting app/config.php, creating the admin, writing install.lock) is behind
   one of those POST branches, so a GET can only ever render.

   Part of installer.php, not a separate entry point.
   */

/* ---------------- POST handling ---------------- */
if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf($_POST['csrf_token'] ?? '')) {

    if ($step === 3) {
        $c = [
            'host' => trim($_POST['db_host'] ?? ''),
            'user' => trim($_POST['db_user'] ?? ''),
            'pass' => (string)($_POST['db_pass'] ?? ''),
            'name' => trim($_POST['db_name'] ?? ''),
        ];
        if ($c['host'] === '' || $c['user'] === '') {
            $error = 'Database host and username are required.';
        } elseif (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $c['name'])) {
            $error = 'Database name may only contain letters, numbers and underscores.';
        } else {
            [$con, $err] = connect($c, false);
            if ($err) {
                $error = 'Could not connect to the database server: ' . $err;
            } else {
                if (!exists($con, $c['name'])) {
                    $con->query("CREATE DATABASE `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    if ($con->errno !== 0) {
                        $error = 'Could not create the database: ' . $con->error;
                    }
                }
                if (!$error) {
                    [$con, $err] = connect($c, true);
                    if ($err) {
                        $error = 'Could not connect to the database: ' . $err;
                    } else {
                        $missing_tables = missing($con);
                        if (count($missing_tables) === 5) {
                            $sql_err = schema($con);
                            if ($sql_err) {
                                $error = $sql_err;
                            } else {
                                $missing_tables = missing($con);
                                $success = 'Database tables were created successfully.';
                            }
                        } elseif (count($missing_tables) > 0) {
                            $notice = 'Some tables are missing (' . implode(', ', $missing_tables) . '). The schema is only applied to a fully empty database to avoid data loss — restore the missing tables first, or set up a fresh database.';
                        } else {
                            $notice = 'All tables already exist — the schema is ready.';
                        }

                        $db_ready = !$error && count($missing_tables) === 0;
                        if ($db_ready) {
                            $_SESSION['install']['db'] = $c;
                            $cfg = $c;
                            header('Location: ?step=4');
                            exit;
                        }
                    }
                }
            }
        }
    }

if ($step === 4) {
        if (empty($_SESSION['install']['db']) || !ready($cfg)) {
            $error = 'The database is not configured yet. Complete the database step before configuring email.';
        } else {
            $m = [
                'host'      => trim($_POST['mail_host'] ?? ''),
                'port'      => (int)($_POST['mail_port'] ?? 0),
                'from'      => trim($_POST['mail_from'] ?? ''),
                'from_name' => trim($_POST['mail_from_name'] ?? ''),
                'user'      => trim($_POST['mail_user'] ?? ''),
                'admin'     => trim($_POST['mail_admin'] ?? ''),
            ];
            $pass = (string)($_POST['mail_pass'] ?? '');
            if ($m['host'] === '') {
                $error = 'SMTP host is required.';
            } elseif ($m['port'] < 1 || $m['port'] > 65535) {
                $error = 'SMTP port must be between 1 and 65535.';
            } elseif (!valid($m['from'])) {
                $error = 'Please enter a valid "From" email address.';
            } elseif ($m['from_name'] === '' || mb_strlen($m['from_name']) > 120) {
                $error = 'From name must be between 1 and 120 characters.';
            } elseif (!valid($m['admin'])) {
                $error = 'Please enter a valid admin notifications email address.';
            } else {
                if ($pass === '') {
                    $pass = MAIL_PASS; // leave blank = keep the current effective password
                }
                $mail_cfg = $m + ['pass' => $pass];
                $_SESSION['install']['mail'] = $mail_cfg;
                $write_err = mailwrite($m, $pass);
                if ($write_err) {
                    $error = $write_err;
                } else {
                    header('Location: ?step=5');
                    exit;
                }
            }
        }
    }

    if ($step === 5) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $country = trim($_POST['country'] ?? '');

        if (empty($_SESSION['install']['db']) || !ready($cfg)) {
            $error = 'The database is not configured yet. Complete step 2 before creating the administrator account.';
        } elseif ($name === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (!valid($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $error = 'Your name must be between 2 and 100 characters.';
        } elseif (strlen($password) < MIN_PASSWORD_LENGTH || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $error = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters and contain both letters and numbers.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!valid($country, 'country')) {
            $error = 'Please select a valid country.';
        } else {
            [$con, $err] = connect($cfg, true);
            if ($err) {
                $error = 'Database connection failed: ' . $err;
            } else {
                $stmt = $con->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $taken = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($taken) {
                    $error = 'A user with this email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $country_val = $country === '' ? null : $country;
                    $stmt = $con->prepare("INSERT INTO users (name, email, password, role, country, status, created) VALUES (?, ?, ?, 'admin', ?, 'active', NOW())");
                    $stmt->bind_param('ssss', $name, $email, $hash, $country_val);
                    if ($stmt->execute()) {
                        $done = true;
                        $lock_ok = @file_put_contents($lock_file, 'Installed: ' . date('c') . PHP_EOL) !== false;
                        /*
                         * install.lock now exists, so the render at the bottom
                         * of this file takes the "already installed" branch
                         * instead of the Finish page — step 6 was unreachable.
                         * This flag marks the one browser session that just
                         * installed the site, so it gets to see the Finish page
                         * exactly once. Nothing else is granted: it carries no
                         * privileges, it cannot re-run any step, and the next
                         * request at the bottom clears it.
                         */
                        $_SESSION['install_done'] = true;
                        unset($_SESSION['install']);
                        $notice = $lock_ok
                            ? 'The administrator account has been created and the installer is now locked.'
                            : 'The administrator account was created, but install.lock could not be written — delete installer.php to secure the site.';
                    } else {
                        $error = 'Could not create the administrator account: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

/* -------- stateful re-entry: remember a DB the user just configured (GET only, never after a POST) -------- */
if (!$error && !$done && !$installed && !$db_ready && $_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_SESSION['install']['db'])) {
    $cfg = $_SESSION['install']['db'];
    if (ready($cfg)) {
        $db_ready = true;
        $notice = 'The database from the previous step is ready — continue to the mail settings.';
    }
}

/* -------- post-install: allow deleting installer.php from the UI -------- */
$deleted_installer = false;
if (($installed || $done) && ($_POST['action'] ?? '') === 'delete_installer' && csrf($_POST['csrf_token'] ?? '')) {
    if (@unlink(ROOT_PATH . '/installer.php')) {
        $deleted_installer = true;
        $notice = 'installer.php has been deleted. The installer can no longer be reached.';
    } else {
        $error = 'Could not delete installer.php automatically (not writable by the web server). Remove the file manually.';
    }
}

/* -------- pre-filled defaults come from conf(): the DB_* constants when not yet configured -------- */
$checks = checks();
$all_pass = true;
foreach ($checks as $c) { if (!$c[0] && !$c[3]) $all_pass = false; }
$admin_exists = false;
if (!$error && !$done && !$installed) {
    [$con, $err] = connect($cfg, true);
    if (!$err) {
        $r = $con->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
        if ($r) $admin_exists = (int)$r->fetch_assoc()['c'] > 0;
        $con->close();
    }
}

/* ====================================================================== */
/*  4. Markup for all six steps                                         */
/* ====================================================================== */
/*
   Installer markup.

   The wizard's HTML: the step bar, the "already installed" / "done" cards and
   the body of each of the six steps. Pure presentation - it only reads the
   variables sections 2 and 3 have already set.

   Part of installer.php, not a separate entry point.
   */

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Install <?= SITE_NAME ?> | Setup Wizard</title>
<?php require __DIR__ . '/app/includes/partials/css-links.php'; ?>
<style>
    .install-wrap { max-width: 760px; margin: 3rem auto 4rem; }
    .install-step { display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .install-step span { background: var(--purple-soft, #efe9ff); color: #6b2bbf; border-radius: 999px; padding: .25rem .8rem; font-size: .8rem; font-weight: 600; }
    .install-step span.active { background: var(--gold, #f0b429); color: #1a1030; }
    .check-row { display: flex; align-items: center; gap: .75rem; padding: .45rem 0; border-bottom: 1px dashed rgba(0,0,0,.08); }
    .check-row:last-child { border-bottom: 0; }
    .check-row .fa-check { color: var(--bs-success); }
    .check-row .fa-triangle-exclamation { color: var(--bs-warning); }
    .check-row .fa-xmark { color: var(--bs-danger); }
</style>
</head>
<body style="background:var(--navy);min-height:100vh">
<div class="install-wrap px-3">
    <div class="text-center mb-4">
        <?= icon('music', 'text-gold fs-1') ?>
        <h1 class="fw-bold text-white mt-2 mb-0"><?= SITE_NAME ?> Installer</h1>
        <p class="text-white-50">Step-by-step setup wizard</p>
    </div>

    <?php if ($error) { ?><div class="alert alert-danger"><?= icon('triangle-exclamation', 'me-2') ?><?= e($error) ?></div><?php } ?>
    <?php if ($notice) { ?><div class="alert alert-info"><?= icon('circle-info', 'me-2') ?><?= e($notice) ?></div><?php } ?>
    <?php if ($success) { ?><div class="alert alert-success"><?= icon('circle-check', 'me-2') ?><?= e($success) ?></div><?php } ?>

    <?php if ($installed && empty($_SESSION['install_done'])) { ?>
        <div class="card border-0 shadow" style="border-radius:16px"><div class="card-body p-4 p-md-5 text-center">
            <?= icon('circle-check', 'text-success fs-1 mb-3') ?>
            <h4 class="fw-bold">Already installed</h4>
            <p class="text-muted">A lock file (<code>install.lock</code>) exists, so the installer is disabled to prevent a takeover.</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a class="btn btn-gold" href="<?= e(url('login')) ?>">Go to Admin Login</a>
                <a class="btn btn-outline-secondary" href="<?= e(url()) ?>">Visit Site</a>
            </div>
            <?php if (file_exists(ROOT_PATH . '/installer.php')) { ?>
                <form method="POST" class="mt-3" onsubmit="return confirm('Delete installer.php now? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                    <input type="hidden" name="action" value="delete_installer">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash-can', 'me-1') ?>Delete installer.php</button>
                </form>
            <?php } ?>
            <p class="small text-muted mt-3 mb-0">To re-run the installer, delete <code>install.lock</code> (and then remove <code>installer.php</code> once finished).</p>
        </div></div>
    <?php } elseif ($done) { ?>
        <div class="card border-0 shadow" style="border-radius:16px"><div class="card-body p-4 p-md-5 text-center">
            <?= icon('check-double', 'text-gold fs-1 mb-3') ?>
            <h4 class="fw-bold">Installation complete</h4>
            <p class="text-muted"><?= e($notice) ?></p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
<a class="btn btn-gold" href="<?= e(url('login')) ?>">Login as Administrator</a>
                <a class="btn btn-outline-secondary" href="<?= e(url()) ?>">Visit Site</a>
            </div>
            <div class="d-flex justify-content-center gap-2 flex-wrap mt-3">
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete installer.php now? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                    <input type="hidden" name="action" value="delete_installer">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash-can', 'me-1') ?>Delete installer.php now</button>
                </form>
            </div>
            <p class="small text-muted mt-3 mb-0">
                Security reminder: delete <code>installer.php</code> from the server, or keep the <code>install.lock</code> file in place. Put your real database and mail credentials in <code>app/config.local.php</code> (it is gitignored) rather than <code>app/config.php</code>, and enable HTTPS.
            </p>
        </div></div>
    <?php } else { ?>

    <div class="install-step">
        <span class="<?= $step === 1 ? 'active' : '' ?>">1. Welcome</span>
        <span class="<?= $step === 2 ? 'active' : '' ?>">2. Requirements</span>
        <span class="<?= $step === 3 ? 'active' : '' ?>">3. Database</span>
        <span class="<?= $step === 4 ? 'active' : '' ?>">4. Mail (SMTP)</span>
        <span class="<?= $step === 5 ? 'active' : '' ?>">5. Admin account</span>
        <span class="<?= $step === 6 ? 'active' : '' ?>">6. Finish</span>
    </div>

    <div class="card border-0 shadow" style="border-radius:16px"><div class="card-body p-4 p-md-5">

    <?php if ($step === 1) { ?>
        <div class="text-center py-3">
            <?= icon('book-open', 'text-gold fs-1 mb-3') ?>
            <h5 class="fw-semibold mb-2">Welcome to <?= SITE_NAME ?></h5>
            <p class="text-muted mb-4">This wizard will get the platform up and running in a few minutes.</p>
            <div class="text-start mb-4">
                <div class="d-flex gap-3 mb-3">
                    <?= icon('circle-check', 'text-gold fs-4') ?>
                    <div>
                        <div class="fw-semibold small">1. Server requirements</div>
                        <div class="text-muted small">Checks PHP version, extensions, upload limits and writable folders.</div>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <?= icon('database', 'text-gold fs-4') ?>
                    <div>
                        <div class="fw-semibold small">2. Database</div>
                        <div class="text-muted small">Connects to MySQL/MariaDB, optionally creates the database and installs the schema.</div>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <?= icon('envelope', 'text-gold fs-4') ?>
                    <div>
                        <div class="fw-semibold small">3. Mail (SMTP)</div>
                        <div class="text-muted small">Outgoing email host, port and sender address — password optional (current one is kept when blank).</div>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <?= icon('user-shield', 'text-gold fs-4') ?>
                    <div>
                        <div class="fw-semibold small">4. Administrator account</div>
                        <div class="text-muted small">Creates the site owner, then locks the installer to prevent a takeover.</div>
                    </div>
                </div>
            </div>
            <div class="alert alert-warning small text-start">
                <?= icon('lightbulb', 'me-2') ?>Afterwards, delete <code>installer.php</code> from the server. Re-running the wizard never modifies existing data — the schema is only applied to a fully empty database.
            </div>
            <a class="btn btn-gold w-100" href="?step=2">Start Installation <?= icon('arrow-right', 'ms-1') ?></a>
        </div>

    <?php } elseif ($step === 2) { ?>
        <h5 class="fw-semibold mb-3">Server requirements check</h5>
        <?php
        $is_https = strtolower((string)($_SERVER['HTTPS'] ?? 'off')) === 'on'
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        ?>
        <div class="alert <?= $is_https ? 'alert-success' : 'alert-warning' ?> small">
            <?= icon($is_https ? 'lock' : 'triangle-exclamation', 'me-2') ?>
            You are installing over <strong><?= $is_https ? 'https' : 'http' ?></strong>.
            <?php if (!$is_https) { ?>Enable HTTPS before going live.<?php } ?>
            The site URL is currently <code><?= BASE_URL ?></code>. On a live server set <code>BASE_URL</code> and <code>GOSPELZORA_HOSTS</code> in <code>app/config.local.php</code> so it matches your real domain.
        </div>
        <?php foreach ($checks as $c) { $ok = $c[1]; $optional = $c[3]; ?>
        <div class="check-row">
            <?= icon($ok ? 'check' : ($optional ? 'triangle-exclamation' : 'xmark'), $ok ? '' : ($optional ? 'text-warning' : '')) ?>
            <div class="flex-grow-1">
                <div class="fw-semibold small"><?= e($c[0]) ?></div>
                <div class="text-muted small"><?= e($c[2]) ?></div>
            </div>
            <span class="badge bg-<?= $ok ? 'success' : ($optional ? 'warning' : 'danger') ?>"><?= $ok ? 'OK' : ($optional ? 'Warn' : 'Fail') ?></span>
        </div>
        <?php } ?>
        <hr>
        <?php if ($all_pass) { ?>
            <a class="btn btn-gold w-100" href="?step=3">Continue to Database Setup <?= icon('arrow-right', 'ms-1') ?></a>
        <?php } else { ?>
            <div class="alert alert-danger mb-3"><?= icon('triangle-exclamation', 'me-2') ?>Fix the failing items above before continuing.</div>
            <a class="btn btn-outline-secondary w-100" href="?step=2">Re-run the check</a>
        <?php } ?>

    <?php } elseif ($step === 3) { ?>
        <h5 class="fw-semibold mb-3">Database setup</h5>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label small">Database host</label>
                    <input type="text" name="db_host" class="form-control" value="<?= e($cfg['host']) ?>" required></div>
                <div class="col-md-6"><label class="form-label small">Database name</label>
                    <input type="text" name="db_name" class="form-control" value="<?= e($cfg['name']) ?>" required pattern="[A-Za-z0-9_]{1,64}"
                           title="Letters, numbers and underscores only"></div>
                <div class="col-md-6"><label class="form-label small">Database username</label>
                    <input type="text" name="db_user" class="form-control" value="<?= e($cfg['user']) ?>" required></div>
                <div class="col-md-6"><label class="form-label small">Database password</label>
                    <input type="password" name="db_pass" class="form-control" autocomplete="new-password" placeholder="Re-enter the database password"></div>
            </div>
            <?php if ($db_ready) { ?>
                <input type="hidden" name="db_ready" value="1">
                <a class="btn btn-gold w-100 mt-4" href="?step=4">Continue to Mail Settings <?= icon('arrow-right', 'ms-1') ?></a>
            <?php } else { ?>
                <button type="submit" class="btn btn-gold w-100 mt-4">Set Up Database</button>
            <?php } ?>
        </form>
        <p class="text-muted small mt-3 mb-0">
            <?= icon('circle-info', 'me-1') ?>
            The database is created automatically if it does not exist, and the schema is installed automatically on a fully empty database (existing data is never modified). Recommended for production: use a dedicated database user whose permissions are limited to this database (not <code>root</code>).
        </p>

    <?php } elseif ($step === 4) { ?>
        <?php if (!$db_ready) { ?>
            <div class="text-center py-4">
                <?= icon('database', 'text-muted fs-1 mb-3') ?>
                <h5 class="fw-semibold">Database setup required</h5>
                <p class="text-muted">Complete the database step before configuring outgoing mail.</p>
                <a class="btn btn-gold w-100" href="?step=3">Go to Database Setup <?= icon('arrow-right', 'ms-1') ?></a>
            </div>
        <?php } else { ?>
        <h5 class="fw-semibold mb-3">Mail settings (SMTP)</h5>
        <p class="text-muted small mb-3">
            <?= icon('circle-info', 'me-1') ?>
            Fields are pre-filled from the current config and can be adjusted. The password is never pre-filled — leaving it blank keeps the current one. An empty username means the SMTP server needs no authentication (typical for a local relay).
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label small">SMTP host</label>
                    <input type="text" name="mail_host" class="form-control" value="<?= e($mail_cfg['host']) ?>" required></div>
                <div class="col-md-4"><label class="form-label small">SMTP port</label>
                    <input type="number" name="mail_port" class="form-control" value="<?= (int)$mail_cfg['port'] ?>" required min="1" max="65535"></div>
                <div class="col-md-6"><label class="form-label small">From email address</label>
                    <input type="email" name="mail_from" class="form-control" value="<?= e($mail_cfg['from']) ?>" required></div>
                <div class="col-md-6"><label class="form-label small">From name</label>
                    <input type="text" name="mail_from_name" class="form-control" value="<?= e($mail_cfg['from_name']) ?>" maxlength="120"></div>
                <div class="col-md-6"><label class="form-label small">SMTP username <span class="text-muted">(optional)</span></label>
                    <input type="text" name="mail_user" class="form-control" value="<?= e($mail_cfg['user']) ?>" autocomplete="off"></div>
                <div class="col-md-6"><label class="form-label small">SMTP password <span class="text-muted">(blank = keep current)</span></label>
                    <input type="password" name="mail_pass" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep the existing password"></div>
                <div class="col-md-12"><label class="form-label small">Admin notifications email <span class="text-muted">(receives contact messages)</span></label>
                    <input type="email" name="mail_admin" class="form-control" value="<?= e($mail_cfg['admin']) ?>" required></div>
            </div>
            <button type="submit" class="btn btn-gold w-100 mt-4">Save Mail Settings &amp; Continue <?= icon('arrow-right', 'ms-1') ?></button>
            <a class="btn btn-link w-100 mt-2" href="?step=3">&larr; Back to Database Setup</a>
        </form>
        <p class="text-muted small mt-3 mb-0">
            <?= icon('lightbulb', 'me-1') ?>
            Emails (account verification, password reset, contact notifications) are best-effort: if a send fails, the user's action still succeeds.
        </p>
        <?php } ?>

    <?php } elseif ($step === 5) { ?>
        <?php if (!$db_ready) { ?>
            <div class="text-center py-4">
                <?= icon('database', 'text-muted fs-1 mb-3') ?>
                <h5 class="fw-semibold">Database setup required</h5>
                <p class="text-muted">Complete the database step before creating the administrator account.</p>
                <a class="btn btn-gold w-100" href="?step=3">Go to Database Setup <?= icon('arrow-right', 'ms-1') ?></a>
            </div>
        <?php } else { ?>
        <h5 class="fw-semibold mb-3">Create the administrator account</h5>
        <?php if ($admin_exists) { ?>
            <div class="alert alert-warning"><?= icon('circle-info', 'me-2') ?>An administrator account already exists in this database. You can still add another admin below, or <a href="<?= e(url('login')) ?>" class="alert-link">log in</a> instead.</div>
        <?php } ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label small">Full name</label>
                    <input type="text" name="name" class="form-control" required maxlength="100" value="<?= e($_POST['name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Email address</label>
                    <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Password (min <?= (int)MIN_PASSWORD_LENGTH ?> chars, letters &amp; numbers)</label>
                    <input type="password" name="password" class="form-control" required minlength="<?= (int)MIN_PASSWORD_LENGTH ?>" autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label small">Confirm password</label>
                    <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label small">Country (optional)</label>
                    <select name="country" class="form-select"><?php
                    $country_selected = $_POST['country'] ?? '';
                    $country_required = false;
                    require __DIR__ . '/app/includes/partials/country-options.php';
                    ?></select></div>
            </div>
            <button type="submit" class="btn btn-gold w-100 mt-4">Create Administrator &amp; Finish</button>
            <a class="btn btn-link w-100 mt-2" href="?step=4">&larr; Back to Mail Settings</a>
        </form>
        <?php } ?>

    <?php } elseif ($step === 6) { ?>
        <?php /* One-shot: having shown Finish, drop the flag so that reloading
                 this URL lands on the "Already installed" page again. */ ?>
        <?php unset($_SESSION['install_done']); ?>
        <h5 class="fw-semibold mb-3">Finish installing</h5>
        <p class="text-muted">The installation is ready. Log in with the administrator account you created.</p>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-gold" href="<?= e(url('login')) ?>">Login as Administrator</a>
            <a class="btn btn-outline-secondary" href="<?= e(url()) ?>">Visit Site</a>
        </div>

    <?php } ?>

    </div></div>

    <p class="text-center text-white-50 small mt-4 mb-0">
        <?= SITE_NAME ?> &middot; <?= SITE_TAGLINE ?> — delete <code>installer.php</code> after installation completes.
    </p>

    <?php } ?>
</div>
</body>
</html>