<?php
/**
 * Gospelzora — the menu at the top of every public page.
 * header.php includes it, so a page never includes this file directly.
 *
 * The bar is one translucent sticky strip: the brand mark and the name on the
 * left, then the search panel toggle, the theme switch and the account
 * cluster, with the shortcuts behind Bootstrap's own collapse. Register is the
 * one thing the bar asks a newcomer to DO, so it is a filled gold button at
 * the end rather than another link.
 *
 * Every class here is a Bootstrap one except brand-mark and brand-name, which
 * fix a size and a face Bootstrap has no utility for.
 */

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

/* One row per shortcut: [label, path, the URL prefix that marks it active]. */
$nav = [
    ['Home', '', ['/', '']],
    ['Music', 'music', ['/music']],
    ['Artists', 'artists', ['/artists']],
    ['Categories', 'categories', ['/categories']],
    ['Top Charts', 'charts', ['/charts']],
    ['Playlists', 'playlists', ['/playlists']],
];
$nav_active = static function (array $prefixes) use ($current_path): bool {
    foreach ($prefixes as $prefix) {
        if ($prefix === '' ? $current_path === '/' : str_contains((string)$current_path, $prefix)) {
            return true;
        }
    }
    return false;
};
?>

<header class="site-header">
    <nav class="navbar navbar-expand-lg" aria-label="Main">
        <div class="container">

            <a class="navbar-brand d-flex align-items-center gap-2 me-lg-4" href="<?= url() ?>">
                <span class="brand-mark" aria-hidden="true">
                    <img src="<?= e(asset('images/logo.svg')) ?>" alt="">
                </span>
                <span class="brand-name"><?= e(SITE_NAME) ?></span>
            </a>

            <?php /* Last thing on the right of the bar, so it needs a left margin
                   of its own: gap-2 only spaces the buttons against each other,
                   because CSS gap never puts a gap in front of the first child. */ ?>
            <div class="d-flex align-items-center gap-2 order-lg-3 ms-auto ms-lg-3">
                <button class="btn btn-ghost rounded-circle" type="button" data-bs-toggle="collapse"
                        data-bs-target="#zora-search" aria-controls="zora-search" aria-expanded="false"
                        aria-label="Search the site">
                    <?= icon('magnifying-glass') ?>
                </button>

                <button class="theme-toggle btn btn-ghost rounded-circle" type="button" id="themeToggle"
                        aria-label="Toggle theme" title="Toggle theme">
                    <?= icon('sun') ?>
                    <?= icon('moon') ?>
                </button>

                <button class="btn btn-ghost rounded-circle d-lg-none" type="button"
                        data-bs-toggle="collapse" data-bs-target="#zora-menu"
                        aria-controls="zora-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <?= icon('bars') ?>
                </button>
            </div>

            <div class="collapse navbar-collapse order-lg-2" id="zora-menu">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1 py-3 py-lg-0">
                    <?php foreach ($nav as [$label, $path, $prefixes]) { ?>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold<?= $nav_active($prefixes) ? ' active' : '' ?>" href="<?= url($path) ?>"><?= e($label) ?></a>
                    </li>
                    <?php } ?>

                    <?php if ($current_user) { ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle fw-semibold d-flex align-items-center" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <?= icon('circle-user', '', 'fs-5') ?>
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
                            <a class="nav-link fw-semibold<?= $nav_active(['/login']) ? ' active' : '' ?>" href="<?= url('login') ?>"><?= icon('right-to-bracket', '', 'me-1') ?>Login</a>
                        </li>
                        <li class="nav-item mt-2 mt-lg-0 ms-lg-2">
                            <a class="btn btn-gold" href="<?= url('register') ?>"><?= icon('user-plus') ?>Register</a>
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
