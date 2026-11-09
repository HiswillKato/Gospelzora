<?php
/**
 * Add / edit an admin account. The form itself lives in
 * includes/account-form.php, shared with add-user.php.
 */
require_once __DIR__ . '/../../bootstrap.php';
$role = 'admin';
require dirname(__DIR__, 3) . '/dashboard/admin/includes/account-form.php';
