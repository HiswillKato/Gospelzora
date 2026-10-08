<?php
/**
 * Standalone "technical difficulties" card, required by the global exception handler in
 * app/bootstrap.php when the database is unreachable. Renders a full themed page so a DB
 * outage on a normal page still shows something friendly instead of a blank 500.
 *
 * The caller sets the 503 and exits; this file only renders.
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Technical difficulties • <?= SITE_NAME ?></title>
<?php require __DIR__ . '/partials/theme-script.php';
require __DIR__ . '/partials/css-links.php'; ?>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background:var(--light-bg,#f7f8fc)">
<div class="container" style="max-width:520px">
<div class="card border-0 shadow-lg" style="border-radius:16px">
<div class="card-body text-center p-5">
<?= icon('triangle-exclamation', 'display-5 text-warning d-block mb-3') ?>
<h1 class="fw-bold mb-3 h4" style="color:var(--modern-ink,#172033)">Technical difficulties</h1>
<p class="text-muted mb-0 fs-5">Something went wrong on our side. Please try again in a moment.</p>
</div>
</div>
</div>
</body></html>
