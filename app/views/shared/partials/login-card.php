<?php
/**
 * The log-in card.
 *
 * pages/login.php renders it twice: once inside the normal site chrome and once
 * on its own while maintenance mode hides the rest of the site. One file, so the
 * two can never drift apart.
 *
 * @var string $error       message from the last attempt, '' for none
 * @var string $resend      success note after a verification resend, '' for none
 * @var bool   $unverified  true when the account exists but its email is unconfirmed
 * @var string $lastEmail   the address to keep in the field
 * @var bool   $minimal     true on the maintenance screen: no register/forgot links
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }
$minimal = $minimal ?? false;

ob_start();
$alert_type = 'success'; $alert_text = $resend;
require __DIR__ . '/alert.php';
$alert_type = 'danger'; $alert_text = $error;
require __DIR__ . '/alert.php';

if ($unverified) { ?>
<div class="alert alert-warning d-flex align-items-start gap-2">
<?= icon('envelope-circle-check', 'mt-1') ?>
<div>
<strong>Email not verified yet.</strong> Click the link in the email we sent you, or resend it.
<form method="POST" class="mt-3">
<input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<input type="hidden" name="resend_verification" value="1">
<input type="hidden" name="email" value="<?= e($lastEmail) ?>">
<button type="submit" class="btn btn-ghost btn-sm">Resend verification email</button>
</form>
</div>
</div>
<?php } ?>

<form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<div class="mb-3">
<label class="form-label">Email</label>
<input type="email" name="email" class="form-control" required value="<?= e($lastEmail) ?>" autofocus>
</div>
<div class="mb-4">
<label class="form-label">Password</label>
<input type="password" name="password" class="form-control" required>
<?php if (!$minimal) { ?>
<div class="text-end mt-2"><a href="<?= url('forgot') ?>" class="small">Forgot password?</a></div>
<?php } ?>
</div>
<button type="submit" class="btn btn-gold w-100 btn-lg">Log in</button>
</form>
<?php
$auth_body = ob_get_clean();

$auth_icon = 'music';
$auth_icon_class = 'text-gold fs-1';
$auth_title = 'Welcome back';
$auth_text = 'Log in to your ' . SITE_NAME . ' account';
$auth_footer = $minimal ? '' : 'Don\'t have an account? <a href="' . e(url('register')) . '" class="fw-semibold">Register</a>';
$auth_plain = $minimal;
require __DIR__ . '/auth-panel.php';
