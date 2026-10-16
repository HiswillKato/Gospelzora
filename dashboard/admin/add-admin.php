<?php
/**
 * Add / edit an admin account. The form itself lives in
 * includes/account-form.php, shared with add-user.php.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
$role = 'admin';
require __DIR__ . '/includes/account-form.php';
