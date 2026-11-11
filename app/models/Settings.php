<?php
declare(strict_types=1);

namespace App\Models;

/** Request-cached key/value settings backed by the existing settings table. */
final class Settings
{
    private \App\core\DB $db;
    /** @var array<string, string|false> */
    private array $cache = [];

    public function __construct(\App\core\DB $db)
    {
        $this->db = $db;
    }

    public function get(string $key): string|false
    {
        if (!array_key_exists($key, $this->cache)) {
            $value = $this->db->scalar('SELECT value FROM settings WHERE name = ?', [$key]);
            $this->cache[$key] = $value === null ? false : (string) $value;
        }
        return $this->cache[$key];
    }

    public function set(string $key, ?string $value): void
    {
        unset($this->cache[$key]);
        if ($value === null) {
            $this->db->execute('DELETE FROM settings WHERE name = ?', [$key]);
            return;
        }

        $this->db->execute(
            'INSERT INTO settings (name, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, $value]
        );
    }

    public function maintenance(): bool
    {
        return $this->get('maintenance_mode') === '1';
    }
}
