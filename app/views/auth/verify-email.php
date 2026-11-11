<?php
require_once __DIR__ . '/../../bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
$verified = false;
$error = '';

if ($token === '') {
    $error = "This verification link is missing its token.";
} else {
    $row = $user->consume('email_verify', $token);
    if (!$row) {
        $error = "This verification link is invalid or has expired. You can request a new one from the login page.";
    } elseif (!$user->verify((int)$row['id'], (string)$row['type'])) {
        $error = "We could not send the email right now. Please try again shortly.";
    } else {
        $analytics->log('user.verify', sprintf("Email verified: %s", $row['email']), (int)$row['id'], $row['name']);
        $verified = true;
    }
}

$page_title = "Verify Email";

ob_start();
if ($verified) { ?>
<p class="text-body-secondary">Your email address is confirmed. You can now log in to your account.</p>
<a href="<?= url('login') ?>" class="btn btn-gold btn-lg w-100 mt-2">Log in</a>
<?php } else { ?>
<p class="text-body-secondary"><?= e($error) ?></p>
<a href="<?= url('login') ?>" class="btn btn-gold btn-lg w-100 mt-2">Go to login</a>
<p class="mt-3 mb-0 small text-body-secondary">You can resend the verification email from the login page.</p>
<?php }

$auth_body = ob_get_clean();
$auth_icon = $verified ? 'circle-check' : 'circle-exclamation';
$auth_icon_class = $verified ? 'text-success fs-1' : 'text-warning fs-1';
$auth_title = $verified ? 'Email verified' : 'Verification failed';
$auth_text = '';
$auth_center = true;
require __DIR__ . '/../shared/partials/auth-panel.php';

