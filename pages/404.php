<?php
http_response_code(404);
require_once __DIR__ . '/../app/bootstrap.php';
$page_title = "Page Not Found";
$page_description = "The page you were looking for could not be found.";
require_once __DIR__ . '/../app/includes/public/header.php';
?>

<section class="py-5 min-vh-100 d-flex align-items-center">
    <div class="container text-center">
        <div class="mb-4">
            <?= icon('music', 'text-gold', '', 'style="font-size: 4rem"') ?>
        </div>
        <h1 class="display-1 fw-bold text-purple">404</h1>
        <h2 class="h3 mb-3">Page Not Found</h2>
        <p class="text-muted mb-4 col-md-6 mx-auto">
            The page you're looking for doesn't exist or has been moved. Let's get you back to the music.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= url() ?>" class="btn btn-gold btn-lg">
                <?= icon('house', 'me-2') ?>Go Home
            </a>
            <a href="<?= url('music') ?>" class="btn btn-outline-purple btn-lg">
                <?= icon('music', 'me-2') ?>Browse Music
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 