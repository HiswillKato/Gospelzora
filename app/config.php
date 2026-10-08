<?php
/**
 * Zora — configuration.
 *
 * THIS IS THE ONE CONFIG FILE. There is no app/config.local.php any more: the
 * settings for this machine live in section 2 below, marked "this machine".
 * Everything else in this file is a fallback default, written as
 * `defined('X') || define('X', …)`, so the values in section 2 win simply by
 * being defined first. That ordering is load-bearing: a value defined here
 * after its default would collide with an already-defined constant, emit a
 * warning and be ignored.
 *
 * If app/config.local.php ever comes back it is still honoured, and still wins,
 * because it is required before anything else is defined — see section 2.
 */

/* ---------------------------------------------------------------------------
 * 1. Paths that the override layer may build on
 *
 * ROOT_PATH is derived, so moving the tree needs no code change. Only
 * .htaccess's literal `RewriteBase /` still pins the project to the document
 * root. The three upload directories come further down, because the local file
 * is allowed to point them somewhere else.
 * ------------------------------------------------------------------------- */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

/* ---------------------------------------------------------------------------
 * 2. Settings for THIS machine
 *
 * Defined first, before every default below, so they take effect. This is the
 * block that used to live in app/config.local.php.
 *
 * The password is in this file rather than an untracked override, so treat it
 * as a secret: app/ is denied by both .htaccess and the vhost, and the file is
 * mode 0640 (group www-data, so Apache reads it and nobody else does).
 * ------------------------------------------------------------------------- */

/* 'production' also arms the deployment self-check in app/bootstrap.php, which
   turns an unpinned BASE_URL into a visible 503 card instead of a site that
   renders unstyled. */
define('APP_ENV', 'production');

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'zora');

/* PINNED, because the vhost terminates TLS and redirects plain http, so
   https://zora/ is the one true address. With this set the Host header is never
   consulted, which is the reliable posture: it does not depend on the vhost
   being right. */
define('BASE_URL', 'https://zora/');

define('GOSPELZORA_HOSTS', ['zora', 'www.zora', 'localhost', '127.0.0.1']);

define('MAIL_HOST', '127.0.0.1');
define('MAIL_PORT', 25);
define('MAIL_FROM', 'noreply@zora.local');
define('MAIL_FROM_NAME', 'Gospelzora');
define('MAIL_USER', '');
define('MAIL_PASS', '');
define('MAIL_ADMIN', 'admin@zora.local');
define('MAIL_SUBJECT_PREFIX', '[Gospelzora] ');
define('MAIL_VERIFY_EXPIRY_HOURS', 48);
define('MAIL_RESET_EXPIRY_HOURS', 1);

/* ---------------------------------------------------------------------------
 * 2a. Optional override file
 *
 * Gone on this machine, and that is the normal case. The guard is kept so that
 * dropping a config.local.php back in still works and still wins: it is required
 * here, before any default below, and it guards itself with the CONFIG_ACTIVE
 * sentinel rather than a bare exit — it is require()d from here, so an
 * unconditional deny would kill every request with a 404 instead of just
 * refusing a direct request for that one file.
 * ------------------------------------------------------------------------- */

define('CONFIG_ACTIVE', true);
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

/*
 * Whether the site URL was pinned above (or by the local file) rather than
 * derived from the Host header on every request. Captured here, before the
 * defaults below run, because once those have executed BASE_URL is defined
 * either way and the evidence is gone.
 */
$zoraPinned = defined('BASE_URL');

/* ---------------------------------------------------------------------------
 * 3. Upload directories
 *
 * The only three directories the web server needs to write. Leave them unset in
 * the local file to use these in-tree defaults; point them outside the docroot
 * to stop uploads being web-writable — but then Apache must be given an Alias to
 * serve them or audio and artwork 404.
 * ------------------------------------------------------------------------- */

defined('AUDIO_PATH')   || define('AUDIO_PATH', ROOT_PATH . '/assets/audio');
defined('IMAGE_PATH')   || define('IMAGE_PATH', ROOT_PATH . '/assets/images');
defined('ARTWORK_PATH') || define('ARTWORK_PATH', IMAGE_PATH . '/artwork');
defined('ASSETS_PATH')  || define('ASSETS_PATH', ROOT_PATH . '/assets');

/* ---------------------------------------------------------------------------
 * 4. Environment
 * ------------------------------------------------------------------------- */

defined('APP_ENV') || define('APP_ENV', 'development');
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Africa/Kampala');

/* Consulted only when BASE_URL is NOT set. Keep in step with ServerName/ServerAlias. */
defined('GOSPELZORA_HOSTS') || define('GOSPELZORA_HOSTS', []);

/*
 * Reverse-proxy addresses, as literal IPs — never a range or a hostname. If a
 * CDN or load balancer terminates TLS in front of Apache and its address is not
 * listed here, the app believes every request arrived over http: the session
 * cookie loses its Secure flag and logins appear to break, and
 * upgrade-insecure-requests is dropped from the CSP.
 */
defined('TRUSTED_PROXY_IPS') || define('TRUSTED_PROXY_IPS', []);

/* Only needed when logins fail silently because session.save_path is not writable. */
defined('SESSION_SAVE_PATH') || define('SESSION_SAVE_PATH', '');

/* -----------------------------------------------------------
 * The request's scheme, then the public URL
 *
 * Both are needed before anything else reads them, and the scheme has to be
 * settled first because it decides whether the detected URL is http:// or
 * https://.
 *
 * $_SERVER['HTTPS'] is trustworthy because Apache set it. When a trusted proxy
 * terminated TLS the forwarded header is the only evidence there is — and because
 * that header is client-controllable everywhere else, it is believed ONLY for the
 * exact addresses in TRUSTED_PROXY_IPS. Getting this wrong either way is
 * visible: believe a forged header and a plain-http site hands out a Secure
 * session cookie, so login appears to break; ignore a real proxy and production
 * loses the cookie's Secure flag.
 * --------------------------------------------------------- */

$zoraRemote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$zoraTrusted = TRUSTED_PROXY_IPS !== []
    && in_array($zoraRemote, array_map('strval', (array)TRUSTED_PROXY_IPS), true);

$zoraHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || ($zoraTrusted && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

/*
 * The public URL. When it is still unset — not pinned in config.local.php —
 * derive it from this request, so the site works under any name without being
 * edited: scheme plus Host header.
 *
 * The Host header is attacker-supplied, so it is filtered down to something that
 * can only be a hostname, and when GOSPELZORA_HOSTS is non-empty the host must
 * be on that list. Both checks matter: an unpinned BASE_URL is interpolated
 * into every asset URL and every canonical link on the page.
 *
 * PIN IT ON A LIVE SERVER. If the real domain is missing from GOSPELZORA_HOSTS
 * the fallback below silently wins, every <link>/<script> points at the
 * visitor's own machine, and the site renders as unstyled text with no error
 * anywhere. The production self-check in app/bootstrap.php turns exactly that
 * case into a visible 503 card instead of a plausible-looking broken page, but
 * pinning it in config.local.php is the actual fix.
 */
if (!defined('BASE_URL') && PHP_SAPI !== 'cli') {
    $zoraHost = trim(preg_replace('~:\d{1,5}$~', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $zoraHost = trim($zoraHost, '[]');

    $zoraIsHostname = $zoraHost !== ''
        && preg_match('~^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*$~', $zoraHost);

    $zoraAllowed = (array)GOSPELZORA_HOSTS;
    $zoraKnown = $zoraAllowed === [] || in_array($zoraHost, array_map('strval', $zoraAllowed), true);

    if ($zoraIsHostname && $zoraKnown) {
        define('BASE_URL', ($zoraHttps ? 'https' : 'http') . '://' . $zoraHost . '/');
    }
}

defined('BASE_URL') || define('BASE_URL', 'http://localhost/');

/* No request to read a scheme from under the CLI, so take it from the URL. */
if (PHP_SAPI === 'cli') {
    $zoraHttps = strtolower((string)BASE_URL) === 'https://';
}

if ($zoraPinned) {
    define('BASE_URL_LOCAL_OVERRIDE', true);
}

/* ---------------------------------------------------------------------------
 * 5. Database
 * ------------------------------------------------------------------------- */

defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_USER') || define('DB_USER', 'gospelzora');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_NAME') || define('DB_NAME', 'gospelzora');

/* ---------------------------------------------------------------------------
 * 6. Site copy and limits
 *
 * SITE_NAME comes before the mail block because MAIL_SUBJECT_PREFIX is built
 * from it.
 * ------------------------------------------------------------------------- */

defined('SITE_NAME') || define('SITE_NAME', 'Gospelzora');
defined('SITE_TAGLINE') || define('SITE_TAGLINE', 'Transforming Lives Through Worship');
defined('SITE_LEGAL_UPDATED') || define('SITE_LEGAL_UPDATED', '1 September 2026');

defined('ITEMS_PER_PAGE') || define('ITEMS_PER_PAGE', 12);
defined('MIN_PASSWORD_LENGTH') || define('MIN_PASSWORD_LENGTH', 8);

/*
 * Login throttle. The counters live in $_SESSION only, so these reset on any
 * session or cookie reset — the documented session-only debt in AGENTS.md. The
 * e-mail threshold is deliberately lower than the per-IP one, because a shared
 * address (a family, an office, a campus) should not lock out everyone on it
 * after one person forgets a password.
 */
defined('LOGIN_MAX_ATTEMPTS')    || define('LOGIN_MAX_ATTEMPTS', 5);
defined('LOGIN_MAX_ATTEMPTS_IP') || define('LOGIN_MAX_ATTEMPTS_IP', 12);
defined('LOGIN_LOCK_MINUTES')    || define('LOGIN_LOCK_MINUTES', 15);

/* ---------------------------------------------------------------------------
 * 7. Email
 *
 * ONE CONTIGUOUS BLOCK, IN THIS ORDER. The installer's mailwrite() matches from
 * define('MAIL_HOST' through define('MAIL_RESET_EXPIRY_HOURS' and replaces the
 * whole run in one preg_replace; split these ten defines apart and the wizard
 * appends a second block instead of replacing the first one.
 *
 * The committed defaults talk to a local Postfix on loopback, which delivers
 * nowhere useful on a live server. MAIL_PASS is only needed when MAIL_USER is
 * set — leave MAIL_USER empty for an unauthenticated relay on localhost.
 * ------------------------------------------------------------------------- */

defined('MAIL_HOST')     || define('MAIL_HOST', '127.0.0.1');
defined('MAIL_PORT')     || define('MAIL_PORT', 25);
defined('MAIL_FROM')     || define('MAIL_FROM', 'noreply@mail.local');
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', SITE_NAME);
defined('MAIL_USER')     || define('MAIL_USER', '');
defined('MAIL_PASS')     || define('MAIL_PASS', '');
defined('MAIL_ADMIN')    || define('MAIL_ADMIN', 'hiswill@mail.local');
defined('MAIL_SUBJECT_PREFIX')       || define('MAIL_SUBJECT_PREFIX', '[' . SITE_NAME . '] ');
defined('MAIL_VERIFY_EXPIRY_HOURS')  || define('MAIL_VERIFY_EXPIRY_HOURS', 48);
defined('MAIL_RESET_EXPIRY_HOURS')   || define('MAIL_RESET_EXPIRY_HOURS', 1);

/* ---------------------------------------------------------------------------
 * 8. Runtime: timezone, session, security headers
 * ------------------------------------------------------------------------- */

date_default_timezone_set(APP_TIMEZONE);

/* $zoraHttps was settled above, before BASE_URL was resolved. */

if (session_status() === PHP_SESSION_NONE) {
    if (SESSION_SAVE_PATH !== '' && is_dir(SESSION_SAVE_PATH)) {
        session_save_path(SESSION_SAVE_PATH);
    }

    session_name('GOSPELZORA');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $zoraHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    /*
     * A self-hosted site needs no external script at all: Bootstrap, Font Awesome,
     * app.js and the webfonts are all committed under assets/. 'unsafe-inline' is
     * required for the handful of inline <script> blocks the shells and partials
     * emit; 'unsafe-eval' is deliberately NOT granted.
     */
    $csp = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "media-src 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'";

    if ($zoraHttps) {
        $csp .= '; upgrade-insecure-requests';
    }

    header('Content-Security-Policy: ' . $csp);
}

/* ---------------------------------------------------------------------------
 * 9. Countries
 *
 * ISO 3166-1 alpha-2 => English name. The single source of truth: the country
 * partial, country() and valid('country') all read this one place, so a new
 * country is added here and nowhere else.
 * ------------------------------------------------------------------------- */

const COUNTRY_LIST = [
    'AF' => 'Afghanistan', 'AX' => 'Aland Islands', 'AL' => 'Albania', 'DZ' => 'Algeria',
    'AS' => 'American Samoa', 'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla',
    'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba',
    'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
    'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
    'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda',
    'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BQ' => 'Bonaire, Saint Eustatius and Saba', 'BA' => 'Bosnia and Herzegovina',
    'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory',
    'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
    'CV' => 'Cabo Verde', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada',
    'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile',
    'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands', 'CO' => 'Colombia',
    'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (Democratic Republic)', 'CK' => 'Cook Islands',
    'CR' => 'Costa Rica', 'CI' => "Cote d'Ivoire", 'HR' => 'Croatia', 'CU' => 'Cuba',
    'CW' => 'Curacao', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark',
    'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador',
    'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea',
    'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands',
    'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France',
    'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'TF' => 'French Southern Territories', 'GA' => 'Gabon',
    'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana',
    'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada',
    'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey',
    'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti',
    'HM' => 'Heard Island and McDonald Islands', 'VA' => 'Holy See', 'HN' => 'Honduras',
    'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India',
    'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland',
    'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica',
    'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan',
    'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea (North)', 'KR' => 'Korea (South)',
    'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia',
    'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya',
    'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao',
    'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives',
    'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique',
    'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico',
    'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia',
    'ME' => 'Montenegro', 'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique',
    'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal',
    'NL' => 'Netherlands', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua',
    'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island',
    'MK' => 'North Macedonia', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman',
    'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama',
    'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
    'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico',
    'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russian Federation',
    'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis',
    'LC' => 'Saint Lucia', 'MF' => 'Saint Martin', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines',
    'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia',
    'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone',
    'SG' => 'Singapore', 'SX' => 'Sint Maarten', 'SK' => 'Slovakia', 'SI' => 'Slovenia',
    'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia and the South Sandwich Islands',
    'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan',
    'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen', 'SE' => 'Sweden', 'CH' => 'Switzerland',
    'SY' => 'Syrian Arab Republic', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania',
    'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau',
    'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkiye',
    'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda',
    'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
    'UM' => 'United States Minor Outlying Islands', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan',
    'VU' => 'Vanuatu', 'VE' => 'Venezuela', 'VN' => 'Viet Nam', 'VG' => 'Virgin Islands (British)',
    'VI' => 'Virgin Islands (U.S.)', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
    'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
];