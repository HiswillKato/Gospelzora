<?php
require_once __DIR__ . '/../app/bootstrap.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf($_POST['csrf_token'] ?? '')) {
$error = "Your session expired. Please try again.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $error = "Please fill in every field.";
        } elseif (mb_strlen($name) > 100) {
            $error = "Your name is too long. Please use 100 characters or fewer.";
        } elseif (mb_strlen($email) > 150) {
            $error = "Your email address is too long. Please use 150 characters or fewer.";
        } elseif (mb_strlen($subject) > 200) {
            $error = "Your subject is too long. Please use 200 characters or fewer.";
        } elseif (!valid($email)) {
            $error = "That email address does not look valid.";
        } else {
            $db->execute("INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)", [$name, $email, $subject, $message]);
            $analytics->log('contact.message', sprintf("Contact message: %s (%s) - %s", $name, $email, $subject));
            try {
                $mail->send('contact', ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message]);
            } catch (Throwable $e) {
                $analytics->log('email.failed', sprintf("To: %s - %s", $email, $e->getMessage()));
            }
            $success = "Thanks for reaching out! We will get back to you soon.";
        }
    }
}

$page_title = "Contact";
$page_description = "Send us a message - we would love to hear from you.";
require_once __DIR__ . '/../app/includes/public/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="text-center mb-5">
                    <h1 class="fw-bold">Contact Us</h1>
                    <p class="text-muted">We’d love to hear from you. Send us a message and we’ll respond as soon as possible.</p>
                </div>
                
                <?php $alert_type = 'success'; $alert_text = $success;
                require __DIR__ . '/../app/includes/partials/alert.php'; ?>
                <?php $alert_type = 'danger'; $alert_text = $error;
                require __DIR__ . '/../app/includes/partials/alert.php'; ?>
                
                <div class="card border-0 shadow" style="border-radius: 16px;">
                    <div class="card-body p-4 p-md-5">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" required maxlength="100" value="<?= e($_POST['name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required maxlength="150" value="<?= e($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Subject</label>
                                    <input type="text" name="subject" class="form-control" required maxlength="200" value="<?= e($_POST['subject'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-gold btn-lg w-100">Send Message</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 