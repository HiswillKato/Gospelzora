<?php
/**
 * Runtime app settings — a tiny key/value store on the `settings` table. Values are plain strings;
 * the `maintenance_mode` key ('1'/'0') drives the maintenance gate in app/bootstrap.php. Lookups
 * are cached for the request.
 */

class Settings {
    private DB $db;

    /** Per-request cache: key => value (string|false when not found). */
    private array $cache = [];

    public function __construct(DB $db) {
        $this->db = $db;
    }

    public function get(string $key): string|false {
        if (!array_key_exists($key, $this->cache)) {
            $value = $this->db->scalar("SELECT value FROM settings WHERE name = ?", [$key]);
            $this->cache[$key] = $value === null ? false : (string)$value;
        }
        return $this->cache[$key];
    }

    public function set(string $key, ?string $value): void {
        unset($this->cache[$key]);
        if ($value === null) {
            $this->db->execute("DELETE FROM settings WHERE name = ?", [$key]);
            return;
        }
        $this->db->execute(
            "INSERT INTO settings (name, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$key, $value]
        );
    }

    /** Maintenance mode: every non-admin request is stopped by the bootstrap gate. */
    public function maintenance(): bool {
        return $this->get('maintenance_mode') === '1';
    }

}
