</main>

    <!-- Footer -->
    <footer class="gospel-footer mt-auto">
        <div class="container py-5">
            <div class="row g-4 justify-content-center">
                <div class="col-12 col-lg-6">
                    <h5 class="text-white fw-bold mb-3">
                        <?= icon('music', 'text-gold me-2') ?><?= SITE_NAME ?>
                    </h5>
                    <p class="footer-text mb-2"><?= SITE_TAGLINE ?></p>
                    <p class="footer-text footer-text-muted small">
                        Discover uplifting gospel music that inspires worship, strengthens faith, and transforms lives. Stream and download anointed songs from gifted artists around the world.
                    </p>
                    <p class="footer-links small d-flex flex-wrap gap-3 mb-0 mt-3">
                        <a href="<?= url('about') ?>">About Us</a>
                        <a href="<?= url('contact') ?>">Contact Us</a>
                    </p>
                </div>

                <div class="col-4 col-md-4 col-lg-2">
                    <h6 class="text-white fw-semibold mb-3 text-start">Explore</h6>
                    <ul class="list-unstyled footer-links text-start">
                        <li><a href="<?= url('music') ?>">Music</a></li>
                        <li><a href="<?= url('artists') ?>">Artists</a></li>
                        <li><a href="<?= url('categories') ?>">Categories</a></li>
                    </ul>
                </div>

                <div class="col-4 col-md-4 col-lg-2">
                    <h6 class="text-white fw-semibold mb-3 text-start">Account</h6>
                    <ul class="list-unstyled footer-links text-start">
                        <?php if ($user->authed()) { ?>
                            <li><a href="<?= url('dashboard/profile') ?>">Profile</a></li>
                            <li><a href="<?= url('favorites') ?>">Favorites</a></li>
                            <li><form method="POST" action="<?= url('logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>"><button type="submit" class="footer-logout">Logout</button></form></li>
                        <?php } else { ?>
                            <li><a href="<?= url('login') ?>">Login</a></li>
                            <li><a href="<?= url('register') ?>">Register</a></li>
                        <?php } ?>
                    </ul>
                </div>

                <div class="col-4 col-md-4 col-lg-2">
                    <h6 class="text-white fw-semibold mb-3 text-start">Legal</h6>
                    <ul class="list-unstyled footer-links text-start">
                        <li><a href="<?= url('terms') ?>"><?= $pages->meta('terms', 'title') ?></a></li>
                        <li><a href="<?= url('privacy') ?>"><?= $pages->meta('privacy', 'title') ?></a></li>
                        <li><a href="<?= url('copyright') ?>"><?= $pages->meta('copyright', 'title') ?></a></li>
                    </ul>
                </div>
            </div>

            <div class="d-flex justify-content-center align-items-center gap-3 mt-4">
                <a href="https://facebook.com/Gospelzora" class="social-icon" aria-label="Facebook"><?= icon('facebook-f', 'brands') ?></a>
                <a href="https://x.com/Gospelzora" class="social-icon" aria-label="Twitter"><?= icon('x-twitter', 'brands') ?></a>
                <a href="https://instagram.com/Gospelzora" class="social-icon" aria-label="Instagram"><?= icon('instagram', 'brands') ?></a>
                <a href="https://youtube.com/@Gospelzora" class="social-icon" aria-label="YouTube"><?= icon('youtube', 'brands') ?></a>
            </div>

            <hr class="border-secondary my-4">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <p class="footer-text footer-text-muted small mb-0">
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.
                </p>
                <p class="footer-text footer-text-muted small mb-0">
                    Made with <?= icon('heart', 'text-danger') ?> for the Kingdom of God
                </p>
            </div>
        </div>
    </footer>

    <!-- Global Music Player -->
    <div id="globalPlayer" class="global-player d-none">
        <div class="container-fluid">
            <div class="player-inner d-flex align-items-center gap-3 py-2">
                <!-- Cover & Info -->
                <div class="player-info d-flex align-items-center gap-3 flex-shrink-0" style="min-width: 200px;">
                    <img id="playerCover" src="<?= image('', 'song') ?>" alt="Song cover artwork" class="player-cover rounded">
                    <div class="overflow-hidden">
                        <div id="playerTitle" class="player-title text-truncate fw-semibold">Song Title</div>
                        <div id="playerArtist" class="player-artist small text-truncate">Artist</div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="player-controls d-flex align-items-center gap-2 flex-shrink-0">
                    <button id="playerPrev" class="btn btn-link p-1" title="Previous"><?= icon('backward-step', 'fs-5') ?></button>
                    <button id="playerPlay" class="btn btn-gold rounded-circle player-play-btn" title="Play/Pause"><?= icon('play', 'fs-4') ?></button>
                    <button id="playerNext" class="btn btn-link p-1" title="Next"><?= icon('forward-step', 'fs-5') ?></button>
                </div>

                <!-- Progress -->
                <div class="player-progress flex-grow-1 d-none d-md-flex align-items-center gap-2">
                    <span id="playerCurrent" class="small">0:00</span>
                    <div class="progress flex-grow-1" style="height: 6px; cursor: pointer;" id="playerProgressBar" role="progressbar" aria-label="Playback progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div id="playerProgress" class="progress-bar bg-gold" role="presentation" style="width: 0%"></div>
                    </div>
                    <span id="playerDuration" class="small">0:00</span>
                </div>

                <!-- Volume & Close -->
                <div class="player-extra d-flex align-items-center gap-2 flex-shrink-0">
                    <div class="d-none d-lg-flex align-items-center gap-1">
                        <?= icon('volume-high') ?>
                        <input type="range" id="playerVolume" class="form-range" min="0" max="100" value="80" style="width: 80px;">
                    </div>
                    <button id="playerMinimize" class="btn btn-link p-1" title="Minimize"><?= icon('chevron-down') ?></button>
                    <button id="playerClose" class="btn btn-link p-1" title="Close"><?= icon('xmark') ?></button>
                </div>
            </div>
        </div>
        <audio id="audioElement" preload="metadata"></audio>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;" id="toastContainer"></div>

    <!-- jQuery, Bootstrap JS, App JS (local) -->
    <?php require __DIR__ . '/../partials/js-links.php'; ?>

    <?php if (isset($extra_js)) { ?>
        <?= $extra_js ?>
    <?php } ?>
<?php $analytics->visit(); ?>
</body>
</html>
