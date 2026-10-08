<?php
require_once __DIR__ . '/../app/bootstrap.php';

// Logout is a POST-only, CSRF-protected action. GET requests (legacy links,
// prefetches, embedded images) are ignored so a page can't log someone out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf($_POST['csrf_token'] ?? '')) {
    redirect();
}

$user->logout();
flash('success', 'You have been logged out.');
redirect();