<?php
require_once __DIR__ . '/../app/bootstrap.php';
$page_title = e($pages->meta('copyright', 'title'));
$page_description = e($pages->meta('copyright', 'description'));
require_once __DIR__ . '/../app/includes/public/header.php';
$page_header_title = e($pages->meta('copyright', 'title'));
$page_header_subtitle = sprintf("Last updated: %s", SITE_LEGAL_UPDATED);
$page_header_content = '';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>
<section class="py-5"><div class="container"><div class="row justify-content-center"><div class="col-lg-9">
<div class="card border-0 shadow" style="border-radius:16px"><div class="card-body p-4 p-md-5">
<?= legal(sprintf(
<<<COPYRIGHT_BODY
<p class="lead">%s respects intellectual property rights and is committed to complying with the <strong>Copyright and Neighbouring Rights Act, 2006</strong> (Uganda) and other applicable copyright laws. This policy explains how we handle copyright and related (neighbouring) right claims.</p>

<h5 class="fw-semibold text-purple">1. What We Host</h5>
<p>The platform hosts music and artwork uploaded by artists. We require every uploading artist to confirm that they own the content or have secured all necessary rights and clearances (including approvals from co-writers, performers, labels and publishers). Even approved uploads might involve rights that the uploader misrepresented, and we take reports of such misuse seriously.</p>

<h5 class="fw-semibold text-purple">2. Copyright and Neighbouring Rights</h5>
<p>Under Ugandan law, authors of musical works, producers of sound recordings, and performers enjoy economic and, in the case of authors and performers, moral rights in their works. Unauthorised reproduction, distribution or communication to the public of protected works may infringe those rights. We therefore respond expeditiously to valid notices of alleged infringement and will remove or disable access to content that is shown to be unauthorised.</p>

<h5 class="fw-semibold text-purple">3. How to Submit a Takedown Notice</h5>
<p>If you believe content on our platform infringes your copyright or neighbouring rights, please send us a notice containing the following information:</p>
<ul>
    <li>Your full name and contact details (email and postal address);</li>
    <li>A description of the work(s) you claim are infringed, including evidence of your ownership of (or exclusive rights in) the work — for example registration details, creator/artist credits, or chain of title;</li>
    <li>The exact location of the allegedly infringing material on our platform (song title and, if possible, the song/artist page URL);</li>
    <li>A statement that you have a good-faith belief that the use is not authorised by the rights owner, its agent or the law; and</li>
    <li>A statement that the information in the notice is accurate and, under penalty of perjury in accordance with Ugandan law, that you are authorised to act on behalf of the rights owner.</li>
</ul>
<p>Send notices to <a href="mailto:admin@gospelzora.com">admin@gospelzora.com</a> or through our <a href="%s">contact page</a>.</p>

<h5 class="fw-semibold text-purple">4. What We Do With a Valid Notice</h5>
<ul>
    <li>We will acknowledge receipt of the notice;</li>
    <li>We will review it and, where the claim appears valid, remove or disable access to the content as soon as practicable;</li>
    <li>We will notify the uploading artist of the removal and of the details we shared with the claimant, so they can respond; and</li>
    <li>We keep records of notices to identify repeated infractions.</li>
</ul>

<h5 class="fw-semibold text-purple">5. Submitting a Counter-Notice</h5>
<p>If you believe your content was removed in error or misidentified, you may send us a counter-notice explaining why the material should be restored, together with any proof of authorisation you hold. We will consider the counter-notice together with the original claim, and may restore content where the claim cannot be substantiated. In the event of a genuine dispute between a rights owner and an uploader, we may keep the content down until the matter is resolved between the parties or by an appropriate authority.</p>

<h5 class="fw-semibold text-purple">6. Repeat Infringers</h5>
<p>Our artist accounts are moderated, and repeat infringement or deliberate attempts to upload unauthorised content will lead to removal of the content and suspension or closure of the account concerned, as well as being reported to relevant authorities where required by law.</p>

<h5 class="fw-semibold text-purple">7. Good-Faith Notice</h5>
<p>We ask that notices be submitted in good faith. Knowingly submitting a false copyright claim could render the claimant liable for damages as provided under applicable law.</p>

<p class="small text-muted mt-4 mb-0">This page is provided for general information and is not legal advice. If you have a copyright or licensing question, please consult a qualified Ugandan attorney.</p>
COPYRIGHT_BODY, SITE_NAME, url('contact')
)) ?>
</div></div></div></div></div></section>
<?php require_once __DIR__ . '/../app/includes/public/footer.php';