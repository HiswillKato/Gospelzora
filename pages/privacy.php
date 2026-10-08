<?php
require_once __DIR__ . '/../app/bootstrap.php';
$page_title = e($pages->meta('privacy', 'title'));
$page_description = e($pages->meta('privacy', 'description'));
require_once __DIR__ . '/../app/includes/public/header.php';
$page_header_title = e($pages->meta('privacy', 'title'));
$page_header_subtitle = sprintf("Last updated: %s", SITE_LEGAL_UPDATED);
$page_header_content = '';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>
<section class="py-5"><div class="container"><div class="row justify-content-center"><div class="col-lg-9">
<div class="card border-0 shadow" style="border-radius:16px"><div class="card-body p-4 p-md-5">
<?= legal(sprintf(
<<<PRIVACY_BODY
<p class="lead">We are committed to protecting your personal data. This Privacy Policy explains what information we collect on %s, why we collect it, how we use and protect it, and your rights under the <strong>Data Protection and Privacy Act, 2019</strong> (Uganda) ("DPPA") and its regulations.</p>

<h5 class="fw-semibold text-purple">1. Data Controller</h5>
<p>%s is the data controller responsible for the personal data we process on this website. You can reach us through our <a href="%s">contact page</a> or by email to <a href="mailto:admin@gospelzora.com">admin@gospelzora.com</a>.</p>

<h5 class="fw-semibold text-purple">2. Information We Collect</h5>
<ul>
    <li><strong>Account data</strong> — name, email address, account type (user / artist) and country you provide when registering or updating your profile.</li>
    <li><strong>Content data</strong> — for artists: the songs, artwork and biographies you upload.</li>
    <li><strong>Activity data</strong> — songs you stream, download, favourite, and your play counts, so we can show popular content.</li>
    <li><strong>Messages</strong> — name, email, subject and message content when you use the contact form.</li>
    <li><strong>Technical data</strong> — a session cookie for authentication, your theme preference stored in your browser's local storage, and standard server logs (IP address, browser type, pages visited) needed to keep the website secure and functioning.</li>
</ul>

<h5 class="fw-semibold text-purple">3. How We Use Your Information</h5>
<ul>
    <li>To create and manage your account and provide the platform's features (streaming, downloads, favourites);</li>
    <li>To respond to contact messages;</li>
    <li>To display songs, artists and activity (e.g. play counts) that you choose to publish;</li>
    <li>To improve, secure and maintain the website; and</li>
    <li>To comply with legal obligations and enforce our Terms of Use.</li>
</ul>

<h5 class="fw-semibold text-purple">4. Lawful Basis for Processing</h5>
<p>Under the DPPA we only process personal data where we have a lawful basis, including: (i) your consent, which you may withdraw at any time; (ii) performance of the contract established by our Terms of Use; and (iii) our legitimate interests in operating and securing the platform. Processing must be fair and lawful, and we keep data only as long as necessary for the purposes described here (data minimisation).</p>

<h5 class="fw-semibold text-purple">5. Cookies and Similar Technologies</h5>
<p>We use a small number of cookies and browser storage features:</p>
<ul>
    <li><strong>Session cookie</strong> — required to keep you logged in and to protect forms against cross-site request forgery.</li>
    <li><strong>Local storage</strong> — stores your light/dark theme preference on your own device.</li>
    <li><strong>Third-party services</strong> — on the registration page, a geolocation lookup service (e.g. ipwho.is or ipapi.co) may be called by your browser to pre-select your country. This sends your IP address to that third party for detection. If you prefer not to use it, simply leave the country field as "Select a country".</li>
</ul>

<h5 class="fw-semibold text-purple">6. Sharing and Disclosure</h5>
<p>We do not sell your personal data. We only share data: (i) with service providers that operate the website, bound by confidentiality obligations; (ii) where you have directed or consented to the sharing; (iii) to comply with a lawful request from a competent authority; or (iv) to protect our rights, safety or property.</p>

<h5 class="fw-semibold text-purple">7. International Transfers</h5>
<p>If we transfer personal data outside Uganda, we will do so in accordance with the DPPA, applying appropriate safeguards to ensure your data continues to be protected.</p>

<h5 class="fw-semibold text-purple">8. Data Security</h5>
<p>We apply technical and organisational measures appropriate to the sensitivity of the data, including secure (hashed) passwords, HTTPS where available, access controls, and restricted permissions on servers that hold personal data.</p>

<h5 class="fw-semibold text-purple">9. Retention</h5>
<p>We keep account data for as long as your account is active. Messages are kept to respond to and (where needed) resolve enquiries. Server logs are retained for a limited period. When data is no longer needed, it is deleted or anonymised.</p>

<h5 class="fw-semibold text-purple">10. Your Rights as a Data Subject</h5>
<p>Under the DPPA you have the right to:</p>
<ul>
    <li>Request <strong>access</strong> to the personal data we hold about you;</li>
    <li>Request <strong>correction</strong> of inaccurate or incomplete data;</li>
    <li>Request <strong>deletion</strong> (erasure) of your personal data;</li>
    <li><strong>Object</strong> to, or request <strong>restriction</strong> of, processing in certain circumstances;</li>
    <li><strong>Withdraw consent</strong> at any time where processing is based on consent;</li>
    <li>Request <strong>data portability</strong> in a structured, machine-readable format where applicable; and</li>
    <li>Lodge a <strong>complaint</strong> with the National Data Protection Office (NDPO) if you believe your data protection rights have been violated.</li>
</ul>
<p>To exercise any of these rights, contact us via the <a href="%s">contact page</a>. We will respond within the timeframes required by law.</p>

<h5 class="fw-semibold text-purple">11. Data Breach Notifications</h5>
<p>If a personal data breach is likely to result in a risk to your rights and freedoms, we will notify the National Data Protection Office and, where required, affected data subjects in accordance with the DPPA.</p>

<h5 class="fw-semibold text-purple">12. Children's Privacy</h5>
<p>We do not knowingly collect personal data from children under 13. If you believe a child has provided us with personal data, please contact us so we can delete it.</p>

<h5 class="fw-semibold text-purple">13. Regulatory Compliance</h5>
<p>We aim to comply with Ugandan law, including the DPPA. Where the law requires registration as a data controller or another authorisation (for example with the National Data Protection Office or, where applicable, licensing by the Uganda Communications Commission as a content provider), we will obtain and maintain it and you may request confirmation of our compliance status through the contact page.</p>

<h5 class="fw-semibold text-purple">14. Changes to this Policy</h5>
<p>We may update this Policy from time to time. The "Last updated" date at the top indicates when the Policy was revised. We encourage you to review it periodically.</p>

<p class="small text-muted mt-4 mb-0">This page is provided for general information and is not legal advice. If you need legal guidance, please consult a qualified Ugandan attorney.</p>
PRIVACY_BODY, SITE_NAME, SITE_NAME, url('contact'), url('contact')
)) ?>
</div></div></div></div></div></section>
<?php require_once __DIR__ . '/../app/includes/public/footer.php';