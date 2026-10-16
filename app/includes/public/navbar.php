<?php
/**
 * Gospelzora — the menu at the top of every public page.
 * header.php includes it, so a page never includes this file directly.
 *
 * The bar is laid out the way the Ember theme lays one out, but kept dark to
 * match this site's brand: a sticky header holding the brand mark and the name,
 * a search button that drops a panel under the bar with Bootstrap's own
 * collapse, the theme switcher, and the account links. Register is the one
 * thing the bar asks a newcomer to DO, so it is a filled button at the end
 * rather than another link.
 *
 * The background is Bootstrap's own text-bg-dark, so the bar can never go
 * see-through. Everything else is a Bootstrap class except brand-mark and
 * brand-name, which only fix a size and a face Bootstrap has no utility for.
 */

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
?>

<header class="site-header sticky-top text-bg-dark">
    <nav class="navbar navbar-expand-lg py-2">
        <div class="container">

            <a class="navbar-brand d-flex align-items-center gap-2 me-lg-4" href="<?= url() ?>">
                <span class="brand-mark" aria-hidden="true">
                    <img src="<?= e(asset('images/logo.png')) ?>" alt="">
                </span>
                <span class="brand-name"><?= e(SITE_NAME) ?></span>
            </a>

            <?php /* Last thing on the right of the bar, so it needs a left margin
                   of its own: gap-2 only spaces the buttons against each other,
                   because CSS gap never puts a gap in front of the first child. */ ?>
            <div class="d-flex align-items-center gap-2 order-lg-3 ms-auto ms-lg-3">
                <button class="btn btn-outline-light rounded-circle d-inline-flex align-items-center justify-content-center"
                        type="button" data-bs-toggle="collapse" data-bs-target="#zora-search"
                        aria-controls="zora-search" aria-expanded="false" aria-label="Search the site">
                    <?= icon('magnifying-glass') ?>
                </button>

                <button class="theme-toggle btn btn-outline-light rounded-circle d-inline-flex align-items-center justify-content-center"
                        type="button" id="themeToggle" aria-label="Toggle theme" title="Toggle theme">
                    <?= icon('sun') ?>
                    <?= icon('moon') ?>
                </button>

                <button class="navbar-toggler border-0 shadow-none" type="button"
                        data-bs-toggle="collapse" data-bs-target="#zora-menu"
                        aria-controls="zora-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <?= icon('bars', '', 'fs-3') ?>
                </button>
            </div>

            <div class="collapse navbar-collapse order-lg-2" id="zora-menu">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 py-3 py-lg-0">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?= ($current_path === '/' || str_ends_with($current_path, '/')) ? ' active' : '' ?>" href="<?= url() ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?= strpos($current_path, '/music') !== false ? ' active' : '' ?>" href="<?= url('music') ?>">Music</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?= strpos($current_path, '/artists') !== false ? ' active' : '' ?>" href="<?= url('artists') ?>">Artists</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?= strpos($current_path, '/categories') !== false ? ' active' : '' ?>" href="<?= url('categories') ?>">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?= strpos($current_path, '/charts') !== false ? ' active' : '' ?>" href="<?= url('charts') ?>">Top Charts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?= strpos($current_path, '/playlists') !== false ? ' active' : '' ?>" href="<?= url('playlists') ?>">Playlists</a>
                    </li>

                    <?php if ($current_user) { ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle fw-semibold text-white d-flex align-items-center" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <?= icon('circle-user', '', 'me-1 fs-5') ?>
                                <span class="d-none d-sm-inline"><?= e($current_user['name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end rounded-4 shadow border-0 p-2">
                                <li><a class="dropdown-item rounded-3" href="<?= url('dashboard/profile') ?>"><?= icon('user', '', 'me-2') ?>Profile</a></li>
                                <li><a class="dropdown-item rounded-3" href="<?= url('dashboard') ?>"><?= icon('gauge-high', '', 'me-2') ?>Dashboard</a></li>
                                <?php if (($current_user['role'] ?? '') === 'artist') { ?>
                                <li><a class="dropdown-item rounded-3" href="<?= url('dashboard/songs') ?>"><?= icon('cloud-arrow-up', '', 'me-2') ?>Upload Music</a></li>
                                <?php } ?>
                                <?php if (($current_user['role'] ?? '') === 'user') { ?>
                                <li><a class="dropdown-item rounded-3" href="<?= url('dashboard/song-requests') ?>"><?= icon('headphones', '', 'me-2') ?>Request a song</a></li>
                                <?php } ?>
                                <li><a class="dropdown-item rounded-3" href="<?= url('favorites') ?>"><?= icon('heart', '', 'me-2') ?>Favorites</a></li>
                                <li><a class="dropdown-item rounded-3" href="<?= url('playlists') ?>"><?= icon('list', '', 'me-2') ?>Playlists</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="<?= url('logout') ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                <button type="submit" class="dropdown-item rounded-3 text-danger"><?= icon('right-from-bracket', '', 'me-2') ?>Logout</button>
                                </form></li>
                            </ul>
                        </li>
                    <?php } else { ?>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold<?= strpos($current_path, '/login') !== false ? ' active' : '' ?>" href="<?= url('login') ?>"><?= icon('right-to-bracket', '', 'me-1') ?>Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-gold ms-lg-2" href="<?= url('register') ?>">Register</a>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="collapse border-top" id="zora-search">
        <div class="container py-3">
            <form class="d-flex" action="<?= e(url('search')) ?>" method="GET" role="search">
                <label class="visually-hidden" for="nav-search">Search songs or artists</label>
                <div class="input-group">
                    <input type="search" id="nav-search" name="q" class="form-control search-input ps-3"
                           placeholder="Search songs or artists&hellip;"
                           value="<?= e($search_term) ?>" maxlength="100" autocomplete="off">
                    <button class="btn btn-gold px-3" type="submit" aria-label="Search">
                        <?= icon('magnifying-glass') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</header>
