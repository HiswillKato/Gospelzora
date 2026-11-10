<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Music — song, category & favorites management.
 *
 * Instance-driven: the shared `$music` built in app/bootstrap.php — `ranked('latest')`,
 * `save($data)`, etc.
 */

class Music {

    private \DB $db;
    private Analytics $analytics;
    private User $user;

    public function __construct(\DB $db, Analytics $analytics, User $user) {
        $this->db = $db;
        $this->analytics = $analytics;
        $this->user = $user;
    }

    /* ---------------------------------------------------------- */
    /* Shared SQL fragments (used by the song queries below)      */
    /* ---------------------------------------------------------- */

    /** Left join that pulls the artist's display name for a song. */
    private const ARTIST_JOIN = 'LEFT JOIN users u ON s.artist = u.id';

    /** FROM + join for the standard "joined song list" query. */
    private const SONG_JOIN = 'FROM songs s ' . self::ARTIST_JOIN;

    /** Song SELECT columns including the artist name (lists & grids). */
    private const SONG_SELECT = 'SELECT s.*, u.name as artist_name';

    /** Song SELECT columns including artist name, slug and image (song page). */
    private const SONG_SELECT_ARTIST = 'SELECT s.*, u.name as artist_name, u.slug as artist_slug, u.image as artist_image';

    /** Homepage rail orderings: logical name => ORDER BY fragment. */
    private const SONG_ORDER = [
        'latest'  => 's.created DESC',
        'popular' => 's.plays DESC',
    ];

    /**
     * Finds one favorite row (used by favorited / toggle). Favorites can be owned by
     * users OR artists (PK ids collide across those tables), so the owner's account type is part of
     * the unique key.
     */
    private function marked(int $userId, string $type, int $songId): ?array {
        return $this->db->row(
            "SELECT id FROM favorites WHERE user = ? AND type = ? AND song = ?",
            [$userId, $type, $songId]
        );
    }

    /* ---------------------------------------------------------- */
    /* Song lookups                                               */
    /* ---------------------------------------------------------- */

    /**
     * Published songs for the homepage rails, newest-first or most-played. `$order` is a logical
     * key from SONG_ORDER (latest|popular) so no caller can inject SQL; an unknown key returns
     * nothing.
     */
    public function ranked(string $order = 'latest', int $limit = 8): array {
        if (!isset(self::SONG_ORDER[$order])) return [];
        return $this->db->select(
            self::SONG_SELECT . ' ' . self::SONG_JOIN .
            " WHERE s.status = 'published' ORDER BY " . self::SONG_ORDER[$order] . " LIMIT $limit"
        );
    }

    /** Public song lookup by slug or numeric id (published only). */
    public function song(mixed $value): ?array {
        ['column' => $column, 'param' => $param] = resolve($value);
        return $this->db->row(
            self::SONG_SELECT_ARTIST . ' ' . self::SONG_JOIN .
            " WHERE s.$column = ? AND s.status = 'published' LIMIT 1",
            [$param]
        );
    }

    /** Edit lookup for the admin/artist panel — optional ownership scope. */
    public function find(int $id, ?int $ownerArtistId = null): ?array {
        if ($ownerArtistId !== null) {
            return $this->db->row("SELECT * FROM songs WHERE id = ? AND artist = ?", [$id, $ownerArtistId]);
        }
        return $this->db->row("SELECT * FROM songs WHERE id = ?", [$id]);
    }

    /** Song lookup for the download handler, with the artist name. */
    public function download(mixed $value): ?array {
        ['column' => $column, 'param' => $param] = resolve($value);
        return $this->db->row(
            self::SONG_SELECT . ' ' . self::SONG_JOIN .
            " WHERE s.$column = ? AND s.status = 'published' LIMIT 1",
            [$param]
        );
    }

    public function filename(array $song): string {
        $title = trim((string)($song['title'] ?? 'song'));
        $artist = trim((string)($song['artist_name'] ?? 'artist'));
        $extension = strtolower(pathinfo((string)($song['audio'] ?? ''), PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';
        $filename = trim($title . ' - ' . $artist);
        $filename = str_replace(['"', '\\', '/'], '', $filename);
        $filename = preg_replace('/[\x00-\x1F\x7F]+/', '', $filename) ?? $filename;
        $filename = trim($filename, " .\t\n\r\0\x0B");
        if ($filename === '') {
            $filename = 'song';
        }
        if ($extension !== '') {
            $filename .= '.' . $extension;
        }
        return $filename;
    }

    /**
     * Filtered, paginated catalog for the Music page. Filters: q, category, artist, sort
     * ('latest'|'popular'|'title'), page.
     */
    public function catalog(array $filters = []): array {
        $q = trim($filters['q'] ?? '');
        $category = (int)($filters['category'] ?? 0);
        $artist = (int)($filters['artist'] ?? 0);
        $sort = $filters['sort'] ?? 'latest';
        $page = max(1, (int)($filters['page'] ?? 1));

        $where = ["s.status = 'published'"];
        $params = [];
        if ($q) {
            $where[] = "(s.title LIKE ? OR u.name LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        if ($category) {
            $where[] = "s.category = ?";
            $params[] = $category;
        }
        if ($artist) {
            $where[] = "s.artist = ?";
            $params[] = $artist;
        }

        $order = match ($sort) {
            'popular' => 's.plays DESC',
            'title' => 's.title ASC',
            default => 's.created DESC',
        };

        $whereSql = implode(' AND ', $where);

        $total = (int)$this->db->scalar("SELECT COUNT(*) " . self::SONG_JOIN . " WHERE $whereSql", $params);
        $pagination = paginate($total, $page);

        $songs = $this->db->select(
            self::SONG_SELECT . ' ' . self::SONG_JOIN .
            " WHERE $whereSql ORDER BY $order LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
            $params
        );

        return ['songs' => $songs, 'total' => $total, 'pagination' => $pagination];
    }

    public function related(int $songId, int $artistId, int $limit = 6): array {
        return $this->db->select(
            self::SONG_SELECT . ' ' . self::SONG_JOIN .
            " WHERE s.artist = ? AND s.id != ? AND s.status = 'published'
             ORDER BY s.plays DESC LIMIT $limit",
            [$artistId, $songId]
        );
    }

    public function songs(int $artistId, string $status = 'published'): array {
        return $this->db->select(
            "SELECT s.* FROM songs s WHERE s.artist = ? AND s.status = ? ORDER BY s.plays DESC",
            [$artistId, $status]
        );
    }

    /* ---------------------------------------------------------- */
    /* Search                                                     */
    /* ---------------------------------------------------------- */

    /**
     * Public site search. $slim=true returns the reduced row shape the AJAX endpoint wants
     * (id/title/artist only, tighter limits, no categories, no log entry).
     */
    public function search(string $q, bool $slim = false): array {
        if ($slim) {
            $like = '%' . $q . '%';

            $songs = $this->db->select(
                "SELECT s.id, s.title, u.name as artist " . self::SONG_JOIN .
                " WHERE s.status = 'published' AND (s.title LIKE ? OR u.name LIKE ?) LIMIT 10",
                [$like, $like]
            );

            $artists = $this->db->select(
                "SELECT id, name FROM users WHERE role = 'artist' AND status = 'active' AND name LIKE ? AND
                 EXISTS (SELECT 1 FROM songs s WHERE s.artist = users.id AND s.status = 'published') LIMIT 5",
                [$like]
            );

            return ['songs' => $songs, 'artists' => $artists];
        }

        $songLimit = 24;
        $artistLimit = 12;
        $categoryLimit = 8;
        $like = '%' . $q . '%';

        $songs = $this->db->select(
            self::SONG_SELECT . ' ' . self::SONG_JOIN .
            " WHERE s.status = 'published' AND (s.title LIKE ? OR u.name LIKE ?)
             ORDER BY s.plays DESC LIMIT $songLimit",
            [$like, $like]
        );

        $artists = $this->db->select(
            "SELECT * FROM users WHERE role = 'artist' AND status = 'active' AND name LIKE ? AND
             EXISTS (SELECT 1 FROM songs s WHERE s.artist = users.id AND s.status = 'published') LIMIT $artistLimit",
            [$like]
        );

        $categories = $this->db->select(
            "SELECT * FROM categories WHERE name LIKE ? LIMIT $categoryLimit",
            [$like]
        );

        if (trim($q) !== '') {
            $this->analytics->log('search.search', sprintf("Query: %s - %s song(s)", trim($q), count($songs)));
        }

        return ['songs' => $songs, 'artists' => $artists, 'categories' => $categories];
    }

    /* ---------------------------------------------------------- */
    /* Song management                                            */
    /* ---------------------------------------------------------- */

    /**
     * Create or update a song. Accepts $_FILES['audio'] / $_FILES['artwork'] under the `audio` /
     * `artwork` keys. Pass $ownerArtistId when acting as an artist (self-uploads become 'pending'
     * and edits are ownership-scoped).
     */
    /**
     * Create or update a song or a category. $type is 'song' or 'category'; the third
     * argument is the artist owner for a song (self-uploads become 'pending' and edits are
     * ownership-scoped) or the row id being edited for a category.
     */
    public function save(string $type, array $data, ?int $ownerArtistId = null): array {
        if ($type === 'category') {
            $id = $ownerArtistId;
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $icon = trim($data['icon'] ?? 'fa-music');
            $status = $data['status'] ?? null;
            if ($status !== null && !in_array($status, ['active', 'inactive'], true)) {
                $status = null;
            }
            if (!$name) {
                return ['success' => false, 'message' => "Please enter a name."];
            }
            if (mb_strlen($name) > 100) {
                return ['success' => false, 'message' => "Name must be 100 characters or fewer."];
            }
            if (mb_strlen($icon) > 50) {
                return ['success' => false, 'message' => "Icon name must be 50 characters or fewer."];
            }

            $slug = slug($name, 'categories', $id ?: 0);

            if ($id) {
                if ($status === null) {
                    $this->db->execute(
                        "UPDATE categories SET name = ?, slug = ?, description = ?, icon = ? WHERE id = ?",
                        [$name, $slug, $description, $icon, $id]
                    );
                } else {
                    $this->db->execute(
                        "UPDATE categories SET name = ?, slug = ?, description = ?, icon = ?, status = ? WHERE id = ?",
                        [$name, $slug, $description, $icon, $status, $id]
                    );
                }
            } else {
                $this->db->execute(
                    "INSERT INTO categories (name, slug, description, icon, status) VALUES (?, ?, ?, ?, ?)",
                    [$name, $slug, $description, $icon, $status ?? 'active']
                );
            }

            if ($this->db->errno() === 1062) {
                return ['success' => false, 'message' => "A category with that name already exists."];
            }
            if ($this->db->errno()) {
                return ['success' => false, 'message' => "The category could not be saved. Please try again."];
            }
            $this->analytics->log(
                $id ? 'category.update' : 'category.create',
                sprintf("Category %s: %s", $id ? 'updated' : 'created', $name)
            );
            return ['success' => true, 'message' => "Category saved."];
        }

        if ($type !== 'song') {
            return ['success' => false, 'message' => "Unknown record type."];
        }

        $id = (int)($data['id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $duration = duration((string)($data['duration'] ?? 0), true);
        $categoryId = $this->categorize($data['category'] ?? 0);
        $artistMode = $ownerArtistId !== null && $ownerArtistId > 0;
        $artistId = $artistMode ? $ownerArtistId : (int)($data['artist'] ?? 0);
        $status = $artistMode
            ? 'pending'
            : (in_array($data['status'] ?? '', ['pending', 'published', 'draft', 'requested', 'approved'], true) ? $data['status'] : 'draft');

        $audioFile = $data['existing_audio'] ?? '';
        $artwork = $data['existing_artwork'] ?? '';
        $err = '';

        if (mb_strlen($title) > 200) {
            return ['success' => false, 'message' => "Title must be 200 characters or fewer."];
        }
        if ($title === '' || $artistId <= 0 || $description === '') {
            return ['success' => false, 'message' => "Please provide a title, an artist and a description."];
        }

        // Resolve the artist and song slug up front — the audio filename is
        // derived from them (e.g. amazing-grace-hiswill.mp3).
        $artist = $this->db->row("SELECT name FROM users WHERE id = ? AND role = 'artist'", [$artistId]);
        if (!$artist) {
            return ['success' => false, 'message' => "Please select a valid artist."];
        }
        // Slug is the song title plus the artist name (e.g.
        // "amazing-grace-hiswill") so it reads differently across artists.
        $slug = slug($title . ' ' . $artist['name'], 'songs', $id ?: 0);

        // The audio file the song currently points at (before any UPDATE) —
        // the only file that may safely be removed when it gets replaced.
        // The POST `existing_audio` hint is never trusted for deletion.
        $previousAudio = '';
        $previousArtwork = '';
        if ($id) {
            $prev = $this->db->row(
                $artistMode
                    ? "SELECT audio, artwork FROM songs WHERE id = ? AND artist = ?"
                    : "SELECT audio, artwork FROM songs WHERE id = ?",
                $artistMode ? [$id, $artistId] : [$id]
            );
            if (!$prev) {
                return ['success' => false, 'message' => "That song could not be found."];
            }
            $previousAudio = (string)($prev['audio'] ?? '');
            $previousArtwork = (string)($prev['artwork'] ?? '');
        }

        // Files THIS request writes. If the row below never lands they are
        // removed again, so a failed save cannot orphan them on disk.
        $stored = [];

        // Audio upload (keeps the existing file when none was uploaded).
        // store() validates the file first, and only then saves it to
        // the audio folder — nothing is written on its own.
        $audioUpload = $data['audio'] ?? null;
        $replacedAudio = false;
        if (is_array($audioUpload) && !empty($audioUpload['name'])) {
            $ext = strtolower(pathinfo($audioUpload['name'], PATHINFO_EXTENSION));
            // The slug already carries the artist name, so it is the filename.
            $audioName = substr($slug, 0, 100) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $upload = store($audioUpload, AUDIO_PATH . '/', 'audio', 'audio', $audioName);
            if ($upload['error'] === null) {
                $audioFile = 'audio/' . $upload['path'];
                $replacedAudio = true;
                $stored[] = $audioFile;
            } else {
                $err = $upload['error'];
            }
        }

        // Artwork upload (keeps the existing file when none was uploaded)
        $artworkUpload = $data['artwork'] ?? null;
        if (is_array($artworkUpload) && !empty($artworkUpload['name'])) {
            $upload = store($artworkUpload, ARTWORK_PATH . '/', 'image', 'artwork');
            if ($upload['error'] === null) {
                $artwork = 'images/artwork/' . $upload['path'];
                $stored[] = $artwork;
            } else {
                $err = $upload['error'];
            }
        }

        if ($err) {
            foreach ($stored as $path) $this->unlink($path);
            return ['success' => false, 'message' => $err];
        }
        // 'requested'/'approved' songs genuinely have no audio yet — only
        // the playable statuses require a file.
        $needsAudio = in_array($status, ['published', 'pending', 'draft'], true);
        if ($needsAudio && !$audioFile) {
            foreach ($stored as $path) $this->unlink($path);
            return ['success' => false, 'message' => "Please provide an audio file for this song."];
        }

        try {
            if ($id) {
                if ($artistMode) {
                    $saved = $this->db->execute(
                        "UPDATE songs SET title = ?, slug = ?, description = ?, audio = ?, artwork = ?,
                            duration = ?, category = ?, status = ?, updated = NOW() WHERE id = ? AND artist = ?",
                        [$title, $slug, $description, $audioFile, $artwork, $duration, $categoryId, $status, $id, $artistId]
                    );
                } else {
                    $saved = $this->db->execute(
                        "UPDATE songs SET artist = ?, title = ?, slug = ?, description = ?, audio = ?, artwork = ?,
                            duration = ?, category = ?, status = ?, updated = NOW() WHERE id = ?",
                        [$artistId, $title, $slug, $description, $audioFile, $artwork, $duration, $categoryId, $status, $id]
                    );
                }
                $songId = $saved && !$this->db->errno() ? $id : 0;
            } else {
                $songId = $this->db->insert(
                    "INSERT INTO songs (artist, category, title, slug, description, audio, artwork, duration, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$artistId, $categoryId, $title, $slug, $description, $audioFile, $artwork, $duration, $status]
                );
            }
        } catch (\Throwable $e) {
            foreach ($stored as $path) $this->unlink($path);
            throw $e;
        }

        // A write that did not land is not a save: report it and take back the
        // files just written, rather than claiming success with a phantom row.
        if ($this->db->errno() !== 0 || $songId <= 0) {
            foreach ($stored as $path) {
                $this->unlink($path);
            }
            return ['success' => false, 'message' => "The song could not be saved. Please try again."];
        }

        // Once the replacement is safely recorded, remove the previous version's
        // audio file — sourced from the DB row above, never from the request.
        if ($replacedAudio && $previousAudio !== '' && $previousAudio !== $audioFile) {
            $this->unlink($previousAudio);
        }
        if (is_array($artworkUpload) && !empty($artworkUpload['name']) && $previousArtwork !== '' && $previousArtwork !== $artwork) {
            $this->unlink($previousArtwork);
        }

        $this->analytics->log($id ? 'song.update' : 'song.create', sprintf("Song #%s \"%s\" (status: %s)", $songId, $title, $status));

        return ['success' => true, 'message' => "Song saved.", 'id' => $songId];
    }

    /**
     * Set a row's status. $type is 'song' (published|draft|pending) or 'category' (active|inactive);
     * the two vocabularies are deliberately different and an unknown type is refused. Returns whether
     * the write landed.
     */
    public function status(string $type, int $id, string $status): bool {
        if ($type === 'category') {
            if (!in_array($status, ['active', 'inactive'], true)) return false;
            $this->db->execute("UPDATE categories SET status = ? WHERE id = ?", [$status, $id]);
            if ($this->db->errno()) return false;
            $cat = $this->db->row("SELECT name FROM categories WHERE id = ?", [$id]);
            if ($cat) {
                $this->analytics->log(
                    $status === 'active' ? 'category.enable' : 'category.disable',
                    sprintf("Category %s: %s", $status === 'active' ? 'enabled' : 'disabled', $cat['name'])
                );
            }
            return true;
        }

        if ($type !== 'song') return false;
        $status = in_array($status, ['published', 'draft', 'pending'], true) ? $status : 'draft';
        $updated = $this->db->execute("UPDATE songs SET status = ?, updated = NOW() WHERE id = ?", [$status, $id]);
        if (!$updated || $this->db->errno()) return false;
        $this->analytics->log('song.status', sprintf("Song #%s -> %s", $id, $status));
        return true;
    }

    /* ---------------------------------------------------------- */
    /* Song requests (members → artists, no file upload)          */
    /* ---------------------------------------------------------- */

    /**
     * A member asks for a song to be added. Creates a `songs` row with status 'requested' (no
     * audio/artwork); an admin fulfills it by editing the song in the admin panel (upload audio →
     * publish). Only regular user accounts may request (guard in the caller too).
     */
    public function request(array $data, int $userId): array {
        $title = trim($data['title'] ?? '');
        $artistId = (int)($data['artist'] ?? 0);
        $categoryId = (int)($data['category'] ?? 0);
        $description = trim($data['description'] ?? '');

        $titleLen = mb_strlen($title);
        if ($titleLen < 2 || $titleLen > 200) {
            return ['success' => false, 'message' => "Please enter a title for your song request."];
        }
        if (mb_strlen($description) > 2000) {
            return ['success' => false, 'message' => "Your note must be 300 characters or fewer."];
        }
        $artist = $this->db->row("SELECT name FROM users WHERE id = ? AND role = 'artist'", [$artistId]);
        if (!$artist) {
            return ['success' => false, 'message' => "Please choose an artist for your song request."];
        }
        // Category is optional, but the column is NOT NULL: "no choice" is 0, not NULL.
        $categoryId = $this->categorize($categoryId);
        $duplicate = $this->db->row(
            "SELECT id FROM songs WHERE artist = ? AND title = ? AND status = 'requested' LIMIT 1",
            [$artistId, $title]
        );
        if ($duplicate) {
            return ['success' => false, 'message' => "You have already requested that song."];
        }

        $songId = $this->db->insert(
            "INSERT INTO songs (artist, category, title, slug, description, audio, status, requester)
             VALUES (?, ?, ?, ?, ?, '', 'requested', ?)",
            [$artistId, $categoryId, $title, slug($title . ' ' . $artist['name'], 'songs', 0), $description, $userId]
        );
        if ($songId <= 0) {
            return ['success' => false, 'message' => "Your request could not be saved. Please try again."];
        }

        $this->analytics->log('song.request', sprintf("Song requested: #%s \"%s\" by %s", $songId, $title, $artist['name']));
        return ['success' => true, 'message' => "Song request sent. Thank you!", 'id' => $songId];
    }

    /** Requested songs (status 'requested'). Scoped to one requester when given. */
    public function requests(?int $requesterId = null): array {
        $sql = "SELECT s.*, u.name AS artist_name FROM songs s " . self::ARTIST_JOIN . " WHERE s.status = 'requested'";
        $params = [];
        if ($requesterId !== null) {
            $sql .= " AND s.requester = ?";
            $params[] = $requesterId;
        }
        return $this->db->select($sql . ' ORDER BY s.created DESC', $params);
    }

    /**
     * Admin approves a member's request: a `requested` song becomes `approved`, handing the artist
     * the right to upload its audio. The upload (publish) is what publishes it.
     */
    public function approve(int $songId): array {
        $song = $this->db->row("SELECT id, title FROM songs WHERE id = ? AND status = 'requested' LIMIT 1", [$songId]);
        if (!$song) {
            return ['success' => false, 'message' => "That song could not be found."];
        }
        $this->db->execute(
            "UPDATE songs SET status = 'approved', updated = NOW() WHERE id = ?",
            [$song['id']]
        );
        $this->analytics->log('song.request.approve', sprintf("Song request approved: #%s \"%s\"", $song['id'], $song['title']));
        return ['success' => true, 'message' => "Song request approved.", 'id' => $song['id']];
    }

    /** An admin-approved song the given artist may now upload audio for. */
    public function approved(int $songId, int $artistId): ?array {
        return $this->db->row(
            "SELECT s.*, u.name AS artist_name FROM songs s " . self::ARTIST_JOIN .
            " WHERE s.id = ? AND s.artist = ? AND s.status = 'approved' LIMIT 1",
            [$songId, $artistId]
        );
    }

    /**
     * The artist uploads audio for an admin-approved song. The file is validated and stored like
     * any other upload; on success the song is published immediately (admin approval was the gate).
     */
    public function publish(int $songId, int $artistId, array $file): array {
        $song = $this->approved($songId, $artistId);
        if (!$song) {
            return ['success' => false, 'message' => "That approved song could not be found."];
        }

        if (empty($file['name'])) {
            return ['success' => false, 'message' => "Please choose an audio file to upload."];
        }
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => "The upload failed. Please try again."];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // Requested-song slugs already include the artist name; the approved
        // slug is inherited, so it is the audio filename.
        $audioName = substr($song['slug'], 0, 100) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;

        $upload = store($file, AUDIO_PATH . '/', 'audio', 'audio', $audioName);
        if ($upload['error'] !== null) {
            return ['success' => false, 'message' => $upload['error']];
        }
        $audioFile = 'audio/' . $upload['path'];

        try {
            $updated = $this->db->affected(
                "UPDATE songs SET audio = ?, status = 'published', updated = NOW() WHERE id = ? AND artist = ? AND status = 'approved'",
                [$audioFile, $songId, $artistId]
            );
        } catch (\Throwable $e) {
            $this->unlink($audioFile);
            throw $e;
        }
        if ($updated !== 1 || $this->db->errno()) {
            $this->unlink($audioFile);
            return ['success' => false, 'message' => "The song could not be published. Please try again."];
        }
        $this->analytics->log('song.request.upload', sprintf("Artist uploaded audio for requested song #%s \"%s\"", $songId, $song['title']));
        return ['success' => true, 'message' => "Audio uploaded and the song is now published.", 'id' => $songId];
    }

    /**
     * Delete a song or a category. $type is 'song' (with an optional ownership scope) or 'category'.
     */
    public function delete(string $type, int $id, ?int $ownerArtistId = null): void {
        if ($type === 'category') {
            $cat = $this->db->row("SELECT name FROM categories WHERE id = ?", [$id]);
            $deleted = $this->db->affected("DELETE FROM categories WHERE id = ?", [$id]);
            if ($deleted > 0 && $cat) {
                $this->analytics->log('category.delete', sprintf("Category deleted: %s", $cat['name']));
            }
            return;
        }
        if ($type !== 'song') return;

        $where = $ownerArtistId !== null ? 'id = ? AND artist = ?' : 'id = ?';
        $params = $ownerArtistId !== null ? [$id, $ownerArtistId] : [$id];
        $song = $this->db->row("SELECT title, audio, artwork FROM songs WHERE $where", $params);
        if (!$song) return;

        $deleted = $this->db->affected("DELETE FROM songs WHERE $where", $params);
        if ($deleted <= 0 || $this->db->errno()) return;

        // Remove files only after the database row is confirmed deleted.
        if (!empty($song['audio'])) $this->unlink((string)$song['audio']);
        if (!empty($song['artwork'])) $this->unlink((string)$song['artwork']);
        $this->analytics->log('song.delete', sprintf("Song #%s \"%s\"", $id, $song['title']));
    }

    /**
     * Best-effort removal of a stored file. Stored paths are relative to assets/
     * (`audio/<filename>`, `images/artwork/<filename>`); only files that resolve inside
     * ASSETS_PATH are ever touched, so arbitrary paths cannot be fed in here.
     */
    private function unlink(string $path): void {
        if (\\App\\Services\\ObjectStorage::enabled()) {
            if (!\\App\\Services\\ObjectStorage::isMediaPath($path)) return;
            try {
                \\App\\Services\\ObjectStorage::instance()->delete($path);
            } catch (\\Throwable $error) {
                error_log('Object Storage deletion failed: ' . $error->getMessage());
            }
            return;
        }

        $real = realpath(ASSETS_PATH . '/' . ltrim(str_replace('\\\\', '/', $path), '/'));
        $root = realpath(ASSETS_PATH);
        if ($real === false || $root === false || !str_starts_with($real, $root . '/')) {
            return;
        }
        @unlink($real);
    }
    /** Find a category by slug or numeric id. */
    public function category(mixed $value): ?array {
        ['column' => $column, 'param' => $param] = resolve($value);
        return $this->db->row(
            "SELECT * FROM categories WHERE $column = ? LIMIT 1",
            [$param]
        );
    }

    /** Categories with published-song counts, optionally including inactive ones. */
    public function all(bool $publishedOnly = true, bool $includeInactive = false): array {
        $statusFilter = $publishedOnly ? " AND s.status = 'published'" : '';
        $activeFilter = $includeInactive ? '' : " AND c.status = 'active'";
        return $this->db->select(
            "SELECT c.*, (SELECT COUNT(*) FROM songs s WHERE s.category = c.id$statusFilter) AS song_count
             FROM categories c WHERE 1$activeFilter
             ORDER BY c.status = 'inactive', c.name"
        );
    }

    /** Paginated published songs in one category. */
    public function browse(int $categoryId, int $page = 1, int $perPage = ITEMS_PER_PAGE): array {
        $page = max(1, $page);
        $total = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM songs s WHERE s.category = ? AND s.status = 'published'",
            [$categoryId]
        );
        $pagination = paginate($total, $page, $perPage);
        $songs = $this->db->select(
            self::SONG_SELECT . ' ' . self::SONG_JOIN .
            " WHERE s.category = ? AND s.status = 'published'
             ORDER BY s.plays DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
            [$categoryId]
        );
        return ['songs' => $songs, 'total' => $total, 'pagination' => $pagination];
    }

    /* ---------------------------------------------------------- */
    /* Playlist helpers                                           */
    /* ---------------------------------------------------------- */

    /** Delete a whole playlist and its items, but only for its owner. */
    public function erase(int $playlistId, int $userId, ?string $type = null): array {
        $type = $type ?? $this->user->role();
        if (!in_array($type, ['user', 'artist', 'admin'], true)) {
            return ['success' => false, 'message' => "Invalid account type."];
        }
        if (!$this->db->execute('START TRANSACTION')) {
            return ['success' => false, 'message' => "The playlist could not be deleted. Please try again."];
        }
        try {
            $owned = $this->db->row(
                "SELECT id FROM playlists WHERE id = ? AND user = ? AND type = ? FOR UPDATE",
                [$playlistId, $userId, $type]
            );
            if (!$owned) {
                $this->db->execute('ROLLBACK');
                return ['success' => false, 'message' => "That playlist could not be found."];
            }
            $this->db->execute("DELETE FROM playlist_items WHERE playlist = ?", [$playlistId]);
            $deleted = $this->db->affected(
                "DELETE FROM playlists WHERE id = ? AND user = ? AND type = ?",
                [$playlistId, $userId, $type]
            );
            if ($deleted <= 0 || $this->db->errno() || !$this->db->execute('COMMIT')) {
                $this->db->execute('ROLLBACK');
                return ['success' => false, 'message' => "The playlist could not be deleted. Please try again."];
            }
            return ['success' => true, 'message' => "Playlist deleted."];
        } catch (\Throwable $e) {
            try { $this->db->execute('ROLLBACK'); } catch (\Throwable $ignored) {}
            throw $e;
        }
    }

    /** Whether a song is already in a playlist. */
    public function holds(int $playlistId, int $songId): bool {
        return (bool)$this->db->scalar(
            "SELECT COUNT(*) FROM playlist_items WHERE playlist = ? AND song = ?",
            [$playlistId, $songId]
        );
    }

}
