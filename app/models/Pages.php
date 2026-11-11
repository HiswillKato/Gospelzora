<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Pages — SEO metadata for public pages.
 *
 * This is the first legacy domain class moved into the PSR-4 App namespace.
 * It deliberately keeps the existing DB adapter and public API so old page
 * templates remain compatible during the staged migration.
 */
final class Pages
{
    private \App\core\DB $db;

    public function __construct(\App\core\DB $db)
    {
        $this->db = $db;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->select(
            'SELECT id, slug, title, description, keywords FROM pages ORDER BY slug'
        );
    }

    public function page(string $key): ?array
    {
        $row = $this->db->row('SELECT * FROM pages WHERE slug = ?', [$key]);
        return $row ?: null;
    }

    public function delete(string $key): bool
    {
        return $this->db->affected('DELETE FROM pages WHERE slug = ?', [$key]) > 0;
    }

    /** Upsert a page's SEO metadata. */
    public function save(string $key, string $title, string $description, string $keywords): array
    {
        $key = trim($key);
        if (!preg_match('~^[a-z][a-z0-9_]{0,99}$~', $key)) {
            return ['success' => false, 'message' => 'Invalid page key. Use lowercase letters, numbers and underscores.'];
        }

        $existing = (bool) $this->db->scalar('SELECT COUNT(*) FROM pages WHERE slug = ?', [$key]);
        if ($existing) {
            $this->db->execute(
                'UPDATE pages SET title = NULLIF(?, ""), description = NULLIF(?, ""), keywords = NULLIF(?, "") WHERE slug = ?',
                [$title, $description, $keywords, $key]
            );
        } else {
            $this->db->insert(
                'INSERT INTO pages (slug, title, description, keywords) VALUES (?, NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""))',
                [$key, $title, $description, $keywords]
            );
        }

        return ['success' => true, 'message' => 'Page saved.'];
    }

    /** Effective escaped metadata; field is title|description|keywords. */
    public function meta(string $key, string $field): string
    {
        $row = $this->db->row('SELECT * FROM pages WHERE slug = ?', [$key]);
        if (!$row) {
            return '';
        }

        $field = in_array($field, ['description', 'keywords'], true) ? $field : 'title';
        return \e(trim((string) ($row[$field] ?? '')));
    }

    public function keywords(string $keywords, int $maxWords = 2): string
    {
        $parts = [];
        foreach (explode(',', $keywords) as $phrase) {
            $phrase = trim($phrase);
            if ($phrase === '') {
                continue;
            }
            $words = preg_split('/\\s+/', $phrase);
            if (count($words) > $maxWords) {
                $parts[] = implode(' ', array_slice($words, 0, $maxWords)) . '…';
            } else {
                $parts[] = $phrase;
            }
        }
        return implode(', ', $parts);
    }
}
