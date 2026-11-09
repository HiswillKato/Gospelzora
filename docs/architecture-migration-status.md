# Public document-root migration

The repository migration to a single public document root is complete.

## Request flow

- The web server document root is `public/`.
- `public/index.php` is the only application front controller.
- `routes/web.php` registers public, authentication, music, dashboard, administration and API endpoints.
- `app/core/Router.php` matches request paths and HTTP methods and returns 404/405 responses when appropriate.
- Request implementations remain in the canonical `app/controllers/`, `app/models/`, `app/services/` and `app/views/` trees.
- The installer entry point is `public/install.php`; its implementation remains at repository root in `install.php`.

## Canonical views

- `app/views/shared/`: shared public, member, admin, error and partial templates.
- `app/views/public/`: homepage and informational/legal pages.
- `app/views/music/`, `artists/`, `categories/`, `playlists/`, `charts/`, and `search/`: feature templates.
- `app/views/auth/`: login, registration, recovery, reset, verification and logout.
- `app/views/dashboard/`: member dashboard, profile, favorites, playlists, songs and requests.
- `app/views/admin/`: administration screens.

There is no root `pages/`, `dashboard/`, `api/`, or `assets/` directory. Those old URL families are handled by routes, and their implementations are kept outside the web root. Do not recreate the legacy entry-point directories.

## Public assets and uploads

All browser-accessible static files are stored under `public/assets/`: `css/`, `js/`, `images/`, `audio/`, `artwork/`, and `webfonts/`. The `fonts/`, `icons/`, and `uploads/` directories are reserved for additional font, icon and uploaded files. New uploads use the public asset paths configured in `app/config.php`.

The temporary asset fallback has been removed. For an existing deployment that still has a repository-root `assets/` directory, run `php deploy/migrate-public-assets.php` from the repository root to copy and verify it. Back up and inspect the media before optionally running the helper with `--move`.

## Deployment requirements

- Point Apache or nginx at the repository's `public/` directory, never the repository root.
- Apache rewrite rules are in `public/.htaccess`; nginx can use `deploy/nginx.conf.example`.
- Keep `.env`, `app/config.local.php`, Composer dependencies, database schema, installer implementation and tests outside the document root.
- Configure the PHP-FPM socket and domain in the nginx example for the target server.
- Run the installer once, verify its lock file is created, and remove `public/install.php` from production after installation.

## Scope boundary

This is a completed public-root and front-controller migration, not a claim that every page has been fully decomposed into thin MVC layers. Some feature views still contain legacy request handling and data operations. Move those responsibilities into feature controllers/services incrementally without changing route contracts or weakening authorization and CSRF checks.

No live server, production database, DNS, or uploaded media outside this Git repository is modified by this repository migration.
