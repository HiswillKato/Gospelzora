# Gospelzora

**Transforming Lives Through Worship**

Gospelzora is a gospel music website I'm building to make it easier for people to discover and enjoy music that encourages faith and worship. The idea is simple: give gospel music a welcoming space where listeners can find songs and learn more about the artists behind them.

This project is also part of my journey as a developer. I'm building it step by step, learning from the challenges that come with turning an idea into a working website.

## What I'm building

Gospelzora is being developed around a few main areas:

- **Music discovery:** A place to browse gospel songs and explore artists.
- **Search:** A straightforward way to find music and related content.
- **Artist and music pages:** Clear information and artwork for the music featured on the site.
- **Administration:** Tools for managing the content shown to visitors.
- **Responsive design:** A layout that works well on phones and computers.

Some parts are still under development, so the available features may change as the project grows.

## Built with

- **PHP** for the website's server-side logic
- **MySQL / MariaDB** for storing data, accessed with MySQLi
- **Bootstrap 5** for the interface and responsive layout
- **HTML, CSS and JavaScript** for the frontend
- **nginx** for local development and URL rewriting

## Running it locally

I'm developing Gospelzora in a Linux-based nginx environment. To work with the project locally, you'll need PHP, a MySQL-compatible database and nginx.

1. Clone the repository:
   ```bash
   git clone https://github.com/HiswillKato/Gospelzora.git
   ```
2. Place the project in your nginx document root, or point an nginx server block to it.
3. Copy `.env.example` to `.env` and set the database, domain and mail values for your environment. Keep `.env` private and outside Git.
4. Routing lives in `.htaccess`, which nginx does not read natively — run the [`.htaccess`-for-nginx](https://github.com/e404/htaccess-for-nginx) Lua module (this project uses it) if the short routes must work.
5. Open the configured local address in your browser.

4. Install PHP dependencies with `composer install` and regenerate the autoloader with `composer dump-autoload`.

Database setup may require additional steps depending on the current version of the project. Never put credentials in tracked source files.

## Why Gospelzora?

Gospel music has a way of bringing people together, offering hope and reminding us of what matters. I wanted to build something that reflects that experience while giving me room to improve my web development skills.

My goal is to keep Gospelzora useful, easy to navigate and focused on the music and message rather than unnecessary complexity.

## Project status

**In development.** I'm still improving the design, organizing the code and working on the core features. This README will be updated as the website becomes more complete.

## Developer

Built and maintained by **Hiswill Kato**.

---

*Gospelzora — Transforming Lives Through Worship.*


## Application architecture

Gospelzora is being migrated incrementally toward the application foundation used in Martazi. Existing pages, dashboard routes, APIs and domain classes remain in place during this migration so the site's current behavior is not replaced all at once.

The new foundation includes:

- `app/core/`: database connection provider, request, response, router, environment loader and exception handler.
- `bootstrap/app.php`: environment, autoloading and application configuration bootstrap.
- `bootstrap/session.php`: session and security-header setup.
- `config/config.php`: structured application, database and mail configuration.
- `app/`: existing Gospelzora domain logic and presentation components.

The current Apache rewrite rules and legacy page entry points remain authoritative for routing until those routes are migrated and tested. The new PHP router is available for that later step; it is not yet the production request dispatcher.
