<?php
/**
 * Search API
 */
require_once __DIR__ . '/../app/bootstrap.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    json(['songs' => [], 'artists' => []]);
}

json($music->search($q, true));