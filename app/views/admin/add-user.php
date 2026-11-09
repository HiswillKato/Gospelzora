<?php
/**
 * Add / edit a user account. The form itself lives in
 * includes/account-form.php, shared with add-admin.php.
 */
require_once __DIR__ . '/../../bootstrap.php';
$role = 'user';
require dirname(__DIR__, 3) . '/dashboard/admin/includes/account-form.php';
