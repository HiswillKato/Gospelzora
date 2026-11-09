# Gospelzora

**Transforming Lives Through Worship**

Gospelzora is a gospel music platform in active development, built to help listeners discover songs, explore artists, and engage with worship music.

## Current progress

The repository contains a substantial PHP application with public music pages, member features, and an administration dashboard. The project is beyond the initial planning stage; development is currently focused on improving structure and maintainability while continuing feature work.

### Implemented application areas

- **Music discovery:** home, music catalogue, song details, search, charts, categories, artists, and playlists.
- **Playback and music APIs:** player endpoints and APIs for songs, artists, search, playlists, and favorites.
- **Member features:** registration and login flows, email verification/password recovery pages, profiles, favorites, playlists, and song requests.
- **Administration:** dashboard and management pages for songs, artists, categories, users, admins, site pages, visits, downloads, logs, messages, and maintenance.
- **Reusable UI:** shared public, member, and admin layouts plus reusable partials (cards, alerts, pagination, empty states, and related components).
- **Informational pages:** About, Contact, Privacy, Terms, and Copyright.
- **Database:** MySQL/MariaDB schema and an installation entry point are included.
- **Frontend assets:** Bootstrap, custom styles and JavaScript, icons, fonts, and image assets.

### Ongoing work

- Organizing the application into clearer feature-oriented directories and separating routing/page entry points from view implementations.
- Migrating existing public pages and shared markup into the new structure incrementally; some views still delegate to legacy scripts/includes during the transition.
- Standardizing database naming (including `playlist_items`) and keeping application code aligned with the schema.
- Improving maintainability, consistency, and production readiness.

> Feature presence in the repository does not necessarily mean every flow has been fully tested or is production-ready. Deployment status and end-to-end test coverage are not currently documented here.

## Technology

- **PHP** — server-side application
- **MySQL / MariaDB** — relational database, accessed with MySQLi
- **Bootstrap 5**, HTML, CSS, and JavaScript — interface
- **nginx** — web server / local development environment
- **Composer** — dependency management and autoloading
- **PHPMailer** — email delivery

## Local setup

Gospelzora is developed in a Linux + nginx environment.

1. Clone the repository:
   ```bash
   git clone https://github.com/HiswillKato/Gospelzora.git
   ```
2. Configure nginx to serve the project and ensure the required URL rewriting rules are supported. The repository includes `.htaccess`; nginx does not process it natively, so configure equivalent nginx rules or the rewrite solution used by your environment.
3. Create a MySQL/MariaDB database and import `database.sql` (review it first for your environment).
4. Configure the database and application settings in `app/config.php`. Keep secrets and production credentials out of Git.
5. Install/refresh Composer dependencies if needed:
   ```bash
   composer install
   ```
6. Visit the configured local site. Review `install.php` and the current configuration before using them; setup requirements can change as development progresses.

## Project status

**Active development — not yet declared production-ready.**

Core public, member, and administrative areas exist. Refactoring and migration work is ongoing, and deployment, security review, and comprehensive end-to-end testing still need to be verified.

## Developer

Built and maintained by **Hiswill Kato**.

---

*Gospelzora — Transforming Lives Through Worship.*
