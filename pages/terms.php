<?php
require_once __DIR__ . '/../app/bootstrap.php';
$page_title = e($pages->meta('terms', 'title'));
$page_description = e($pages->meta('terms', 'description'));
require_once __DIR__ . '/../app/includes/public/header.php';
$page_header_title = e($pages->meta('terms', 'title'));
$page_header_subtitle = sprintf("Last updated: %s", SITE_LEGAL_UPDATED);
$page_header_content = '';
require __DIR__ . '/../app/includes/partials/page-header.php';
?>
<section class="py-5"><div class="container"><div class="row justify-content-center"><div class="col-lg-9">
<div class="card border-0 shadow" style="border-radius:16px"><div class="card-body p-4 p-md-5">
<?= legal(sprintf(
<<<TERMS_BODY
<h5 class="fw-semibold text-purple">1. Introduction</h5>
<p>Welcome to %s ("we", "us", "our"), a platform that connects gospel artists and worshippers through streaming and download of inspirational music. These Terms of Use ("Terms") govern your access to and use of this website and its services. By creating an account, streaming, downloading or otherwise using this website, you agree to be bound by these Terms, which form a binding contract under the <strong>Uganda Contracts Act, 2010</strong>.</p>

<h5 class="fw-semibold text-purple">2. Eligibility</h5>
<p>You must be at least 13 years of age to use this website. If you are under 18, you may only use the website with the involvement of a parent or guardian who agrees to be bound by these Terms.</p>

<h5 class="fw-semibold text-purple">3. Accounts</h5>
<ul>
    <li>You are responsible for safeguarding your account credentials and for all activity that occurs under your account.</li>
    <li>You must provide accurate and truthful information when registering.</li>
    <li>We may suspend or close accounts that violate these Terms, the law, or that are used for fraud or abuse.</li>
    <li>Users who register as Artists may upload music, which remains subject to our moderation and approval process before publication.</li>
</ul>

<h5 class="fw-semibold text-purple">4. Content You Submit</h5>
<p>By uploading any music, artwork, biography or other content ("User Content") to the platform, you confirm that:</p>
<ul>
    <li>You own the material or have secured all necessary rights, licences and clearances (including, without limitation, any approvals required from co-writers, performers, record labels and publishers) to upload it and to authorise its publication on the platform;</li>
    <li>The content does not infringe the copyright, neighbouring rights or any other rights of any third party under the <strong>Copyright and Neighbouring Rights Act, 2006</strong> (Uganda) or any applicable law;</li>
    <li>The content does not contain unlawful, defamatory, offensive or misleading material; and</li>
    <li>You grant us a non-exclusive, royalty-free, worldwide licence to host, store, stream, display and (where enabled) make the content available for download for the purpose of operating and promoting the platform. This licence ends when the content is removed from the website.</li>
</ul>

<h5 class="fw-semibold text-purple">5. Acceptable Use</h5>
<p>You agree not to use the website to:</p>
<ul>
    <li>Upload or transmit any content that is unlawful, infringing, hateful, defamatory, harassing, obscene or harmful to minors;</li>
    <li>Attempt to gain unauthorised access to any part of the website, our systems, or other users' accounts — conduct that may constitute an offence under the <strong>Computer Misuse Act, 2011</strong> (as amended);</li>
    <li>Interfere with or disrupt the website, servers or networks connected to the website;</li>
    <li>Introduce viruses, malware or any other harmful code;</li>
    <li>Use automated means (bots, scrapers) to access, collect or duplicate content at scale; or</li>
    <li>Impersonate another person or entity, or otherwise misrepresent your identity.</li>
</ul>

<h5 class="fw-semibold text-purple">6. Intellectual Property</h5>
<p>The website design, software, text, graphics and other materials owned by us are protected by copyright and other intellectual property laws. Artist names, song titles and User Content remain the property of their respective owners. Downloading content grants you a personal, non-commercial licence to use that content for personal worship and listening — it does not transfer any copyright in the content, and you may not re-distribute, sell, sample or publicly perform downloaded material without permission.</p>

<h5 class="fw-semibold text-purple">7. Streaming and Downloads</h5>
<ul>
    <li>Streaming is provided for your personal, non-commercial use.</li>
    <li>Where downloads are enabled, they are limited to legitimate personal use. We may impose limits or remove download availability at any time.</li>
</ul>

<h5 class="fw-semibold text-purple">8. Termination</h5>
<p>We may suspend or terminate your access, with or without notice, if we reasonably believe you have breached these Terms or applicable law, or if your use creates risk or legal exposure for us or other users. You may stop using the website at any time by closing your account through our contact page.</p>

<h5 class="fw-semibold text-purple">9. Disclaimer of Warranties</h5>
<p>The website and all content are provided "as is" and "as available". To the fullest extent permitted by law, we make no warranties, express or implied, about the availability, accuracy or fitness of the website or its content.</p>

<h5 class="fw-semibold text-purple">10. Limitation of Liability</h5>
<p>To the fullest extent permitted by Ugandan law, we will not be liable for indirect, incidental, special or consequential damages arising out of your use of the website. We act as a hosting platform for artist content and are not the publisher of that content; the artists who upload content are responsible for it.</p>

<h5 class="fw-semibold text-purple">11. Governing Law and Jurisdiction</h5>
<p>These Terms are governed by the laws of the <strong>Republic of Uganda</strong>.
  Any dispute arising out of or in connection with these Terms shall be subject to the exclusive jurisdiction of the courts of Uganda. We will always attempt to resolve disputes amicably before resorting to court proceedings.</p>

<h5 class="fw-semibold text-purple">12. Changes to these Terms</h5>
<p>We may update these Terms from time to time. The "Last updated" date at the top indicates when the Terms were revised. Continued use of the website after changes are published constitutes acceptance of the revised Terms.</p>

<h5 class="fw-semibold text-purple">13. Contact</h5>
<p>Questions about these Terms can be sent via our <a href="%s">contact page</a>
  or by email to <a href="mailto:admin@gospelzora.com">admin@gospelzora.com</a>.</p>

<p class="small text-muted mt-4 mb-0">This page is provided for general information and is not legal advice. If you need legal guidance, please consult a qualified Ugandan attorney.</p>
TERMS_BODY, SITE_NAME, url('contact')
)) ?>
</div></div></div></div></div></section>
<?php require_once __DIR__ . '/../app/includes/public/footer.php';