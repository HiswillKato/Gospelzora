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
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow" style="border-radius: 16px;">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <?= icon('key', 'text-gold fs-1') ?>
                            <h1 class="fw-bold mt-2 fs-2">Forgot Password?</h1>
                            <p class="text-muted">Enter your email and we'll send you a reset link.</p>
                        </div>
                        
                        <?php $alert_type = 'success'; $alert_text = $done;
                        require __DIR__ . '/../app/includes/partials/alert.php'; ?>
                        <?php $alert_type = 'danger'; $alert_text = $error;
                        require __DIR__ . '/../app/includes/partials/alert.php'; ?>
                        
                        <?php if ($done === '') { ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>" autofocus>
                            </div>
                            <button type="submit" class="btn btn-gold w-100 btn-lg">Send Reset Link</button>
                        </form>
                        <?php } else { ?>
                        <p class="text-center mt-2 mb-0">
                            <a href="<?= url('login') ?>" class="text-purple fw-semibold">Back to login</a>
                        </p>
                        <?php } ?>
                        
                        <p class="text-center mt-4 mb-0 text-muted">
                            Remembered it? <a href="<?= url('login') ?>" class="text-purple fw-semibold">Login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 