<?php
/**
 * Standalone maintenance card page, required by the maintenance gate in app/bootstrap.php.
 * Emits a full themed HTML page; the caller sets the 503 and exits.
 *
 * @var string $page_title        pre-escaped
 * @var string $page_description  pre-escaped
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= $page_title ?> • <?= SITE_NAME ?></title>
<script>
    (function() {
        try {
            var saved = localStorage.getItem('gospelzora-theme');
            var theme = saved === 'light' || saved === 'dark'
                ? saved
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme-mode', theme);
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
        } catch (e) {}
    })();
</script>
<link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
<link href="<?= asset('css/all.min.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background:var(--light-bg,#f7f8fc)">
<div class="container" style="max-width:520px">
<div class="card border-0 shadow-lg" style="border-radius:16px">
<div class="card-body text-center p-5">
<i class="fa-solid fa-screwdriver-wrench display-5 text-warning d-block mb-3"></i>
<h1 class="fw-bold mb-3 h4"><?= $page_title ?></h1>
<p class="text-muted mb-0 fs-5"><?= $page_description ?></p>
</div>
</div>
</div>
</body></html>
