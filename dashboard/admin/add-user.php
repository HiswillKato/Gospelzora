<?php
/**
 * Add / edit a user account. The form itself lives in
 * includes/account-form.php, shared with add-admin.php.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
$role = 'user';
require __DIR__ . '/includes/account-form.php';
