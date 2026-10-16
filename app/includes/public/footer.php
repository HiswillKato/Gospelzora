<?php
$available_social = socials();
$current_year = date('Y');
?>
</main>

    <!-- Footer -->
    <footer class="bg-dark text-white-50 mt-auto pt-5">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-12 col-lg-6">
                    <div class="d-flex align-items-center gap-2">
                        <span class="brand-mark" aria-hidden="true">
                            <img src="<?= e(asset('images/logo.png')) ?>" alt="">
                        </span>
                        <div>
                            <p class="h5 text-white mb-0"><?= e(SITE_NAME) ?></p>
                            <p class="small mb-0"><?= e(SITE_TAGLINE) ?></p>
                        </div>
                    </div>
                    <p class="small mt-3 mb-0">
                        Discover uplifting gospel music that inspires worship, strengthens faith, and transforms lives.
                        Stream and download anointed songs from gifted artists around the world.
                    </p>
                    <?php if ($available_social): ?>
                        <div class="d-flex gap-2 mt-4">
                            <?php foreach ($available_social as $network => $social): ?>
                                <a class="btn btn-outline-light btn-sm rounded-circle d-inline-flex align-items-center justify-content-center"
                                   href="<?= e($social['url']) ?>" target="_blank" rel="noopener"
                                   aria-label="<?= e(SITE_NAME) ?> on <?= e($network) ?>">
                                    <?= icon($social['icon'], 'brands') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-4 col-md-4 col-lg-2">
                    <p class="text-uppercase small fw-bold text-white">Explore</p>
                    <ul class="list-unstyled small d-grid gap-2">
                        <li><a class="link-light" href="<?= url('music') ?>">Music</a></li>
                        <li><a class="link-light" href="<?= url('artists') ?>">Artists</a></li>
                        <li><a class="link-light" href="<?= url('categories') ?>">Categories</a></li>
                    </ul>
                </div>

                <div class="col-4 col-md-4 col-lg-2">
                    <p class="text-uppercase small fw-bold text-white">Account</p>
                    <ul class="list-unstyled small d-grid gap-2">
                        <?php if ($user->authed()) { ?>
                            <li><a class="link-light" href="<?= url('dashboard/profile') ?>">Profile</a></li>
                            <li><a class="link-light" href="<?= url('favorites') ?>">Favorites</a></li>
                            <li><form method="POST" action="<?= url('logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><button type="submit" class="btn btn-link link-light p-0 text-decoration-none">Logout</button></form></li>
                        <?php } else { ?>
                            <li><a class="link-light" href="<?= url('login') ?>">Login</a></li>
                            <li><a class="link-light" href="<?= url('register') ?>">Register</a></li>
                        <?php } ?>
                    </ul>
                </div>

                <div class="col-4 col-md-4 col-lg-2">
                    <p class="text-uppercase small fw-bold text-white">Legal</p>
                    <ul class="list-unstyled small d-grid gap-2">
                        <li><a class="link-light" href="<?= url('terms') ?>"><?= $pages->meta('terms', 'title') ?></a></li>
                        <li><a class="link-light" href="<?= url('privacy') ?>"><?= $pages->meta('privacy', 'title') ?></a></li>
                        <li><a class="link-light" href="<?= url('copyright') ?>"><?= $pages->meta('copyright', 'title') ?></a></li>
                    </ul>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-5 py-4 border-top border-secondary">
                <p class="small text-white-50 mb-0">&copy; <?= e($current_year) ?> <?= e(SITE_NAME) ?>. All rights reserved.</p>
                <p class="small text-white-50 mb-0">Made with <?= icon('heart', '', 'text-danger') ?> for the Kingdom of God</p>
            </div>
        </div>
    </footer>

    <button class="btn btn-gold rounded-circle d-inline-flex align-items-center justify-content-center position-fixed bottom-0 end-0 m-3" type="button" id="back-to-top" aria-label="Back to top" hidden>
        <?= icon('arrow-up') ?>
    </button>

    <!-- Global Music Player -->
    <!-- jQuery, Bootstrap JS, App JS (local) -->
    <?php require __DIR__ . '/../partials/js-links.php'; ?>

    <?php if (isset($extra_js)) { ?>
        <?= $extra_js ?>
    <?php } ?>
<?php $analytics->visit(); ?>
</body>
</html>
