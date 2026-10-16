<?php
/**
 * Admin account list. The table and its actions live in
 * includes/account-list.php, shared with users.php.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
$role = 'admin';
require __DIR__ . '/includes/account-list.php';
