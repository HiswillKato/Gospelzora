<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'app/views/auth/login.php',
    'app/views/auth/register.php',
    'app/views/auth/forgot.php',
    'app/views/auth/logout.php',
    'app/views/auth/reset.php',
    'app/views/auth/verify-email.php',
    'app/views/dashboard/partials/overview.php',
    'app/views/dashboard/partials/songs.php',
    'app/views/admin/partials/account-form.php',
    'app/views/admin/partials/account-list.php',
    'app/views/shared/member/header.php',
    'app/views/shared/member/footer.php',
];
foreach ($required as $relative) {
    if (!is_file($root . '/' . $relative)) {
        fwrite(STDERR, "FAIL: missing canonical view {$relative}" . PHP_EOL);
        exit(1);
    }
}

$authContracts = [
    'login' => '$user->login(',
    'register' => '$user->register(',
    'forgot' => '$user->recover(',
    'logout' => '$user->logout(',
    'reset' => '$user->reset(',
    'verify-email' => '$user->consume(\'email_verify\'',
];
foreach ($authContracts as $view => $contract) {
    $source = file_get_contents($root . '/app/views/auth/' . $view . '.php');
    if ($source === false || !str_contains($source, $contract)) {
        fwrite(STDERR, "FAIL: authentication view {$view} is missing its workflow implementation" . PHP_EOL);
        exit(1);
    }
    if (str_contains($source, '/app/views/auth/' . $view . '.php')) {
        fwrite(STDERR, "FAIL: authentication view {$view} contains a self-referencing compatibility include" . PHP_EOL);
        exit(1);
    }
}
if (is_dir($root . '/dashboard/includes') || is_dir($root . '/dashboard/admin/includes') || is_dir($root . '/app/includes')) {
    fwrite(STDERR, "FAIL: a retired includes directory remains" . PHP_EOL);
    exit(1);
}
echo "PASS: canonical views exist and legacy includes directories are absent" . PHP_EOL;
