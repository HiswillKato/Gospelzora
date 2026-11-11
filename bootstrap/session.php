<?php
declare(strict_types=1);

/** Session and response security settings; included from app/config.php. */

if (session_status() === PHP_SESSION_NONE) {
    if (SESSION_SAVE_PATH !== '' && is_dir(SESSION_SAVE_PATH)) {
        session_save_path(SESSION_SAVE_PATH);
    }

    session_name('GOSPELZORA');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $zoraHttps,
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

    $mediaOrigin = '';
    $mediaUrl = trim((string)(getenv('R2_MEDIA_URL') ?: ($_ENV['R2_MEDIA_URL'] ?? '')));
    if ($mediaUrl !== '') {
        $parts = parse_url($mediaUrl);
        if (is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])) {
            $mediaOrigin = 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }
    }

    $csp = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:" . ($mediaOrigin !== '' ? ' ' . $mediaOrigin : '') . '; '
        . "font-src 'self'; "
        . "media-src 'self'" . ($mediaOrigin !== '' ? ' ' . $mediaOrigin : '') . '; '
        . "form-action 'self'; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'";

    if ($zoraHttps) {
        $csp .= '; upgrade-insecure-requests';
    }
    header('Content-Security-Policy: ' . $csp);
}
