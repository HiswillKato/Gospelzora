<?php
require_once __DIR__ . '/../../app/bootstrap.php';

// Admin logout is POST-only + CSRF-protected (GET is ignored entirely).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf($_POST['csrf_token'] ?? '')) {
    redirect('login');
}

$user->logout();
redirect('login');