<?php
/**
 * Apply pending schema migrations for Gospelzora.
 *
 * Usage: php tools/apply-migrations.php
 *
 * This script:
 * 1. Checks which migrations have already been applied (via schema_migrations table)
 * 2. Scans db/migrations/ directory for versioned SQL files
 * 3. Applies any pending migrations in order
 * 4. Records each applied version in schema_migrations
 *
 * NOTE: This is intended for moving toward production. During active development,
 * the CREATE TABLE IF NOT EXISTS in database.sql is sufficient.
 */

require_once __DIR__ . '/../app/bootstrap.php';

$base = BASE_PATH;

// 1. Get already-applied versions from database
$db = new \App\core\Database($config['database']);
$applied = $db->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied_set = array_values(array_unique($applied));

// 2. Scan migration files
$migrations_dir = $base . '/db/migrations';
$version_pattern = '/^(\d+)_.*\.sql$/i';

$files = [];
if (is_dir($migrations_dir)) {
    foreach (scandir($migrations_dir) as $file) {
        if (preg_match($version_pattern, $file, $m)) {
            $version = $m[1];
            // Avoid duplicate versions
            if (!in_array($version, $applied_set)) {
                $files[$version] = $m[0];
            }
        }
    }
}

// Sort by version number
ksort($files);

// 3. Apply pending migrations
$applied_versions = [];

foreach ($files as $version => $filename) {
    $filepath = $migrations_dir . '/' . $filename;
    $sql = file_get_contents($filepath);

    if ($sql === false || $sql === '') {
        error_log("Migration $version: empty or missing file $filename");
        continue;
    }

    // Execute via multi_query (database.sql uses this pattern)
    try {
        $success = $db->query($sql);
        if ($success === false) {
            error_log("Migration $version: SQL error - " . $db->error ?? 'unknown');
            continue;
        }
    } catch (\Throwable $e) {
        error_log("Migration $version: exception - " . $e->getMessage());
        continue;
    }

    // Record the applied version
    $db->query(
        "INSERT IGNORE INTO schema_migrations (version) VALUES (?)",
        [$version]
    );

    $applied_versions[] = $version;
    error_log("Migration applied: v{$version} ({$filename})");
}

// 4. Summary
if (empty($applied_versions)) {
    echo "No pending migrations. All versions are up to date.\n";
} else {
    echo "Applied " . count($applied_versions) . " migration(s):\n";
    foreach ($applied_versions as $v) {
        echo "  - v{$v}\n";
    }
}

// Close connection (PDO cleanup is handled by PHP shutdown)
echo "Schema migration check complete.\n";