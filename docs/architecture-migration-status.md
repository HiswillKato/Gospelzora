# Architecture migration status

This branch is an incremental migration. The existing public URLs and legacy page scripts remain the compatibility boundary until each area has its own controller/view tests.

## Migrated

- Shared application foundation: environment loading, database adapter, request/response, router and exception handling.
- Namespaced domain models/services for users, music, analytics, settings, pages and mail.
- Homepage data preparation moved to `HomeController`; homepage markup moved to `app/Views/home/index.php`.
- JSON API behavior moved into `ApiController` for player, search, favorites, artist lookup, icons and playlists. Existing `api/*.php` entry points remain as thin compatibility adapters.
- CI lints PHP and runs architecture smoke checks.

## Still on the legacy page layer

- Authentication pages: login, registration, logout, password recovery/reset and email verification.
- Public catalogue/detail pages: music, artists, categories, charts, search, songs, playlists and static content.
- Member dashboard and admin pages.
- Upload workflows, scheduled/background work and analytics write paths.

These areas contain intertwined authorization, CSRF, session, upload, SQL and template behavior. They must be extracted in coherent units and tested against the existing database and browser flows; bulk-replacing them with generic wrappers would only disguise the old architecture and risks breaking security or existing URLs.

## Deployment boundary

- Do not deploy this branch directly to production yet.
- Do not run database migrations as part of this code-only refactor.
- Keep the existing rewrite rules and URL contracts until route parity is verified.
- Configure production secrets outside Git, use a least-privilege database account, and verify Nginx access restrictions before deployment.
- Run the full CI workflow and a staging smoke test with a copy of the production schema before merging.
