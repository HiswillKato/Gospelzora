<?php
require_once __DIR__ . '/../app/bootstrap.php';

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
require_once __DIR__ . '/../app/includes/public/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow" style="border-radius: 16px;">
                    <div class="card-body p-4 p-md-5 text-center">
                        <?php if ($verified) { ?>
                            <?= icon('circle-check', 'text-success fs-1') ?>
                            <h1 class="fw-bold mt-3 fs-2">Email Verified</h1>
                            <p class="text-muted">Your email address is confirmed. You can now log in to your account.</p>
                            <a href="<?= url('login') ?>" class="btn btn-gold btn-lg w-100 mt-2">Login</a>
                        <?php } else { ?>
                            <?= icon('circle-exclamation', 'text-warning fs-1') ?>
                            <h1 class="fw-bold mt-3 fs-2">Verification Failed</h1>
                            <p class="text-muted"><?= e($error) ?></p>
                            <a href="<?= url('login') ?>" class="btn btn-gold btn-lg w-100 mt-2">Go to Login</a>
                            <p class="mt-3 mb-0 small text-muted">You can resend the verification email from the login page.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 