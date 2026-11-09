<?php
/**
 * A single dashboard stat tile: the tinted glyph, the number, the label.
 * The Bootstrap column wrapper comes from the page.
 *
 * Set $stat_href to make the whole tile a link — that is how the dashboards
 * navigate, so a tile is a shortcut rather than decoration.
 *
 * @var string      $stat_label  pre-escaped label
 * @var int|string  $stat_value  formatted with number_format()
 * @var string      $stat_icon   Font Awesome name
 * @var string      $stat_color  Bootstrap contextual name: 'primary', 'danger', …
 * @var string      $stat_href   link target, '' for a plain (non-clickable) tile
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }
$stat_tag = $stat_href !== '' ? 'a' : 'div';
$stat_attrs = $stat_href !== '' ? ' href="' . e($stat_href) . '"' : '';
?>
<<?= $stat_tag ?> class="card tile stat-card h-100 text-decoration-none"<?= $stat_attrs ?>>
<div class="card-body d-flex align-items-center gap-3">
<span class="stat-icon bg-<?= e($stat_color) ?> bg-opacity-10 text-<?= e($stat_color) ?>"><?= icon($stat_icon) ?></span>
<span class="min-width-0">
<span class="d-block small text-body-secondary"><?= $stat_label ?></span>
<span class="fs-4 fw-bold"><?= number_format($stat_value) ?></span>
</span>
</div>
</<?= $stat_tag ?>>
