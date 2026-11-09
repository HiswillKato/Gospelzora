# Architecture migration status

The application now has its page implementations under `app/views/`. Legacy URL scripts remain thin compatibility entry points so existing rewrite rules and URLs continue to work while routing is migrated separately.

## View structure

- `app/views/shared/`: the actual public header, navigation and footer markup.
- `app/views/public/`: homepage and informational/legal pages.
- `app/views/music/`: catalogue, song detail and download-independent music page templates.
- `app/views/artists/`, `categories/`, `playlists/`, `charts/`, `search/`: feature page templates.
- `app/views/auth/`: login, registration, recovery, reset, verification and logout.
- `app/views/dashboard/`: member dashboard, profile, favorites, playlists, songs and requests.
- `app/views/admin/`: administration screens.
- Root `public/` (web document root) is distinct from `app/views/public/` (miscellaneous site pages).

## Migrated

- Actual page implementations have been moved from `pages/` and `dashboard/` into the corresponding view folders.
- Legacy page paths now act as compatibility adapters that include the migrated implementation.
- All shared markup, partials, admin/member shells and error templates live under `app/views/shared/`. There is no separate `app/includes/` directory.
- Homepage data preparation is in `app/controllers/Home.php`; homepage markup is in `app/views/public/home.php`.
- API behavior is in `app/controllers/Api.php`; existing `api/*.php` paths remain adapters.
- The `playlist_items` table name is reflected in the schema and model queries.

- `app/includes/` has been removed; all references now target the canonical `app/views/shared/` tree.

## Remaining architectural work

This is a **view/file migration**, not yet a full MVC extraction. Some migrated page files still contain request handling, authorization, form processing and data retrieval alongside their markup. The next refactor should move those responsibilities into feature controllers/services and leave the views presentation-only, one feature at a time.

## Safety and deployment

- Keep this branch separate from `master`; do not deploy it directly.
- No live database has been changed. Existing installations need a separate, data-preserving rename from `playlistitems` to `playlist_items`.
- Keep rewrite rules and URL contracts until route parity is verified.
- Test full browser flows and staging against a copy of the production schema before merging or deploying.
