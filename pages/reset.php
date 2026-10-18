<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($user->authed()) {
    redirect('dashboard');
}

$token = trim((string)($_GET['token'] ?? ''));
$error = '';
$done = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string)($_POST['token'] ?? ''));
    if (!csrf($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
    } else {
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if ($new === '') {
            $error = "Please choose a new password.";
        } elseif ($new !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            $result = $user->reset($token, $new);
            if ($result['success']) {
                flash('success', $result['message']);
                redirect('login');
            }
            $error = $result['message'];
        }
    }
}

$missing = $token === '';

$page_title = "Reset Password";
require_once __DIR__ . '/../app/includes/public/header.php';

ob_start();
$alert_type = 'danger'; $alert_text = $error;
require __DIR__ . '/../app/includes/partials/alert.php';

if ($missing) { ?>
<div class="alert alert-warning mb-0">
This reset link is missing its token — it may have been opened incorrectly. Please
<a href="<?= url('forgot') ?>">request a new reset link</a>.
</div>
<?php } else { ?>
<form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<input type="hidden" name="token" value="<?= e($token) ?>">
<div class="mb-3">
<label class="form-label">New Password</label>
<input type="password" name="new_password" class="form-control" required minlength="<?= MIN_PASSWORD_LENGTH ?>">
</div>
<div class="mb-4">
<label class="form-label">Confirm New Password</label>
<input type="password" name="confirm_password" class="form-control" required>
</div>
<button type="submit" class="btn btn-gold w-100 btn-lg">Update password</button>
</form>
<?php }

$auth_body = ob_get_clean();
$auth_icon = 'lock';
$auth_icon_class = 'text-gold fs-1';
$auth_title = 'Reset password';
$auth_text = 'Choose a new password for your account.';
$auth_footer = '<a href="' . e(url('login')) . '" class="fw-semibold">Back to login</a>';
require __DIR__ . '/../app/includes/partials/auth-panel.php';

require_once __DIR__ . '/../app/includes/public/footer.php'; 