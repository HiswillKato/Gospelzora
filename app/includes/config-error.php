<?php
/**
 * Standalone "site configuration needed" card, required by the deployment
 * self-check in app/bootstrap.php when a live server is misconfigured in a
 * way that would otherwise render a subtly broken site (BASE_URL pointing at
 * localhost, or an unwritable upload directory).
 *
 * The visitor is told nothing technical: the page is deliberately opaque to
 * the public and the specifics go to the error log, which is where the
 * operator looks. The one exception is the BASE_URL case, because the site is
 * visibly unstyled and the operator needs the host name in front of them.
 *
 * The caller sets $configProblems and the 503; this file only renders.
 *
 * @var string[] $configProblems  one finished HTML sentence per problem
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Site configuration needed • <?= SITE_NAME ?></title>
<?php require __DIR__ . '/partials/theme-script.php';
require __DIR__ . '/partials/css-links.php'; ?>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background:var(--light-bg,#f7f8fc)">
<div class="container" style="max-width:680px">
<div class="card border-0 shadow-lg" style="border-radius:16px">
<div class="card-body p-5">
<?= icon('wrench', 'display-5 text-warning d-block mb-3') ?>
<h1 class="fw-bold mb-3 h4" style="color:var(--modern-ink,#172033)">This site needs one configuration change</h1>
<p class="text-muted mb-4">It has not been switched over to this server yet, so it is being held back rather than shown to visitors half-working. The details are in the server error log.</p>
<div class="list-group list-group-flush">
<?php foreach ($configProblems as $configProblem) { ?>
<div class="list-group-item px-0"><?= $configProblem ?></div>
<?php } ?>
</div>
<hr>
<p class="small text-muted mb-0">Set the values in <code>app/config.php</code>, then reload the page.</p>
</div>
</div>
</div>
</body></html>
