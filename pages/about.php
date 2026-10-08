<?php
require_once __DIR__ . '/../app/bootstrap.php';
$page_title = "About Us";
$page_description = "Learn about Gospelzora and our mission to spread worship through music.";
require_once __DIR__ . '/../app/includes/public/header.php';
?>

<section class="py-5" style="background: var(--navy); color: white;">
    <div class="container text-center py-4">
        <h1 class="display-5 fw-bold">About Gospelzora</h1>
        <p class="lead opacity-75"><?= SITE_TAGLINE ?></p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="fw-bold text-purple mb-3">What is Gospelzora?</h2>
                <p class="lead text-muted">Gospelzora is a gospel music platform dedicated to making anointed worship and praise music easily accessible to believers around the world.</p>
                
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
                
                <div class="mt-5 p-4 rounded" style="background: rgba(74,28,107,0.06);">
                    <p class="mb-0 text-center fst-italic">“Let everything that has breath praise the Lord.” — Psalm 150:6</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/public/footer.php'; 