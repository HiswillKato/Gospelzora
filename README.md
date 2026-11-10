# Gospelzora

**Transforming Lives Through Worship**

Gospelzora is a PHP/MySQL gospel music platform for discovering songs, exploring artists, streaming music, and managing listener and artist accounts.

## Application status

The repository contains the public music experience, member and artist areas, administration tools, authentication flows, and the supporting database schema. Only `public/` is exposed by the web server. It remains in active development and is **not yet declared production-ready**.

### Implemented areas

- **Music discovery:** home, music catalogue, song details, search, charts, categories, artists, and playlists.
- **Playback and APIs:** music player, song and artist endpoints, search, playlists, and favorites.
- **Accounts:** registration, login, email verification, password recovery, profiles, favorites, playlists, and song requests.
- **Member and artist dashboards:** account overview, profile management, artist song management, and uploads.
- **Administration:** song, artist, category, account, page metadata, visitor, download, log, message, and maintenance management.
- **Shared interface:** public, member, and admin shells, with reusable partials under `app/views/shared/`.
- **Feature views:** public pages under `app/views/public/`; authentication, dashboard, music, playlist, artist, category, chart, search, and admin views are grouped under `app/views/`.
- **Database:** MySQL/MariaDB schema, installer, playlist tables, account tables, analytics tables, and settings.

### Architecture notes

- `app/views/` is the canonical location for page markup and reusable feature partials.
- Shared layout and UI components live in `app/views/shared/`; feature-specific partials live with their feature views.
- The old `app/includes/`, `dashboard/includes/`, and `dashboard/admin/includes/` trees have been removed. Do not recreate them.
- `public/` is the only web document root. Set the web server root to this directory, never the repository root.
- `public/index.php` is the single front controller. `routes/web.php` maps public, authentication, music, dashboard, admin and API URLs through `app/core/Router.php`.
- There is no `/pages/` directory or standalone root `dashboard/` or `api/` entry-point architecture. Those URLs are handled by routes, while implementations remain under `app/views/` and `app/controllers/`.
- Application source, configuration, Composer dependencies, database schema, installer implementation and tests remain outside `public/`.

## Technology

- **PHP 8.4+** — server-side application
- **MySQL / MariaDB** — relational database through MySQLi
- **Bootstrap 5**, HTML, CSS, and JavaScript — interface
- **Composer** — dependency management and autoloading
- **PHPMailer** — transactional email

## Local setup

Gospelzora is developed in a Linux environment. The web server document root MUST be the repository's `public/` directory.

1. Clone the repository:
   ```bash
   git clone https://github.com/HiswillKato/Gospelzora.git
   ```
2. Create a MySQL/MariaDB database matching `DB_NAME` in your local configuration. The default database name is `gospelzora`.
3. Import the schema into the database you created:
   ```bash
   mysql -u YOUR_DB_USER -p gospelzora < database.sql
   ```
   The schema does not select a hardcoded database and does not drop existing tables. It uses `CREATE TABLE IF NOT EXISTS`; it does not alter existing table definitions, so structural upgrades require explicit migrations.
4. Configure the database, base URL, trusted proxy addresses (if applicable), and mail settings through `.env` or the ignored `app/config.local.php`. Never commit production secrets.
5. Install dependencies:
   ```bash
   composer install
   ```
6. Configure the web server to serve `Gospelzora/public/` as its document root. Apache uses `public/.htaccess`; the repository-root `.htaccess` denies requests if the wrong root is configured. For nginx, adapt `deploy/nginx.conf.example`: set the domain, certificate paths, PHP-FPM socket, and upload limits. Keep PHP `upload_max_filesize` and `post_max_size` at 100M or higher. Never expose the repository root.
7. All tracked browser assets are already stored under `public/assets/`; there is no root `assets/` directory in this repository. If an older deployed copy still has a repository-root `assets/` directory, run `php deploy/migrate-public-assets.php` from the repository root to copy and verify those files. Back up the old media and inspect the result before optionally using `--move` to remove the legacy directory. New uploads are written under `public/assets/`.
8. Run the installer only when setting up a new environment. It is available at `/install.php`, with its implementation outside the document root. It checks for the complete required table set; do not treat it as a schema migration tool.

## Automated checks

GitHub Actions runs on pushes to branches and pull requests targeting `master`. The workflow:

- lints PHP files outside `vendor/`;
- runs the Router architecture smoke tests;
- tests trusted-proxy client IP handling;
- checks the canonical view layout and retired include directories;
- checks that the installer table list matches the schema and that the schema is non-destructive.

These checks do not replace database-backed integration tests, upload tests, or browser/end-to-end testing against a configured environment.

## Developer

Built and maintained by **Hiswill Kato**.

---

*Gospelzora — Transforming Lives Through Worship.*
