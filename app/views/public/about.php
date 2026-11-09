<?php
require_once __DIR__ . '/../../bootstrap.php';
$page_title = "About Us";
$page_description = "Learn about Gospelzora and our mission to spread worship through music.";
require_once __DIR__ . '/../shared/header.php';
?>

<section class="hero-section">
    <div class="container text-center">
        <span class="eyebrow text-warning">Gospelzora</span>
        <h1 class="hero-title mt-2">About us</h1>
        <p class="hero-tagline mb-0"><?= SITE_TAGLINE ?></p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="section-title text-primary mb-3">What is Gospelzora?</h2>
                <p class="lead text-body-secondary">Gospelzora is a gospel music platform dedicated to making anointed worship and praise music easily accessible to believers around the world.</p>

                <h3 class="h5 fw-semibold mt-4">Our Mission</h3>
                <p>To provide a clean, modern, and inspiring space where people can discover, stream, and download gospel music that draws them closer to God and strengthens their walk of faith.</p>

                <h3 class="h5 fw-semibold mt-4">Our Vision</h3>
                <p>To become a trusted destination for quality gospel music that supports personal devotion, church worship, and everyday inspiration.</p>

                <h3 class="h5 fw-semibold mt-4">Our Purpose</h3>
                <ul>
                    <li>Help believers find music that fuels genuine worship</li>
                    <li>Support gospel artists by giving their music a platform</li>
                    <li>Encourage the use of music as a tool for spiritual growth</li>
                    <li>Create a community centered around the message of the Gospel through song</li>
                </ul>

                <h3 class="h5 fw-semibold mt-4">Gospel Music Discovery</h3>
                <p>Whether you are looking for intimate worship, energetic praise, African gospel rhythms, classic hymns, or contemporary sounds, Gospelzora organizes music into clear categories so you can quickly find what your heart needs.</p>

                <h3 class="h5 fw-semibold mt-4">Contact</h3>
                <p>We would love to hear from you. Visit our <a href="<?= url('contact') ?>">Contact page</a> to send a message, request songs, or inquire about partnership.</p>

                <div class="card tile mt-5"><div class="card-body text-center fst-italic">
                    “Let everything that has breath praise the Lord.” — Psalm 150:6
                </div></div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../shared/footer.php'; 