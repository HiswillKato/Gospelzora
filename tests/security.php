<?php
declare(strict_types=1);

define('TRUSTED_PROXY_IPS', ['203.0.113.10', '10.0.0.2']);
require_once dirname(__DIR__) . '/app/functions.php';

function assertIp(string $expected, string $message): void
{
    $actual = ip();
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$message}; expected {$expected}, got {$actual}" . PHP_EOL);
        exit(1);
    }
    echo "PASS: {$message}" . PHP_EOL;
}

$_SERVER = [
    'REMOTE_ADDR' => '198.51.100.5',
    'HTTP_CF_CONNECTING_IP' => '192.0.2.99',
    'HTTP_X_FORWARDED_FOR' => '192.0.2.88',
];
assertIp('198.51.100.5', 'untrusted peers cannot spoof forwarded client IP');

$_SERVER = [
    'REMOTE_ADDR' => '203.0.113.10',
    'HTTP_CF_CONNECTING_IP' => '192.0.2.44',
    'HTTP_X_FORWARDED_FOR' => '192.0.2.33',
];
assertIp('192.0.2.44', 'trusted Cloudflare peer may supply the client IP');

$_SERVER = [
    'REMOTE_ADDR' => '203.0.113.10',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.90, 10.0.0.2',
];
assertIp('198.51.100.90', 'trusted proxy chain skips configured intermediate proxies');

$_SERVER = ['REMOTE_ADDR' => 'not-an-ip'];
assertIp('0.0.0.0', 'invalid remote address is rejected');
