<?php
/**
 * Artist live-search API for the admin/artist panel — returns matching active artists.
 */
require_once __DIR__ . '/../app/bootstrap.php';

if (!$user->check('admin') && !$user->check('artist')) {
    json(['artists' => []], 403);
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    json(['artists' => []]);
}

json(['artists' => $user->search($q)]);