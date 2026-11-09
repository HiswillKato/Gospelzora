<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sql = file_get_contents($root . '/database.sql');
$installer = file_get_contents($root . '/install.php');
if ($sql === false || $installer === false) {
    fwrite(STDERR, "FAIL: schema or installer file is unavailable" . PHP_EOL);
    exit(1);
}

$schemaTables = [];
preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`([^`]+)`/i', $sql, $matches);
$schemaTables = $matches[1] ?? [];
if (!$schemaTables) {
    fwrite(STDERR, "FAIL: no non-destructive schema table definitions found" . PHP_EOL);
    exit(1);
}

if (preg_match('/^\s*(?:USE\s+|DROP TABLE\s+IF EXISTS)/mi', $sql)) {
    fwrite(STDERR, "FAIL: schema must not select a hardcoded database or drop existing tables" . PHP_EOL);
    exit(1);
}

if (!preg_match('/\$all\s*=\s*\[(.*?)\];/s', $installer, $tableListMatch)) {
    fwrite(STDERR, "FAIL: installer required-table list was not found" . PHP_EOL);
    exit(1);
}
preg_match_all("/'([^']+)'/", $tableListMatch[1], $installerMatches);
$installerTables = $installerMatches[1] ?? [];
sort($schemaTables);
sort($installerTables);
if ($schemaTables !== $installerTables) {
    fwrite(STDERR, "FAIL: installer table checks do not match the schema" . PHP_EOL);
    fwrite(STDERR, "Schema: " . implode(', ', $schemaTables) . PHP_EOL);
    fwrite(STDERR, "Installer: " . implode(', ', $installerTables) . PHP_EOL);
    exit(1);
}
echo "PASS: installer checks every schema table and schema bootstrap is non-destructive" . PHP_EOL;
