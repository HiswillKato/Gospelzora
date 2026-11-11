<?php
/**
 * Single page shell for routed views.
 *
 * The route table (routes/web.php) or a controller sets:
 *   $view_file  absolute path to the content view — required
 *   $shell      'public' (default) | 'member' | 'admin'
 *
 * The view runs FIRST into an output buffer, so it can set $page_title,
 * $page_description, $extra_css and $extra_js before the header/footer render.
 * A view that renders a complete document itself and calls exit (for example
 * the maintenance login card, or public/404.php) simply flushes its own
 * output; the shell below is skipped because control never returns here.
 */

if (!isset($view_file) || !is_string($view_file) || !is_file($view_file)) {
    throw new RuntimeException('Layout: $view_file is missing or unreadable.');
}

$shell = isset($shell) && is_string($shell) && $shell !== '' ? $shell : 'public';
$shell_dir = $shell === 'public' ? '' : $shell . '/';

ob_start();
require $view_file;
$content = ob_get_clean();

require __DIR__ . '/' . $shell_dir . 'header.php';
echo $content;
require __DIR__ . '/' . $shell_dir . 'footer.php';
