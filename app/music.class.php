<?php
/**
 * Music — song, category & favorites management.
 *
 * Instance-driven: the shared `$music` built in app/bootstrap.php — `ranked('latest')`,
 * `save($data)`, etc.
 */

class Music {

    private DB $db;
    private Analytics $analytics;
    private User $user;

    public function __construct(DB $db, Analytics $analytics, User $user) {
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
        $categoryId = (int)($data['category'] ?? 0);
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

        // Resolve the artist and song slug up front — the audio filename is
        // derived from them (e.g. amazing-grace-hiswill.mp3).
        $artist = $this->db->row("SELECT name FROM users WHERE id = ? AND role = 'artist'", [$artistId]);
        if (!$artist) {
            return ['success' => false, 'message' => "Please select a valid artist."];
        }
        // Slug is the song title plus the artist name (e.g.
        // "amazing-grace-hiswill") so it reads differently across artists.
        $slug = slug($title . ' ' . $artist['name'], 'songs', $id ?: 0);

        // Audio upload (keeps the existing file when none was uploaded).
        // store() validates the file first, and only then saves it to
        // the audio folder — nothing is written on its own.
        $audioUpload = $data['audio'] ?? null;
        $replacedAudio = false;
        if (is_array($audioUpload) && !empty($audioUpload['name'])) {
            $ext = strtolower(pathinfo($audioUpload['name'], PATHINFO_EXTENSION));
            // The slug already carries the artist name, so it is the filename.
            $audioName = substr($slug, 0, 120) . '.' . $ext;
            $upload = store($audioUpload, AUDIO_PATH . '/', 'audio', 'audio', $audioName);
            if ($upload['error'] === null) {
                $audioFile = 'audio/' . $upload['path'];
                $replacedAudio = true;
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
            } else {
                $err = $upload['error'];
            }
        }

        if ($err) return ['success' => false, 'message' => $err];
        // 'requested'/'approved' songs genuinely have no audio yet — only
        // the playable statuses require a file.
        $needsAudio = in_array($status, ['published', 'pending', 'draft'], true);
        if (!$title || !$artistId || ($needsAudio && !$audioFile) || !$description) {
            return ['success' => false, 'message' => "Please provide a title, an artist, a description and an audio file."];
        }

        $categoryId = $categoryId > 0 ? $categoryId : null;

        // The audio file the song currently points at (before any UPDATE) —
        // the only file that may safely be removed when it gets replaced.
        // The POST `existing_audio` hint is never trusted for deletion.
        $previousAudio = '';
        if ($id) {
            $prev = $this->db->row(
                $artistMode
                    ? "SELECT audio FROM songs WHERE id = ? AND artist = ?"
                    : "SELECT audio FROM songs WHERE id = ?",
                $artistMode ? [$id, $artistId] : [$id]
            );
            $previousAudio = (string)($prev['audio'] ?? '');
        }

        if ($id) {
            if ($artistMode) {
                $this->db->execute(
                    "UPDATE songs SET title = ?, slug = ?, description = ?, audio = ?, artwork = ?,
                        duration = ?, category = ?, status = ?, updated = NOW() WHERE id = ? AND artist = ?",
                    [$title, $slug, $description, $audioFile, $artwork, $duration, $categoryId, $status, $id, $artistId]
                );
            } else {
                $this->db->execute(
                    "UPDATE songs SET artist = ?, title = ?, slug = ?, description = ?, audio = ?, artwork = ?,
                        duration = ?, category = ?, status = ?, updated = NOW() WHERE id = ?",
                    [$artistId, $title, $slug, $description, $audioFile, $artwork, $duration, $categoryId, $status, $id]
                );
            }
            $songId = $id;
        } else {
            $songId = $this->db->insert(
                "INSERT INTO songs (artist, category, title, slug, description, audio, artwork, duration, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$artistId, $categoryId, $title, $slug, $description, $audioFile, $artwork, $duration, $status]
            );
        }

        // Once the replacement is safely recorded, remove the previous version's
        // audio file — sourced from the DB row above, never from the request.
        if ($replacedAudio && $previousAudio !== '' && $previousAudio !== $audioFile) {
            $this->unlink($previousAudio);
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
        $this->db->execute("UPDATE songs SET status = ?, updated = NOW() WHERE id = ?", [$status, $id]);
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
        // Category is optional but must reference a real category when given.
        $categoryId = ($categoryId > 0 && $this->db->scalar("SELECT id FROM categories WHERE id = ?", [$categoryId])) ? $categoryId : 0;
        $categoryId = $categoryId > 0 ? $categoryId : null;
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
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => "The upload failed. Please try again."];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // Requested-song slugs already include the artist name; the approved
        // slug is inherited, so it is the audio filename.
        $audioName = substr($song['slug'], 0, 120) . '.' . $ext;

        $upload = store($file, AUDIO_PATH . '/', 'audio', 'audio', $audioName);
        if ($upload['error'] !== null) {
            return ['success' => false, 'message' => $upload['error']];
        }
        $audioFile = 'audio/' . $upload['path'];

        $this->db->execute(
            "UPDATE songs SET audio = ?, status = 'published', updated = NOW() WHERE id = ? AND artist = ? AND status = 'approved'",
            [$audioFile, $songId, $artistId]
        );
        $this->analytics->log('song.request.upload', sprintf("Artist uploaded audio for requested song #%s \"%s\"", $songId, $song['title']));
        return ['success' => true, 'message' => "Audio uploaded and the song is now published.", 'id' => $songId];
    }

    /**
     * Delete a song or a category. $type is 'song' (with an optional ownership scope) or 'category'.
     */
    public function delete(string $type, int $id, ?int $ownerArtistId = null): void {
        if ($type === 'category') {
            $cat = $this->db->row("SELECT name FROM categories WHERE id = ?", [$id]);
            $this->db->execute("DELETE FROM categories WHERE id = ?", [$id]);
            if ($cat) {
                $this->analytics->log('category.delete', sprintf("Category deleted: %s", $cat['name']));
            }
            return;
        }
        if ($type !== 'song') return;

        $where = $ownerArtistId !== null ? 'id = ? AND artist = ?' : 'id = ?';
        $params = $ownerArtistId !== null ? [$id, $ownerArtistId] : [$id];
        $song = $this->db->row("SELECT title, audio FROM songs WHERE $where", $params);

        $this->db->execute("DELETE FROM songs WHERE $where", $params);

        // Remove the physical file alongside the database row.
        if ($song && !empty($song['audio'])) {
            $this->unlink($song['audio']);
        }

        $this->analytics->log('song.delete', sprintf("Song #%s \"%s\"", $id, $song ? $song['title'] : ''));
    }

    /**
     * Best-effort removal of a song's audio file. Stored paths look like `audio/<filename>`; only
     * files that resolve inside AUDIO_PATH are ever touched, so arbitrary paths cannot be fed in
     * here.
     */
    private function unlink(string $path): void {
        $file = AUDIO_PATH . '/' . basename($path);
        $real = realpath($file);
        $root = realpath(AUDIO_PATH);
        if ($real === false || $root === false || !str_starts_with($real, $root . '/')) {
            return;
        }
        @unlink($real);
    }

    /** Song rows for the admin/artist panel, with artist names. */
    public function manage(?int $artistId = null): array {
        $sql = self::SONG_SELECT . ' ' . self::SONG_JOIN;
        if ($artistId !== null) {
            return $this->db->select("$sql WHERE s.artist = ? ORDER BY s.created DESC", [$artistId]);
        }
        return $this->db->select("$sql ORDER BY s.created DESC");
    }

    /**
     * Count one play or one download for a song, at most once per browser session, and log the
     * event that feeds the charts.
     */
    public function increment(int $id, string $type = 'play'): void {
        $column = match ($type) {
            'play'     => 'plays',
            'download' => 'downloads',
            // events.type is enum('play','download'), so an unknown type
            // would be a fatal on insert rather than a no-op.
            default    => null,
        };
        if ($column === null) return;
        if ($this->counted($type, $id)) return;
        $this->mark($type, $id);
        if ($this->db->affected("UPDATE songs SET {$column} = {$column} + 1 WHERE id = ?", [$id]) > 0) {
            $this->db->execute("INSERT INTO events (song, type) VALUES (?, ?)", [$id, $type]);
        }
    }

    /**
     * Session-based dedupe: a song's play/download counts at most once per browser session, so
     * refreshing or replaying a song (or re-downloading it) doesn't inflate the counters or the
     * events chart data. Tracked ids live in the session, one marker set per song and type.
     */
    private function counted(string $type, int $id): bool {
        if (PHP_SAPI === 'cli') return false;
        return !empty($_SESSION['counted'][$type][(int)$id]);
    }

    private function mark(string $type, int $id): void {
        if (PHP_SAPI === 'cli') return;
        $_SESSION['counted'][$type][(int)$id] = true;
    }

    /* ---------------------------------------------------------- */
    /* Top Charts                                                 */
    /* ---------------------------------------------------------- */

    /**
     * Chart registry: key => label, in display order. The first three are lifetime rankings
     * persisted as rows in the `charts` table (see sync()); the period charts are computed
     * live from `events`.
     */
    public const CHARTS = [
        'overall' => 'Top Overall',
        'plays' => 'Most Played',
        'downloads' => 'Most Downloaded',
        'daily' => 'Top Daily',
        'weekly' => 'Top Weekly',
        'monthly' => 'Top Monthly',
        'yearly' => 'Top Yearly',
    ];

    /**
     * Period charts: key => [SQL date window over events alias `e`, human note shown under the
     * pill switcher]. Ranked by plays inside the window (downloads as the secondary
     * column/tie-breaker).
     */
    public const PERIOD_CHARTS = [
        'daily' => [
            'range' => 'e.created >= CURDATE()',
            'note' => 'Ranked by plays recorded today (since midnight).',
        ],
        'weekly' => [
            'range' => 'e.created >= NOW() - INTERVAL 7 DAY',
            'note' => 'Ranked by plays recorded in the last 7 days.',
        ],
        'monthly' => [
            'range' => 'e.created >= NOW() - INTERVAL 30 DAY',
            'note' => 'Ranked by plays recorded in the last 30 days.',
        ],
        'yearly' => [
            'range' => 'e.created >= NOW() - INTERVAL 365 DAY',
            'note' => 'Ranked by plays recorded in the last 12 months.',
        ],
    ];

    /** Ranking expression (with deterministic tie-breakers) per chart key. */
    private const CHART_ORDER = [
        'overall' => 's.plays + s.downloads DESC, s.plays DESC, s.id DESC',
        'plays' => 's.plays DESC, s.downloads DESC, s.id DESC',
        'downloads' => 's.downloads DESC, s.plays DESC, s.id DESC',
    ];

    /**
     * Rebuilds the stored ranking for one chart from the live play/download counters (published
     * songs only, top $limit). Derived data, so re-running it is always safe — the Top Charts page
     * syncs on view.
     */
    public function sync(string $chart = 'overall', int $limit = 50): void {
        if (!isset(self::CHART_ORDER[$chart])) {
            $chart = 'overall';
        }
        $ids = $this->db->select(
            "SELECT s.id FROM songs s WHERE s.status = 'published' ORDER BY " .
            self::CHART_ORDER[$chart] . " LIMIT " . (int)$limit
        );
        $this->db->execute("DELETE FROM charts WHERE chart = ?", [$chart]);
        $position = 1;
        foreach ($ids as $row) {
            $this->db->insert(
                "INSERT INTO charts (chart, song, position) VALUES (?, ?, ?)",
                [$chart, (int)$row['id'], $position++]
            );
        }
    }

    /**
     * Ranked chart rows (position + song + artist). Lifetime charts sync into the `charts` table
     * first; period charts are computed live from `events`, and their
     * plays/downloads columns carry the period counts rather than the lifetime totals.
     */
    public function chart(string $chart = 'overall', int $limit = 50): array {
        if (isset(self::PERIOD_CHARTS[$chart])) {
            return $this->period($chart, $limit);
        }
        if (!isset(self::CHART_ORDER[$chart])) {
            $chart = 'overall';
        }
        $this->sync($chart, $limit);
        return $this->db->select(
            "SELECT c.position, s.id, s.title, s.slug, s.artwork, s.duration, s.audio,
                    s.plays, s.downloads, u.name AS artist_name, u.slug AS artist_slug
             FROM charts c
             JOIN songs s ON s.id = c.song
             " . self::ARTIST_JOIN . "
             WHERE c.chart = ?
             ORDER BY c.position
             LIMIT " . (int)$limit,
            [$chart]
        );
    }

    /**
     * Period chart rows: songs with at least one play/download inside the window, ranked by period
     * plays, then period downloads, then newest id. Returns the same row shape as the lifetime
     * chart query.
     */
    private function period(string $period, int $limit): array {
        $range = self::PERIOD_CHARTS[$period]['range'];
        $rows = $this->db->select(
            "SELECT s.id, s.title, s.slug, s.artwork, s.duration, s.audio,
                    u.name AS artist_name, u.slug AS artist_slug,
                    SUM(e.type = 'play') AS period_plays,
                    SUM(e.type = 'download') AS period_downloads
             FROM events e
             INNER JOIN songs s ON s.id = e.song
             " . self::ARTIST_JOIN . "
             WHERE s.status = 'published' AND ($range)
             GROUP BY s.id
             ORDER BY period_plays DESC, period_downloads DESC, s.id DESC
             LIMIT " . (int)$limit
        );
        foreach ($rows as $i => $row) {
            $rows[$i]['position'] = $i + 1;
            $rows[$i]['plays'] = (int)$row['period_plays'];
            $rows[$i]['downloads'] = (int)$row['period_downloads'];
            unset($rows[$i]['period_plays'], $rows[$i]['period_downloads']);
        }
        return $rows;
    }

    /* ---------------------------------------------------------- */
    /* Favorites                                                  */
    /* ---------------------------------------------------------- */

    public function favorited(int $songId, ?int $userId = null): bool {
        if ($userId === null) $userId = ($_SESSION['user_id'] ?? null) ? (int)$_SESSION['user_id'] : null;
        if (!$userId) return false;
        return (bool)$this->marked($userId, $this->user->role(), $songId);
    }

    public function toggle(int $userId, int $songId, ?string $type = null): array {
        $type = $type ?? $this->user->role();
        if (!in_array($type, ['user', 'artist', 'admin'], true)) {
            return ['success' => false, 'message' => "Invalid account type."];
        }
        if (!$this->song($songId)) {
            return ['success' => false, 'message' => "That song could not be found."];
        }

        $existing = $this->marked($userId, $type, $songId);

        if ($existing) {
            $this->db->execute("DELETE FROM favorites WHERE id = ?", [(int)$existing['id']]);
            return ['success' => true, 'favorited' => false, 'message' => "Removed from your favorites."];
        }

        $this->db->execute(
            "INSERT INTO favorites (user, type, song) VALUES (?, ?, ?)",
            [$userId, $type, $songId]
        );
        return ['success' => true, 'favorited' => true, 'message' => "Added to your favorites."];
    }

    public function favorites(int $userId, ?string $type = null): array {
        $type = $type ?? $this->user->role();
        return $this->db->select(
            "SELECT s.*, u.name as artist_name FROM favorites f INNER JOIN songs s ON f.song = s.id " .
            self::ARTIST_JOIN .
            " WHERE f.user = ? AND f.type = ? AND s.status = 'published' ORDER BY f.created DESC",
            [$userId, $type]
        );
    }

    /* ---------------------------------------------------------- */
    /* Playlists                                                  */
    /* ---------------------------------------------------------- */

    /** All playlists owned by an account, newest first. */
    public function playlists(int $userId, ?string $type = null): array {
        $type = $type ?? $this->user->role();
        return $this->db->select(
            "SELECT * FROM playlists WHERE user = ? AND type = ? ORDER BY created DESC",
            [$userId, $type]
        );
    }

    /** Public playlists across all accounts, newest first. */
    public function discover(int $limit = 12): array {
        return $this->db->select(
            "SELECT p.*, u.name AS owner FROM playlists p " .
            "INNER JOIN users u ON u.id = p.user " .
            "WHERE p.privacy = 'public' ORDER BY p.created DESC LIMIT " . (int)$limit
        );
    }

    /** A single playlist by slug, with its songs ordered by position. */
    public function playlist(string $slug): ?array {
        $row = $this->db->row("SELECT * FROM playlists WHERE slug = ?", [$slug]);
        if (!$row) return null;

        $row['songs_list'] = $this->tracks((int)$row['id']);
        return $row;
    }

    /**
     * A playlist by id, but only when it belongs to the given account. Used by the
     * owner-only screens (the edit form, delete) so an id typed into the URL cannot
     * reach somebody else's playlist.
     */
    public function owned(int $playlistId, int $userId, ?string $type = null): ?array {
        $type = $type ?? $this->user->role();
        $row = $this->db->row(
            "SELECT * FROM playlists WHERE id = ? AND user = ? AND type = ?",
            [$playlistId, $userId, $type]
        );
        return $row ?: null;
    }

    /**
     * Whether a visitor may read a playlist row: a public playlist is open to
     * everyone, a private one only to its owner.
     */
    public function visible(?array $playlist, ?int $userId, ?string $type = null): bool {
        if (!$playlist) return false;
        if ($playlist['privacy'] === 'public') return true;
        if ($userId === null) return false;
        $type = $type ?? $this->user->role();
        return (int)$playlist['user'] === $userId && $playlist['type'] === $type;
    }

    /** Songs in a playlist, as an ordered list of song rows for the player. */
    public function tracks(int $playlistId): array {
        return $this->db->select(
            "SELECT s.*, u.name as artist_name FROM playlistitems pi " .
            "INNER JOIN songs s ON s.id = pi.song " .
            self::ARTIST_JOIN .
            " WHERE pi.playlist = ? AND s.status = 'published' ORDER BY pi.position, pi.id",
            [$playlistId]
        );
    }

    /**
     * Create or update a playlist. When $id is given the playlist is edited and
     * ownership is checked; otherwise a new one is created.
     */
    public function compose(int $userId, ?int $id, string $name, ?string $description, string $privacy, ?string $type = null): array {
        $type = $type ?? $this->user->role();
        if (!in_array($type, ['user', 'artist', 'admin'], true)) {
            return ['success' => false, 'message' => "Invalid account type."];
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) {
            return ['success' => false, 'message' => "Playlist name is required (max 160 characters)."];
        }
        if (!in_array($privacy, ['public', 'private'], true)) {
            $privacy = 'private';
        }

        if ($id !== null && $id > 0) {
            $existing = $this->db->row(
                "SELECT id FROM playlists WHERE id = ? AND user = ? AND type = ?",
                [$id, $userId, $type]
            );
            if (!$existing) {
                return ['success' => false, 'message' => "That playlist could not be found."];
            }
            $newSlug = slug($name, 'playlists', $id);
            $this->db->execute(
                "UPDATE playlists SET name = ?, slug = ?, description = ?, privacy = ? WHERE id = ?",
                [$name, $newSlug, $description !== '' ? $description : null, $privacy, $id]
            );
            return ['success' => true, 'message' => "Playlist updated.", 'id' => $id, 'slug' => $newSlug];
        }

        $newSlug = slug($name, 'playlists', 0);
        $newId = $this->db->insert(
            "INSERT INTO playlists (user, type, name, slug, description, privacy) VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $type, $name, $newSlug, $description !== '' ? $description : null, $privacy]
        );
        return ['success' => true, 'message' => "Playlist created.", 'id' => $newId, 'slug' => $newSlug];
    }

    /** Append a song to a playlist, ignoring duplicates. Maintains the songs counter. */
    public function attach(int $playlistId, int $songId): array {
        if (!$this->db->row("SELECT id FROM playlists WHERE id = ?", [$playlistId])) {
            return ['success' => false, 'message' => "That playlist could not be found."];
        }
        if (!$this->song($songId)) {
            return ['success' => false, 'message' => "That song could not be found."];
        }
        $exists = (bool)$this->db->scalar(
            "SELECT COUNT(*) FROM playlistitems WHERE playlist = ? AND song = ?",
            [$playlistId, $songId]
        );
        if ($exists) {
            return ['success' => false, 'message' => "That song is already in this playlist."];
        }
        $maxPos = (int)$this->db->scalar(
            "SELECT COALESCE(MAX(position), 0) FROM playlistitems WHERE playlist = ?",
            [$playlistId]
        );
        $this->db->insert(
            "INSERT INTO playlistitems (playlist, song, position) VALUES (?, ?, ?)",
            [$playlistId, $songId, $maxPos + 1]
        );
        $this->db->execute(
            "UPDATE playlists SET songs = songs + 1 WHERE id = ?",
            [$playlistId]
        );
        return ['success' => true, 'message' => "Song added to playlist."];
    }

    /** Remove a song from a playlist. Decrements the songs counter. */
    public function detach(int $playlistId, int $songId): array {
        $deleted = $this->db->affected(
            "DELETE FROM playlistitems WHERE playlist = ? AND song = ?",
            [$playlistId, $songId]
        );
        if ($deleted > 0) {
            $this->db->execute(
                "UPDATE playlists SET songs = GREATEST(songs - 1, 0) WHERE id = ?",
                [$playlistId]
            );
            return ['success' => true, 'message' => "Song removed from playlist."];
        }
        return ['success' => false, 'message' => "That song is not in this playlist."];
    }

    /**
     * Move a song up or down within a playlist. Only the relative direction is taken,
     * so the caller cannot inject a target position.
     */
    public function move(int $playlistId, int $songId, string $direction): array {
        if (!in_array($direction, ['up', 'down'], true)) {
            return ['success' => false, 'message' => "Invalid move direction."];
        }
        $row = $this->db->row(
            "SELECT id, position FROM playlistitems WHERE playlist = ? AND song = ?",
            [$playlistId, $songId]
        );
        if (!$row) {
            return ['success' => false, 'message' => "That song is not in this playlist."];
        }

        $comparison = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        // The neighbour is the nearest item on the requested side; ties break on id
        // so two items that somehow share a position still swap deterministically.
        $neighbour = $this->db->row(
            "SELECT id, position FROM playlistitems
             WHERE playlist = ? AND (position $comparison ? OR (position = ? AND id $comparison ?))
             ORDER BY position $order, id $order LIMIT 1",
            [$playlistId, (int)$row['position'], (int)$row['position'], (int)$row['id']]
        );
        if (!$neighbour) {
            return ['success' => false, 'message' => "That song is already at the " . ($direction === 'up' ? 'top' : 'bottom') . " of the playlist."];
        }

        // A genuine swap, in three statements. Both rows are written to the OTHER
        // row's value, so the pair never has to share a position at any point in
        // between — which matters because (playlist, position) is indexed. Park the
        // moved row on a sentinel far outside the contiguous 1..n range that
        // attach() hands out, so a neighbour that happens to sit on the same
        // position cannot collide with it either.
        $movedId = (int)$row['id'];
        $neighbourId = (int)$neighbour['id'];
        $movedPosition = (int)$row['position'];
        $neighbourPosition = (int)$neighbour['position'];
        $parked = 1000000;

        $this->db->execute("UPDATE playlistitems SET position = ? WHERE id = ?", [$parked, $movedId]);
        $this->db->execute("UPDATE playlistitems SET position = ? WHERE id = ?", [$movedPosition, $neighbourId]);
        $this->db->execute("UPDATE playlistitems SET position = ? WHERE id = ?", [$neighbourPosition, $movedId]);

        return ['success' => true, 'message' => "Playlist reordered."];
    }

    /** Delete a whole playlist, but only for its owner. */
    public function erase(int $playlistId, int $userId, ?string $type = null): array {
        $type = $type ?? $this->user->role();
        $deleted = $this->db->affected(
            "DELETE FROM playlists WHERE id = ? AND user = ? AND type = ?",
            [$playlistId, $userId, $type]
        );
        if ($deleted > 0) {
            return ['success' => true, 'message' => "Playlist deleted."];
        }
        return ['success' => false, 'message' => "That playlist could not be found."];
    }

    /**
     * Whether a song is already in a playlist (used to show the correct button state).
     */
    public function holds(int $playlistId, int $songId): bool {
        return (bool)$this->db->scalar(
            "SELECT COUNT(*) FROM playlistitems WHERE playlist = ? AND song = ?",
            [$playlistId, $songId]
        );
    }

    /* ---------------------------------------------------------- */
    /* Categories                                                 */
    /* ---------------------------------------------------------- */

    /**
     * Categories, both directions in one method:
     *   categories()            every active category (dropdowns, filters)
     *   categories($songId)     the categories one song belongs to
     */
    public function categories(?int $songId = null): array {
        if ($songId !== null) {
            return $this->db->select(
                "SELECT c.* FROM categories c INNER JOIN songs s ON s.category = c.id WHERE s.id = ?",
                [$songId]
            );
        }
        return $this->db->select("SELECT c.* FROM categories c WHERE c.status = 'active' ORDER BY c.name");
    }

    public function category(mixed $value): ?array {
        ['column' => $column, 'param' => $param] = resolve($value);
        return $this->db->row("SELECT * FROM categories WHERE $column = ? LIMIT 1", [$param]);
    }

    public function all(bool $publishedOnly = true, bool $includeInactive = false): array {
        $statusFilter = $publishedOnly ? " AND s.status = 'published'" : '';
        $activeFilter = $includeInactive ? '' : " AND c.status = 'active'";
        return $this->db->select(
            "SELECT c.*, (SELECT COUNT(*) FROM songs s WHERE s.category = c.id$statusFilter) as song_count
             FROM categories c WHERE 1$activeFilter
             ORDER BY c.status = 'inactive', c.name"
        );
    }

    /** Paginated songs inside a category. */
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

}
