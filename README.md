# Gospelzora

**Transforming Lives Through Worship**

Gospelzora is a PHP/MySQL gospel music platform for discovering songs, exploring artists, streaming music, and managing listener and artist accounts.

## Application status

The repository contains the public music experience, member and artist areas, administration tools, authentication flows, and the supporting database schema. It remains in active development and is **not yet declared production-ready**.

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
- Existing `pages/` and `dashboard/` scripts remain as URL-compatible entry points while request routing is migrated incrementally. They should delegate to canonical views rather than duplicate page markup.
- `app/core/Router.php` is covered by smoke tests, but the current Apache rewrite rules still dispatch through the compatibility entry points. A complete front-controller migration is separate work.

## Technology

- **PHP 8.4+** — server-side application
- **MySQL / MariaDB** — relational database through MySQLi
- **Bootstrap 5**, HTML, CSS, and JavaScript — interface
- **Composer** — dependency management and autoloading
- **PHPMailer** — transactional email

## Local setup

Gospelzora is developed in a Linux environment with Apache rewrite rules or equivalent web-server routing.

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
6. Configure the web server to serve the project root and apply the repository's rewrite rules. nginx does not process `.htaccess`; configure equivalent rules when using nginx.
7. Run the installer only when setting up a new environment. It checks for the complete required table set; do not treat it as a schema migration tool.

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
