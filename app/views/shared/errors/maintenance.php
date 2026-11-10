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
<?php require __DIR__ . '/../partials/theme-script.php';
require __DIR__ . '/../partials/css-links.php'; ?>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
<div class="container" style="max-width:520px">
<div class="card tile shadow-lg">
<div class="card-body text-center p-5">
<?= icon('screwdriver-wrench', 'display-5 text-warning d-block mb-3') ?>
<h1 class="fw-bold mb-3 h4"><?= $page_title ?></h1>
<p class="text-body-secondary mb-0 fs-5"><?= $page_description ?></p>
</div>
</div>
</div>
</body></html>
