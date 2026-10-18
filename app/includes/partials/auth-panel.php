<?php
/**
 * The centred account card shared by login, register, forgot, reset and
 * verify-email.
 *
 * A page sets the heading values and hands its inner markup in as $auth_body
 * (usually captured with ob_start()/ob_get_clean()); the section, container,
 * column and card are printed here once so the five pages cannot drift apart.
 *
 * @var string $auth_icon    Font Awesome name shown above the heading
 * @var string $auth_icon_class  colour/font-size classes for that glyph
 * @var string $auth_title   pre-escaped heading
 * @var string $auth_text    pre-escaped sub-line, '' to omit
 * @var string $auth_body    raw HTML for the card body
 * @var string $auth_footer  raw HTML for the line after the form, '' for none
 * @var bool   $auth_center  true centres the body (verify-email)
 * @var bool   $auth_plain   true drops the site section wrapper (maintenance login)
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }
$auth_center = $auth_center ?? false;
$auth_plain = $auth_plain ?? false;
$auth_footer = $auth_footer ?? '';
$auth_text = $auth_text ?? '';
?>
<?php if (!$auth_plain) { ?><section class="py-5"><div class="container"><div class="row justify-content-center"><div class="col-md-6 col-lg-5"><?php } ?>
<div class="card tile">
<div class="card-body p-4 p-md-5<?= $auth_center ? ' text-center' : '' ?>">
<div class="text-center mb-4">
<?= icon($auth_icon, $auth_icon_class) ?>
<h1 class="fw-bold mt-2 fs-2"><?= $auth_title ?></h1>
<?php if ($auth_text !== '') { ?><p class="text-body-secondary mb-0"><?= $auth_text ?></p><?php } ?>
</div>
<?= $auth_body ?>
<?php if ($auth_footer !== '') { ?><p class="text-center mt-4 mb-0 text-body-secondary"><?= $auth_footer ?></p><?php } ?>
</div>
</div>
<?php if (!$auth_plain) { ?></div></div></div></section><?php } ?>
