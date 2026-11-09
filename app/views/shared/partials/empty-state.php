<?php
/**
 * The centered "nothing here yet" block.
 *
 * @var string $empty_icon   Font Awesome name, rendered through icon()
 * @var string $empty_title  pre-escaped display text
 * @var string $empty_text   pre-escaped, '' to omit the paragraph
 * @var string $empty_cta    raw HTML rendered after the message (e.g. a button), '' for none
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><div class="empty-state">
<div class="empty-state__icon">
    <?= icon($empty_icon) ?>
</div>
<h4><?= $empty_title ?></h4>
<?php if ($empty_text !== '') { ?>
<p><?= $empty_text ?></p>
<?php } ?>
<?= $empty_cta ?>
</div>
