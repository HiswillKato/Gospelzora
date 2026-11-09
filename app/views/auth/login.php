<?php
require_once __DIR__ . '/../../bootstrap.php';

if ($user->authed()) {
    redirect('dashboard');
}

$error = '';
$resend = '';
$unverified = false;
$lastEmail = trim((string)($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
    } elseif (isset($_POST['resend_verification'])) {
        $result = $user->resend($lastEmail);
        if ($result['success']) {
            $resend = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        $email = $lastEmail;
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please fill in all fields.";
        } else {
            $result = $user->login($email, $password);
            if ($result['success']) {
                flash('success', $result['message']);
                redirect('dashboard');
            } else {
                // Non-admins get blocked here during maintenance
                // ('maintenance_login_blocked') — only admins proceed.
                $error = $result['message'];
                $unverified = !empty($result['unverified']);
            }
        }
    }
}

// During maintenance the login card stays identical to the normal login —
// only the site below)/(above is dropped: no header/navbar, no footer, no
// forgot-password or register links. Non-admin submissions are rejected on
// POST ("maintenance_login_blocked") while signed-out admins can still get in.
if ($settings->maintenance() && !$user->check('admin')) {
    $minimal = true;
    ?><!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Login • <?= SITE_NAME ?></title>
<?php require __DIR__ . '/../../includes/partials/theme-script.php';
require __DIR__ . '/../../includes/partials/css-links.php'; ?></head>
<body>
<div class="login-wrap">
<div class="w-100" style="max-width:26rem">
<?php require __DIR__ . '/../../includes/partials/login-card.php'; ?>
</div>
</div>
</body></html><?php
    exit;
}

$page_title = "Login";
$page_description = "Log in to your Gospelzora account.";
require_once __DIR__ . '/../shared/header.php';
$minimal = false;
require __DIR__ . '/../../includes/partials/login-card.php';
require_once __DIR__ . '/../shared/footer.php'; 