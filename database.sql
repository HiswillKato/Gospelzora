-- ---------------------------------------------------------------------------
-- Gospelzora — schema
--
-- Applied by installer.php with mysqli::multi_query() on the whole file, so it
-- must stay plain SQL: no DELIMITER, no stored procedures, no result sets.
--
-- WARNING, and deploy/check.php says the same about this file: it hardcodes
-- `USE zora` and DROPs every table first. It is an installer input, not a
-- migration — never pipe it at a database that holds data.
--
-- NO FOREIGN KEYS, DELIBERATELY. The domain classes clean up by hand and in an
-- order foreign keys would refuse: User::remove() deletes the `users` row first
-- and only then sweeps `favorites` and `tokens` (app/user.class.php), and
-- Music removes a category only after checking its songs. Adding constraints
-- here would turn those ordinary deletes into errors. Uniqueness is still
-- enforced where the app relies on it, via UNIQUE keys.
--
-- InnoDB + utf8mb4 throughout: the legal pages and bios hold arbitrary Unicode,
-- and the counters are updated with `column = column + 1`.
-- ---------------------------------------------------------------------------

USE zora;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `icons`;
DROP TABLE IF EXISTS `charts`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `visitors`;
DROP TABLE IF EXISTS `logins`;
DROP TABLE IF EXISTS `logs`;
DROP TABLE IF EXISTS `tokens`;
DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `playlistitems`;
DROP TABLE IF EXISTS `playlists`;
DROP TABLE IF EXISTS `songs`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `pages`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- users — one table for every account type
--
-- role is the access-control vocabulary ('user' | 'artist' | 'admin'); status
-- is 'active' | 'inactive' and is what revokes a session, since User::current()
-- re-reads this row on every request rather than trusting the session.
--
-- `updated` is managed explicitly (`updated = NOW()` in the code) and is
-- deliberately NOT `ON UPDATE CURRENT_TIMESTAMP`: User::sync() compares it
-- against the copy in the session to notice that the row changed underneath an
-- open session, and an automatic bump on every write would fire on unrelated
-- updates such as recording a login.
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(100) NOT NULL,
  `email`     VARCHAR(254) NOT NULL,
  `password`  VARCHAR(255) NOT NULL,
  `role`      VARCHAR(20)  NOT NULL DEFAULT 'user',
  `country`   CHAR(2)      NOT NULL DEFAULT '',
  `slug`      VARCHAR(190) NULL DEFAULT NULL,   -- User::register() inserts NULL when no slug is given
  `status`    VARCHAR(20)  NOT NULL DEFAULT 'active',
  `bio`       TEXT         NULL,
  `image`     VARCHAR(255) NULL,
  `login`     DATETIME     NULL,
  `verified`  DATETIME     NULL,
  `created`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated`   DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_slug` (`slug`),
  KEY `idx_users_role_status` (`role`, `status`),
  KEY `idx_users_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- categories — song categories, each with a Font Awesome icon from `icons`
-- ---------------------------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(190) NOT NULL,
  `description` TEXT         NULL,
  `icon`        VARCHAR(100) NOT NULL DEFAULT 'fa-music',
  `status`      VARCHAR(20)  NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- songs
--
-- artist is the owning users.id (an artist account); requester is the member who
-- asked for a track, non-zero only while status = 'requested'. `audio` and
-- `artwork` hold paths relative to assets/ ('audio/x.mp3',
-- 'images/artwork/x.png'); duration is seconds, 0 when unknown.
--
-- `updated` is explicit here for the same reason as on `users`.
-- ---------------------------------------------------------------------------
CREATE TABLE `songs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `artist`      INT UNSIGNED NOT NULL DEFAULT 0,
  `category`    INT UNSIGNED NOT NULL DEFAULT 0,
  `title`       VARCHAR(200) NOT NULL,
  `slug`        VARCHAR(190) NOT NULL,
  `description` TEXT         NULL,
  `audio`       VARCHAR(255) NULL,
  `artwork`     VARCHAR(255) NULL,
  `duration`    INT UNSIGNED NOT NULL DEFAULT 0,
  `status`      VARCHAR(20)  NOT NULL DEFAULT 'draft',
  `requester`   INT UNSIGNED NOT NULL DEFAULT 0,
  `plays`       INT UNSIGNED NOT NULL DEFAULT 0,
  `downloads`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated`     DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_songs_slug` (`slug`),
  KEY `idx_songs_artist` (`artist`),
  KEY `idx_songs_category` (`category`),
  KEY `idx_songs_status` (`status`),
  KEY `idx_songs_plays` (`plays`),
  KEY `idx_songs_downloads` (`downloads`),
  KEY `idx_songs_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- playlists / playlistitems
--
-- `type` is the list kind, and `privacy` decides whether the public pages show
-- it. `songs` on the playlist is a denormalised counter kept in step by
-- Music::addSong()/removeSong() — the counts come from playlistitems directly.
-- ---------------------------------------------------------------------------
CREATE TABLE `playlists` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user`        INT UNSIGNED NOT NULL DEFAULT 0,
  `type`        VARCHAR(20)  NOT NULL DEFAULT 'playlist',
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(190) NOT NULL,
  `description` TEXT         NULL,
  `privacy`     VARCHAR(20)  NOT NULL DEFAULT 'public',
  `songs`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_playlists_slug` (`slug`),
  KEY `idx_playlists_user_type` (`user`, `type`),
  KEY `idx_playlists_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `playlistitems` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `playlist` INT UNSIGNED NOT NULL,
  `song`     INT UNSIGNED NOT NULL,
  `position` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_playlistitems_song` (`playlist`, `song`),
  KEY `idx_playlistitems_position` (`playlist`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- favorites
--
-- One row per saved track. `type` is the kind of thing favourited ('song'
-- today) and is part of the uniqueness rule, so the same id under another type
-- is a different row rather than a duplicate-key error.
-- ---------------------------------------------------------------------------
CREATE TABLE `favorites` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user`    INT UNSIGNED NOT NULL,
  `type`    VARCHAR(20)  NOT NULL DEFAULT 'song',
  `song`    INT UNSIGNED NOT NULL,
  `created` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_favorites_user_type_song` (`user`, `type`, `song`),
  KEY `idx_favorites_song` (`song`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- pages — per-page SEO metadata, one row per slug
--
-- Only the metadata lives here; the body of the legal pages is the static copy
-- in pages/terms.php, pages/privacy.php and pages/copyright.php. The widths
-- match the limits dashboard/admin/page-edit.php enforces.
-- ---------------------------------------------------------------------------
CREATE TABLE `pages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(100) NOT NULL,
  `title`       VARCHAR(200) NULL,
  `description` VARCHAR(300) NULL,
  `keywords`    VARCHAR(300) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- settings — key/value
--
-- `name` is the primary key because Settings::set() relies on
-- ON DUPLICATE KEY UPDATE. It also holds the per-account login throttle state
-- (`login_lock_*`), which is why the key is VARCHAR and not an enum.
-- ---------------------------------------------------------------------------
CREATE TABLE `settings` (
  `name`  VARCHAR(100) NOT NULL,
  `value` TEXT         NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- tokens — email verification and password reset
--
-- Only the SHA-256 hash of the token is stored, never the token itself, so a
-- database dump cannot be replayed against a live account. `used` is what makes
-- a single-use claim single-use: the claim is one conditional UPDATE on
-- `used IS NULL`, so two concurrent requests cannot both win.
-- ---------------------------------------------------------------------------
CREATE TABLE `tokens` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`    VARCHAR(20)  NOT NULL,
  `user`    INT UNSIGNED NOT NULL,
  `purpose` VARCHAR(40)  NOT NULL,
  `hash`    CHAR(64)     NOT NULL,
  `expires` DATETIME     NOT NULL,
  `used`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tokens_purpose_hash` (`purpose`, `hash`),
  KEY `idx_tokens_user` (`user`),
  KEY `idx_tokens_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- logs — the activity log
--
-- `type` separates the streams ('activity' today); `action` is a key from
-- Analytics::ACTIONS, `details` the finished display sentence and `args` the
-- optional context. Written once per action, read newest-first.
-- ---------------------------------------------------------------------------
CREATE TABLE `logs` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`    VARCHAR(20)  NOT NULL DEFAULT 'activity',
  `actor`   INT UNSIGNED NOT NULL DEFAULT 0,
  `name`    VARCHAR(190) NOT NULL DEFAULT '',
  `action`  VARCHAR(100) NOT NULL,
  `details` TEXT         NULL,
  `args`    TEXT         NULL,
  `ip`      VARCHAR(45)  NOT NULL DEFAULT '',
  `created` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_created` (`created`),
  KEY `idx_logs_action` (`action`),
  KEY `idx_logs_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- logins — every authentication attempt, successful or not
--
-- `email` is what was submitted (not the account id) so that failures against
-- addresses with no account are still recorded, which is the point of the table.
-- ---------------------------------------------------------------------------
CREATE TABLE `logins` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`     VARCHAR(254) NOT NULL DEFAULT '',
  `success`   TINYINT(1)   NOT NULL DEFAULT 0,
  `method`    VARCHAR(20)  NOT NULL DEFAULT 'site',
  `useragent` VARCHAR(255) NOT NULL DEFAULT '',
  `ip`        VARCHAR(45)  NOT NULL DEFAULT '',
  `created`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logins_created` (`created`),
  KEY `idx_logins_email` (`email`),
  KEY `idx_logins_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- visitors — one row per public page request
--
-- `path` and `referer` are long and unindexed on purpose; only `created`,
-- `status` and `ip` are ever filtered or grouped on.
-- ---------------------------------------------------------------------------
CREATE TABLE `visitors` (
  `id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `path`      VARCHAR(500)  NOT NULL DEFAULT '',
  `method`    VARCHAR(10)   NOT NULL DEFAULT 'GET',
  `status`    SMALLINT UNSIGNED NOT NULL DEFAULT 200,
  `ip`        VARCHAR(45)   NOT NULL DEFAULT '',
  `useragent` VARCHAR(255)  NOT NULL DEFAULT '',
  `referer`   VARCHAR(500)  NOT NULL DEFAULT '',
  `created`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visitors_created` (`created`),
  KEY `idx_visitors_status` (`status`),
  KEY `idx_visitors_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- messages — contact form submissions for the admin panel
-- ---------------------------------------------------------------------------
CREATE TABLE `messages` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`    VARCHAR(100) NOT NULL,
  `email`   VARCHAR(254) NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `message` TEXT         NOT NULL,
  `seen`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_seen` (`seen`),
  KEY `idx_messages_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- events — one row per counted play or download
--
-- This is the raw material for the period charts, which are computed live from
-- this table over a date window (`e.created >= …`) while the lifetime charts are
-- a rebuildable ranking cached in `charts`. The app prunes it at 250,000 rows,
-- so it is expected to be the largest table here.
--
-- `type` is a real ENUM, and that is deliberate: Music::increment() maps an
-- unknown type to null and skips the write, so the enum is a second line of
-- defence rather than the only one.
-- ---------------------------------------------------------------------------
CREATE TABLE `events` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `song`    INT UNSIGNED NOT NULL,
  `type`    ENUM('play','download') NOT NULL,
  `created` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_events_type_created` (`type`, `created`),
  KEY `idx_events_song` (`song`),
  KEY `idx_events_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- charts — cached lifetime ranking, one row per position per chart key
--
-- Pure derived data: Music::sync() truncates the chart and rebuilds it from the
-- live counters, so this table can be dropped at any time.
-- ---------------------------------------------------------------------------
CREATE TABLE `charts` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chart`    VARCHAR(40)  NOT NULL,
  `song`     INT UNSIGNED NOT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_charts_chart_song` (`chart`, `song`),
  KEY `idx_charts_position` (`chart`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- icons — the Font Awesome catalogue offered by the category icon picker
--
-- `css` is the class(es) to render and `uses` the plain-English hint shown
-- under the name. api/icons.php serves this to the browser and deliberately
-- re-exposes `css` under the key `fa_class`, which is the response contract
-- assets/js/app.js reads.
-- ---------------------------------------------------------------------------
CREATE TABLE `icons` (
  `name` VARCHAR(60)  NOT NULL,
  `css`  VARCHAR(120) NOT NULL,
  `uses` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------------

-- The three static legal pages. Their bodies live in pages/*.php; this is only
-- the SEO metadata the shells read, so an admin can edit it later.
INSERT INTO `pages` (`slug`, `title`, `description`, `keywords`) VALUES
  ('copyright', 'Copyright Policy', 'How Gospelzora handles copyright and neighbouring rights claims, takedown notices and counter-notices.', 'copyright policy, takedown notice, neighbouring rights, uganda copyright'),
  ('privacy',   'Privacy Policy', 'What personal data Gospelzora collects, why it collects it, and how it is stored, used and protected.', 'privacy policy, personal data, data protection, gdpr'),
  ('terms',     'Terms of Service', 'The terms that govern the use of Gospelzora by artists and worshippers, including accounts, uploads and content.', 'terms of service, terms and conditions, user agreement');

-- The icon picker's catalogue: Font Awesome 6 free, solid unless noted.
INSERT INTO `icons` (`name`, `css`, `uses`) VALUES
  ('music',            'fa-music',            'Music, songs, general worship'),
  ('microphone',       'fa-microphone',       'Vocals, soloist, live recording'),
  ('headphones',       'fa-headphones',       'Listening, audio, playback'),
  ('guitar',           'fa-guitar',           'Instrumental, band, strings'),
  ('piano',            'fa-piano',            'Keys, instrumental, piano'),
  ('drum',             'fa-drum',             'Percussion, instrumental'),
  ('users',            'fa-users',            'Artists, Group, Choir, Users'),
  ('user-group',       'fa-user-group',       'Group, Choir, fellowship, team'),
  ('microphone-lines', 'fa-microphone-lines', 'Podcast, spoken word, testimonial'),
  ('radio',            'fa-radio',            'Broadcast, live stream, station'),
  ('compact-disc',     'fa-compact-disc',     'Album, recording, release'),
  ('list-music',       'fa-list-music',       'Playlist, track list, album'),
  ('play',             'fa-play',             'Play, listen, preview'),
  ('heart',            'fa-heart',            'Favourite, love, worship'),
  ('star',             'fa-star',             'Featured, favourite, top rated'),
  ('book-open',        'fa-book-open',        'Lyrics, scripture, study'),
  ('book-bible',       'fa-book-bible',       'Scripture, Bible, devotional'),
  ('hands-praying',    'fa-hands-praying',    'Prayer, worship, ministry'),
  ('hand-holding-heart','fa-hand-holding-heart','Love, care, community'),
  ('church',           'fa-church',           'Church, congregation, parish'),
  ('mosque',           'fa-mosque',           'Mosque, congregation, Islamic'),
  ('globe',            'fa-globe',            'World, international, nations'),
  ('earth-africa',     'fa-earth-africa',     'Africa, Uganda, region'),
  ('star-and-crescent','fa-star-and-crescent','Islamic, Crescent'),
  ('hands',            'fa-hands',            'Community, fellowship, serving'),
  ('heart-circle-check','fa-heart-circle-check','Favourite added, approved'),
  ('people-group',     'fa-people-group',     'Community, members, audience'),
  ('person-singing',   'fa-person-singing',   'Artist, singer, soloist'),
  ('sliders',          'fa-sliders',          'Charts, top songs, ranking'),
  ('chart-simple',     'fa-chart-simple',     'Charts, statistics, popular'),
  ('fire',             'fa-fire',             'Trending, hot, energetic'),
  ('crown',            'fa-crown',            'Top rated, champion, best'),
  ('trophy',           'fa-trophy',           'Award, achievement, winner'),
  ('sparkles',         'fa-sparkles',         'New, fresh, featured'),
  ('wand-magic-sparkles','fa-wand-magic-sparkles','Worship, miracle, inspiration'),
  ('dove',             'fa-dove',             'Peace, Holy Spirit, hope'),
  ('flame',            'fa-flame',            'Fire, passion, revival'),
  ('bolt',             'fa-bolt',             'Energetic, powerful, live'),
  ('moon',             'fa-moon',             'Night, reflection, quiet'),
  ('sun',              'fa-sun',              'Joy, light, hope'),
  ('leaf',             'fa-leaf',             'Nature, new, growth'),
  ('water',            'fa-water',            'Baptism, cleansing, life'),
  ('mountain',         'fa-mountain',         'Faith, refuge, strength'),
  ('tree',             'fa-tree',             'Growth, life, rooted'),
  ('seedling',         'fa-seedling',         'New, growth, potential'),
  ('hands-holding',    'fa-hands-holding',    'Community, care, support'),
  ('gift',             'fa-gift',             'Blessing, giveaway, offering'),
  ('hand-holding',     'fa-hand-holding',     'Friendship, partnership'),
  ('users-view',       'fa-users-view',       'Audience, viewers, congregation'),
  ('microphone-vocal', 'fa-microphone-vocal', 'Singer, vocalist, spoken'),
  ('record-vinyl',     'fa-record-vinyl',     'Vinyl, classic, retro'),
  ('waveform',         'fa-waveform',         'Audio, sound, playback'),
  ('file-audio',       'fa-file-audio',       'Download, audio file, track'),
  ('tag',              'fa-tag',              'Category, label, genre');