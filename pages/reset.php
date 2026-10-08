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
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow" style="border-radius: 16px;">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <?= icon('lock', 'text-gold fs-1') ?>
                            <h1 class="fw-bold mt-2 fs-2">Reset Password</h1>
                            <p class="text-muted">Choose a new password for your account.</p>
                        </div>
                        
                        <?php $alert_type = 'danger'; $alert_text = $error;
                        require __DIR__ . '/../app/includes/partials/alert.php'; ?>

                        <?php if ($missing) { ?>
                            <div class="alert alert-warning">
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
                            <button type="submit" class="btn btn-gold w-100 btn-lg">Update Password</button>
                        </form>
                        <?php } ?>

                        <p class="text-center mt-4 mb-0 text-muted">
                            <a href="<?= url('login') ?>" class="text-purple fw-semibold">Back to login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 