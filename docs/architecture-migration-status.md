# Architecture migration status

The migration is incremental. Existing public URLs and legacy page scripts remain the compatibility boundary until each feature has been extracted and tested.

## Current structure

- Controllers: `app/controllers/` (PSR-4 namespace `App\\controllers`).
- Shared public shell: `app/views/shared/{header,navigation,footer}.php`.
- General public pages: `app/views/public/`, including `home.php` and temporary bridges for About, Contact and legal pages.
- Feature view directories: `music`, `artists`, `categories`, `playlists`, `charts`, `search`, `auth`, `dashboard` and `admin`.
- The web document root and the application views are separate concerns. The root-level public entry point is not the same as `app/views/public/`.

## Migrated

- Shared application foundation: environment loading, database adapter, request/response, router and exception handling.
- Namespaced domain models/services for users, music, analytics, settings, pages and mail.
- Homepage data preparation lives in `app/controllers/Home.php`; homepage markup lives in `app/views/public/home.php`.
- JSON API behavior lives in `app/controllers/Api.php` for player, search, favorites, artist lookup, icons and playlists. Existing `api/*.php` entry points remain thin compatibility adapters.
- CI lints PHP and runs architecture smoke checks.

## Still on the legacy page layer

Authentication pages; catalogue and detail pages; member dashboard; admin pages; upload workflows; and analytics write paths.
The informational-page view files are temporary bridges to their existing `pages/*.php` scripts. The shared shell files also delegate to the existing `app/includes/public/` markup while the extraction is completed.

These areas contain intertwined authorization, CSRF, session, upload, SQL and template behavior. Extract them in coherent units and test them against the existing database and browser flows; avoid generic wrappers that disguise the old architecture or risk breaking security and existing URLs.

## Deployment boundary

- Do not deploy this branch directly to production yet.
- Do not run database migrations as part of this code-only refactor.
- Keep existing rewrite rules and URL contracts until route parity is verified.
- Configure production secrets outside Git, use a least-privilege database account, and verify Nginx access restrictions before deployment.
- Run full CI and a staging smoke test with a copy of the production schema before merging.
