<?php
/**
 * Pages — SEO metadata (title/description/keywords) for public pages.
 *
 * Table: `pages` stores one row per `slug`. The metadata lives in
 * `title`/`description`/`keywords`. The site is English-only, so these columns
 * carry the single, untranslated set of SEO text.
 */

class Pages {

    private DB $db;

    public function __construct(DB $db) {
        $this->db = $db;
    }

    /** All pages (slug + SEO metadata), ordered by key. */
    public function all(): array {
        return $this->db->select(
            'SELECT id, slug, title, description, keywords
             FROM pages ORDER BY slug'
        );
    }

    public function page(string $key): ?array {
        $row = $this->db->row('SELECT * FROM pages WHERE slug = ?', [$key]);
        return $row ?: null;
    }

    public function delete(string $key): bool {
        return $this->db->affected('DELETE FROM pages WHERE slug = ?', [$key]) > 0;
    }

    /**
     * Upsert a page's SEO metadata. Creates the page when the key is new, updates it otherwise.
     */
    public function save(string $key, string $title, string $description, string $keywords): array {
        $key = trim($key);
        if (!preg_match('~^[a-z][a-z0-9_]{0,99}$~', $key)) {
            return ['success' => false, 'message' => "Invalid page key. Use lowercase letters, numbers and underscores."];
        }

        $existing = (bool)$this->db->scalar('SELECT COUNT(*) FROM pages WHERE slug = ?', [$key]);

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
        return ['success' => true, 'message' => "Page saved."];
    }

    /**
     * Effective metadata for a page. Field is one of title|description|keywords.
     */
    public function meta(string $key, string $field): string {
        $row = $this->db->row('SELECT * FROM pages WHERE slug = ?', [$key]);
        if (!$row) {
            return '';
        }
        $field = in_array($field, ['description', 'keywords'], true) ? $field : 'title';
        return e(trim((string)($row[$field] ?? '')));
    }

    /**
     * Short display form of a keywords string for list tables. Every comma-separated keyword phrase
     * longer than $maxWords words is shown as its first $maxWords words followed by an ellipsis,
     * and the parts are re-joined with ", ". Display only — the stored value is untouched and stays
     * available (e.g. as a tooltip) on the same row.
     */
    public function keywords(string $keywords, int $maxWords = 2): string {
        $parts = [];
        foreach (explode(',', $keywords) as $phrase) {
            $phrase = trim($phrase);
            if ($phrase === '') {
                continue;
            }
            $words = preg_split('/\s+/', $phrase);
            if (count($words) > $maxWords) {
                $parts[] = implode(' ', array_slice($words, 0, $maxWords)) . '…';
            } else {
                $parts[] = $phrase;
            }
        }
        return implode(', ', $parts);
    }
}
