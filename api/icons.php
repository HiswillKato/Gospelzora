<?php
/**
 * Icons API — the `icons` table as JSON for client-side icon filling and the
 * icon picker's live search.
 *
 * GET /api/icons.php          -> every icon
 * GET /api/icons.php?q=users  -> icons matching name, css or uses
 *
 * The JSON keeps the `fa_class` key: it is the response contract assets/js/app.js
 * reads. Only the database column is named `css`.
 * (e.g. fa-users -> applies to Artists, Group, Choir, Users).
 */
require_once __DIR__ . '/../app/bootstrap.php';

$q = strtolower(trim($_GET['q'] ?? ''));
$rows = icons();

$icons = [];
foreach ($rows as $name => $meta) {
    if ($q !== '') {
        $haystack = $name . ' ' . $meta['css'] . ' ' . str_replace(',', ' ', $meta['uses']);
        if (strpos(strtolower($haystack), $q) === false) continue;
    }
    $icons[$name] = [
        'fa_class' => $meta['css'],
        'uses' => usage($name) ?? [],
    ];
}

json(['icons' => $icons, 'query' => $q]);