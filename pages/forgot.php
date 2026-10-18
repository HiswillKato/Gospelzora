<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($user->authed()) {
    redirect('dashboard');
}

$error = '';
$done = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        if (!valid($email)) {
            $error = "Please enter a valid email address.";
        } else {
            // Generic response either way — never reveal whether an account exists.
            $user->recover($email);
            $done = "If an account exists for that email, password-reset instructions are on their way. Check your inbox (and spam).";
        }
    }
}

$page_title = "Forgot Password";
require_once __DIR__ . '/../app/includes/public/header.php';

ob_start();
$alert_type = 'success'; $alert_text = $done;
require __DIR__ . '/../app/includes/partials/alert.php';
$alert_type = 'danger'; $alert_text = $error;
require __DIR__ . '/../app/includes/partials/alert.php';

if ($done === '') { ?>
<form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<div class="mb-3">
<label class="form-label">Email</label>
<input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>" autofocus>
</div>
<button type="submit" class="btn btn-gold w-100 btn-lg">Send reset link</button>
</form>
<?php } else { ?>
<p class="text-center mt-2 mb-0"><a href="<?= url('login') ?>" class="fw-semibold">Back to login</a></p>
<?php }

$auth_body = ob_get_clean();
$auth_icon = 'key';
$auth_icon_class = 'text-gold fs-1';
$auth_title = 'Forgot password?';
$auth_text = "Enter your email and we'll send you a reset link.";
$auth_footer = 'Remembered it? <a href="' . e(url('login')) . '" class="fw-semibold">Log in</a>';
require __DIR__ . '/../app/includes/partials/auth-panel.php';

require_once __DIR__ . '/../app/includes/public/footer.php'; 