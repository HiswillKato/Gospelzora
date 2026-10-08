<?php
require_once __DIR__ . '/../app/bootstrap.php';

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
    ?><!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Login • <?= SITE_NAME ?></title>
<?php require __DIR__ . '/../app/includes/partials/theme-script.php';
require __DIR__ . '/../app/includes/partials/css-links.php'; ?></head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background:var(--light-bg,#f7f8fc); padding:1rem">
<section class="py-5 w-100">
<div class="container">
<div class="row justify-content-center">
<div class="col-md-6 col-lg-5">
<div class="card border-0 shadow" style="border-radius:16px">
<div class="card-body p-4 p-md-5">
<div class="text-center mb-4">
<?= icon('music', 'text-gold fs-1') ?>
<h1 class="fw-bold mt-2 fs-2">Welcome Back</h1>
<p class="text-muted">Login to your Gospelzora account</p>
</div>
<?php $alert_type = 'danger'; $alert_text = $error;
require __DIR__ . '/../app/includes/partials/alert.php'; ?>
<form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<div class="mb-3">
<label class="form-label">Email</label>
<input type="email" name="email" class="form-control" required value="<?= e($lastEmail) ?>" autofocus>
</div>
<div class="mb-4">
<label class="form-label">Password</label>
<input type="password" name="password" class="form-control" required>
</div>
<button type="submit" class="btn btn-gold w-100 btn-lg">Login</button>
</form>
</div>
</div>
</div>
</div>
</div>
</section>
</body></html><?php
    exit;
}

$page_title = "Login";
$page_description = "Log in to your Gospelzora account.";
require_once __DIR__ . '/../app/includes/public/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow" style="border-radius: 16px;">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <?= icon('music', 'text-gold fs-1') ?>
                            <h1 class="fw-bold mt-2 fs-2">Welcome Back</h1>
                            <p class="text-muted">Login to your Gospelzora account</p>
                        </div>

                        <?php $alert_type = 'success'; $alert_text = $resend;
                        require __DIR__ . '/../app/includes/partials/alert.php'; ?>
                        <?php $alert_type = 'danger'; $alert_text = $error;
                        require __DIR__ . '/../app/includes/partials/alert.php'; ?>

                        <?php if ($unverified) { ?>
                        <div class="alert alert-warning">
                            <?= icon('envelope-circle-check', 'me-1') ?>
                            <strong>Email not verified yet.</strong> Click the link in the email we sent you, or resend it.
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                <input type="hidden" name="resend_verification" value="1">
                                <input type="hidden" name="email" value="<?= e($lastEmail) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning">Resend verification email</button>
                            </form>
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
                                <div class="text-end mt-2">
                                    <a href="<?= url('forgot') ?>" class="small text-purple text-decoration-none">Forgot password?</a>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-gold w-100 btn-lg">Login</button>
                        </form>

                        <p class="text-center mt-4 mb-0 text-muted">
                            Don't have an account? <a href="<?= url('register') ?>" class="text-purple fw-semibold">Register</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 