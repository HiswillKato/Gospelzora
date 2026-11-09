<?php
/**
 * Admin account list. The table and its actions live in
 * includes/account-list.php, shared with users.php.
 */
require_once __DIR__ . '/../../bootstrap.php';
$role = 'admin';
require dirname(__DIR__, 3) . '/dashboard/admin/includes/account-list.php';
