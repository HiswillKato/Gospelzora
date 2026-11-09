<?php
$available_social = socials();
$current_year = date('Y');
?>
</main>

    <!-- Footer -->
    <footer class="site-footer mt-auto">
        <div class="container py-5">
            <div class="row g-4 g-lg-5">
                <div class="col-12 col-lg-5">
                    <a class="navbar-brand d-inline-flex align-items-center gap-2 mb-3" href="<?= url() ?>">
                        <span class="brand-mark" aria-hidden="true">
                            <img src="<?= e(asset('images/logo.svg')) ?>" alt="">
                        </span>
                        <span class="d-flex flex-column">
                            <span class="brand-name"><?= e(SITE_NAME) ?></span>
                            <span class="brand-tagline"><?= e(SITE_TAGLINE) ?></span>
                        </span>
                    </a>
                    <p class="text-body-secondary mb-4">
                        Discover uplifting gospel music that inspires worship, strengthens faith, and transforms lives.
                        Stream and download anointed songs from gifted artists around the world.
                    </p>
                    <?php if ($available_social): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($available_social as $network => $social): ?>
                                <a class="social" href="<?= e($social['url']) ?>" target="_blank" rel="noopener"
                                   aria-label="<?= e(SITE_NAME) ?> on <?= e($network) ?>" title="<?= e($network) ?>">
                                    <?= icon($social['icon'], 'brands') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-4 col-lg-2">
                    <p class="eyebrow mb-3">Explore</p>
                    <ul class="list-unstyled d-grid gap-2 mb-0">
                        <li><a class="link-body-emphasis" href="<?= url('music') ?>">Music</a></li>
                        <li><a class="link-body-emphasis" href="<?= url('artists') ?>">Artists</a></li>
                        <li><a class="link-body-emphasis" href="<?= url('categories') ?>">Categories</a></li>
                        <li><a class="link-body-emphasis" href="<?= url('charts') ?>">Top Charts</a></li>
                    </ul>
                </div>

                <div class="col-4 col-lg-2">
                    <p class="eyebrow mb-3">Account</p>
                    <ul class="list-unstyled d-grid gap-2 mb-0">
                        <?php if ($user->authed()) { ?>
                            <li><a class="link-body-emphasis" href="<?= url('dashboard/profile') ?>">Profile</a></li>
                            <li><a class="link-body-emphasis" href="<?= url('dashboard') ?>">Dashboard</a></li>
                            <li><a class="link-body-emphasis" href="<?= url('favorites') ?>">Favorites</a></li>
                            <li><form method="POST" action="<?= url('logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><button type="submit" class="btn btn-link link-body-emphasis p-0">Logout</button></form></li>
                        <?php } else { ?>
                            <li><a class="link-body-emphasis" href="<?= url('login') ?>">Login</a></li>
                            <li><a class="link-body-emphasis" href="<?= url('register') ?>">Register</a></li>
                        <?php } ?>
                    </ul>
                </div>

                <div class="col-4 col-lg-3">
                    <p class="eyebrow mb-3">Legal</p>
                    <ul class="list-unstyled d-grid gap-2 mb-0">
                        <li><a class="link-body-emphasis" href="<?= url('terms') ?>"><?= $pages->meta('terms', 'title') ?></a></li>
                        <li><a class="link-body-emphasis" href="<?= url('privacy') ?>"><?= $pages->meta('privacy', 'title') ?></a></li>
                        <li><a class="link-body-emphasis" href="<?= url('copyright') ?>"><?= $pages->meta('copyright', 'title') ?></a></li>
                        <li><a class="link-body-emphasis" href="<?= url('contact') ?>">Contact</a></li>
                    </ul>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 small text-body-secondary">
                <span>&copy; <?= e($current_year) ?> <?= e(SITE_NAME) ?>. All rights reserved.</span>
                <span>Made with <?= icon('heart', '', 'text-danger') ?> for the Kingdom of God</span>
            </div>
        </div>
    </footer>

    <button class="btn btn-gold rounded-circle d-inline-flex align-items-center justify-content-center position-fixed bottom-0 end-0 m-3" type="button" id="back-to-top" aria-label="Back to top" hidden>
        <?= icon('arrow-up') ?>
    </button>

    <!-- Global Music Player -->
    <!-- jQuery, Bootstrap JS, App JS (local) -->
    <?php require __DIR__ . '/partials/js-links.php'; ?>

    <?php if (isset($extra_js)) { ?>
        <?= $extra_js ?>
    <?php } ?>
<?php $analytics->visit(); ?>
</body>
</html>
